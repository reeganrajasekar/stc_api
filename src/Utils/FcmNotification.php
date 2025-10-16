<?php

namespace Utils;

use PDO;

class FcmNotification
{
    protected $db;
    protected $serverKey;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        // Add your Firebase Server Key to .env file
        $this->serverKey = $_ENV['FCM_SERVER_KEY'] ?? '';
    }

    /**
     * Send notification to a single user
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        try {
            // Get user's FCM token from database
            $sql = "SELECT fcm FROM users WHERE user_id = :user_id AND fcm IS NOT NULL AND fcm != ''";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || empty($user['fcm'])) {
                return false; // No FCM token found
            }

            return $this->sendNotification($user['fcm'], $title, $body, $data);
        } catch (\Throwable $e) {
            error_log("FCM sendToUser error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToMultipleUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $results = [];
        foreach ($userIds as $userId) {
            $results[$userId] = $this->sendToUser($userId, $title, $body, $data);
        }
        return $results;
    }

    /**
     * Send notification to all users
     */
    public function sendToAllUsers(string $title, string $body, array $data = []): int
    {
        try {
            $sql = "SELECT user_id FROM users WHERE fcm IS NOT NULL AND fcm != '' AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $successCount = 0;
            foreach ($users as $userId) {
                if ($this->sendToUser($userId, $title, $body, $data)) {
                    $successCount++;
                }
            }

            return $successCount;
        } catch (\Throwable $e) {
            error_log("FCM sendToAllUsers error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send notification using FCM HTTP API
     */
    private function sendNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        if (empty($this->serverKey)) {
            error_log("FCM Server Key not configured");
            return false;
        }

        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => 1
        ];

        $payload = [
            'to' => $fcmToken,
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high'
        ];

        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            return isset($responseData['success']) && $responseData['success'] > 0;
        }

        error_log("FCM send failed: HTTP $httpCode - $response");
        return false;
    }

    /**
     * Send learning reminder notifications
     */
    public function sendLearningReminder(int $userId, string $lessonName = ''): bool
    {
        $title = "Time to Learn English! 📚";
        $body = $lessonName 
            ? "Your lesson '$lessonName' is waiting for you!"
            : "Don't break your learning streak! Practice now.";
        
        $data = [
            'type' => 'learning_reminder',
            'lesson' => $lessonName,
            'action' => 'open_lesson'
        ];

        return $this->sendToUser($userId, $title, $body, $data);
    }

    /**
     * Send achievement notifications
     */
    public function sendAchievement(int $userId, string $achievement): bool
    {
        $title = "Congratulations! 🎉";
        $body = "You've achieved: $achievement";
        
        $data = [
            'type' => 'achievement',
            'achievement' => $achievement,
            'action' => 'view_achievements'
        ];

        return $this->sendToUser($userId, $title, $body, $data);
    }

    /**
     * Send streak notifications
     */
    public function sendStreakNotification(int $userId, int $streakDays): bool
    {
        $title = "Amazing Streak! 🔥";
        $body = "You're on a $streakDays day learning streak! Keep it up!";
        
        $data = [
            'type' => 'streak',
            'streak_days' => $streakDays,
            'action' => 'continue_learning'
        ];

        return $this->sendToUser($userId, $title, $body, $data);
    }

    /**
     * Send new content notifications
     */
    public function sendNewContent(int $userId, string $contentType, string $contentName): bool
    {
        $title = "New Content Available! ✨";
        $body = "Check out the new $contentType: $contentName";
        
        $data = [
            'type' => 'new_content',
            'content_type' => $contentType,
            'content_name' => $contentName,
            'action' => 'view_content'
        ];

        return $this->sendToUser($userId, $title, $body, $data);
    }

    
}