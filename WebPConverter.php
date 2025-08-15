<?php

declare(strict_types=1);

/**
 * WebP Image Converter
 * 
 * A clean, focused image converter that converts various image formats to WebP.
 * Supports both static and animated images, handles URLs, and follows SOLID principles.
 * 
 * @author Generated AI Assistant
 * @version 1.0.0
 */
class WebPConverter
{
    private const SUPPORTED_MIME_TYPES = [
        'image/gif',
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/svg+xml',
        'image/tiff',
        'image/bmp',
        'image/heic',
        'image/heif',
        'image/webp'
    ];

    private const ANIMATED_FORMATS = ['gif', 'webp'];

    private int $quality;

    public function __construct(int $quality = 90)
    {
        $this->validateQuality($quality);
        $this->quality = $quality;
    }

    /**
     * Convert an image file to WebP format
     */
    public function convertFile(string $filePath): string
    {
        $this->validateFile($filePath);
        
        $mimeType = $this->getMimeType($filePath);
        $this->validateMimeType($mimeType);

        $isAnimated = $this->isAnimatedImage($filePath, $mimeType);
        $outputPath = $this->generateOutputPath();

        if ($isAnimated && $this->supportsImagick()) {
            $this->convertWithImagick($filePath, $outputPath);
        } else {
            $this->convertWithGD($filePath, $outputPath, $mimeType);
        }

        return $outputPath;
    }

    /**
     * Convert an image from URL to WebP format
     */
    public function convertFromUrl(string $url): string
    {
        $tempFile = $this->downloadToTemp($url);
        
        try {
            return $this->convertFile($tempFile);
        } finally {
            $this->cleanupTempFile($tempFile);
        }
    }

    /**
     * Convert image data to WebP format
     */
    public function convertFromData(string $imageData): string
    {
        $tempFile = $this->saveDataToTemp($imageData);
        
        try {
            return $this->convertFile($tempFile);
        } finally {
            $this->cleanupTempFile($tempFile);
        }
    }

    private function validateQuality(int $quality): void
    {
        if ($quality < 0 || $quality > 100) {
            throw new InvalidArgumentException('Quality must be between 0 and 100');
        }
    }

    private function validateFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException('File does not exist: ' . $filePath);
        }

        if (!is_readable($filePath)) {
            throw new InvalidArgumentException('File is not readable: ' . $filePath);
        }
    }

    private function validateMimeType(string $mimeType): void
    {
        if (!in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported image format: ' . $mimeType);
        }
    }

    private function getMimeType(string $filePath): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);
        
        if ($mimeType === false) {
            throw new RuntimeException('Unable to determine file MIME type');
        }

        return $mimeType;
    }

    private function isAnimatedImage(string $filePath, string $mimeType): bool
    {
        $extension = $this->getExtensionFromMimeType($mimeType);
        
        if (!in_array($extension, self::ANIMATED_FORMATS, true)) {
            return false;
        }

        if ($this->supportsImagick()) {
            return $this->isAnimatedWithImagick($filePath);
        }

        return $this->isAnimatedWithHeuristic($filePath);
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeToExtension = [
            'image/gif' => 'gif',
            'image/jpeg' => 'jpeg',
            'image/pjpeg' => 'jpeg',
            'image/png' => 'png',
            'image/svg+xml' => 'svg',
            'image/tiff' => 'tiff',
            'image/bmp' => 'bmp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'image/webp' => 'webp'
        ];

        return $mimeToExtension[$mimeType] ?? 'unknown';
    }

    private function isAnimatedWithImagick(string $filePath): bool
    {
        try {
            $imagick = new Imagick($filePath);
            $frameCount = $imagick->getNumberImages();
            $imagick->destroy();
            
            return $frameCount > 1;
        } catch (Exception $e) {
            return false;
        }
    }

    private function isAnimatedWithHeuristic(string $filePath): bool
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $chunk = fread($handle, 1024 * 1024); // Read first 1MB
        fclose($handle);

        // Look for animation markers
        return strpos($chunk, 'NETSCAPE2.0') !== false || 
               strpos($chunk, 'ANIM') !== false;
    }

    private function convertWithImagick(string $source, string $destination): void
    {
        if (!$this->supportsImagick()) {
            throw new RuntimeException('Imagick extension is required for animated image conversion');
        }

        try {
            $imagick = new Imagick($source);
            
            // Handle animated images
            if ($imagick->getNumberImages() > 1) {
                $imagick = $imagick->coalesceImages();
                $imagick->setOption('webp:method', '6');
                $imagick->setOption('webp:lossless', 'false');
            }

            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($this->quality);

            if ($imagick->getNumberImages() > 1) {
                $imagick->writeImages($destination, true);
            } else {
                $imagick->writeImage($destination);
            }

            $imagick->destroy();
        } catch (Exception $e) {
            throw new RuntimeException('Imagick conversion failed: ' . $e->getMessage());
        }
    }

    private function convertWithGD(string $source, string $destination, string $mimeType): void
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('GD extension is required for image conversion');
        }

        $image = $this->createImageFromFile($source, $mimeType);
        
        try {
            // Ensure truecolor for WebP conversion
            if (!imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }

            // Preserve transparency for PNG
            if ($mimeType === 'image/png') {
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }

            if (!imagewebp($image, $destination, $this->quality)) {
                throw new RuntimeException('GD WebP conversion failed');
            }
        } finally {
            imagedestroy($image);
        }
    }

    private function createImageFromFile(string $source, string $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/pjpeg':
                return imagecreatefromjpeg($source);
            case 'image/png':
                return imagecreatefrompng($source);
            case 'image/gif':
                return imagecreatefromgif($source);
            case 'image/bmp':
                return imagecreatefrombmp($source);
            case 'image/webp':
                return imagecreatefromwebp($source);
            case 'image/svg+xml':
                return $this->convertSvgToImage($source);
            default:
                throw new RuntimeException('Unsupported format for GD conversion: ' . $mimeType);
        }
    }

    private function convertSvgToImage(string $svgPath)
    {
        if (!$this->supportsImagick()) {
            throw new RuntimeException('Imagick is required for SVG conversion');
        }

        try {
            $imagick = new Imagick();
            $imagick->setBackgroundColor(new ImagickPixel('transparent'));
            $imagick->readImage($svgPath);
            $imagick->setImageFormat('png');
            
            $blob = $imagick->getImageBlob();
            $imagick->destroy();
            
            return imagecreatefromstring($blob);
        } catch (Exception $e) {
            throw new RuntimeException('SVG conversion failed: ' . $e->getMessage());
        }
    }

    private function supportsImagick(): bool
    {
        return extension_loaded('imagick');
    }

    private function generateOutputPath(): string
    {
        return tempnam(sys_get_temp_dir(), 'webp_') . '.webp';
    }

    private function downloadToTemp(string $url): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'img_download_');
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'WebPConverter/1.0'
            ]
        ]);

        $data = file_get_contents($url, false, $context);
        
        if ($data === false) {
            throw new RuntimeException('Failed to download image from URL: ' . $url);
        }

        if (file_put_contents($tempFile, $data) === false) {
            throw new RuntimeException('Failed to save downloaded image data');
        }

        return $tempFile;
    }

    private function saveDataToTemp(string $data): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'img_data_');
        
        if (file_put_contents($tempFile, $data) === false) {
            throw new RuntimeException('Failed to save image data to temporary file');
        }

        return $tempFile;
    }

    private function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
