<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateRecommendations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RecommendationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->books()->wherePivot('status', 'read')->exists()) {
            return back()->with('error', 'Добави поне една прочетена книга, за да получиш препоръки.');
        }

        // Atomically claim the pending flag so double clicks queue only one job.
        if (! Cache::add(GenerateRecommendations::pendingKey($user->id), true, now()->addMinutes(10))) {
            return back()->with('status', 'Препоръките вече се генерират.');
        }

        Cache::forget(GenerateRecommendations::errorKey($user->id));

        GenerateRecommendations::dispatch($user);

        return back()->with('status', 'Генерирам препоръки… Обнови страницата след малко.');
    }
}
