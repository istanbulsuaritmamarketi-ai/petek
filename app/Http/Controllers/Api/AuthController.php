<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function formatUser(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name ?? $user->nickname,
            'full_name' => $user->name ?? $user->nickname,
            'email' => $user->email,
            'nickname' => $user->nickname,
            'department' => $user->department ?? 'Bilinmiyor',
            'faculty' => $user->faculty ?? 'Bilinmiyor',
            'role' => $user->role ?? 'student',
            'account_type' => $user->account_type ?? 'personal',
            'badges' => $user->badges ?? ['yeni_arı'],
            'bee_points' => $user->bee_points ?? 10,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'post_count' => $user->post_count ?? $user->posts()->count(),
            'focus_minutes' => $user->focus_minutes ?? 0,
            'follower_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'created_at' => $user->created_at->toISOString(),
        ];
    }

    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'nickname' => 'required|string|max:50|unique:users,nickname',
            'password' => 'required|string|min:6',
            'department' => 'nullable|string',
            'faculty' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $request->nickname,
            'nickname' => $request->nickname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'department' => $request->department,
            'faculty' => $request->faculty,
            'role' => 'student',
            'account_type' => 'personal',
            'badges' => ['yeni_arı'],
            'bee_points' => 10,
            'post_count' => 0,
            'focus_minutes' => 0,
        ]);

        $token = $user->createToken('petek_mobile_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => $this->formatUser($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        // Login by email or nickname
        $user = User::where('email', $request->email)
            ->orWhere('nickname', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'detail' => 'Hatalı e-posta veya şifre'
            ], 401);
        }

        if ($user->role === 'banned') {
            return response()->json([
                'detail' => 'Hesabınız askıya alınmıştır'
            ], 403);
        }

        if ($user->is_frozen) {
            $user->is_frozen = false;
            $user->save();
        }

        $token = $user->createToken('petek_mobile_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => $this->formatUser($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if ($request->filled('nickname') && $request->nickname !== $user->nickname) {
            if (User::where('nickname', $request->nickname)->where('id', '!=', $user->id)->exists()) {
                return response()->json(['detail' => 'Bu rumuz kullanılıyor'], 400);
            }
            $user->nickname = trim($request->nickname);
        }

        if ($request->filled('name') || $request->filled('full_name')) {
            $user->name = trim($request->input('name', $request->input('full_name')));
        }

        if ($request->filled('department')) {
            $user->department = trim($request->department);
        }

        if ($request->filled('faculty')) {
            $user->faculty = trim($request->faculty);
        }

        if ($request->has('avatar')) {
            $user->avatar = $request->avatar;
        }

        if ($request->has('bio')) {
            $user->bio = trim($request->bio);
        }

        $user->save();

        return response()->json($this->formatUser($user));
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $user = $request->user();
        $user->location_lat = $request->lat;
        $user->location_lng = $request->lng;
        $user->location_updated_at = now();
        $user->save();

        return response()->json(['message' => 'Konum güncellendi']);
    }

    public function toggleFollow(Request $request, $userId)
    {
        $user = $request->user();
        if ((string)$user->id === (string)$userId) {
            return response()->json(['detail' => 'Kendinizi takip edemezsiniz'], 400);
        }

        $target = User::findOrFail($userId);

        if ($user->following()->where('following_id', $target->id)->exists()) {
            $user->following()->detach($target->id);
            return response()->json(['message' => 'Takipten çıkıldı', 'following' => false]);
        } else {
            $user->following()->attach($target->id);
            return response()->json(['message' => 'Takip edildi', 'following' => true]);
        }
    }

    public function searchUsers(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (empty($q)) {
            $users = User::where('role', '!=', 'banned')
                ->latest()
                ->limit(20)
                ->get();
        } else {
            $users = User::where('role', '!=', 'banned')
                ->where(function ($query) use ($q) {
                    $query->where('nickname', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('department', 'like', "%{$q}%")
                        ->orWhere('faculty', 'like', "%{$q}%");
                })
                ->limit(20)
                ->get();
        }

        $currentUser = auth('sanctum')->user() ?? $request->user();

        return response()->json($users->map(function ($u) use ($currentUser) {
            return [
                'id' => (string) $u->id,
                'nickname' => $u->nickname,
                'name' => $u->name,
                'department' => $u->department ?? 'İTÜ Öğrencisi',
                'faculty' => $u->faculty ?? '',
                'avatar' => $u->avatar,
                'bee_points' => $u->bee_points ?? 0,
                'is_following' => $currentUser ? $currentUser->following()->where('following_id', $u->id)->exists() : false,
            ];
        }));
    }

    public function getUserProfile($userId, Request $request)
    {
        $user = User::findOrFail($userId);
        $data = $this->formatUser($user);
        $currentUser = auth('sanctum')->user() ?? $request->user();
        $data['is_following'] = $currentUser ? $currentUser->following()->where('following_id', $user->id)->exists() : false;
        $data['is_blocked'] = $currentUser ? $currentUser->blockedUsers()->where('blocked_id', $user->id)->exists() : false;
        return response()->json($data);
    }

    public function getUserPosts($userId, Request $request)
    {
        $skip = (int) $request->get('skip', 0);
        $limit = (int) $request->get('limit', 20);
        $isOwner = $request->user() && (int) $request->user()->id === (int) $userId;

        $query = Post::with(['media', 'likes', 'comments'])
            ->where('user_id', $userId)
            ->where('is_archived', false);

        if (!$isOwner) {
            $query->where('is_anonymous', false);
        }

        $posts = $query->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return response()->json($posts->map(function ($p) {
            return [
                'id' => (string) $p->id,
                'content' => $p->content,
                'image' => $p->media->first()?->url,
                'media' => $p->media->map(fn($m) => ['type' => $m->type, 'url' => $m->url]),
                'like_count' => $p->likes->count(),
                'comment_count' => $p->comments->count(),
                'view_count' => $p->view_count,
                'created_at' => $p->created_at->toISOString(),
            ];
        }));
    }

    public function getUserArchivedPosts($userId, Request $request)
    {
        $currentUser = $request->user();
        if (!$currentUser || (string)$currentUser->id !== (string)$userId) {
            return response()->json(['detail' => 'Arşivlenen gönderileri yalnızca hesap sahibi görüntüleyebilir.'], 403);
        }

        $skip = (int) $request->get('skip', 0);
        $limit = (int) $request->get('limit', 20);

        $posts = Post::with(['media', 'likes', 'comments'])
            ->where('user_id', $userId)
            ->where('is_archived', true)
            ->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return response()->json($posts->map(function ($p) {
            return [
                'id' => (string) $p->id,
                'content' => $p->content,
                'image' => $p->media->first()?->url,
                'media' => $p->media->map(fn($m) => ['type' => $m->type, 'url' => $m->url]),
                'like_count' => $p->likes->count(),
                'comment_count' => $p->comments->count(),
                'view_count' => $p->view_count,
                'created_at' => $p->created_at->toISOString(),
            ];
        }));
    }

    public function getUserLikedPosts($userId, Request $request)
    {
        $currentUser = $request->user();
        if (!$currentUser || (string)$currentUser->id !== (string)$userId) {
            return response()->json(['detail' => 'Beğenilen gönderileri yalnızca hesap sahibi görüntüleyebilir.'], 403);
        }

        $skip = (int) $request->get('skip', 0);
        $limit = (int) $request->get('limit', 20);

        $user = User::findOrFail($userId);
        $posts = $user->likedPosts()
            ->with(['media', 'likes', 'comments'])
            ->where('is_archived', false)
            ->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return response()->json($posts->map(function ($p) {
            return [
                'id' => (string) $p->id,
                'content' => $p->content,
                'image' => $p->media->first()?->url,
                'media' => $p->media->map(fn($m) => ['type' => $m->type, 'url' => $m->url]),
                'like_count' => $p->likes->count(),
                'comment_count' => $p->comments->count(),
                'view_count' => $p->view_count,
                'created_at' => $p->created_at->toISOString(),
            ];
        }));
    }

    public function getUserReplies($userId, Request $request)
    {
        $currentUser = $request->user();
        if (!$currentUser || (string)$currentUser->id !== (string)$userId) {
            return response()->json(['detail' => 'Yanıtları yalnızca hesap sahibi görüntüleyebilir.'], 403);
        }

        $limit = (int) $request->get('limit', 20);

        $comments = Comment::with('post')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        return response()->json($comments->map(function ($c) {
            return [
                'post_id' => (string) $c->post_id,
                'post_content' => mb_substr($c->post?->content ?? '', 0, 30) . '...',
                'comment_id' => (string) $c->id,
                'comment_content' => $c->content,
                'created_at' => $c->created_at->toISOString(),
            ];
        }));
    }

    public function freezeAccount(Request $request)
    {
        $user = $request->user();
        $user->is_frozen = true;
        $user->tokens()->delete();
        $user->save();

        return response()->json([
            'message' => 'Hesabınız başarıyla donduruldu. Tekrar giriş yaparak istediğiniz an aktif edebilirsiniz.',
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->posts()->delete();
        $user->comments()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Hesabınız ve tüm verileriniz kalıcı olarak silindi.',
        ]);
    }

    public function getFollowers($userId)
    {
        $user = User::findOrFail($userId);
        $followers = $user->followers()
            ->select('users.id', 'users.nickname', 'users.department', 'users.faculty', 'users.avatar', 'users.bee_points')
            ->get();

        return response()->json($followers->map(fn($u) => [
            'id' => (string) $u->id,
            'nickname' => $u->nickname,
            'department' => $u->department,
            'faculty' => $u->faculty,
            'avatar' => $u->avatar,
            'bee_points' => (int) $u->bee_points,
        ]));
    }

    public function getFollowing($userId)
    {
        $user = User::findOrFail($userId);
        $following = $user->following()
            ->select('users.id', 'users.nickname', 'users.department', 'users.faculty', 'users.avatar', 'users.bee_points')
            ->get();

        return response()->json($following->map(fn($u) => [
            'id' => (string) $u->id,
            'nickname' => $u->nickname,
            'department' => $u->department,
            'faculty' => $u->faculty,
            'avatar' => $u->avatar,
            'bee_points' => (int) $u->bee_points,
        ]));
    }

    public function toggleBlock($userId, Request $request)
    {
        $currentUser = $request->user();
        if ((string)$currentUser->id === (string)$userId) {
            return response()->json(['detail' => 'Kendinizi engelleyemezsiniz.'], 400);
        }

        $targetUser = User::findOrFail($userId);
        $isBlocked = $currentUser->blockedUsers()->where('blocked_id', $targetUser->id)->exists();

        if ($isBlocked) {
            $currentUser->blockedUsers()->detach($targetUser->id);
            return response()->json([
                'blocked' => false,
                'message' => 'Kullanıcının engeli kaldırıldı.',
            ]);
        } else {
            $currentUser->blockedUsers()->attach($targetUser->id);
            // Also unfollow if following
            $currentUser->following()->detach($targetUser->id);
            $targetUser->following()->detach($currentUser->id);
            return response()->json([
                'blocked' => true,
                'message' => 'Kullanıcı engellendi. Artık içeriklerini ve mesajlarını görmeyeceksiniz.',
            ]);
        }
    }

    public function getBlockedUsers(Request $request)
    {
        $currentUser = $request->user();
        $blocked = $currentUser->blockedUsers()
            ->select('users.id', 'users.nickname', 'users.department', 'users.avatar')
            ->get();

        return response()->json($blocked->map(fn($u) => [
            'id' => (string) $u->id,
            'nickname' => $u->nickname,
            'department' => $u->department,
            'avatar' => $u->avatar,
        ]));
    }
}
