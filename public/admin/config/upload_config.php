<?php
/**
 * Upload Configuration
 * Centralized configuration for file uploads
 */

/**
 * Get upload directory for a specific type
 * @param string $type The upload type (e.g., 'audio', 'images', 'documents')
 * @return string The upload directory path
 */
function getUploadDir($type = 'general') {
    // Calculate path relative to the public directory
    // This config file is in public/admin/config/, so we need to go up to public/
    $baseDir = dirname(dirname(__DIR__)) . '/uploads/';
    
    // Ensure the base uploads directory exists
    if (!file_exists($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    
    switch ($type) {
        case 'audio':
            return $baseDir . 'audio/';
        case 'images':
            return $baseDir . 'images/';
        case 'documents':
            return $baseDir . 'documents/';
        case 'lessons':
            return $baseDir . 'lessons/';
        default:
            return $baseDir . 'general/';
    }
}

/**
 * Get relative path for storing in database
 * @param string $type The upload type
 * @param string $filename The filename with any subdirectories
 * @return string The relative path from the uploads directory
 */
function getRelativePath($type, $filename) {
    switch ($type) {
        case 'audio':
            return 'uploads/audio/' . $filename;
        case 'images':
            return 'uploads/images/' . $filename;
        case 'documents':
            return 'uploads/documents/' . $filename;
        case 'lessons':
            return 'uploads/lessons/' . $filename;
        default:
            return 'uploads/general/' . $filename;
    }
}

/**
 * Get allowed file types for upload
 * @param string $type The upload type
 * @return array Array of allowed MIME types and extensions
 */
function getAllowedFileTypes($type) {
    switch ($type) {
        case 'audio':
            return [
                'mime_types' => [
                    'audio/mpeg' => ['mp3'],
                    'audio/mp3' => ['mp3'],
                    'audio/wav' => ['wav'],
                    'audio/x-wav' => ['wav'],
                    'audio/wave' => ['wav'],
                    'audio/ogg' => ['ogg'],
                    'audio/mp4' => ['m4a', 'mp4'],
                    'audio/m4a' => ['m4a'],
                    'audio/webm' => ['webm'],
                    'audio/flac' => ['flac']
                ],
                'max_size' => 50 * 1024 * 1024, // 50MB
                'min_size' => 1024 // 1KB
            ];
        case 'images':
            return [
                'mime_types' => [
                    'image/jpeg' => ['jpg', 'jpeg'],
                    'image/png' => ['png'],
                    'image/gif' => ['gif'],
                    'image/webp' => ['webp']
                ],
                'max_size' => 10 * 1024 * 1024, // 10MB
                'min_size' => 100 // 100 bytes
            ];
        case 'documents':
            return [
                'mime_types' => [
                    'application/pdf' => ['pdf'],
                    'application/msword' => ['doc'],
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
                    'text/plain' => ['txt']
                ],
                'max_size' => 25 * 1024 * 1024, // 25MB
                'min_size' => 100 // 100 bytes
            ];
        default:
            return [
                'mime_types' => [],
                'max_size' => 5 * 1024 * 1024, // 5MB
                'min_size' => 100 // 100 bytes
            ];
    }
}

/**
 * Create secure upload directory with proper permissions and .htaccess
 * @param string $directory The directory path to create
 * @param string $type The upload type for appropriate .htaccess rules
 * @return bool Success status
 */
function createSecureUploadDir($directory, $type = 'general') {
    if (!file_exists($directory)) {
        if (!mkdir($directory, 0755, true)) {
            return false;
        }
        
        // Create appropriate .htaccess file based on type
        $htaccessContent = "# Prevent direct PHP execution\n";
        $htaccessContent .= "php_flag engine off\n";
        $htaccessContent .= "AddType text/plain .php .php3 .phtml .pht\n";
        
        switch ($type) {
            case 'audio':
                $htaccessContent .= "# Allow audio files\n";
                $htaccessContent .= "AddType audio/mpeg .mp3\n";
                $htaccessContent .= "AddType audio/wav .wav\n";
                $htaccessContent .= "AddType audio/ogg .ogg\n";
                $htaccessContent .= "AddType audio/mp4 .m4a\n";
                $htaccessContent .= "AddType audio/webm .webm\n";
                $htaccessContent .= "AddType audio/flac .flac\n";
                break;
            case 'images':
                $htaccessContent .= "# Allow image files\n";
                $htaccessContent .= "AddType image/jpeg .jpg .jpeg\n";
                $htaccessContent .= "AddType image/png .png\n";
                $htaccessContent .= "AddType image/gif .gif\n";
                $htaccessContent .= "AddType image/webp .webp\n";
                break;
            case 'documents':
                $htaccessContent .= "# Allow document files\n";
                $htaccessContent .= "AddType application/pdf .pdf\n";
                $htaccessContent .= "AddType application/msword .doc\n";
                $htaccessContent .= "AddType application/vnd.openxmlformats-officedocument.wordprocessingml.document .docx\n";
                $htaccessContent .= "AddType text/plain .txt\n";
                break;
        }
        
        file_put_contents($directory . '.htaccess', $htaccessContent);
    }
    
    return true;
}

/**
 * Generate secure filename
 * @param string $originalName The original filename
 * @param string $prefix Optional prefix for the filename
 * @return string Secure filename
 */
function generateSecureFilename($originalName, $prefix = '') {
    $pathInfo = pathinfo($originalName);
    $extension = strtolower($pathInfo['extension'] ?? '');
    $baseName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $pathInfo['filename'] ?? 'file');
    $baseName = substr($baseName, 0, 50); // Limit length
    
    $timestamp = date('Ymd_His');
    $randomBytes = bin2hex(random_bytes(8));
    
    $secureFilename = $prefix ? 
        "{$prefix}_{$timestamp}_{$randomBytes}_{$baseName}.{$extension}" :
        "{$timestamp}_{$randomBytes}_{$baseName}.{$extension}";
    
    return $secureFilename;
}

/**
 * Format bytes into human readable format
 * @param int $bytes The number of bytes
 * @param int $precision The decimal precision
 * @return string Formatted string
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Validate file upload
 * @param array $file The $_FILES array element
 * @param string $type The upload type
 * @return array Result array with success status and message
 */
function validateFileUpload($file, $type) {
    $config = getAllowedFileTypes($type);
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File too large (exceeds server limit of ' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds form limit)',
            UPLOAD_ERR_PARTIAL => 'File upload was interrupted',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: no temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Server error: cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension'
        ];
        return [
            'success' => false,
            'message' => $errorMessages[$file['error']] ?? 'Unknown upload error (code: ' . $file['error'] . ')'
        ];
    }
    
    // Validate file size
    if ($file['size'] > $config['max_size']) {
        return [
            'success' => false,
            'message' => 'File too large. Maximum size allowed is ' . formatBytes($config['max_size'])
        ];
    }
    
    if ($file['size'] < $config['min_size']) {
        return [
            'success' => false,
            'message' => 'File too small. Minimum size is ' . formatBytes($config['min_size'])
        ];
    }
    
    // Validate MIME type using file content inspection
    if (!function_exists('finfo_open')) {
        return [
            'success' => false,
            'message' => 'Server error: File type detection not available'
        ];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!$detectedMimeType || !array_key_exists($detectedMimeType, $config['mime_types'])) {
        $allowedTypes = array_keys($config['mime_types']);
        return [
            'success' => false,
            'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes)
        ];
    }
    
    // Validate file extension
    $originalName = basename($file['name']);
    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $config['mime_types'][$detectedMimeType])) {
        return [
            'success' => false,
            'message' => 'File extension does not match content type. Expected: ' . implode(', ', $config['mime_types'][$detectedMimeType])
        ];
    }
    
    return ['success' => true, 'message' => 'File validation passed'];
}