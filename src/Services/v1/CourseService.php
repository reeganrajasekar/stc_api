<?php

namespace Services\v1;

use PDO;
use Utils\Helper;
use Utils\MediaUrlHelper;

class CourseService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAllCourseCategories(): array
    {
        $sql = "SELECT * FROM course_categories ORDER BY category_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert any image paths to absolute URLs
        return MediaUrlHelper::convertArrayPathsToUrls($categories, ['category_image']);
    }

    public function getAllCourses(?string $categoryId = null): array
    {
        $sql = "SELECT c.*, cc.category_name 
                FROM courses c 
                LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
                WHERE c.is_active = 1";
        
        $params = [];
        
        if ($categoryId) {
            $sql .= " AND c.course_category_id = :category_id";
            $params['category_id'] = $categoryId;
        }
        
        $sql .= " ORDER BY c.display_order ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert image paths to absolute URLs
        return MediaUrlHelper::convertArrayPathsToUrls($courses, ['course_image', 'quiz_image']);
    }

    public function getCourseById(string $courseId): ?array
    {
        $sql = "SELECT c.*, cc.category_name,
                       (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.course_id AND l.is_active = 1) as total_lessons,
                       (SELECT COUNT(*) FROM quiz q WHERE q.course_id = c.course_id AND q.is_active = 1) as total_quiz_questions
                FROM courses c 
                LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
                WHERE c.course_id = :course_id AND c.is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['course_id' => $courseId]);
        
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            return null;
        }
        
        // Convert image paths to absolute URLs
        return MediaUrlHelper::convertPathsToUrls($course, ['course_image', 'quiz_image']);
    }

    public function getCoursesByCategory(string $categoryId): array
    {
        $sql = "SELECT c.*, cc.category_name 
                FROM courses c 
                LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
                WHERE c.course_category_id = :category_id AND c.is_active = 1
                ORDER BY c.display_order ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['category_id' => $categoryId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}