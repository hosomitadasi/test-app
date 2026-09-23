<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NameRequest;
use App\Http\Requests\Auth\PasswordRequest;
use App\Http\Requests\Auth\RegisterEmailRequest;
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
 * 会員登録。名前 → メールアドレス → 確認番号 → カギ の順に1つの画面の中で進む。
 *
 * 利用者は、最後にカギを登録した時点で初めて作る。
 */
class RegisterController extends Controller
{
    use ManagesFlowState;

    public function __construct(private readonly VerificationCodeService $codes)
    {
    }

    protected function sessionKey(): string
    {
        return 'registration';
    }

    public function show(Request $request): Response
    {
        $state = $this->flowState($request);

        return Inertia::render('Auth/Register', [
            // メールの URL から来たときは、確認番号の入力から始める
            'initialStep' => $request->session()->get('resume_step', 'name'),
            'name' => $state['name'] ?? '',
            'email' => $state['email'] ?? '',
        ]);
    }

    public function storeName(NameRequest $request): RedirectResponse
    {
        $this->putFlowState($request, ['name' => $request->validated('name')]);

        return back();
    }

    public function storeEmail(RegisterEmailRequest $request): RedirectResponse
    {
        $state = $this->requireFlowState($request, ['name']);

        $this->putFlowState($request, [
            'name' => $state['name'],
            'email' => $request->validated('email'),
            'verified' => false,
        ]);

        return back();
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $state = $this->requireFlowState($request, ['name', 'email']);

        $this->codes->issue($state['email'], VerificationCode::PURPOSE_REGISTER, $state['name']);

        return back();
    }

    public function verify(VerificationCodeRequest $request): RedirectResponse
    {
        $state = $this->requireFlowState($request, ['name', 'email']);

        $this->codes->verify($state['email'], VerificationCode::PURPOSE_REGISTER, $request->validated('code'));

        $this->putFlowState($request, ['verified' => true] + $state);

        return back();
    }

    public function storePassword(PasswordRequest $request, CreateNewUser $creator): RedirectResponse
    {
        $state = $this->requireFlowState($request, ['name', 'email', 'verified']);

        if (User::where('email', $state['email'])->exists()) {
            // 登録の途中で、同じアドレスが別に登録されたとき
            throw ValidationException::withMessages(['password' => AuthMessages::EMAIL_TAKEN]);
        }

        $user = $creator->create([
            'name' => $state['name'],
            'email' => $state['email'],
            'password' => $request->validated('password'),
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget($this->sessionKey());

        return redirect()->route('top')->with('dialog', AuthMessages::registered());
    }

    /**
     * メールの URL から戻ってきたとき。どの端末・ブラウザで開いても、確認番号の入力から続けられる。
     */
    public function continue(Request $request, string $token): RedirectResponse
    {
        $record = $this->codes->consumeLink(
            $token,
            VerificationCode::PURPOSE_REGISTER,
            $this->flowState($request)['email'] ?? null
        );

        if ($record === null) {
            return redirect()->route('register')->with('dialog', AuthMessages::LINK_INVALID);
        }

        $this->putFlowState($request, [
            'name' => $record->name,
            'email' => $record->email,
            'verified' => false,
        ]);

        return redirect()->route('register')->with('resume_step', 'verify');
    }
}
