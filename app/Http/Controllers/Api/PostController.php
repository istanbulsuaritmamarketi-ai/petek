<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Comment;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    protected CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    private function formatPost(Post $post, $currentUser = null): array
    {
        $author = $post->user;
        $isAnon = $post->is_anonymous;

        $authorNickname = $isAnon ? ($author?->department ?? 'Bilinmeyen') : ($author?->nickname ?? 'Bilinmeyen');
        $authorDepartment = $author?->department ?? 'Bilinmiyor';
        $authorBadges = $isAnon ? [] : ($author?->badges ?? []);
        $authorAvatar = $isAnon ? null : $author?->avatar;

        $mediaList = $post->media->map(fn($m) => [
            'type' => $m->type,
            'media_type' => $m->type,
            'url' => $m->url,
            'file_url' => $m->url,
            'image_size' => $m->thumbnail ?? 'instagram',
        ])->toArray();

        $likeIds = $post->likes->pluck('id')->map(fn($id) => (string)$id)->toArray();

        return [
            'id' => (string) $post->id,
            'content' => $post->content,
            'image' => !empty($mediaList) ? $mediaList[0]['url'] : null,
            'image_size' => !empty($mediaList) ? ($mediaList[0]['image_size'] ?? 'instagram') : 'instagram',
            'media' => $mediaList,
            'post_type' => $post->post_type,
            'is_anonymous' => $isAnon,
            'user_id' => (string) $post->user_id,
            'author_id' => (string) $post->user_id,
            'author_nickname' => $authorNickname,
            'author_department' => $authorDepartment,
            'author_badges' => $authorBadges,
            'author_avatar' => $authorAvatar,
            'hashtags' => $post->hashtags ?? [],
            'likes' => $likeIds,
            'like_count' => count($likeIds),
            'comments' => [], // Populated on show()
            'comment_count' => $post->comments_count ?? $post->allComments()->count(),
            'view_count' => $post->view_count,
            'is_archived' => $post->is_archived,
            'visibility' => $post->visibility ?? 'public',
            'is_friends_only' => ($post->visibility ?? 'public') === 'friends',
            'is_pinned' => (bool) ($post->is_pinned ?? false),
            'pinned_at' => $post->pinned_at?->toISOString(),
            'created_at' => $post->created_at->toISOString(),
        ];
    }

    private function extractHashtags(string $text): array
    {
        preg_match_all('/#([\p{L}\p{N}_]+)/u', $text, $matches);
        $tags = [];
        foreach ($matches[1] ?? [] as $t) {
            $clean = trim(ltrim($t, '#'));
            if ($clean !== '') {
                $tags[] = $clean;
            }
        }
        return array_values(array_unique($tags));
    }

    public function index(Request $request)
    {
        $postType = $request->get('post_type');
        $hashtag = $request->get('hashtag');
        $trending = filter_var($request->get('trending'), FILTER_VALIDATE_BOOLEAN);
        $sort = $request->get('sort');
        // Backwards compatibility: if trending flag is passed and sort is not provided, sort = 'hot'
        if (!$sort && $trending) {
            $sort = 'hot';
        } elseif (!$sort) {
            $sort = 'smart';
        }
        $q = trim($request->get('q', ''));
        $skip = (int) $request->get('skip', 0);
        $limit = (int) $request->get('limit', 20);

        $query = Post::with(['user', 'media', 'likes'])
            ->withCount('allComments')
            ->where('is_archived', false);

        // Filter out blocked users if user is authenticated
        $currentUser = auth('sanctum')->user();
        if ($currentUser) {
            $blockedUserIds = DB::table('user_blocks')
                ->where('user_id', $currentUser->id)
                ->pluck('blocked_id')
                ->merge(
                    DB::table('user_blocks')
                        ->where('blocked_id', $currentUser->id)
                        ->pluck('user_id')
                )
                ->unique()
                ->reject(fn($id) => (string)$id === (string)$currentUser->id)
                ->toArray();

            if (!empty($blockedUserIds)) {
                $query->whereNotIn('user_id', $blockedUserIds);
            }
        }

        // Visibility filter:
        // - 'public' posts are visible to all students and guests
        // - 'friends' posts are visible to:
        //     1. The author themselves
        //     2. Admins
        //     3. Friends of the author (the author follows the current user / mutual follow)
        if (!$currentUser) {
            $query->where(function ($vq) {
                $vq->where('visibility', 'public')
                   ->orWhereNull('visibility');
            });
        } elseif ($currentUser->role !== 'admin') {
            $query->where(function ($vq) use ($currentUser) {
                $vq->where('visibility', 'public')
                   ->orWhereNull('visibility')
                   ->orWhere('user_id', $currentUser->id)
                   ->orWhere(function ($fq) use ($currentUser) {
                       $fq->where('visibility', 'friends')
                          ->whereExists(function ($ex) use ($currentUser) {
                              $ex->select(DB::raw(1))
                                 ->from('user_follows')
                                 ->whereColumn('user_follows.follower_id', 'posts.user_id')
                                 ->where('user_follows.following_id', $currentUser->id);
                          });
                   });
            });
        }

        // Following filter: only posts by accounts the user follows (or pinned announcements)
        if ($sort === 'following') {
            if ($currentUser) {
                $followingIds = DB::table('user_follows')
                    ->where('follower_id', $currentUser->id)
                    ->pluck('following_id')
                    ->toArray();

                $query->where(function ($sub) use ($followingIds) {
                    $sub->whereIn('user_id', $followingIds)
                        ->orWhere('is_pinned', true);
                });
            } else {
                $query->where('is_pinned', true);
            }
        }

        // Full text search for posts, hashtags, and authors
        if ($q !== '') {
            $cleanTag = ltrim($q, '#');
            $query->where(function ($sub) use ($q, $cleanTag) {
                $sub->where('content', 'like', "%{$q}%")
                    ->orWhereJsonContains('hashtags', $cleanTag)
                    ->orWhereHas('user', function ($uq) use ($q) {
                        $uq->where('nickname', 'like', "%{$q}%")
                           ->orWhere('name', 'like', "%{$q}%");
                    });
            });
        }

        if ($postType && $postType !== 'all') {
            $query->where('post_type', $postType);
        }

        if ($hashtag) {
            $cleanHash = trim(ltrim($hashtag, '#'));
            $query->where(function ($sub) use ($cleanHash) {
                $sub->whereJsonContains('hashtags', $cleanHash)
                    ->orWhere('content', 'like', "%#{$cleanHash}%");
            });
        }

        if ($request->has('is_anonymous')) {
            $isAnon = filter_var($request->get('is_anonymous'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_anonymous', $isAnon);
        }

        // Pinned posts ALWAYS come first across all sorting modes
        $query->orderBy('is_pinned', 'desc')
              ->orderBy('pinned_at', 'desc');

        // Apply sorting criteria
        switch ($sort) {
            case 'popular':
            case 'hot':
                $query->orderByRaw('( (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) * 2 + (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) * 3 + posts.view_count * 0.1 ) / POW(GREATEST(0, TIMESTAMPDIFF(HOUR, posts.created_at, NOW())) + 2, 1.5) DESC')
                      ->orderBy('created_at', 'desc');
                break;

            case 'top':
                $query->withCount('likes')
                      ->orderBy('likes_count', 'desc')
                      ->orderBy('created_at', 'desc');
                break;

            case 'following':
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;

            case 'hybrid':
            case 'smart':
            default:
                // Akıllı / Hibrit Sıralama (Instagram/X Tarzı):
                // Son 48 saatte yüksek beğeni/yorum/etkileşim alan sıcak gönderilere hafif bir ağırlık verilip,
                // yeni gönderilerle harmanlanan dinamik zaman puanlaması.
                $query->orderByRaw('UNIX_TIMESTAMP(posts.created_at) + (CASE WHEN TIMESTAMPDIFF(HOUR, posts.created_at, NOW()) <= 48 THEN LEAST(50400, ((SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) * 1200 + (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) * 2400 + LEAST(posts.view_count, 500) * 10)) / (1 + TIMESTAMPDIFF(HOUR, posts.created_at, NOW()) * 0.05) ELSE 0 END) DESC')
                      ->orderBy('created_at', 'desc');
                break;
        }

        $posts = $query->skip($skip)->take($limit)->get();

        return response()->json($posts->map(fn($p) => $this->formatPost($p))->values());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $content = $request->input('content', '');

        // 🤖 Arı Muhafız: Cinsel / Müstehcen İçerik & Medya Denetimi
        $uploadedFile = $request->file('file') ?? $request->file('image');
        $violation = \App\Services\ContentModerationService::inspectAndLog($content, $uploadedFile, $user, 'post');
        if ($violation && $violation['blocked']) {
            return response()->json([
                'detail' => $violation['message'],
                'reason' => $violation['reason'],
                'matched' => $violation['matched'] ?? [],
            ], 422);
        }

        $postType = $request->input('post_type', 'general');
        $isAnonymous = filter_var($request->input('is_anonymous', false), FILTER_VALIDATE_BOOLEAN);

        $extracted = $this->extractHashtags($content);
        $raw = $request->input('hashtags', []);
        $passed = is_array($raw) ? $raw : (json_decode($raw, true) ?? []);
        $allHashtags = array_values(array_unique(array_filter(array_merge(
            array_map(fn($t) => trim(ltrim((string)$t, '#')), (array)$passed),
            $extracted
        ))));

        $visibility = $request->input('visibility', 'public');
        if (!in_array($visibility, ['public', 'friends'])) {
            $visibility = 'public';
        }

        $post = Post::create([
            'user_id' => $user->id,
            'content' => $content,
            'post_type' => $postType,
            'visibility' => $visibility,
            'is_anonymous' => $isAnonymous,
            'hashtags' => $allHashtags,
            'view_count' => 0,
            'is_archived' => false,
        ]);

        // Handle uploaded file (photo or video)
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = $file->getMimeType() ?: '';
            $ext = strtolower($file->getClientOriginalExtension() ?: '');
            $isVideo = str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm', '3gp']);
            $mediaType = $isVideo ? 'video' : 'image';

            $url = null;
            try {
                $url = $this->cloudinary->uploadMedia($file);
            } catch (\Exception $e) {
                // Fallback to local direct public storage (no symlink required)
                $uploadDir = public_path('uploads/media');
                if (!file_exists($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                $filename = 'post_' . uniqid() . '.' . ($ext ?: 'jpg');
                $file->move($uploadDir, $filename);
                $url = url('uploads/media/' . $filename);
                if (str_contains(config('app.url'), 'https://') || request()->isSecure() || str_contains($url, 'itupetek.com.tr')) {
                    $url = str_replace('http://', 'https://', $url);
                }
            }

            if ($url) {
                PostMedia::create([
                    'post_id' => $post->id,
                    'type' => $mediaType,
                    'url' => $url,
                    'thumbnail' => $request->input('image_size', $request->input('aspect_ratio', 'instagram')),
                ]);
            }
        } elseif ($request->has('image_base64') || ($request->has('image') && str_starts_with($request->input('image'), 'data:image'))) {
            $base64 = $request->input('image_base64') ?: $request->input('image');
            $mediaType = $request->input('media_type', 'image');
            $url = null;
            try {
                $url = $this->cloudinary->uploadBase64($base64);
            } catch (\Exception $e) {
                if (preg_match('/^data:(?:image|video)\/(\w+);base64,/', $base64, $match)) {
                    $ext = strtolower($match[1] === 'jpeg' ? 'jpg' : $match[1]);
                    $contentData = base64_decode(substr($base64, strpos($base64, ',') + 1));
                    $uploadDir = public_path('uploads/media');
                    if (!file_exists($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }
                    $filename = 'post_' . uniqid() . '.' . $ext;
                    file_put_contents($uploadDir . '/' . $filename, $contentData);
                    $url = url('uploads/media/' . $filename);
                    if (str_contains(config('app.url'), 'https://') || request()->isSecure() || str_contains($url, 'itupetek.com.tr')) {
                        $url = str_replace('http://', 'https://', $url);
                    }
                }
            }

            if ($url) {
                PostMedia::create([
                    'post_id' => $post->id,
                    'type' => $mediaType,
                    'url' => $url,
                    'thumbnail' => $request->input('image_size', $request->input('aspect_ratio', 'instagram')),
                ]);
            }
        } elseif ($request->has('media') && is_array($request->input('media'))) {
            foreach ($request->input('media') as $m) {
                if (!empty($m['url'])) {
                    PostMedia::create([
                        'post_id' => $post->id,
                        'type' => $m['type'] ?? 'image',
                        'url' => $m['url'],
                        'thumbnail' => $m['image_size'] ?? $request->input('image_size', 'instagram'),
                    ]);
                }
            }
        } elseif ($request->has('image') && !empty($request->input('image'))) {
            PostMedia::create([
                'post_id' => $post->id,
                'type' => $request->input('media_type', 'image'),
                'url' => $request->input('image'),
                'thumbnail' => $request->input('image_size', 'instagram'),
            ]);
        }

        $user->increment('post_count');
        $post->load(['user', 'media', 'likes']);

        return response()->json($this->formatPost($post), 201);
    }

    public function show($id)
    {
        $post = Post::with([
            'user',
            'media',
            'likes',
            'comments' => function ($q) {
                $q->with(['user', 'likes', 'replies.user', 'replies.likes']);
            }
        ])->findOrFail($id);

        // Visibility check for friends-only post
        if (($post->visibility ?? 'public') === 'friends') {
            $currentUser = auth('sanctum')->user() ?? request()->user();
            $canView = $currentUser && (
                (int)$currentUser->id === (int)$post->user_id ||
                $currentUser->role === 'admin' ||
                DB::table('user_follows')->where('follower_id', $post->user_id)->where('following_id', $currentUser->id)->exists()
            );
            if (!$canView) {
                return response()->json([
                    'message' => 'Bu gönderi yalnızca yazarın arkadaşlarına özeldir.'
                ], 403);
            }
        }

        $post->increment('view_count');

        $formatted = $this->formatPost($post);

        // Format comments and nested replies
        $formatted['comments'] = $post->comments->map(function ($c) {
            $authorNickname = $c->is_anonymous ? ($c->user?->department ?? 'Bilinmeyen') : ($c->user?->nickname ?? 'Bilinmeyen');
            $authorAvatar = $c->is_anonymous ? null : $c->user?->avatar;
            $commentLikes = $c->likes->pluck('id')->map(fn($id) => (string)$id)->toArray();

            $replies = $c->replies->map(function ($r) {
                $replyNickname = $r->is_anonymous ? ($r->user?->department ?? 'Bilinmeyen') : ($r->user?->nickname ?? 'Bilinmeyen');
                $replyAvatar = $r->is_anonymous ? null : $r->user?->avatar;
                $replyLikes = $r->likes->pluck('id')->map(fn($id) => (string)$id)->toArray();
                return [
                    'id' => (string) $r->id,
                    'content' => $r->content,
                    'author_id' => (string) $r->user_id,
                    'user_id' => (string) $r->user_id,
                    'author_nickname' => $replyNickname,
                    'author_avatar' => $replyAvatar,
                    'is_anonymous' => $r->is_anonymous,
                    'created_at' => $r->created_at->toISOString(),
                    'likes' => $replyLikes,
                    'view_count' => $r->view_count,
                ];
            })->toArray();

            return [
                'id' => (string) $c->id,
                'content' => $c->content,
                'author_id' => (string) $c->user_id,
                'user_id' => (string) $c->user_id,
                'author_nickname' => $authorNickname,
                'author_avatar' => $authorAvatar,
                'is_anonymous' => $c->is_anonymous,
                'created_at' => $c->created_at->toISOString(),
                'likes' => $commentLikes,
                'replies' => $replies,
            ];
        })->toArray();

        return response()->json($formatted);
    }

    public function getComments($id)
    {
        $post = Post::with([
            'comments' => function ($q) {
                $q->with(['user', 'likes', 'replies.user', 'replies.likes']);
            }
        ])->findOrFail($id);

        $comments = $post->comments->map(function ($c) {
            $authorNickname = $c->is_anonymous ? ($c->user?->department ?? 'Bilinmeyen') : ($c->user?->nickname ?? 'Bilinmeyen');
            $authorAvatar = $c->is_anonymous ? null : $c->user?->avatar;
            $commentLikes = $c->likes->pluck('id')->map(fn($id) => (string)$id)->toArray();

            $replies = $c->replies->map(function ($r) {
                $replyNickname = $r->is_anonymous ? ($r->user?->department ?? 'Bilinmeyen') : ($r->user?->nickname ?? 'Bilinmeyen');
                $replyAvatar = $r->is_anonymous ? null : $r->user?->avatar;
                $replyLikes = $r->likes->pluck('id')->map(fn($id) => (string)$id)->toArray();
                return [
                    'id' => (string) $r->id,
                    'content' => $r->content,
                    'author_id' => (string) $r->user_id,
                    'user_id' => (string) $r->user_id,
                    'author_nickname' => $replyNickname,
                    'author_avatar' => $replyAvatar,
                    'is_anonymous' => $r->is_anonymous,
                    'created_at' => $r->created_at->toISOString(),
                    'likes' => $replyLikes,
                    'view_count' => $r->view_count,
                ];
            })->toArray();

            return [
                'id' => (string) $c->id,
                'content' => $c->content,
                'author_id' => (string) $c->user_id,
                'user_id' => (string) $c->user_id,
                'author_nickname' => $authorNickname,
                'author_avatar' => $authorAvatar,
                'is_anonymous' => $c->is_anonymous,
                'created_at' => $c->created_at->toISOString(),
                'likes' => $commentLikes,
                'replies' => $replies,
            ];
        });

        return response()->json($comments);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();
        $post = Post::findOrFail($id);

        if ((int) $post->user_id !== (int) $user->id && $user->role !== 'admin' && $user->role !== 'moderator') {
            return response()->json(['detail' => 'Yetkiniz yok'], 403);
        }

        // 1. Delete all comment likes, nested replies and comments
        $commentIds = Comment::where('post_id', $post->id)->pluck('id');
        \Illuminate\Support\Facades\DB::table('comment_likes')->whereIn('comment_id', $commentIds)->delete();
        Comment::whereIn('parent_id', $commentIds)->delete();
        Comment::where('post_id', $post->id)->delete();

        // 2. Detach all likes
        $post->likes()->detach();

        // 3. Delete reports
        \App\Models\Report::where('target_type', 'post')->where('target_id', $post->id)->delete();

        // 4. Delete media files from storage & db
        foreach ($post->media as $m) {
            if ($m->url && str_contains($m->url, 'storage/media/')) {
                $filename = basename($m->url);
                \Illuminate\Support\Facades\Storage::disk('public')->delete('media/' . $filename);
            }
            $m->delete();
        }

        // 5. Decrement author's post count
        $author = $post->user;
        if ($author && $author->post_count > 0) {
            $author->decrement('post_count');
        }

        $post->delete();

        return response()->json(['message' => 'Gönderi ve tüm ilişkili verileri başarıyla silindi']);
    }

    public function reportPost(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $user = $request->user();
        $post = Post::findOrFail($id);

        \App\Models\Report::create([
            'reporter_id' => $user->id,
            'target_id' => $post->id,
            'target_type' => 'post',
            'reason' => $request->input('reason'),
            'status' => 'pending',
            'action_taken' => null,
        ]);

        return response()->json(['message' => 'Şikayetiniz yönetime iletildi.']);
    }

    public function toggleLike($id, Request $request)
    {
        $user = $request->user();
        $post = Post::findOrFail($id);

        if ($post->likes()->where('user_id', $user->id)->exists()) {
            $post->likes()->detach($user->id);
            return response()->json([
                'liked' => false,
                'like_count' => $post->likes()->count(),
            ]);
        } else {
            $post->likes()->attach($user->id);
            return response()->json([
                'liked' => true,
                'like_count' => $post->likes()->count(),
            ]);
        }
    }

    public function getPostLikers($id, Request $request)
    {
        $post = Post::findOrFail($id);
        $likers = $post->likes()
            ->select('users.id', 'users.nickname', 'users.name', 'users.avatar', 'users.department', 'users.faculty')
            ->orderByPivot('created_at', 'desc')
            ->get();

        return response()->json($likers->map(function ($u) {
            return [
                'id' => (string) $u->id,
                'nickname' => $u->nickname,
                'name' => $u->name,
                'avatar' => $u->avatar,
                'department' => $u->department,
                'faculty' => $u->faculty,
            ];
        }));
    }

    public function addComment(Request $request, $id)
    {
        $request->validate(['content' => 'required|string']);
        $user = $request->user();

        // 🤖 Arı Muhafız: Cinsel / Müstehcen İçerik Denetimi
        $violation = \App\Services\ContentModerationService::inspectAndLog($request->content, null, $user, 'comment');
        if ($violation && $violation['blocked']) {
            return response()->json([
                'detail' => $violation['message'],
                'reason' => $violation['reason'],
            ], 422);
        }

        $post = Post::findOrFail($id);
        $isAnon = filter_var($request->input('is_anonymous', false), FILTER_VALIDATE_BOOLEAN);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => $request->content,
            'is_anonymous' => $isAnon,
            'view_count' => 0,
        ]);

        return response()->json([
            'message' => 'Yorum eklendi',
            'comment' => [
                'id' => (string) $comment->id,
                'content' => $comment->content,
                'author_id' => (string) $user->id,
                'user_id' => (string) $user->id,
                'author_nickname' => $isAnon ? ($user->department ?? 'Bilinmeyen') : $user->nickname,
                'author_avatar' => $isAnon ? null : $user->avatar,
                'is_anonymous' => $isAnon,
                'likes' => [],
                'replies' => [],
                'created_at' => $comment->created_at->toISOString(),
            ]
        ], 201);
    }

    public function toggleCommentLike($postId, $commentId, Request $request)
    {
        $user = $request->user();
        $comment = Comment::where('post_id', $postId)->findOrFail($commentId);

        if ($comment->likes()->where('user_id', $user->id)->exists()) {
            $comment->likes()->detach($user->id);
            return response()->json(['liked' => false]);
        } else {
            $comment->likes()->attach($user->id);
            return response()->json(['liked' => true]);
        }
    }

    public function replyToComment(Request $request, $postId, $commentId)
    {
        $request->validate(['content' => 'required|string']);
        $user = $request->user();

        // 🤖 Arı Muhafız: Cinsel / Müstehcen İçerik Denetimi
        $violation = \App\Services\ContentModerationService::inspectAndLog($request->content, null, $user, 'comment_reply');
        if ($violation && $violation['blocked']) {
            return response()->json([
                'detail' => $violation['message'],
                'reason' => $violation['reason'],
            ], 422);
        }

        $parent = Comment::where('post_id', $postId)->findOrFail($commentId);
        $isAnon = filter_var($request->input('is_anonymous', false), FILTER_VALIDATE_BOOLEAN);

        $reply = Comment::create([
            'post_id' => $postId,
            'user_id' => $user->id,
            'parent_id' => $parent->id,
            'content' => $request->content,
            'is_anonymous' => $isAnon,
            'view_count' => 0,
        ]);

        return response()->json([
            'message' => 'Yanıt eklendi',
            'reply' => [
                'id' => (string) $reply->id,
                'content' => $reply->content,
                'author_id' => (string) $user->id,
                'user_id' => (string) $user->id,
                'author_nickname' => $isAnon ? ($user->department ?? 'Bilinmeyen') : $user->nickname,
                'author_avatar' => $isAnon ? null : $user->avatar,
                'is_anonymous' => $isAnon,
                'created_at' => $reply->created_at->toISOString(),
                'likes' => [],
                'view_count' => 0,
            ]
        ], 201);
    }

    public function toggleReplyLike($postId, $commentId, $replyId, Request $request)
    {
        $user = $request->user();
        $reply = Comment::where('post_id', $postId)->where('parent_id', $commentId)->findOrFail($replyId);

        if ($reply->likes()->where('user_id', $user->id)->exists()) {
            $reply->likes()->detach($user->id);
            return response()->json(['liked' => false]);
        } else {
            $reply->likes()->attach($user->id);
            return response()->json(['liked' => true]);
        }
    }

    public function toggleArchive($id, Request $request)
    {
        $user = $request->user();
        $post = Post::findOrFail($id);

        if ((int) $post->user_id !== (int) $user->id && $user->role !== 'admin') {
            return response()->json(['detail' => 'Yalnızca kendi gönderinizi arşivleyebilirsiniz'], 403);
        }

        if ($request->is('*/unarchive')) {
            $post->is_archived = false;
        } elseif ($request->is('*/archive')) {
            $post->is_archived = true;
        } else {
            $post->is_archived = !$post->is_archived;
        }
        $post->save();

        return response()->json([
            'message' => $post->is_archived ? 'Gönderi arşivlendi' : 'Gönderi arşivden çıkarıldı',
            'is_archived' => (bool) $post->is_archived,
        ]);
    }

    public function deleteComment($postId, $commentId, Request $request)
    {
        $user = $request->user();
        $post = Post::findOrFail($postId);
        $comment = Comment::where('post_id', $postId)->findOrFail($commentId);

        if ((int) $comment->user_id !== (int) $user->id && (int) $post->user_id !== (int) $user->id && $user->role !== 'admin') {
            return response()->json(['detail' => 'Bu yorumu silme yetkiniz yok'], 403);
        }

        $comment->delete();
        return response()->json(['message' => 'Yorum silindi']);
    }

    public function deleteReply($postId, $commentId, $replyId, Request $request)
    {
        $user = $request->user();
        $post = Post::findOrFail($postId);
        $reply = Comment::where('post_id', $postId)->where('parent_id', $commentId)->findOrFail($replyId);

        if ((int) $reply->user_id !== (int) $user->id && (int) $post->user_id !== (int) $user->id && $user->role !== 'admin') {
            return response()->json(['detail' => 'Bu yanıtı silme yetkiniz yok'], 403);
        }

        $reply->delete();
        return response()->json(['message' => 'Yanıt silindi']);
    }

    public function deleteCommentDirect($id, Request $request)
    {
        $user = $request->user();
        $comment = Comment::findOrFail($id);
        $post = Post::find($comment->post_id);

        if ((int) $comment->user_id !== (int) $user->id && ($post && (int) $post->user_id !== (int) $user->id) && $user->role !== 'admin') {
            return response()->json(['detail' => 'Bu yorumu silme yetkiniz yok'], 403);
        }

        $comment->delete();
        return response()->json(['message' => 'Yorum silindi']);
    }

    public function trendingHashtags()
    {
        $posts = DB::table('posts')
            ->where('is_archived', false)
            ->select('hashtags', 'content')
            ->get();

        $counts = [];
        foreach ($posts as $row) {
            $list = [];
            if (!empty($row->hashtags)) {
                $decoded = is_array($row->hashtags) ? $row->hashtags : json_decode($row->hashtags, true);
                if (is_array($decoded)) {
                    $list = $decoded;
                }
            }

            // Also extract hashtags directly from content to ensure real-time consistency
            if (!empty($row->content)) {
                $contentTags = $this->extractHashtags($row->content);
                $list = array_merge($list, $contentTags);
            }

            foreach ($list as $tag) {
                $clean = trim(ltrim((string)$tag, '#'));
                if ($clean !== '') {
                    $counts[$clean] = ($counts[$clean] ?? 0) + 1;
                }
            }
        }

        arsort($counts);
        $result = [];
        foreach (array_slice($counts, 0, 10, true) as $tag => $count) {
            $result[] = ['tag' => $tag, 'count' => $count];
        }

        return response()->json($result);
    }

    public function togglePin(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['detail' => 'Yalnızca yöneticiler gönderi sabitleyebilir.'], 403);
        }

        $post = Post::findOrFail($id);

        $willPin = !$post->is_pinned;

        if ($willPin) {
            // Unpin any previously pinned post so the new one is the primary pinned post
            Post::where('is_pinned', true)
                ->where('id', '!=', $post->id)
                ->update(['is_pinned' => false, 'pinned_at' => null]);

            $post->is_pinned = true;
            $post->pinned_at = now();
        } else {
            $post->is_pinned = false;
            $post->pinned_at = null;
        }

        $post->save();

        return response()->json([
            'message' => $post->is_pinned ? 'Gönderi akışın en başına sabitlendi 📌' : 'Gönderi sabitlemesi kaldırıldı',
            'is_pinned' => (bool) $post->is_pinned,
            'pinned_at' => $post->pinned_at?->toISOString(),
            'post' => $this->formatPost($post, $user),
        ]);
    }
}
