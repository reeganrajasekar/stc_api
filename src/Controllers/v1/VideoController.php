<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Services\v1\VideoService;
use Utils\Helper;
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    // Get all videos with pagination
    $app->get('/v1/videos', function (Request $request, Response $response) use ($container) {
        try {
            $videoService = new VideoService($container->get(PDO::class));
            $queryParams = $request->getQueryParams();
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;
            
            $result = $videoService->getAllVideos($page, $limit);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Videos retrieved successfully',
                'data' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get videos by category with pagination
    $app->get('/v1/videos/category/{category_id}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $videoService = new VideoService($container->get(PDO::class));
            $categoryId = (int)$args['category_id'];
            $queryParams = $request->getQueryParams();
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;
            
            if ($categoryId <= 0) {
                return Helper::errorResponse('Invalid category ID', 400);
            }
            
            $result = $videoService->getVideosByCategory($categoryId, $page, $limit);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Videos retrieved successfully',
                'data' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get specific video by ID
    $app->get('/v1/videos/{video_id}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $videoService = new VideoService($container->get(PDO::class));
            $videoId = (int)$args['video_id'];
            
            if ($videoId <= 0) {
                return Helper::errorResponse('Invalid video ID', 400);
            }
            
            $video = $videoService->getVideoById($videoId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Video retrieved successfully',
                'data' => $video
            ], 200);
            
        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Video not found' ? 404 : 500;
            return Helper::errorResponse($e->getMessage(), $statusCode);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get all video categories
    $app->get('/v1/video-categories', function (Request $request, Response $response) use ($container) {
        try {
            $videoService = new VideoService($container->get(PDO::class));
            $categories = $videoService->getAllVideoCategories();
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Video categories retrieved successfully',
                'data' => $categories
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get specific video category by ID
    $app->get('/v1/video-categories/{category_id}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $videoService = new VideoService($container->get(PDO::class));
            $categoryId = (int)$args['category_id'];
            
            if ($categoryId <= 0) {
                return Helper::errorResponse('Invalid category ID', 400);
            }
            
            $category = $videoService->getCategoryById($categoryId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Category retrieved successfully',
                'data' => $category
            ], 200);
            
        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Category not found' ? 404 : 500;
            return Helper::errorResponse($e->getMessage(), $statusCode);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());
};