<?php

namespace App\Services;

use App\Models\ModerationLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ContentModerationService
{
    /**
     * Kapsamlı Cinsel & Müstehcen Kelime ve Kalıp Listesi (Türkçe & İngilizce)
     */
    protected static array $sexualTerms = [
        // Müstehcen Anatomi & Küfür
        'amcık', 'amcik', 'yarrak', 'yarak', 'sik', 'sikiş', 'sikis', 'sikişme', 'sikismek', 'sikeyim', 'siktiğim',
        'orospu', 'kaltak', 'kahpe', 'piç', 'götlek', 'amk', 'aq', 'amq', 'oç', 'göt deliği', 'got deligi',
        'vajina', 'penis', 'klitoris', 'testis', 'daşşak', 'dassak', 'taşşak',

        // Cinsel Eylemler & Müstehcen İfadeler
        'seks', 'sex', 'porno', 'porn', 'pornhub', 'xvideos', 'nude', 'nudes', 'çıplak', 'ciplak', 'çırılçıplak',
        'ifşa', 'ifsa', 'nsfw', 'anal', 'oral', 'sakso', 'saksofon', 'blowjob', 'handjob', 'mastürbasyon', 'masturbasyon',
        'boşalma', 'bosalma', 'orgazm', 'döl', 'sperm', 'fetiş', 'fetis', 'bdsm', 'grup seks', 'threesome',
        'sikişelim', 'sikelim', 'azdım', 'azdim', 'azgın', 'azgin', 'sik beni', 'amıma koy', 'amına koy', 'kucak dansı',

        // Escort & Fuhuş & Ücretli İlişki
        'eskort', 'escort', 'esc', 'travesti', 'trans masaj', 'mutlu son', 'vip masaj', 'evimde masaj',
        'ücretli görüşme', 'ucretli gorusme', 'saatlik görüşme', 'partner ara', 'gecelik bayan', 'cinsel ilişki',
        'sugar daddy', 'sugar mommy', 'onlyfans', 'patreon 18+', 'fansly', 'camfrog',

        // İngilizce Cinsel Terimler
        'pussy', 'dick', 'cock', 'boobs', 'tits', 'slut', 'whore', 'bitch', 'fuck', 'fucking',
        'deepthroat', 'cumshot', 'gangbang', 'horny', 'erotic', 'erotik'
    ];

    /**
     * Metni normalize et (boşlukları, sembolleri, leetspeak karakterlerini temizle)
     */
    public static function normalizeText(string $text): string
    {
        $normalized = mb_strtolower($text, 'UTF-8');

        // Türkçe karakter dönüşümleri
        $trFrom = ['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
        $trTo   = ['i', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'];
        $normalized = str_replace($trFrom, $trTo, $normalized);

        // Leetspeak harf benzeri sayıları/sembolleri çevir
        $leetMap = [
            '@' => 'a', '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a',
            '5' => 's', '7' => 't', '$' => 's', '+' => 't', '!' => 'i',
            '|' => 'i'
        ];
        $normalized = strtr($normalized, $leetMap);

        return $normalized;
    }

    /**
     * Metinde cinsel/müstehcen içerik var mı denetle
     */
    public static function checkText(string $rawText): array
    {
        if (trim($rawText) === '') {
            return ['blocked' => false];
        }

        $norm = self::normalizeText($rawText);

        // Karakterler arasına konan nokta, alt tire ve boşlukları birleştirip kontrol et
        // Örn: "s.i.k.i.ş" veya "p o r n o"
        $condensed = preg_replace('/[\s\.\-_\*\+]+/u', '', $norm);

        $matched = [];

        foreach (self::$sexualTerms as $term) {
            $termNorm = mb_strtolower($term, 'UTF-8');

            // 1. Doğrudan kelime sınırlarıyla arama
            $escaped = preg_quote($termNorm, '/');
            if (preg_match('/(?:\b|^)' . $escaped . '(?:\b|$)/u', $norm)) {
                $matched[] = $term;
                continue;
            }

            // 2. Birleştirilmiş (condensed) metinde arama (uzunluğu >= 4 olan terimler için)
            if (mb_strlen($termNorm) >= 4 && str_contains($condensed, $termNorm)) {
                $matched[] = $term;
            }
        }

        // 3. Telefon Numarası + Cinsel/Fuhuş İlanı Kalıbı
        $hasPhone = preg_match('/(?:0\s*5[0-9]{2}[\s\.\-]*[0-9]{3}[\s\.\-]*[0-9]{2}[\s\.\-]*[0-9]{2})/u', $rawText);
        $hasEroticKeyword = preg_match('/(masaj|bayan|görüşme|otel|evimde|ücret|saatlik|partner|eskort|escort)/iu', $rawText);
        if ($hasPhone && $hasEroticKeyword) {
            $matched[] = 'telefon_fuhus_eslestirmesi';
        }

        if (!empty($matched)) {
            return [
                'blocked' => true,
                'reason' => 'sexual_content',
                'matched' => array_values(array_unique($matched)),
                'message' => '🤖 Arı Muhafız: Paylaşımınız topluluk kurallarına aykırı cinsel / müstehcen ifadeler içerdiği için engellendi.'
            ];
        }

        return ['blocked' => false];
    }

    /**
     * Yüklenen Medyada (Görsel/Video) Cinsel / Nü / NSFW Denetimi
     */
    public static function checkMedia($file): array
    {
        if (!$file) {
            return ['blocked' => false];
        }

        $filename = '';
        $path = '';

        if ($file instanceof UploadedFile) {
            $filename = strtolower($file->getClientOriginalName() ?: '');
            $path = $file->getRealPath();
        } elseif (is_string($file)) {
            $filename = strtolower(basename($file));
            $path = $file;
        }

        // 1. Dosya adı anahtar kelime kontrolü
        foreach (['nude', 'porno', 'porn', 'nsfw', 'sex', 'amcik', 'yarrak', 'ciplak', 'ifsa', 'onlyfans', 'sikis', 'erotic'] as $badWord) {
            if (str_contains($filename, $badWord)) {
                return [
                    'blocked' => true,
                    'reason' => 'nsfw_media_filename',
                    'matched' => [$badWord],
                    'message' => '🤖 Arı Muhafız: Yüklenen medya dosyasının adı cinsel / müstehcen içerik kurallarını ihlal ediyor.'
                ];
            }
        }

        // 2. PHP GD ile Cilt Tonu & Nü Görsel Sezgisel Analizi (Skin Tone Nudity Ratio)
        if (!empty($path) && file_exists($path) && function_exists('imagecreatefromstring')) {
            try {
                $fileContents = @file_get_contents($path);
                if ($fileContents && strlen($fileContents) > 500) {
                    $img = @imagecreatefromstring($fileContents);
                    if ($img) {
                        $w = imagesx($img);
                        $h = imagesy($img);

                        // Hız için 40x40 piksel ızgara üzerinde örnekle (1600 piksel)
                        $sampleW = 40;
                        $sampleH = 40;
                        $skinPixels = 0;
                        $totalSamples = $sampleW * $sampleH;

                        $stepX = max(1, (int) ($w / $sampleW));
                        $stepY = max(1, (int) ($h / $sampleH));

                        for ($x = 0; $x < $sampleW; $x++) {
                            for ($y = 0; $y < $sampleH; $y++) {
                                $px = min($w - 1, $x * $stepX);
                                $py = min($h - 1, $y * $stepY);
                                $rgb = imagecolorat($img, $px, $py);

                                $r = ($rgb >> 16) & 0xFF;
                                $g = ($rgb >> 8) & 0xFF;
                                $b = $rgb & 0xFF;

                                // İnsan Ten Rengi Renk Skalası Algoritması (Peer et al. standard RGB skin model):
                                // R > 95, G > 40, B > 20
                                // max(R,G,B) - min(R,G,B) > 15
                                // |R - G| > 15
                                // R > G ve R > B
                                if ($r > 95 && $g > 40 && $b > 20 &&
                                    (max($r, $g, $b) - min($r, $g, $b)) > 15 &&
                                    abs($r - $g) > 15 &&
                                    $r > $g && $r > $b
                                ) {
                                    $skinPixels++;
                                }
                            }
                        }

                        imagedestroy($img);

                        $skinRatio = $skinPixels / $totalSamples;

                        // Görselin %60'tan fazlası açık ten rengiyle kaplıysa ve yüz/kıyafet dengesi yoksa
                        if ($skinRatio > 0.60) {
                            return [
                                'blocked' => true,
                                'reason' => 'nsfw_skin_ratio',
                                'matched' => ['skin_ratio: ' . round($skinRatio * 100) . '%'],
                                'message' => '🤖 Arı Muhafız: Yüklenen görsel aşırı çıplaklık / müstehcen cilt oranı tespit edildiği için engellendi.'
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Arı Muhafız GD analiz hatası: ' . $e->getMessage());
            }
        }

        return ['blocked' => false];
    }

    /**
     * Tüm içeriği tara ve gerekirse otomatik engelle & logla
     */
    public static function inspectAndLog(?string $text, $file = null, ?User $user = null, string $action = 'post'): ?array
    {
        // Metin denetimi
        if (!empty($text)) {
            $textResult = self::checkText($text);
            if ($textResult['blocked']) {
                self::logIncident($user, $action, $textResult['reason'], $text, $textResult['matched']);
                return $textResult;
            }
        }

        // Medya denetimi
        if ($file) {
            $mediaResult = self::checkMedia($file);
            if ($mediaResult['blocked']) {
                self::logIncident($user, $action, $mediaResult['reason'], '[MEDYA DOSYASI]', $mediaResult['matched']);
                return $mediaResult;
            }
        }

        return null;
    }

    /**
     * Engelleme olayını veritabanına logla
     */
    protected static function logIncident(?User $user, string $action, string $reason, ?string $content, array $matched)
    {
        try {
            ModerationLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'reason' => $reason,
                'content_snippet' => mb_substr($content ?? '', 0, 250),
                'matched_terms' => $matched,
                'ip_address' => request()->ip(),
            ]);

            Log::channel('daily')->warning("🤖 [Arı Muhafız] Cinsel/Müstehcen İçerik Engellendi: User: {$user?->nickname} (#{$user?->id}), Aksiyon: {$action}, Sebep: {$reason}");
        } catch (\Throwable $e) {
            Log::error('Arı Muhafız log hatası: ' . $e->getMessage());
        }
    }
}
