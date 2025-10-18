<?php
require("../layout/Session.php");
require("../../config/db.php");
require("../../config/upload_config.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'get_conversations':
            getConversations($conn);
            break;
        case 'get_conversation':
            getConversation($conn);
            break;
        case 'add_conversation':
            addConversation($conn);
            break;
        case 'update_conversation':
            updateConversation($conn);
            break;
        case 'delete_conversation':
            deleteConversation($conn);
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
        case 'get_sentences_by_category':
            getSentencesByCategory($conn);
            break;
        case 'get_conversations_by_category':
            getConversationsByCategory($conn);
            break;
        case 'duplicate_conversation':
            duplicateConversation($conn);
            break;
        case 'get_audio':
            getAudioFile($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function getAudioFile($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    $sql = "SELECT audio_file, audio_file_name FROM speaking_repeat_after_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['audio_file'])) { 
            $filePath = '../../../' . $row['audio_file']; 
            if (file_exists($filePath)) { 
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo); 
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

function getConversations($conn) {
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
            0 => 'sr.question_id',
            1 => 'sr.sentence_text', 
            2 => 'sc.category_name',
            3 => 'sr.audio_file_name',
            4 => 'sr.phonetic_text',
            5 => 'sr.tips',
            6 => 'sr.points',
            7 => 'sr.is_active',
            8 => 'sr.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'sr.created_at') . ' ' . $orderDir;
        
        // Base filtering
        $where = " WHERE 1=1 ";
        
        // Apply category filter if provided
        $categoryFilter = $params['category_filter'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND sr.category_id = " . intval($categoryFilter) . " ";
        }
        
        // Total records count (with category filter applied)
        $totalSql = "SELECT COUNT(*) as total FROM speaking_repeat_after_questions sr LEFT JOIN speaking_repeat_after sc ON sr.category_id = sc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        // Apply search filter
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (sr.sentence_text LIKE '%$search%' OR sc.category_name LIKE '%$search%' OR sr.phonetic_text LIKE '%$search%' OR sr.tips LIKE '%$search%') ";
        }
        
        // Filtered count
        $filteredSql = "SELECT COUNT(*) as total FROM speaking_repeat_after_questions sr LEFT JOIN speaking_repeat_after sc ON sr.category_id = sc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        // Pagination
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        // Main data query
        $sql = "SELECT sr.question_id, sr.sentence_text, sr.category_id, sr.phonetic_text, sr.tips, 
                       sr.points, sr.is_active, sr.created_at, sr.audio_file_name, 
                       sr.audio_file_size, sc.category_name
                FROM speaking_repeat_after_questions sr 
                LEFT JOIN speaking_repeat_after sc ON sr.category_id = sc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'question_id' => $row['question_id'],
                    'sentence_text' => $row['sentence_text'],
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'category_id' => $row['category_id'],
                    'phonetic_text' => $row['phonetic_text'] ?? '',
                    'tips' => $row['tips'] ?? '',
                    'points' => $row['points'],
                    'is_active' => $row['is_active'],
                    'created_at' => $row['created_at'],
                    'audio_file_name' => $row['audio_file_name'],
                    'audio_file_size' => $row['audio_file_size']
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

function getConversation($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    $sql = "SELECT sr.question_id, sr.category_id, sr.sentence_text, sr.phonetic_text, sr.tips, 
                   sr.points, sr.is_active, sr.audio_file_name, sr.audio_file_size, 
                   sr.created_at, sr.updated_at, sc.category_name 
            FROM speaking_repeat_after_questions sr 
            LEFT JOIN speaking_repeat_after sc ON sr.category_id = sc.category_id 
            WHERE sr.question_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Sentence not found']);
    }
}

function addConversation($conn) {
    try {
        // Validate and sanitize input data
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $sentence_text = trim($_POST['sentence_text'] ?? '');
        $phonetic_text = trim($_POST['phonetic_text'] ?? '');
        $tips = trim($_POST['tips'] ?? '');
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        // Input validation
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($sentence_text) || strlen($sentence_text) < 5) {
            throw new Exception('Sentence text must be at least 5 characters long');
        }
        
        // Handle audio file upload with industry standards
        $audioData = handleAudioUpload();
        
        // Begin database transaction for data integrity
        $conn->begin_transaction();
        
        try {
            // Check if audio file was actually uploaded
            $hasAudioFile = !empty($audioData['file_path']);
            
            // Always use full schema with audio columns
            $sql = "INSERT INTO speaking_repeat_after_questions (category_id, sentence_text, audio_file, audio_file_name, audio_file_size, phonetic_text, tips, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            if ($hasAudioFile) {
                // Store only the file path (not binary data)
                $stmt->bind_param("issssssi", 
                    $category_id, $sentence_text, $audioData['file_path'], $audioData['original_name'], 
                    $audioData['file_size'], $phonetic_text, $tips, $is_active
                );
            } else {
                // No audio file - insert empty values
                $emptyPath = '';
                $emptyFileName = '';
                $emptySize = 0;
                
                $stmt->bind_param("issssssi", 
                    $category_id, $sentence_text, $emptyPath, $emptyFileName, 
                    $emptySize, $phonetic_text, $tips, $is_active
                );
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $conn->commit();
            
            // Log successful operation
            error_log("Sentence added successfully: ID $insertId, Category: $category_id, Audio: " . ($audioData['file_path'] ? 'Yes' : 'No'));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Sentence added successfully', 
                'id' => $insertId,
                'audio_uploaded' => !empty($audioData['file_path']),
                'file_name' => $audioData['original_name']
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            // Clean up uploaded file if database insert failed
            if (!empty($audioData['file_path']) && file_exists('../../../' . $audioData['file_path'])) {
                unlink('../../../' . $audioData['file_path']);
                error_log("Cleaned up uploaded file after database error: " . $audioData['file_path']);
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add Sentence Error: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateConversation($conn) {
    try {
        $question_id = $_POST['question_id'] ?? 0;
        $category_id = $_POST['category_id'] ?? 0;
        $sentence_text = $_POST['sentence_text'] ?? '';
        $phonetic_text = $_POST['phonetic_text'] ?? '';
        $tips = $_POST['tips'] ?? '';
        $is_active = $_POST['is_active'] ?? 1;
        
        // Handle audio file upload only if a file is actually uploaded
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] == 0) {
            try {
                $audioData = handleAudioUpload();
                
                if (!empty($audioData['file_path'])) {
                    // Store only the file path (not binary data)
                    $sql = "UPDATE speaking_repeat_after_questions SET category_id=?, sentence_text=?, audio_file=?, audio_file_name=?, audio_file_size=?, phonetic_text=?, tips=?, is_active=?, updated_at=NOW() WHERE question_id=?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare failed: ' . $conn->error);
                    }
                    
                    // Bind parameters with file path
                    $stmt->bind_param("isssssiii", $category_id, $sentence_text, $audioData['file_path'], $audioData['original_name'], $audioData['file_size'], $phonetic_text, $tips, $is_active, $question_id);
                } else {
                    // No audio uploaded, update without audio fields
                    $sql = "UPDATE speaking_repeat_after_questions SET category_id=?, sentence_text=?, phonetic_text=?, tips=?, is_active=?, updated_at=NOW() WHERE question_id=?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare failed: ' . $conn->error);
                    }
                    $stmt->bind_param("isssii", $category_id, $sentence_text, $phonetic_text, $tips, $is_active, $question_id);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error uploading audio: ' . $e->getMessage()]);
                return;
            }
        } else {
            // No audio file uploaded, update without audio fields
            $sql = "UPDATE speaking_repeat_after_questions SET category_id=?, sentence_text=?, phonetic_text=?, tips=?, is_active=?, updated_at=NOW() WHERE question_id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("isssii", $category_id, $sentence_text, $phonetic_text, $tips, $is_active, $question_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Sentence updated successfully']);
        } else {
            throw new Exception('Error updating sentence: ' . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Update Sentence Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteConversation($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    // Get audio file path before deleting
    $getFileSql = "SELECT audio_file FROM speaking_repeat_after_questions WHERE question_id = ?";
    $getStmt = $conn->prepare($getFileSql);
    $getStmt->bind_param("i", $question_id);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $audioFile = $result->fetch_assoc()['audio_file'] ?? '';
    
    $sql = "DELETE FROM speaking_repeat_after_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    
    if ($stmt->execute()) {
        // Delete audio file if exists
        if (!empty($audioFile) && file_exists('../../../' . $audioFile)) {
            unlink('../../../' . $audioFile);
        }
        
        echo json_encode(['success' => true, 'message' => 'Sentence deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting sentence: ' . $conn->error]);
    }
}

function getCategories($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'speaking_repeat_after'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    // For admin interface, show all categories (both active and inactive)
    $sql = "SELECT category_id, category_name, category_description, 
                   display_order, is_active, created_at, points
            FROM speaking_repeat_after 
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
    
    $sql = "SELECT * FROM speaking_repeat_after WHERE category_id = ?";
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
    $tableCheck = $conn->query("SHOW TABLES LIKE 'speaking_repeat_after'");
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
            $adjustSql = "UPDATE speaking_repeat_after SET display_order = display_order + 1 WHERE display_order >= ?";
            $adjustStmt = $conn->prepare($adjustSql);
            $adjustStmt->bind_param("i", $display_order);
            $adjustStmt->execute();
            $adjustStmt->close();
        }
        
        // Insert the new category
        $sql = "INSERT INTO speaking_repeat_after (category_name, category_description, display_order, points) VALUES (?, ?, ?, ?)";
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
        $getCurrentSql = "SELECT display_order FROM speaking_repeat_after WHERE category_id = ?";
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
                $adjustSql = "UPDATE speaking_repeat_after SET display_order = display_order + 1 
                             WHERE display_order >= ? AND display_order < ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $new_display_order, $old_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            } else {
                // Moving DOWN (e.g., from 2 to 5)
                // Decrement positions 3, 4, 5 by 1 (old position + 1 to target position)
                $adjustSql = "UPDATE speaking_repeat_after SET display_order = display_order - 1 
                             WHERE display_order > ? AND display_order <= ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $old_display_order, $new_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            }
        }
        
        // Update the category with new position
        $sql = "UPDATE speaking_repeat_after SET category_name=?, category_description=?, display_order=?, points=? WHERE category_id=?";
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
    
    $sql = "DELETE FROM speaking_repeat_after WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $conn->error]);
    }
}

function getConversationsByCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT sr.*, sc.category_name 
            FROM speaking_repeat_after_questions sr 
            LEFT JOIN speaking_repeat_after sc ON sr.category_id = sc.category_id 
            WHERE sr.category_id = ? 
            ORDER BY sr.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $conversations]);
}

