<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Services\v1\UserCourseProgressService;
use Utils\Helper;
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    // Start a course (creates initial progress record)
    $app->post('/v1/courses/{course_id}/start', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $token = $request->getAttribute('token');
            $progressService = new UserCourseProgressService($container->get(PDO::class));
            $userId = (int)$token->id;
            $courseId = (int)$args['course_id'];
            
            if ($courseId <= 0) {
                return Helper::errorResponse('Invalid course ID', 400);
            }
            
            $progress = $progressService->startCourse($userId, $courseId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Course started successfully',
                'data' => $progress
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Mark lesson as complete
    $app->post('/v1/courses/{course_id}/lessons/{lesson_id}/complete', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $token = $request->getAttribute('token');
            $progressService = new UserCourseProgressService($container->get(PDO::class));
            $userId = (int)$token->id;
            $courseId = (int)$args['course_id'];
            $lessonId = (int)$args['lesson_id'];
            
            if ($courseId <= 0 || $lessonId <= 0) {
                return Helper::errorResponse('Invalid course ID or lesson ID', 400);
            }
            
            $progress = $progressService->markLessonComplete($userId, $courseId, $lessonId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Lesson marked as complete successfully',
                'data' => $progress
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get user's progress for a specific course
    $app->get('/v1/courses/{course_id}/progress', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $token = $request->getAttribute('token');
            $progressService = new UserCourseProgressService($container->get(PDO::class));
            $userId = (int)$token->id;
            $courseId = (int)$args['course_id'];
            
            if ($courseId <= 0) {
                return Helper::errorResponse('Invalid course ID', 400);
            }
            
            $progress = $progressService->getUserCourseProgress($userId, $courseId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Course progress retrieved successfully',
                'data' => $progress
            ], 200);
            
        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Course progress not found' ? 404 : 500;
            return Helper::errorResponse($e->getMessage(), $statusCode);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get all user's course progress
    $app->get('/v1/my-progress', function (Request $request, Response $response) use ($container) {
        try {
            $token = $request->getAttribute('token');
            $progressService = new UserCourseProgressService($container->get(PDO::class));
            $userId = (int)$token->id;
            
            $progressList = $progressService->getAllUserProgress($userId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'User progress retrieved successfully',
                'data' => $progressList
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get user's dashboard/summary
    $app->get('/v1/my-dashboard', function (Request $request, Response $response) use ($container) {
        try {
            $token = $request->getAttribute('token');
            $progressService = new UserCourseProgressService($container->get(PDO::class));
            $userId = (int)$token->id;
            
            $progressList = $progressService->getAllUserProgress($userId);
            
            // Calculate summary statistics
            $totalCourses = count($progressList);
            $completedCourses = count(array_filter($progressList, fn($p) => $p['course_completed'] == 1));
            $inProgressCourses = count(array_filter($progressList, fn($p) => $p['completion_percentage'] > 0 && $p['course_completed'] == 0));
            $totalPointsEarned = array_sum(array_column($progressList, 'total_points_earned'));
            $averageCompletion = $totalCourses > 0 ? round(array_sum(array_column($progressList, 'completion_percentage')) / $totalCourses, 2) : 0;
            
            // Get recently accessed courses
            $recentCourses = array_slice($progressList, 0, 5);
            
            $dashboard = [
                'summary' => [
                    'total_courses' => $totalCourses,
                    'completed_courses' => $completedCourses,
                    'in_progress_courses' => $inProgressCourses,
                    'total_points_earned' => $totalPointsEarned,
                    'average_completion_percentage' => $averageCompletion
                ],
                'recent_courses' => $recentCourses,
                'all_progress' => $progressList
            ];
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'User dashboard retrieved successfully',
                'data' => $dashboard
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());
};