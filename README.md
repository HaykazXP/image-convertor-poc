# Image to WebP Converter

A simple web-based tool to convert images (JPG, JPEG, PNG, GIF) to the WebP format using PHP. Supports multiple conversion methods: PHP-GD, PHP-Imagick, and cwebp (shell tool). Allows batch conversion with selectable quality range.

---

## Features
- Upload an image and convert it to WebP format.
- Choose conversion method: PHP-GD, PHP-Imagick, or cwebp.
- Set a quality range for batch conversion (e.g., 60–90).
- View original and converted images with size and reduction statistics.
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
- **PHP-Imagick**: Imagick PHP extension with WebP support.
    - Install: `sudo apt-get install php-imagick`
    - Check WebP support: Run `php -i | grep imagick` and check supported formats for `WEBP`.
- **cwebp**: The `cwebp` command-line tool (from the [libwebp](https://developers.google.com/speed/webp/download) package).
    - Install: `sudo apt-get install webp`
    - Check: Run `cwebp -version` in your terminal.

> **Note:** At least one conversion method must be available for the converter to work. The default is PHP-GD.

---

## Installation

1. **Clone or Download** this repository to your web server directory:
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

3. **Install Dependencies**
   - Install at least one of the required PHP extensions/tools (see Requirements above).

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

2. **Select Conversion Method**
   - Choose between PHP-GD, PHP-Imagick, or cwebp (shell) from the dropdown.
   - If a method is not available, an error will be shown.

3. **Set Quality Range**
   - Enter minimum and maximum quality (1–100). The converter will generate a WebP for each quality value in the range.

4. **Convert**
   - Click "Convert Image". The results will show the original and all converted images, with size and reduction stats.

5. **Download Converted Images**
   - Right-click and save any of the generated WebP images.

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

- **GD/Imagick/cwebp not found:**
  - Make sure the required extension/tool is installed and enabled.
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


- **Integration Version:**
  - There is an encapsulated version in the `integration_version` branch. It's worth checking that branch.
