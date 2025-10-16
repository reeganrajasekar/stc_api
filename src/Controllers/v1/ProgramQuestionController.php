<?php

// System
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// Utils
use Utils\Helper;

// Service
use Services\v1\ProgramQuestionService;

// Middlewares
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    #region getAvailableQuestionTables
    $app->get(
        '/v1/questions/tables',
        function (Request $req, Response $res) use ($container) {
            try {
                // Service
                $service = new ProgramQuestionService($container->get(PDO::class));
                return $service->getAvailableQuestionTables();
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region getQuestions - Get questions by program, program_type, and category_id
    $app->get(
        '/v1/questions',
        function (Request $req, Response $res) use ($container) {
            try {
                // Get query parameters
                $queryParams = $req->getQueryParams();
                $program = $queryParams['program'] ?? null;
                $programType = $queryParams['program_type'] ?? null;
                $categoryId = isset($queryParams['category_id']) ? (int)$queryParams['category_id'] : null;
                
                // Validate required parameters
                if (!$program) {
                    return Helper::jsonResponse("Missing required parameter: program", 400);
                }
                if (!$programType) {
                    return Helper::jsonResponse("Missing required parameter: program_type", 400);
                }
                if (!$categoryId || $categoryId <= 0) {
                    return Helper::jsonResponse("Missing or invalid required parameter: category_id", 400);
                }
                
                $program = strtolower($program);
                $programType = strtolower($programType);
                
                // Get optional filter parameters
                $filters = [
                    'limit' => isset($queryParams['limit']) ? (int)$queryParams['limit'] : 20,
                    'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0,
                    'random' => isset($queryParams['random']) ? (bool)$queryParams['random'] : false
                ];

                // Validate limit
                if ($filters['limit'] > 100) {
                    return Helper::jsonResponse("Limit cannot exceed 100", 400);
                }
                
                // Service
                $service = new ProgramQuestionService($container->get(PDO::class));
                return $service->getQuestions($program, $programType, $categoryId, $filters);
            } catch (\InvalidArgumentException $e) {
                return Helper::jsonResponse($e->getMessage(), 400);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region getQuestionById - Get specific question by ID
    $app->get(
        '/v1/questions/details',
        function (Request $req, Response $res) use ($container) {
            try {
                // Get query parameters
                $queryParams = $req->getQueryParams();
                $program = $queryParams['program'] ?? null;
                $programType = $queryParams['program_type'] ?? null;
                $questionId = isset($queryParams['question_id']) ? (int)$queryParams['question_id'] : null;
                
                // Validate required parameters
                if (!$program) {
                    return Helper::jsonResponse("Missing required parameter: program", 400);
                }
                if (!$programType) {
                    return Helper::jsonResponse("Missing required parameter: program_type", 400);
                }
                if (!$questionId || $questionId <= 0) {
                    return Helper::jsonResponse("Missing or invalid required parameter: question_id", 400);
                }
                
                $program = strtolower($program);
                $programType = strtolower($programType);
                
                // Service
                $service = new ProgramQuestionService($container->get(PDO::class));
                return $service->getQuestionById($program, $programType, $questionId);
            } catch (\InvalidArgumentException $e) {
                return Helper::jsonResponse($e->getMessage(), 400);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region getQuestionsCount - Get questions count by category
    $app->get(
        '/v1/questions/count',
        function (Request $req, Response $res) use ($container) {
            try {
                // Get query parameters
                $queryParams = $req->getQueryParams();
                $program = $queryParams['program'] ?? null;
                $programType = $queryParams['program_type'] ?? null;
                $categoryId = $queryParams['category_id'] ?? null;
                
                // Validate required parameters
                if (!$program) {
                    return Helper::jsonResponse("Missing required parameter: program", 400);
                }
                if (!$programType) {
                    return Helper::jsonResponse("Missing required parameter: program_type", 400);
                }
                if (!$categoryId || $categoryId <= 0) {
                    return Helper::jsonResponse("Missing or invalid required parameter: category_id", 400);
                }
                
                $program = strtolower($program);
                $programType = strtolower($programType);
                
                // Service
                $service = new ProgramQuestionService($container->get(PDO::class));
                return $service->getQuestionsCount($program, $programType, $categoryId);
            } catch (\InvalidArgumentException $e) {
                return Helper::jsonResponse($e->getMessage(), 400);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region DEBUG - Test POST endpoint
    $app->post(
        '/v1/questions/test',
        function (Request $req, Response $res) use ($container) {
            return Helper::jsonResponse([
                'message' => 'POST endpoint is working',
                'method' => $req->getMethod(),
                'uri' => (string)$req->getUri(),
                'headers' => $req->getHeaders()
            ]);
        }
    );

    #region submitAnswer - Submit answer for a question
    $app->post(
        '/v1/questions/submit',
        function (Request $req, Response $res) use ($container) {
            try {
                // Get JWT token from request
                $token = $req->getAttribute('token');
                if (!$token) {
                    return Helper::jsonResponse("Authentication required", 401);
                }
                
                // Get request body
                $body = $req->getParsedBody();
                if (!$body) {
                    return Helper::jsonResponse("Request body is required", 400);
                }
                
                // Validate required parameters
                $program = isset($body['program']) ? strtolower($body['program']) : null;
                $programType = isset($body['program_type']) ? strtolower($body['program_type']) : null;
                
                if (!$program) {
                    return Helper::jsonResponse("Missing required parameter: program", 400);
                }
                if (!$programType) {
                    return Helper::jsonResponse("Missing required parameter: program_type", 400);
                }
                
                // Validate submission data
                $requiredFields = ['category_id', 'question_id', 'user_answer', 'is_correct', 'time_spent'];
                foreach ($requiredFields as $field) {
                    if (!isset($body[$field])) {
                        return Helper::jsonResponse("Missing required field: $field", 400);
                    }
                }
                
                // Validate data types
                if (!is_numeric($body['category_id']) || (int)$body['category_id'] <= 0) {
                    return Helper::jsonResponse("Invalid category_id", 400);
                }
                if (!is_numeric($body['question_id']) || (int)$body['question_id'] <= 0) {
                    return Helper::jsonResponse("Invalid question_id", 400);
                }
                if (!is_numeric($body['time_spent']) || (int)$body['time_spent'] < 0) {
                    return Helper::jsonResponse("Invalid time_spent", 400);
                }
                
                // Prepare submission data
                $submissionData = [
                    'category_id' => (int)$body['category_id'],
                    'question_id' => (int)$body['question_id'],
                    'user_answer' => $body['user_answer'],
                    'is_correct' => (bool)$body['is_correct'],
                    'time_spent' => (int)$body['time_spent'],
                    'points_earned' => isset($body['points_earned']) ? (int)$body['points_earned'] : null
                ];
                
                // Service
                $service = new ProgramQuestionService($container->get(PDO::class));
                return $service->submitAnswer($token, $program, $programType, $submissionData);
                
            } catch (\InvalidArgumentException $e) {
                return Helper::jsonResponse($e->getMessage(), 400);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region getQuestionsForReview - Get all questions for review with user answers
    $app->get(
        '/v1/questions/review',
        function (Request $req, Response $res) use ($container) {
            try {
                // Get JWT token from request
                $token = $req->getAttribute('token');
                if (!$token) {
                    return Helper::jsonResponse("Authentication required", 401);
                }
                
                // Get query parameters
                $queryParams = $req->getQueryParams();
                $program = $queryParams['program'] ?? null;
                $programType = $queryParams['program_type'] ?? null;
                $categoryId = isset($queryParams['category_id']) ? (int)$queryParams['category_id'] : null;
                
                // Validate required parameters
                if (!$program) {
                    return Helper::jsonResponse("Missing required parameter: program", 400);
                }
                if (!$programType) {
                    return Helper::jsonResponse("Missing required parameter: program_type", 400);
                }
                if (!$categoryId || $categoryId <= 0) {
                    return Helper::jsonResponse("Missing or invalid required parameter: category_id", 400);
                }
                
                $program = strtolower($program);
                $programType = strtolower($programType);
                
                // Service
                $service = new ProgramQuestionService($container->get(PDO::class));
                return $service->getQuestionsForReview($token, $program, $programType, $categoryId);
                
            } catch (\InvalidArgumentException $e) {
                return Helper::jsonResponse($e->getMessage(), 400);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region reviewAndSubmit - Submit all answers for a category
    $app->post(
        '/v1/questions/review-submit',
        function (Request $req, Response $res) use ($container) {
            try {
                // Get JWT token from request
                $token = $req->getAttribute('token');
                if (!$token) {
                    return Helper::jsonResponse("Authentication required", 401);
                }
                
                // Get request body
                $body = $req->getParsedBody();
                if (!$body) {
                    return Helper::jsonResponse("Request body is required", 400);
                }
                
                // Validate required parameters
                $program = isset($body['program']) ? strtolower($body['program']) : null;
                $programType = isset($body['program_type']) ? strtolower($body['program_type']) : null;
                
                if (!$program) {
                    return Helper::jsonResponse("Missing required parameter: program", 400);
                }
                if (!$programType) {
                    return Helper::jsonResponse("Missing required parameter: program_type", 400);
                }
                
                // Validate submission data
                $requiredFields = ['category_id', 'answers', 'total_time_spent'];
                foreach ($requiredFields as $field) {
                    if (!isset($body[$field])) {
                        return Helper::jsonResponse("Missing required field: $field", 400);
                    }
                }
                
                // Validate data types
                if (!is_numeric($body['category_id']) || (int)$body['category_id'] <= 0) {
                    return Helper::jsonResponse("Invalid category_id", 400);
                }
                if (!is_array($body['answers']) || empty($body['answers'])) {
                    return Helper::jsonResponse("Answers must be a non-empty array", 400);
                }
                if (!is_numeric($body['total_time_spent']) || (int)$body['total_time_spent'] < 0) {
                    return Helper::jsonResponse("Invalid total_time_spent", 400);
                }
                
                // Prepare submission data
                $submissionData = [
                    'category_id' => (int)$body['category_id'],
                    'answers' => $body['answers'],
                    'total_time_spent' => (int)$body['total_time_spent']
                ];
                
                // Service
                $service = new ProgramQuestionService($container->get(PDO::class));
                return $service->reviewAndSubmit($token, $program, $programType, $submissionData);
                
            } catch (\InvalidArgumentException $e) {
                return Helper::jsonResponse($e->getMessage(), 400);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());
};