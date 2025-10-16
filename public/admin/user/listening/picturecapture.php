<?php
require("../layout/Session.php");
require("../../config/db.php");
require("../../config/upload_config.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'get_questions':
            getQuestions($conn);
            break;
        case 'get_question':
            getQuestion($conn);
            break;
        case 'add_question':
            addQuestion($conn);
            break;
        case 'update_question':
            updateQuestion($conn);
            break;
        case 'delete_question':
            deleteQuestion($conn);
            break;
        case 'get_categories':
            getCategories($conn);
            break;
        case 'get_category':
            getCategory($conn);
            break;
        case 'add_category':
            addCategory($conn);
            break;
        case 'update_category':
            updateCategory($conn);
            break;
        case 'delete_category':
            deleteCategory($conn);
            break;
        case 'get_questions_by_category':
            getQuestionsByCategory($conn);
            break;
        case 'duplicate_question':
            duplicateQuestion($conn);
            break;
        case 'get_audio':
            getAudioFile($conn);
            break;
        case 'get_image':
            getImageFile($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function getAudioFile($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    $sql = "SELECT audio_file, audio_file_name FROM picture_capture_listen_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['audio_file'])) { 
            $filePath = '../../../' . $row['audio_file'];
            
            // Check if file exists
            if (file_exists($filePath)) {
                // Get mime type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                
                // Fallback mime type for audio files
                if (!$mimeType || strpos($mimeType, 'audio/') !== 0) {
                    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    switch ($extension) {
                        case 'mp3':
                            $mimeType = 'audio/mpeg';
                            break;
                        case 'wav':
                            $mimeType = 'audio/wav';
                            break;
                        case 'm4a':
                            $mimeType = 'audio/mp4';
                            break;
                        case 'ogg':
                            $mimeType = 'audio/ogg';
                            break;
                        default:
                            $mimeType = 'audio/mpeg';
                    }
                }
                
                // Set headers for audio streaming
                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . filesize($filePath));
                header('Content-Disposition: inline; filename="' . $row['audio_file_name'] . '"');
                header('Accept-Ranges: bytes');
                
                // Output the file
                readfile($filePath);
                exit;
            }
        }
    }
    
    http_response_code(404);
    echo "Audio file not found";
    exit;
}

function getImageFile($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    $image_index = $_POST['image_index'] ?? 0;
    
    $sql = "SELECT image_files FROM picture_capture_listen_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['image_files'])) {
            $imageFiles = json_decode($row['image_files'], true);
            
            if (isset($imageFiles[$image_index])) {
                $filePath = '../../../' . $imageFiles[$image_index];
                
                // Check if file exists
                if (file_exists($filePath)) {
                    // Get mime type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $filePath);
                    finfo_close($finfo);
                    
                    // Fallback mime type for images
                    if (!$mimeType || strpos($mimeType, 'image/') !== 0) {
                        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                        switch ($extension) {
                            case 'jpg':
                            case 'jpeg':
                                $mimeType = 'image/jpeg';
                                break;
                            case 'png':
                                $mimeType = 'image/png';
                                break;
                            case 'gif':
                                $mimeType = 'image/gif';
                                break;
                            case 'webp':
                                $mimeType = 'image/webp';
                                break;
                            default:
                                $mimeType = 'image/jpeg';
                        }
                    }
                    
                    // Set headers for image display
                    header('Content-Type: ' . $mimeType);
                    header('Content-Length: ' . filesize($filePath));
                    header('Cache-Control: public, max-age=3600');
                    
                    // Output the file
                    readfile($filePath);
                    exit;
                }
            }
        }
    }
    
    http_response_code(404);
    echo "Image file not found";
    exit;
}

function getQuestions($conn) {
    try {
        header('Content-Type: application/json');
        
        $params = $_REQUEST;
        
        // DataTable parameters
        $draw = intval($params['draw'] ?? 1);
        $start = intval($params['start'] ?? 0);
        $length = intval($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';
        $orderColumn = intval($params['order'][0]['column'] ?? 0);
        $orderDir = $params['order'][0]['dir'] ?? 'desc';
        
        // Column mapping for ordering
        $columns = [
            0 => 'pq.question_id',
            1 => 'pq.question_text', 
            2 => 'pc.category_name',
            3 => 'pq.image_files',
            4 => 'pq.correct_image',
            5 => 'pq.audio_file_name',
            6 => 'pq.tips',
            7 => 'pq.is_active',
            8 => 'pq.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'pq.created_at') . ' ' . $orderDir;
        
        // Base filtering
        $where = " WHERE 1=1 ";
        
        // Apply category filter if provided
        $categoryFilter = $params['category_filter'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND pq.category_id = " . intval($categoryFilter) . " ";
        }
        
        // Total records count (with category filter applied)
        $totalSql = "SELECT COUNT(*) as total FROM picture_capture_listen_questions pq LEFT JOIN picture_capture_listen pc ON pq.category_id = pc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        // Apply search filter
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (pq.question_text LIKE '%$search%' OR pc.category_name LIKE '%$search%' OR pq.tips LIKE '%$search%') ";
        }
        
        // Filtered count
        $filteredSql = "SELECT COUNT(*) as total FROM picture_capture_listen_questions pq LEFT JOIN picture_capture_listen pc ON pq.category_id = pc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        // Pagination
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        // Main data query
        $sql = "SELECT pq.question_id, pq.question_text, pq.category_id, pq.image_files, pq.correct_image,
                       pq.audio_file, pq.audio_file_name, pq.audio_file_size, pq.tips, 
                       pq.is_active, pq.created_at, pc.category_name
                FROM picture_capture_listen_questions pq 
                LEFT JOIN picture_capture_listen pc ON pq.category_id = pc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $imageFiles = json_decode($row['image_files'], true) ?? [];
                $imageCount = count($imageFiles);
                
                $data[] = [
                    'question_id' => $row['question_id'],
                    'question_text' => $row['question_text'],
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'category_id' => $row['category_id'],
                    'image_files' => $row['image_files'],
                    'image_count' => $imageCount,
                    'correct_image' => $row['correct_image'],
                    'audio_file' => $row['audio_file'],
                    'audio_file_name' => $row['audio_file_name'],
                    'audio_file_size' => $row['audio_file_size'],
                    'tips' => $row['tips'] ?? '',
                    'is_active' => $row['is_active'],
                    'created_at' => $row['created_at']
                ];
            }
        }
        
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'draw' => $draw ?? 1,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => $e->getMessage()
        ]);
    }
}

function getQuestion($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    $sql = "SELECT pq.question_id, pq.category_id, pq.question_text, pq.image_files, pq.correct_image,
                   pq.audio_file, pq.audio_file_name, pq.audio_file_size, pq.tips, 
                   pq.is_active, pq.created_at, pq.updated_at, pc.category_name 
            FROM picture_capture_listen_questions pq 
            LEFT JOIN picture_capture_listen pc ON pq.category_id = pc.category_id 
            WHERE pq.question_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Question not found']);
    }
}

