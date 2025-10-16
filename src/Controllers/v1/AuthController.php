<?php

// System
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// Utils
use Utils\Helper;

// Service
use Services\v1\AuthService;

// Middlewares
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    #region mobileVerification
    $app->post(
        '/v1/auth/mobile-verification',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'country_code' => 'string|required|min:1|max:10',
                    'mobile'       => 'string|required|min:5|max:20',
                    'device_type'  => 'string|required|in:android,ios',
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->mobileVerification($data);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    );

    #region mobileOtpVerification
    $app->post(
        '/v1/auth/mobile-otp-verification',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'country_code' => 'string|required|min:1|max:10',
                    'mobile' => 'string|required|min:5|max:20',
                    'otp'    => 'string|required|min:4|max:4',
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->mobileOtpVerification($data);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    );

    #region mobileOtpResend
    $app->post(
        '/v1/auth/mobile-otp-resend',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'country_code' => 'string|required|min:1|max:10',
                    'mobile'       => 'string|required|min:5|max:20'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->mobileOtpResend($data);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    );

    #region emailVerification
    $app->post(
        '/v1/auth/email-verification',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'email' => 'string|required|min:5|max:255',
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->emailVerification($data, $token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region emailOtpVerification
    $app->post(
        '/v1/auth/email-otp-verification',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input validation
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'email' => 'string|required|min:5|max:255',
                    'otp'    => 'string|required|min:4|max:4',
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->emailOtpVerification($data, $token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region emailOtpResend
    $app->post(
        '/v1/auth/email-otp-resend',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'email' => 'string|required|min:5|max:255',
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->emailOtpResend($data, $token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());


    #region profileCreation
    $app->post(
        '/v1/auth/profile-creation',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'name' => 'string|required|min:2|max:125',
                    'password' => 'string|required|min:6|max:100',
                    'terms' => 'int|in:0,1',
                    'biometric' => 'int|in:0,1',
                    'fcm' => 'string|required'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->profileCreation($data, $token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region login
    $app->post(
        '/v1/auth/login',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'email' => 'string|required|min:5|max:255',
                    'password' => 'string|required|max:100',
                    'device_type'  => 'string|required|in:android,ios',
                    'fcm_token' => 'string|required'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->login($data);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    );

    #region refreshToken
    $app->post(
        '/v1/auth/refresh',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                
                $service = new AuthService($container->get(PDO::class));
                return $service->refreshToken($token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['refresh']))->add(new JwtMiddleware());

    #region forgotPasswordVerification
    $app->post(
        '/v1/auth/forgot-password-verification',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'country_code' => 'string|required|min:1|max:10',
                    'mobile'       => 'string|required|min:5|max:20'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->forgotPasswordVerification($data);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    );

    #region forgotPasswordUpdate
    $app->post(
        '/v1/auth/forgot-password-update',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'password' => 'string|required|min:6|max:100',
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->forgotPasswordUpdate($data, $token);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    )->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    #region register
    $app->post(
        '/v1/auth/register',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'name' => 'string|required|min:2|max:125',
                    'email' => 'string|required|min:5|max:255',
                    'mobile_number' => 'string|required|min:5|max:20',
                    'password' => 'string|required|min:6|max:100',
                    'fcm_token' => 'string|required'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->register($data);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    );

    #region googleSignIn
    $app->post(
        '/v1/auth/google-signin',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $data = $req->getParsedBody();
                $errors = Helper::validateInput($data, [
                    'id_token' => 'string|required',
                    'fcm_token' => 'string',
                    'device_type' => 'string|in:android,ios'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                // Service
                $service = new AuthService($container->get(PDO::class));
                return $service->googleSignIn($data);
            } catch (\PDOException $e) {
                Helper::getLogger()->error("Database error: " . $e->getMessage());
                return Helper::errorResponse("Database error", 500);
            } catch (\Throwable $e) {
                Helper::getLogger()->critical("Server error: " . $e->getMessage());
                return Helper::errorResponse("Something went wrong", 500);
            }
        }
    );

    #region profileUpdate
    $app->put(
        '/v1/auth/profile-update',
        function (Request $req, Response $res) use ($container) {
            try {
                // Input
                $token = $req->getAttribute('token');
                $data = $req->getParsedBody();
                
                // Handle file upload if present (multipart/form-data)
                $uploadedFiles = $req->getUploadedFiles();
                if (isset($uploadedFiles['profile_picture'])) {
                    $uploadedFile = $uploadedFiles['profile_picture'];
                    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                        // Convert uploaded file to base64
                        $fileContent = $uploadedFile->getStream()->getContents();
                        $data['profile_picture'] = base64_encode($fileContent);
                        $data['profile_picture_type'] = 'binary';
                    }
                }
                
                $errors = Helper::validateInput($data, [
                    'name' => 'string|min:2|max:125', 
                    'mobile_number' => 'string|min:5|max:12',
                    'profile_picture' => 'string'
                ]);
                if (!empty($errors)) return Helper::jsonResponse($errors, 400);

                $service = new AuthService($container->get(PDO::class));
                return $service->profileUpdate($data, $token);
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
