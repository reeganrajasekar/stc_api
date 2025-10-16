<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Services\v1\MediaService;
use Utils\Helper;
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    // Secure audio file streaming endpoint
    $app->get('/v1/media/audio/{file_id}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $token = $request->getAttribute('token');
            $mediaService = new MediaService($container->get(PDO::class));
            $fileId = $args['file_id'];
            
            // Validate file access permissions
            $fileInfo = $mediaService->getAudioFileInfo($fileId, (int)$token->id);
            
            if (!$fileInfo) {
                return Helper::errorResponse('Audio file not found or access denied', 404);
            }
            
            $filePath = $fileInfo['file_path'];
            
            // Check if file exists on server
            if (!file_exists($filePath)) {
                return Helper::errorResponse('Audio file not found on server', 404);
            }
            
            // Get file info
            $fileSize = filesize($filePath);
            $mimeType = $fileInfo['mime_type'] ?? 'audio/mpeg';
            
            // Handle range requests for audio streaming
            $range = $request->getHeaderLine('Range');
            
            if ($range) {
                return $mediaService->streamAudioWithRange($response, $filePath, $fileSize, $mimeType, $range);
            } else {
                return $mediaService->streamAudioFile($response, $filePath, $fileSize, $mimeType);
            }
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Generate secure temporary download URL
    $app->post('/v1/media/audio/{file_id}/url', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $token = $request->getAttribute('token');
            $mediaService = new MediaService($container->get(PDO::class));
            $fileId = $args['file_id'];
            $body = $request->getParsedBody();
            
            // Optional expiry time (default 1 hour)
            $expiryMinutes = isset($body['expiry_minutes']) ? (int)$body['expiry_minutes'] : 60;
            
            if ($expiryMinutes > 1440) { // Max 24 hours
                return Helper::errorResponse('Expiry time cannot exceed 24 hours', 400);
            }
            
            $secureUrl = $mediaService->generateSecureAudioUrl($fileId, (int)$token->id, $expiryMinutes);
            
            if (!$secureUrl) {
                return Helper::errorResponse('Audio file not found or access denied', 404);
            }
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Secure URL generated successfully',
                'data' => [
                    'secure_url' => $secureUrl['url'],
                    'expires_at' => $secureUrl['expires_at'],
                    'file_info' => $secureUrl['file_info']
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Access audio via secure token (for mobile apps)
    $app->get('/v1/media/secure/{token}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $mediaService = new MediaService($container->get(PDO::class));
            $secureToken = $args['token'];
            
            $fileAccess = $mediaService->validateSecureToken($secureToken);
            
            if (!$fileAccess) {
                return Helper::errorResponse('Invalid or expired secure token', 403);
            }
            
            $filePath = $fileAccess['file_path'];
            
            if (!file_exists($filePath)) {
                return Helper::errorResponse('Audio file not found on server', 404);
            }
            
            $fileSize = filesize($filePath);
            $mimeType = $fileAccess['mime_type'] ?? 'audio/mpeg';
            
            // Handle range requests for audio streaming
            $range = $request->getHeaderLine('Range');
            
            if ($range) {
                return $mediaService->streamAudioWithRange($response, $filePath, $fileSize, $mimeType, $range);
            } else {
                return $mediaService->streamAudioFile($response, $filePath, $fileSize, $mimeType);
            }
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    });
};