<?php

namespace Ralkage\AdManagement\Service;

use Flarum\Foundation\Paths;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;

class ImageService
{
    protected $paths;
    protected $settings;

    public function __construct(Paths $paths, SettingsRepositoryInterface $settings)
    {
        $this->paths = $paths;
        $this->settings = $settings;
    }

    public function getAllowedFormats(): array
    {
        $formats = $this->settings->get('ralkage-ad-management.allowed_image_formats', 'jpg,jpeg,png,webp,gif');
        return array_filter(array_map('trim', explode(',', strtolower($formats))));
    }

    public function validateImageUrl(string $imageUrl): void
    {
        $parts = parse_url($imageUrl);

        if (!$parts || empty($parts['host'])) {
            throw new ValidationException(['image_url' => 'Invalid image URL.']);
        }

        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            throw new ValidationException(['image_url' => 'Image URL must use http or https.']);
        }

        if (!$this->isSafeUrl($imageUrl)) {
            throw new ValidationException(['image_url' => 'Image URL points to a disallowed host.']);
        }

        $extension = strtolower(pathinfo($parts['path'] ?? '', PATHINFO_EXTENSION));
        $allowed = $this->getAllowedFormats();

        if (!in_array($extension, $allowed, true)) {
            throw new ValidationException([
                'image_url' => 'Image format "' . $extension . '" is not allowed. Allowed formats: ' . implode(', ', $allowed) . '.',
            ]);
        }
    }

    /**
     * Process an ad image: resize to zone dimensions if needed, then compress.
     * Returns a URL to the processed image (local copy if modified, original URL otherwise).
     */
    public function processImage(string $imageUrl, ?int $maxWidth = null, ?int $maxHeight = null): string
    {
        $extension = $this->getExtension($imageUrl);
        $processable = in_array($extension, ['jpg', 'jpeg', 'png', 'webp']);

        $needsResize = $processable && ($maxWidth || $maxHeight);
        $needsCompress = (bool) $this->settings->get('ralkage-ad-management.enable_compression', false);

        if (!$needsResize && !$needsCompress) {
            return $imageUrl;
        }

        $imageData = $this->downloadImage($imageUrl);
        if (!$imageData) {
            return $imageUrl;
        }

        $modified = false;

        // Step 1: Enforce zone dimensions (resize to fit within max_width × max_height)
        if ($needsResize && extension_loaded('gd')) {
            $resized = $this->resizeToFit($imageData, $maxWidth, $maxHeight);
            if ($resized !== null) {
                $imageData = $resized;
                $modified = true;
            }
        }

        // Step 2: Compress
        if ($needsCompress && $processable) {
            $method = $this->settings->get('ralkage-ad-management.compression_method', 'gd');

            if ($method === 'resmush' && function_exists('curl_init')) {
                $compressed = $this->compressViaResmush($imageData, $extension);
                if ($compressed !== null) {
                    $imageData = $compressed;
                    $modified = true;
                }
            } elseif (extension_loaded('gd')) {
                $compressed = $this->compressViaGd($imageData, $extension);
                if ($compressed !== null && strlen($compressed) < strlen($imageData)) {
                    $imageData = $compressed;
                    $modified = true;
                }
            }
        }

        if (!$modified) {
            return $imageUrl;
        }

        return $this->saveLocally($imageData, $extension) ?? $imageUrl;
    }

    public function deleteCompressedImage(string $imageUrl): void
    {
        if (strpos($imageUrl, '/assets/ad-images/') === false) {
            return;
        }

        $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
        $filepath = $this->paths->public . '/assets/ad-images/' . $filename;

        if (file_exists($filepath)) {
            @unlink($filepath);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Validate that a URL is safe to fetch: must be http/https, must not
     * resolve to a private/loopback/link-local address (SSRF prevention).
     */
    private function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (!$parts || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];

        // Resolve to IPv4
        $ip = gethostbyname($host);

        // If resolution failed or returned the hostname unchanged (non-existent host)
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Block private, loopback, and link-local ranges
        $privateRanges = [
            // Loopback
            ['127.0.0.0', '127.255.255.255'],
            // Private class A
            ['10.0.0.0', '10.255.255.255'],
            // Private class B
            ['172.16.0.0', '172.31.255.255'],
            // Private class C
            ['192.168.0.0', '192.168.255.255'],
            // Link-local (AWS metadata endpoint lives here)
            ['169.254.0.0', '169.254.255.255'],
            // IANA special-purpose
            ['100.64.0.0', '100.127.255.255'],
        ];

        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            // IPv6 or unparseable — block for safety
            return false;
        }

        foreach ($privateRanges as [$start, $end]) {
            if ($ipLong >= ip2long($start) && $ipLong <= ip2long($end)) {
                return false;
            }
        }

        return true;
    }

    private function downloadImage(string $imageUrl): ?string
    {
        if (!$this->isSafeUrl($imageUrl)) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'FlarumAdManagement/1.0',
            ],
        ]);

        $data = @file_get_contents($imageUrl, false, $context);

        if (!$data || strlen($data) > 5 * 1024 * 1024) {
            return null;
        }

        return $data;
    }

    /**
     * Resize image binary data to fit within maxWidth × maxHeight, preserving aspect ratio.
     * Returns new binary data, or null if no resize was needed.
     */
    private function resizeToFit(string $imageData, ?int $maxWidth, ?int $maxHeight): ?string
    {
        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            return null;
        }

        $origWidth  = imagesx($image);
        $origHeight = imagesy($image);

        if ((!$maxWidth || $origWidth <= $maxWidth) && (!$maxHeight || $origHeight <= $maxHeight)) {
            imagedestroy($image);
            return null; // Within bounds — no resize needed
        }

        $ratio     = $origWidth / $origHeight;
        $newWidth  = $origWidth;
        $newHeight = $origHeight;

        if ($maxWidth && $newWidth > $maxWidth) {
            $newWidth  = $maxWidth;
            $newHeight = (int) round($maxWidth / $ratio);
        }
        if ($maxHeight && $newHeight > $maxHeight) {
            $newHeight = $maxHeight;
            $newWidth  = (int) round($maxHeight * $ratio);
        }

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve alpha/transparency for PNG
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $info = @getimagesizefromstring($imageData);
        $mime = $info ? $info['mime'] : 'image/jpeg';

        ob_start();
        switch ($mime) {
            case 'image/jpeg': imagejpeg($canvas, null, 95); break;
            case 'image/png':  imagepng($canvas, null, 1);  break;
            case 'image/webp': imagewebp($canvas, null, 95); break;
            default:
                ob_end_clean();
                imagedestroy($image);
                imagedestroy($canvas);
                return null;
        }
        $output = ob_get_clean();

        imagedestroy($image);
        imagedestroy($canvas);

        return $output ?: null;
    }

    /**
     * Compress via the resmush.it lossless optimization API (file-upload endpoint).
     * Returns optimized binary data, or null on failure / if not smaller.
     */
    private function compressViaResmush(string $imageData, string $extension): ?string
    {
        $quality = max(0, min(100, (int) $this->settings->get('ralkage-ad-management.compression_quality', 92)));

        $tmpFile = tempnam(sys_get_temp_dir(), 'resmush_') . '.' . $extension;
        if (file_put_contents($tmpFile, $imageData) === false) {
            return null;
        }

        $curlFile = new \CURLFile($tmpFile, 'image/' . $extension, 'image.' . $extension);

        $ch = curl_init('https://api.resmush.it/ws.php');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POSTFIELDS     => ['files' => $curlFile, 'qlty' => $quality],
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_errno($ch);
        curl_close($ch);
        @unlink($tmpFile);

        if ($curlError || !$response) {
            return null;
        }

        $result = json_decode($response, true);
        if (!$result || !empty($result['error']) || empty($result['dest'])) {
            return null;
        }

        $optimized = @file_get_contents($result['dest']);
        if (!$optimized || strlen($optimized) >= strlen($imageData)) {
            return null;
        }

        return $optimized;
    }

    /**
     * Compress via PHP GD (lossy for JPEG/WebP, deflate for PNG).
     * Returns compressed binary data, or null on failure.
     */
    private function compressViaGd(string $imageData, string $extension): ?string
    {
        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            return null;
        }

        $quality = max(1, min(100, (int) $this->settings->get('ralkage-ad-management.compression_quality', 85)));

        ob_start();
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, null, $quality);
                break;
            case 'png':
                $pngLevel = max(0, min(9, (int) round((100 - $quality) / 11)));
                imagepng($image, null, $pngLevel);
                break;
            case 'webp':
                imagewebp($image, null, $quality);
                break;
            default:
                ob_end_clean();
                imagedestroy($image);
                return null;
        }
        $output = ob_get_clean();
        imagedestroy($image);

        return $output ?: null;
    }

    private function saveLocally(string $imageData, string $extension): ?string
    {
        $dir = $this->paths->public . '/assets/ad-images';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (!is_writable($dir)) {
            return null;
        }

        $filename = md5(uniqid('', true)) . '.' . $extension;
        $filepath = $dir . '/' . $filename;

        if (file_put_contents($filepath, $imageData) === false) {
            return null;
        }

        $baseUrl = $this->settings->get('url', '');
        return rtrim($baseUrl, '/') . '/assets/ad-images/' . $filename;
    }

    private function getExtension(string $imageUrl): string
    {
        $parsed = parse_url($imageUrl, PHP_URL_PATH);
        return strtolower(pathinfo($parsed, PATHINFO_EXTENSION));
    }
}
