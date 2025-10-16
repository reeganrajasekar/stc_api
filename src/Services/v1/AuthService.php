<?php

namespace Services\v1;

// System
use PDO;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;

// Utils
use Utils\Helper;
use Utils\Sms;
use Utils\Mail;
use Utils\ActivityLogger;

class AuthService
{
    protected  $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    #region mobileVerification
    public function mobileVerification(array $data): Response
    {
        try {
            // Check if user already exists
            $sql = "SELECT 
                        * 
                    FROM 
                        users 
                    WHERE 
                        mobile_number = :mobile_number 
                        AND is_active = 1;
                    ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':mobile_number', (string)$data['mobile'], PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch();

            if (!$row) {
                // Insert new user
                $sql = "INSERT INTO 
                            users (
                                full_name, email, mobile_number, password_hash, fcm_token
                            )
                        VALUES 
                            (
                                '', '', :mobile_number, '', ''
                            );
                        ";

                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':mobile_number', (string)$data['mobile'], PDO::PARAM_STR);
                $stmt->execute();
            } else {
                if ($row['password_hash'] != '') return Helper::jsonResponse("User already exists", 409);
            }

           
            if ($_ENV['APP_DEBUG'] == 'true') {
                $otp = 1111;
            } else {
                $otp = random_int(1000, 9999);
            }
            // Use hardcoded +91 country code
            $smsSent = Sms::sendRegisterationOtp($this->db, '+91', $data['mobile'], $otp);
            if (!$smsSent) return Helper::jsonResponse("Failed to send OTP SMS", 500);

            $sql = "INSERT INTO otp_mobile (mobile_number, otp, is_deleted) VALUES (:mobile_number, :otp, 0);";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':mobile_number', (string)$data['mobile'], PDO::PARAM_STR);
            $stmt->bindValue(':otp', (string)password_hash($otp, PASSWORD_DEFAULT), PDO::PARAM_STR);
            $stmt->execute();

            return Helper::jsonResponse("OTP sent successfully to your mobile.");
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region mobileOtpVerification
    public function mobileOtpVerification(array $data): Response
    {
        try {
            // Fetch latest OTP entry for the mobile number
            $sql = "SELECT u.id, om.otp 
                    FROM users u
                    JOIN otp_mobile om ON u.mobile_number = om.mobile_number
                    WHERE u.mobile_number = :mobile_number AND u.is_active = 1 AND om.is_deleted = 0
                    ORDER BY om.created_at DESC
                    LIMIT 1;";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':mobile_number', (string)$data['mobile'], PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return Helper::jsonResponse("Invalid mobile number or OTP not found", 404);
            }

            // Verify OTP
            if (!password_verify($data['otp'], $row['otp'])) {
                return Helper::jsonResponse("Invalid OTP", 401);
            }

            // Optionally: Mark OTP as used or soft-delete
            $sql = "UPDATE otp_mobile SET is_deleted = 1 WHERE mobile_number = :mobile_number;
                    UPDATE users SET is_verified = 1 WHERE id = :id;
                    ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':mobile_number', (string)$data['mobile'], PDO::PARAM_STR);
            $stmt->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
            $stmt->execute();

            // You can issue a JWT token or proceed with further steps here
            $issuedAt = time();
            $accessExpireAt  = $issuedAt + (2 * 60 * 60);         // 2 hours = 7200 seconds
            $refreshExpireAt = $issuedAt + (365 * 24 * 60 * 60);  // 365 days = 31,536,000 seconds

            $accessPayload = [
                'iat' => $issuedAt,
                'iss' => $_ENV['JWT_ISSUER'],
                'nbf' => $issuedAt,
                'exp' => $accessExpireAt,
                'id' => $row['id'],
                'role' => 'user',
            ];

            $refreshPayload = [
                'iat' => $issuedAt,
                'iss' => $_ENV['JWT_ISSUER'],
                'nbf' => $issuedAt,
                'exp' => $refreshExpireAt,
                'id' => $row['id'],
                'role' => 'refresh',
            ];
            $user['access_token'] = JWT::encode($accessPayload, $_ENV['JWT_SECRET'], 'HS256');
            $user['refresh_token'] = JWT::encode($refreshPayload, $_ENV['JWT_SECRET'], 'HS256');
            