function addQuestion($conn) {
    try {
        // Validate and sanitize input data
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $question_text = trim($_POST['question_text'] ?? '');
        $correct_image = trim($_POST['correct_image'] ?? '');
        $tips = trim($_POST['tips'] ?? '');
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        // Input validation
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($question_text) || strlen($question_text) < 5) {
            throw new Exception('Question text must be at least 5 characters long');
        }
        
        // Handle image files upload
        $imageData = handleImageUpload();
        if (empty($imageData)) {
            throw new Exception('At least one image file is required');
        }
        
        // Handle audio file upload
        $audioData = handleAudioUpload();
        
        // Begin database transaction for data integrity
        $conn->begin_transaction();
        
        try {
            // Check if audio file was actually uploaded
            $hasAudioFile = !empty($audioData['file_path']);
            
            // Store image files as JSON
            $imageFilesJson = json_encode($imageData);
            
            // Insert question with all data
            $sql = "INSERT INTO picture_capture_listen_questions (category_id, question_text, image_files, correct_image, audio_file, audio_file_name, audio_file_size, tips, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            if ($hasAudioFile) {
                $stmt->bind_param("isssssisi", 
                    $category_id, $question_text, $imageFilesJson, $correct_image,
                    $audioData['file_path'], $audioData['original_name'], $audioData['file_size'], 
                    $tips, $is_active
                );
            } else {
                // No audio file - insert empty values
                $emptyPath = '';
                $emptyFileName = '';
                $emptySize = 0;
                
                $stmt->bind_param("isssssisi", 
                    $category_id, $question_text, $imageFilesJson, $correct_image,
                    $emptyPath, $emptyFileName, $emptySize, 
                    $tips, $is_active
                );
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $conn->commit();
            
            // Log successful operation
            error_log("Picture capture question added successfully: ID $insertId, Category: $category_id, Images: " . count($imageData) . ", Audio: " . ($audioData['file_path'] ? 'Yes' : 'No'));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Question added successfully', 
                'id' => $insertId,
                'images_uploaded' => count($imageData),
                'audio_uploaded' => !empty($audioData['file_path'])
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            // Clean up uploaded files if database insert failed
            if (!empty($audioData['file_path']) && file_exists('../../../' . $audioData['file_path'])) {
                unlink('../../../' . $audioData['file_path']);
            }
            foreach ($imageData as $imagePath) {
                if (file_exists('../../../' . $imagePath)) {
                    unlink('../../../' . $imagePath);
                }
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add Question Error: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateQuestion($conn) {
    try {
        $question_id = $_POST['question_id'] ?? 0;
        $category_id = $_POST['category_id'] ?? 0;
        $question_text = $_POST['question_text'] ?? '';
        $correct_image = $_POST['correct_image'] ?? '';
        $tips = $_POST['tips'] ?? '';
        $is_active = $_POST['is_active'] ?? 1;
        
        // Get current data
        $currentSql = "SELECT image_files, audio_file, audio_file_name, audio_file_size FROM picture_capture_listen_questions WHERE question_id = ?";
        $currentStmt = $conn->prepare($currentSql);
        $currentStmt->bind_param("i", $question_id);
        $currentStmt->execute();
        $currentResult = $currentStmt->get_result();
        $currentData = $currentResult->fetch_assoc();
        
        $imageFilesJson = $currentData['image_files'] ?? '[]';
        $audioFile = $currentData['audio_file'] ?? '';
        $audioFileName = $currentData['audio_file_name'] ?? '';
        $audioFileSize = $currentData['audio_file_size'] ?? 0;
        
        // Handle image updates
        $finalImageFiles = [];
        
        // Get current images from database
        $currentImages = json_decode($currentData['image_files'] ?? '[]', true);
        if (!is_array($currentImages)) {
            $currentImages = [];
        }
        
        // Check if user specified which current images to keep (by index)
        if (isset($_POST['keep_current_image_indices'])) {
            $keptIndices = json_decode($_POST['keep_current_image_indices'], true);
            if (is_array($keptIndices)) {
                // Only keep images at the specified indices
                foreach ($keptIndices as $index) {
                    if (isset($currentImages[$index])) {
                        $finalImageFiles[] = $currentImages[$index];
                    }
                }
            }
        } else {
            // No indices specified, keep all current images
            $finalImageFiles = $currentImages;
        }
        
        // Then, add any new uploaded images
        if (isset($_FILES['image_files']) && !empty($_FILES['image_files']['name'][0])) {
            try {
                $newImageData = handleImageUpload();
                if (!empty($newImageData)) {
                    // Append new images to existing ones
                    $finalImageFiles = array_merge($finalImageFiles, $newImageData);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error uploading images: ' . $e->getMessage()]);
                return;
            }
        }
        
        // Validate that we have at least one image
        if (empty($finalImageFiles)) {
            echo json_encode(['success' => false, 'message' => 'At least one image is required']);
            return;
        }
        
        // Update the JSON with final image list
        $imageFilesJson = json_encode(array_values($finalImageFiles)); // Re-index array
        
        // Handle audio file upload only if a file is actually uploaded
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] == 0) {
            try {
                $audioData = handleAudioUpload();
                
                if (!empty($audioData['file_path'])) {
                    $audioFile = $audioData['file_path'];
                    $audioFileName = $audioData['original_name'];
                    $audioFileSize = $audioData['file_size'];
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error uploading audio: ' . $e->getMessage()]);
                return;
            }
        }
        // If no new audio file uploaded, keep existing audio data (already loaded from database above)
        
        // Update question
        $sql = "UPDATE picture_capture_listen_questions SET category_id=?, question_text=?, image_files=?, correct_image=?, audio_file=?, audio_file_name=?, audio_file_size=?, tips=?, is_active=?, updated_at=NOW() WHERE question_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("isssssisii", $category_id, $question_text, $imageFilesJson, $correct_image, $audioFile, $audioFileName, $audioFileSize, $tips, $is_active, $question_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Question updated successfully']);
        } else {
            throw new Exception('Error updating question: ' . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Update Question Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteQuestion($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    // Get files before deleting
    $getFilesSql = "SELECT image_files, audio_file FROM picture_capture_listen_questions WHERE question_id = ?";
    $getStmt = $conn->prepare($getFilesSql);
    $getStmt->bind_param("i", $question_id);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $fileData = $result->fetch_assoc();
    
    $sql = "DELETE FROM picture_capture_listen_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    
    if ($stmt->execute()) {
        // Delete image files if exist
        if (!empty($fileData['image_files'])) {
            $imageFiles = json_decode($fileData['image_files'], true);
            if (is_array($imageFiles)) {
                foreach ($imageFiles as $imagePath) {
                    if (file_exists('../../../' . $imagePath)) {
                        unlink('../../../' . $imagePath);
                    }
                }
            }
        }
        
        // Delete audio file if exists
        if (!empty($fileData['audio_file']) && file_exists('../../../' . $fileData['audio_file'])) {
            unlink('../../../' . $fileData['audio_file']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Question deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting question: ' . $conn->error]);
    }
}

function getCategories($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'picture_capture_listen'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    // For admin interface, show all categories (both active and inactive)
    $sql = "SELECT category_id, category_name, category_description, 
                   display_order, is_active, created_at, points
            FROM picture_capture_listen 
            ORDER BY display_order ASC, category_name ASC";
    $result = $conn->query($sql);
    
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Database query error: ' . $conn->error]);
        return;
    }
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $categories, 'count' => count($categories)]);
}

function getCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT * FROM picture_capture_listen WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
    }
}

function addCategory($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'picture_capture_listen'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    $category_name = $_POST['category_name'] ?? '';
    $category_description = $_POST['category_description'] ?? '';
    $display_order = intval($_POST['display_order'] ?? 0);
    $points = intval($_POST['points'] ?? 10);
    
    if (empty($category_name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        return;
    }
    
    // Validate points
    if ($points < 1 || $points > 100) {
        echo json_encode(['success' => false, 'message' => 'Points must be between 1 and 100']);
        return;
    }
    
    try {
        $conn->begin_transaction();
        
        // Adjust existing display orders if inserting at specific position
        if ($display_order > 0) {
            // Increment all positions >= target position by 1
            $adjustSql = "UPDATE picture_capture_listen SET display_order = display_order + 1 WHERE display_order >= ?";
            $adjustStmt = $conn->prepare($adjustSql);
            $adjustStmt->bind_param("i", $display_order);
            $adjustStmt->execute();
            $adjustStmt->close();
        }
        
        // Insert the new category
        $sql = "INSERT INTO picture_capture_listen (category_name, category_description, display_order, points) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $category_name, $category_description, $display_order, $points);
        
        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Category added successfully with selective display order adjustment']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error adding category: ' . $conn->error]);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function updateCategory($conn) {
    $category_id = intval($_POST['category_id'] ?? 0);
    $category_name = $_POST['category_name'] ?? '';
    $category_description = $_POST['category_description'] ?? '';
    $new_display_order = intval($_POST['display_order'] ?? 0);
    $points = intval($_POST['points'] ?? 10);
    
    // Validate points
    if ($points < 1 || $points > 100) {
        echo json_encode(['success' => false, 'message' => 'Points must be between 1 and 100']);
        return;
    }
    
    try {
        $conn->begin_transaction();
        
        // Get current display order
        $getCurrentSql = "SELECT display_order FROM picture_capture_listen WHERE category_id = ?";
        $getCurrentStmt = $conn->prepare($getCurrentSql);
        $getCurrentStmt->bind_param("i", $category_id);
        $getCurrentStmt->execute();
        $result = $getCurrentStmt->get_result();
        $current = $result->fetch_assoc();
        $old_display_order = intval($current['display_order'] ?? 0);
        $getCurrentStmt->close();
        
        // Only adjust if display order actually changed
        if ($new_display_order != $old_display_order && $new_display_order > 0 && $old_display_order > 0) {
            if ($new_display_order < $old_display_order) {
                // Moving UP (e.g., from 5 to 2)
                // Increment positions 2, 3, 4 by 1 (target position to old position - 1)
                $adjustSql = "UPDATE picture_capture_listen SET display_order = display_order + 1 
                             WHERE display_order >= ? AND display_order < ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $new_display_order, $old_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            } else {
                // Moving DOWN (e.g., from 2 to 5)
                // Decrement positions 3, 4, 5 by 1 (old position + 1 to target position)
                $adjustSql = "UPDATE picture_capture_listen SET display_order = display_order - 1 
                             WHERE display_order > ? AND display_order <= ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $old_display_order, $new_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            }
        }
        
        // Update the category with new position
        $sql = "UPDATE picture_capture_listen SET category_name=?, category_description=?, display_order=?, points=? WHERE category_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiii", $category_name, $category_description, $new_display_order, $points, $category_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Category updated successfully with selective display order adjustment']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error updating category: ' . $conn->error]);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function deleteCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "DELETE FROM picture_capture_listen WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $conn->error]);
    }
}

function getQuestionsByCategory($conn) {
    try {
        header('Content-Type: application/json');
        
        $params = $_REQUEST;
        $draw = intval($params['draw'] ?? 1);
        $start = intval($params['start'] ?? 0);
        $length = intval($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';
        $orderColumn = intval($params['order'][0]['column'] ?? 0);
        $orderDir = $params['order'][0]['dir'] ?? 'desc';
        
        $columns = [
            0 => 'pq.question_id',
            1 => 'pq.question_text', 
            2 => 'pq.image_files',
            3 => 'pq.correct_image',
            4 => 'pq.audio_file_name',
            5 => 'pq.tips',
            6 => 'pq.is_active',
            7 => 'pq.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'pq.created_at') . ' ' . $orderDir;
        $where = " WHERE 1=1 ";
        
        $categoryFilter = $params['category_filter'] ?? $params['category_id'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND pq.category_id = " . intval($categoryFilter) . " ";
        }
        
        $totalSql = "SELECT COUNT(*) as total FROM picture_capture_listen_questions pq LEFT JOIN picture_capture_listen pc ON pq.category_id = pc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (pq.question_text LIKE '%$search%' OR pq.tips LIKE '%$search%') ";
        }
        
        $filteredSql = "SELECT COUNT(*) as total FROM picture_capture_listen_questions pq LEFT JOIN picture_capture_listen pc ON pq.category_id = pc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        $sql = "SELECT pq.question_id, pq.question_text, pq.category_id, pq.image_files, pq.correct_image,
                       pq.audio_file, pq.audio_file_name, pq.audio_file_size, pq.tips, 
                       pq.is_active, pq.created_at, pc.category_name
                FROM picture_capture_listen_questions pq 
                LEFT JOIN picture_capture_listen pc ON pq.category_id = pc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $imageFiles = json_decode($row['image_files'], true) ?? [];
                $imageCount = count($imageFiles);
                
                $data[] = [
                    'question_id' => $row['question_id'],
                    'question_text' => $row['question_text'],
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'category_id' => $row['category_id'],
                    'image_files' => $row['image_files'],
                    'image_count' => $imageCount,
                    'correct_image' => $row['correct_image'],
                    'audio_file' => $row['audio_file'],
                    'audio_file_name' => $row['audio_file_name'],
                    'audio_file_size' => $row['audio_file_size'],
                    'tips' => $row['tips'] ?? '',
                    'is_active' => $row['is_active'],
                    'created_at' => $row['created_at']
                ];
            }
        }
        
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'draw' => $draw ?? 1,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => $e->getMessage()
        ]);
    }
}

function duplicateQuestion($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    // Get original question
    $sql = "SELECT * FROM picture_capture_listen_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Insert duplicate with modified title
        $new_question_text = $row['question_text'] . ' (Copy)';
        
        // Handle image files duplication
        $new_image_files = [];
        if (!empty($row['image_files'])) {
            $imageFiles = json_decode($row['image_files'], true);
            if (is_array($imageFiles)) {
                foreach ($imageFiles as $imagePath) {
                    if (file_exists('../../../' . $imagePath)) {
                        $original_path = '../../../' . $imagePath;
                        $file_extension = pathinfo($imagePath, PATHINFO_EXTENSION);
                        $unique_filename = uniqid('img_', true) . '.' . $file_extension;
                        
                        // Use current year/month structure
                        $yearMonth = date('Y/m');
                        $fullUploadDir = '../../../uploads/images/' . $yearMonth . '/';
                        
                        // Create directory if it doesn't exist
                        if (!is_dir($fullUploadDir)) {
                            mkdir($fullUploadDir, 0755, true);
                        }
                        
                        $new_path = $fullUploadDir . $unique_filename;
                        
                        if (copy($original_path, $new_path)) {
                            $new_image_files[] = 'uploads/images/' . $yearMonth . '/' . $unique_filename;
                        }
                    }
                }
            }
        }
        
        // Handle audio file duplication
        $new_audio_file = '';
        $new_audio_name = '';
        $new_audio_size = 0;
        
        if (!empty($row['audio_file']) && file_exists('../../../' . $row['audio_file'])) {
            $original_path = '../../../' . $row['audio_file'];
            $file_extension = pathinfo($row['audio_file'], PATHINFO_EXTENSION);
            $unique_filename = uniqid('audio_', true) . '.' . $file_extension;
            
            // Use current year/month structure
            $yearMonth = date('Y/m');
            $fullUploadDir = '../../../uploads/audio/' . $yearMonth . '/';
            
            // Create directory if it doesn't exist
            if (!is_dir($fullUploadDir)) {
                mkdir($fullUploadDir, 0755, true);
            }
            
            $new_path = $fullUploadDir . $unique_filename;
            
            if (copy($original_path, $new_path)) {
                $new_audio_file = 'uploads/audio/' . $yearMonth . '/' . $unique_filename;
                $new_audio_name = $row['audio_file_name'];
                $new_audio_size = $row['audio_file_size'];
            }
        }
        
        // Insert duplicate question
        $imageFilesJson = json_encode($new_image_files);
        $sql = "INSERT INTO picture_capture_listen_questions (category_id, question_text, image_files, correct_image, audio_file, audio_file_name, audio_file_size, tips, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssisi", 
            $row['category_id'], 
            $new_question_text, 
            $imageFilesJson,
            $row['correct_image'],
            $new_audio_file, 
            $new_audio_name, 
            $new_audio_size, 
            $row['tips'], 
            $row['is_active']
        );
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Question duplicated successfully', 'new_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error duplicating question: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Original question not found']);
    }
}



/**
 * Industry-standard image files upload handler
 * Handles multiple image files for picture capture questions
 */
