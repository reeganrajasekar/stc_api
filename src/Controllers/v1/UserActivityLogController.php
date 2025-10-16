<?php

// System
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// Utils
use Utils\Helper;

// Service
use Services\v1\UserActivityLogService;

// Middlewares
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    #region logActivity
    $app->post(
        '/v1/user/activity-log',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'activity_type' => 'string|required|min:1|max:50',
                    'activity_description' => 'string|max:1000',
                    'ip_address' => 'string|max:45',
                    'device_info' => 'string|max:255'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new UserActivityLogService($container->get(PDO::class));
                return $service->logActivity($data, $token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region getUserActivityLogs
    $app->get(
        '/v1/user/activity-logs',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $queryParams = $req->getQueryParams();
                
                // Validate query parameters
                $allowedParams = ['page', 'limit', 'activity_type', 'from_date', 'to_date'];
                $params = [];
                foreach ($allowedParams as $param) {
                    if (isset($queryParams[$param])) {
                        $params[$param] = $queryParams[$param];
                    }
                }

                // Service
                $service = new UserActivityLogService($container->get(PDO::class));
                return $service->getUserActivityLogs($token, $params);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region getActivityLogById
    $app->get(
        '/v1/user/activity-log/{log_id}',
        function (Request $req, Response $res, array $args) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $logId = (int)$args['log_id'];

                if ($logId <= 0) {
                    return Helper::jsonResponse("Invalid log ID", 400);
                }

                // Service
                $service = new UserActivityLogService($container->get(PDO::class));
                return $service->getActivityLogById($logId, $token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region getActivityStats
    $app->get(
        '/v1/user/activity-stats',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $queryParams = $req->getQueryParams();
                
                // Validate query parameters
                $allowedParams = ['from_date', 'to_date'];
                $params = [];
                foreach ($allowedParams as $param) {
                    if (isset($queryParams[$param])) {
                        $params[$param] = $queryParams[$param];
                    }
                }

                // Service
                $service = new UserActivityLogService($container->get(PDO::class));
                return $service->getActivityStats($token, $params);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region deleteActivityLog
    $app->delete(
        '/v1/user/activity-log/{log_id}',
        function (Request $req, Response $res, array $args) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $logId = (int)$args['log_id'];

                if ($logId <= 0) {
                    return Helper::jsonResponse("Invalid log ID", 400);
                }

                // Service
                $service = new UserActivityLogService($container->get(PDO::class));
                return $service->deleteActivityLog($logId, $token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region clearUserActivityLogs
    $app->delete(
        '/v1/user/activity-logs',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $queryParams = $req->getQueryParams();
                
                // Validate query parameters
                $allowedParams = ['activity_type', 'before_date'];
                $params = [];
                foreach ($allowedParams as $param) {
                    if (isset($queryParams[$param])) {
                        $params[$param] = $queryParams[$param];
                    }
                }

                // Service
                $service = new UserActivityLogService($container->get(PDO::class));
                return $service->clearUserActivityLogs($token, $params);
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