# Image to WebP Converter

A simple web-based tool to convert images (JPG, JPEG, PNG, GIF) to the WebP format using PHP. Automatically selects conversion method: uses PHP-GD for static images and PHP-Imagick for animated GIFs. Fixed quality at 90 for simplicity.

---

## Features
- **Auto-method**: PHP-GD for static images; PHP-Imagick for animated GIFs.
- **Fixed quality**: 90.
- **Simple UI**: Upload a file and get one converted output.
- **Stats**: Shows sizes and reduction percentage.
- Clean, responsive UI (see `assets/style.css`).

---

## Requirements

### General
- PHP 7.0 or higher (recommended: PHP 7.4+)
- Web server (e.g., Apache, Nginx)

### For Conversion Methods
- **PHP-GD**: PHP compiled with the GD extension and WebP support.
    - Install: `sudo apt-get install php-gd`
    - Check WebP support: Run `php -i | grep -i webp` and look for `WebP Support => enabled`.
- **PHP-Imagick**: Imagick PHP extension with WebP support (required for animated GIFs).
    - Install: `sudo apt-get install php-imagick`
    - Check WebP support: Run `php -i | grep imagick` and check supported formats for `WEBP`.

> **Note:** PHP-GD is used for static images; PHP-Imagick is required for animated GIFs.

---

## Installation / Running locally (Linux)

1. **Clone or Download** this repository to your web server directory or to any folder:
   ```sh
    git clone https://github.com/HaykazXP/image-convertor-poc "Image convertor"
   # or download and extract the ZIP
   ```

2. **Set Permissions**
   - Ensure the web server can write to the `uploads/` and `converted/` directories:
     ```sh
     sudo chown -R www-data:www-data uploads converted
     sudo chmod -R 755 uploads converted
     ```

3. **Install PHP extensions**
    - `sudo apt-get update && sudo apt-get install -y php php-gd php-imagick`

4. **Configure PHP (if needed)**
   - Make sure `file_uploads` is enabled in your `php.ini`.
   - Increase `upload_max_filesize` and `post_max_size` if you want to allow larger images.

5. **Run a local PHP server** (if you don't have Apache/Nginx configured)
    - From the project folder, run:
      ```sh
      php -S localhost:8000
      ```
    - Open your browser at `http://localhost:8000/index.php`.

---

## Usage

1. **Upload an Image**
    - Click "Select image to upload" and choose a JPG, JPEG, PNG, or GIF file (max 5MB).

2. **Convert**
    - Click "Convert Image". The app auto-selects the method (GD or Imagick) and uses quality 90.

3. **Download Converted Image**
    - Right-click and save the generated WebP image.

---

## Project Structure

```
Image convertor/
├── assets/
│   └── style.css         # CSS styles
├── converted/            # Output directory for converted images (WebP)
├── uploads/              # Uploaded images
├── index.php             # Main PHP application
└── README.md             # This file
```

---

## Troubleshooting

- **GD or Imagick not found:**
  - Make sure the required extension is installed and enabled.
  - Check your PHP error logs for details.
- **WebP not supported:**
  - For GD/Imagick, check that WebP support is enabled (see Requirements).
- **Permission errors:**
  - Ensure the web server user has write access to `uploads/` and `converted/`.
- **File too large:**
  - The default max upload size is 5MB. Increase `upload_max_filesize` and `post_max_size` in `php.ini` if needed.

---

## Security Notes
- Only image files (JPG, JPEG, PNG, GIF) are allowed for upload.
- Uploaded and converted files are stored in `uploads/` and `converted/` respectively. Clean up these folders regularly if needed.

---

## License

MIT License

---

## Credits
- [WebP format by Google](https://developers.google.com/speed/webp/)
- PHP GD and Imagick extensions
- [libwebp](https://developers.google.com/speed/webp/download) (for cwebp)
