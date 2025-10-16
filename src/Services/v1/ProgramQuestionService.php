<?php

namespace Services\v1;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Utils\Helper;

class ProgramQuestionService
{
    protected $db;
    
    // Program type to question table mapping
    private $questionTables = [
        'listening' => [
            'conversation' => 'listening_qa',
            'difference' => 'listening_difference',
            'misswords' => 'listening_misswords'
        ],
        'reading' => [
            'readallowed' => 'reading_readallowed',
            'speedread' => 'reading_speedread'
        ],
        'speaking' => [
            'repeat' => 'speaking_repeat',
            'story20' => 'speaking_story20'
        ]
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get question table name based on program and program type
     */
    private function getQuestionTable(string $program, string $programType): string
    {
        if (!isset($this->questionTables[$program])) {
            throw new \InvalidArgumentException("Invalid program: $program");
        }
        
        if (!isset($this->questionTables[$program][$programType])) {
            throw new \InvalidArgumentException("Invalid program type: $programType for program: $program");
        }
        
        return $this->questionTables[$program][$programType];
    }

    /**
     * Get questions for a specific program, program type, and category
     */
    public function getQuestions(string $program, string $programType, int $categoryId, array $filters = []): Response
    {
        try {
            $questionTable = $this->getQuestionTable($program, $programType);
            
            // Build dynamic SQL based on filters
            $whereConditions = ['category_id = :category_id'];
            $params = [':category_id' => $categoryId];
            
            // Add is_active filter if exists in table
            $whereConditions[] = 'is_active = 1';
            
            // Add limit and offset for pagination
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
            $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
            
            // Order by clause
            $orderBy = 'ORDER BY created_at ASC';
            if (isset($filters['random']) && $filters['random']) {
                $orderBy = 'ORDER BY RAND()';
            }

            $sql = "SELECT * FROM {$questionTable} 
                    WHERE " . implode(' AND ', $whereConditions) . " 
                    {$orderBy} 
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total FROM {$questionTable} 
                        WHERE " . implode(' AND ', $whereConditions);
            $countStmt = $this->db->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return Helper::jsonResponse([
                'questions' => $questions,
                'total_questions' => (int)$totalCount,
                'current_page_count' => count($questions),
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $totalCount
                ],
                'program' => $program,
                'program_type' => $programType,
                'category_id' => $categoryId,
                'table_used' => $questionTable
            ]);

        } catch (\Throwable $e) {
            Helper::getLogger()->error("ProgramQuestionService error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get a specific question by ID
     */
    public function getQuestionById(string $program, string $programType, int $questionId): Response
    {
        try {
            $questionTable = $this->getQuestionTable($program, $programType);
            
            // Determine the primary key column name based on table
            $primaryKeyColumn = $this->getPrimaryKeyColumn($questionTable);
            
            $sql = "SELECT * FROM {$questionTable} 
                    WHERE {$primaryKeyColumn} = :question_id AND is_active = 1";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
            $stmt->execute();
            
            $question = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$question) {
                return Helper::jsonResponse("Question not found", 404);
            }

            return Helper::jsonResponse([
                'question' => $question,
                'program' => $program,
                'program_type' => $programType,
                'table_used' => $questionTable
            ]);

        } catch (\Throwable $e) {
            Helper::getLogger()->error("ProgramQuestionService error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get questions count by category
     */
    public function getQuestionsCount(string $program, string $programType, int $categoryId): Response
    {
        try {
            $questionTable = $this->getQuestionTable($program, $programType);
            
            $sql = "SELECT 
                        COUNT(*) as total_questions,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_questions,
                        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_questions
                    FROM {$questionTable} 
                    WHERE category_id = :category_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();
            
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);

            return Helper::jsonResponse([
                'counts' => [
                    'total_questions' => (int)$counts['total_questions'],
                    'active_questions' => (int)$counts['active_questions'],
                    'inactive_questions' => (int)$counts['inactive_questions']
                ],
                'program' => $program,
                'program_type' => $programType,
                'category_id' => $categoryId,
                'table_used' => $questionTable
            ]);

        } catch (\Throwable $e) {
            Helper::getLogger()->error("ProgramQuestionService error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get available question tables and their structure
     */
    public function getAvailableQuestionTables(): Response
    {
        try {
            $tables = [];
            foreach ($this->questionTables as $program => $programTypes) {
                $tables[$program] = [];
                foreach ($programTypes as $programType => $tableName) {
                    $tables[$program][$programType] = $tableName;
                }
            }

            return Helper::jsonResponse([
                'question_tables' => $tables,
                'total_programs' => count($tables)
            ]);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("ProgramQuestionService error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all questions for review with user's current answers (if any)
     */
    public function getQuestionsForReview(object $token, string $program, string $programType, int $categoryId): Response
    {
        try {
            $questionTable = $this->getQuestionTable($program, $programType);
            $userId = (int)$token->id;
            
            // Get all questions for the category with user's previous answers
            $sql = "SELECT 
                        q.*,
                        s.submission_id,
                        s.user_answer,
                        s.is_correct as user_is_correct,
                        s.points_earned as user_points,
                        s.time_spent_seconds as user_time_spent,
                        s.revision_count,
                        s.submitted_at
                    FROM {$questionTable} q
                    LEFT JOIN user_question_submissions s ON q." . $this->getPrimaryKeyColumn($questionTable) . " = s.question_id 
                        AND s.user_id = :user_id 
                        AND s.program_type = :program_type 
                        AND s.category_id = :category_id
                    WHERE q.category_id = :category_id_q 
                    AND q.is_active = 1
                    ORDER BY q.created_at ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':program_type', $programType, PDO::PARAM_STR);
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->bindValue(':category_id_q', $categoryId, PDO::PARAM_INT);
            $stmt->execute();
            
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format questions with user answers
            $formattedQuestions = array_map(function($question) {
                $primaryKey = array_key_exists('question_id', $question) ? 'question_id' : 
                             (array_key_exists('passage_id', $question) ? 'passage_id' : 
                             (array_key_exists('story_id', $question) ? 'story_id' : 'id'));
                
                return [
                    'question_id' => (int)$question[$primaryKey],
                    'category_id' => (int)$question['category_id'],
                    'question_data' => array_diff_key($question, array_flip([
                        'submission_id', 'user_answer', 'user_is_correct', 'user_points', 
                        'user_time_spent', 'revision_count', 'submitted_at'
                    ])),
                    'user_submission' => [
                        'submission_id' => $question['submission_id'] ? (int)$question['submission_id'] : null,
                        'user_answer' => $question['user_answer'],
                        'is_answered' => !empty($question['user_answer']),
                        'is_correct' => $question['user_is_correct'] ? (bool)$question['user_is_correct'] : null,
                        'points_earned' => $question['user_points'] ? (int)$question['user_points'] : 0,
                        'time_spent_seconds' => $question['user_time_spent'] ? (int)$question['user_time_spent'] : 0,
                        'revision_count' => $question['revision_count'] ? (int)$question['revision_count'] : 0,
                        'submitted_at' => $question['submitted_at']
                    ]
                ];
            }, $questions);
            
            // Calculate summary
            $totalQuestions = count($formattedQuestions);
            $answeredQuestions = count(array_filter($formattedQuestions, function($q) {
                return $q['user_submission']['is_answered'];
            }));
            $correctAnswers = count(array_filter($formattedQuestions, function($q) {
                return $q['user_submission']['is_correct'] === true;
            }));
            $totalPoints = array_sum(array_column(array_column($formattedQuestions, 'user_submission'), 'points_earned'));
            
            return Helper::jsonResponse([
                'questions' => $formattedQuestions,
                'summary' => [
                    'total_questions' => $totalQuestions,
                    'answered_questions' => $answeredQuestions,
                    'unanswered_questions' => $totalQuestions - $answeredQuestions,
                    'correct_answers' => $correctAnswers,
                    'incorrect_answers' => $answeredQuestions - $correctAnswers,
                    'total_points' => $totalPoints,
                    'completion_percentage' => $totalQuestions > 0 ? round(($answeredQuestions / $totalQuestions) * 100, 2) : 0,
                    'accuracy_percentage' => $answeredQuestions > 0 ? round(($correctAnswers / $answeredQuestions) * 100, 2) : 0
                ],
                'program' => $program,
                'program_type' => $programType,
                'category_id' => $categoryId,
                'table_used' => $questionTable,
                'ready_for_submission' => $answeredQuestions === $totalQuestions
            ]);

        } catch (\PDOException $e) {
            // If submissions table doesn't exist, just return questions without user answers
            return $this->getQuestions($program, $programType, $categoryId, ['limit' => 1000]);
        } catch (\Throwable $e) {
            Helper::getLogger()->error("ProgramQuestionService getQuestionsForReview error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Submit all answers for a category (Review and Submit)
     */
    public function reviewAndSubmit(object $token, string $program, string $programType, array $submissionData): Response
    {
        try {
            $this->db->beginTransaction();
            
            // Validate required fields
            $requiredFields = ['category_id', 'answers', 'total_time_spent'];
            foreach ($requiredFields as $field) {
                if (!isset($submissionData[$field])) {
                    throw new \InvalidArgumentException("Missing required field: $field");
                }
            }
            
            $userId = (int)$token->id;
            $categoryId = (int)$submissionData['category_id'];
            $answers = $submissionData['answers']; // Array of question answers
            $totalTimeSpent = (int)$submissionData['total_time_spent'];
            
            if (!is_array($answers) || empty($answers)) {
                throw new \InvalidArgumentException("Answers must be a non-empty array");
            }
            
            $results = [];
            $totalPoints = 0;
            $correctAnswers = 0;
            $totalQuestions = count($answers);
            
            // Process each answer
            foreach ($answers as $answer) {
                if (!isset($answer['question_id']) || !isset($answer['user_answer']) || !isset($answer['is_correct'])) {
                    throw new \InvalidArgumentException("Each answer must have question_id, user_answer, and is_correct");
                }
                
                $questionId = (int)$answer['question_id'];
                $userAnswer = $answer['user_answer'];
                $isCorrect = (bool)$answer['is_correct'];
                $questionTimeSpent = isset($answer['time_spent']) ? (int)$answer['time_spent'] : 0;
                $pointsEarned = isset($answer['points_earned']) ? (int)$answer['points_earned'] : ($isCorrect ? 10 : 0);
                
                if ($isCorrect) {
                    $correctAnswers++;
                }
                $totalPoints += $pointsEarned;
                
                // Check if submission already exists
                $existingSubmissionSql = "SELECT submission_id, points_earned FROM user_question_submissions 
                                         WHERE user_id = :user_id 
                                         AND program_type = :program_type 
                                         AND category_id = :category_id 
                                         AND question_id = :question_id";
                
                try {
                    $existingStmt = $this->db->prepare($existingSubmissionSql);
                    $existingStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $existingStmt->bindValue(':program_type', $programType, PDO::PARAM_STR);
                    $existingStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                    $existingStmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
                    $existingStmt->execute();
                    
                    $existingSubmission = $existingStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existingSubmission) {
                        // Update existing submission
                        $updateSql = "UPDATE user_question_submissions 
                                     SET user_answer = :user_answer,
                                         is_correct = :is_correct,
                                         points_earned = :points_earned,
                                         time_spent_seconds = :time_spent,
                                         submitted_at = NOW(),
                                         updated_at = NOW(),
                                         revision_count = COALESCE(revision_count, 0) + 1
                                     WHERE submission_id = :submission_id";
                        
                        $updateStmt = $this->db->prepare($updateSql);
                        $updateStmt->bindValue(':user_answer', $userAnswer, PDO::PARAM_STR);
                        $updateStmt->bindValue(':is_correct', $isCorrect ? 1 : 0, PDO::PARAM_INT);
                        $updateStmt->bindValue(':points_earned', $pointsEarned, PDO::PARAM_INT);
                        $updateStmt->bindValue(':time_spent', $questionTimeSpent, PDO::PARAM_INT);
                        $updateStmt->bindValue(':submission_id', $existingSubmission['submission_id'], PDO::PARAM_INT);
                        $updateStmt->execute();
                        
                        $results[] = [
                            'question_id' => $questionId,
                            'action' => 'updated',
                            'is_correct' => $isCorrect,
                            'points_earned' => $pointsEarned,
                            'points_difference' => $pointsEarned - (int)$existingSubmission['points_earned']
                        ];
                    } else {
                        // Insert new submission
                        $insertSql = "INSERT INTO user_question_submissions 
                                     (user_id, activity_id, program_type, category_id, question_id, 
                                      user_answer, is_correct, points_earned, time_spent_seconds, 
                                      submitted_at, created_at, revision_count)
                                     VALUES 
                                     (:user_id, 0, :program_type, :category_id, :question_id, 
                                      :user_answer, :is_correct, :points_earned, :time_spent, 
                                      NOW(), NOW(), 0)";
                        
                        $insertStmt = $this->db->prepare($insertSql);
                        $insertStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                        $insertStmt->bindValue(':program_type', $programType, PDO::PARAM_STR);
                        $insertStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                        $insertStmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
                        $insertStmt->bindValue(':user_answer', $userAnswer, PDO::PARAM_STR);
                        $insertStmt->bindValue(':is_correct', $isCorrect ? 1 : 0, PDO::PARAM_INT);
                        $insertStmt->bindValue(':points_earned', $pointsEarned, PDO::PARAM_INT);
                        $insertStmt->bindValue(':time_spent', $questionTimeSpent, PDO::PARAM_INT);
                        $insertStmt->execute();
                        
                        $results[] = [
                            'question_id' => $questionId,
                            'action' => 'created',
                            'is_correct' => $isCorrect,
                            'points_earned' => $pointsEarned,
                            'points_difference' => $pointsEarned
                        ];
                    }
                } catch (\PDOException $e) {
                    // Submissions table might not exist, continue without detailed logging
                    $results[] = [
                        'question_id' => $questionId,
                        'action' => 'processed',
                        'is_correct' => $isCorrect,
                        'points_earned' => $pointsEarned,
                        'points_difference' => $pointsEarned
                    ];
                }
            }
            
            // Calculate final score percentage
            $scorePercentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
            $passingCriteria = 70.0; // Default passing criteria
            $status = $scorePercentage >= $passingCriteria ? 'passed' : 'failed';
            
            // Update or create user_program_activity record
            $activitySql = "SELECT * FROM user_program_activity 
                           WHERE user_id = :user_id 
                           AND program_type = :program_type 
                           AND category_id = :category_id";
            
            $activityStmt = $this->db->prepare($activitySql);
            $activityStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $activityStmt->bindValue(':program_type', $programType, PDO::PARAM_STR);
            $activityStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $activityStmt->execute();
            
            $existingActivity = $activityStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingActivity) {
                // Update existing activity
                $updateActivitySql = "UPDATE user_program_activity 
                                     SET category_score = :score_percentage,
                                         category_points = :total_points,
                                         status = :status,
                                         time_spent_seconds = :total_time_spent,
                                         last_activity_date = NOW(),
                                         completed_at = :completed_at,
                                         updated_at = NOW()
                                     WHERE activity_id = :activity_id";
                
                $updateActivityStmt = $this->db->prepare($updateActivitySql);
                $updateActivityStmt->bindValue(':score_percentage', $scorePercentage, PDO::PARAM_STR);
                $updateActivityStmt->bindValue(':total_points', $totalPoints, PDO::PARAM_INT);
                $updateActivityStmt->bindValue(':status', $status, PDO::PARAM_STR);
                $updateActivityStmt->bindValue(':total_time_spent', $totalTimeSpent, PDO::PARAM_INT);
                $updateActivityStmt->bindValue(':completed_at', $status === 'passed' ? date('Y-m-d H:i:s') : null, PDO::PARAM_STR);
                $updateActivityStmt->bindValue(':activity_id', $existingActivity['activity_id'], PDO::PARAM_INT);
                $updateActivityStmt->execute();
                
                $activityId = $existingActivity['activity_id'];
            } else {
                // Create new activity
                $insertActivitySql = "INSERT INTO user_program_activity 
                                     (user_id, program_type, category_id, category_score, category_points, 
                                      status, passing_criteria, time_spent_seconds, last_activity_date, 
                                      completed_at, is_active, created_at, updated_at)
                                     VALUES 
                                     (:user_id, :program_type, :category_id, :score_percentage, :total_points, 
                                      :status, :passing_criteria, :total_time_spent, NOW(), 
                                      :completed_at, 1, NOW(), NOW())";
                
                $insertActivityStmt = $this->db->prepare($insertActivitySql);
                $insertActivityStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $insertActivityStmt->bindValue(':program_type', $programType, PDO::PARAM_STR);
                $insertActivityStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                $insertActivityStmt->bindValue(':score_percentage', $scorePercentage, PDO::PARAM_STR);
                $insertActivityStmt->bindValue(':total_points', $totalPoints, PDO::PARAM_INT);
                $insertActivityStmt->bindValue(':status', $status, PDO::PARAM_STR);
                $insertActivityStmt->bindValue(':passing_criteria', $passingCriteria, PDO::PARAM_STR);
                $insertActivityStmt->bindValue(':total_time_spent', $totalTimeSpent, PDO::PARAM_INT);
                $insertActivityStmt->bindValue(':completed_at', $status === 'passed' ? date('Y-m-d H:i:s') : null, PDO::PARAM_STR);
                $insertActivityStmt->execute();
                
                $activityId = $this->db->lastInsertId();
            }
            
            // Update user's total points
            $pointsDifference = array_sum(array_column($results, 'points_difference'));
            if ($pointsDifference != 0) {
                $updateUserSql = "UPDATE users 
                                 SET total_points = total_points + :points_difference,
                                     updated_at = NOW()
                                 WHERE id = :user_id";
                
                $updateUserStmt = $this->db->prepare($updateUserSql);
                $updateUserStmt->bindValue(':points_difference', $pointsDifference, PDO::PARAM_INT);
                $updateUserStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $updateUserStmt->execute();
            }
            
            $this->db->commit();
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Category completed successfully',
                'results' => [
                    'activity_id' => (int)$activityId,
                    'user_id' => $userId,
                    'program' => $program,
                    'program_type' => $programType,
                    'category_id' => $categoryId,
                    'total_questions' => $totalQuestions,
                    'correct_answers' => $correctAnswers,
                    'incorrect_answers' => $totalQuestions - $correctAnswers,
                    'score_percentage' => $scorePercentage,
                    'total_points' => $totalPoints,
                    'points_difference' => $pointsDifference,
                    'total_time_spent_seconds' => $totalTimeSpent,
                    'status' => $status,
                    'passing_criteria' => $passingCriteria,
                    'is_passed' => $status === 'passed',
                    'completed_at' => $status === 'passed' ? date('Y-m-d H:i:s') : null
                ],
                'question_results' => $results
            ]);
            
        } catch (\Throwable $e) {
            $this->db->rollBack();
            Helper::getLogger()->error("ProgramQuestionService reviewAndSubmit error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Submit program question answer and update user activity
     */
    public function submitAnswer(object $token, string $program, string $programType, array $submissionData): Response
    {
        try {
            $this->db->beginTransaction();
            
            // Validate required fields
            $requiredFields = ['category_id', 'question_id', 'user_answer', 'is_correct', 'time_spent'];
            foreach ($requiredFields as $field) {
                if (!isset($submissionData[$field])) {
                    throw new \InvalidArgumentException("Missing required field: $field");
                }
            }
            
            $userId = (int)$token->id;
            $categoryId = (int)$submissionData['category_id'];
            $questionId = (int)$submissionData['question_id'];
            $userAnswer = $submissionData['user_answer'];
            $isCorrect = (bool)$submissionData['is_correct'];
            $timeSpent = (int)$submissionData['time_spent'];
            $pointsEarned = isset($submissionData['points_earned']) ? (int)$submissionData['points_earned'] : ($isCorrect ? 10 : 0);
            
            // Check if user_program_activity record exists
            $activitySql = "SELECT * FROM user_program_activity 
                           WHERE user_id = :user_id 
                           AND program_type = :program_type 
                           AND category_id = :category_id";
            
            $activityStmt = $this->db->prepare($activitySql);
            $activityStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $activityStmt->bindValue(':program_type', $programType, PDO::PARAM_STR);
            $activityStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $activityStmt->execute();
            
            $existingActivity = $activityStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingActivity) {
                // Update existing activity record
                $newPoints = $existingActivity['category_points'] + $pointsEarned;
                $newTimeSpent = $existingActivity['time_spent_seconds'] + $timeSpent;
                
                $updateSql = "UPDATE user_program_activity 
                             SET current_question_id = :question_id,
                                 category_points = :category_points,
                                 time_spent_seconds = :time_spent_seconds,
                                 last_activity_date = NOW(),
                                 updated_at = NOW()
                             WHERE activity_id = :activity_id";
                
                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
                $updateStmt->bindValue(':category_points', $newPoints, PDO::PARAM_INT);
                $updateStmt->bindValue(':time_spent_seconds', $newTimeSpent, PDO::PARAM_INT);
                $updateStmt->bindValue(':activity_id', $existingActivity['activity_id'], PDO::PARAM_INT);
                $updateStmt->execute();
                
                $activityId = $existingActivity['activity_id'];
            } else {
                // Create new activity record
                $insertSql = "INSERT INTO user_program_activity 
                             (user_id, program_type, category_id, current_question_id, 
                              category_score, category_points, status, passing_criteria, 
                              time_spent_seconds, last_activity_date, is_active, created_at, updated_at)
                             VALUES 
                             (:user_id, :program_type, :category_id, :question_id, 
                              0.00, :category_points, 'in_progress', 70.00, 
                              :time_spent_seconds, NOW(), 1, NOW(), NOW())";
                
                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $insertStmt->bindValue(':program_type', $programType, PDO::PARAM_STR);
                $insertStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                $insertStmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
                $insertStmt->bindValue(':category_points', $pointsEarned, PDO::PARAM_INT);
                $insertStmt->bindValue(':time_spent_seconds', $timeSpent, PDO::PARAM_INT);
                $insertStmt->execute();
                
                $activityId = $this->db->lastInsertId();
            }
            
            // Log the submission in user_question_submissions table (if exists)
            try {
                // Check if submission already exists
                $existingSubmissionSql = "SELECT submission_id, points_earned FROM user_question_submissions 
                                         WHERE user_id = :user_id 
                                         AND program_type = :program_type 
                                         AND category_id = :category_id 
                                         AND question_id = :question_id";
                
                $existingStmt = $this->db->prepare($existingSubmissionSql);
                $existingStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $existingStmt->bindValue(':program_type', $programType, PDO::PARAM_STR);
                $existingStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                $existingStmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
                $existingStmt->execute();
                
                $existingSubmission = $existingStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingSubmission) {
                    // Update existing submission
                    $submissionLogSql = "UPDATE user_question_submissions 
                                       SET user_answer = :user_answer,
                                           is_correct = :is_correct,
                                           points_earned = :points_earned,
                                           time_spent_seconds = :time_spent,
                                           submitted_at = NOW(),
                                           revision_count = COALESCE(revision_count, 0) + 1
                                       WHERE submission_id = :submission_id";
                    
                    $submissionStmt = $this->db->prepare($submissionLogSql);
                    $submissionStmt->bindValue(':user_answer', $userAnswer, PDO::PARAM_STR);
                    $submissionStmt->bindValue(':is_correct', $isCorrect ? 1 : 0, PDO::PARAM_INT);
                    $submissionStmt->bindValue(':points_earned', $pointsEarned, PDO::PARAM_INT);
                    $submissionStmt->bindValue(':time_spent', $timeSpent, PDO::PARAM_INT);
                    $submissionStmt->bindValue(':submission_id', $existingSubmission['submission_id'], PDO::PARAM_INT);
                    $submissionStmt->execute();
                    
                    $submissionId = $existingSubmission['submission_id'];
                    $pointsDifference = $pointsEarned - (int)$existingSubmission['points_earned'];
                } else {
                    // Insert new submission
                    $submissionLogSql = "INSERT INTO user_question_submissions 
                                       (user_id, activity_id, program_type, category_id, question_id, 
                                        user_answer, is_correct, points_earned, time_spent_seconds, 
                                        submitted_at, created_at, revision_count)
                                       VALUES 
                                       (:user_id, :activity_id, :program_type, :category_id, :question_id, 
                                        :user_answer, :is_correct, :points_earned, :time_spent_seconds, 
                                        NOW(), NOW(), 0)";
                    
                    $submissionStmt = $this->db->prepare($submissionLogSql);
                    $submissionStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $submissionStmt->bindValue(':activity_id', $activityId, PDO::PARAM_INT);
                    $submissionStmt->bindValue(':program_type', $programType, PDO::PARAM_STR);
                    $submissionStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                    $submissionStmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
                    $submissionStmt->bindValue(':user_answer', $userAnswer, PDO::PARAM_STR);
                    $submissionStmt->bindValue(':is_correct', $isCorrect ? 1 : 0, PDO::PARAM_INT);
                    $submissionStmt->bindValue(':points_earned', $pointsEarned, PDO::PARAM_INT);
                    $submissionStmt->bindValue(':time_spent_seconds', $timeSpent, PDO::PARAM_INT);
                    $submissionStmt->execute();
                    
                    $submissionId = $this->db->lastInsertId();
                    $pointsDifference = $pointsEarned;
                }
            } catch (\PDOException $e) {
                // Table might not exist, continue without logging submission details
                $submissionId = null;
                $pointsDifference = $pointsEarned;
            }
            
            // Update user's total points (only add the difference)
            if (isset($pointsDifference) && $pointsDifference != 0) {
                $updateUserSql = "UPDATE users 
                                 SET total_points = total_points + :points_difference,
                                     updated_at = NOW()
                                 WHERE id = :user_id";
                
                $updateUserStmt = $this->db->prepare($updateUserSql);
                $updateUserStmt->bindValue(':points_difference', $pointsDifference, PDO::PARAM_INT);
                $updateUserStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $updateUserStmt->execute();
            }
            
            $this->db->commit();
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Answer submitted successfully',
                'submission' => [
                    'activity_id' => (int)$activityId,
                    'submission_id' => $submissionId ? (int)$submissionId : null,
                    'user_id' => $userId,
                    'program' => $program,
                    'program_type' => $programType,
                    'category_id' => $categoryId,
                    'question_id' => $questionId,
                    'is_correct' => $isCorrect,
                    'points_earned' => $pointsEarned,
                    'points_difference' => isset($pointsDifference) ? $pointsDifference : $pointsEarned,
                    'time_spent_seconds' => $timeSpent,
                    'action' => isset($existingSubmission) ? 'updated' : 'created'
                ]
            ]);
            
        } catch (\Throwable $e) {
            $this->db->rollBack();
            Helper::getLogger()->error("ProgramQuestionService submission error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Determine primary key column name based on table name
     */
    private function getPrimaryKeyColumn(string $tableName): string
    {
        // Map table names to their primary key columns
        $primaryKeyMap = [
            'listening_qa' => 'question_id',
            'listening_difference' => 'question_id', 
            'listening_misswords' => 'question_id',
            'reading_readallowed' => 'passage_id',
            'reading_speedread' => 'passage_id',
            'speaking_repeat' => 'question_id',
            'speaking_story20' => 'story_id'
        ];

        return $primaryKeyMap[$tableName] ?? 'id';
    }
}