<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\RadarController;
use App\Http\Controllers\Api\ProfessorController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FocusController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CampusVibeController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\QuickLinkController;
use App\Http\Controllers\Api\DiningController;
use App\Http\Controllers\Api\LibraryController;

// --- STATS ROUTES ---
Route::get('/stats/countdown', [StatsController::class, 'countdown']);

// --- DINING (YEMEKHANE MENÜSÜ & PUANLAMA) ---
Route::prefix('dining')->group(function () {
    Route::get('/today', [DiningController::class, 'getToday']);
    Route::get('/history', [DiningController::class, 'getHistory']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/{id}/rate', [DiningController::class, 'rateMenu']);
        Route::delete('/ratings/{id}', [DiningController::class, 'deleteRating']);
        Route::post('/admin/menu', [DiningController::class, 'saveMenu']);
        Route::post('/admin/sync-sks', [DiningController::class, 'syncWithSks']);
        Route::post('/admin/notify-rate', [DiningController::class, 'sendRatingBroadcast']);
        Route::post('/admin/{id}/notify', [DiningController::class, 'sendNotification']);
    });
});

// --- LIBRARY & STUDY SPACE RADAR (KÜTÜPHANE VE PRİZ RADARI) ---
Route::prefix('library')->group(function () {
    Route::get('/radar', [LibraryController::class, 'getRadar']);
    Route::post('/report', [LibraryController::class, 'submitReport'])->middleware('auth:sanctum');
});

// --- AUTH ROUTES ---
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/send-verification-code', [AuthController::class, 'sendVerificationCode']);
        Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
        Route::get('/verification-status', [AuthController::class, 'getVerificationStatus']);
    });
});

// --- PROFILE & USERS ROUTES ---
Route::prefix('users')->group(function () {
    Route::get('/search', [AuthController::class, 'searchUsers']);
    Route::get('/{id}', [AuthController::class, 'getUserProfile']);
    Route::get('/{id}/posts', [AuthController::class, 'getUserPosts']);
    Route::get('/{id}/followers', [AuthController::class, 'getFollowers']);
    Route::get('/{id}/following', [AuthController::class, 'getFollowing']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/{id}/archived-posts', [AuthController::class, 'getUserArchivedPosts']);
        Route::get('/{id}/liked-posts', [AuthController::class, 'getUserLikedPosts']);
        Route::get('/{id}/replies', [AuthController::class, 'getUserReplies']);
        Route::match(['put', 'post'], '/profile', [AuthController::class, 'updateProfile']);
        Route::post('/avatar', [AuthController::class, 'uploadAvatar']);
        Route::post('/location', [AuthController::class, 'updateLocation']);
        Route::post('/freeze', [AuthController::class, 'freezeAccount']);
        Route::delete('/me', [AuthController::class, 'deleteAccount']);
        Route::post('/{id}/follow', [AuthController::class, 'toggleFollow']);
        Route::post('/{id}/block', [AuthController::class, 'toggleBlock']);
        Route::get('/blocks/list', [AuthController::class, 'getBlockedUsers']);
    });
});

// --- POSTS ROUTES ---
Route::prefix('posts')->group(function () {
    Route::get('/trending-hashtags', [PostController::class, 'trendingHashtags']);
    Route::get('/', [PostController::class, 'index']);
    Route::get('/{id}', [PostController::class, 'show']);
    Route::get('/{id}/comments', [PostController::class, 'getComments']);
    Route::get('/{id}/likes', [PostController::class, 'getPostLikers']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [PostController::class, 'store']);
        Route::delete('/{id}', [PostController::class, 'destroy']);
        Route::post('/{id}/like', [PostController::class, 'toggleLike']);
        Route::post('/{id}/archive', [PostController::class, 'toggleArchive']);
        Route::post('/{id}/unarchive', [PostController::class, 'toggleArchive']);
        Route::post('/{id}/pin', [PostController::class, 'togglePin']);

        // Comments & Replies
        Route::post('/{id}/comment', [PostController::class, 'addComment']);
        Route::delete('/{id}/comments/{commentId}', [PostController::class, 'deleteComment']);
        Route::post('/{id}/comments/{commentId}/like', [PostController::class, 'toggleCommentLike']);
        Route::post('/{id}/comments/{commentId}/reply', [PostController::class, 'replyToComment']);
        Route::delete('/{id}/comments/{commentId}/replies/{replyId}', [PostController::class, 'deleteReply']);
        Route::post('/{id}/comments/{commentId}/replies/{replyId}/like', [PostController::class, 'toggleReplyLike']);
        Route::post('/{id}/report', [PostController::class, 'reportPost']);
        Route::delete('/comments/{id}', [PostController::class, 'deleteCommentDirect']);
    });
});

