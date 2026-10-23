<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $tooMany = static fn (Request $request, array $headers) => response()->json([
            'message' => 'Terlalu banyak percobaan. Tunggu sesuai Retry-After sebelum mencoba lagi.',
        ], 429, $headers);

        RateLimiter::for('login', function (Request $request) use ($tooMany) {
            $value = $request->input('email');
            $email = hash('sha256', is_string($value) ? mb_strtolower(trim($value)) : '');
            $ip = $request->ip();

            return [
                Limit::perMinute(20)->by('login-ip:'.$ip)->response($tooMany),
                Limit::perMinute(5)->by('login-pair:'.$ip.':'.$email)->response($tooMany),
                Limit::perMinutes(15, 15)->by('login-account:'.$email)->response($tooMany),
            ];
        });

        RateLimiter::for('registration', function (Request $request) use ($tooMany) {
            return [
                Limit::perMinute(2)->by('register-minute:'.$request->ip())->response($tooMany),
                Limit::perHour(5)->by('register-hour:'.$request->ip())->response($tooMany),
            ];
        });
    }
}