function getSentencesByCategory($conn) {
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
            0 => 'sr.question_id',
            1 => 'sr.sentence_text', 
            2 => 'sr.audio_file_name',
            3 => 'sr.phonetic_text',
            4 => 'sr.tips',
            5 => 'sr.is_active',
            6 => 'sr.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'sr.created_at') . ' ' . $orderDir;
        $where = " WHERE 1=1 ";
        
        $categoryFilter = $params['category_filter'] ?? $params['category_id'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND sr.category_id = " . intval($categoryFilter) . " ";
        }
        
        $totalSql = "SELECT COUNT(*) as total FROM speaking_repeat_after_questions sr LEFT JOIN speaking_repeat_after sc ON sr.category_id = sc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (sr.sentence_text LIKE '%$search%' OR sr.phonetic_text LIKE '%$search%' OR sr.tips LIKE '%$search%') ";
        }
        
        $filteredSql = "SELECT COUNT(*) as total FROM speaking_repeat_after_questions sr LEFT JOIN speaking_repeat_after sc ON sr.category_id = sc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        $sql = "SELECT sr.question_id, sr.sentence_text, sr.category_id, sr.phonetic_text, sr.tips, 
                       sr.is_active, sr.created_at, sr.audio_file_name, 
                       sr.audio_file_size, sc.category_name
                FROM speaking_repeat_after_questions sr 
                LEFT JOIN speaking_repeat_after sc ON sr.category_id = sc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'question_id' => $row['question_id'],
                    'sentence_text' => $row['sentence_text'],
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'category_id' => $row['category_id'],
                    'phonetic_text' => $row['phonetic_text'] ?? '',
                    'tips' => $row['tips'] ?? '',
                    'is_active' => $row['is_active'],
                    'created_at' => $row['created_at'],
                    'audio_file_name' => $row['audio_file_name'],
                    'audio_file_size' => $row['audio_file_size']
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

