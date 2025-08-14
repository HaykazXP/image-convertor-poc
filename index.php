<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$upload_dir = 'uploads/';
$converted_dir = 'converted/';
$original_image = '';
$converted_image = '';
$original_size = '';
$converted_size = '';
$error = '';
$conversion_method = 'gd';
$min_quality = isset($_POST['min_quality']) ? intval($_POST['min_quality']) : 60;
$max_quality = isset($_POST['max_quality']) ? intval($_POST['max_quality']) : 90;
$converted_images = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
    if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $file_name = uniqid() . '-' . basename($file['name']);
        $target_file = $upload_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if image file is a actual image or fake image
        $check = getimagesize($file['tmp_name']);
        if ($check === false) {
            $error = 'File is not an image.';
        }

        // Check file size (e.g., 5MB limit)
        if ($file['size'] > 5000000) {
            $error = 'Sorry, your file is too large.';
        }

        // Allow certain file formats
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowed_types)) {
            $error = 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.';
        }

        if (empty($error) && move_uploaded_file($file['tmp_name'], $target_file)) {
            $original_image = $target_file;
            $original_size = filesize($original_image);

            $conversion_method = $_POST['conversion_method'] ?? 'gd';

            // Validate quality range
            if ($min_quality < 1 || $min_quality > 100 || $max_quality < 1 || $max_quality > 100 || $min_quality > $max_quality) {
                $error = 'Invalid quality range. Min and max quality must be between 1 and 100, and min must not exceed max.';
            } else {
                $all_conversions_successful = true;
                
                for ($quality = $min_quality; $quality <= $max_quality; $quality++) {
                    $output_file_name = pathinfo($file_name, PATHINFO_FILENAME) . "-q{$quality}.webp";
                    $output_file = $converted_dir . $output_file_name;
                    
                    $conversion_successful = false;
                    if ($conversion_method === 'gd') {
                        $conversion_successful = convertWithGD($original_image, $output_file, $imageFileType, $quality);
                    } elseif ($conversion_method === 'imagick') {
                        if (extension_loaded('imagick')) {
                            $conversion_successful = convertWithImagick($original_image, $output_file, $quality);
                        } else {
                            $error = 'Imagick extension is not installed or enabled.';
                            break;
                        }
                    } elseif ($conversion_method === 'cwebp') {
                        $conversion_successful = convertWithCwebp($original_image, $output_file, $quality);
                    } elseif ($conversion_method === 'gif2webp') {
                        $conversion_successful = convertWithCwebp($original_image, $output_file, $quality);
                    }

                    if ($conversion_successful) {
                        $converted_images[] = [
                            'path' => $output_file,
                            'size' => filesize($output_file),
                            'quality' => $quality
                        ];
                    } else {
                        $all_conversions_successful = false;
                        if(empty($error)) {
                            $error = "Image conversion failed for quality: {$quality}";
                        }
                        break;
                    }
                }

                if ($all_conversions_successful && !empty($converted_images)) {
                    $converted_image = $converted_images[0]['path'];
                    $converted_size = $converted_images[0]['size'];
                }
            }
        } else {
            if(empty($error)) {
                $error = 'Sorry, there was an error uploading your file.';
            }
        }
    } else {
        $error = 'File upload error: ' . $_FILES['image']['error'];
    }
}

function convertWithGD($source, $destination, $original_type, $quality) {
    $image = null;
    switch ($original_type) {
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
        return imagewebp($image, $destination, $quality);
    }
    return false;
}