function handleImageUpload() {
    $imageFiles = [];
    
    // Check if files were uploaded
    if (!isset($_FILES['image_files']) || empty($_FILES['image_files']['name'][0])) {
        return $imageFiles; // No files uploaded, return empty array
    }
    
    $files = $_FILES['image_files'];
    $fileCount = count($files['name']);
    
    // Process each uploaded image
    for ($i = 0; $i < $fileCount; $i++) {
        // Skip if no file or error
        if (empty($files['name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // Create file array for validation
        $file = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i]
        ];
        
        // Get file info
        $originalName = basename($file['name']);
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Custom image validation
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        
        // Check file extension
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Image " . ($i + 1) . ": Invalid file type. Allowed types: " . implode(', ', $allowedExtensions));
        }
        
        // Check file size
        if ($file['size'] > $maxFileSize) {
            throw new Exception("Image " . ($i + 1) . ": File too large. Maximum size: 10MB");
        }
        
        // Check MIME type
        if (!in_array($file['type'], $allowedMimeTypes)) {
            throw new Exception("Image " . ($i + 1) . ": Invalid MIME type. File type: " . $file['type']);
        }
        
        // Additional security check for images using getimagesize
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception("Image " . ($i + 1) . ": Invalid image file or corrupted");
        }
        
        // Verify the image dimensions are reasonable
        if ($imageInfo[0] > 5000 || $imageInfo[1] > 5000) {
            throw new Exception("Image " . ($i + 1) . ": Image dimensions too large. Maximum: 5000x5000 pixels");
        }
        
        // Create upload directory structure
        $yearMonth = date('Y/m');
        $baseUploadDir = '../../../uploads/images/';
        $fullUploadDir = $baseUploadDir . $yearMonth . '/';
        
        // Create directory if it doesn't exist
        if (!is_dir($fullUploadDir)) {
            if (!mkdir($fullUploadDir, 0755, true)) {
                throw new Exception('Cannot create upload directory. Check server permissions.');
            }
        }
        
        // Generate secure filename
        $secureFilename = uniqid('img_', true) . '.' . $fileExtension;
        $uploadPath = $fullUploadDir . $secureFilename;
        $relativePath = 'uploads/images/' . $yearMonth . '/' . $secureFilename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception("Image " . ($i + 1) . ": Failed to save uploaded file");
        }
        
        // Set secure permissions
        chmod($uploadPath, 0644);
        
        // Add to results array
        $imageFiles[] = $relativePath;
        
        error_log("Image file uploaded successfully: {$relativePath} | Size: " . formatFileSize($file['size']));
    }
    
    return $imageFiles;
}

/**
 * Industry-standard audio file upload handler
 * Implements security best practices, validation, and error handling
 */
function handleAudioUpload() {
    $audioData = [
        'file_path' => '',
        'original_name' => '',
        'mime_type' => '',
        'file_size' => 0
    ];
    
    // Check if file was uploaded
    if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return $audioData; // No file uploaded, return empty data
    }
    
    $file = $_FILES['audio_file'];
    
    // Custom audio validation
    $allowedExtensions = ['mp3', 'wav', 'm4a', 'ogg', 'aac'];
    $allowedMimeTypes = ['audio/mpeg', 'audio/wav', 'audio/mp4', 'audio/ogg', 'audio/aac'];
    $maxFileSize = 50 * 1024 * 1024; // 50MB
    
    // Check file extension
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception("Invalid audio file type. Allowed types: " . implode(', ', $allowedExtensions));
    }
    
    // Check file size
    if ($file['size'] > $maxFileSize) {
        throw new Exception("Audio file too large. Maximum size: 50MB");
    }
    
    // Check MIME type
    if (!in_array($file['type'], $allowedMimeTypes)) {
        throw new Exception("Invalid audio MIME type. File type: " . $file['type']);
    }
    
    // Get file info for processing
    $originalName = basename($file['name']);
    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // Get MIME type for return value
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Additional security: Check for executable content in audio files
    $fileContent = file_get_contents($file['tmp_name'], false, null, 0, 1024);
    $suspiciousPatterns = ['<?php', '#!/', '<script', 'eval(', 'exec('];
    foreach ($suspiciousPatterns as $pattern) {
        if (stripos($fileContent, $pattern) !== false) {
            throw new Exception('Security violation: Suspicious content detected in audio file');
        }
    }
    
    // Create upload directory structure
    $yearMonth = date('Y/m');
    $baseUploadDir = '../../../uploads/audio/';
    $fullUploadDir = $baseUploadDir . $yearMonth . '/';
    
    // Create directory if it doesn't exist
    if (!is_dir($fullUploadDir)) {
        if (!mkdir($fullUploadDir, 0755, true)) {
            throw new Exception('Cannot create upload directory. Check server permissions.');
        }
    }
    
    // Generate secure filename
    $secureFilename = uniqid('audio_', true) . '.' . $fileExtension;
    $uploadPath = $fullUploadDir . $secureFilename;
    $relativePath = 'uploads/audio/' . $yearMonth . '/' . $secureFilename;
    
    // Move uploaded file with atomic operation
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to save uploaded file. Check server permissions and disk space.');
    }
    
    // Set secure file permissions (readable by owner and group, not executable)
    chmod($uploadPath, 0644);
    
    // Verify file integrity after upload
    if (!file_exists($uploadPath)) {
        throw new Exception('File upload verification failed: File not found after upload');
    }
    
    $uploadedSize = filesize($uploadPath);
    if ($uploadedSize !== $file['size']) {
        unlink($uploadPath); // Clean up corrupted file
        throw new Exception('File upload verification failed: Size mismatch (expected: ' . $file['size'] . ', got: ' . $uploadedSize . ')');
    }
    
    // Log successful upload for audit trail
    error_log("Audio file uploaded successfully: {$relativePath} | Size: " . formatFileSize($file['size']) . " | Type: {$detectedMimeType}");
    
    return [
        'file_path' => $relativePath,
        'original_name' => $originalName,
        'mime_type' => $detectedMimeType,
        'file_size' => $file['size']
    ];
}

/**
 * Helper function to format file sizes
 */
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

?>

<?php include '../layout/Header.php'; ?>
 
<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">Picture Identify - Speaking Practice</h5>
    </div>
</div>
<!-- Categories Action Buttons -->
<div class="row mb-3" id="categoriesActionButtons">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <button class="btn btn-primary me-2" onclick="showAddCategoryModal()">
                            <i class="ri-add-line"></i> Add Category
                        </button>
                        
                        <button class="btn btn-outline-success" onclick="refreshCategoriesTable()">
                            <i class="ri-refresh-line"></i> Refresh
                        </button>
                        
                      
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Questions Action Buttons (Initially Hidden) -->
<div class="row mb-3" id="questionsActionButtons" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <button class="btn btn-primary me-2" onclick="showQuickAddPanelForCategory()">
                            <i class="ri-add-line"></i> Add Question
                        </button>
                        
                        <button class="btn btn-outline-secondary me-2" onclick="backToCategoriesView()">
                            <i class="ri-arrow-left-line"></i> Back to Categories
                        </button>
                        
                        <button class="btn btn-outline-success" onclick="refreshCurrentDataTable()">
                            <i class="ri-refresh-line"></i> Refresh
                        </button> 
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Add Panel (Initially Hidden) -->
<div class="row mb-3" id="quickAddPanel" style="display: none;">
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="ri-lightning-line"></i> Quick Add Panel</h6>
                <button class="btn btn-sm btn-outline-light" onclick="hideQuickAddPanel()">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="card-body">
                <form id="quickAddForm">
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select class="form-select" id="quick_category_id" name="category_id" required>
                            <option value="">Select Category</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Question Text *</label>
                        <textarea class="form-control" id="quick_question_text" name="question_text" rows="2" required placeholder="Enter the question or instruction..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Image Files *</label>
                                <div class="d-flex gap-2 mb-2">
                                    <input type="file" class="form-control" id="quick_image_files" accept="image/*" multiple onchange="addQuickImages(this)">
                                    <button type="button" class="btn btn-outline-primary" onclick="triggerImageUpload('quick_image_files')">
                                        <i class="ri-add-line"></i>Images
                                    </button>
                                </div>
                                <div class="form-text">Select images (JPG, PNG, GIF, WebP). Click images below to set as correct answer.</div>
                                <div id="quick_images_preview" style="display: none; margin-top: 10px;">
                                    <div class="images-preview-container" id="quick_images_container"></div>
                                </div>
                                <!-- Hidden input to store the actual files for form submission -->
                                <input type="hidden" id="quick_images_data" name="image_files_data">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Audio File *</label>
                                <input type="file" class="form-control" id="quick_audio_file" name="audio_file" accept="audio/*" required onchange="previewQuickAudio(this)">
                                <div id="quick_audio_preview" style="display: none; margin-top: 10px;">
                                    <div class="audio-player-container">
                                        <div class="audio-controls">
                                            <div class="audio-filename" id="quick_audio_filename"></div>
                                            <audio id="quick_preview_audio" controls style="width: 100%; height: 30px;"></audio>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="removeQuickAudio()" title="Remove">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Correct Answer</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="text" class="form-control" id="quick_correct_image" name="correct_image" placeholder="Click on an image above to set as correct answer" readonly style="background-color: #f8f9fa;">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearCorrectAnswer('quick')" title="Clear correct answer">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                                <div class="form-text">Click on any image above to automatically set it as the correct answer.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Hints/Tips (Optional)</label>
                        <textarea class="form-control" id="quick_tips" name="tips" rows="2" placeholder="Add helpful hints or tips for this question..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success" id="addContinueBtn">
                            <span class="btn-text">
                                <i class="ri-add-line"></i> Add & Continue
                            </span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Processing...
                            </span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearQuickForm()">
                            <i class="ri-refresh-line"></i> Clear Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Categories Table -->
<div class="row" id="categoriesView">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="categoriesTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Points</th>
                                <th>Display Order</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Questions Section (Initially Hidden) -->
