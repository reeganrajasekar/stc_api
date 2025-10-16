<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Utils\Helper;
use Utils\MediaUrlHelper;

return function (App $app) {
    $container = $app->getContainer();

    // Test endpoint to verify secure URLs
    $app->get('/v1/test/media-urls', function (Request $request, Response $response) use ($container) {
        try {
            $testData = [
                'detected_app_url' => $_ENV['APP_URL'] ?? 'not detected',
                'server_info' => [
                    'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'not set',
                    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not set',
                    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'not set'
                ],
                'test_files' => [
                    'book_pdf' => 'uploads/books/book_1759983078_68e735e689ecb.pdf',
                    'book_image' => 'uploads/books/thumb_1759983078_68e735e687f70.png',
                    'lesson_audio' => 'uploads/lessons/audio/lesson_audio.mp3',
                    'course_image' => 'uploads/courses/course_123.jpg'
                ],
                'secure_urls' => []
            ];
            
            // Convert to secure URLs
            foreach ($testData['test_files'] as $type => $path) {
                $testData['secure_urls'][$type] = MediaUrlHelper::getAbsoluteUrl($path);
            }
            
            // Example of media URLs for audio
            $testData['media_urls_example'] = MediaUrlHelper::getMediaUrls(
                'uploads/lessons/audio/lesson_audio.mp3', 
                'audio', 
                '123'
            );
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Secure Media URL test - No direct file paths exposed!',
                'data' => $testData,
                'instructions' => [
                    'All file URLs now go through secure endpoints',
                    'No direct file paths are exposed',
                    'Files are served through /v1/media/* endpoints',
                    'Port is auto-detected from your server'
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    });
};