// --- SETTINGS (PUBLIC) ---
Route::get('/settings/ads', [AdminController::class, 'getAdSettings']);
Route::get('/settings/categories', [AdminController::class, 'getPostCategories']);
Route::get('/settings/bee-tiers', [AdminController::class, 'getBeeTiers']);
Route::get('/settings/banner', [AdminController::class, 'getHeaderBanner']);
Route::get('/settings/branding', [AdminController::class, 'getBrandingSettings']);
Route::get('/settings/badges', [AdminController::class, 'getCustomBadges']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [AdminController::class, 'getNotifications']);
    Route::delete('/notifications/{id}', [AdminController::class, 'deleteNotification']);
    Route::delete('/notifications', [AdminController::class, 'clearAllNotifications']);
});


// --- RADAR & ZONES ROUTES ---
Route::get('/zones', [RadarController::class, 'getZones']);
Route::post('/zones/ping-location', [RadarController::class, 'pingLocation']);
Route::get('/zones/{id}', [RadarController::class, 'getZone']);
Route::get('/zones/{id}/suggestions', [RadarController::class, 'getSuggestions']);
Route::get('/metro/m2', [RadarController::class, 'getM2AyazagaLive']);
Route::get('/metro/m2-ayazaga', [RadarController::class, 'getM2AyazagaLive']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/zones', [RadarController::class, 'storeZone']);
    Route::put('/zones/{id}', [RadarController::class, 'updateZone']);
    Route::delete('/zones/{id}', [RadarController::class, 'deleteZone']);
    Route::post('/zones/{id}/vote', [RadarController::class, 'vote']);
    Route::post('/zones/{id}/prices', [RadarController::class, 'suggestPrice']);
    Route::post('/zones/{id}/suggestions', [RadarController::class, 'addSuggestion']);
});

// --- PROFESSORS (HOCAMETRE) ---
Route::prefix('professors')->group(function () {
    Route::get('/', [ProfessorController::class, 'index']);
    Route::get('/{id}', [ProfessorController::class, 'show']);
    Route::post('/{id}/rate', [ProfessorController::class, 'rate'])->middleware('auth:sanctum');
});

// --- EVENTS ---
Route::prefix('events')->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [EventController::class, 'store']);
        Route::delete('/{id}', [EventController::class, 'destroy']);
        Route::post('/crawl-itu', [EventController::class, 'crawlItuEvents']);
    });
});

// --- FOCUS SESSIONS & LEADERBOARD ---
Route::prefix('focus')->group(function () {
    Route::get('/leaderboard', [FocusController::class, 'leaderboard']);
    Route::get('/active-workers', [FocusController::class, 'activeWorkers']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/active', [FocusController::class, 'active']);
        Route::get('/stats', [FocusController::class, 'stats']);
        Route::post('/start', [FocusController::class, 'start']);
        Route::post('/end', [FocusController::class, 'end']);
        Route::post('/{id}/end', [FocusController::class, 'end']);
        Route::post('/fail', [FocusController::class, 'fail']);
    });
});

// --- KAMPUS VIBE ---
Route::prefix('vibes')->group(function () {
    Route::get('/', [CampusVibeController::class, 'index']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [CampusVibeController::class, 'store']);
        Route::delete('/{id}', [CampusVibeController::class, 'destroy']);
    });
});

