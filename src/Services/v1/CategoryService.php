<?php

namespace Services\v1;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Utils\Helper;

class CategoryService
{
    protected $db;
    
    // Program type to table mapping
    private $categoryTables = [
        'listening' => [
            'conversation' => 'listening_qa_categories',
            'difference' => 'listening_difference_categories',
            'misswords' => 'listening_misswords_categories'
        ],
        'reading' => [
            'readallowed' => 'reading_readallowed_categories',
            'speedread' => 'reading_speedread_categories'
        ],
        'speaking' => [
            'repeat' => 'speaking_repeat_categories',
            'story20' => 'speaking_story20_categories'
        ]
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get category table name based on program and program type
     */
    private function getCategoryTable(string $program, string $programType): string
    {
        if (!isset($this->categoryTables[$program])) {
            throw new \InvalidArgumentException("Invalid program: $program");
        }
        
        if (!isset($this->categoryTables[$program][$programType])) {
            throw new \InvalidArgumentException("Invalid program type: $programType for program: $program");
        }
        
        return $this->categoryTables[$program][$programType];
    }

    /**
     * Get all available programs and their program types
     */
    public function getAvailablePrograms(): Response
    {
        try {
            $programs = [];
            foreach ($this->categoryTables as $program => $programTypes) {
                $programs[$program] = array_keys($programTypes);
            }

            return Helper::jsonResponse([
                'programs' => $programs,
                'total_programs' => count($programs)
            ]);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("CategoryService error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get categories for a specific program and program type with user activity data
     */
    public function getCategories(object $token, string $program, string $programType): Response
    {
        try {
            $categoryTable = $this->getCategoryTable($program, $programType);
            
            $sql = "SELECT 
                        c.category_id,
                        c.category_name,
                        c.category_description,
                        c.display_order,
                        c.is_active,
                        c.created_at,
                        ua.activity_id,
                        ua.category_score,
                        ua.category_points,
                        ua.status,
                        ua.passing_criteria,
                        ua.time_spent_seconds,
                        ua.last_activity_date,
                        ua.completed_at,
                        CASE 
                            WHEN ua.activity_id IS NULL THEN 'not_started'
                            ELSE ua.status 
                        END as user_status,
                        CASE 
                            WHEN ua.category_score >= ua.passing_criteria THEN 1
                            ELSE 0 
                        END as is_passed
                    FROM 
                        {$categoryTable} c
                    LEFT JOIN 
                        user_program_activity ua ON c.category_id = ua.category_id 
                        AND ua.user_id = :user_id 
                        AND ua.program_type = :program_type
                    WHERE 
                        c.is_active = 1
                    ORDER BY 
                        c.display_order ASC, c.category_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->bindValue(':program_type', (string)$programType, PDO::PARAM_STR);
            $stmt->execute();
            
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the response data
            $formattedCategories = array_map(function($category) {
                return [
                    'category_id' => (int)$category['category_id'],
                    'category_name' => $category['category_name'],
                    'category_description' => $category['category_description'],
                    'display_order' => (int)$category['display_order'],
                    'is_active' => (bool)$category['is_active'],
                    'created_at' => $category['created_at'],
                    'user_activity' => [
                        'activity_id' => $category['activity_id'] ? (int)$category['activity_id'] : null,
                        'status' => $category['user_status'],
                        'category_score' => $category['category_score'] ? (float)$category['category_score'] : 0.00,
                        'category_points' => $category['category_points'] ? (int)$category['category_points'] : 0,
                        'passing_criteria' => $category['passing_criteria'] ? (float)$category['passing_criteria'] : 70.00,
                        'time_spent_seconds' => $category['time_spent_seconds'] ? (int)$category['time_spent_seconds'] : 0,
                        'last_activity_date' => $category['last_activity_date'],
                        'completed_at' => $category['completed_at'],
                        'is_passed' => (bool)$category['is_passed']
                    ]
                ];
            }, $categories);

            return Helper::jsonResponse([
                'categories' => $formattedCategories,
                'total_categories' => count($formattedCategories),
                'program' => $program,
                'program_type' => $programType,
                'table_used' => $categoryTable
            ]);

        } catch (\Throwable $e) {
            Helper::getLogger()->error("CategoryService error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get specific category details with user progress
     */
    public function getCategoryDetails(object $token, string $program, string $programType, int $categoryId): Response
    {
        try {
            $categoryTable = $this->getCategoryTable($program, $programType);
            
            $sql = "SELECT 
                        c.category_id,
                        c.category_name,
                        c.category_description,
                        c.display_order,
                        c.is_active,
                        c.created_at,
                        ua.activity_id,
                        ua.current_question_id,
                        ua.category_score,
                        ua.category_points,
                        ua.status,
                        ua.passing_criteria,
                        ua.time_spent_seconds,
                        ua.last_activity_date,
                        ua.completed_at,
                        CASE 
                            WHEN ua.activity_id IS NULL THEN 'not_started'
                            ELSE ua.status 
                        END as user_status,
                        CASE 
                            WHEN ua.category_score >= ua.passing_criteria THEN 1
                            ELSE 0 
                        END as is_passed
                    FROM 
                        {$categoryTable} c
                    LEFT JOIN 
                        user_program_activity ua ON c.category_id = ua.category_id 
                        AND ua.user_id = :user_id 
                        AND ua.program_type = :program_type
                    WHERE 
                        c.category_id = :category_id 
                        AND c.is_active = 1";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->bindValue(':program_type', (string)$programType, PDO::PARAM_STR);
            $stmt->bindValue(':category_id', (int)$categoryId, PDO::PARAM_INT);
            $stmt->execute();
            
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                return Helper::jsonResponse("Category not found", 404);
            }

            $formattedCategory = [
                'category_id' => (int)$category['category_id'],
                'category_name' => $category['category_name'],
                'category_description' => $category['category_description'],
                'display_order' => (int)$category['display_order'],
                'is_active' => (bool)$category['is_active'],
                'created_at' => $category['created_at'],
                'program' => $program,
                'program_type' => $programType,
                'table_used' => $categoryTable,
                'user_activity' => [
                    'activity_id' => $category['activity_id'] ? (int)$category['activity_id'] : null,
                    'current_question_id' => $category['current_question_id'] ? (int)$category['current_question_id'] : null,
                    'status' => $category['user_status'],
                    'category_score' => $category['category_score'] ? (float)$category['category_score'] : 0.00,
                    'category_points' => $category['category_points'] ? (int)$category['category_points'] : 0,
                    'passing_criteria' => $category['passing_criteria'] ? (float)$category['passing_criteria'] : 70.00,
                    'time_spent_seconds' => $category['time_spent_seconds'] ? (int)$category['time_spent_seconds'] : 0,
                    'last_activity_date' => $category['last_activity_date'],
                    'completed_at' => $category['completed_at'],
                    'is_passed' => (bool)$category['is_passed']
                ]
            ];

            return Helper::jsonResponse($formattedCategory);

        } catch (\Throwable $e) {
            Helper::getLogger()->error("CategoryService error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user's progress summary for specific program and program type
     */
    public function getUserProgressSummary(object $token, string $program, string $programType): Response
    {
        try {
            $categoryTable = $this->getCategoryTable($program, $programType);
            
            $sql = "SELECT 
                        COUNT(c.category_id) as total_categories,
                        COUNT(ua.activity_id) as started_categories,
                        SUM(CASE WHEN ua.status = 'passed' THEN 1 ELSE 0 END) as passed_categories,
                        SUM(CASE WHEN ua.status = 'failed' THEN 1 ELSE 0 END) as failed_categories,
                        SUM(CASE WHEN ua.status = 'not_started' OR ua.status IS NULL THEN 1 ELSE 0 END) as not_started_categories,
                        AVG(ua.category_score) as average_score,
                        SUM(ua.category_points) as total_points,
                        SUM(ua.time_spent_seconds) as total_time_spent
                    FROM 
                        {$categoryTable} c
                    LEFT JOIN 
                        user_program_activity ua ON c.category_id = ua.category_id 
                        AND ua.user_id = :user_id 
                        AND ua.program_type = :program_type
                    WHERE 
                        c.is_active = 1";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->bindValue(':program_type', (string)$programType, PDO::PARAM_STR);
            $stmt->execute();
            
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            $progressSummary = [
                'total_categories' => (int)$summary['total_categories'],
                'started_categories' => (int)$summary['started_categories'],
                'passed_categories' => (int)$summary['passed_categories'],
                'failed_categories' => (int)$summary['failed_categories'],
                'not_started_categories' => (int)$summary['not_started_categories'],
                'average_score' => $summary['average_score'] ? round((float)$summary['average_score'], 2) : 0.00,
                'total_points' => (int)$summary['total_points'] ?: 0,
                'total_time_spent_seconds' => (int)$summary['total_time_spent'] ?: 0,
                'completion_percentage' => $summary['total_categories'] > 0 ? 
                    round(($summary['passed_categories'] / $summary['total_categories']) * 100, 2) : 0.00,
                'program' => $program,
                'program_type' => $programType,
                'table_used' => $categoryTable
            ];

            return Helper::jsonResponse($progressSummary);

        } catch (\Throwable $e) {
            Helper::getLogger()->error("CategoryService error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get filtered categories based on status and score range
     */
    public function getFilteredCategories(object $token, string $program, string $programType, array $filters = []): Response
    {
        try {
            $categoryTable = $this->getCategoryTable($program, $programType);
            
            // Build dynamic SQL based on filters
            $whereConditions = ['c.is_active = 1'];
            $params = [':user_id' => (int)$token->id, ':program_type' => $programType];
            
            if (isset($filters['status']) && $filters['status']) {
                if ($filters['status'] === 'not_started') {
                    $whereConditions[] = '(ua.activity_id IS NULL OR ua.status = :status)';
                } else {
                    $whereConditions[] = 'ua.status = :status';
                }
                $params[':status'] = $filters['status'];
            }
            
            if (isset($filters['min_score']) && $filters['min_score'] !== null) {
                $whereConditions[] = 'ua.category_score >= :min_score';
                $params[':min_score'] = (float)$filters['min_score'];
            }
            
            if (isset($filters['max_score']) && $filters['max_score'] !== null) {
                $whereConditions[] = 'ua.category_score <= :max_score';
                $params[':max_score'] = (float)$filters['max_score'];
            }

            $sql = "SELECT 
                        c.category_id,
                        c.category_name,
                        c.category_description,
                        c.display_order,
                        c.is_active,
                        c.created_at,
                        ua.activity_id,
                        ua.category_score,
                        ua.category_points,
                        ua.status,
                        ua.passing_criteria,
                        ua.time_spent_seconds,
                        ua.last_activity_date,
                        ua.completed_at,
                        CASE 
                            WHEN ua.activity_id IS NULL THEN 'not_started'
                            ELSE ua.status 
                        END as user_status,
                        CASE 
                            WHEN ua.category_score >= ua.passing_criteria THEN 1
                            ELSE 0 
                        END as is_passed
                    FROM 
                        {$categoryTable} c
                    LEFT JOIN 
                        user_program_activity ua ON c.category_id = ua.category_id 
                        AND ua.user_id = :user_id 
                        AND ua.program_type = :program_type
                    WHERE " . implode(' AND ', $whereConditions) . "
                    ORDER BY 
                        c.display_order ASC, c.category_name ASC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the response data
            $formattedCategories = array_map(function($category) {
                return [
                    'category_id' => (int)$category['category_id'],
                    'category_name' => $category['category_name'],
                    'category_description' => $category['category_description'],
                    'display_order' => (int)$category['display_order'],
                    'is_active' => (bool)$category['is_active'],
                    'created_at' => $category['created_at'],
                    'user_activity' => [
                        'activity_id' => $category['activity_id'] ? (int)$category['activity_id'] : null,
                        'status' => $category['user_status'],
                        'category_score' => $category['category_score'] ? (float)$category['category_score'] : 0.00,
                        'category_points' => $category['category_points'] ? (int)$category['category_points'] : 0,
                        'passing_criteria' => $category['passing_criteria'] ? (float)$category['passing_criteria'] : 70.00,
                        'time_spent_seconds' => $category['time_spent_seconds'] ? (int)$category['time_spent_seconds'] : 0,
                        'last_activity_date' => $category['last_activity_date'],
                        'completed_at' => $category['completed_at'],
                        'is_passed' => (bool)$category['is_passed']
                    ]
                ];
            }, $categories);

            return Helper::jsonResponse([
                'categories' => $formattedCategories,
                'total_categories' => count($formattedCategories),
                'program' => $program,
                'program_type' => $programType,
                'table_used' => $categoryTable,
                'filters_applied' => $filters
            ]);

        } catch (\Throwable $e) {
            Helper::getLogger()->error("CategoryService error: " . $e->getMessage());
            throw $e;
        }
    }
}