<div class="row mb-3" id="questionsSection" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="questionsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Question</th>
                                <th>Images</th>
                                <th>Correct Image</th>
                                <th>Audio</th>
                                <th>Hints</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Edit Question Modal -->
    <div class="modal fade" id="editQuestionModal" tabindex="-1" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editQuestionModalLabel">Edit Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editQuestionForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="edit_question_id" name="question_id">
                        <input type="hidden" name="action" value="update_question">
                        
                        <div class="mb-3">
                            <label for="edit_category_id" class="form-label">Category *</label>
                            <select class="form-select" id="edit_category_id" name="category_id" required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_question_text" class="form-label">Question Text *</label>
                            <textarea class="form-control" id="edit_question_text" name="question_text" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_image_files" class="form-label">Image Files</label>
                            <div class="d-flex gap-2 mb-2">
                                <input type="file" class="form-control" id="edit_image_files" accept="image/*" multiple onchange="addEditImages(this)">
                                <button type="button" class="btn btn-outline-primary" onclick="triggerImageUpload('edit_image_files')">
                                    <i class="ri-add-line"></i> Add Images
                                </button>
                            </div>
                            <div class="form-text">Add new images or manage existing ones. Click images to set as correct answer.</div>
                            
                            <!-- Current images display -->
                            <div id="edit_current_images" style="display: none; margin-top: 10px;">
                                <small class="text-muted">Current Images:</small>
                                <div class="current-images-container mt-2" id="edit_current_images_container"></div>
                            </div>
                            
                            <!-- New images preview -->
                            <div id="edit_images_preview" style="display: none; margin-top: 10px;">
                                <small class="text-success">New Images:</small>
                                <div class="images-preview-container mt-2" id="edit_images_container"></div>
                            </div>
                            
                            <!-- Hidden input to store the actual files for form submission -->
                            <input type="hidden" id="edit_images_data" name="image_files_data">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="edit_correct_image" class="form-label">Correct Answer</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control" id="edit_correct_image" name="correct_image" placeholder="Click on an image above to set as correct answer" readonly style="background-color: #f8f9fa;">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearCorrectAnswer('edit')" title="Clear correct answer">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_is_active" class="form-label">Status</label>
                                    <select class="form-select" id="edit_is_active" name="is_active">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_audio_file" class="form-label">Audio File</label>
                            <input type="file" class="form-control" id="edit_audio_file" name="audio_file" accept="audio/*" onchange="previewEditAudio(this)">
                            <div class="form-text">Supported formats: MP3, WAV, M4A</div>
                            
                            <!-- Current audio player -->
                            <div id="edit_current_audio" style="display: none; margin-top: 10px;">
                                <small class="text-muted">Current Audio:</small>
                                <div class="audio-player-container mt-2">
                                    <button type="button" class="btn btn-sm btn-primary play-btn" onclick="toggleEditCurrentAudio(this)">
                                        <i class="ri-play-fill"></i>
                                    </button>
                                    <div class="audio-controls">
                                        <div class="audio-filename" id="edit_current_filename"></div>
                                        <div class="audio-progress" onclick="seekEditAudio(event)">
                                            <div class="audio-progress-bar" id="edit_current_progress"></div>
                                        </div>
                                        <div class="audio-time">
                                            <span id="edit_current_time">0:00</span>
                                            <span id="edit_current_duration">0:00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- New audio preview -->
                            <div id="edit_audio_preview" style="display: none; margin-top: 10px;">
                                <small class="text-success">New Audio Preview:</small>
                                <div class="audio-player-container mt-2">
                                    <button type="button" class="btn btn-sm btn-primary play-btn" onclick="togglePreviewAudio('edit_preview_audio', this)">
                                        <i class="ri-play-fill"></i>
                                    </button>
                                    <div class="audio-controls">
                                        <div class="audio-filename" id="edit_audio_filename"></div>
                                        <audio id="edit_preview_audio" controls style="width: 100%; height: 30px;"></audio>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeEditAudio()" title="Remove">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_tips" class="form-label">Hints/Tips (Optional)</label>
                            <textarea class="form-control" id="edit_tips" name="tips" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Categories Modal -->
    <div class="modal fade" id="manageCategoriesModal" tabindex="-1" aria-labelledby="manageCategoriesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manageCategoriesModalLabel">Manage Categories</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6>Categories</h6>
                        <button class="btn btn-sm btn-primary" onclick="showAddCategoryForm()">
                            <i class="ri-add-line"></i> Add Category
                        </button>
                    </div>
                    <div id="categoriesList">
                        <!-- Categories will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalLabel">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="categoryForm">
                    <div class="modal-body">
                        <input type="hidden" id="category_id_edit" name="category_id">
                        <input type="hidden" name="action" id="categoryAction" value="add_category">
                        
                        <div class="mb-3">
                            <label for="category_name" class="form-label">Category Name *</label>
                            <input type="text" class="form-control category-input" id="category_name" name="category_name" required autocomplete="off">
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_description" class="form-label">Description</label>
                            <textarea class="form-control category-input" id="category_description" name="category_description" rows="3" autocomplete="off"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="points" class="form-label">Points *</label>
                                    <input type="number" class="form-control category-input" id="points" name="points" value="10" min="1" max="100" required autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="display_order" class="form-label">Display Order</label>
                                    <input type="number" class="form-control category-input" id="display_order" name="display_order" value="0" autocomplete="off">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Import Modal -->
    <div class="modal fade" id="bulkImportModal" tabindex="-1" aria-labelledby="bulkImportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkImportModalLabel">Bulk Import Sentences</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="bulkImportForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_import">
                        
                        <div class="alert alert-info">
                            <h6><i class="ri-information-line"></i> Import Instructions:</h6>
                            <ul class="mb-0">
                                <li>Upload a JSON file with sentence data</li>
                                <li>Each sentence should have: sentence_text</li>
                                <li>Optional fields: category_id, phonetic_text, tips, points, is_active</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulk_file" class="form-label">JSON File *</label>
                            <input type="file" class="form-control" id="bulk_file" name="bulk_file" accept=".json" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sample JSON Format:</label>
                            <pre class="bg-light p-3 rounded"><code>[
  {
    "category_id": 1,
    "sentence_text": "The quick brown fox jumps over the lazy dog",
    "phonetic_text": "/ðə kwɪk braʊn fɒks dʒʌmps ˈəʊvə ðə ˈleɪzi dɒɡ/",
    "tips": "Focus on clear pronunciation of each word",
    "points": 10,
    "is_active": 1
  }
]</code></pre>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Import Sentences</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Viewer Modal -->
    <div class="modal fade" id="imageViewerModal" tabindex="-1" aria-labelledby="imageViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageViewerModalLabel">Question Images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 id="imageViewerQuestionText"></h6>
                        <div id="imageViewerCorrectAnswer" class="mb-2"></div>
                    </div>
                    <div class="row" id="imageViewerContainer">
                        <!-- Images will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this item? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .images-preview-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            background-color: #f8f9fa;
        }
        
        .current-images-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #28a745;
            border-radius: 5px;
            padding: 10px;
            background-color: #f8fff9;
        }
        
        .image-preview-item {
            display: inline-block;
            margin: 5px;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .image-preview-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .image-preview-item img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }
        
        .image-preview-item img:hover {
            border-color: #007bff;
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }
        
        .image-preview-item.correct-answer img {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.4), 0 4px 12px rgba(40, 167, 69, 0.2);
        }
        
        .image-preview-item.correct-answer img:hover {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.5), 0 6px 16px rgba(40, 167, 69, 0.3);
        }
        
        .image-preview-item .image-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .image-preview-item .image-remove:hover {
            background: #dc3545;
            transform: scale(1.1);
        }
        
        .image-preview-item .image-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            padding: 8px 4px 4px;
            font-size: 11px;
            text-align: center;
            font-weight: 500;
        }
        
        .image-preview-item .correct-badge {
            position: absolute;
            top: 5px;
            left: 5px;
            background: #28a745;
            color: white;
            border-radius: 12px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .form-success {
            animation: successPulse 0.6s ease-in-out;
        }
        
        @keyframes successPulse {
            0% { background-color: transparent; }
            50% { background-color: rgba(40, 167, 69, 0.1); }
            100% { background-color: transparent; }
        }
        
        /* Image Viewer Modal Styles */
        .modal-xl {
            max-width: 90%;
        }
        
        .image-viewer-item {
            margin-bottom: 20px;
        }
        
        .image-viewer-item img {
            width: 100%;
            max-width: 200px;
            height: 200px;
            object-fit: contain;
            border-radius: 8px;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .image-viewer-item img:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .image-viewer-item.correct-answer img {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.3);
        }
        
        .image-viewer-item .image-label {
            text-align: center;
            margin-top: 8px;
            font-weight: 500;
        }
        
        .image-viewer-item .correct-badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-top: 5px;
        }
        
        /* Correct Image Preview in DataTable */
        .correct-image-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .correct-image-preview img {
            transition: transform 0.2s ease;
        }
        
        .correct-image-preview img:hover {
            transform: scale(1.1);
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        let categoriesTable;
        let questionsTable;
        let deleteItemId = null;
        let deleteItemType = null;
        let currentCategoryId = null;
        let currentCategoryName = '';

        $(document).ready(function() {
            // Initialize Categories DataTable
            categoriesTable = $('#categoriesTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: { action: 'get_categories' },
                    dataSrc: function(json) {
                        if (json.success && json.data) {
                            return json.data;
                        }
                        return [];
                    }
                },
                columns: [
                    { data: 'category_id' },
                    { data: 'category_name' },
                    { 
                        data: 'category_description',
                        render: function(data, type, row) {
                            return data || 'No description';
                        }
                    },
                    { 
                        data: 'points',
                        render: function(data, type, row) {
                            return `<span class="badge bg-info">${data}</span>`;
                        }
                    },
                    { data: 'display_order' },
                    { 
                        data: 'is_active',
                        render: function(data, type, row) {
                            return data == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                        }
                    },
                    { 
                        data: 'created_at',
                        render: function(data, type, row) {
                            return new Date(data).toLocaleDateString();
                        }
                    },
                    { 
                        data: null,
                        render: function(data, type, row) {
                            return `
                                <button class="btn btn-sm btn-info me-1" onclick="viewCategoryQuestions(${row.category_id}, '${row.category_name}')" title="View Questions">
                                    <i class="ri-eye-line"></i> View
                                </button>
                                <button class="btn btn-sm btn-warning me-1" onclick="editCategory(${row.category_id})" title="Edit">
                                    <i class="ri-edit-line"></i>
                                </button>
                                <button class="btn btn-sm btn-danger me-1" onclick="deleteCategory(${row.category_id})" title="Delete">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            `;
                        }
                    }
                ],
                order: [[4, 'asc'], [1, 'asc']],
                pageLength: 10,
                responsive: true
            });

            // Load categories for filters and quick add
            loadCategoriesForFilters();

            // Edit question form submission
            $('#editQuestionForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('editQuestionForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#edit_category_id').val()) {
                    showInlineError('edit_category_id', 'Please select a category');
                    hasError = true;
                }
                
                if (!$('#edit_question_text').val() || $('#edit_question_text').val().length < 5) {
                    showInlineError('edit_question_text', 'Question text must be at least 5 characters');
                    hasError = true;
                }
                
                // Validate correct answer is set
                if (!$('#edit_correct_image').val()) {
                    showInlineError('edit_correct_image', 'Please select a correct answer by clicking on an image');
                    showErrorToast('Please select a correct answer by clicking on an image');
                    hasError = true;
                }
                
                // Count total images (current + new)
                const currentImageCount = $('#edit_current_images_container .image-preview-item').length;
                const newImageCount = editImageFiles.length;
                const totalImages = currentImageCount + newImageCount;
                
                if (totalImages === 0) {
                    showErrorToast('At least one image is required');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                const formData = new FormData(this);
                
                // Collect remaining current images (after removals) with their original paths
                const remainingCurrentImages = [];
                $('#edit_current_images_container .image-preview-item').each(function() {
                    const img = $(this).find('img');
                    // Get the data attribute that stores the original image index
                    const questionId = img.attr('data-question-id');
                    const imageIndex = img.attr('data-image-index');
                    
                    // Store the index so we can retrieve the original path from server
                    if (imageIndex !== undefined) {
                        remainingCurrentImages.push(parseInt(imageIndex));
                    }
                });
                
                // Send remaining current image indices as JSON
                formData.append('keep_current_image_indices', JSON.stringify(remainingCurrentImages));
                
                // Add new image files if any
                if (editImageFiles.length > 0) {
                    editImageFiles.forEach((file, index) => {
                        formData.append('image_files[]', file);
                    });
                }
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#editQuestionModal').modal('hide');
                            refreshCurrentDataTable();
                            showSuccessToast('Question updated successfully!');
                        } else {
                            showErrorToast(result.message);
                            // Try to identify which field has the error
                            if (result.message.includes('category')) {
                                showInlineError('edit_category_id', result.message);
                            } else if (result.message.includes('question')) {
                                showInlineError('edit_question_text', result.message);
                            }
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while updating the question.');
                    }
                });
            });

            // Category form submission
            $('#categoryForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('categoryForm');
                
                // Client-side validation
                if (!$('#category_name').val()) {
                    showInlineError('category_name', 'Category name is required');
                    return false;
                }
                
                const points = parseInt($('#points').val());
                if (!points || points < 1 || points > 100) {
                    showInlineError('points', 'Points must be between 1 and 100');
                    return false;
                }
                
                const formData = new FormData(this);
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#categoryModal').modal('hide');
                            refreshCategoriesTable();
                            showSuccessToast(result.message);
                        } else {
                            showErrorToast(result.message);
                            showInlineError('category_name', result.message);
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while saving the category.');
                    }
                });
            });

            // Delete confirmation
            $('#confirmDelete').on('click', function() {
                if (deleteItemType === 'question') {
                    deleteQuestionConfirm(deleteItemId);
                } else if (deleteItemType === 'category') {
                    deleteCategoryConfirm(deleteItemId);
                }
            });
            
            // Quick add form submission with loading state
            $('#quickAddForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('quickAddForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#quick_category_id').val()) {
                    showInlineError('quick_category_id', 'Please select a category');
                    hasError = true;
                }
                
                if (!$('#quick_question_text').val() || $('#quick_question_text').val().length < 5) {
                    showInlineError('quick_question_text', 'Question text must be at least 5 characters');
                    hasError = true;
                }
                
                // Image files are mandatory
                if (quickImageFiles.length === 0) {
                    showInlineError('quick_image_files', 'At least one image file is required');
                    hasError = true;
                }
                
                // Correct answer is mandatory
                if (!$('#quick_correct_image').val()) {
                    showInlineError('quick_correct_image', 'Please select a correct answer by clicking on an image');
                    showErrorToast('Please select a correct answer by clicking on an image');
                    hasError = true;
                }
                
                // Audio file is mandatory
                if (!$('#quick_audio_file')[0].files || $('#quick_audio_file')[0].files.length === 0) {
                    showInlineError('quick_audio_file', 'Audio file is required');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = $('#addContinueBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                // Temporarily enable category field for form submission if it's disabled
                const categoryField = $('#quick_category_id');
                const wasDisabled = categoryField.prop('disabled');
                if (wasDisabled) {
                    categoryField.prop('disabled', false);
                }
                
                const formData = new FormData(this);
                formData.append('action', 'add_question');
                
                // Add image files manually
                quickImageFiles.forEach((file, index) => {
                    formData.append('image_files[]', file);
                });
                
                // Restore disabled state if it was disabled
                if (wasDisabled) {
                    categoryField.prop('disabled', true);
                }
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        console.log('Server response:', response);
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                // Show success message first
                                showSuccessToast('Question added successfully!');
                                
                                // Add success animation to form
                                $('#quickAddForm').addClass('form-success');
                                setTimeout(() => {
                                    $('#quickAddForm').removeClass('form-success');
                                }, 600);
                                
                                // Reset form but keep category selected
                                resetQuickFormExceptCategory();
                                
                                // Reload questions table
                                refreshCurrentDataTable();
                                
                                // Focus on question text for next entry
                                setTimeout(() => {
                                    $('#quick_question_text').focus();
                                }, 200);
                            } else {
                                showErrorToast(result.message);
                                // Try to identify which field has the error
                                if (result.message.includes('category')) {
                                    showInlineError('quick_category_id', result.message);
                                } else if (result.message.includes('question')) {
                                    showInlineError('quick_question_text', result.message);
                                } else if (result.message.includes('image')) {
                                    showInlineError('quick_image_files', result.message);
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            showErrorToast('Error processing server response');
                        }
                    },
                    error: function(xhr) {
                        showErrorToast('An error occurred while saving the conversation.');
                        console.error('AJAX error:', xhr.responseText);
                    },
                    complete: function() {
                        // Hide loading state
                        submitBtn.prop('disabled', false);
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                    }
                });
            });
            

        });

        // Category Management Functions
        function showAddCategoryModal() {
            $('#category_id_edit').val('');
            $('#category_name').val('');
            $('#category_description').val('');
            $('#points').val('10');
            $('#display_order').val('0');
            $('#categoryAction').val('add_category');
            $('#categoryModalLabel').text('Add Category');
            $('#categoryModal').modal('show');
        }

        function viewCategoryQuestions(categoryId, categoryName) {
            console.log('viewCategoryQuestions called with:', categoryId, categoryName);
            currentCategoryId = categoryId;
            currentCategoryName = categoryName;
            
            // Hide categories view and action buttons
            $('#categoriesView').hide();
            $('#categoriesActionButtons').hide();
            
            // Show questions section and action buttons
            $('#questionsSection').show();
            $('#questionsActionButtons').show();
            
            // Initialize or reload questions table
            if (questionsTable) {
                questionsTable.destroy();
                questionsTable = null;
            }
            
            // Small delay to ensure DOM is ready
            setTimeout(function() {
                console.log('Initializing questions DataTable for category:', categoryId);
                questionsTable = $('#questionsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_questions_by_category';
                        d.category_filter = categoryId;
                        return d;
                    },
                    dataSrc: function(json) {
                        console.log('Questions DataTable received data:', json);
                        if (json.error) {
                            console.error('Server error:', json.error);
                            showErrorToast('Server error: ' + json.error);
                            return [];
                        }
                        if (json.data) {
                            return json.data;
                        }
                        return [];
                    },
                    error: function(xhr, error, thrown) {
                        console.error('Questions DataTable AJAX error:', error, thrown);
                        console.error('Response text:', xhr.responseText);
                        showErrorToast('Error loading questions: ' + error);
                    }
                },
                columns: [
                    { data: 'question_id' },
                    { 
                        data: 'question_text',
                        render: function(data, type, row) {
                            return data && data.length > 50 ? data.substring(0, 50) + '...' : (data || '');
                        }
                    },
                    { 
                        data: 'image_count',
                        render: function(data, type, row) {
                            if (data > 0) {
                                // Properly escape data for HTML attributes
                                const questionId = row.question_id;
                                const questionText = (row.question_text || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                                const imageFiles = (row.image_files || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                                const correctImage = (row.correct_image || '').replace(/'/g, "\\'");
                                
                                return `
                                    <div class="images-display">
                                        <button class="btn btn-sm btn-primary" onclick="viewQuestionImages(${questionId}, '${questionText}', '${imageFiles}', '${correctImage}')" title="View Images">
                                            <i class="ri-image-line"></i> ${data} Image${data > 1 ? 's' : ''}
                                        </button>
                                    </div>
                                `;
                            }
                            return '<span class="text-muted">No Images</span>';
                        }
                    },
                    { 
                        data: 'correct_image',
                        render: function(data, type, row) {
                            // Add null/undefined checks
                            if (!data || data === null || data === undefined || data === '') {
                                return '<span class="badge bg-secondary">Not Set</span>';
                            }
                            
                            if (row && row.image_files) {
                                try {
                                    const imageFiles = JSON.parse(row.image_files);
                                    if (Array.isArray(imageFiles) && imageFiles.length > 0) {
                                        // Extract image number from "image1", "image2", etc.
                                        const imageNumber = parseInt(data.replace('image', '')) - 1;
                                        if (imageNumber >= 0 && imageFiles[imageNumber]) {
                                            let imagePath = imageFiles[imageNumber].replace(/\\\//g, '/').replace(/\\/g, '/');
                                            
                                            // Ensure the path starts correctly
                                            if (!imagePath.startsWith('uploads/')) {
                                                imagePath = 'uploads/' + imagePath.replace(/^.*uploads[\/\\]/, '');
                                            }
                                            
                                            const imageUrl = '../../../' + imagePath;
                                            return `
                                                <div class="correct-image-preview">
                                                    <img src="${imageUrl}" alt="${data}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 2px solid #28a745;" title="Correct Answer: ${data}" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                    <div style="font-size: 10px; color: #28a745; text-align: center; margin-top: 2px;">${data}</div>
                                                    <div style="display: none; font-size: 10px; color: #dc3545; text-align: center;">Image Error</div>
                                                </div>
                                            `;
                                        }
                                    }
                                } catch (e) {
                                    console.error('Error parsing image files:', e);
                                }
                                return `<span class="badge bg-success">${data}</span>`;
                            }
                            return `<span class="badge bg-success">${data}</span>`;
                        }
                    },
                    { 
                        data: 'audio_file_name',
                        render: function(data, type, row) {
                            if (data) {
                                return `
                                    <div class="audio-player-container">
                                        <button class="btn btn-sm btn-primary play-btn" onclick="toggleQuestionAudio(${row.question_id}, this)" title="Play Audio">
                                            <i class="ri-play-fill"></i>
                                        </button>
                                        <div class="audio-controls">
                                            <div class="audio-filename">${data.length > 25 ? data.substring(0, 25) + '...' : data}</div>
                                        </div>
                                    </div>
                                `;
                            }
                            return '<span class="text-muted">No Audio</span>';
                        }
                    },
                    { 
                        data: 'tips',
                        render: function(data, type, row) {
                            if (data) {
                                return data.length > 30 ? data.substring(0, 30) + '...' : data;
                            }
                            return '<span class="text-muted">-</span>';
                        }
                    },
                    { 
                        data: 'is_active',
                        render: function(data, type, row) {
                            return data == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                        }
                    },
                    { 
                        data: 'created_at',
                        render: function(data, type, row) {
                            return new Date(data).toLocaleDateString();
                        }
                    },
                    { 
                        data: null,
                        render: function(data, type, row) {
                            return `
                                <button class="btn btn-sm btn-info me-1" onclick="editQuestion(${row.question_id})" title="Edit">
                                    <i class="ri-edit-line"></i>
                                </button>
                                <button class="btn btn-sm btn-warning me-1" onclick="duplicateQuestion(${row.question_id})" title="Duplicate">
                                    <i class="ri-file-copy-line"></i>
                                </button>
                                <button class="btn btn-sm btn-danger me-1" onclick="deleteQuestion(${row.question_id})" title="Delete">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            `;
                        }
                    }
                ],
                drawCallback: function(settings) {
                    console.log('Questions DataTable draw completed');
                },
                order: [[0, 'desc']],
                pageLength: 10,
                responsive: true,
                language: {
                    emptyTable: "No questions found for this category",
                    zeroRecords: "No matching questions found"
                }
                });
                
                console.log('Questions DataTable initialized successfully');
            }, 100);
        }

        function backToCategoriesView() {
            // Hide questions section and action buttons
            $('#questionsSection').hide();
            $('#questionsActionButtons').hide();
            
            // Hide quick add panel
            $('#quickAddPanel').hide();
            
            // Show categories view and action buttons
            $('#categoriesView').show();
            $('#categoriesActionButtons').show();
            
            // Clean up questions table
            if (questionsTable) {
                questionsTable.destroy();
                questionsTable = null;
            }
            
            // Reset category selection state
            $('#quick_category_id').prop('disabled', false);
            $('#quick_category_id').removeClass('bg-light');
            $('.locked-indicator').remove();
            
            currentCategoryId = null;
            currentCategoryName = '';
        }

        function showQuickAddPanelForCategory() {
            if (!currentCategoryId) {
                showErrorToast('No category selected');
                return;
            }
            
            // Load categories and pre-select the current category
            loadCategoriesForFilters();
            
            setTimeout(() => {
                $('#quick_category_id').val(currentCategoryId);
                // Lock the category selection when viewing specific category
                $('#quick_category_id').prop('disabled', true);
                $('#quick_category_id').addClass('bg-light');
                
                // Add visual indicator that category is locked
                const categoryGroup = $('#quick_category_id').closest('.mb-3');
                if (categoryGroup.find('.locked-indicator').length === 0) {
                    categoryGroup.find('label').append(' <small class="text-muted locked-indicator">(Locked to current category)</small>');
                }
                
                // Show the quick add panel
                $('#quickAddPanel').slideDown(400, function() {
                    $('#quick_question_text').focus();
                });
                
                // Keep the questions table visible below the quick add panel
                $('#questionsSection').show();
            }, 200);
        }

        function refreshCategoriesTable() {
            if (categoriesTable) {
                categoriesTable.ajax.reload();
            }
        }

        function refreshCurrentDataTable() {
            if (questionsTable) {
                questionsTable.ajax.reload();
            }
        }

        // Helper function to show inline error message
        function showInlineError(fieldId, message) {
            const field = $('#' + fieldId);
            const formGroup = field.closest('.mb-3');
            
            // Remove any existing error
            formGroup.find('.invalid-feedback').remove();
            field.removeClass('is-invalid');
            
            // Add error styling and message
            field.addClass('is-invalid');
            formGroup.append(`<div class="invalid-feedback d-block">${message}</div>`);
            
            // Scroll to the error
            field[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            field.focus();
        }
        
        // Helper function to clear all inline errors in a form
        function clearInlineErrors(formId) {
            const form = $('#' + formId);
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.invalid-feedback').remove();
        }
        
        // Remove error styling when user starts typing
        $(document).on('input change', '.form-control, .form-select', function() {
            $(this).removeClass('is-invalid');
            $(this).closest('.mb-3').find('.invalid-feedback').remove();
        });
        
        // Helper function to show error toast
        function showErrorToast(message) {
            // Create toast container if it doesn't exist
            if ($('#errorToastContainer').length === 0) {
                $('body').append(`
                    <div id="errorToastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
                    </div>
                `);
            }
            
            const toastId = 'toast-' + Date.now();
            const toast = `
                <div id="${toastId}" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="ri-error-warning-line me-2"></i>${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            $('#errorToastContainer').append(toast);
            const toastElement = new bootstrap.Toast(document.getElementById(toastId), { delay: 5000 });
            toastElement.show();
            
            // Remove toast element after it's hidden
            $('#' + toastId).on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }



        function editQuestion(questionId) {
            // Store current quick panel state before loading categories
            const quickCategoryValue = $('#quick_category_id').val();
            const quickCategoryDisabled = $('#quick_category_id').prop('disabled');
            const quickCategoryHasLockClass = $('#quick_category_id').hasClass('bg-light');
            
            // First ensure categories are loaded in edit modal
            loadCategoriesForFilters();
            
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_question', question_id: questionId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        // Wait a bit for categories to load, then populate form
                        setTimeout(function() {
                            $('#edit_question_id').val(data.question_id);
                            $('#edit_category_id').val(data.category_id);
                            $('#edit_question_text').val(data.question_text);
                            $('#edit_correct_image').val(data.correct_image || '');
                            $('#edit_tips').val(data.tips || '');
                            $('#edit_is_active').val(data.is_active);
                            
                            // Set current correct answer
                            currentCorrectAnswer.edit = data.correct_image || '';
                            
                            // Reset edit image arrays
                            editImageFiles = [];
                            
                            // Restore quick panel category state
                            if (quickCategoryValue) {
                                $('#quick_category_id').val(quickCategoryValue);
                                if (quickCategoryDisabled) {
                                    $('#quick_category_id').prop('disabled', true);
                                }
                                if (quickCategoryHasLockClass) {
                                    $('#quick_category_id').addClass('bg-light');
                                }
                            }
                            
                            // Show current images if exist
                            if (data.image_files) {
                                displayCurrentImages(data.image_files, data.question_id);
                                $('#edit_current_images').show();
                            } else {
                                $('#edit_current_images').hide();
                            }
                            
                            // Show current audio if exists
                            if (data.audio_file_name) {
                                $('#edit_current_filename').text(data.audio_file_name);
                                $('#edit_current_audio').show();
                            } else {
                                $('#edit_current_audio').hide();
                            }
                            
                            // Hide previews and reset
                            $('#edit_images_preview').hide();
                            $('#edit_audio_preview').hide();
                            $('#edit_image_files').val('');
                            $('#edit_audio_file').val('');
                            editCurrentAudio = null;
                            
                            $('#editQuestionModal').modal('show');
                        }, 200);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading question data');
                }
            });
        }

        function deleteQuestion(questionId) {
            deleteItemId = questionId;
            deleteItemType = 'question';
            $('#deleteModal').modal('show');
        }

        function deleteQuestionConfirm(questionId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_question', question_id: questionId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#deleteModal').modal('hide');
                        refreshCurrentDataTable();
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        function showAddCategoryForm() {
            // Hide the manage categories modal first
            $('#manageCategoriesModal').modal('hide');
            
            // Wait for the modal to fully hide before showing the new one
            setTimeout(function() {
                $('#category_id_edit').val('');
                $('#category_name').val('');
                $('#category_description').val('');
                $('#display_order').val('0');
                $('#categoryAction').val('add_category');
                $('#categoryModalLabel').text('Add Category');
                
                // Ensure inputs are enabled and focusable
                $('.category-input').prop('disabled', false).removeAttr('readonly').removeAttr('disabled');
                
                // Show the category modal
                $('#categoryModal').modal('show');
                
                // Force focus after modal is shown
                $('#categoryModal').on('shown.bs.modal', function() {
                    setTimeout(function() {
                        $('#category_name').focus().click();
                    }, 100);
                });
            }, 300);
        }

        function editCategory(categoryId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_category', category_id: categoryId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        $('#category_id_edit').val(data.category_id);
                        $('#category_name').val(data.category_name);
                        $('#category_description').val(data.category_description);
                        $('#points').val(data.points);
                        $('#display_order').val(data.display_order);
                        $('#categoryAction').val('update_category');
                        $('#categoryModalLabel').text('Edit Category');
                        
                        // Ensure inputs are enabled and focusable
                        $('.category-input').prop('disabled', false).removeAttr('readonly').removeAttr('disabled');
                        
                        // Show the category modal
                        $('#categoryModal').modal('show');
                        
                        // Force focus after modal is shown
                        $('#categoryModal').on('shown.bs.modal', function() {
                            setTimeout(function() {
                                $('#category_name').focus().click();
                            }, 100);
                        });
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        function deleteCategory(categoryId) {
            deleteItemId = categoryId;
            deleteItemType = 'category';
            $('#deleteModal').modal('show');
        }

        function deleteCategoryConfirm(categoryId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_category', category_id: categoryId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#deleteModal').modal('hide');
                        refreshCategoriesTable();
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }


        


        // Enhanced modal event handlers
        $('#categoryModal').on('shown.bs.modal', function() {
            // Remove any existing event handlers to prevent duplicates
            $(this).off('shown.bs.modal');
            
            // Ensure inputs are focusable and clickable
            $('.category-input').prop('disabled', false).removeAttr('readonly').removeAttr('disabled');
            
            setTimeout(function() {
                $('#category_name').focus().click();
                
                // Force enable all inputs
                $('.category-input').each(function() {
                    $(this).prop('disabled', false).removeAttr('readonly').removeAttr('disabled');
                });
            }, 150);
        });

        // Reset edit form when modal is closed
        $('#editQuestionModal').on('hidden.bs.modal', function() {
            $('#editQuestionForm')[0].reset();
            $('#edit_images_preview').hide();
            $('#edit_current_images').hide();
            $('#edit_audio_preview').hide();
            $('#edit_current_audio').hide();
            
            // Reset image arrays and correct answer
            editImageFiles = [];
            currentCorrectAnswer.edit = '';
            
            if (editCurrentAudio) {
                editCurrentAudio.pause();
                editCurrentAudio = null;
            }
        });

        // Reset category form when modal is closed
        $('#categoryModal').on('hidden.bs.modal', function() {
            $('#categoryForm')[0].reset();
            $('#categoryAction').val('add_category');
            $('#categoryModalLabel').text('Add Category');
        });
        
        // Ensure inputs remain editable when clicking on them
        $(document).on('click focus', '.category-input', function() {
            $(this).prop('disabled', false).removeAttr('readonly').removeAttr('disabled');
        });
        
        // Handle label clicks to focus inputs
        $(document).on('click', '.modal .form-label', function() {
            const targetId = $(this).attr('for');
            if (targetId) {
                $('#' + targetId).focus().click();
            }
        });

        function hideQuickAddPanel() {
            // Reset category field state when hiding panel
            $('#quick_category_id').prop('disabled', false);
            $('#quick_category_id').removeClass('bg-light');
            $('.locked-indicator').remove();
            
            $('#quickAddPanel').slideUp();
            $('#sentencesSection').show();
        }

        function clearQuickForm() {
            $('#quickAddForm')[0].reset();
            $('#quick_images_preview').hide();
            $('#quick_audio_preview').hide();
            $('#quick_correct_image').removeClass('is-valid is-invalid');
            
            // Reset image arrays and correct answer
            quickImageFiles = [];
            currentCorrectAnswer.quick = '';
            
            $('#quick_question_text').focus();
        }

        function resetQuickFormExceptCategory() {
            // Store the selected category and its state
            const selectedCategory = $('#quick_category_id').val();
            const wasDisabled = $('#quick_category_id').prop('disabled');
            const hasLockClass = $('#quick_category_id').hasClass('bg-light');
            
            // Reset all fields
            $('#quick_question_text').val('');
            $('#quick_correct_image').val('').removeClass('is-valid is-invalid');
            $('#quick_tips').val('');
            $('#quick_image_files').val('');
            $('#quick_audio_file').val('');
            $('#quick_images_preview').hide();
            $('#quick_audio_preview').hide();
            
            // Reset image arrays and correct answer
            quickImageFiles = [];
            currentCorrectAnswer.quick = '';
            
            // Restore the category selection and its state
            $('#quick_category_id').val(selectedCategory);
            if (wasDisabled) {
                $('#quick_category_id').prop('disabled', true);
            }
            if (hasLockClass) {
                $('#quick_category_id').addClass('bg-light');
            }
        }

        function loadCategoriesForFilters() {
            // Store current quick panel state
            const quickCategoryValue = $('#quick_category_id').val();
            const quickCategoryDisabled = $('#quick_category_id').prop('disabled');
            const quickCategoryHasLockClass = $('#quick_category_id').hasClass('bg-light');
            
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_categories' },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        // Update quick add category dropdown
                        const quickCategorySelect = $('#quick_category_id');
                        quickCategorySelect.empty().append('<option value="">Select Category</option>');
                        
                        // Update edit category dropdown
                        const editCategorySelect = $('#edit_category_id');
                        editCategorySelect.empty().append('<option value="">Select Category</option>');
                        
                        result.data.forEach(function(category) {
                            quickCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                            editCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                        });
                        
                        // Restore quick panel state after loading categories
                        if (quickCategoryValue) {
                            quickCategorySelect.val(quickCategoryValue);
                        }
                        if (quickCategoryDisabled) {
                            quickCategorySelect.prop('disabled', true);
                        }
                        if (quickCategoryHasLockClass) {
                            quickCategorySelect.addClass('bg-light');
                        }
                    }
                }
            });
        }



        // Enhanced refresh function with error handling
        function refreshDataTable() {
            if (questionsTable) {
                questionsTable.ajax.reload();
            } else if (categoriesTable) {
                categoriesTable.ajax.reload();
            }
        }

        function duplicateQuestion(questionId) {
            if (confirm('Are you sure you want to duplicate this question?')) {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: { action: 'duplicate_question', question_id: questionId },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            refreshCurrentDataTable();
                            showSuccessToast('Question duplicated successfully!');
                        } else {
                            showErrorToast(result.message);
                        }
                    }
                });
            }
        }



        function showSuccessToast(message) {
            // Simple toast notification
            const toast = $(`
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div class="toast show" role="alert">
                        <div class="toast-header bg-success text-white">
                            <i class="ri-check-line me-2"></i>
                            <strong class="me-auto">Success</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">${message}</div>
                    </div>
                </div>
            `);
            
            $('body').append(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function showErrorToast(message) {
            // Error toast notification
            const toast = $(`
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div class="toast show" role="alert">
                        <div class="toast-header bg-danger text-white">
                            <i class="ri-error-warning-line me-2"></i>
                            <strong class="me-auto">Error</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">${message}</div>
                    </div>
                </div>
            `);
            
            $('body').append(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        function showInfoToast(message) {
            // Info toast notification
            const toast = $(`
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div class="toast show" role="alert">
                        <div class="toast-header bg-info text-white">
                            <i class="ri-information-line me-2"></i>
                            <strong class="me-auto">Info</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">${message}</div>
                    </div>
                </div>
            `);
            
            $('body').append(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Audio player functionality
        let audioPlayers = {};
        let currentPlayingId = null;

        function toggleAudio(questionId, button) {
            // Stop currently playing audio if different
            if (currentPlayingId && currentPlayingId !== questionId) {
                stopAudio(currentPlayingId);
            }

            // If this audio is already playing, pause it
            if (audioPlayers[questionId] && !audioPlayers[questionId].paused) {
                pauseAudio(questionId, button);
                return;
            }

            // If audio exists but is paused, resume it
            if (audioPlayers[questionId]) {
                resumeAudio(questionId, button);
                return;
            }

            // Create new audio player
            createAudioPlayer(questionId, button);
        }

        function createAudioPlayer(questionId, button) {
            // Show progress bar and time display
            $(`#audio-player-${questionId} .audio-progress`).fadeIn(200);
            $(`#audio-player-${questionId} .audio-time`).fadeIn(200);
            
            // Create form data for audio request
            const formData = new FormData();
            formData.append('action', 'get_audio');
            formData.append('question_id', questionId);

            // Fetch audio as blob
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Audio not found');
                return response.blob();
            })
            .then(blob => {
                const audioUrl = URL.createObjectURL(blob);
                const audio = new Audio(audioUrl);
                
                audioPlayers[questionId] = audio;
                currentPlayingId = questionId;

                // Update button to pause icon
                $(button).html('<i class="ri-pause-fill"></i>');
                $(button).removeClass('btn-primary').addClass('btn-success');

                // Set up event listeners
                audio.addEventListener('loadedmetadata', function() {
                    updateDuration(questionId, audio.duration);
                });

                audio.addEventListener('timeupdate', function() {
                    updateProgress(questionId, audio.currentTime, audio.duration);
                });

                audio.addEventListener('ended', function() {
                    resetAudioPlayer(questionId, button);
                    URL.revokeObjectURL(audioUrl);
                });

                audio.addEventListener('error', function(e) {
                    console.error('Audio error:', e);
                    showErrorToast('Error playing audio file');
                    resetAudioPlayer(questionId, button);
                });

                // Play audio
                audio.play().catch(err => {
                    console.error('Play error:', err);
                    showErrorToast('Error playing audio: ' + err.message);
                    resetAudioPlayer(questionId, button);
                });
            })
            .catch(err => {
                console.error('Fetch error:', err);
                alert('Error loading audio file');
            });
        }

        function pauseAudio(questionId, button) {
            if (audioPlayers[questionId]) {
                audioPlayers[questionId].pause();
                $(button).html('<i class="ri-play-fill"></i>');
                $(button).removeClass('btn-success').addClass('btn-primary');
                currentPlayingId = null;
            }
        }

        function resumeAudio(questionId, button) {
            if (audioPlayers[questionId]) {
                audioPlayers[questionId].play();
                $(button).html('<i class="ri-pause-fill"></i>');
                $(button).removeClass('btn-primary').addClass('btn-success');
                currentPlayingId = questionId;
            }
        }

        function stopAudio(questionId) {
            if (audioPlayers[questionId]) {
                audioPlayers[questionId].pause();
                audioPlayers[questionId].currentTime = 0;
                
                const button = $(`#audio-player-${questionId} .play-btn`);
                $(button).html('<i class="ri-play-fill"></i>');
                $(button).removeClass('btn-success').addClass('btn-primary');
                
                updateProgress(questionId, 0, audioPlayers[questionId].duration);
            }
        }

        function resetAudioPlayer(questionId, button) {
            $(button).html('<i class="ri-play-fill"></i>');
            $(button).removeClass('btn-success').addClass('btn-primary');
            updateProgress(questionId, 0, 0);
            currentPlayingId = null;
            
            if (audioPlayers[questionId]) {
                delete audioPlayers[questionId];
            }
        }

        function updateProgress(questionId, currentTime, duration) {
            const progress = duration > 0 ? (currentTime / duration) * 100 : 0;
            $(`#progress-${questionId}`).css('width', progress + '%');
            $(`#current-time-${questionId}`).text(formatTime(currentTime));
        }

        function updateDuration(questionId, duration) {
            $(`#duration-${questionId}`).text(formatTime(duration));
        }

        function seekAudio(event, questionId) {
            if (!audioPlayers[questionId]) return;

            const progressBar = $(event.currentTarget);
            const clickX = event.offsetX;
            const width = progressBar.width();
            const percentage = clickX / width;
            
            const audio = audioPlayers[questionId];
            audio.currentTime = percentage * audio.duration;
        }

        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return mins + ':' + (secs < 10 ? '0' : '') + secs;
        }

        // Preview audio functions for quick add form
        function previewQuickAudio(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const audioUrl = URL.createObjectURL(file);
                
                $('#quick_audio_filename').text(file.name);
                $('#quick_preview_audio').attr('src', audioUrl);
                $('#quick_audio_preview').slideDown();
            }
        }

        function removeQuickAudio() {
            $('#quick_audio_file').val('');
            $('#quick_preview_audio').attr('src', '');
            $('#quick_audio_preview').slideUp();
        }

        function togglePreviewAudio(audioId, button) {
            const audio = document.getElementById(audioId);
            
            if (audio.paused) {
                audio.play();
                $(button).html('<i class="ri-pause-fill"></i>');
                $(button).removeClass('btn-primary').addClass('btn-success');
            } else {
                audio.pause();
                $(button).html('<i class="ri-play-fill"></i>');
                $(button).removeClass('btn-success').addClass('btn-primary');
            }
        }

        // Preview audio functions for edit modal
        let editCurrentAudio = null;

        function previewEditAudio(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const audioUrl = URL.createObjectURL(file);
                
                $('#edit_audio_filename').text(file.name);
                $('#edit_preview_audio').attr('src', audioUrl);
                $('#edit_audio_preview').slideDown();
                
                // Hide current audio when new one is selected
                $('#edit_current_audio').slideUp();
            }
        }

        function removeEditAudio() {
            $('#edit_audio_file').val('');
            $('#edit_preview_audio').attr('src', '');
            $('#edit_audio_preview').slideUp();
            
            // Show current audio again if it exists
            if ($('#edit_current_filename').text()) {
                $('#edit_current_audio').slideDown();
            }
        }

        function toggleEditCurrentAudio(button) {
            const questionId = $('#edit_question_id').val();
            
            if (!editCurrentAudio) {
                // Create audio player for current audio
                const formData = new FormData();
                formData.append('action', 'get_audio');
                formData.append('question_id', questionId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.blob())
                .then(blob => {
                    const audioUrl = URL.createObjectURL(blob);
                    editCurrentAudio = new Audio(audioUrl);
                    
                    $(button).html('<i class="ri-pause-fill"></i>');
                    $(button).removeClass('btn-primary').addClass('btn-success');
                    
                    editCurrentAudio.addEventListener('timeupdate', function() {
                        const progress = (editCurrentAudio.currentTime / editCurrentAudio.duration) * 100;
                        $('#edit_current_progress').css('width', progress + '%');
                        $('#edit_current_time').text(formatTime(editCurrentAudio.currentTime));
                    });
                    
                    editCurrentAudio.addEventListener('loadedmetadata', function() {
                        $('#edit_current_duration').text(formatTime(editCurrentAudio.duration));
                    });
                    
                    editCurrentAudio.addEventListener('ended', function() {
                        $(button).html('<i class="ri-play-fill"></i>');
                        $(button).removeClass('btn-success').addClass('btn-primary');
                        $('#edit_current_progress').css('width', '0%');
                    });
                    
                    editCurrentAudio.play();
                })
                .catch(err => {
                    console.error('Error loading audio:', err);
                    showErrorToast('Error loading audio file');
                });
            } else {
                if (editCurrentAudio.paused) {
                    editCurrentAudio.play();
                    $(button).html('<i class="ri-pause-fill"></i>');
                    $(button).removeClass('btn-primary').addClass('btn-success');
                } else {
                    editCurrentAudio.pause();
                    $(button).html('<i class="ri-play-fill"></i>');
                    $(button).removeClass('btn-success').addClass('btn-primary');
                }
            }
        }

        function seekEditAudio(event) {
            if (!editCurrentAudio) return;
            
            const progressBar = $(event.currentTarget);
            const clickX = event.offsetX;
            const width = progressBar.width();
            const percentage = clickX / width;
            
            editCurrentAudio.currentTime = percentage * editCurrentAudio.duration;
        }

        function toggleQuestionAudio(questionId, button) {
            const icon = button.querySelector('i');
            
            // Create form data for POST request
            const formData = new FormData();
            formData.append('action', 'get_audio');
            formData.append('question_id', questionId);
            
            if (icon.classList.contains('ri-play-fill')) {
                // Fetch audio file and play
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Audio file not found');
                    }
                    return response.blob();
                })
                .then(blob => {
                    const audioUrl = URL.createObjectURL(blob);
                    const audio = new Audio(audioUrl);
                    
                    // Store audio reference on button for pause functionality
                    button.audioElement = audio;
                    
                    audio.play().then(() => {
                        icon.classList.remove('ri-play-fill');
                        icon.classList.add('ri-pause-fill');
                    }).catch(error => {
                        console.error('Error playing audio:', error);
                        showErrorToast('Error playing audio file');
                    });
                    
                    audio.onended = function() {
                        icon.classList.remove('ri-pause-fill');
                        icon.classList.add('ri-play-fill');
                        URL.revokeObjectURL(audioUrl);
                        delete button.audioElement;
                    };
                })
                .catch(error => {
                    console.error('Error loading audio:', error);
                    showErrorToast('Error loading audio file');
                });
            } else {
                // Pause audio
                if (button.audioElement) {
                    button.audioElement.pause();
                    icon.classList.remove('ri-pause-fill');
                    icon.classList.add('ri-play-fill');
                    delete button.audioElement;
                }
            }
        }

        // Global variables for image management
        let quickImageFiles = [];
        let editImageFiles = [];
        let currentCorrectAnswer = { quick: '', edit: '' };

        // Image handling functions
        function triggerImageUpload(inputId) {
            document.getElementById(inputId).click();
        }

        function addQuickImages(input) {
            if (input.files && input.files.length > 0) {
                // Add new files to existing array
                for (let i = 0; i < input.files.length; i++) {
                    quickImageFiles.push(input.files[i]);
                }
                
                // Clear the input to allow selecting the same files again
                input.value = '';
                
                // Update preview
                updateQuickImagesPreview();
                
                // Update form data
                updateQuickImagesFormData();
            }
        }

        function updateQuickImagesPreview() {
            const container = $('#quick_images_container');
            container.empty();
            
            if (quickImageFiles.length === 0) {
                $('#quick_images_preview').hide();
                return;
            }
            
            quickImageFiles.forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const imageId = `image${index + 1}`;
                    const isCorrect = currentCorrectAnswer.quick === imageId;
                    
                    const imageDiv = $(`
                        <div class="image-preview-item ${isCorrect ? 'correct-answer' : ''}" data-image-id="${imageId}" data-index="${index}">
                            <img src="${e.target.result}" onclick="setCorrectAnswer('quick', '${imageId}', ${index})" title="Click to set as correct answer">
                            <button type="button" class="image-remove" onclick="removeQuickImage(${index})" title="Remove image">
                                <i class="ri-close-line"></i>
                            </button>
                            <div class="image-label">${imageId}</div>
                            ${isCorrect ? '<div class="correct-badge">CORRECT</div>' : ''}
                        </div>
                    `);
                    container.append(imageDiv);
                };
                
                reader.readAsDataURL(file);
            });
            
            $('#quick_images_preview').slideDown();
        }

        function removeQuickImage(index) {
            // Remove the file from array
            quickImageFiles.splice(index, 1);
            
            // Update correct answer if the removed image was selected
            const removedImageId = `image${index + 1}`;
            if (currentCorrectAnswer.quick === removedImageId) {
                currentCorrectAnswer.quick = '';
                $('#quick_correct_image').val('');
            }
            
            // Update image IDs for remaining images
            if (currentCorrectAnswer.quick) {
                const currentIndex = parseInt(currentCorrectAnswer.quick.replace('image', '')) - 1;
                if (currentIndex > index) {
                    currentCorrectAnswer.quick = `image${currentIndex}`;
                    $('#quick_correct_image').val(currentCorrectAnswer.quick);
                }
            }
            
            // Update preview
            updateQuickImagesPreview();
            
            // Update form data
            updateQuickImagesFormData();
        }

        function removeQuickImages() {
            quickImageFiles = [];
            currentCorrectAnswer.quick = '';
            $('#quick_correct_image').val('');
            $('#quick_images_preview').slideUp();
            updateQuickImagesFormData();
        }

        function updateQuickImagesFormData() {
            // Create a new DataTransfer object to store files
            const dt = new DataTransfer();
            quickImageFiles.forEach(file => dt.items.add(file));
            
            // Update the hidden input or create a new file input for form submission
            const formData = new FormData();
            quickImageFiles.forEach((file, index) => {
                formData.append('image_files[]', file);
            });
            
            // Store files in a way that can be accessed during form submission
            $('#quickAddForm')[0].quickImageFiles = quickImageFiles;
        }

        function setCorrectAnswer(formType, imageId, index) {
            currentCorrectAnswer[formType] = imageId;
            $(`#${formType}_correct_image`).val(imageId);
            
            // Show success feedback
            const input = $(`#${formType}_correct_image`);
            input.removeClass('is-invalid').addClass('is-valid');
            setTimeout(() => {
                input.removeClass('is-valid');
            }, 2000);
            
            // Update visual indicators
            if (formType === 'quick') {
                $('#quick_images_container .image-preview-item').removeClass('correct-answer');
                $('#quick_images_container .correct-badge').remove();
                
                const selectedItem = $(`#quick_images_container .image-preview-item[data-image-id="${imageId}"]`);
                selectedItem.addClass('correct-answer');
                selectedItem.append('<div class="correct-badge">CORRECT</div>');
            } else {
                $('#edit_images_container .image-preview-item, #edit_current_images_container .image-preview-item').removeClass('correct-answer');
                $('#edit_images_container .correct-badge, #edit_current_images_container .correct-badge').remove();
                
                const selectedItem = $(`#edit_images_container .image-preview-item[data-image-id="${imageId}"], #edit_current_images_container .image-preview-item[data-image-id="${imageId}"]`);
                selectedItem.addClass('correct-answer');
                selectedItem.append('<div class="correct-badge">CORRECT</div>');
            }
            
            // Show toast notification
            showInfoToast(`Image "${imageId}" set as correct answer`);
        }

        function clearCorrectAnswer(formType) {
            currentCorrectAnswer[formType] = '';
            $(`#${formType}_correct_image`).val('').removeClass('is-valid is-invalid');
            
            // Remove visual indicators
            const containerSelector = formType === 'quick' ? '#quick_images_container' : '#edit_images_container, #edit_current_images_container';
            $(`${containerSelector} .image-preview-item`).removeClass('correct-answer');
            $(`${containerSelector} .correct-badge`).remove();
            
            // Show feedback
            showInfoToast('Correct answer cleared');
        }

        function addEditImages(input) {
            if (input.files && input.files.length > 0) {
                // Add new files to existing array
                for (let i = 0; i < input.files.length; i++) {
                    editImageFiles.push(input.files[i]);
                }
                
                // Clear the input to allow selecting the same files again
                input.value = '';
                
                // Update preview
                updateEditImagesPreview();
                
                // Update form data
                updateEditImagesFormData();
            }
        }

        function updateEditImagesPreview() {
            const container = $('#edit_images_container');
            container.empty();
            
            if (editImageFiles.length === 0) {
                $('#edit_images_preview').hide();
                return;
            }
            
            // Count existing current images to continue numbering
            const currentImageCount = $('#edit_current_images_container .image-preview-item').length;
            
            editImageFiles.forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Number new images starting after current images
                    const imageNumber = currentImageCount + index + 1;
                    const imageId = `image${imageNumber}`;
                    const isCorrect = currentCorrectAnswer.edit === imageId;
                    
                    const imageDiv = $(`
                        <div class="image-preview-item ${isCorrect ? 'correct-answer' : ''}" data-image-id="${imageId}" data-index="${index}" data-is-new="true">
                            <img src="${e.target.result}" onclick="setCorrectAnswer('edit', '${imageId}', ${index})" title="Click to set as correct answer">
                            <button type="button" class="image-remove" onclick="removeEditImage(${index})" title="Remove image">
                                <i class="ri-close-line"></i>
                            </button>
                            <div class="image-label">${imageId}</div>
                            ${isCorrect ? '<div class="correct-badge">CORRECT</div>' : ''}
                        </div>
                    `);
                    container.append(imageDiv);
                };
                
                reader.readAsDataURL(file);
            });
            
            $('#edit_images_preview').slideDown();
        }

        function removeEditImage(index) {
            // Get the current image count before removal
            const currentImageCount = $('#edit_current_images_container .image-preem').length;
            
            // Calculate the image ID that will be removed
            const removedImageNumber = currentImageCount + index + 1;
            const removedImageId = `image${removedImageNumber}`;
            
            // Remove the file from array
            editImageFiles.splice(index, 1);
            
            // Update correct answer if the removed image was selected
            if (currentCorrectAnswer.edit === removedImageId) {
                currentCorrectAnswer.edit = '';
                $('#edit_correct_image').val('');
            }
            
            // Update image IDs for remaining new images if correct answer is a new image
            if (currentCorrectAnswer.edit && currentCorrectAnswer.edit.startsWith('image')) {
                const correctImageNumber = parseInt(currentCorrectAnswer.edit.replace('image', ''));
                // If the correct answer is a new image that comes after the removed one
                if (correctImageNumber > removedImageNumber) {
                    currentCorrectAnswer.edit = `image${correctImageNumber - 1}`;
                    $('#edit_correct_image').val(currentCorrectAnswer.edit);
                }
            }
            
            // Update preview
            updateEditImagesPreview();
            
            // Update form data
            updateEditImagesFormData();
        }

        function removeEditImages() {
            editImageFiles = [];
            
            // Clear correct answer if it was a new image (numbered after current images)
            const currentImageCount = $('#edit_current_images_container .image-preview-item').length;
            if (currentCorrectAnswer.edit && currentCorrectAnswer.edit.startsWith('image')) {
                const correctImageNumber = parseInt(currentCorrectAnswer.edit.replace('image', ''));
                // If correct answer is a new image (number > current image count)
                if (correctImageNumber > currentImageCount) {
                    currentCorrectAnswer.edit = '';
                    $('#edit_correct_image').val('');
                }
            }
            
            $('#edit_images_preview').slideUp();
            updateEditImagesFormData();
        }

        function updateEditImagesFormData() {
            // Store files in a way that can be accessed during form submission
            $('#editQuestionForm')[0].editImageFiles = editImageFiles;
        }

        function displayCurrentImages(imageFilesJson, questionId) {
            const container = $('#edit_current_images_container');
            container.empty();
            
            try {
                const imageFiles = JSON.parse(imageFilesJson);
                if (Array.isArray(imageFiles)) {
                    imageFiles.forEach((imagePath, index) => {
                        const imageId = `image${index + 1}`;
                        const isCorrect = currentCorrectAnswer.edit === imageId;
                        
                        const imageDiv = $(`
                            <div class="image-preview-item ${isCorrect ? 'correct-answer' : ''}" data-image-id="${imageId}" data-index="${index}">
                                <img src="#" onclick="setCorrectAnswer('edit', '${imageId}', ${index})" title="Click to set as correct answer" data-question-id="${questionId}" data-image-index="${index}">
                                <button type="button" class="image-remove" onclick="removeCurrentImage(${questionId}, ${index})" title="Remove image">
                                    <i class="ri-close-line"></i>
                                </button>
                                <div class="image-label">${imageId}</div>
                                ${isCorrect ? '<div class="correct-badge">CORRECT</div>' : ''}
                            </div>
                        `);
                        
                        // Load image via AJAX
                        const formData = new FormData();
                        formData.append('action', 'get_image');
                        formData.append('question_id', questionId);
                        formData.append('image_index', index);
                        
                        fetch('', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.blob())
                        .then(blob => {
                            const imageUrl = URL.createObjectURL(blob);
                            imageDiv.find('img').attr('src', imageUrl);
                        })
                        .catch(error => {
                            console.error('Error loading image:', error);
                            imageDiv.find('img').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEyIiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+');
                        });
                        
                        container.append(imageDiv);
                    });
                }
            } catch (e) {
                console.error('Error parsing image files JSON:', e);
            }
        }

        function removeCurrentImage(questionId, index) {
            // Find and remove the image preview element
            const imageItem = $(`#edit_current_images_container .image-preview-item[data-index="${index}"]`);
            
            if (imageItem.length > 0) {
                // Get the image ID before removing
                const imageId = imageItem.attr('data-image-id');
                
                // If this was the correct answer, clear it
                if (currentCorrectAnswer.edit === imageId) {
                    currentCorrectAnswer.edit = '';
                    $('#edit_correct_image').val('');
                }
                
                // Remove the image element with animation
                imageItem.fadeOut(300, function() {
                    $(this).remove();
                    
                    // Renumber remaining images
                    let newIndex = 1;
                    $('#edit_current_images_container .image-preview-item').each(function() {
                        const newImageId = `image${newIndex}`;
                        $(this).attr('data-image-id', newImageId);
                        $(this).attr('data-index', newIndex - 1);
                        $(this).find('.image-label').first().text(newImageId);
                        
                        // Update onclick handler
                        const img = $(this).find('img');
                        img.attr('onclick', `setCorrectAnswer('edit', '${newImageId}', ${newIndex - 1})`);
                        
                        // Update remove button
                        const removeBtn = $(this).find('.image-remove');
                        removeBtn.attr('onclick', `removeCurrentImage(${questionId}, ${newIndex - 1})`);
                        
                        newIndex++;
                    });
                    
                    // Update correct answer if needed
                    if (currentCorrectAnswer.edit && currentCorrectAnswer.edit.startsWith('image')) {
                        const correctIndex = parseInt(currentCorrectAnswer.edit.replace('image', ''));
                        if (correctIndex > index + 1) {
                            // Adjust the correct answer index
                            currentCorrectAnswer.edit = `image${correctIndex - 1}`;
                            $('#edit_correct_image').val(currentCorrectAnswer.edit);
                        }
                    }
                    
                    // Check if there are any images left
                    if ($('#edit_current_images_container .image-preview-item').length === 0) {
                        $('#edit_current_images').hide();
                    }
                });
                
                showInfoToast('Image removed. Changes will be saved when you update the question.');
            }
        }

        function viewQuestionImages(questionId, questionText, imageFilesJson, correctImage) {
              
            $('#imageViewerModalLabel').text(`Question Images - ID: ${questionId}`);
            $('#imageViewerQuestionText').text(questionText); 
            if (correctImage) {
                $('#imageViewerCorrectAnswer').html(`<strong>Correct Answer:</strong> <span class="badge bg-success">${correctImage}</span>`);
            } else {
                $('#imageViewerCorrectAnswer').html(`<span class="badge bg-secondary">No correct answer set</span>`);
            }
            
            // Clear previous images
            const container = $('#imageViewerContainer');
            container.empty();
            
            try {
                const imageFiles = JSON.parse(imageFilesJson);
                if (Array.isArray(imageFiles) && imageFiles.length > 0) {
                    imageFiles.forEach((imagePath, index) => {
                        const imageId = `image${index + 1}`;
                        const isCorrect = correctImage === imageId;
                        
                        const imageDiv = $(`
                            <div class="col-md-4 col-sm-6">
                                <div class="image-viewer-item ${isCorrect ? 'correct-answer' : ''}" data-image-id="${imageId}">
                                    <img src="#" alt="${imageId}" onclick="openImageFullscreen(this)" data-question-id="${questionId}" data-image-index="${index}">
                                    <div class="image-label">
                                        ${imageId}
                                        ${isCorrect ? '<div class="correct-badge">CORRECT ANSWER</div>' : ''}
                                    </div>
                                </div>
                            </div>
                        `);
                         
                        let cleanImagePath = imagePath.replace(/\\\//g, '/').replace(/\\/g, '/'); 
                        if (!cleanImagePath.startsWith('uploads/')) {
                            cleanImagePath = 'uploads/' + cleanImagePath.replace(/^.*uploads[\/\\]/, '');
                        }
                        
                        const imageUrl = '../../../' + cleanImagePath;
                        
                        console.log('Loading image:', {questionId, index, imagePath, cleanImagePath, imageUrl}); 
                        imageDiv.find('img').attr('src', imageUrl); 
                        imageDiv.find('img').on('error', function() {
                            console.error('Error loading image:', imageUrl); 
                            const altPath = '../../../uploads/' + imagePath.split('/').pop().split('\\').pop();
                            console.log('Trying alternative path:', altPath);
                            $(this).attr('src', altPath); 
                            $(this).on('error', function() {
                                $(this).attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjE1MCIgeT0iMTAwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTYiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5JbWFnZSBOb3QgRm91bmQ8L3RleHQ+PC9zdmc>');
                            });
                        });
                        
                        container.append(imageDiv);
                    });
                } else {
                    container.html('<div class="col-12"><div class="alert alert-info">No images found for this question.</div></div>');
                }
            } catch (e) {
                console.error('Error parsing image files JSON:', e);
                container.html('<div class="col-12"><div class="alert alert-danger">Error loading images.</div></div>');
            }
            
            // Show the modal
            $('#imageViewerModal').modal('show');
        }
        
        function openImageFullscreen(img) {
            // Create a fullscreen overlay
            const overlay = $(`
                <div id="imageFullscreenOverlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.9);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                ">
                    <img src="${img.src}" style="
                        max-width: 80%;
                        max-height: 80%;
                        object-fit: contain;
                        border-radius: 8px;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.5);
                    ">
                    <div style="
                        position: absolute;
                        top: 20px;
                        right: 20px;
                        color: white;
                        font-size: 24px;
                        cursor: pointer;
                        background: rgba(0,0,0,0.5);
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    " onclick="closeImageFullscreen()">×</div>
                </div>
            `);
            
            $('body').append(overlay);
            
            // Close on click anywhere
            overlay.on('click', function(e) {
                if (e.target === this) {
                    closeImageFullscreen();
                }
            });
            
            // Close on escape key
            $(document).on('keydown.fullscreen', function(e) {
                if (e.key === 'Escape') {
                    closeImageFullscreen();
                }
            });
        }
        
        function closeImageFullscreen() {
            $('#imageFullscreenOverlay').remove();
            $(document).off('keydown.fullscreen');
        }
   

    </script>

<?php require("../layout/Footer.php"); ?>
