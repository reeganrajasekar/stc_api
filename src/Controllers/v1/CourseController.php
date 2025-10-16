<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Services\v1\CourseService;
use Utils\Helper;
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    // Get all course categories
    $app->get('/v1/course-categories', function (Request $request, Response $response) use ($container) {
        try {
            $courseService = new CourseService($container->get(PDO::class));
            $categories = $courseService->getAllCourseCategories();
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Course categories retrieved successfully',
                'data' => $categories
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get all courses (with optional category filter)
    $app->get('/v1/courses', function (Request $request, Response $response) use ($container) {
        try {
            $courseService = new CourseService($container->get(PDO::class));
            $queryParams = $request->getQueryParams();
            $categoryId = isset($queryParams['category_id']) ? $queryParams['category_id'] : null;
            
            $courses = $courseService->getAllCourses($categoryId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Courses retrieved successfully',
                'data' => $courses
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get courses by category
    $app->get('/v1/courses/category/{category_id}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $courseService = new CourseService($container->get(PDO::class));
            $categoryId = $args['category_id'];
            
            if (!is_numeric($categoryId) || $categoryId <= 0) {
                return Helper::errorResponse('Invalid category ID', 400);
            }
            
            $courses = $courseService->getCoursesByCategory($categoryId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Courses retrieved successfully',
                'data' => $courses
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get specific course by ID
    $app->get('/v1/courses/{course_id}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $courseService = new CourseService($container->get(PDO::class));
            $courseId = $args['course_id'];
            
            if (!is_numeric($courseId) || $courseId <= 0) {
                return Helper::errorResponse('Invalid course ID', 400);
            }
            
            $course = $courseService->getCourseById($courseId);
            
            if (!$course) {
                return Helper::errorResponse('Course not found', 404);
            }
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Course details retrieved successfully',
                'data' => $course
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());
};