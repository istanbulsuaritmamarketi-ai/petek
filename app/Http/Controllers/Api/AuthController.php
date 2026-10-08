<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\EmailVerificationCode;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function sanitizeAndFormatAvatar(User $user): ?string
    {
        $raw = $user->getRawOriginal('avatar');
        if (empty($raw)) {
            return null;
        }

        // Clean HTTP / HTTPS URL
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://') || str_starts_with($raw, '/uploads/') || str_starts_with($raw, 'uploads/')) {
            return $raw;
        }

        // Base64 data URI
        if (str_starts_with($raw, 'data:image') || str_starts_with($raw, 'data:application')) {
            $commaPos = strpos($raw, ',');
            if ($commaPos === false) {
                $user->avatar = null;
                $user->saveQuietly();
                return null;
            }

            $decoded = base64_decode(substr($raw, $commaPos + 1), true);
            if ($decoded === false || strlen($decoded) < 100 || (function_exists('getimagesizefromstring') && !@getimagesizefromstring($decoded))) {
                // Image corrupted (e.g. truncated at 1MB like user 5) - heal profile by clearing corrupted data
                $user->avatar = null;
                $user->saveQuietly();
                return null;
            }

            // Valid base64: save to local file so we don't send 1MB of JSON in every API response!
            try {
                $imageInfo = @getimagesizefromstring($decoded);
                $ext = 'jpg';
                if (!empty($imageInfo['mime'])) {
                    if (str_contains($imageInfo['mime'], 'png')) $ext = 'png';
                    elseif (str_contains($imageInfo['mime'], 'webp')) $ext = 'webp';
                }
                $filename = 'avatar_' . $user->id . '_' . time() . '_' . Str::random(4) . '.' . $ext;
                $uploadDir = public_path('uploads/avatars');
                if (!file_exists($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                file_put_contents($uploadDir . '/' . $filename, $decoded);
                $fileUrl = url('uploads/avatars/' . $filename);

                $user->avatar = $fileUrl;
                $user->saveQuietly();
                return $fileUrl;
            } catch (\Throwable $e) {
                return $raw;
            }
        }

        return $raw;
    }

    private function formatUser(User $user): array
    {
        $isAdmin = $user->role === 'admin' || in_array((int) $user->id, [1, 3]);

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
            'avatar' => $this->sanitizeAndFormatAvatar($user),
            'bio' => $user->bio,
            'post_count' => $user->post_count ?? $user->posts()->count(),
            'focus_minutes' => $user->focus_minutes ?? 0,
            'follower_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'share_campus_status' => (bool) ($user->share_campus_status ?? true),
            'is_email_verified' => $isAdmin || !is_null($user->email_verified_at),
            'email_verified_at' => $user->email_verified_at?->toISOString() ?? ($isAdmin ? now()->toISOString() : null),
            'is_on_campus' => $user->isOnCampus(),
            'campus_status' => $user->getCampusStatus(),
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

        // Otomatik 6 haneli doğrulama PIN kodunu üret ve e-postaya gönder
        $dispatchResult = $this->dispatchVerificationCode($user);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => $this->formatUser($user),
            'requires_verification' => true,
            'message' => 'Kayıt başarılı! 6 haneli doğrulama kodu ' . $user->email . ' adresinize gönderildi.',
            'debug_code' => $dispatchResult['debug_code'] ?? null,
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
        $isAdmin = $user->role === 'admin' || in_array((int) $user->id, [1, 3]);
        $isVerified = $isAdmin || !is_null($user->email_verified_at);

        // Eğer kullanıcı henüz doğrulanmamışsa ve aktif kodu yoksa otomatik yeni kod gönder
        $debugCode = null;
        if (!$isVerified) {
            $hasActiveCode = EmailVerificationCode::where('email', $user->email)
                ->where('expires_at', '>', now())
                ->exists();
            if (!$hasActiveCode) {
                $dispatch = $this->dispatchVerificationCode($user);
                $debugCode = $dispatch['debug_code'] ?? null;
            }
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => $this->formatUser($user),
            'requires_verification' => !$isVerified,
            'debug_code' => $debugCode,
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

        if ($request->hasFile('avatar') || $request->hasFile('photo') || $request->hasFile('image') || $request->hasFile('file')) {
            $file = $request->file('avatar') ?: ($request->file('photo') ?: ($request->file('image') ?: $request->file('file')));
            $user->avatar = $this->storeAvatarFile($file, $user->id);
        } elseif ($request->has('avatar') || $request->has('photo') || $request->has('image')) {
            $avatarInput = $request->input('avatar') ?: ($request->input('photo') ?: $request->input('image'));
            if (empty($avatarInput)) {
                $user->avatar = null;
            } elseif (str_starts_with($avatarInput, 'data:image') || str_starts_with($avatarInput, 'data:application')) {
                $user->avatar = $this->storeAvatarBase64($avatarInput, $user->id);
            } else {
                $user->avatar = $avatarInput;
            }
        }

        if ($request->has('bio')) {
            $user->bio = trim($request->bio);
        }

        if ($request->has('share_campus_status')) {
            $user->share_campus_status = filter_var($request->input('share_campus_status'), FILTER_VALIDATE_BOOLEAN);
        }

        $user->save();

        return response()->json($this->formatUser($user));
    }

    public function uploadAvatar(Request $request)
    {
        $user = $request->user();

        if ($request->hasFile('avatar') || $request->hasFile('photo') || $request->hasFile('image') || $request->hasFile('file')) {
            $file = $request->file('avatar') ?: ($request->file('photo') ?: ($request->file('image') ?: $request->file('file')));
            $user->avatar = $this->storeAvatarFile($file, $user->id);
        } elseif ($request->has('avatar') || $request->has('photo') || $request->has('image')) {
            $avatarInput = $request->input('avatar') ?: ($request->input('photo') ?: $request->input('image'));
            if (empty($avatarInput)) {
                $user->avatar = null;
            } elseif (str_starts_with($avatarInput, 'data:image') || str_starts_with($avatarInput, 'data:application')) {
                $user->avatar = $this->storeAvatarBase64($avatarInput, $user->id);
            } else {
                $user->avatar = $avatarInput;
            }
        }

        $user->save();

        return response()->json($this->formatUser($user));
    }

    private function storeAvatarFile($file, $userId): string
    {
        try {
            $cloudinary = app(CloudinaryService::class);
            return $cloudinary->uploadMedia($file, 'petek_avatars');
        } catch (\Throwable $e) {
            Log::warning('Cloudinary avatar upload failed, using local storage: ' . $e->getMessage());
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'heic', 'heif', 'avif', 'bmp'])) {
                $ext = 'jpg';
            }
            $filename = 'avatar_' . $userId . '_' . time() . '_' . Str::random(6) . '.' . $ext;
            $uploadDir = public_path('uploads/avatars');
            if (!file_exists($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $file->move($uploadDir, $filename);
            return url('uploads/avatars/' . $filename);
        }
    }

    private function storeAvatarBase64(string $base64Data, $userId): string
    {
        $commaPos = strpos($base64Data, ',');
        $contentData = $commaPos !== false ? base64_decode(substr($base64Data, $commaPos + 1), true) : false;

        if ($contentData === false || strlen($contentData) < 100) {
            throw ValidationException::withMessages(['avatar' => ['Geçersiz veya bozuk görsel verisi.']]);
        }

        $imgInfo = function_exists('getimagesizefromstring') ? @getimagesizefromstring($contentData) : null;
        if (function_exists('getimagesizefromstring') && !$imgInfo) {
            throw ValidationException::withMessages(['avatar' => ['Yüklenen görsel formatı okunamadı veya dosya bozuk.']]);
        }

        try {
            $cloudinary = app(CloudinaryService::class);
            return $cloudinary->uploadBase64($base64Data, 'petek_avatars');
        } catch (\Throwable $e) {
            Log::warning('Cloudinary base64 avatar upload failed, using local storage: ' . $e->getMessage());
            $ext = 'jpg';
            if ($imgInfo && !empty($imgInfo['mime'])) {
                if (str_contains($imgInfo['mime'], 'png')) $ext = 'png';
                elseif (str_contains($imgInfo['mime'], 'webp')) $ext = 'webp';
                elseif (str_contains($imgInfo['mime'], 'gif')) $ext = 'gif';
            } elseif (preg_match('/^data:(?:image|application)\/([a-zA-Z0-9\+\-\.]+);base64,/', $base64Data, $match)) {
                $rawMime = strtolower($match[1]);
                if (str_contains($rawMime, 'png')) $ext = 'png';
                elseif (str_contains($rawMime, 'webp')) $ext = 'webp';
                elseif (str_contains($rawMime, 'gif')) $ext = 'gif';
                elseif (str_contains($rawMime, 'svg')) $ext = 'svg';
                elseif (str_contains($rawMime, 'heic')) $ext = 'heic';
                elseif (str_contains($rawMime, 'heif')) $ext = 'heif';
                elseif (str_contains($rawMime, 'avif')) $ext = 'avif';
                elseif (str_contains($rawMime, 'bmp')) $ext = 'bmp';
                else $ext = 'jpg';
            }

            $filename = 'avatar_' . $userId . '_' . time() . '_' . Str::random(6) . '.' . $ext;
            $uploadDir = public_path('uploads/avatars');
            if (!file_exists($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            file_put_contents($uploadDir . '/' . $filename, $contentData);
            return url('uploads/avatars/' . $filename);
        }
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

    private function formatUserPost(Post $p): array
    {
        $mediaList = $p->media->map(fn($m) => [
            'type' => $m->type,
            'media_type' => $m->type,
            'url' => $m->url,
            'file_url' => $m->url,
            'image_size' => $m->thumbnail ?? 'instagram',
        ])->values()->toArray();

        return [
            'id' => (string) $p->id,
            'content' => $p->content,
            'image' => !empty($mediaList) ? $mediaList[0]['url'] : null,
            'image_size' => !empty($mediaList) ? ($mediaList[0]['image_size'] ?? 'instagram') : 'instagram',
            'media' => $mediaList,
            'post_type' => $p->post_type ?? 'general',
            'type' => $p->post_type ?? 'general',
            'like_count' => $p->likes->count(),
            'comment_count' => $p->comments->count(),
            'view_count' => $p->view_count,
            'visibility' => $p->visibility ?? 'public',
            'is_friends_only' => ($p->visibility ?? 'public') === 'friends',
            'created_at' => $p->created_at->toISOString(),
        ];
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

            $currentUser = $request->user();
            $isAdmin = $currentUser && $currentUser->role === 'admin';
            $isFriend = $currentUser && DB::table('user_follows')
                ->where('follower_id', $userId)
                ->where('following_id', $currentUser->id)
                ->exists();

            if (!$isAdmin && !$isFriend) {
                $query->where(function ($sub) {
                    $sub->where('visibility', 'public')
                        ->orWhereNull('visibility');
                });
            }
        }

        $posts = $query->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return response()->json($posts->map(fn($p) => $this->formatUserPost($p))->values());
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

        return response()->json($posts->map(fn($p) => $this->formatUserPost($p))->values());
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

        return response()->json($posts->map(fn($p) => $this->formatUserPost($p))->values());
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

    public function dispatchVerificationCode(User $user): array
    {
        // Dakikada en fazla 3 kod talebi sınırı
        $recentCount = EmailVerificationCode::where('email', $user->email)
            ->where('created_at', '>', now()->subMinutes(2))
            ->count();
        if ($recentCount >= 3) {
            return [
                'success' => false,
                'detail' => 'Lütfen yeni bir kod istemeden önce 2 dakika bekleyin.',
                'status_code' => 429,
            ];
        }

        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Eski kodları temizle ve yenisini kaydet
        EmailVerificationCode::where('email', $user->email)->delete();
        EmailVerificationCode::create([
            'email' => $user->email,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
        ]);

        // E-Posta gönderimi (Çift Hatlı Gönderim: 1. Laravel Mailer -> 2. Native PHP mail() Fallback)
        $mailSent = false;
        try {
            if (config('mail.default') !== 'log') {
                Mail::html($this->getVerificationEmailHtml($user->nickname, $code), function ($message) use ($user, $code) {
                    $message->to($user->email)
                        ->subject('🐝 İTÜ Petek — E-Posta Doğrulama Kodun: ' . $code);
                });
                $mailSent = true;
            }
        } catch (\Throwable $e) {
            Log::warning("Laravel Mail gönderim uyarısı ({$user->email}): " . $e->getMessage());
        }

        // Eğer Laravel Mail gönderilmediyse veya 'log' sürücüsündeyse, doğrudan sunucunun yerel MTA (sendmail/mail) servisini kullan:
        if (!$mailSent) {
            try {
                $htmlBody = $this->getVerificationEmailHtml($user->nickname, $code);
                $subject = '=?UTF-8?B?' . base64_encode('🐝 İTÜ Petek — E-Posta Doğrulama Kodun: ' . $code) . '?=';
                $fromEmail = config('mail.from.address') && config('mail.from.address') !== 'hello@example.com'
                    ? config('mail.from.address')
                    : 'noreply@itupetek.com.tr';
                $fromName = config('mail.from.name') ?: 'İTÜ Petek';

                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
                $headers .= "Reply-To: iletisim@itupetek.com.tr\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

                $mailSent = @mail($user->email, $subject, $htmlBody, $headers, "-f {$fromEmail}");
                if (!$mailSent) {
                    $mailSent = @mail($user->email, $subject, $htmlBody, $headers);
                }
            } catch (\Throwable $ex) {
                Log::warning("Native mail() gönderim uyarısı: " . $ex->getMessage());
            }
        }

        Log::info("📧 [Email Verification] {$user->email} için doğrulama kodu üretildi: {$code} (Mail Gönderildi: " . ($mailSent ? 'EVET' : 'HAYIR/LOG') . ")");

        $isAdminOrDebug = (bool) (config('app.debug') || $user->role === 'admin' || in_array((int) $user->id, [1, 3]));

        return [
            'success' => true,
            'message' => "6 haneli doğrulama kodu {$user->email} adresinize gönderildi.",
            'email' => $user->email,
            'expires_in_minutes' => 15,
            'mail_sent' => $mailSent,
            'debug_code' => $isAdminOrDebug ? $code : null,
            'status_code' => 200,
        ];
    }

    public function sendVerificationCode(Request $request)
    {
        $user = $request->user();
        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'E-posta adresiniz zaten doğrulanmıştır.',
                'is_verified' => true,
            ]);
        }

        $result = $this->dispatchVerificationCode($user);
        if (!$result['success']) {
            return response()->json([
                'detail' => $result['detail'],
            ], $result['status_code']);
        }

        return response()->json([
            'message' => $result['message'],
            'email' => $result['email'],
            'expires_in_minutes' => $result['expires_in_minutes'],
            'debug_code' => $result['debug_code'],
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:4|max:10',
        ]);

        $user = $request->user();
        $inputCode = trim($request->input('code'));

        $record = EmailVerificationCode::where('email', $user->email)
            ->where('code', $inputCode)
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            return response()->json([
                'detail' => 'Geçersiz veya süresi dolmuş doğrulama kodu. Lütfen yeni bir kod isteyin.',
            ], 422);
        }

        // Kullanıcıyı doğrula
        $user->email_verified_at = now();

        // İTÜ Onaylı Arı rozetini ekle
        $badges = is_array($user->badges) ? $user->badges : [];
        if (!in_array('onayli_ari', $badges)) {
            $badges[] = 'onayli_ari';
            $user->badges = $badges;
        }

        // 25 Arı Puanı Ödülü Ver
        $user->bee_points = ($user->bee_points ?: 0) + 25;
        $user->save();

        // Kodu tüket
        $record->delete();

        return response()->json([
            'message' => 'Tebrikler! E-posta adresiniz başarıyla doğrulandı. İTÜ Onaylı Arı rozeti ve +25 Arı Puanı hesabınıza yüklendi! 🐝',
            'user' => $this->formatUser($user),
        ]);
    }

    public function getVerificationStatus(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'is_verified' => !is_null($user->email_verified_at),
            'email' => $user->email,
            'verified_at' => $user->email_verified_at?->toISOString(),
        ]);
    }

    private function getVerificationEmailHtml(string $nickname, string $code): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İTÜ Petek Doğrulama Kodu</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0E0E12; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #FFFFFF;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #0E0E12; padding: 40px 10px;">
        <tr>
            <td align="center">
                <table width="100%" max-width="520" border="0" cellspacing="0" cellpadding="0" style="max-width: 520px; background-color: #17171E; border-radius: 20px; border: 1px solid rgba(253, 185, 19, 0.25); overflow: hidden; box-shadow: 0 12px 30px rgba(0,0,0,0.5);">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 32px 24px 20px 24px; background: linear-gradient(180deg, rgba(253, 185, 19, 0.12) 0%, rgba(23, 23, 30, 0) 100%);">
                            <div style="font-size: 38px; line-height: 1;">🐝</div>
                            <h1 style="margin: 12px 0 4px 0; font-size: 24px; font-weight: 800; color: #FDB913; letter-spacing: 0.5px;">İTÜ PETEK</h1>
                            <p style="margin: 0; font-size: 13px; color: #9CA3AF;">İstanbul Teknik Üniversitesi Öğrenci Platformu</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding: 20px 32px 32px 32px; text-align: center;">
                            <h2 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #FFFFFF;">Selam @{$nickname}!</h2>
                            <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 22px; color: #D1D5DB;">
                                İTÜ Petek hesabını doğrulamak, <strong style="color: #10B981;">İTÜ Onaylı Arı</strong> rozetini almak ve <strong style="color: #FDB913;">+25 Arı Puanı</strong> kazanmak için aşağıdaki 6 haneli güvenlik kodunu uygulamaya gir:
                            </p>
                            
                            <!-- Kod Kutusu -->
                            <div style="background-color: #0B0B0F; border: 2px dashed #FDB913; border-radius: 14px; padding: 18px; margin: 24px 0; display: inline-block; min-width: 220px;">
                                <span style="font-family: 'Courier New', Courier, monospace; font-size: 34px; font-weight: 800; letter-spacing: 8px; color: #FDB913;">{$code}</span>
                            </div>
                            
                            <p style="margin: 20px 0 0 0; font-size: 12px; color: #9CA3AF;">
                                ⏱️ Bu kod <strong>15 dakika</strong> boyunca geçerlidir. Kodunuzu kimseyle paylaşmayın.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 16px 24px; background-color: #121217; border-top: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                            <p style="margin: 0; font-size: 11px; color: #6B7280;">
                                Bu işlemi siz başlatmadıysanız bu e-postayı güvenle göz ardı edebilirsiniz.<br>
                                © 2026 İTÜ Petek — Tüm hakları saklıdır.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
