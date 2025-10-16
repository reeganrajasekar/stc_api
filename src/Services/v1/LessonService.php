<?php

namespace Services\v1;

use PDO;
use Utils\Helper;
use Utils\MediaUrlHelper;

class LessonService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getLessonsByCourse(string $courseId): array
    {
        $sql = "SELECT lesson_id, course_id, lesson_number, lesson_title, 
                       lesson_overview, duration_minutes, points, display_order,
                       lesson_image, created_at, updated_at
                FROM lessons 
                WHERE course_id = :course_id AND is_active = 1
                ORDER BY display_order ASC, lesson_number ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['course_id' => $courseId]);
        
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert image paths to absolute URLs
        return MediaUrlHelper::convertArrayPathsToUrls($lessons, ['lesson_image']);
    }

    public function getLessonContent(string $courseId, string $lessonId): ?array
    {
        $sql = "SELECT * FROM lessons 
                WHERE course_id = :course_id AND lesson_id = :lesson_id AND is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'course_id' => $courseId,
            'lesson_id' => $lessonId
        ]);
        
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lesson) {
            return null;
        }

        // Get course information
        $courseSql = "SELECT course_name, course_subtitle FROM courses WHERE course_id = :course_id";
        $courseStmt = $this->db->prepare($courseSql);
        $courseStmt->execute(['course_id' => $courseId]);
        $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($course) {
            $lesson['course_name'] = $course['course_name'];
            $lesson['course_subtitle'] = $course['course_subtitle'];
        }

        return $lesson;
    }
}