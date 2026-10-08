<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClubController extends Controller
{
    private function formatUser(User $user): array
    {
        $isAdmin = $user->role === 'admin' || in_array((int) $user->id, [1, 3]);

        return [
            'id' => (string) $user->id,
            'name' => $user->name ?? $user->nickname,
            'full_name' => $user->name ?? $user->nickname,
            'email' => $user->email,
            'nickname' => $user->nickname,
            'department' => $user->department ?? 'İTÜ Kulübü',
            'faculty' => $user->faculty ?? 'Öğrenci Kulüpleri',
            'role' => $user->role ?? 'student',
            'account_type' => $user->account_type ?? 'personal',
            'club_name' => $user->club_name,
            'club_description' => $user->club_description,
            'club_category' => $user->club_category,
            'owner_id' => $user->owner_id ? (string) $user->owner_id : null,
            'course_schedule' => $user->course_schedule ?: [],
            'badges' => $user->badges ?? ($user->account_type === 'club' ? ['kulup'] : ['yeni_arı']),
            'bee_points' => $user->bee_points ?? 10,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'post_count' => $user->post_count ?? $user->posts()->count(),
            'focus_minutes' => $user->focus_minutes ?? 0,
            'follower_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'is_email_verified' => true,
            'is_on_campus' => $user->isOnCampus(),
            'created_at' => $user->created_at->toISOString(),
        ];
    }

    public function create(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'club_name' => 'required|string|max:100',
            'nickname' => 'required|string|max:40|regex:/^[a-zA-Z0-9_]+$/|unique:users,nickname',
            'club_category' => 'required|string|max:100',
            'club_description' => 'nullable|string|max:1000',
            'avatar' => 'nullable|string',
            'bio' => 'nullable|string|max:300',
        ], [
            'nickname.regex' => 'Kulüp kullanıcı adı yalnızca harf, rakam ve alt çizgi içerebilir.',
            'nickname.unique' => 'Bu kullanıcı adı başka bir hesap tarafından kullanılıyor.',
        ]);

        $nickname = strtolower(trim($request->nickname));
        $email = $nickname . '@club.itu.edu.tr';

        // Check email uniqueness fallback
        if (User::where('email', $email)->exists()) {
            $email = $nickname . '_' . Str::random(4) . '@club.itu.edu.tr';
        }

        $avatar = $request->avatar;
        if (!empty($avatar) && str_starts_with($avatar, 'data:image')) {
            try {
                $cloudinary = app(CloudinaryService::class);
                $avatar = $cloudinary->uploadBase64($avatar, 'petek_avatars');
            } catch (\Throwable $e) {
                Log::warning('Cloudinary avatar upload failed for club: ' . $e->getMessage());
            }
        }

        $club = User::create([
            'name' => trim($request->club_name),
            'nickname' => $nickname,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'account_type' => 'club',
            'role' => 'club_manager',
            'owner_id' => $user->account_type === 'club' ? $user->owner_id : $user->id,
            'club_name' => trim($request->club_name),
            'club_description' => $request->club_description,
            'club_category' => $request->club_category,
            'bio' => $request->bio ?: $request->club_description,
            'department' => 'İTÜ Kulübü',
            'faculty' => $request->club_category,
            'avatar' => $avatar,
            'email_verified_at' => now(),
            'badges' => ['kulup'],
            'bee_points' => 50,
            'share_campus_status' => true,
        ]);

        return response()->json([
            'message' => "{$club->club_name} hesabı başarıyla oluşturuldu! Artık bu hesaba geçiş yapabilirsin.",
            'club' => $this->formatUser($club),
        ], 201);
    }

    public function getMyManagedClubs(Request $request)
    {
        $user = $request->user();

        // Determine owner ID (if current user is a club account, the owner is owner_id)
        $ownerId = $user->account_type === 'club' ? $user->owner_id : $user->id;

        $clubs = User::where('owner_id', $ownerId)
            ->where('account_type', 'club')
            ->orderBy('club_name', 'asc')
            ->get();

        $ownerUser = $ownerId ? User::find($ownerId) : null;

        return response()->json([
            'current_user_id' => (string) $user->id,
            'is_current_club' => $user->account_type === 'club',
            'owner' => $ownerUser ? $this->formatUser($ownerUser) : null,
            'clubs' => $clubs->map(fn($c) => $this->formatUser($c))->values(),
        ]);
    }

    public function switchAccount(Request $request, $id)
    {
        $currentUser = $request->user();
        $targetId = (int) $id;

        $targetUser = User::findOrFail($targetId);

        // Security check:
        // 1. Is target the owner of current club account?
        $isSwitchingToOwner = ($currentUser->account_type === 'club' && $currentUser->owner_id === $targetUser->id);

        // 2. Is target a club owned by current user (or owned by the same owner)?
        $currentOwnerId = $currentUser->account_type === 'club' ? $currentUser->owner_id : $currentUser->id;
        $isSwitchingToManagedClub = ($targetUser->account_type === 'club' && $targetUser->owner_id === $currentOwnerId);

        // 3. Is current user Super Admin?
        $isAdmin = in_array((int) $currentUser->id, [1, 3]) || $currentUser->role === 'admin';

        if (!$isSwitchingToOwner && !$isSwitchingToManagedClub && !$isAdmin) {
            return response()->json(['detail' => 'Bu hesaba geçiş yapma yetkiniz bulunmuyor.'], 403);
        }

        // Generate new Sanctum token for target account
        $token = $targetUser->createToken('petek-switch-token')->plainTextToken;

        return response()->json([
            'message' => "{$targetUser->name} hesabına başarıyla geçiş yapıldı.",
            'token' => $token,
            'user' => $this->formatUser($targetUser),
        ]);
    }

    public function transferOwnership(Request $request, $id)
    {
        $currentUser = $request->user();
        $club = User::where('id', $id)->where('account_type', 'club')->firstOrFail();

        $currentOwnerId = $currentUser->account_type === 'club' ? $currentUser->owner_id : $currentUser->id;
        $isAdmin = in_array((int) $currentUser->id, [1, 3]) || $currentUser->role === 'admin';

        if ($club->owner_id !== $currentOwnerId && !$isAdmin) {
            return response()->json(['detail' => 'Bu kulüp hesabını devretme yetkiniz yok.'], 403);
        }

        $request->validate([
            'target_identity' => 'required|string', // nickname, email, or user id
        ]);

        $query = trim($request->target_identity);
        $targetUser = User::where('id', $query)
            ->orWhere('nickname', $query)
            ->orWhere('email', $query)
            ->first();

        if (!$targetUser) {
            return response()->json(['detail' => 'Devredilecek öğrenci hesabı bulunamadı.'], 404);
        }

        if ($targetUser->account_type === 'club') {
            return response()->json(['detail' => 'Bir kulüp hesabı başka bir kulübe devredilemez. Gerçek bir öğrenci hesabı seçin.'], 422);
        }

        if ($targetUser->id === $club->owner_id) {
            return response()->json(['detail' => 'Kulüp zaten bu öğrenciye ait.'], 422);
        }

        $club->owner_id = $targetUser->id;
        $club->save();

        return response()->json([
            'message' => "{$club->club_name} yöneticiliği başarıyla {$targetUser->nickname} ({$targetUser->name}) öğrencisine devredildi.",
            'club' => $this->formatUser($club),
        ]);
    }

    public function updateClubSettings(Request $request, $id)
    {
        $currentUser = $request->user();
        $club = User::where('id', $id)->where('account_type', 'club')->firstOrFail();

        $currentOwnerId = $currentUser->account_type === 'club' ? $currentUser->owner_id : $currentUser->id;
        $isAdmin = in_array((int) $currentUser->id, [1, 3]) || $currentUser->role === 'admin';

        if ($club->owner_id !== $currentOwnerId && $currentUser->id !== $club->id && !$isAdmin) {
            return response()->json(['detail' => 'Yetkisiz erişim.'], 403);
        }

        $request->validate([
            'club_name' => 'nullable|string|max:100',
            'club_category' => 'nullable|string|max:100',
            'club_description' => 'nullable|string|max:1000',
            'bio' => 'nullable|string|max:300',
            'avatar' => 'nullable|string',
        ]);

        if ($request->has('club_name')) {
            $club->name = trim($request->club_name);
            $club->club_name = trim($request->club_name);
        }
        if ($request->has('club_category')) {
            $club->club_category = trim($request->club_category);
            $club->faculty = trim($request->club_category);
        }
        if ($request->has('club_description')) {
            $club->club_description = $request->club_description;
        }
        if ($request->has('bio')) {
            $club->bio = $request->bio;
        }
        if ($request->has('avatar') && !empty($request->avatar)) {
            $avatar = $request->avatar;
            if (str_starts_with($avatar, 'data:image')) {
                try {
                    $cloudinary = app(CloudinaryService::class);
                    $avatar = $cloudinary->uploadBase64($avatar, 'petek_avatars');
                } catch (\Throwable $e) {}
            }
            $club->avatar = $avatar;
        }

        $club->save();

        return response()->json([
            'message' => 'Kulüp ayarları başarıyla güncellendi.',
            'club' => $this->formatUser($club),
        ]);
    }

    public function listClubs(Request $request)
    {
        $query = User::where('account_type', 'club');

        if ($request->filled('q')) {
            $search = trim($request->q);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nickname', 'like', "%{$search}%")
                  ->orWhere('club_description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('club_category', $request->category);
        }

        $clubs = $query->orderBy('name', 'asc')->get();

        return response()->json($clubs->map(fn($c) => $this->formatUser($c)));
    }

    // --- SCHEDULE & WIDGET ENDPOINTS ---

    public function getSchedule(Request $request)
    {
        $user = $request->user();
        return response()->json($user->course_schedule ?: []);
    }

    public function saveSchedule(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'courses' => 'required|array',
        ]);

        $user->course_schedule = $request->courses;
        $user->save();

        return response()->json([
            'message' => 'Ders programınız başarıyla güncellendi.',
            'schedule' => $user->course_schedule,
        ]);
    }
}
