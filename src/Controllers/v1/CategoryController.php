<?php

// System
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// Utils
use Utils\Helper;

// Service
use Services\v1\CategoryService;

// Middlewares
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    #region getAvailablePrograms
    $app->get(
        '/v1/categories/programs',
        function (Request $req, Response $res) use ($container) {
            try {
                // Service
                $service = new CategoryService($container->get(PDO::class));
                return $service->getAvailablePrograms();
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region getCategories - Dynamic with query parameters
    $app->get(
        '/v1/categories',
        function (Request $req, Response $res) use ($container) {
            try {
                // Get query parameters
                $queryParams = $req->getQueryParams();
                $program = $queryParams['program'] ?? null;
                $programType = $queryParams['program_type'] ?? null;
                
                // Validate required parameters
                if (!$program) {
                    return Helper::jsonResponse("Missing required parameter: program", 400);
                }
                if (!$programType) {
                    return Helper::jsonResponse("Missing required parameter: program_type", 400);
                }
                
                $program = strtolower($program);
                $programType = strtolower($programType);
                
                // Get token from middleware
                $token = $req->getAttribute('token');
                
                // Service
                $service = new CategoryService($container->get(PDO::class));
                return $service->getCategories($token, $program, $programType);
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

    #region getFilteredCategories
    $app->get(
        '/v1/categories/filtered',
        function (Request $req, Response $res) use ($container) {
            try {
                // Get query parameters
                $queryParams = $req->getQueryParams();
                $program = $queryParams['program'] ?? null;
                $programType = $queryParams['program_type'] ?? null;
                
                // Validate required parameters
                if (!$program) {
                    return Helper::jsonResponse("Missing required parameter: program", 400);
                }
                if (!$programType) {
                    return Helper::jsonResponse("Missing required parameter: program_type", 400);
                }
                
                $program = strtolower($program);
                $programType = strtolower($programType);
                
                // Get filter parameters
                $filters = [
                    'status' => $queryParams['status'] ?? null,
                    'min_score' => isset($queryParams['min_score']) ? (float)$queryParams['min_score'] : null,
                    'max_score' => isset($queryParams['max_score']) ? (float)$queryParams['max_score'] : null
                ];

                // Validate status if provided
                if ($filters['status'] && !in_array($filters['status'], ['passed', 'failed', 'not_started'])) {
                    return Helper::jsonResponse("Invalid status. Allowed values: passed, failed, not_started", 400);
                }

                // Get token from middleware
                $token = $req->getAttribute('token');
                
                // Service
                $service = new CategoryService($container->get(PDO::class));
                return $service->getFilteredCategories($token, $program, $programType, $filters);
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

    #region getProgressSummary
    $app->get(
        '/v1/categories/progress-summary',
        function (Request $req, Response $res) use ($container) {
            try {
                // Get query parameters
                $queryParams = $req->getQueryParams();
                $program = $queryParams['program'] ?? null;
                $programType = $queryParams['program_type'] ?? null;
                
                // Validate required parameters
                if (!$program) {
                    return Helper::jsonResponse("Missing required parameter: program", 400);
                }
                if (!$programType) {
                    return Helper::jsonResponse("Missing required parameter: program_type", 400);
                }
                
                $program = strtolower($program);
                $programType = strtolower($programType);
                
                // Get token from middleware
                $token = $req->getAttribute('token');
                
                // Service
                $service = new CategoryService($container->get(PDO::class));
                return $service->getUserProgressSummary($token, $program, $programType);
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

    #region getCategoryDetails
    $app->get(
        '/v1/categories/details',
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

                // Get token from middleware
                $token = $req->getAttribute('token');
                
                // Service
                $service = new CategoryService($container->get(PDO::class));
                return $service->getCategoryDetails($token, $program, $programType, $categoryId);
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