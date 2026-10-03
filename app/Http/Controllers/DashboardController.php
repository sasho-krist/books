<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateRecommendations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'recommendations' => $user->recommendations()->latest('id')->get(),
            'pending' => Cache::has(GenerateRecommendations::pendingKey($user->id)),
            'error' => Cache::get(GenerateRecommendations::errorKey($user->id)),
            'readCount' => $user->books()->wherePivot('status', 'read')->count(),
        ]);
    }
}
