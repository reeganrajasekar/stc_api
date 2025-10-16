<?php

namespace Services\v1;

use PDO;
use Utils\Helper;
use Utils\MediaUrlHelper;

class VideoService
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAllVideos($page = 1, $limit = 10)
    {
        try {
            $offset = ($page - 1) * $limit;
            
            $stmt = $this->db->prepare("
                SELECT v.*, vc.category_name 
                FROM videos v 
                LEFT JOIN video_category vc ON v.category_id = vc.id 
                ORDER BY v.created_at DESC 
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert video and image paths to absolute URLs
            $videos = MediaUrlHelper::convertArrayPathsToUrls($videos, [
                'video_file', 'video_url', 'thumbnail', 'video_thumbnail', 'video_image'
            ]);
            
            // Get total count
            $countStmt = $this->db->query("SELECT COUNT(*) FROM videos");
            $totalCount = $countStmt->fetchColumn();
            
            return [
                'videos' => $videos,
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalCount / $limit)
            ];
            
        } catch (\Exception $e) {
            throw new \Exception("Error fetching videos: " . $e->getMessage());
        }
    }

    public function getVideosByCategory($categoryId, $page = 1, $limit = 10)
    {
        try {
            $offset = ($page - 1) * $limit;
            
            $stmt = $this->db->prepare("
                SELECT v.*, vc.category_name 
                FROM videos v 
                LEFT JOIN video_category vc ON v.category_id = vc.id 
                WHERE v.category_id = :category_id 
                ORDER BY v.created_at DESC 
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert video and image paths to absolute URLs
            $videos = MediaUrlHelper::convertArrayPathsToUrls($videos, [
                'video_file', 'video_url', 'thumbnail', 'video_thumbnail', 'video_image'
            ]);
            
            // Get total count for category
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM videos WHERE category_id = :category_id");
            $countStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $countStmt->execute();
            $totalCount = $countStmt->fetchColumn();
            
            return [
                'videos' => $videos,
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalCount / $limit),
                'category_id' => $categoryId
            ];
            
        } catch (\Exception $e) {
            throw new \Exception("Error fetching videos by category: " . $e->getMessage());
        }
    }

    public function getVideoById($videoId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT v.*, vc.category_name 
                FROM videos v 
                LEFT JOIN video_category vc ON v.category_id = vc.id 
                WHERE v.id = :video_id
            ");
            
            $stmt->bindValue(':video_id', $videoId, PDO::PARAM_INT);
            $stmt->execute();
            
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$video) {
                throw new \Exception("Video not found");
            }
            
            // Convert video and image paths to absolute URLs
            $video = MediaUrlHelper::convertPathsToUrls($video, [
                'video_file', 'video_url', 'thumbnail', 'video_thumbnail', 'video_image'
            ]);
            
            // Add secure video URLs for mobile app compatibility
            if (!empty($video['video_file'])) {
                $video['video_urls'] = MediaUrlHelper::getMediaUrls($video['video_file'], 'video', $videoId);
            }
            
            return $video;
            
        } catch (\Exception $e) {
            throw new \Exception("Error fetching video: " . $e->getMessage());
        }
    }

    public function getAllVideoCategories()
    {
        try {
            $stmt = $this->db->query("
                SELECT vc.*, COUNT(v.id) as video_count 
                FROM video_category vc 
                LEFT JOIN videos v ON vc.id = v.category_id 
                GROUP BY vc.id 
                ORDER BY vc.created_at DESC
            ");
            
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert image paths to absolute URLs
            return MediaUrlHelper::convertArrayPathsToUrls($categories, ['category_image', 'thumbnail']);
        }
            
        } catch (\Exception $e) {
            throw new \Exception("Error fetching video categories: " . $e->getMessage());
        }
    }

    public function getCategoryById($categoryId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT vc.*, COUNT(v.id) as video_count 
                FROM video_category vc 
                LEFT JOIN videos v ON vc.id = v.category_id 
                WHERE vc.id = :category_id 
                GROUP BY vc.id
            ");
            
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();
            
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$category) {
                throw new \Exception("Category not found");
            }
            
            // Convert image paths to absolute URLs
            return MediaUrlHelper::convertPathsToUrls($category, ['category_image', 'thumbnail']);
            
        } catch (\Exception $e) {
            throw new \Exception("Error fetching category: " . $e->getMessage());
        }
    }
}