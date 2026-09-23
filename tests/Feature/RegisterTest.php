<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\AuthMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 会員登録（名前 → メールアドレス → 確認番号 → カギ）を確認する。
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_register_screen_starts_from_name(): void
    {
        $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('initialStep', 'name'));

        $register = $this->pageSource('Auth/Register');
        $this->assertStringContainsString('ありがとうございます！これで名前の登録は完了です。次の行程へ進めます。', $register);
        $this->assertStringContainsString('ありがとうございます！それでは次に進みます。', $register);
        $this->assertStringContainsString('ありがとうございます！これでメールアドレスの登録は完了です。それでは次に進めます。', $register);
        $this->assertStringContainsString('メールを入れ直す', $register);
        $this->assertPageLinksTo('Auth/Register', route('top'));
    }

    /** 最後まで進むと利用者ができ、ログインしてトップ画面へ進む */
    public function test_user_can_register_through_all_steps(): void
    {
        $this->post(route('register.name'), ['name' => '山田 花子'])->assertSessionHasNoErrors();
        $this->post(route('register.email'), ['email' => 'hanako@example.com'])->assertSessionHasNoErrors();
        $this->post(route('register.send-code'))->assertSessionHasNoErrors();

        $code = $this->sentCode('hanako@example.com');
        $this->post(route('register.verify'), ['code' => $code])->assertSessionHasNoErrors();

        $this->post(route('register.password'), ['password' => 'hanako123', 'password_confirmation' => 'hanako123'])
            ->assertRedirect(route('top'))
            ->assertSessionHas('dialog', 'ありがとうございます！これでカギも登録できました！それでは練習を始めましょう！');

        $user = User::where('email', 'hanako@example.com')->firstOrFail();
        $this->assertSame('山田 花子', $user->name);
        $this->assertTrue(Hash::check('hanako123', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    /** メールの文面が仕様どおりであること */
    public function test_verification_mail_has_code_url_and_expiry(): void
    {
        $this->startRegistration('hanako@example.com');

        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) {
            $this->assertMatchesRegularExpression('/\A\d{6}\z/', $mail->code);
            $this->assertStringStartsWith(url('/register/continue/'), $mail->url);

            $text = $mail->render();
            $this->assertStringContainsString('スマホの練習君です。', $text);
            $this->assertStringContainsString('確認番号は', $text);
            $this->assertStringContainsString($mail->code, $text);
            $this->assertStringContainsString('ここにあるURLを押していただくと元の画面に戻ります。', $text);
            $this->assertStringContainsString('元の画面に戻りましたらこの番号を入力してください。', $text);
            $this->assertStringContainsString($mail->url, $text);
            $this->assertStringContainsString('この番号は10分間有効です。', $text);

            return true;
        });
    }

    public function test_name_is_required(): void
    {
        $this->post(route('register.name'), ['name' => ''])
            ->assertSessionHasErrors(['name' => AuthMessages::NAME_REQUIRED]);

        // 全角の空白だけでも未入力とみなす
        $this->post(route('register.name'), ['name' => '　　'])
            ->assertSessionHasErrors(['name' => AuthMessages::NAME_REQUIRED]);
    }

    public function test_email_validation_messages(): void
    {
        $this->post(route('register.name'), ['name' => '山田']);

        $this->post(route('register.email'), ['email' => ''])
            ->assertSessionHasErrors(['email' => AuthMessages::EMAIL_REQUIRED]);

        $this->post(route('register.email'), ['email' => 'not-an-address'])
            ->assertSessionHasErrors(['email' => AuthMessages::EMAIL_INVALID]);

        // 改行を含むアドレスは使わせない
        $this->post(route('register.email'), ['email' => "a@example.com\r\nBcc: b@example.com"])
            ->assertSessionHasErrors(['email' => AuthMessages::EMAIL_INVALID]);

        User::factory()->create(['email' => 'taken@example.com']);
        $this->post(route('register.email'), ['email' => 'taken@example.com'])
            ->assertSessionHasErrors(['email' => AuthMessages::EMAIL_TAKEN]);
    }

    /** 名前を入れずにメールアドレスへ進もうとしたら、はじめからやり直してもらう */
    public function test_steps_cannot_be_skipped(): void
    {
        $this->post(route('register.email'), ['email' => 'hanako@example.com'])
            ->assertSessionHasErrors(['flow' => AuthMessages::FLOW_EXPIRED]);

        $this->post(route('register.name'), ['name' => '山田']);
        $this->post(route('register.email'), ['email' => 'hanako@example.com']);
        $this->post(route('register.password'), ['password' => 'hanako123', 'password_confirmation' => 'hanako123'])
            ->assertSessionHasErrors(['flow' => AuthMessages::FLOW_EXPIRED]);

        $this->assertDatabaseMissing('users', ['email' => 'hanako@example.com']);
    }

    public function test_code_validation_messages(): void
    {
        $this->startRegistration('hanako@example.com');

        $this->post(route('register.verify'), ['code' => ''])
            ->assertSessionHasErrors(['code' => AuthMessages::CODE_REQUIRED]);

        $this->post(route('register.verify'), ['code' => '12345'])
            ->assertSessionHasErrors(['code' => AuthMessages::CODE_MISMATCH]);

        $this->post(route('register.verify'), ['code' => $this->wrongCode('hanako@example.com')])
            ->assertSessionHasErrors(['code' => AuthMessages::CODE_MISMATCH]);
    }

    /** 全角の数字や空白が混ざっていても受け付ける */
    public function test_full_width_code_is_accepted(): void
    {
        $this->startRegistration('hanako@example.com');
        $code = $this->sentCode('hanako@example.com');

        $fullWidth = mb_convert_kana(substr($code, 0, 3).' '.substr($code, 3), 'N');
        $this->post(route('register.verify'), ['code' => $fullWidth])->assertSessionHasNoErrors();
    }

    /** 発行から10分たった番号は使えない */
    public function test_code_expires_after_ten_minutes(): void
    {
        $this->startRegistration('hanako@example.com');
        $code = $this->sentCode('hanako@example.com');

        $this->travel(10)->minutes();
        $this->travel(1)->seconds();

        $this->post(route('register.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => AuthMessages::CODE_EXPIRED]);
    }

    /** 新しい番号を発行したら、前の番号は使えない */
    public function test_old_code_is_invalid_after_reissue(): void
    {
        $this->startRegistration('hanako@example.com');
        $oldCode = $this->sentCode('hanako@example.com');

        $this->post(route('register.send-code'));
        $newCode = $this->sentCode('hanako@example.com');
        $this->assertSame(1, VerificationCode::where('email', 'hanako@example.com')->count());

        if ($oldCode !== $newCode) {
            $this->post(route('register.verify'), ['code' => $oldCode])
                ->assertSessionHasErrors(['code' => AuthMessages::CODE_MISMATCH]);
        }

        $this->post(route('register.verify'), ['code' => $newCode])->assertSessionHasNoErrors();
    }

    /** 5回まちがえた番号は、正しい番号を入れても使えない */
    public function test_code_is_locked_after_five_failures(): void
    {
        $this->startRegistration('hanako@example.com');
        $code = $this->sentCode('hanako@example.com');
        $wrong = $this->wrongCode('hanako@example.com');

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('register.verify'), ['code' => $wrong])
                ->assertSessionHasErrors(['code' => AuthMessages::CODE_MISMATCH]);
        }

        $this->post(route('register.verify'), ['code' => $wrong])
            ->assertSessionHasErrors(['code' => AuthMessages::CODE_LOCKED]);

        $this->post(route('register.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => AuthMessages::CODE_LOCKED]);
    }

    /** 短い間に何度もメールを送ろうとしたら、少し待ってもらう */
    public function test_sending_codes_is_throttled(): void
    {
        $this->startRegistration('hanako@example.com');

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('register.send-code'))->assertSessionHasNoErrors();
        }

        $this->post(route('register.send-code'))
            ->assertSessionHasErrors(['email' => AuthMessages::CODE_SEND_THROTTLED]);
    }

    public function test_password_validation_messages(): void
    {
        $this->startRegistration('hanako@example.com');
        $this->post(route('register.verify'), ['code' => $this->sentCode('hanako@example.com')]);

        $this->post(route('register.password'), ['password' => '', 'password_confirmation' => ''])
            ->assertSessionHasErrors(['password' => AuthMessages::PASSWORD_REQUIRED]);

        foreach (['short1', 'onlyletters', '12345678'] as $password) {
            $this->post(route('register.password'), ['password' => $password, 'password_confirmation' => $password])
                ->assertSessionHasErrors(['password' => AuthMessages::PASSWORD_RULE]);
        }

        $this->post(route('register.password'), ['password' => 'hanako123', 'password_confirmation' => 'hanako124'])
            ->assertSessionHasErrors(['password' => AuthMessages::PASSWORD_CONFIRMATION]);

        $this->assertGuest();
    }

    /** メールの URL を別のブラウザで開いても、確認番号の入力から続けられる */
    public function test_mail_link_resumes_from_code_input_in_another_browser(): void
    {
        $this->startRegistration('hanako@example.com');
        $code = $this->sentCode('hanako@example.com');
        $url = $this->sentUrl('hanako@example.com');

        // 別のブラウザ（途中経過を持っていない）で開いたことにする
        $this->flushSession();

        $this->get($url)->assertRedirect(route('register'));
        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->where('initialStep', 'verify')
            ->where('name', '山田')
            ->where('email', 'hanako@example.com'));

        $this->post(route('register.verify'), ['code' => $code])->assertSessionHasNoErrors();
        $this->post(route('register.password'), ['password' => 'hanako123', 'password_confirmation' => 'hanako123'])
            ->assertRedirect(route('top'));

        $this->assertDatabaseHas('users', ['email' => 'hanako@example.com', 'name' => '山田']);
    }

    /** メールの URL は1回しか使えない（同じブラウザで開き直すのはよい） */
    public function test_mail_link_can_be_used_only_once(): void
    {
        $this->startRegistration('hanako@example.com');
        $url = $this->sentUrl('hanako@example.com');

        $this->flushSession();
        $this->get($url)->assertSessionHas('resume_step', 'verify');

        // 同じブラウザで開き直した
        $this->get($url)->assertSessionHas('resume_step', 'verify');

        // 別のブラウザで開いた
        $this->flushSession();
        $this->get($url)
            ->assertRedirect(route('register'))
            ->assertSessionHas('dialog', AuthMessages::LINK_INVALID);
    }

    public function test_expired_mail_link_is_rejected(): void
    {
        $this->startRegistration('hanako@example.com');
        $url = $this->sentUrl('hanako@example.com');

        $this->travel(11)->minutes();
        $this->flushSession();

        $this->get($url)->assertSessionHas('dialog', AuthMessages::LINK_INVALID);
    }

    // ---------- 補助 ----------

    private function startRegistration(string $email): void
    {
        $this->post(route('register.name'), ['name' => '山田'])->assertSessionHasNoErrors();
        $this->post(route('register.email'), ['email' => $email])->assertSessionHasNoErrors();
        $this->post(route('register.send-code'))->assertSessionHasNoErrors();
    }

    /** 最後に送ったメールの確認番号 */
    private function sentCode(string $email): string
    {
        return $this->lastMail($email)->code;
    }

    private function sentUrl(string $email): string
    {
        return $this->lastMail($email)->url;
    }

    private function lastMail(string $email): VerificationCodeMail
    {
        $mails = Mail::sent(VerificationCodeMail::class, fn ($mail) => $mail->hasTo($email));
        $this->assertNotEmpty($mails);

        return $mails->last();
    }

    /** 送った番号とはちがう6桁 */
    private function wrongCode(string $email): string
    {
        $code = $this->sentCode($email);

        return $code === '000000' ? '111111' : '000000';
    }
}
