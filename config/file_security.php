<?php
class FileUploadSecurity
{
    const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];
    const ALLOWED_IMAGE_EXTENSIONS = [
        'jpeg',
        'jpg',
        'png',
        'gif',
        'webp'
    ];
    const MAX_FILE_SIZE = 10 * 1024 * 1024;
    const MAX_IMAGE_WIDTH = 4096;
    const MAX_IMAGE_HEIGHT = 4096;
    const DANGEROUS_PATTERNS = [
        '/\.(php|phtml|php3|php4|php5|pht|phar|exe|bat|sh|cmd|com|scr|vbs|js|jar|htaccess)$/i',
        '/\.(asp|aspx|jsp|cfm|cgi|pl|py|rb)$/i'
    ];

    public static function validateImageMagicBytes($filePath)
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) return false;

        $bytes = fread($handle, 12);
        fclose($handle);
        $magicBytes = [
            'jpeg' => ["\xFF\xD8\xFF"],
            'png'  => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
            'gif'  => ["\x47\x49\x46\x38\x37\x61", "\x47\x49\x46\x38\x39\x61"],
            'webp' => ["\x52\x49\x46\x46", "\x57\x45\x42\x50"]
        ];

        foreach ($magicBytes as $format => $signatures) {
            foreach ($signatures as $signature) {
                if ($format === 'webp') {
                    if (substr($bytes, 0, 4) === $signature && substr($bytes, 8, 4) === "\x57\x45\x42\x50") {
                        return true;
                    }
                } else {
                    if (substr($bytes, 0, strlen($signature)) === $signature) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function generateSecureFilename($prefix, $extension)
    {
        return $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
    }

    public static function validateImageFile($tmpName, $originalName, $size)
    {
        $errors = [];

        if ($size > self::MAX_FILE_SIZE) {
            $errors[] = 'Kích thước file vượt quá giới hạn';
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $fileName = pathinfo($originalName, PATHINFO_FILENAME);

        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $fileName) || preg_match($pattern, $originalName)) {
                $errors[] = 'Phát hiện loại file không hợp lệ';
            }
        }

        if (!in_array($ext, self::ALLOWED_IMAGE_EXTENSIONS)) {
            $errors[] = 'Phần mở rộng file không được phép';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES)) {
            $errors[] = 'Loại MIME không được phép';
        }
        if (!self::validateImageMagicBytes($tmpName)) {
            $errors[] = 'Xác thực header file thất bại';
        }

        $imageInfo = @getimagesize($tmpName);
        if (!$imageInfo) {
            $errors[] = 'File ảnh không hợp lệ';
        } else {
            if ($imageInfo[0] > self::MAX_IMAGE_WIDTH || $imageInfo[1] > self::MAX_IMAGE_HEIGHT) {
                $errors[] = 'Kích thước ảnh quá lớn';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'extension' => $ext
        ];
    }

    public static function sanitizeString($input, $maxLength = 255)
    {
        $input = trim($input);
        if (strlen($input) > $maxLength) {
            return false;
        }
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    public static function createSecureUploadDir($path)
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                return false;
            }
        }

        $htaccessPath = $path . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = <<<EOT
                <Files "*.php">
                    Order Deny,Allow
                    Deny from all
                </Files>

                <Files "*.phtml">
                    Order Deny,Allow
                    Deny from all
                </Files>

                <Files "*.phar">
                    Order Deny,Allow
                    Deny from all
                </Files>

                php_flag engine off

                <FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
                    Order Allow,Deny
                    Allow from all
                </FilesMatch>
            EOT;
            file_put_contents($htaccessPath, $htaccessContent);
        }

        return true;
    }
}