            // Log OTP verification activity
            ActivityLogger::logOtpVerification($this->db, $row['id'], 'mobile', true, ActivityLogger::getClientIp(), ActivityLogger::getDeviceInfo());
            
            return Helper::jsonResponse($user);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region mobileOtpResend
    public function mobileOtpResend(array $data): Response
    {
        try {
            $sql = "SELECT * FROM otp_mobile
                    WHERE mobile_number = :mobile_number AND is_deleted = 0;
                    ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':mobile_number', (string)$data['mobile'], PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return Helper::jsonResponse("Invalid mobile number", 404);
            }

            // Generate OTP
            if ($_ENV['APP_DEBUG'] == 'true') {
                $otp = 1111;
            } else {
                $otp = random_int(1000, 9999);
            }
            // Use hardcoded +91 country code
            $smsSent = Sms::sendRegisterationOtp($this->db, '+91', $data['mobile'], $otp);
            if (!$smsSent) return Helper::jsonResponse("Failed to send OTP SMS", 500);

            $sql = "INSERT INTO otp_mobile (mobile_number, otp, is_deleted) VALUES (:mobile_number, :otp, 0);";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':mobile_number', (string)$data['mobile'], PDO::PARAM_STR);
            $stmt->bindValue(':otp', (string)password_hash($otp, PASSWORD_DEFAULT), PDO::PARAM_STR);
            $stmt->execute();

