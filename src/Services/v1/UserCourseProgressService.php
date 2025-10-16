<?php

namespace Services\v1;

use PDO;
use Utils\Helper;
use Utils\MediaUrlHelper;

class UserCourseProgressService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function markLessonComplete(int $userId, int $courseId, int $lessonId): array
    {
        try {
            $this->db->beginTransaction();

            // Get total lessons count for the course
            $totalLessons = $this->getTotalLessonsCount($courseId);
            
            // Get or create progress record
            $progress = $this->getOrCreateProgress($userId, $courseId, $totalLessons);
            
            // If total_lessons is 0 in existing record, update it
            if ($progress['total_lessons'] == 0 && $totalLessons > 0) {
                $sql = "UPDATE user_course_progress SET total_lessons = :total_lessons WHERE progress_id = :progress_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'total_lessons' => $totalLessons,
                    'progress_id' => $progress['progress_id']
                ]);
                $progress['total_lessons'] = $totalLessons;
            }
            
            // Debug logging
            error_log("Progress data: " . print_r($progress, true));
            error_log("Completed lessons raw: " . print_r($progress['completed_lessons'] ?? null, true));
            
            // Parse completed lessons array
            $completedLessons = $this->parseCompletedLessons($progress['completed_lessons'] ?? '[]');
            
            // Add lesson to completed if not already completed
            if (!in_array($lessonId, $completedLessons)) {
                $completedLessons[] = $lessonId;
                
                // Get lesson points
                $lessonPoints = $this->getLessonPoints($lessonId);
                
                // Update progress
                $completedCount = count($completedLessons);
                $completionPercentage = $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100, 2) : 0;
                $courseCompleted = $completionPercentage >= 100 ? 1 : 0;
                
                // Get next lesson ID
                $nextLessonId = $this->getNextLessonId($courseId, $lessonId);
                
                $sql = "UPDATE user_course_progress SET 
                        completed_lessons = :completed_lessons,
                        last_completed_lesson_id = :last_completed_lesson_id,
                        current_lesson_id = :current_lesson_id,
                        completed_lessons_count = :completed_count,
                        completion_percentage = :completion_percentage,
                        total_points_earned = total_points_earned + :lesson_points,
                        course_completed = :course_completed,
                        completed_at = :completed_at,
                        last_accessed_at = NOW()
                        WHERE user_id = :user_id AND course_id = :course_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'completed_lessons' => json_encode($completedLessons),
                    'last_completed_lesson_id' => $lessonId,
                    'current_lesson_id' => $nextLessonId,
                    'completed_count' => $completedCount,
                    'completion_percentage' => $completionPercentage,
                    'lesson_points' => $lessonPoints,
                    'course_completed' => $courseCompleted,
                    'completed_at' => $courseCompleted ? date('Y-m-d H:i:s') : null,
                    'user_id' => $userId,
                    'course_id' => $courseId
                ]);
            }
            
            $this->db->commit();
            
            // Return updated progress
            return $this->getUserCourseProgress($userId, $courseId);
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getUserCourseProgress(int $userId, int $courseId): array
    {
        $sql = "SELECT ucp.*, c.course_name, c.course_subtitle, c.course_image
                FROM user_course_progress ucp
                LEFT JOIN courses c ON ucp.course_id = c.course_id
                WHERE ucp.user_id = :user_id AND ucp.course_id = :course_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
        
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$progress) {
            throw new \Exception('Course progress not found');
        }
        
        // Parse JSON fields
        $progress['completed_lessons'] = $this->parseCompletedLessons($progress['completed_lessons'] ?? '[]');
        
        // Convert image paths to absolute URLs
        $progress = MediaUrlHelper::convertPathsToUrls($progress, ['course_image']);
        
        // Get current and next lesson details
        if ($progress['current_lesson_id']) {
            $progress['current_lesson'] = $this->getLessonDetails($progress['current_lesson_id']);
        }
        
        if ($progress['last_completed_lesson_id']) {
            $progress['last_completed_lesson'] = $this->getLessonDetails($progress['last_completed_lesson_id']);
        }
        
        return $progress;
    }

    public function getAllUserProgress(int $userId): array
    {
        $sql = "SELECT ucp.*, c.course_name, c.course_subtitle, c.course_image, cc.category_name
                FROM user_course_progress ucp
                LEFT JOIN courses c ON ucp.course_id = c.course_id
                LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id
                WHERE ucp.user_id = :user_id
                ORDER BY ucp.last_accessed_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $progressList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse JSON fields for each progress record
        foreach ($progressList as &$progress) {
            $progress['completed_lessons'] = $this->parseCompletedLessons($progress['completed_lessons'] ?? '[]');
        }
        
        return $progressList;
    }

    public function startCourse(int $userId, int $courseId): array
    {
        $totalLessons = $this->getTotalLessonsCount($courseId);
        $firstLessonId = $this->getFirstLessonId($courseId);
        
        return $this->getOrCreateProgress($userId, $courseId, $totalLessons, $firstLessonId);
    }

    private function getOrCreateProgress(int $userId, int $courseId, int $totalLessons, ?int $currentLessonId = null): array
    {
        // Check if progress exists
        $sql = "SELECT * FROM user_course_progress WHERE user_id = :user_id AND course_id = :course_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return $existing;
        }
        
        // Create new progress record
        $sql = "INSERT INTO user_course_progress 
                (user_id, course_id, total_lessons, current_lesson_id, completed_lessons) 
                VALUES (:user_id, :course_id, :total_lessons, :current_lesson_id, '[]')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'course_id' => $courseId,
            'total_lessons' => $totalLessons,
            'current_lesson_id' => $currentLessonId
        ]);
        
        return $this->getUserCourseProgress($userId, $courseId);
    }

    private function getTotalLessonsCount(int $courseId): int
    {
        // First try with is_active = 1
        $sql = "SELECT COUNT(*) FROM lessons WHERE course_id = :course_id AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['course_id' => $courseId]);
        $count = (int)$stmt->fetchColumn();
        
        // If no active lessons found, try without is_active filter
        if ($count === 0) {
            $sql = "SELECT COUNT(*) FROM lessons WHERE course_id = :course_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['course_id' => $courseId]);
            $count = (int)$stmt->fetchColumn();
            
            // Debug logging
            error_log("Total lessons for course $courseId: $count (without is_active filter)");
        } else {
            error_log("Total lessons for course $courseId: $count (with is_active = 1)");
        }
        
        return $count;
    }

    private function getLessonPoints(int $lessonId): int
    {
        $sql = "SELECT points FROM lessons WHERE lesson_id = :lesson_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['lesson_id' => $lessonId]);
        return (int)$stmt->fetchColumn();
    }

    private function getFirstLessonId(int $courseId): ?int
    {
        // First try with is_active = 1
        $sql = "SELECT lesson_id FROM lessons 
                WHERE course_id = :course_id AND is_active = 1 
                ORDER BY display_order ASC, lesson_number ASC 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['course_id' => $courseId]);
        $result = $stmt->fetchColumn();
        
        // If no active lesson found, try without is_active filter
        if (!$result) {
            $sql = "SELECT lesson_id FROM lessons 
                    WHERE course_id = :course_id 
                    ORDER BY display_order ASC, lesson_number ASC 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['course_id' => $courseId]);
            $result = $stmt->fetchColumn();
        }
        
        return $result ? (int)$result : null;
    }

    private function getNextLessonId(int $courseId, int $currentLessonId): ?int
    {
        // First try with is_active = 1
        $sql = "SELECT lesson_id FROM lessons 
                WHERE course_id = :course_id AND is_active = 1 
                AND (display_order > (SELECT display_order FROM lessons WHERE lesson_id = :current_lesson_id)
                     OR (display_order = (SELECT display_order FROM lessons WHERE lesson_id = :current_lesson_id) 
                         AND lesson_id > :current_lesson_id))
                ORDER BY display_order ASC, lesson_id ASC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'course_id' => $courseId,
            'current_lesson_id' => $currentLessonId
        ]);
        
        $result = $stmt->fetchColumn();
        
        // If no active lesson found, try without is_active filter
        if (!$result) {
            $sql = "SELECT lesson_id FROM lessons 
                    WHERE course_id = :course_id 
                    AND (display_order > (SELECT display_order FROM lessons WHERE lesson_id = :current_lesson_id)
                         OR (display_order = (SELECT display_order FROM lessons WHERE lesson_id = :current_lesson_id) 
                             AND lesson_id > :current_lesson_id))
                    ORDER BY display_order ASC, lesson_id ASC 
                    LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'course_id' => $courseId,
                'current_lesson_id' => $currentLessonId
            ]);
            
            $result = $stmt->fetchColumn();
        }
        
        return $result ? (int)$result : null;
    }

    private function getLessonDetails(int $lessonId): ?array
    {
        $sql = "SELECT lesson_id, lesson_number, lesson_title, lesson_overview, 
                       duration_minutes, points, lesson_image
                FROM lessons WHERE lesson_id = :lesson_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['lesson_id' => $lessonId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function parseCompletedLessons($data): array
    {
        if (is_null($data)) {
            return [];
        } elseif (is_string($data)) {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        } elseif (is_array($data)) {
            return $data;
        } else {
            return [];
        }
    }
}