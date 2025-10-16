<?php

// System
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// Utils
use Utils\Helper;
use Utils\FcmNotification;

// Middlewares
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    #region sendNotification
    $app->post(
        '/v1/notifications/send',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'title' => 'string|required|min:1|max:100',
                    'body' => 'string|required|min:1|max:500',
                    'user_id' => 'int|required',
                    'data' => 'array'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $fcm = new FcmNotification($container->get(PDO::class));
                $success = $fcm->sendToUser(
                    $data['user_id'], 
                    $data['title'], 
                    $data['body'], 
                    $data['data'] ?? []
                );

                if ($success) {
                    return Helper::jsonResponse("Notification sent successfully", 200);
                } else {
                    return Helper::jsonResponse("Failed to send notification", 500);
                }
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['admin']))->add(new JwtMiddleware());

    #region sendLearningReminder
    $app->post(
        '/v1/notifications/learning-reminder',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'user_id' => 'int|required',
                    'lesson_name' => 'string|max:100'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $fcm = new FcmNotification($container->get(PDO::class));
                $success = $fcm->sendLearningReminder(
                    $data['user_id'], 
                    $data['lesson_name'] ?? ''
                );

                if ($success) {
                    return Helper::jsonResponse("Learning reminder sent successfully", 200);
                } else {
                    return Helper::jsonResponse("Failed to send learning reminder", 500);
                }
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['admin']))->add(new JwtMiddleware());

    #region sendToAllUsers
    $app->post(
        '/v1/notifications/broadcast',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'title' => 'string|required|min:1|max:100',
                    'body' => 'string|required|min:1|max:500',
                    'data' => 'array'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $fcm = new FcmNotification($container->get(PDO::class));
                $sentCount = $fcm->sendToAllUsers(
                    $data['title'], 
                    $data['body'], 
                    $data['data'] ?? []
                );

                return Helper::jsonResponse([
                    'message' => 'Broadcast notification sent',
                    'sent_to_users' => $sentCount
                ], 200);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['admin']))->add(new JwtMiddleware());

    #region sendAchievement
    $app->post(
        '/v1/notifications/achievement',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'user_id' => 'int|required',
                    'achievement' => 'string|required|min:1|max:200'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $fcm = new FcmNotification($container->get(PDO::class));
                $success = $fcm->sendAchievement(
                    $data['user_id'], 
                    $data['achievement']
                );

                if ($success) {
                    return Helper::jsonResponse("Achievement notification sent successfully", 200);
                } else {
                    return Helper::jsonResponse("Failed to send achievement notification", 500);
                }
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['admin']))->add(new JwtMiddleware());
};