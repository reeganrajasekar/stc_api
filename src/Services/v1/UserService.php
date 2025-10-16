<?php

namespace Services\v1;

// System
use PDO;
use Psr\Http\Message\ResponseInterface as Response;

// Utils
use Utils\Helper;
use Utils\ActivityLogger;

class UserService
{
    protected  $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    #region userById
    public function userById(object $token): Response
    {
        try {
            $sql = "SELECT 
                        id, name, email, country_code, mobile, biometric
                        id_mobile_verified, is_mobile_verified
                    FROM users WHERE id = :user_id AND is_deleted = 0;";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();
            $list = $stmt->fetch();

            return Helper::jsonResponse($list, 200);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("ProductService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region editUser
    public function editUser(array $data, object $token): Response
    {
        try {
            $sql = "UPDATE users 
                    SET name = :name
                    WHERE id = :user_id;
                    ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':name', (string)$data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();

            // Log profile update activity
            ActivityLogger::logProfileUpdate($this->db, $token->id, 'name', ActivityLogger::getClientIp(), ActivityLogger::getDeviceInfo());
            
            // Response
            return Helper::jsonResponse('Username Updated Successfully!', 201);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("PinService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region editBiometricUser
    public function editBiometricUser(array $data, object $token): Response
    {
        try {
            $sql = "UPDATE users 
                    SET biometric = :biometric
                    WHERE id = :user_id;
                    ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':biometric', (string)$data['biometric'], PDO::PARAM_STR);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();

            // Response
            return Helper::jsonResponse('Biometric Updated Successfully!', 201);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("PinService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region deleteUser
    public function deleteUser(object $token): Response
    {
        try {
            $sql = "UPDATE users 
                    SET is_deleted = 1
                    WHERE id = :user_id;
                    
                    UPDATE device_mappings 
                    SET is_deleted = 1
                    WHERE user_id = :user_id;
                    ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();

            // Log account deletion activity
            ActivityLogger::logAccountDeletion($this->db, $token->id, ActivityLogger::getClientIp(), ActivityLogger::getDeviceInfo());
            
            // Response
            return Helper::jsonResponse('User deleted Successfully!', 201);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("PinService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region changePassword
    public function changePassword(array $data, object $token): Response
    {
        try {
            $sql = "SELECT password FROM users WHERE id = :user_id AND is_deleted = 0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($data['current_password'], $user['password'])) {
                return Helper::jsonResponse("Current Password is Incorrect.", 401);
            }

            // Hash and update the new password
            $hashedPassword = password_hash($data['new_password'], PASSWORD_BCRYPT);
            $updateSql = "UPDATE users SET password = :new_password WHERE id = :user_id";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->bindValue(':new_password', $hashedPassword, PDO::PARAM_STR);
            $updateStmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $updateStmt->execute();

            // Log password change activity
            ActivityLogger::logPasswordChange($this->db, $token->id, ActivityLogger::getClientIp(), ActivityLogger::getDeviceInfo());
            
            return Helper::jsonResponse("Password Changed Successfully.", 200);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("ProductService error: " . $e->getMessage());
            throw $e;
        }
    }
}
