<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'ImageConverter.php';

$upload_dir = 'uploads/';
$converted_dir = 'converted/';
$original_image = '';
$converted_image = '';
$original_size = '';
$converted_size = '';
$error = '';
$quality = 90;
$converter = new ImageConverter($quality, $upload_dir, $converted_dir);


    /**
     * Get conversion statistics
     * 
     * @param array $result Result array from convertImage or convertFromUrl
     * @return array Statistics array
     */
    function getStats($result)
    {
        $stats = [
            'size_difference' => 0,
            'percentage_reduction' => 0
        ];
        
        if ($result['success'] && $result['original_size'] > 0) {
            $stats['size_difference'] = $result['original_size'] - $result['converted_size'];
            $stats['percentage_reduction'] = ($stats['size_difference'] / $result['original_size']) * 100;
        }
        
        return $stats;
    }


    
    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes Number of bytes
     * @return string Formatted size string
     */
    function formatSize($bytes)
    {
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
            
            // Use the ImageConverter class
            $result = $converter->convertImage($original_image);
            
            if ($result['success']) {
                $original_size = $result['original_size'];
                $converted_image = $result['converted_path'];
                $converted_size = $result['converted_size'];
            } else {
                $error = $result['message'];
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

// All conversion logic has been moved to ImageConverter class
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
            <button type="submit">Convert Image</button>
        </form>

        <?php if ($original_image && $converted_image): ?>
        <div class="results">
            <h2>Conversion Results</h2>
            <div class="image-comparison">
                <div class="image-box original">
                    <h3>Original Image</h3>
                    <img src="<?php echo $original_image; ?>" alt="Original Image">
                    <p>Size: <?php echo formatSize($original_size); ?></p>
                </div>
                <div class="image-box">
                    <h3>WebP Image (Quality: <?php echo $quality; ?>)</h3>
                    <img src="<?php echo $converted_image; ?>" alt="Converted WebP Image">
                    <p>Size: <?php echo formatSize($converted_size); ?></p>
                    <?php
                        $stats = getStats([
                            'success' => true,
                            'original_size' => $original_size,
                            'converted_size' => $converted_size
                        ]);
                    ?>
                    <p>Reduction: <?php echo formatSize($stats['size_difference']); ?> (<?php echo number_format($stats['percentage_reduction'], 2); ?>%)</p>
                </div>
            </div>
            
            <div class="summary">
                <h3>Summary</h3>
                <p>Original Size: <?php echo formatSize($original_size); ?></p>
                <p>Converted Size: <?php echo formatSize($converted_size); ?></p>
                <p>Quality Used: <?php echo $quality; ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

