<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Support\AuthMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * カギを忘れたときの作り直し（メールアドレス → 確認番号 → カギ）を確認する。
 */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_screen_is_displayed(): void
    {
        $this->get(route('password.forgot'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ForgotPassword')
            ->where('initialStep', 'email'));

        $source = $this->pageSource('Auth/ForgotPassword');
        $this->assertStringContainsString('登録されているのが見つかりました！それでは新しいカギも作り直しましょう。', $source);
        $this->assertPageLinksTo('Auth/ForgotPassword', route('login'));
    }

    /** 作り直せたら、そのままログインしてトップ画面へ進む */
    public function test_user_can_reset_password_and_is_logged_in(): void
    {
        $user = User::factory()->create(['name' => '山田', 'email' => 'yamada@example.com']);

        $this->post(route('password.forgot.email'), ['email' => 'yamada@example.com'])->assertSessionHasNoErrors();
        $this->post(route('password.forgot.send-code'))->assertSessionHasNoErrors();

        $mail = Mail::sent(VerificationCodeMail::class)->last();
        $this->assertStringStartsWith(url('/forgot-password/continue/'), $mail->url);

        $this->post(route('password.forgot.verify'), ['code' => $mail->code])->assertSessionHasNoErrors();
        $this->post(route('password.forgot.password'), ['password' => 'newkey123', 'password_confirmation' => 'newkey123'])
            ->assertRedirect(route('top'))
            ->assertSessionHas('dialog', 'ありがとうございます！お帰りなさい山田さん！ではまた練習を始めましょう！');

        $this->assertTrue(Hash::check('newkey123', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_email_is_rejected(): void
    {
        $this->post(route('password.forgot.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors(['email' => AuthMessages::EMAIL_NOT_FOUND]);

        $this->post(route('password.forgot.email'), ['email' => ''])
            ->assertSessionHasErrors(['email' => AuthMessages::EMAIL_REQUIRED]);

        $this->post(route('password.forgot.email'), ['email' => 'not-an-address'])
            ->assertSessionHasErrors(['email' => AuthMessages::EMAIL_INVALID]);

        Mail::assertNothingSent();
    }

    /** 番号を確かめる前に、カギを作り直すことはできない */
    public function test_password_cannot_be_reset_without_code(): void
    {
        $user = User::factory()->create(['email' => 'yamada@example.com']);

        $this->post(route('password.forgot.email'), ['email' => 'yamada@example.com']);
        $this->post(route('password.forgot.send-code'));

        $this->post(route('password.forgot.password'), ['password' => 'newkey123', 'password_confirmation' => 'newkey123'])
            ->assertSessionHasErrors(['flow' => AuthMessages::FLOW_EXPIRED]);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertGuest();
    }

    /** 会員登録の番号は、カギの作り直しには使えない */
    public function test_register_code_cannot_be_used_for_reset(): void
    {
        User::factory()->create(['email' => 'yamada@example.com']);

        $this->post(route('password.forgot.email'), ['email' => 'yamada@example.com']);
        $this->post(route('password.forgot.send-code'));
        $resetCode = Mail::sent(VerificationCodeMail::class)->last()->code;

        // 同じ番号が会員登録の確認に使えないこと（用途ごとに分けている）
        $this->flushSession();
        $this->post(route('register.name'), ['name' => '山田']);
        $this->post(route('register.email'), ['email' => 'other@example.com']);
        $this->post(route('register.verify'), ['code' => $resetCode])
            ->assertSessionHasErrors('code');
    }
}
