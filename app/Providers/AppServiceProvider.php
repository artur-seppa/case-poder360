<?php

namespace App\Providers;

use App\Contracts\RefreshTokenRepositoryInterface;
use App\Contracts\TaskRepositoryInterface;
use App\Repositories\RefreshTokenRepository;
use App\Repositories\TaskRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
        $this->app->bind(RefreshTokenRepositoryInterface::class, RefreshTokenRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        // Same max "stay logged in without a password" window as the API's refresh
        // token (default 400 days is too long for a stolen cookie to stay valid,
        // and this token doesn't rotate on use the way the API refresh token does).
        Auth::guard('web')->setRememberDuration(
            (int) config('auth.refresh_token_ttl_days') * 24 * 60
        );

        if (class_exists(\Knuckles\Scribe\Scribe::class)) {
            \Knuckles\Scribe\Scribe::beforeResponseCall(function (\Symfony\Component\HttpFoundation\Request $request, \Knuckles\Camel\Extraction\ExtractedEndpointData $endpointData) {
                $user = \App\Models\User::where('email', 'demo@example.com')->first();

                if ($user) {
                    $token = $user->createToken('scribe-docs')->plainTextToken;
                    $request->headers->set('Authorization', "Bearer {$token}");
                }
            });
        }
    }
}