function convertWithImagick($source, $destination, $quality) {
    try {
        $image = new Imagick($source);

        // Handle animated GIFs
        if (strtolower(pathinfo($source, PATHINFO_EXTENSION)) === 'gif') {
            $image = $image->coalesceImages();
            $image->setOption('webp:animation', 'true');
        }

        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($quality);
        $image->setOption('webp:lossless', 'true');
        
        if ($image->getNumberImages() > 1) {
            $image->writeImages($destination, true);
        } else {
            $image->writeImage($destination);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function convertWithCwebp($source, $destination, $quality) {
    global $error;
    $source_type = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    
    // Use gif2webp for animated GIFs
    if ($source_type === 'gif') {
        $tool_path = trim(shell_exec('which gif2webp'));
        if (empty($tool_path)) {
            $error = 'gif2webp tool not found. Please install it to convert animated GIFs.';
            // Fallback to cwebp for single frame conversion
            return convertWithCwebpFallback($source, $destination, $quality);
        }
        $command = escapeshellarg($tool_path) . ' -q ' . $quality . ' ' . escapeshellarg($source) . ' -o ' . escapeshellarg($destination);
    } else {
        // Use cwebp for other image types
        $tool_path = trim(shell_exec('which cwebp'));
        if (empty($tool_path)) {
            $error = 'cwebp tool not found. Please install it.';
            return false;
        }
        $command = escapeshellarg($tool_path) . ' -q ' . $quality . ' -lossless ' . escapeshellarg($source) . ' -o ' . escapeshellarg($destination);
    }

    shell_exec($command);
    return file_exists($destination);
}

function convertWithCwebpFallback($source, $destination, $quality) {
    global $error;
    $cwebp_path = trim(shell_exec('which cwebp'));
    if (empty($cwebp_path)) {
        $error = 'cwebp tool not found. Please install it.';
        return false;
    }
    $command = escapeshellarg($cwebp_path) . ' -q ' . $quality . ' -lossless ' . escapeshellarg($source) . ' -o ' . escapeshellarg($destination);
    shell_exec($command);
    return file_exists($destination);
}

function formatSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image to WebP Converter</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h1>Image to WebP Converter</h1>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form action="index.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="image">Select image to upload:</label>
                <input type="file" name="image" id="image" required>
            </div>
            <div class="form-group">
                <label for="conversion_method">Conversion Method:</label>
                <select name="conversion_method" id="conversion_method">
                    <option value="gd" <?php echo ($conversion_method === 'gd') ? 'selected' : ''; ?>>PHP-GD</option>
                    <option value="imagick" <?php echo ($conversion_method === 'imagick') ? 'selected' : ''; ?>>PHP-Imagick</option>
                    <option value="cwebp" <?php echo ($conversion_method === 'cwebp') ? 'selected' : ''; ?>>cwebp (shell)</option>
                    <option value="gif2webp" <?php echo ($conversion_method === 'gif2webp') ? 'selected' : ''; ?>>gif2webp (shell)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="min_quality">Minimum Quality (1-100):</label>
                <input type="number" name="min_quality" id="min_quality" min="1" max="100" value="60" required>
            </div>
            <div class="form-group">
                <label for="max_quality">Maximum Quality (1-100):</label>
                <input type="number" name="max_quality" id="max_quality" min="1" max="100" value="90" required>
            </div>
            <button type="submit">Convert Image</button>
        </form>

        <?php if ($original_image && !empty($converted_images)): ?>
        <div class="results">
            <h2>Conversion Results</h2>
            <div class="image-comparison">
                <div class="image-box original">
                    <h3>Original Image</h3>
                    <img src="<?php echo $original_image; ?>" alt="Original Image">
                    <p>Size: <?php echo formatSize($original_size); ?></p>
                </div>
                
                <?php foreach ($converted_images as $converted): ?>
                <div class="image-box">
                    <h3>WebP Image (Quality: <?php echo $converted['quality']; ?>)</h3>
                    <img src="<?php echo $converted['path']; ?>" alt="Converted WebP Image">
                    <p>Size: <?php echo formatSize($converted['size']); ?></p>
                    <?php 
                        $size_difference = $original_size - $converted['size'];
                        $percentage_reduction = ($original_size > 0) ? ($size_difference / $original_size) * 100 : 0;
                    ?>
                    <p>Reduction: <?php echo formatSize($size_difference); ?> (<?php echo number_format($percentage_reduction, 2); ?>%)</p>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="summary">
                <h3>Summary</h3>
                <p>Original Size: <?php echo formatSize($original_size); ?></p>
                <p>Number of Conversions: <?php echo count($converted_images); ?></p>
                <p>Quality Range: <?php echo $min_quality; ?> to <?php echo $max_quality; ?></p>
                <?php
                    $best_reduction = 0;
                    $best_quality = 0;
                    foreach ($converted_images as $converted) {
                        $reduction = ($original_size - $converted['size']) / $original_size * 100;
                        if ($reduction > $best_reduction) {
                            $best_reduction = $reduction;
                            $best_quality = $converted['quality'];
                        }
                    }
                ?>
                <p>Best Reduction at Quality <?php echo $best_quality; ?>: <?php echo number_format($best_reduction, 2); ?>%</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