            return Helper::jsonResponse("OTP resent successfully to your mobile.");
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region emailVerification
    public function emailVerification(array $data, object $token): Response
    {
        try {
            // Check if user already exists
            $sql = "SELECT id,password_hash FROM users WHERE email = :email AND NOT id = :id AND is_active = 1;";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', (string)$data['email'], PDO::PARAM_STR);
            $stmt->bindValue(':id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();

            if (!$row) {
                // Update mail
                $sql = "UPDATE users SET email = :email WHERE id = :id;";

                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':email', (string)$data['email'], PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$token->id, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                if ($row['password_hash'] != '') return Helper::jsonResponse("Email already exists", 409);
            }

            // Generate OTP
            if ($_ENV['APP_DEBUG'] == 'true') {
                $otp = 1111;
            } else {
                $otp = random_int(1000, 9999);
            }
            $templatePath = __DIR__ . '/../../Templates/v1/otp_verification_template.php';
            $htmlBody = Mail::renderTemplate($templatePath, [
                'otp' => $otp
            ]);
            // $mailSent = Mail::queue($this->db, $data['email'], "OTP", $htmlBody);
            $mailSent = Mail::sendMail($data['email'], "Registration - OTP", $htmlBody);
            if (!$mailSent) return Helper::jsonResponse("Failed to send OTP Mail", 500);

            $sql = "INSERT INTO otp_email (email, otp, is_deleted) VALUES (:email, :otp, 0);";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', (string)$data['email'], PDO::PARAM_STR);
            $stmt->bindValue(':otp', (string)password_hash($otp, PASSWORD_DEFAULT), PDO::PARAM_STR);
            $stmt->execute();

            return Helper::jsonResponse("OTP sent successfully to your mail.");
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region emailOtpVerification
    public function emailOtpVerification(array $data, object $token): Response
    {
        try {
            // Fetch latest OTP entry for the email
            $sql = "SELECT u.id, om.otp 
                    FROM users u
                    JOIN otp_email om ON u.email = om.email
                    WHERE u.email = :email AND u.id = :id AND u.is_active = 1 AND om.is_deleted = 0
                    ORDER BY om.created_at DESC
                    LIMIT 1;";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', (string)$data['email'], PDO::PARAM_STR);
            $stmt->bindValue(':id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return Helper::jsonResponse("Invalid Mail or OTP not found", 404);
            }

            // Verify OTP
            if (!password_verify($data['otp'], $row['otp'])) {
                return Helper::jsonResponse("Invalid OTP", 401);
            }

            // Optionally: Mark OTP as used or soft-delete
            $sql = "UPDATE otp_email SET is_deleted = 1 WHERE email = :email;
                    UPDATE users SET is_verified = 1 WHERE id = :id;
                    ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', (string)$data['email'], PDO::PARAM_STR);
            $stmt->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
            $stmt->execute();

            return Helper::jsonResponse([]);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region emailOtpResend
    public function emailOtpResend(array $data, object $token): Response
    {
        try {
            $sql = "SELECT * FROM otp_email
                    WHERE email = :email AND is_deleted = 0;";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', (string)$data['email'], PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return Helper::jsonResponse("Invalid Email", 404);
            }

            // Generate OTP
            if ($_ENV['APP_DEBUG'] == 'true') {
                $otp = 1111;
            } else {
                $otp = random_int(1000, 9999);
            }
            $templatePath = __DIR__ . '/../../Templates/v1/otp_resend_template.php';
            $htmlBody = Mail::renderTemplate($templatePath, [
                'otp' => $otp
            ]);
            // $mailSent = Mail::queue($this->db, $data['email'], "OTP", $htmlBody);
            $mailSent = Mail::sendMail($data['email'], "Registration - OTP", $htmlBody);
            if (!$mailSent) return Helper::jsonResponse("Failed to send OTP Mail", 500);

            $sql = "INSERT INTO otp_email (email, otp, is_deleted) VALUES (:email, :otp, 0);";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', (string)$data['email'], PDO::PARAM_STR);
            $stmt->bindValue(':otp', (string)password_hash($otp, PASSWORD_DEFAULT), PDO::PARAM_STR);
            $stmt->execute();

            return Helper::jsonResponse("OTP sent successfully to your mail.");
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }

   
    #region login
    public function login(array $data): Response
    {
        try {
            $sql = "SELECT id,full_name,email,mobile_number,password_hash 
                    FROM users 
                    WHERE (email = :email OR mobile_number = :email) AND is_active = 1;";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', (string)$data['email'], PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($data['password'], $user['password_hash'])) {

                $sql = "UPDATE users 
                        SET 
                            fcm_token = :fcm_token,
                            last_login = CURRENT_TIMESTAMP
                        WHERE id = :id;";

                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':fcm_token', (string)$data['fcm_token'], PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$user['id'], PDO::PARAM_INT);
                $stmt->execute();

                $issuedAt = time();
                $accessExpireAt  = $issuedAt + (2 * 60 * 60);         // 2 hours = 7200 seconds
                $refreshExpireAt = $issuedAt + (365 * 24 * 60 * 60);  // 365 days = 31,536,000 seconds

                $accessPayload = [
                    'iat' => $issuedAt,
                    'iss' => $_ENV['JWT_ISSUER'],
                    'nbf' => $issuedAt,
                    'exp' => $accessExpireAt,
                    'id' => $user['id'],
                    'role' => 'user',
                ];

                $refreshPayload = [
                    'iat' => $issuedAt,
                    'iss' => $_ENV['JWT_ISSUER'],
                    'nbf' => $issuedAt,
                    'exp' => $refreshExpireAt,
                    'id' => $user['id'],
                    'role' => 'refresh',
                ];

                $user['access_token'] = JWT::encode($accessPayload, $_ENV['JWT_SECRET'], 'HS256');
                $user['refresh_token'] = JWT::encode($refreshPayload, $_ENV['JWT_SECRET'], 'HS256');
                unset($user['password_hash']);
                
                // Log successful login activity
                ActivityLogger::logLogin($this->db, $user['id'], true, ActivityLogger::getClientIp(), ActivityLogger::getDeviceInfo());
                
                return Helper::jsonResponse($user);
            }

            // Log failed login attempt if user exists
            if ($user) {
                ActivityLogger::logLogin($this->db, $user['id'], false, ActivityLogger::getClientIp(), ActivityLogger::getDeviceInfo());
            }
            
            return Helper::jsonResponse("Invalid credentials", 401);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region refreshToken
    public function refreshToken(object $token): Response
    {
        try {
            $issuedAt = time();
            $accessExpireAt  = $issuedAt + (2 * 60 * 60);         // 2 hours = 7200 seconds
            $refreshExpireAt = $issuedAt + (365 * 24 * 60 * 60);  // 365 days = 31,536,000 seconds

            $accessPayload = [
                'iat' => $issuedAt,
                'iss' => $_ENV['JWT_ISSUER'],
                'nbf' => $issuedAt,
                'exp' => $accessExpireAt,
                'id' => $token->id,
                'role' => 'user',
            ];

            $refreshPayload = [
                'iat' => $issuedAt,
                'iss' => $_ENV['JWT_ISSUER'],
                'nbf' => $issuedAt,
                'exp' => $refreshExpireAt,
                'id' => $token->id,
                'role' => 'refresh',
            ];

            $stmt = $this->db->prepare("INSERT INTO user_activity_log (user_id, activity_type, activity_description, ip_address, device_info) VALUES (:user_id, :activity_type, :activity_description, :ip_address, :device_info)");
            $stmt->bindValue("user_id", (int)$token->id, PDO::PARAM_INT);
            $stmt->bindValue("activity_type", "token_refresh", PDO::PARAM_STR);
            $stmt->bindValue("activity_description", "Access token refreshed", PDO::PARAM_STR);
            $stmt->bindValue("ip_address", $_SERVER['REMOTE_ADDR'] ?? 'unknown', PDO::PARAM_STR);
            $stmt->bindValue("device_info", $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', PDO::PARAM_STR);
            $stmt->execute();

            $token = [];
            $token['access_token'] = JWT::encode($accessPayload, $_ENV['JWT_SECRET'], 'HS256');
            $token['refresh_token'] = JWT::encode($refreshPayload, $_ENV['JWT_SECRET'], 'HS256');
            return Helper::jsonResponse($token, 201);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region forgotPasswordVerification
    public function forgotPasswordVerification(array $data): Response
    {
        try {
            $sql = "SELECT id,email FROM users WHERE mobile_number = :mobile_number AND is_active = 1;";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':mobile_number', (string)$data['mobile'], PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch();

            if (!$user) return Helper::jsonResponse("User not exists", 404);

            // Generate OTP
            if ($_ENV['APP_DEBUG'] == 'true') {
                $otp = 1111;
            } else {
                $otp = random_int(1000, 9999);
            }
            // Use hardcoded +91 country code for SMS
            $smsSent = Sms::sendForgetPasswordOtp($this->db, '+91', $data['mobile'], $otp);
            if (!$smsSent) return Helper::jsonResponse("Failed to send OTP SMS", 500);
            // Mail OTP
            $templatePath = __DIR__ . '/../../Templates/v1/otp_forget_password_template.php';
            $htmlBody = Mail::renderTemplate($templatePath, [
                'otp' => $otp
            ]);
            // $mailSent = Mail::queue($this->db, $user['email'], "OTP", $htmlBody);
            $mailSent = Mail::sendMail($user['email'], "Forget Password - OTP", $htmlBody);
            if (!$mailSent) return Helper::jsonResponse("Failed to send OTP Mail", 500);

            $sql = "INSERT INTO otp_mobile (mobile_number, otp, is_deleted) VALUES (:mobile_number, :otp, 0);";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':mobile_number', (string)$data['mobile'], PDO::PARAM_STR);
            $stmt->bindValue(':otp', (string)password_hash($otp, PASSWORD_DEFAULT), PDO::PARAM_STR);
            $stmt->execute();

            return Helper::jsonResponse("OTP sent successfully to your mobile.");
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region forgotPasswordUpdate
    public function forgotPasswordUpdate(array $data, object $token): Response
    {
        try {
            $sql = "UPDATE 
                        users 
                    SET 
                        password_hash = :password_hash
                    WHERE 
                        id = :id;
                    ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':password_hash', (string)password_hash($data['password'], PASSWORD_DEFAULT), PDO::PARAM_STR);
            $stmt->bindValue(':id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();

            return Helper::jsonResponse("Account Password Changed successfully!", 201);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }
    public function googleSignIn(array $data): Response
    {
        try { 
            $this->db->beginTransaction(); 
            $googleUser = \Utils\GoogleAuth::verifyGoogleToken($data['id_token']);
            
      
            if ($googleUser) {
                $this->db->rollBack();
                return Helper::jsonResponse($googleUser , 401);
            }
 
            $sql = "SELECT id, full_name, email, mobile_number, google_uid, auth_provider, password_hash, profile_picture 
                    FROM users 
                    WHERE (email = :email OR google_uid = :google_uid) AND is_active = 0;";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', (string)$googleUser['email'], PDO::PARAM_STR);
            $stmt->bindValue(':google_uid', (string)$googleUser['google_id'], PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Existing user - check for auth provider conflicts
                if ($user['auth_provider'] !== 'google' && !empty($user['password_hash'])) {
                    $this->db->rollBack();
                    return Helper::jsonResponse("Email already registered with different login method. Please use email/password login or reset your password.", 409);
                }

                // Update existing user with Google info
                $sql = "UPDATE users 
                        SET 
                            full_name = COALESCE(NULLIF(:name, ''), full_name),
                            fcm_token = :fcm,
                            google_uid = :google_uid,
                            auth_provider = 'google',
                            is_email_verified = 1,
                            profile_picture = COALESCE(NULLIF(:profile_picture, ''), profile_picture),
                            profile_picture_type = CASE 
                                WHEN :profile_picture != '' THEN 'url' 
                                ELSE profile_picture_type 
                            END,
                            updated_at = CURRENT_TIMESTAMP,
                            last_login = CURRENT_TIMESTAMP
                        WHERE id = :id;";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':name', (string)$googleUser['name'], PDO::PARAM_STR);
                $stmt->bindValue(':fcm', (string)($data['fcm_token'] ?? ''), PDO::PARAM_STR);
                $stmt->bindValue(':google_uid', (string)$googleUser['google_id'], PDO::PARAM_STR);
                $stmt->bindValue(':profile_picture', (string)($googleUser['picture'] ?? ''), PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$user['id'], PDO::PARAM_INT);
                $stmt->execute();

                // Update user array with new values
                $user['full_name'] = $googleUser['name'] ?: $user['full_name'];
                $user['google_uid'] = $googleUser['google_id'];
                $user['auth_provider'] = 'google';
                $user['profile_picture'] = $googleUser['picture'] ?: $user['profile_picture'];
                
            } else {
                // Check if email exists with different auth provider
                $sql = "SELECT id, password_hash FROM users WHERE email = :email AND is_active = 1;";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':email', (string)$googleUser['email'], PDO::PARAM_STR);
                $stmt->execute();
                $emailExists = $stmt->fetch();

                if ($emailExists && !empty($emailExists['password_hash'])) {
                    $this->db->rollBack();
                    return Helper::jsonResponse("Email already registered with different login method. Please use email/password login or reset your password.", 409);
                }

                // Create new user account
                $sql = "INSERT INTO users (
                            full_name, email, mobile_number, password_hash, fcm_token, google_uid, 
                            auth_provider, is_email_verified, is_mobile_verified, 
                            profile_picture, profile_picture_type, device_type,  is_active, is_verified
                        ) VALUES (
                            :name, :email, '', '', :fcm, :google_uid, 
                            'google', 1, 0, 
                            :profile_picture, :profile_picture_type, :device_type,
                             1, 1
                        );";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':name', (string)$googleUser['name'], PDO::PARAM_STR);
                $stmt->bindValue(':email', (string)$googleUser['email'], PDO::PARAM_STR);
                $stmt->bindValue(':fcm', (string)($data['fcm_token'] ?? ''), PDO::PARAM_STR);
                $stmt->bindValue(':google_uid', (string)$googleUser['google_id'], PDO::PARAM_STR);
                $stmt->bindValue(':profile_picture', (string)($googleUser['picture'] ?? ''), PDO::PARAM_STR);
                $stmt->bindValue(':profile_picture_type', $googleUser['picture'] ? 'url' : '', PDO::PARAM_STR);
                $stmt->bindValue(':device_type', (string)($data['device_type'] ?? ''), PDO::PARAM_STR);
                $stmt->execute();

                $userId = $this->db->lastInsertId();
                $user = [
                    'id' => $userId,
                    'full_name' => $googleUser['name'],
                    'email' => $googleUser['email'],
                    'mobile_number' => '',
                    'google_uid' => $googleUser['google_id'],
                    'auth_provider' => 'google',
                    'profile_picture' => $googleUser['picture'] ?? '',
                    'is_email_verified' => 1,
                    'is_mobile_verified' => 0
                ];
            }

            // Generate JWT tokens
            $issuedAt = time();
            $accessExpireAt = $issuedAt + (2 * 60 * 60);         // 2 hours
            $refreshExpireAt = $issuedAt + (365 * 24 * 60 * 60); // 365 days

            $accessPayload = [
                'iat' => $issuedAt,
                'iss' => $_ENV['JWT_ISSUER'],
                'nbf' => $issuedAt,
                'exp' => $accessExpireAt,
                'id' => $user['id'],
                'role' => 'user',
            ];

            $refreshPayload = [
                'iat' => $issuedAt,
                'iss' => $_ENV['JWT_ISSUER'],
                'nbf' => $issuedAt,
                'exp' => $refreshExpireAt,
                'id' => $user['id'],
                'role' => 'refresh',
            ];

            $user['access_token'] = JWT::encode($accessPayload, $_ENV['JWT_SECRET'], 'HS256');
            $user['refresh_token'] = JWT::encode($refreshPayload, $_ENV['JWT_SECRET'], 'HS256');
            
            // Remove sensitive data from response
            unset($user['password_hash'], $user['google_uid']);

            // Commit transaction
            $this->db->commit();

            return Helper::jsonResponse($user);

        } catch (\Throwable $e) {
            // Rollback transaction on any error
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Helper::getLogger()->error("AuthService Google sign-in error: " . $e->getMessage());
            throw $e;
        }
    }
    #region profileCreation
    public function profileCreation(array $data, object $token): Response
    {
        try {
            $sql = "UPDATE users 
                    SET 
                        full_name = :name,
                        password_hash = :password,
                        fcm_token = :fcm
                    WHERE id = :id;";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':name', (string)$data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':password', (string)password_hash($data['password'], PASSWORD_DEFAULT), PDO::PARAM_STR);
            $stmt->bindValue(':fcm', (string)$data['fcm'], PDO::PARAM_STR);
            $stmt->bindValue(':id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();

            return Helper::jsonResponse("Account created successfully!", 201);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }
    public function profileUpdate(array $data, object $token): Response
    {
        try {
            $this->db->beginTransaction();
            
            // Get current user data
            $sql = "SELECT id, full_name, mobile_number, profile_picture FROM users WHERE id = :id AND is_active = 1;";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();
            $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentUser) {
                $this->db->rollBack();
                return Helper::jsonResponse("User not found", 404);
            }
            
            $updateFields = [];
            $bindValues = [];
             
            if (isset($data['mobile_number']) && $data['mobile_number'] !== $currentUser['mobile_number']) {
                // Check if new mobile number already exists
                $sql = "SELECT id FROM users WHERE mobile_number = :mobile_number AND id != :id AND is_active = 1;";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':mobile_number', (string)$data['mobile_number'], PDO::PARAM_STR);
                $stmt->bindValue(':id', (int)$token->id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    $this->db->rollBack();
                    return Helper::jsonResponse("Mobile number already exists", 409);
                }
                
                $updateFields[] = "mobile_number = :mobile_number";
                $bindValues[':mobile_number'] = (string)$data['mobile_number']; 
                $updateFields[] = "is_mobile_verified = 0";
            }
            
             
            
            // Update name if provided
            if (isset($data['name']) && !empty($data['name'])) {
                $updateFields[] = "full_name = :name";
                $bindValues[':name'] = (string)$data['name'];
            }
            
            // Handle profile picture update
            if (isset($data['profile_picture'])) {
                if (is_string($data['profile_picture']) && !empty($data['profile_picture'])) {
                    // Store the image data
                    $updateFields[] = "profile_picture = :profile_picture";
                    $bindValues[':profile_picture'] = (string)$data['profile_picture'];
                    
                    // Determine picture type
                    if (isset($data['profile_picture_type']) && $data['profile_picture_type'] === 'binary') {
                        // File uploaded from mobile
                        $updateFields[] = "profile_picture_type = 'binary'";
                    } elseif (filter_var($data['profile_picture'], FILTER_VALIDATE_URL)) {
                        // URL provided
                        $updateFields[] = "profile_picture_type = 'url'";
                    } else {
                        // Base64 string from mobile
                        $updateFields[] = "profile_picture_type = 'base64'";
                    }
                } else {
                    // Remove profile picture
                    $updateFields[] = "profile_picture = NULL";
                    $updateFields[] = "profile_picture_type = NULL";
                }
            }
            
            // If no fields to update
            if (empty($updateFields)) {
                $this->db->rollBack();
                return Helper::jsonResponse("No fields to update", 400);
            }
            
            // Add updated timestamp
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            
            // Build and execute update query
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id;";
            $stmt = $this->db->prepare($sql);
            
            // Bind all values
            foreach ($bindValues as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':id', (int)$token->id, PDO::PARAM_INT);
            
            $stmt->execute();
            
            // Get updated user data
            $sql = "SELECT id, full_name, email, mobile_number, profile_picture, profile_picture_type, is_email_verified, is_mobile_verified FROM users WHERE id = :id;";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();
            $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->db->commit();
            
            return Helper::jsonResponse([
                'message' => 'Profile updated successfully',
                'user' => $updatedUser
            ]);
            
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Helper::getLogger()->error("AuthService profile update error: " . $e->getMessage());
            throw $e;
        }
    }


    #region register
    public function register(array $data): Response
    {
        try {
            // Check if user already exists
            $sql = "SELECT id FROM users WHERE email = :email OR mobile_number = :mobile_number AND is_active = 1;";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', (string)$data['email'], PDO::PARAM_STR);
            $stmt->bindValue(':mobile_number', (string)$data['mobile_number'], PDO::PARAM_STR);
            $stmt->execute();
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                return Helper::jsonResponse("User already exists with this email or mobile number", 409);
            }

            // Create new user
            $sql = "INSERT INTO users (
                        full_name, email, mobile_number, password_hash, fcm_token
                    ) VALUES (
                        :full_name, :email, :mobile_number, :password_hash, :fcm_token
                    );";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':full_name', (string)$data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':email', (string)$data['email'], PDO::PARAM_STR);
            $stmt->bindValue(':mobile_number', (string)$data['mobile_number'], PDO::PARAM_STR);
            $stmt->bindValue(':password_hash', (string)password_hash($data['password'], PASSWORD_DEFAULT), PDO::PARAM_STR);
            $stmt->bindValue(':fcm_token', (string)$data['fcm_token'], PDO::PARAM_STR);
            $stmt->execute();

            $userId = $this->db->lastInsertId();

            // Generate JWT tokens
            $issuedAt = time();
            $accessExpireAt = $issuedAt + (2 * 60 * 60);         // 2 hours
            $refreshExpireAt = $issuedAt + (365 * 24 * 60 * 60); // 365 days

            $accessPayload = [
                'iat' => $issuedAt,
                'iss' => $_ENV['JWT_ISSUER'],
                'nbf' => $issuedAt,
                'exp' => $accessExpireAt,
                'id' => $userId,
                'role' => 'user',
            ];

            $refreshPayload = [
                'iat' => $issuedAt,
                'iss' => $_ENV['JWT_ISSUER'],
                'nbf' => $issuedAt,
                'exp' => $refreshExpireAt,
                'id' => $userId,
                'role' => 'refresh',
            ];

            $user = [
                'id' => $userId,
                'full_name' => $data['name'],
                'email' => $data['email'],
                'mobile_number' => $data['mobile_number'],
                'access_token' => JWT::encode($accessPayload, $_ENV['JWT_SECRET'], 'HS256'),
                'refresh_token' => JWT::encode($refreshPayload, $_ENV['JWT_SECRET'], 'HS256')
            ];

            return Helper::jsonResponse($user, 201);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("AuthService error: " . $e->getMessage());
            throw $e;
        }
    }


}  
   