// --- CHAT & DM ---
Route::prefix('chat')->middleware('auth:sanctum')->group(function () {
    Route::get('/conversations', [ChatController::class, 'conversations']);
    Route::post('/conversations/{userId}', [ChatController::class, 'getOrCreateConversation']);
    Route::get('/conversations/{conversationId}/messages', [ChatController::class, 'messages']);
    Route::post('/conversations/{conversationId}/messages', [ChatController::class, 'sendMessage']);
    // Direct message route aliases
    Route::get('/messages/{conversationId}', [ChatController::class, 'messages']);
    Route::post('/messages/{conversationId}', [ChatController::class, 'sendMessage']);
    // Campus presence status
    Route::get('/conversation/{conversationId}/status', [ChatController::class, 'getConversationStatus']);
    Route::get('/user-status/{userId}', [ChatController::class, 'getUserCampusStatus']);
});

// --- ADMIN PANEL ---
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);
    Route::get('/pending-prices', [AdminController::class, 'pendingPrices']);
    Route::get('/reports', [AdminController::class, 'reports']);
    Route::post('/reports/{id}/action', [AdminController::class, 'handleReportAction']);
    Route::get('/settings/bee-tiers', [AdminController::class, 'getBeeTiers']);
    Route::put('/settings/bee-tiers', [AdminController::class, 'updateBeeTiers']);
    Route::get('/settings/banner', [AdminController::class, 'getHeaderBanner']);
    Route::put('/settings/banner', [AdminController::class, 'updateHeaderBanner']);
    Route::get('/settings/ads', [AdminController::class, 'getAdSettings']);
    Route::put('/settings/ads', [AdminController::class, 'updateAdSettings']);
    Route::get('/settings/categories', [AdminController::class, 'getPostCategories']);
    Route::put('/settings/categories', [AdminController::class, 'updatePostCategories']);
    Route::get('/settings/contact', [AdminController::class, 'getContactSettings']);
    Route::put('/settings/contact', [AdminController::class, 'updateContactSettings']);
    Route::get('/settings/branding', [AdminController::class, 'getBrandingSettings']);
    Route::put('/settings/branding', [AdminController::class, 'updateBrandingSettings']);
    Route::get('/settings/badges', [AdminController::class, 'getCustomBadges']);
    Route::put('/settings/badges', [AdminController::class, 'updateCustomBadges']);
    Route::put('/prices/{zoneId}/{priceId}', [AdminController::class, 'managePrice']);
    Route::post('/{action}/{userId}', [AdminController::class, 'userAction']);

    // Library Floor & Study Spaces CRUD
    Route::get('/library-floors', [LibraryController::class, 'adminFloors']);
    Route::post('/library-floors', [LibraryController::class, 'storeFloor']);
    Route::put('/library-floors/{id}', [LibraryController::class, 'updateFloor']);
    Route::delete('/library-floors/{id}', [LibraryController::class, 'deleteFloor']);

    // Broadcast Notifications
    Route::get('/broadcasts', [AdminController::class, 'getBroadcastNotifications']);
    Route::post('/broadcasts', [AdminController::class, 'sendBroadcastNotification']);
    Route::delete('/broadcasts/{id}', [AdminController::class, 'deleteBroadcastNotification']);

    // Moderation & Guard Bot Logs
    Route::get('/moderation/logs', [AdminController::class, 'getModerationLogs']);
    Route::post('/users/{id}/toggle-verification', [AdminController::class, 'toggleUserEmailVerification']);
});

// --- DEALS & DISCOUNTS ---
Route::prefix('deals')->group(function () {
    Route::get('/', [DealController::class, 'index']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [DealController::class, 'store']);
        Route::put('/{id}', [DealController::class, 'update']);
        Route::delete('/{id}', [DealController::class, 'destroy']);
        Route::post('/crawl', [DealController::class, 'crawlDeals']);
    });
});

// --- QUICK LINKS (PORTALS) ---
Route::prefix('quick-links')->group(function () {
    Route::get('/', [QuickLinkController::class, 'index']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [QuickLinkController::class, 'store']);
        Route::put('/{id}', [QuickLinkController::class, 'update']);
        Route::delete('/{id}', [QuickLinkController::class, 'destroy']);
    });
});
