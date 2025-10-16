<?php

namespace Services\v1;

// System
use PDO;
use Psr\Http\Message\ResponseInterface as Response;

// Utils
use Utils\Helper;

class UserActivityLogService
{
    protected $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    #region logActivity
    public function logActivity(array $data, object $token): Response
    {
        try {
            $sql = "INSERT INTO user_activity_log 
                        (user_id, activity_type, activity_description, ip_address, device_info) 
                    VALUES 
                        (:user_id, :activity_type, :activity_description, :ip_address, :device_info)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->bindValue(':activity_type', (string)$data['activity_type'], PDO::PARAM_STR);
            $stmt->bindValue(':activity_description', (string)($data['activity_description'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':ip_address', (string)($data['ip_address'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':device_info', (string)($data['device_info'] ?? ''), PDO::PARAM_STR);
            $stmt->execute();

            return Helper::jsonResponse("Activity logged successfully", 201);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("UserActivityLogService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region getUserActivityLogs
    public function getUserActivityLogs(object $token, array $params = []): Response
    {
        try {
            $page = isset($params['page']) ? (int)$params['page'] : 1;
            $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            $whereClause = "WHERE user_id = :user_id";
            $bindParams = [':user_id' => $token->id];
            
            // Add activity type filter if provided
            if (!empty($params['activity_type'])) {
                $whereClause .= " AND activity_type = :activity_type";
                $bindParams[':activity_type'] = $params['activity_type'];
            }
            
            // Add date range filter if provided
            if (!empty($params['from_date'])) {
                $whereClause .= " AND created_at >= :from_date";
                $bindParams[':from_date'] = $params['from_date'];
            }
            
            if (!empty($params['to_date'])) {
                $whereClause .= " AND created_at <= :to_date";
                $bindParams[':to_date'] = $params['to_date'];
            }

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM user_activity_log $whereClause";
            $countStmt = $this->db->prepare($countSql);
            foreach ($bindParams as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get paginated results
            $sql = "SELECT 
                        log_id, activity_type, activity_description, 
                        ip_address, device_info, created_at
                    FROM user_activity_log 
                    $whereClause 
                    ORDER BY created_at DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            foreach ($bindParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalCount / $limit),
                    'total_records' => (int)$totalCount,
                    'per_page' => $limit
                ]
            ];

            return Helper::jsonResponse($response, 200);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("UserActivityLogService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region getActivityLogById
    public function getActivityLogById(int $logId, object $token): Response
    {
        try {
            $sql = "SELECT 
                        log_id, activity_type, activity_description, 
                        ip_address, device_info, created_at
                    FROM user_activity_log 
                    WHERE log_id = :log_id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':log_id', $logId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();
            
            $log = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$log) {
                return Helper::jsonResponse("Activity log not found", 404);
            }

            return Helper::jsonResponse($log, 200);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("UserActivityLogService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region getActivityStats
    public function getActivityStats(object $token, array $params = []): Response
    {
        try {
            $whereClause = "WHERE user_id = :user_id";
            $bindParams = [':user_id' => $token->id];
            
            // Add date range filter if provided
            if (!empty($params['from_date'])) {
                $whereClause .= " AND created_at >= :from_date";
                $bindParams[':from_date'] = $params['from_date'];
            }
            
            if (!empty($params['to_date'])) {
                $whereClause .= " AND created_at <= :to_date";
                $bindParams[':to_date'] = $params['to_date'];
            }

            // Get activity type statistics
            $sql = "SELECT 
                        activity_type, 
                        COUNT(*) as count,
                        MAX(created_at) as last_activity
                    FROM user_activity_log 
                    $whereClause 
                    GROUP BY activity_type 
                    ORDER BY count DESC";
            
            $stmt = $this->db->prepare($sql);
            foreach ($bindParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total activity count
            $totalSql = "SELECT COUNT(*) as total_activities FROM user_activity_log $whereClause";
            $totalStmt = $this->db->prepare($totalSql);
            foreach ($bindParams as $key => $value) {
                $totalStmt->bindValue($key, $value);
            }
            $totalStmt->execute();
            $totalActivities = $totalStmt->fetch(PDO::FETCH_ASSOC)['total_activities'];

            $response = [
                'total_activities' => (int)$totalActivities,
                'activity_breakdown' => $stats
            ];

            return Helper::jsonResponse($response, 200);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("UserActivityLogService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region deleteActivityLog
    public function deleteActivityLog(int $logId, object $token): Response
    {
        try {
            $sql = "DELETE FROM user_activity_log 
                    WHERE log_id = :log_id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':log_id', $logId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return Helper::jsonResponse("Activity log not found", 404);
            }

            return Helper::jsonResponse("Activity log deleted successfully", 200);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("UserActivityLogService error: " . $e->getMessage());
            throw $e;
        }
    }

    #region clearUserActivityLogs
    public function clearUserActivityLogs(object $token, array $params = []): Response
    {
        try {
            $whereClause = "WHERE user_id = :user_id";
            $bindParams = [':user_id' => $token->id];
            
            // Add activity type filter if provided
            if (!empty($params['activity_type'])) {
                $whereClause .= " AND activity_type = :activity_type";
                $bindParams[':activity_type'] = $params['activity_type'];
            }
            
            // Add date range filter if provided
            if (!empty($params['before_date'])) {
                $whereClause .= " AND created_at < :before_date";
                $bindParams[':before_date'] = $params['before_date'];
            }

            $sql = "DELETE FROM user_activity_log $whereClause";
            
            $stmt = $this->db->prepare($sql);
            foreach ($bindParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $deletedCount = $stmt->rowCount();

            return Helper::jsonResponse([
                'message' => 'Activity logs cleared successfully',
                'deleted_count' => $deletedCount
            ], 200);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("UserActivityLogService error: " . $e->getMessage());
            throw $e;
        }
    }
}