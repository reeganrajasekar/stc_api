<?php

namespace Utils;

class MediaUrlHelper
{
    /**
     * Convert relative file path to absolute URL
     */
    public static function getAbsoluteUrl(?string $relativePath): string
    {
        if (empty($relativePath)) {
            return '';
        }
        
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $baseUrl = rtrim($baseUrl, '/');
        
        // If it's already a full URL, return as is
        if (strpos($relativePath, 'http') === 0) {
            return $relativePath;
        }
        
        // Clean the relative path
        $relativePath = ltrim($relativePath, '/');
        
        return $baseUrl . '/' . $relativePath;
    }

    /**
     * Convert multiple file paths to absolute URLs
     */
    public static function convertPathsToUrls(array $data, array $pathFields): array
    {
        foreach ($pathFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = self::getAbsoluteUrl($data[$field]);
            }
        }
        
        return $data;
    }

    /**
     * Convert file paths in array of records
     */
    public static function convertArrayPathsToUrls(array $records, array $pathFields): array
    {
        return array_map(function($record) use ($pathFields) {
            return self::convertPathsToUrls($record, $pathFields);
        }, $records);
    }

    /**
     * Get secure media URL for authenticated access
     */
    public static function getSecureMediaUrl(string $mediaType, string $mediaId): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $baseUrl = rtrim($baseUrl, '/');
        
        return $baseUrl . "/v1/media/{$mediaType}/{$mediaId}";
    }

    /**
     * Get media info with both secure and direct URLs
     */
    public static function getMediaUrls(string $relativePath, string $mediaType, string $mediaId): array
    {
        if (empty($relativePath)) {
            return [
                'direct_url' => '',
                'secure_url' => '',
                'generate_secure_url_endpoint' => ''
            ];
        }

        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $baseUrl = rtrim($baseUrl, '/');

        return [
            'direct_url' => self::getAbsoluteUrl($relativePath),
            'secure_url' => $baseUrl . "/v1/media/{$mediaType}/{$mediaId}",
            'generate_secure_url_endpoint' => $baseUrl . "/v1/media/{$mediaType}/{$mediaId}/url"
        ];
    }
}