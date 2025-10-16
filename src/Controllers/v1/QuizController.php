<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Services\v1\QuizService;
use Utils\Helper;
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    // Get quiz questions for a specific course
    $app->get('/v1/courses/{course_id}/quiz', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $quizService = new QuizService($container->get(PDO::class));
            $courseId = $args['course_id'];
            
            if (!is_numeric($courseId) || $courseId <= 0) {
                return Helper::errorResponse('Invalid course ID', 400);
            }
            
            $quiz = $quizService->getQuizByCourse($courseId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Quiz questions retrieved successfully',
                'data' => $quiz
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Submit quiz answer
    $app->post('/v1/courses/{course_id}/quiz/submit', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $quizService = new QuizService($container->get(PDO::class));
            $courseId = $args['course_id'];
            $body = $request->getParsedBody();
            
            if (!is_numeric($courseId) || $courseId <= 0) {
                return Helper::errorResponse('Invalid course ID', 400);
            }
            
            // Validate required fields
            if (!isset($body['quiz_id']) || !isset($body['selected_answer'])) {
                return Helper::errorResponse('Quiz ID and selected answer are required', 400);
            }
            
            if (!is_numeric($body['quiz_id']) || $body['quiz_id'] <= 0) {
                return Helper::errorResponse('Invalid quiz ID', 400);
            }
            
            if (empty(trim($body['selected_answer']))) {
                return Helper::errorResponse('Selected answer cannot be empty', 400);
            }
            
            $result = $quizService->submitQuizAnswer(
                $courseId,
                $body['quiz_id'],
                trim($body['selected_answer'])
            );
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Quiz answer submitted successfully',
                'data' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());
};