<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuthMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * トップ画面・ログイン・ログインしていないときの扱いを確認する。
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** トップ画面は、ログイン前とログイン後で同じ画面を使い、出すボタンを切り替える */
    public function test_top_screen_switches_buttons_by_login_state(): void
    {
        $this->get(route('top'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Top')
            ->where('auth.user', null));

        $this->actingAs(User::factory()->create(['name' => '山田']));
        $this->get(route('top'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Top')
            ->where('auth.user.name', '山田'));

        $top = $this->pageSource('Top');
        $this->assertStringContainsString('スマホの練習君', $top);
        $this->assertPageLinksTo('Top', route('register'));
        $this->assertPageLinksTo('Top', route('login'));
        $this->assertPageLinksTo('Top', route('practice.menu'));
        $this->assertPageLinksTo('Top', route('growth.index'));
        foreach (['初めての方はこちら', '使ったことがある方はこちら', '早速練習をする', '成長の記録を見る'] as $label) {
            $this->assertStringContainsString($label, $top);
        }
    }

    /** ログインしていない人は、練習・成長の記録を開けず、トップ画面へ戻される */
    public function test_guests_are_redirected_to_top(): void
    {
        $urls = [
            route('practice.menu'),
            route('practice.contents'),
            route('practice.details'),
            route('practice.difficulty', ['type' => 'tap']),
            route('practice.play', ['type' => 'tap', 'level' => 'easy']),
            route('practice.typing.difficulty'),
            route('practice.typing.play', ['level' => 'easy']),
            route('growth.index'),
            route('growth.history'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('top'));
        }

        $this->postJson(route('practice-records.store'), [])->assertUnauthorized();
    }

    /** ログインしている人が登録・ログインの画面を開いたら、トップ画面へ戻す */
    public function test_logged_in_users_cannot_open_guest_screens(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('login'))->assertRedirect('/');
        $this->get(route('register'))->assertRedirect('/');
        $this->get(route('password.forgot'))->assertRedirect('/');
    }

    public function test_login_screen_is_displayed(): void
    {
        $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));

        $login = $this->pageSource('Auth/Login');
        $this->assertStringContainsString('カギを忘れました！', $login);
        $this->assertStringContainsString('このアプリを使っていたかどうか調べるので、メールアドレスを教えてください。', $login);
        $this->assertPageLinksTo('Auth/Login', route('top'));
    }

    /** ログインできたら、お帰りなさいのダイアログを出してトップ画面へ進む */
    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['name' => '山田', 'email' => 'yamada@example.com']);

        $this->post('/login', ['email' => 'yamada@example.com', 'password' => 'password'])
            ->assertRedirect(route('top'))
            ->assertSessionHas('dialog', 'お帰りなさい！山田さん。それでは練習を始めましょう！');

        $this->assertAuthenticatedAs($user);

        $this->get(route('top'))->assertInertia(fn (Assert $page) => $page
            ->where('flash.dialog', 'お帰りなさい！山田さん。それでは練習を始めましょう！'));
    }

    /** 全角で入れたメールアドレスでもログインできる */
    public function test_full_width_email_is_accepted_on_login(): void
    {
        User::factory()->create(['email' => 'yamada@example.com']);

        $this->post('/login', ['email' => 'ｙａｍａｄａ＠ｅｘａｍｐｌｅ．ｃｏｍ', 'password' => 'password'])
            ->assertRedirect(route('top'));

        $this->assertAuthenticated();
    }

    public function test_login_requires_both_fields(): void
    {
        $this->post('/login', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors([
                'email' => AuthMessages::LOGIN_REQUIRED,
                'password' => AuthMessages::LOGIN_REQUIRED,
            ]);

        $this->assertGuest();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'yamada@example.com']);

        $this->post('/login', ['email' => 'yamada@example.com', 'password' => 'wrong-password1'])
            ->assertSessionHasErrors([
                'email' => 'どうやら違うようです。前に入れたカギとパスワードを思い出してみましょう！',
            ]);

        $this->assertGuest();
    }

    /** 何度もまちがえたら、少し待ってもらう */
    public function test_login_is_throttled_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'yamada@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'yamada@example.com', 'password' => 'wrong-password1']);
        }

        $response = $this->post('/login', ['email' => 'yamada@example.com', 'password' => 'password']);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('少しお休みしましょう', session('errors')->first('email'));
        $this->assertGuest();
    }

    /** 自動ログアウト：最後の操作から12時間でセッションが切れる設定になっていること */
    public function test_session_expires_twelve_hours_after_last_activity(): void
    {
        $this->assertSame(720, (int) config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
    }

    /** セッションが切れたあとに送信したら、エラー画面ではなくトップ画面へ戻して理由を伝える */
    public function test_expired_session_on_submit_returns_to_top(): void
    {
        Route::middleware('web')->post('/_test/csrf', fn () => 'ok');

        // テストでは CSRF の確認が止まるため、ここだけ有効にする
        $this->app['env'] = 'local';
        $this->withMiddleware()
            ->post('/_test/csrf', [], ['X-Inertia' => 'true'])
            ->assertRedirect(route('top'))
            ->assertSessionHas('dialog', AuthMessages::SESSION_EXPIRED);
    }
}
