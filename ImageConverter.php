<?php

class ImageConverter
{
    private $quality;
    private $uploadDir;
    private $convertedDir;

    public function __construct($quality = 90, $uploadDir = 'uploads/', $convertedDir = 'converted/')
    {
        $this->quality = $quality;
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->convertedDir = rtrim($convertedDir, '/') . '/';
    }

    /**
     * Check if a GIF file is animated
     * 
     * @param string $filePath Path to the GIF file
     * @return bool True if animated, false if static
     */
    public function isAnimatedGif($filePath)
    {
        // Prefer Imagick for accurate detection
        if (extension_loaded('imagick')) {
            try {
                $im = new Imagick($filePath);
                return $im->getNumberImages() > 1;
            } catch (Exception $e) {
                // Fall through to signature-based heuristic
            }
        }

        // Heuristic: look for NETSCAPE2.0 animation application extension
        $fp = @fopen($filePath, 'rb');
        if ($fp) {
            $chunk = fread($fp, 1024 * 1024); // read up to 1MB
            fclose($fp);
            if ($chunk !== false && strpos($chunk, 'NETSCAPE2.0') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert image to WebP format
     * 
     * @param string $sourcePath Path to source image
     * @param string $destinationPath Path where converted image will be saved
     * @return array Result array with success status, message, and file info
     */
    public function convertImage($sourcePath, $destinationPath = null)
    {
        $result = [
            'success' => false,
            'message' => '',
            'original_size' => 0,
            'converted_size' => 0,
            'converted_path' => '',
            'method_used' => '',
            'is_animated' => false
        ];

        // Validate source file
        if (!file_exists($sourcePath)) {
            $result['message'] = 'Source file does not exist.';
            return $result;
        }

        // Get file info
        $result['original_size'] = filesize($sourcePath);
        $imageFileType = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        // Validate file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowedTypes)) {
            $result['message'] = 'Unsupported file type. Only JPG, JPEG, PNG & GIF files are allowed.';
            return $result;
        }

        // Validate image
        $check = getimagesize($sourcePath);
        if ($check === false) {
            $result['message'] = 'File is not a valid image.';
            return $result;
        }

        // Generate destination path if not provided
        if ($destinationPath === null) {
            $fileName = pathinfo($sourcePath, PATHINFO_FILENAME);
            $destinationPath = $this->convertedDir . $fileName . '-q' . $this->quality . '.webp';
        }

        // Ensure destination directory exists
        $destinationDir = dirname($destinationPath);
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        // Determine if it's an animated GIF
        $isAnimatedGif = false;
        if ($imageFileType === 'gif') {
            $isAnimatedGif = $this->isAnimatedGif($sourcePath);
            $result['is_animated'] = $isAnimatedGif;
        }

        // Convert based on image type
        if ($isAnimatedGif) {
            if (!extension_loaded('imagick')) {
                $result['message'] = 'Animated GIFs require the Imagick PHP extension. Please install/enable Imagick.';
                return $result;
            }
            $success = $this->convertWithImagick($sourcePath, $destinationPath);
            $result['method_used'] = 'PHP-Imagick';
        } else {
            $success = $this->convertWithGD($sourcePath, $destinationPath, $imageFileType);
            $result['method_used'] = 'PHP-GD';
        }

        if ($success && file_exists($destinationPath)) {
            $result['success'] = true;
            $result['message'] = 'Image converted successfully.';
            $result['converted_size'] = filesize($destinationPath);
            $result['converted_path'] = $destinationPath;
        } else {
            $result['message'] = 'Image conversion failed.';
        }

        return $result;
    }

    /**
     * Convert image using PHP-GD
     * 
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @param string $originalType Original image type
     * @return bool True on success, false on failure
     */
    private function convertWithGD($source, $destination, $originalType)
    {
        $image = null;
        switch ($originalType) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($source);
                break;
            case 'png':
                $image = imagecreatefrompng($source);
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            case 'gif':
                $image = imagecreatefromgif($source);
                break;
            default:
                return false;
        }

        if ($image !== null) {
            // Ensure image is in truecolor format to avoid "Palette image not supported" warnings
            if (!imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }
            $result = imagewebp($image, $destination, $this->quality);
            imagedestroy($image);
            return $result;
        }
        return false;
    }

    /**
     * Convert image using PHP-Imagick
     * 
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @return bool True on success, false on failure
     */
    private function convertWithImagick($source, $destination)
    {
        try {
            $image = new Imagick($source);

            // Handle animated GIFs
            if (strtolower(pathinfo($source, PATHINFO_EXTENSION)) === 'gif') {
                $image = $image->coalesceImages();
                $image->setOption('webp:animation', 'true');
            }

            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($this->quality);

            if ($image->getNumberImages() > 1) {
                $image->writeImages($destination, true);
            } else {
                $image->writeImage($destination);
            }

            $image->destroy();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Convert image from URL
     * 
     * @param string $imageUrl URL of the image to convert
     * @param string $destinationPath Optional destination path
     * @return array Result array
     */
    public function convertFromUrl($imageUrl, $destinationPath = null)
    {
        $result = [
            'success' => false,
            'message' => '',
            'original_size' => 0,
            'converted_size' => 0,
            'converted_path' => '',
            'method_used' => '',
            'is_animated' => false
        ];

        // Download image to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'img_conv_');
        $imageData = @file_get_contents($imageUrl);

        if ($imageData === false) {
            $result['message'] = 'Failed to download image from URL.';
            return $result;
        }

        if (file_put_contents($tempFile, $imageData) === false) {
            $result['message'] = 'Failed to save downloaded image.';
            return $result;
        }

        // Convert the temporary file
        $convertResult = $this->convertImage($tempFile, $destinationPath);

        // Clean up temporary file
        unlink($tempFile);

        return $convertResult;
    }

}
