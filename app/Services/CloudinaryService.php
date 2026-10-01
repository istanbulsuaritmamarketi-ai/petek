<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected string $cloudName;
    protected string $apiKey;
    protected string $apiSecret;

    public function __construct()
    {
        $this->cloudName = env('CLOUDINARY_CLOUD_NAME', 'ddnf2nnjk');
        $this->apiKey = env('CLOUDINARY_API_KEY', '258899781264416');
        $this->apiSecret = env('CLOUDINARY_API_SECRET', 'JUk7zQxvmyeyc6TrPDHKOPlZfZw');
    }

    /**
     * Upload an uploaded file to Cloudinary.
     */
    public function uploadMedia(UploadedFile $file, string $folder = 'petek_v3'): string
    {
        $timestamp = time();
        $resourceType = str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';

        // Signature generation: folder={folder}&timestamp={timestamp}{api_secret}
        $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
        $signature = sha1($paramsToSign . $this->apiSecret);

        $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/{$resourceType}/upload";

        $response = Http::attach(
            'file', file_get_contents($file->getRealPath()), $file->getClientOriginalName()
        )->post($url, [
            'api_key' => $this->apiKey,
            'timestamp' => $timestamp,
            'folder' => $folder,
            'signature' => $signature,
        ]);

        if ($response->successful()) {
            return $response->json('secure_url');
        }

        Log::error('Cloudinary Upload Failed: ' . $response->body());
        throw new \Exception('Medya yükleme başarısız: ' . $response->body());
    }
}
