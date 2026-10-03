<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            /** @var GoogleUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')
                ->withErrors(['email' => 'Входът с Google не бе успешен. Опитай отново.']);
        }

        if (! $googleUser->getEmail() || ($googleUser->user['email_verified'] ?? false) !== true) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google акаунтът няма потвърден имейл.']);
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            // Link to an existing account only because Google verified the email.
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getEmail(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
