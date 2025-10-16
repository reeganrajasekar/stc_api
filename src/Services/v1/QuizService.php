<?php

namespace Services\v1;

use PDO;
use Utils\Helper;

class QuizService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getQuizByCourse(string $courseId): array
    {
        $sql = "SELECT quiz_id, course_id, question_text, option_a, option_b, 
                       option_c, option_d, points, created_at, updated_at
                FROM quiz 
                WHERE course_id = :course_id AND is_active = 1
                ORDER BY quiz_id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['course_id' => $courseId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function submitQuizAnswer(string $courseId, string $quizId, string $selectedAnswer): array
    {
        // Get the quiz question with correct answer
        $sql = "SELECT * FROM quiz 
                WHERE quiz_id = :quiz_id AND course_id = :course_id AND is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'quiz_id' => $quizId,
            'course_id' => $courseId
        ]);
        
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quiz) {
            throw new \Exception('Quiz question not found');
        }

        $isCorrect = strtoupper($selectedAnswer) === strtoupper($quiz['correct_answer']);
        $pointsEarned = $isCorrect ? $quiz['points'] : 0;

        return [
            'quiz_id' => $quizId,
            'question_text' => $quiz['question_text'],
            'selected_answer' => $selectedAnswer,
            'correct_answer' => $quiz['correct_answer'],
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'total_points' => $quiz['points']
        ];
    }

    public function getQuizById(string $quizId): ?array
    {
        $sql = "SELECT * FROM quiz WHERE quiz_id = :quiz_id AND is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['quiz_id' => $quizId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}