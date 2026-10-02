<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function conversations(Request $request)
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['participants'])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return response()->json($conversations->map(function ($c) use ($user) {
            $other = $c->participants->firstWhere('id', '!=', $user->id);
            $unread = Message::where('conversation_id', $c->id)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->count();

            $campusStatus = $other ? $other->getCampusStatus() : ['is_on_campus' => false, 'status_text' => 'Kampüs Dışında', 'campus_name' => null];

            return [
                'id' => (string) $c->id,
                'other_user_id' => (string) ($other?->id ?? ''),
                'other_user_nickname' => $other?->nickname ?? 'Bilinmeyen',
                'other_user_avatar' => $other?->avatar,
                'other_user_is_on_campus' => (bool) $campusStatus['is_on_campus'],
                'other_user_campus_status' => $campusStatus,
                'last_message' => $c->last_message,
                'last_message_at' => $c->last_message_at?->toISOString(),
                'unread_count' => $unread,
            ];
        }));
    }

    public function getOrCreateConversation(Request $request, $userId)
    {
        $user = $request->user();

        if ((string)$user->id === (string)$userId) {
            return response()->json(['detail' => 'Kendinizle sohbet edemezsiniz'], 400);
        }

        $target = User::findOrFail($userId);

        // Find existing conversation between the two users
        $conversation = Conversation::whereHas('participants', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->whereHas('participants', function ($q) use ($target) {
            $q->where('user_id', $target->id);
        })->first();

        if ($conversation) {
            return response()->json([
                'conversation_id' => (string) $conversation->id,
                'created' => false,
            ]);
        }

        // Create new conversation
        $conversation = Conversation::create([
            'last_message' => null,
            'last_message_at' => now(),
        ]);

        $conversation->participants()->attach([$user->id, $target->id]);

        return response()->json([
            'conversation_id' => (string) $conversation->id,
            'created' => true,
        ], 201);
    }

    public function messages($conversationId, Request $request)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Verify participant
        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['detail' => 'Yetkisiz erişim'], 403);
        }

        // Mark incoming messages as read
        Message::where('conversation_id', $conversationId)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = $conversation->messages()->take(100)->get();

        return response()->json($messages->map(function ($m) {
            return [
                'id' => (string) $m->id,
                'sender_id' => (string) $m->sender_id,
                'receiver_id' => (string) $m->receiver_id,
                'content' => $m->content,
                'message_type' => $m->message_type ?? 'text',
                'image' => $m->image,
                'audio' => $m->audio,
                'audio_duration' => $m->audio_duration,
                'is_read' => (bool) $m->is_read,
                'created_at' => $m->created_at->toISOString(),
            ];
        }));
    }

    public function sendMessage(Request $request, $conversationId)
    {
        $request->validate([
            'content' => 'nullable|string|max:2000',
            'message_type' => 'nullable|string|in:text,image,audio',
            'image' => 'nullable|string',
            'audio' => 'nullable|string',
            'audio_duration' => 'nullable|integer',
        ], [
            'content.max' => 'Mesaj uzunluğu en fazla 2000 karakter olabilir.',
        ]);
        $user = $request->user();
        $conversation = Conversation::with('participants')->find($conversationId);
        if (!$conversation) {
            $targetUser = User::find($conversationId);
            if ($targetUser) {
                if ((string)$user->id === (string)$targetUser->id) {
                    return response()->json(['detail' => 'Kendinizle sohbet edemezsiniz'], 400);
                }
                $conversation = Conversation::whereHas('participants', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->whereHas('participants', function ($q) use ($targetUser) {
                    $q->where('user_id', $targetUser->id);
                })->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'last_message' => null,
                        'last_message_at' => now(),
                    ]);
                    $conversation->participants()->attach([$user->id, $targetUser->id]);
                }
                $conversation->load('participants');
            } else {
                return response()->json(['detail' => 'Sohbet bulunamadı'], 404);
            }
        }

        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['detail' => 'Yetkisiz erişim'], 403);
        }

        $other = $conversation->participants->firstWhere('id', '!=', $user->id);

        if ($other) {
            $isBlocked = \Illuminate\Support\Facades\DB::table('user_blocks')
                ->where(function ($q) use ($user, $other) {
                    $q->where('user_id', $user->id)->where('blocked_id', $other->id);
                })
                ->orWhere(function ($q) use ($user, $other) {
                    $q->where('user_id', $other->id)->where('blocked_id', $user->id);
                })
                ->exists();

            if ($isBlocked) {
                return response()->json(['detail' => 'Bu kullanıcıyla mesajlaşamazsınız (engelleme mevcut).'], 403);
            }
        }

        $messageType = $request->input('message_type', 'text');
        if ($request->filled('image')) {
            $messageType = 'image';
        } elseif ($request->filled('audio')) {
            $messageType = 'audio';
        }

        if (!$request->filled('content') && !$request->filled('image') && !$request->filled('audio')) {
            return response()->json(['detail' => 'Mesaj içeriği boş olamaz'], 422);
        }

        // 🤖 Arı Muhafız: Cinsel / Müstehcen İçerik Denetimi
        $violation = \App\Services\ContentModerationService::inspectAndLog(
            $request->input('content', ''),
            $request->input('image'),
            $user,
            'chat_message'
        );
        if ($violation && $violation['blocked']) {
            return response()->json([
                'detail' => $violation['message'],
                'reason' => $violation['reason'],
            ], 422);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'receiver_id' => $other?->id,
            'content' => $request->content,
            'message_type' => $messageType,
            'image' => $request->image,
            'audio' => $request->audio,
            'audio_duration' => $request->audio_duration,
            'is_read' => false,
        ]);

        $lastMessageText = $request->content;
        if ($messageType === 'image') {
            $lastMessageText = '📷 Görsel';
        } elseif ($messageType === 'audio') {
            $lastMessageText = '🎤 Sesli Mesaj' . ($request->audio_duration ? ' (' . $request->audio_duration . 's)' : '');
        }

        $conversation->update([
            'last_message' => $lastMessageText,
            'last_message_at' => now(),
        ]);

        return response()->json([
            'id' => (string) $message->id,
            'sender_id' => (string) $message->sender_id,
            'receiver_id' => (string) $message->receiver_id,
            'content' => $message->content,
            'message_type' => $message->message_type,
            'image' => $message->image,
            'audio' => $message->audio,
            'audio_duration' => $message->audio_duration,
            'is_read' => false,
            'created_at' => $message->created_at->toISOString(),
        ], 201);
    }

    public function getConversationStatus($conversationId, Request $request)
    {
        $user = $request->user();
        $conversation = Conversation::with('participants')->find($conversationId);

        $other = null;
        if ($conversation) {
            $other = $conversation->participants->firstWhere('id', '!=', $user->id);
        } else {
            // Might be target user id passed
            $other = User::find($conversationId);
        }

        if (!$other) {
            return response()->json([
                'other_user_id' => null,
                'other_user_nickname' => null,
                'other_user_avatar' => null,
                'is_on_campus' => false,
                'status_text' => 'Kampüs Dışında',
                'campus_name' => null,
            ]);
        }

        $campusStatus = $other->getCampusStatus();

        return response()->json([
            'other_user_id' => (string) $other->id,
            'other_user_nickname' => $other->nickname,
            'other_user_avatar' => $other->avatar,
            'is_on_campus' => (bool) $campusStatus['is_on_campus'],
            'status_text' => $campusStatus['status_text'],
            'campus_name' => $campusStatus['campus_name'],
        ]);
    }

    public function getUserCampusStatus($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'is_on_campus' => false,
                'status_text' => 'Kampüs Dışında',
                'campus_name' => null,
            ]);
        }

        return response()->json($user->getCampusStatus());
    }
}
