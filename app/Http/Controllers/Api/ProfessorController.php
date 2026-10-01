<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Professor;
use App\Models\ProfessorRating;
use Illuminate\Http\Request;

class ProfessorController extends Controller
{
    private function formatProfessor(Professor $prof): array
    {
        return [
            'id' => (string) $prof->id,
            'name' => $prof->name,
            'department' => $prof->department,
            'avg_attendance' => $prof->avg_attendance,
            'avg_difficulty' => $prof->avg_difficulty,
            'avg_teaching' => $prof->avg_teaching,
            'rating_count' => $prof->rating_count,
        ];
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $department = $request->get('department');

        $query = Professor::with('ratings');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($department) {
            $query->where('department', $department);
        }

        $professors = $query->get();

        return response()->json($professors->map(fn($p) => $this->formatProfessor($p))->values());
    }

    public function show($id)
    {
        $prof = Professor::with(['ratings.user'])->findOrFail($id);
        $formatted = $this->formatProfessor($prof);

        $formatted['ratings'] = $prof->ratings->map(function ($r) {
            return [
                'user_id' => (string) $r->user_id,
                'user_nickname' => $r->user?->nickname ?? 'Öğrenci',
                'attendance' => $r->attendance,
                'difficulty' => $r->difficulty,
                'teaching' => $r->teaching,
                'comment' => $r->comment,
                'created_at' => $r->created_at->toISOString(),
            ];
        });

        return response()->json($formatted);
    }

    public function rate(Request $request, $id)
    {
        $user = $request->user();

        // RBAC: Teachers cannot rate other professors
        if ($user->role === 'teacher') {
            return response()->json(['detail' => 'Akademisyenler hoca değerlendiremez'], 403);
        }

        $request->validate([
            'attendance' => 'required|integer|min:1|max:5',
            'difficulty' => 'required|integer|min:1|max:5',
            'teaching' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $prof = Professor::findOrFail($id);

        ProfessorRating::updateOrCreate(
            [
                'professor_id' => $prof->id,
                'user_id' => $user->id,
            ],
            [
                'attendance' => $request->attendance,
                'difficulty' => $request->difficulty,
                'teaching' => $request->teaching,
                'comment' => $request->comment,
            ]
        );

        $prof->load('ratings');

        return response()->json([
            'message' => 'Değerlendirmeniz kaydedildi',
            'professor' => $this->formatProfessor($prof),
        ]);
    }
}