function duplicateConversation($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    // Get original sentence
    $sql = "SELECT * FROM speaking_repeat_after_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Insert duplicate with modified title
        $new_sentence_text = $row['sentence_text'] . ' (Copy)';
        
        // Handle audio file duplication
        $new_audio_file = null;
        $new_audio_name = null;
        $new_audio_size = null;
        $hasAudio = false;
        
        if (!empty($row['audio_file']) && file_exists('../../../' . $row['audio_file'])) {
            $original_path = '../../../' . $row['audio_file'];
            $file_extension = pathinfo($row['audio_file'], PATHINFO_EXTENSION);
            $unique_filename = generateSecureFilename($row['audio_file_name'], 'audio');
            
            // Use current year/month structure
            $yearMonth = date('Y/m');
            $fullUploadDir = getUploadDir('audio') . $yearMonth . '/';
            createSecureUploadDir($fullUploadDir, 'audio');
            
            $new_path = $fullUploadDir . $unique_filename;
            
            if (copy($original_path, $new_path)) {
                $new_audio_file = getRelativePath('audio', $yearMonth . '/' . $unique_filename);
                $new_audio_name = $row['audio_file_name'];
                $new_audio_size = $row['audio_file_size'];
                $hasAudio = true;
            }
        }
        
        // Insert with or without audio based on whether audio exists
        if ($hasAudio) {
            // Store the new file path
            $sql = "INSERT INTO speaking_repeat_after_questions (category_id, sentence_text, audio_file, audio_file_name, audio_file_size, phonetic_text, tips, points, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssissii", 
                $row['category_id'], 
                $new_sentence_text, 
                $new_audio_file, 
                $new_audio_name, 
                $new_audio_size, 
                $row['phonetic_text'], 
                $row['tips'], 
                $row['points'], 
                $row['is_active']
            );
        } else {
            // No audio - insert with empty audio fields
            $sql = "INSERT INTO speaking_repeat_after_questions (category_id, sentence_text, audio_file, audio_file_name, audio_file_size, phonetic_text, tips, points, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $emptyPath = '';
            $emptyName = '';
            $emptySize = 0;
            $stmt->bind_param("isssissii", 
                $row['category_id'], 
                $new_sentence_text, 
                $emptyPath,
                $emptyName,
                $emptySize,
                $row['phonetic_text'], 
                $row['tips'], 
                $row['points'], 
                $row['is_active']
            );
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Sentence duplicated successfully', 'new_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error duplicating sentence: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Original sentence not found']);
    }
}

