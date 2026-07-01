<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthService $authService): RedirectResponse
    {
        $authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->boolean('remember'),
            $request->ip(),
        );

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
