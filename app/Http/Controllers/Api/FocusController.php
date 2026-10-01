<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FocusSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FocusController extends Controller
{
    public function active(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response('null', 200, ['Content-Type' => 'application/json']);
        }

        $session = FocusSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$session) {
            return response('null', 200, ['Content-Type' => 'application/json']);
        }

        $minutes = max(0, (int) now()->diffInMinutes($session->started_at));

        return response()->json([
            'id' => (string) $session->id,
            'user_id' => (string) $session->user_id,
            'zone_id' => $session->zone_id ? (string) $session->zone_id : null,
            'started_at' => $session->started_at->toISOString(),
            'end_time' => null,
            'minutes' => $minutes,
            'course_code' => $session->course_code,
            'is_active' => true,
            'failed' => false,
        ]);
    }

    public function start(Request $request)
    {
        $user = $request->user();

        $existing = FocusSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return response()->json([
                'id' => (string) $existing->id,
                'session_id' => (string) $existing->id,
                'user_id' => (string) $existing->user_id,
                'started_at' => $existing->started_at->toISOString(),
                'is_active' => true,
            ]);
        }

        $rawCourse = $request->input('course_code');
        $cleanCourse = $rawCourse ? trim(preg_replace('/\s+/', '', mb_strtoupper($rawCourse, 'UTF-8'))) : null;

        $session = FocusSession::create([
            'user_id' => $user->id,
            'zone_id' => $request->input('zone_id'),
            'course_code' => $cleanCourse,
            'started_at' => now(),
            'ended_at' => null,
            'duration_minutes' => 0,
            'is_active' => true,
            'failed' => false,
        ]);

        return response()->json([
            'id' => (string) $session->id,
            'session_id' => (string) $session->id,
            'user_id' => (string) $session->user_id,
            'course_code' => $session->course_code,
            'started_at' => $session->started_at->toISOString(),
            'is_active' => true,
        ], 201);
    }

    public function end(Request $request, $id = null)
    {
        $user = $request->user();

        $query = FocusSession::where('user_id', $user->id)->where('is_active', true);
        if ($id) {
            $session = $query->where('id', $id)->first();
        } else {
            $session = $query->latest()->first();
        }

        if (!$session) {
            return response()->json(['detail' => 'Aktif oturum bulunamadı'], 404);
        }

        $minutes = max(1, (int) now()->diffInMinutes($session->started_at));
        $session->ended_at = now();
        $session->duration_minutes = $minutes;
        $session->is_active = false;
        $session->failed = false;
        $session->save();

        $points = max(1, intdiv($minutes, 5));
        $user->increment('focus_minutes', $minutes);
        $user->increment('bee_points', $points);

        return response()->json([
            'message' => "{$minutes} dakika çalıştınız!",
            'minutes' => $minutes,
            'points' => $points,
            'total_focus_minutes' => $user->focus_minutes,
            'bee_points' => $user->bee_points,
        ]);
    }

    public function fail(Request $request)
    {
        $user = $request->user();
        $session = FocusSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if ($session) {
            $session->ended_at = now();
            $session->is_active = false;
            $session->failed = true;
            $session->save();
        }

        return response()->json(['message' => 'Odaklanma oturumu iptal edildi']);
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        $period = $request->get('period', 'weekly');

        $startDate = match ($period) {
            'daily' => now()->subDays(7),
            'monthly' => now()->subDays(90),
            default => now()->subWeeks(4),
        };

        $sessions = FocusSession::where('user_id', $user->id)
            ->where('is_active', false)
            ->where('failed', false)
            ->where('started_at', '>=', $startDate)
            ->orderBy('started_at')
            ->get();

        $daysMap = [
            'Mon' => 'Pzt', 'Tue' => 'Sal', 'Wed' => 'Çar', 'Thu' => 'Per',
            'Fri' => 'Cum', 'Sat' => 'Cmt', 'Sun' => 'Paz'
        ];

        $grouped = [];
        foreach ($sessions as $s) {
            $label = $daysMap[$s->started_at->format('D')] ?? $s->started_at->format('d M');
            $grouped[$label] = ($grouped[$label] ?? 0) + $s->duration_minutes;
        }

        $chartData = [];
        foreach ($grouped as $label => $minutes) {
            $chartData[] = ['label' => $label, 'minutes' => $minutes];
        }

        $allCompleted = FocusSession::where('user_id', $user->id)
            ->where('is_active', false)
            ->where('failed', false)
            ->get();

        $groupedCourses = [];
        foreach ($allCompleted as $sess) {
            $raw = $sess->course_code;
            $code = $raw ? trim(preg_replace('/\s+/', '', mb_strtoupper($raw, 'UTF-8'))) : 'GENEL';
            if (empty($code)) $code = 'GENEL';
            if (!isset($groupedCourses[$code])) {
                $groupedCourses[$code] = [
                    'course' => $code,
                    'minutes' => 0,
                    'sessions' => 0,
                ];
            }
            $groupedCourses[$code]['minutes'] += (int) $sess->duration_minutes;
            $groupedCourses[$code]['sessions'] += 1;
        }

        usort($groupedCourses, fn($a, $b) => $b['minutes'] <=> $a['minutes']);
        $courseStats = array_values($groupedCourses);

        return response()->json([
            'period' => $period,
            'chart_data' => $chartData,
            'data' => $chartData,
            'courses' => $courseStats,
        ]);
    }

    public function leaderboard(Request $request)
    {
        $type = $request->get('type', 'faculty');

        if ($type === 'students' || $type === 'student') {
            $students = User::where('is_frozen', false)
                ->orderBy('focus_minutes', 'desc')
                ->orderBy('bee_points', 'desc')
                ->limit(30)
                ->get();

            return response()->json($students->map(function ($u, $index) {
                return [
                    'rank' => $index + 1,
                    'id' => (string) $u->id,
                    'nickname' => $u->nickname ?: $u->name,
                    'avatar' => $u->avatar,
                    'department' => $u->department ?: 'İTÜ',
                    'faculty' => $u->faculty ?: 'İTÜ',
                    'total_focus_minutes' => (int) $u->focus_minutes,
                    'bee_points' => (int) $u->bee_points,
                ];
            })->values());
        }

        $rankings = DB::table('users')
            ->whereNotNull('faculty')
            ->where('faculty', '!=', '')
            ->where('is_frozen', false)
            ->select(
                'faculty',
                DB::raw('SUM(focus_minutes) as total_minutes'),
                DB::raw('COUNT(id) as student_count')
            )
            ->groupBy('faculty')
            ->orderBy('total_minutes', 'desc')
            ->limit(20)
            ->get();

        return response()->json($rankings->map(function ($r, $index) {
            return [
                'rank' => $index + 1,
                'faculty_name' => $r->faculty,
                'faculty' => $r->faculty,
                'total_focus_minutes' => (int) $r->total_minutes,
                'total_minutes' => (int) $r->total_minutes,
                'member_count' => (int) $r->student_count,
                'student_count' => (int) $r->student_count,
            ];
        }));
    }

    public function activeWorkers(Request $request)
    {
        $user = auth('sanctum')->user();

        $query = FocusSession::with(['user:id,nickname,avatar,department'])
            ->where('is_active', true)
            ->whereNotNull('user_id');

        if ($user) {
            $query->where('user_id', '!=', $user->id);
        }

        $sessions = $query->latest()->limit(20)->get();

        return response()->json($sessions->map(function ($s) {
            $minutes = max(0, (int) now()->diffInMinutes($s->started_at));
            return [
                'id' => $s->id,
                'user' => $s->user,
                'course_code' => $s->course_code,
                'started_at' => $s->started_at ? $s->started_at->toISOString() : null,
                'current_minutes' => $minutes,
            ];
        }));
    }
}

