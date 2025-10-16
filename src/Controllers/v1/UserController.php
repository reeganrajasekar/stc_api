<?php

// System
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// Utils
use Utils\Helper;

// Service
use Services\v1\UserService;

// Middlewares
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    #region userById
    $app->get(
        '/v1/user',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');

                // Service
                $service = new UserService($container->get(PDO::class));
                return $service->userById($token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region editUser
    $app->put(
        '/v1/user',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'name' => 'string|required|min:2|max:125',
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new UserService($container->get(PDO::class));
                return $service->editUser($data, $token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region editBiometricUser
    $app->put(
        '/v1/user/biometric',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'biometric' => 'int|required|in:0,1',
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new UserService($container->get(PDO::class));
                return $service->editBiometricUser($data, $token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region deleteUser
    $app->delete(
        '/v1/user',
        function (Request $req, Response $res, array $args) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');

                // Service
                $service = new UserService($container->get(PDO::class));
                return $service->deleteUser($token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());


    #region change_password
    $app->post(
        '/v1/user/change-password',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'current_password' => 'string|required|min:6|max:100',
                    'new_password' => 'string|required|min:6|max:100',
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new UserService($container->get(PDO::class));
                return $service->changePassword($data, $token);
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
