<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\VerificationCode;
use App\Support\AuthMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * メールで送る確認番号（6桁）の発行と照合。
 *
 * - 発行から10分たった番号は使えない
 * - 新しい番号を発行したら、同じメールアドレス・同じ用途の古い番号は使えない
 * - 5回まちがえた番号は使えない
 */
class VerificationCodeService
{
    /** 番号の有効期間（分） */
    public const TTL_MINUTES = 10;

    /** まちがえてもよい回数。これに達した番号は使えなくする。 */
    public const MAX_ATTEMPTS = 5;

    /** 同じメールアドレスへ、10分の間に送ってよい通数 */
    private const SEND_LIMIT = 5;
    private const SEND_LIMIT_DECAY_SECONDS = 600;

    /** 用途ごとの、メールの URL に使うルート */
    private const CONTINUE_ROUTES = [
        VerificationCode::PURPOSE_REGISTER => 'register.continue',
        VerificationCode::PURPOSE_PASSWORD_RESET => 'password.forgot.continue',
    ];

    /**
     * 新しい番号を発行してメールで送る。前に発行した番号は使えなくなる。
     *
     * @throws ValidationException 短い間に何度も送ろうとしたとき
     */
    public function issue(string $email, string $purpose, ?string $name = null): void
    {
        $limiterKey = 'verification-send:'.$purpose.':'.Str::lower($email);
        if (RateLimiter::tooManyAttempts($limiterKey, self::SEND_LIMIT)) {
            throw ValidationException::withMessages(['email' => AuthMessages::CODE_SEND_THROTTLED]);
        }
        RateLimiter::hit($limiterKey, self::SEND_LIMIT_DECAY_SECONDS);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $linkToken = Str::random(64);

        DB::transaction(function () use ($email, $purpose, $name, $code, $linkToken) {
            // 前に発行した番号は消して、使えなくする
            VerificationCode::where('email', $email)->where('purpose', $purpose)->delete();

            VerificationCode::create([
                'email' => $email,
                'purpose' => $purpose,
                'name' => $name,
                'code_hash' => Hash::make($code),
                'link_token_hash' => hash('sha256', $linkToken),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);
        });

        $url = route(self::CONTINUE_ROUTES[$purpose], ['token' => $linkToken]);
        Mail::to($email)->send(new VerificationCodeMail($code, $url, self::TTL_MINUTES));
    }

    /**
     * 入力された番号を確かめる。合っていれば、その番号は使用済みにする。
     *
     * @throws ValidationException 番号がちがう・期限切れ・使えなくなっているとき
     */
    public function verify(string $email, string $purpose, string $code): void
    {
        $record = VerificationCode::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if ($record === null) {
            // 番号が見つからない（新しい番号の発行で消えた、など）場合は、送り直してもらう
            throw ValidationException::withMessages(['code' => AuthMessages::CODE_EXPIRED]);
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => AuthMessages::CODE_LOCKED]);
        }

        if ($record->isExpired()) {
            throw ValidationException::withMessages(['code' => AuthMessages::CODE_EXPIRED]);
        }

        if (!Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            throw ValidationException::withMessages([
                'code' => $record->attempts >= self::MAX_ATTEMPTS
                    ? AuthMessages::CODE_LOCKED
                    : AuthMessages::CODE_MISMATCH,
            ]);
        }

        $record->forceFill(['verified_at' => now()])->save();
    }

    /**
     * メールの URL から、まだ使える番号を探す。
     *
     * 見つかった番号の URL は使用済みにする（1回しか使えない）。
     * ただし、同じ端末で開き直した場合は続きから進めるよう、
     * 使用済みでも $alreadyInSessionFor と同じアドレスならそのまま返す。
     */
    public function consumeLink(string $token, string $purpose, ?string $alreadyInSessionFor): ?VerificationCode
    {
        $record = VerificationCode::where('link_token_hash', hash('sha256', $token))
            ->where('purpose', $purpose)
            ->first();

        if ($record === null
            || $record->verified_at !== null
            || $record->isExpired()
            || $record->attempts >= self::MAX_ATTEMPTS) {
            return null;
        }

        if ($record->link_used_at !== null) {
            return $record->email === $alreadyInSessionFor ? $record : null;
        }

        $record->forceFill(['link_used_at' => now()])->save();

        return $record;
    }
}