function bulkImport($conn) {
    if (!isset($_FILES['bulk_file']) || $_FILES['bulk_file']['error'] != 0) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        return;
    }
    
    $file_content = file_get_contents($_FILES['bulk_file']['tmp_name']);
    $data = json_decode($file_content, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
        return;
    }
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($data as $index => $item) {
        try {
            $sql = "INSERT INTO speaking_repeat_after_questions (category_id, sentence_text, phonetic_text, tips, points, is_active) VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issiii", 
                $item['category_id'] ?? 1,
                $item['sentence_text'] ?? '',
                $item['phonetic_text'] ?? '',
                $item['tips'] ?? '',
                $item['points'] ?? 10,
                $item['is_active'] ?? 1
            );
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Row " . ($index + 1) . ": " . $conn->error;
            }
        } catch (Exception $e) {
            $error_count++;
            $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Import completed: $success_count successful, $error_count failed",
        'details' => [
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors
        ]
    ]);
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
    
    // Validate file upload using centralized validation
    $validation = validateFileUpload($file, 'audio');
    if (!$validation['success']) {
        throw new Exception($validation['message']);
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
    
    // Create secure upload directory structure
    $yearMonth = date('Y/m');
    $fullUploadDir = getUploadDir('audio') . $yearMonth . '/';
    
    if (!createSecureUploadDir($fullUploadDir, 'audio')) {
        throw new Exception('Cannot create upload directory. Check server permissions.');
    }
    
    // Generate cryptographically secure filename
    $secureFilename = generateSecureFilename($originalName, 'audio');
    
    $uploadPath = $fullUploadDir . $secureFilename;
    $relativePath = getRelativePath('audio', $yearMonth . '/' . $secureFilename);
    
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
    error_log("Audio file uploaded successfully: {$relativePath} | Size: " . formatBytes($file['size']) . " | Type: {$detectedMimeType}");
    
    return [
        'file_path' => $relativePath,
        'original_name' => $originalName,
        'mime_type' => $detectedMimeType,
        'file_size' => $file['size']
    ];
}



