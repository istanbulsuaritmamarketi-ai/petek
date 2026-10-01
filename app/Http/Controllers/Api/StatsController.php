<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function countdown()
    {
        // ITU Academic Calendar Finals: Mid-June or January depending on semester
        $now = now();
        $year = $now->year;
        
        // If before June 15, target is June 15 this year; else next January 15
        if ($now->month < 6 || ($now->month === 6 && $now->day <= 15)) {
            $finals = \Carbon\Carbon::create($year, 6, 15, 0, 0, 0);
        } elseif ($now->month < 12) {
            $finals = \Carbon\Carbon::create($year + 1, 1, 15, 0, 0, 0);
        } else {
            $finals = \Carbon\Carbon::create($year + 1, 1, 15, 0, 0, 0);
        }

        $days = max(0, (int) $now->diffInDays($finals, false));

        return response()->json([
            'finals_date' => $finals->toISOString(),
            'days_left' => $days,
        ]);
    }

    public function adminStats()
    {
        return response()->json([
            'total_users' => \App\Models\User::count(),
            'total_posts' => \App\Models\Post::count(),
            'total_zones' => \App\Models\Zone::count(),
            'total_focus_minutes' => (int) \App\Models\User::sum('focus_minutes'),
            'total_reports' => \Illuminate\Support\Facades\DB::table('reports')->count(),
        ]);
    }
}
