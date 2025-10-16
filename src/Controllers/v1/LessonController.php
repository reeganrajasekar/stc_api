<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Services\v1\LessonService;
use Utils\Helper;
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    // Get all lessons for a specific course
    $app->get('/v1/courses/{course_id}/lessons', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $lessonService = new LessonService($container->get(PDO::class));
            $courseId = $args['course_id'];
            
            if (!is_numeric($courseId) || $courseId <= 0) {
                return Helper::errorResponse('Invalid course ID', 400);
            }
            
            $lessons = $lessonService->getLessonsByCourse($courseId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Lessons retrieved successfully',
                'data' => $lessons
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get specific lesson content
    $app->get('/v1/courses/{course_id}/lessons/{lesson_id}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $lessonService = new LessonService($container->get(PDO::class));
            $courseId = $args['course_id'];
            $lessonId = $args['lesson_id'];
            
            if (!is_numeric($courseId) || $courseId <= 0) {
                return Helper::errorResponse('Invalid course ID', 400);
            }
            
            if (!is_numeric($lessonId) || $lessonId <= 0) {
                return Helper::errorResponse('Invalid lesson ID', 400);
            }
            
            $lesson = $lessonService->getLessonContent($courseId, $lessonId);
            
            if (!$lesson) {
                return Helper::errorResponse('Lesson not found', 404);
            }
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Lesson content retrieved successfully',
                'data' => $lesson
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());
};