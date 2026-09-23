<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordEmailRequest;
use App\Http\Requests\Auth\PasswordRequest;
use App\Http\Requests\Auth\VerificationCodeRequest;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\VerificationCodeService;
use App\Support\AuthMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * カギ（パスワード）を忘れたときの作り直し。
 * メールアドレス → 確認番号 → 新しいカギ の順に1つの画面の中で進み、終わったらそのままログインする。
 */
class ForgotPasswordController extends Controller
{
    use ManagesFlowState;

    public function __construct(private readonly VerificationCodeService $codes)
    {
    }

    protected function sessionKey(): string
    {
        return 'password_reset';
    }

    public function show(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'initialStep' => $request->session()->get('resume_step', 'email'),
            'email' => $this->flowState($request)['email'] ?? '',
        ]);
    }

    public function storeEmail(ForgotPasswordEmailRequest $request): RedirectResponse
    {
        $this->putFlowState($request, [
            'email' => $request->validated('email'),
            'verified' => false,
        ]);

        return back();
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $state = $this->requireFlowState($request, ['email']);

        $this->codes->issue($state['email'], VerificationCode::PURPOSE_PASSWORD_RESET);

        return back();
    }

    public function verify(VerificationCodeRequest $request): RedirectResponse
    {
        $state = $this->requireFlowState($request, ['email']);

        $this->codes->verify($state['email'], VerificationCode::PURPOSE_PASSWORD_RESET, $request->validated('code'));

        $this->putFlowState($request, ['verified' => true] + $state);

        return back();
    }

    public function storePassword(PasswordRequest $request, ResetUserPassword $resetter): RedirectResponse
    {
        $state = $this->requireFlowState($request, ['email', 'verified']);

        $user = User::where('email', $state['email'])->first();
        if ($user === null) {
            throw ValidationException::withMessages(['flow' => AuthMessages::FLOW_EXPIRED]);
        }

        $resetter->reset($user, ['password' => $request->validated('password')]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget($this->sessionKey());

        return redirect()->route('top')->with('dialog', AuthMessages::passwordReset($user->name));
    }

    /**
     * メールの URL から戻ってきたとき。どの端末・ブラウザで開いても、確認番号の入力から続けられる。
     */
    public function continue(Request $request, string $token): RedirectResponse
    {
        $record = $this->codes->consumeLink(
            $token,
            VerificationCode::PURPOSE_PASSWORD_RESET,
            $this->flowState($request)['email'] ?? null
        );

        if ($record === null) {
            return redirect()->route('password.forgot')->with('dialog', AuthMessages::LINK_INVALID);
        }

        $this->putFlowState($request, ['email' => $record->email, 'verified' => false]);

        return redirect()->route('password.forgot')->with('resume_step', 'verify');
    }
}
