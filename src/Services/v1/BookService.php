<?php

namespace Services\v1;

use PDO;
use Exception;
use Utils\MediaUrlHelper;

class BookService
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAllBooks($page = 1, $limit = 10, $categoryId = null, $isPopular = null, $isRecommended = null)
    {
        try {
            $offset = ($page - 1) * $limit;
            
            $whereConditions = ['b.is_active = 1'];
            $params = [];
            
            if ($categoryId !== null) {
                $whereConditions[] = 'b.category_id = :category_id';
                $params[':category_id'] = $categoryId;
            }
            
            if ($isPopular !== null) {
                $whereConditions[] = 'b.is_popular = :is_popular';
                $params[':is_popular'] = $isPopular;
            }
            
            if ($isRecommended !== null) {
                $whereConditions[] = 'b.is_recommended = :is_recommended';
                $params[':is_recommended'] = $isRecommended;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $sql = "SELECT b.*, bc.category_name 
                    FROM books b 
                    LEFT JOIN book_categories bc ON b.category_id = bc.category_id 
                    WHERE {$whereClause} 
                    ORDER BY b.created_at DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert image paths to absolute URLs
            $books = MediaUrlHelper::convertArrayPathsToUrls($books, [
                'book_image', 'book_cover', 'cover_image', 'thumbnail'
            ]);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM books b WHERE {$whereClause}";
            $countStmt = $this->db->prepare($countSql);
            
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'books' => $books,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            throw new Exception("Error fetching books: " . $e->getMessage());
        }
    }

    public function getBookById($bookId)
    {
        try {
            $sql = "SELECT b.*, bc.category_name 
                    FROM books b 
                    LEFT JOIN book_categories bc ON b.category_id = bc.category_id 
                    WHERE b.book_id = :book_id AND b.is_active = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->execute();
            
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$book) {
                throw new Exception("Book not found");
            }
            
            // Convert image paths to absolute URLs
            return MediaUrlHelper::convertPathsToUrls($book, [
                'book_image', 'book_cover', 'cover_image', 'thumbnail'
            ]);
            
        } catch (Exception $e) {
            throw new Exception("Error fetching book: " . $e->getMessage());
        }
    }

    public function getBooksByCategory($categoryId, $page = 1, $limit = 10)
    {
        try {
            return $this->getAllBooks($page, $limit, $categoryId);
            
        } catch (Exception $e) {
            throw new Exception("Error fetching books by category: " . $e->getMessage());
        }
    }

    public function getPopularBooks($page = 1, $limit = 10)
    {
        try {
            return $this->getAllBooks($page, $limit, null, 1);
            
        } catch (Exception $e) {
            throw new Exception("Error fetching popular books: " . $e->getMessage());
        }
    }

    public function getRecommendedBooks($page = 1, $limit = 10)
    {
        try {
            return $this->getAllBooks($page, $limit, null, null, 1);
            
        } catch (Exception $e) {
            throw new Exception("Error fetching recommended books: " . $e->getMessage());
        }
    }

    public function getAllBookCategories()
    {
        try {
            $sql = "SELECT * FROM book_categories ORDER BY category_order ASC, category_name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            throw new Exception("Error fetching book categories: " . $e->getMessage());
        }
    }

    public function getBookCategoryById($categoryId)
    {
        try {
            $sql = "SELECT * FROM book_categories WHERE category_id = :category_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();
            
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$category) {
                throw new Exception("Book category not found");
            }
            
            return $category;
            
        } catch (Exception $e) {
            throw new Exception("Error fetching book category: " . $e->getMessage());
        }
    }

    public function searchBooks($query, $page = 1, $limit = 10)
    {
        try {
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT b.*, bc.category_name 
                    FROM books b 
                    LEFT JOIN book_categories bc ON b.category_id = bc.category_id 
                    WHERE b.is_active = 1 
                    AND (b.book_title LIKE :query OR b.book_author LIKE :query)
                    ORDER BY b.created_at DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            $searchQuery = '%' . $query . '%';
            $stmt->bindParam(':query', $searchQuery);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM books b 
                        WHERE b.is_active = 1 
                        AND (b.book_title LIKE :query OR b.book_author LIKE :query)";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->bindParam(':query', $searchQuery);
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'books' => $books,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            throw new Exception("Error searching books: " . $e->getMessage());
        }
    }
}