?>

<?php include '../layout/Header.php'; ?>
 
<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">Repeat After Me - Speaking Practice</h5>
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

<!-- Sentences Action Buttons (Initially Hidden) -->
<div class="row mb-3" id="sentencesActionButtons" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <button class="btn btn-primary me-2" onclick="showQuickAddPanelForCategory()">
                            <i class="ri-add-line"></i> Add Sentence
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
                        <label class="form-label">Sentence Text *</label>
                        <textarea class="form-control" id="quick_sentence_text" name="sentence_text" rows="2" required placeholder="Enter the sentence to repeat..."></textarea>
                    </div>
                    
                    <div class="row">
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
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phonetic Text (Optional)</label>
                                <input type="text" class="form-control" id="quick_phonetic_text" name="phonetic_text" placeholder="/fəˈnetɪk/">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Hints/Tips (Optional)</label>
                        <textarea class="form-control" id="quick_tips" name="tips" rows="2" placeholder="Add helpful hints or pronunciation tips..."></textarea>
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

<!-- Sentences Section (Initially Hidden) -->
<div class="row mb-3" id="sentencesSection" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="sentencesTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Sentence</th>
                                <th>Audio</th>
                                <th>Phonetic</th>
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

    <!-- Edit Sentence Modal -->
    <div class="modal fade" id="editConversationModal" tabindex="-1" aria-labelledby="editConversationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editConversationModalLabel">Edit Sentence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editConversationForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="edit_question_id" name="question_id">
                        <input type="hidden" name="action" value="update_conversation">
                        
                        <div class="mb-3">
                            <label for="edit_category_id" class="form-label">Category *</label>
                            <select class="form-select" id="edit_category_id" name="category_id" required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_sentence_text" class="form-label">Sentence Text *</label>
                            <textarea class="form-control" id="edit_sentence_text" name="sentence_text" rows="3" required></textarea>
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
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_phonetic_text" class="form-label">Phonetic Text (Optional)</label>
                                    <input type="text" class="form-control" id="edit_phonetic_text" name="phonetic_text" placeholder="/fəˈnetɪk/">
                                </div>
                            </div>
                            <div class="col-md-6">
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
                            <label for="edit_tips" class="form-label">Hints/Tips (Optional)</label>
                            <textarea class="form-control" id="edit_tips" name="tips" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Sentence</button>
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
 
    <script>
        let categoriesTable;
        let sentencesTable;
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
                                <button class="btn btn-sm btn-info me-1" onclick="viewCategorySentences(${row.category_id}, '${row.category_name}')" title="View Sentences">
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

            // Edit conversation form submission
            $('#editConversationForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('editConversationForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#edit_category_id').val()) {
                    showInlineError('edit_category_id', 'Please select a category');
                    hasError = true;
                }
                
                if (!$('#edit_sentence_text').val() || $('#edit_sentence_text').val().length < 5) {
                    showInlineError('edit_sentence_text', 'Sentence text must be at least 5 characters');
                    hasError = true;
                }
                
                if (hasError) {
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
                            $('#editConversationModal').modal('hide');
                            refreshCurrentDataTable();
                            showSuccessToast('Sentence updated successfully!');
                        } else {
                            showErrorToast(result.message);
                            // Try to identify which field has the error
                            if (result.message.includes('category')) {
                                showInlineError('edit_category_id', result.message);
                            } else if (result.message.includes('sentence')) {
                                showInlineError('edit_sentence_text', result.message);
                            }
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while updating the conversation.');
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
                if (deleteItemType === 'conversation') {
                    deleteConversationConfirm(deleteItemId);
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
                
                if (!$('#quick_sentence_text').val() || $('#quick_sentence_text').val().length < 5) {
                    showInlineError('quick_sentence_text', 'Sentence text must be at least 5 characters');
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
                formData.append('action', 'add_conversation');
                
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
                                showSuccessToast('Sentence added successfully!');
                                
                                // Add success animation to form
                                $('#quickAddForm').addClass('form-success');
                                setTimeout(() => {
                                    $('#quickAddForm').removeClass('form-success');
                                }, 600);
                                
                                // Reset form but keep category selected
                                resetQuickFormExceptCategory();
                                
                                // Reload sentences table
                                refreshCurrentDataTable();
                                
                                // Focus on sentence text for next entry
                                setTimeout(() => {
                                    $('#quick_sentence_text').focus();
                                }, 200);
                            } else {
                                showErrorToast(result.message);
                                // Try to identify which field has the error
                                if (result.message.includes('category')) {
                                    showInlineError('quick_category_id', result.message);
                                } else if (result.message.includes('sentence')) {
                                    showInlineError('quick_sentence_text', result.message);
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
            
            // Bulk import form submission
            $('#bulkImportForm').on('submit', function(e) {
                e.preventDefault();
                
                clearInlineErrors('bulkImportForm');
                
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
                            $('#bulkImportModal').modal('hide');
                            refreshDataTable();
                            showSuccessToast(result.message);
                        } else {
                            showErrorToast(result.message);
                            showInlineError('bulk_file', result.message);
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred during bulk import.');
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

        function viewCategorySentences(categoryId, categoryName) {
            console.log('viewCategorySentences called with:', categoryId, categoryName);
            currentCategoryId = categoryId;
            currentCategoryName = categoryName;
            
            // Hide categories view and action buttons
            $('#categoriesView').hide();
            $('#categoriesActionButtons').hide();
            
            // Show sentences section and action buttons
            $('#sentencesSection').show();
            $('#sentencesActionButtons').show();
            
            // Initialize or reload sentences table
            if (sentencesTable) {
                sentencesTable.destroy();
                sentencesTable = null;
            }
            
            // Small delay to ensure DOM is ready
            setTimeout(function() {
                console.log('Initializing sentences DataTable for category:', categoryId);
                sentencesTable = $('#sentencesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_sentences_by_category';
                        d.category_filter = categoryId;
                        return d;
                    },
                    dataSrc: function(json) {
                        console.log('Sentences DataTable received data:', json);
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
                        console.error('Sentences DataTable AJAX error:', error, thrown);
                        console.error('Response text:', xhr.responseText);
                        showErrorToast('Error loading sentences: ' + error);
                    }
                },
                columns: [
                    { data: 'question_id' },
                    { 
                        data: 'sentence_text',
                        render: function(data, type, row) {
                            return data && data.length > 50 ? data.substring(0, 50) + '...' : (data || '');
                        }
                    },
                    { 
                        data: 'audio_file_name',
                        render: function(data, type, row) {
                            if (data) {
                                return `
                                    <div class="audio-player-container">
                                        <button class="btn btn-sm btn-primary play-btn" onclick="toggleSentenceAudio(${row.question_id}, this)" title="Play Audio">
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
                        data: 'phonetic_text',
                        render: function(data, type, row) {
                            return data ? data : '<span class="text-muted">-</span>';
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
                                <button class="btn btn-sm btn-info me-1" onclick="editConversation(${row.question_id})" title="Edit">
                                    <i class="ri-edit-line"></i>
                                </button>
                                <button class="btn btn-sm btn-warning me-1" onclick="duplicateConversation(${row.question_id})" title="Duplicate">
                                    <i class="ri-file-copy-line"></i>
                                </button>
                                <button class="btn btn-sm btn-danger me-1" onclick="deleteConversation(${row.question_id})" title="Delete">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            `;
                        }
                    }
                ],
                drawCallback: function(settings) {
                    console.log('Sentences DataTable draw completed');
                },
                order: [[0, 'desc']],
                pageLength: 10,
                responsive: true,
                language: {
                    emptyTable: "No sentences found for this category",
                    zeroRecords: "No matching sentences found"
                }
                });
                
                console.log('Sentences DataTable initialized successfully');
            }, 100);
        }

        function backToCategoriesView() {
            // Hide sentences section and action buttons
            $('#sentencesSection').hide();
            $('#sentencesActionButtons').hide();
            
            // Show categories view and action buttons
            $('#categoriesView').show();
            $('#categoriesActionButtons').show();
            
            // Clean up sentences table
            if (sentencesTable) {
                sentencesTable.destroy();
                sentencesTable = null;
            }
            
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
                    $('#quick_sentence_text').focus();
                });
                
                // Keep the sentences table visible below the quick add panel
                $('#sentencesSection').show();
            }, 200);
        }

        function refreshCategoriesTable() {
            if (categoriesTable) {
                categoriesTable.ajax.reload();
            }
        }

        function refreshCurrentDataTable() {
            if (sentencesTable) {
                sentencesTable.ajax.reload();
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



        function editConversation(questionId) {
            // Store current quick panel state before loading categories
            const quickCategoryValue = $('#quick_category_id').val();
            const quickCategoryDisabled = $('#quick_category_id').prop('disabled');
            const quickCategoryHasLockClass = $('#quick_category_id').hasClass('bg-light');
            
            // First ensure categories are loaded in edit modal
            loadCategoriesForFilters();
            
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_conversation', question_id: questionId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        // Wait a bit for categories to load, then populate form
                        setTimeout(function() {
                            $('#edit_question_id').val(data.question_id);
                            $('#edit_category_id').val(data.category_id);
                            $('#edit_sentence_text').val(data.sentence_text);
                            $('#edit_phonetic_text').val(data.phonetic_text || '');
                            $('#edit_tips').val(data.tips || '');
                            $('#edit_is_active').val(data.is_active);
                            
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
                            
                            // Show current audio if exists
                            if (data.audio_file_name) {
                                $('#edit_current_filename').text(data.audio_file_name);
                                $('#edit_current_audio').show();
                            } else {
                                $('#edit_current_audio').hide();
                            }
                            
                            // Hide preview and reset
                            $('#edit_audio_preview').hide();
                            $('#edit_audio_file').val('');
                            editCurrentAudio = null;
                            
                            $('#editConversationModal').modal('show');
                        }, 200);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading conversation data');
                }
            });
        }

        function deleteConversation(questionId) {
            deleteItemId = questionId;
            deleteItemType = 'conversation';
            $('#deleteModal').modal('show');
        }

        function deleteConversationConfirm(questionId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_conversation', question_id: questionId },
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
        $('#editConversationModal').on('hidden.bs.modal', function() {
            $('#editConversationForm')[0].reset();
            $('#edit_audio_preview').hide();
            $('#edit_current_audio').hide();
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
            $('#quick_audio_preview').hide();
            $('#quick_sentence_text').focus();
        }

        function resetQuickFormExceptCategory() {
            // Store the selected category and its state
            const selectedCategory = $('#quick_category_id').val();
            const wasDisabled = $('#quick_category_id').prop('disabled');
            const hasLockClass = $('#quick_category_id').hasClass('bg-light');
            
            // Reset all fields
            $('#quick_sentence_text').val('');
            $('#quick_phonetic_text').val('');
            $('#quick_tips').val('');
            $('#quick_audio_file').val('');
            $('#quick_audio_preview').hide();
            
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
            if (sentencesTable) {
                sentencesTable.ajax.reload();
            } else if (categoriesTable) {
                categoriesTable.ajax.reload();
            }
        }

        function duplicateConversation(questionId) {
            if (confirm('Are you sure you want to duplicate this sentence?')) {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: { action: 'duplicate_conversation', question_id: questionId },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            refreshCurrentDataTable();
                            showSuccessToast('Sentence duplicated successfully!');
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

        function toggleSentenceAudio(questionId, button) {
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

    </script>

<?php require("../layout/Footer.php"); ?>
