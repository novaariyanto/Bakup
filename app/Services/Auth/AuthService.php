<?php

namespace App\Services\Auth;

use App\Services\BaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService extends BaseService
{
    public function login(string $email, string $password, bool $remember, string $ipAddress): void
    {
        $this->ensureIsNotRateLimited($email, $ipAddress);

        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            RateLimiter::hit($this->throttleKey($email, $ipAddress));

            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($email, $ipAddress));
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
    }

    protected function ensureIsNotRateLimited(string $email, string $ipAddress): void
    {
        $key = $this->throttleKey($email, $ipAddress);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    protected function throttleKey(string $email, string $ipAddress): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ipAddress);
    }
}
