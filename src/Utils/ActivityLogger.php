<?php

namespace Utils;

use PDO;

class ActivityLogger
{
    protected $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Log user activity
     */
    public static function log(PDO $db, int $userId, string $activityType, string $description = '', string $ipAddress = '', string $deviceInfo = ''): bool
    {
        try {
            $sql = "INSERT INTO user_activity_log 
                        (user_id, activity_type, activity_description, ip_address, device_info) 
                    VALUES 
                        (:user_id, :activity_type, :activity_description, :ip_address, :device_info)";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':activity_type', $activityType, PDO::PARAM_STR);
            $stmt->bindValue(':activity_description', $description, PDO::PARAM_STR);
            $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
            $stmt->bindValue(':device_info', $deviceInfo, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (\Throwable $e) {
            error_log("ActivityLogger error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log login activity
     */
    public static function logLogin(PDO $db, int $userId, bool $success = true, string $ipAddress = '', string $deviceInfo = ''): bool
    {
        $activityType = $success ? 'login_success' : 'login_failed';
        $description = $success ? 'User logged in successfully' : 'Failed login attempt';
        
        return self::log($db, $userId, $activityType, $description, $ipAddress, $deviceInfo);
    }

    /**
     * Log logout activity
     */
    public static function logLogout(PDO $db, int $userId, string $ipAddress = '', string $deviceInfo = ''): bool
    {
        return self::log($db, $userId, 'logout', 'User logged out', $ipAddress, $deviceInfo);
    }

    /**
     * Log password change activity
     */
    public static function logPasswordChange(PDO $db, int $userId, string $ipAddress = '', string $deviceInfo = ''): bool
    {
        return self::log($db, $userId, 'password_change', 'User changed password', $ipAddress, $deviceInfo);
    }

    /**
     * Log profile update activity
     */
    public static function logProfileUpdate(PDO $db, int $userId, string $field = '', string $ipAddress = '', string $deviceInfo = ''): bool
    {
        $description = $field ? "Updated profile field: $field" : 'Profile updated';
        return self::log($db, $userId, 'profile_update', $description, $ipAddress, $deviceInfo);
    }

    /**
     * Log OTP verification activity
     */
    public static function logOtpVerification(PDO $db, int $userId, string $type = 'mobile', bool $success = true, string $ipAddress = '', string $deviceInfo = ''): bool
    {
        $activityType = $success ? 'otp_verified' : 'otp_failed';
        $description = $success ? "OTP verified successfully ($type)" : "OTP verification failed ($type)";
        
        return self::log($db, $userId, $activityType, $description, $ipAddress, $deviceInfo);
    }

    /**
     * Log account creation activity
     */
    public static function logAccountCreation(PDO $db, int $userId, string $ipAddress = '', string $deviceInfo = ''): bool
    {
        return self::log($db, $userId, 'account_created', 'User account created', $ipAddress, $deviceInfo);
    }

    /**
     * Log account deletion activity
     */
    public static function logAccountDeletion(PDO $db, int $userId, string $ipAddress = '', string $deviceInfo = ''): bool
    {
        return self::log($db, $userId, 'account_deleted', 'User account deleted', $ipAddress, $deviceInfo);
    }

    /**
     * Log security-related activities
     */
    public static function logSecurityEvent(PDO $db, int $userId, string $event, string $description = '', string $ipAddress = '', string $deviceInfo = ''): bool
    {
        $activityType = 'security_' . $event;
        $description = $description ?: "Security event: $event";
        
        return self::log($db, $userId, $activityType, $description, $ipAddress, $deviceInfo);
    }

    /**
     * Get client IP address from request
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get device info from User-Agent
     */
    public static function getDeviceInfo(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    /**
     * Clean old activity logs (for maintenance)
     */
    public static function cleanOldLogs(PDO $db, int $daysToKeep = 90): int
    {
        try {
            $sql = "DELETE FROM user_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':days', $daysToKeep, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            error_log("ActivityLogger cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}