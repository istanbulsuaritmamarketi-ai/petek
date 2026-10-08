<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     * E-postası doğrulanmamış öğrencilerin kovan içi etkileşimlerini engeller.
     * Süper Adminler (ID 1 & 3 ve admin rolü) kırılmaz kural gereği muaftır.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Misafir veya giriş yapmamışsa sanctum zaten yakalar
        if (!$user) {
            return $next($request);
        }

        // KIRILMAZ KURAL: Süper Admin hesap dokunulmazlığı (ID 1 & 3) ve admin rolü
        if ($user->role === 'admin' || in_array((int) $user->id, [1, 3])) {
            return $next($request);
        }

        // E-posta doğrulanmamışsa 403 Forbidden dön
        if (is_null($user->email_verified_at)) {
            return response()->json([
                'detail' => 'Kovana tam erişim sağlamak için önce @itu.edu.tr e-posta adresinizi doğrulamanız gerekmektedir.',
                'requires_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        return $next($request);
    }
}
