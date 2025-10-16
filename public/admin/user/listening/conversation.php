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
        case 'bulk_import':
            bulkImport($conn);
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
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $question_id = $_POST['question_id'] ?? $_GET['question_id'] ?? 0;
    
    if (!$question_id) {
        http_response_code(400);
        echo "Question ID is required";
        exit;
    }
    
    $sql = "SELECT audio_file, audio_file_name FROM listening_conversation_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['audio_file'])) {
            // Construct correct path to audio file
            // The audio_file field contains relative path like: uploads/audio/2024/12/audio_xxx.mp3
            $filePath = '../../../' . $row['audio_file'];
            
            // Debug logging
            error_log("Attempting to play audio file: " . $filePath);
            error_log("File exists: " . (file_exists($filePath) ? 'YES' : 'NO'));
            
            // Check if file exists
            if (file_exists($filePath)) {
                // Get mime type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                
                // Set headers for audio streaming
                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . filesize($filePath));
                header('Content-Disposition: inline; filename="' . $row['audio_file_name'] . '"');
                header('Accept-Ranges: bytes');
                header('Cache-Control: public, max-age=3600');
                
                // Output the file
                readfile($filePath);
                exit;
            } else {
                error_log("Audio file not found at path: " . $filePath);
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
            0 => 'lq.question_id',
            1 => 'lq.question_text', 
            2 => 'lc.category_name',
            3 => 'lq.audio_file_name',
            4 => 'lq.option_a',
            5 => 'lq.correct_answer',
            6 => 'lq.is_active',
            7 => 'lq.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'lq.created_at') . ' ' . $orderDir;
        
        // Base filtering
        $where = " WHERE 1=1 ";
        
        // Apply category filter if provided
        $categoryFilter = $params['category_filter'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND lq.category_id = " . intval($categoryFilter) . " ";
        }
        
        // Total records count (with category filter applied)
        $totalSql = "SELECT COUNT(*) as total FROM listening_conversation_questions lq LEFT JOIN listening_conversation lc ON lq.category_id = lc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        // Apply search filter
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (lq.question_text LIKE '%$search%' OR lc.category_name LIKE '%$search%' OR lq.option_a LIKE '%$search%' OR lq.option_b LIKE '%$search%' OR lq.option_c LIKE '%$search%' OR lq.option_d LIKE '%$search%') ";
        }
        
        // Filtered count
        $filteredSql = "SELECT COUNT(*) as total FROM listening_conversation_questions lq LEFT JOIN listening_conversation lc ON lq.category_id = lc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        // Pagination
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        // Main data query
        $sql = "SELECT lq.question_id, lq.question_text, lq.category_id, lq.option_a, lq.option_b, lq.option_c, lq.option_d, 
                       lq.correct_answer, lq.explanation, lq.is_active, lq.created_at, lq.audio_file_name, 
                       lq.audio_file_size, lc.category_name
                FROM listening_conversation_questions lq 
                LEFT JOIN listening_conversation lc ON lq.category_id = lc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'question_id' => $row['question_id'],
                    'question_text' => $row['question_text'],
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'category_id' => $row['category_id'],
                    'option_a' => $row['option_a'],
                    'option_b' => $row['option_b'],
                    'option_c' => $row['option_c'],
                    'option_d' => $row['option_d'],
                    'correct_answer' => $row['correct_answer'],
                    'explanation' => $row['explanation'] ?? '',
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
    
    $sql = "SELECT lq.question_id, lq.category_id, lq.question_text, lq.option_a, lq.option_b, lq.option_c, lq.option_d, 
                   lq.correct_answer, lq.explanation, lq.is_active, lq.audio_file_name, lq.audio_file_size, 
                   lq.created_at, lq.updated_at, lc.category_name 
            FROM listening_conversation_questions lq 
            LEFT JOIN listening_conversation lc ON lq.category_id = lc.category_id 
            WHERE lq.question_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Don't include audio_file binary data in JSON response
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Conversation not found']);
    }
}

function addConversation($conn) {
    try {
        // Debug: Log raw POST data
        error_log("Raw POST data for addConversation: " . json_encode([
            'category_id' => $_POST['category_id'] ?? 'NOT SET',
            'option_a' => $_POST['option_a'] ?? 'NOT SET',
            'option_b' => $_POST['option_b'] ?? 'NOT SET',
            'option_c' => $_POST['option_c'] ?? 'NOT SET',
            'option_d' => $_POST['option_d'] ?? 'NOT SET',
            'correct_answer' => $_POST['correct_answer'] ?? 'NOT SET'
        ]));
        
        // Validate and sanitize input data
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $question_text = trim($_POST['question_text'] ?? '');
        $option_a = trim($_POST['option_a'] ?? '');
        $option_b = trim($_POST['option_b'] ?? '');
        $option_c = trim($_POST['option_c'] ?? '');
        $option_d = trim($_POST['option_d'] ?? '');
        $correct_answer = $_POST['correct_answer'] ?? '';
        $explanation = trim($_POST['explanation'] ?? '');
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        // Input validation
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($question_text) || strlen($question_text) < 10) {
            throw new Exception('Question text must be at least 10 characters long');
        }
        
        if (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
            throw new Exception('All answer options (A, B, C, D) are required');
        }
        
        if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
            throw new Exception('Please select a valid correct answer (A, B, C, or D)');
        }
        
        // Handle audio file upload with industry standards
        $audioData = handleAudioUpload();
        
        // Begin database transaction for data integrity
        $conn->begin_transaction();
        
        try {
            // Check if audio file was actually uploaded
            $hasAudioFile = !empty($audioData['file_path']);
            
            // Always use full schema with audio columns
            $sql = "INSERT INTO listening_conversation_questions (category_id, question_text, audio_file, audio_file_name, audio_file_size, option_a, option_b, option_c, option_d, correct_answer, explanation, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            if ($hasAudioFile) {
                // Debug log the values being inserted
                error_log("Adding conversation with audio - Values: " . json_encode([
                    'category_id' => $category_id,
                    'question_text' => substr($question_text, 0, 50),
                    'option_a' => $option_a,
                    'option_b' => $option_b,
                    'option_c' => $option_c,
                    'option_d' => $option_d,
                    'correct_answer' => $correct_answer,
                    'explanation' => substr($explanation, 0, 50),
                    'is_active' => $is_active
                ]));
                
                // Store only the file path (not binary data)
                // Type string: i=category_id, s=question_text, s=audio_file, s=audio_file_name, i=audio_file_size, s=option_a, s=option_b, s=option_c, s=option_d, s=correct_answer, s=explanation, i=is_active
                $stmt->bind_param("isssissssssi", 
                    $category_id, $question_text, $audioData['file_path'], $audioData['original_name'], 
                    $audioData['file_size'], $option_a, $option_b, 
                    $option_c, $option_d, $correct_answer, $explanation, $is_active
                );
            } else {
                // Debug log the values being inserted
                error_log("Adding conversation without audio - Values: " . json_encode([
                    'category_id' => $category_id,
                    'question_text' => substr($question_text, 0, 50),
                    'option_a' => $option_a,
                    'option_b' => $option_b,
                    'option_c' => $option_c,
                    'option_d' => $option_d,
                    'correct_answer' => $correct_answer,
                    'explanation' => substr($explanation, 0, 50),
                    'is_active' => $is_active
                ]));
                
                // No audio file - insert empty values
                $emptyPath = '';
                $emptyFileName = '';
                $emptySize = 0;
                
                $stmt->bind_param("isssissssssi", 
                    $category_id, $question_text, $emptyPath, $emptyFileName, 
                    $emptySize, $option_a, $option_b, 
                    $option_c, $option_d, $correct_answer, $explanation, $is_active
                );
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $conn->commit();
            
            // Log successful operation
            error_log("Conversation added successfully: ID $insertId, Category: $category_id, Audio: " . ($audioData['file_path'] ? 'Yes' : 'No'));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Conversation added successfully', 
                'id' => $insertId,
                'audio_uploaded' => !empty($audioData['file_path']),
                'file_name' => $audioData['original_name']
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            // Clean up uploaded file if database insert failed
            if (!empty($audioData['file_path']) && file_exists('../../../../' . $audioData['file_path'])) {
                unlink('../../../../' . $audioData['file_path']);
                error_log("Cleaned up uploaded file after database error: " . $audioData['file_path']);
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add Conversation Error: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateConversation($conn) {
    try {
        $question_id = $_POST['question_id'] ?? 0;
        $category_id = $_POST['category_id'] ?? 0;
        $question_text = $_POST['question_text'] ?? '';
        $option_a = $_POST['option_a'] ?? '';
        $option_b = $_POST['option_b'] ?? '';
        $option_c = $_POST['option_c'] ?? '';
        $option_d = $_POST['option_d'] ?? '';
        $correct_answer = $_POST['correct_answer'] ?? '';
        $explanation = $_POST['explanation'] ?? '';
        $is_active = $_POST['is_active'] ?? 1;
        
        // Handle audio file upload only if a file is actually uploaded
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] == 0) {
            try {
                $audioData = handleAudioUpload();
                
                if (!empty($audioData['file_path'])) {
                    // Store only the file path (not binary data)
                    $sql = "UPDATE listening_conversation_questions SET category_id=?, question_text=?, audio_file=?, audio_file_name=?, audio_file_size=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, explanation=?, is_active=?, updated_at=NOW() WHERE question_id=?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare failed: ' . $conn->error);
                    }
                    
                    // Bind parameters with file path
                    $stmt->bind_param("isssissssssiii", $category_id, $question_text, $audioData['file_path'], $audioData['original_name'], $audioData['file_size'], $option_a, $option_b, $option_c, $option_d, $correct_answer, $explanation, $is_active, $question_id);
                } else {
                    // No audio uploaded, update without audio fields
                    $sql = "UPDATE listening_conversation_questions SET category_id=?, question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, explanation=?, is_active=?, updated_at=NOW() WHERE question_id=?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare failed: ' . $conn->error);
                    }
                    $stmt->bind_param("isssssssii", $category_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $explanation, $is_active, $question_id);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error uploading audio: ' . $e->getMessage()]);
                return;
            }
        } else {
            // No audio file uploaded, update without audio fields
            $sql = "UPDATE listening_conversation_questions SET category_id=?, question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, explanation=?, is_active=?, updated_at=NOW() WHERE question_id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("isssssssii", $category_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $explanation, $is_active, $question_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Conversation updated successfully']);
        } else {
            throw new Exception('Error updating conversation: ' . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Update Conversation Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteConversation($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    // Get audio file path before deleting
    $getFileSql = "SELECT audio_file FROM listening_conversation_questions WHERE question_id = ?";
    $getStmt = $conn->prepare($getFileSql);
    $getStmt->bind_param("i", $question_id);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $audioFile = $result->fetch_assoc()['audio_file'] ?? '';
    
    $sql = "DELETE FROM listening_conversation_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    
    if ($stmt->execute()) {
        // Delete audio file if exists
        if (!empty($audioFile) && file_exists('../../../../' . $audioFile)) {
            unlink('../../../../' . $audioFile);
        }
        
        echo json_encode(['success' => true, 'message' => 'Conversation deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting conversation: ' . $conn->error]);
    }
}

function getCategories($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'listening_conversation'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    $sql = "SELECT category_id, category_name, category_description, display_order, points, is_active, created_at FROM listening_conversation WHERE is_active = 1 ORDER BY display_order ASC, category_name ASC";
    $result = $conn->query($sql);
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $categories]);
}

function getCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT category_id, category_name, category_description, 
                   display_order, is_active, created_at, points 
            FROM listening_conversation 
            WHERE category_id = ?";
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

/**
 * Reorder all categories to have sequential display orders starting from 1
 */
function reorderAllCategories($conn) {
    $sql = "SELECT category_id FROM listening_conversation ORDER BY display_order ASC, category_id ASC";
    $result = $conn->query($sql);
    
    $order = 1;
    while ($row = $result->fetch_assoc()) {
        $updateSql = "UPDATE listening_conversation SET display_order = ? WHERE category_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ii", $order, $row['category_id']);
        $updateStmt->execute();
        $updateStmt->close();
        $order++;
    }
}

function addCategory($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'listening_conversation'");
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
    
    if ($points < 1 || $points > 100) {
        echo json_encode(['success' => false, 'message' => 'Points must be between 1 and 100']);
        return;
    }
    
    try {
        $conn->begin_transaction();
        
        // Adjust existing display orders if inserting at specific position
        if ($display_order > 0) {
            // Increment all positions >= target position by 1
            $adjustSql = "UPDATE listening_conversation SET display_order = display_order + 1 WHERE display_order >= ?";
            $adjustStmt = $conn->prepare($adjustSql);
            $adjustStmt->bind_param("i", $display_order);
            $adjustStmt->execute();
            $adjustStmt->close();
        }
        
        // Insert the new category
        $sql = "INSERT INTO listening_conversation (category_name, category_description, display_order, points) VALUES (?, ?, ?, ?)";
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
    
    if ($points < 1 || $points > 100) {
        echo json_encode(['success' => false, 'message' => 'Points must be between 1 and 100']);
        return;
    }
    
    try {
        $conn->begin_transaction();
        
        // Get current display order
        $getCurrentSql = "SELECT display_order FROM listening_conversation WHERE category_id = ?";
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
                $adjustSql = "UPDATE listening_conversation SET display_order = display_order + 1 
                             WHERE display_order >= ? AND display_order < ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $new_display_order, $old_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            } else {
                // Moving DOWN (e.g., from 2 to 5)
                // Decrement positions 3, 4, 5 by 1 (old position + 1 to target position)
                $adjustSql = "UPDATE listening_conversation SET display_order = display_order - 1 
                             WHERE display_order > ? AND display_order <= ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $old_display_order, $new_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            }
        }
        
        // Update the category with new position and points
        $sql = "UPDATE listening_conversation SET category_name=?, category_description=?, display_order=?, points=? WHERE category_id=?";
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
    
    $sql = "DELETE FROM listening_conversation WHERE category_id = ?";
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
    
    $sql = "SELECT lq.*, lc.category_name 
            FROM listening_conversation_questions lq 
            LEFT JOIN listening_conversation lc ON lq.category_id = lc.category_id 
            WHERE lq.category_id = ? 
            ORDER BY lq.created_at DESC";
    
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

function duplicateConversation($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    // Get original conversation
    $sql = "SELECT * FROM listening_conversation_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
     
    if ($row = $result->fetch_assoc()) {
        // Insert duplicate with modified title
        $new_question_text = $row['question_text'] . ' (Copy)';
        
        // Handle audio file duplication
        $new_audio_file = '';
        $new_audio_name = '';
        $hasAudio = false;
        
        if (!empty($row['audio_file']) && file_exists('../../../' . $row['audio_file'])) {
            // Create year/month directory structure
            $yearMonth = date('Y/m');
            $upload_dir = '../../../uploads/audio/' . $yearMonth . '/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $original_path = '../../../' . $row['audio_file'];
            $file_extension = pathinfo($row['audio_file'], PATHINFO_EXTENSION);
            $unique_filename = 'audio_' . time() . '_' . uniqid() . '.' . $file_extension;
            $new_path = $upload_dir . $unique_filename;
            
            if (copy($original_path, $new_path)) {
                $new_audio_file = getRelativePath('audio', $yearMonth . '/' . $unique_filename);
                $new_audio_name = $row['audio_file_name'];
                $hasAudio = true;
            }
        }
        
        // Insert with or without audio based on whether audio exists
        if ($hasAudio) {
            // Store the new file path
            $sql = "INSERT INTO listening_conversation_questions (category_id, question_text, audio_file, audio_file_name, audio_file_size, option_a, option_b, option_c, option_d, correct_answer, explanation, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssissssssi", 
                $row['category_id'], 
                $new_question_text, 
                $new_audio_file, 
                $new_audio_name, 
                $new_audio_size, 
                $row['option_a'], 
                $row['option_b'], 
                $row['option_c'], 
                $row['option_d'], 
                $row['correct_answer'], 
                $row['explanation'], 
                $row['is_active']
            );
            error_log("Inserting duplicate WITH audio: $new_audio_file");
        } else {
            // No audio - insert with empty audio fields
            $emptyPath = '';
            $emptyName = '';
            $emptySize = 0;
            $sql = "INSERT INTO listening_conversation_questions (category_id, question_text, audio_file, audio_file_name, audio_file_size, option_a, option_b, option_c, option_d, correct_answer, explanation, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssissssssi", 
                $row['category_id'], 
                $new_question_text, 
                $emptyPath,
                $emptyName,
                $emptySize,
                $row['option_a'], 
                $row['option_b'], 
                $row['option_c'], 
                $row['option_d'], 
                $row['correct_answer'], 
                $row['explanation'], 
                $row['is_active']
            );
            error_log("Inserting duplicate WITHOUT audio (original had no audio or copy failed)");
        }
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            error_log("Duplicate created successfully with ID: $new_id");
            echo json_encode([
                'success' => true, 
                'message' => 'Conversation duplicated successfully' . ($hasAudio ? ' with audio' : ' (no audio)'), 
                'new_id' => $new_id,
                'has_audio' => $hasAudio
            ]);
        } else {
            error_log("ERROR: Failed to insert duplicate: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Error duplicating conversation: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Original conversation not found']);
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
            $sql = "INSERT INTO listening_conversation_questions (category_id, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssssii", 
                $item['category_id'] ?? 1,
                $item['question_text'] ?? '',
                $item['option_a'] ?? '',
                $item['option_b'] ?? '',
                $item['option_c'] ?? '',
                $item['option_d'] ?? '',
                $item['correct_answer'] ?? 'A',
                $item['explanation'] ?? '', 
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
    
    // Use centralized upload configuration
    require_once('../../../admin/config/upload_config.php');
    
    // Validate file upload using centralized function
    $validation = validateFileUpload($file, 'audio');
    if (!$validation['success']) {
        throw new Exception($validation['message']);
    }
    
    // Get file info after validation
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $originalName = basename($file['name']);
    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // Additional security: Check for executable content in audio files
    $fileContent = file_get_contents($file['tmp_name'], false, null, 0, 1024);
    $suspiciousPatterns = ['<?php', '#!/', '<script', 'eval(', 'exec('];
    foreach ($suspiciousPatterns as $pattern) {
        if (stripos($fileContent, $pattern) !== false) {
            throw new Exception('Security violation: Suspicious content detected in audio file');
        }
    }
    
    $uploadDir = getUploadDir('audio');
    $yearMonth = date('Y/m');
    $fullUploadDir = $uploadDir . $yearMonth . '/';
    
    // Create secure upload directory
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
        <h5 class="h4 text-primary fw-bolder m-0">Conversations - Listening</h5>
    </div>
</div>
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <!-- Category Management Buttons (shown when viewing categories) -->
                        <div id="categoryManagementButtons">
                            <button class="btn btn-primary me-2" onclick="showAddCategoryModal()">
                                <i class="ri-add-line"></i> Add Category
                            </button>
                            
                            <button class="btn btn-outline-success" onclick="refreshCategoriesTable()">
                                <i class="ri-refresh-line"></i> Refresh
                            </button>
                        </div>
                        
                        <!-- Conversation Management Buttons (shown when viewing conversations) -->
                        <div id="conversationManagementButtons" style="display: none;">
                            <button class="btn btn-success me-2" onclick="showQuickAddPanelSmart()">
                                <i class="ri-add-line"></i> Add Conversation
                            </button>
                            
                            <button class="btn btn-outline-primary" onclick="backToCategoriesView()">
                                <i class="ri-arrow-left-line"></i> Back to Categories
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Categories View (Initially Visible) -->
<div class="row mb-3" id="categoriesView">
    <div class="col-12">
        <div class="card border-info">
          
            <div class="card-body">
                <div class="table-responsive">
                    <table id="categoriesDataTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Display Order</th>
                                <th>Points</th>
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

<!-- Category Conversations View (Initially Hidden) -->
<div class="row mb-3" id="categoryConversationsView" style="display: none;">
    <div class="col-12">
        <div class="card border-success">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="ri-chat-3-line"></i> <span id="categoryTitle">Category Conversations</span></h6>
                <div>
                    <button class="btn btn-sm btn-outline-light me-2" onclick="showQuickAddPanelForCategory()">
                        <i class="ri-add-line"></i> Add Conversation
                    </button>
                    <button class="btn btn-sm btn-outline-light" onclick="backToCategoriesView()">
                        <i class="ri-arrow-left-line"></i> Back to Categories
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="categoryConversationsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Question</th>
                                <th>Audio</th>
                                <th>Options</th>
                                <th>Correct Answer</th> 
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" id="quick_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                        </div>
                         
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Question Text *</label>
                        <textarea class="form-control" id="quick_question_text" name="question_text" rows="2" required placeholder="Enter your listening question here..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Option A *</label>
                                <input type="text" class="form-control" id="quick_option_a" name="option_a" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Option B *</label>
                                <input type="text" class="form-control" id="quick_option_b" name="option_b" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Option C *</label>
                                <input type="text" class="form-control" id="quick_option_c" name="option_c" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Option D *</label>
                                <input type="text" class="form-control" id="quick_option_d" name="option_d" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Correct Answer *</label>
                                <select class="form-select" id="quick_correct_answer" name="correct_answer" required>
                                    <option value="">Select Answer</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="quick_is_active" name="is_active">
                                    <option value="1" selected>Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Explanation (Optional)</label>
                        <textarea class="form-control" id="quick_explanation" name="explanation" rows="2" placeholder="Explain why this is the correct answer..."></textarea>
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

<!-- Conversations Table (Initially Hidden) -->
<div class="row" id="conversationsTableView" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="conversationsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Question</th>
                                <th>Category</th>
                                <th>Audio</th>
                                <th>Options</th>
                                <th>Correct Answer</th> 
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

    <!-- Edit Conversation Modal -->
    <div class="modal fade" id="editConversationModal" tabindex="-1" aria-labelledby="editConversationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editConversationModalLabel">Edit Conversation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editConversationForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="edit_question_id" name="question_id">
                        <input type="hidden" name="action" value="update_conversation">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_category_id" class="form-label">Category *</label>
                                    <select class="form-select" id="edit_category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                    </select>
                                </div>
                            </div>
                          
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_question_text" class="form-label">Question Text *</label>
                            <textarea class="form-control" id="edit_question_text" name="question_text" rows="3" required></textarea>
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
                                    <label for="edit_option_a" class="form-label">Option A *</label>
                                    <input type="text" class="form-control" id="edit_option_a" name="option_a" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_option_b" class="form-label">Option B *</label>
                                    <input type="text" class="form-control" id="edit_option_b" name="option_b" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_option_c" class="form-label">Option C *</label>
                                    <input type="text" class="form-control" id="edit_option_c" name="option_c" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_option_d" class="form-label">Option D *</label>
                                    <input type="text" class="form-control" id="edit_option_d" name="option_d" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_correct_answer" class="form-label">Correct Answer *</label>
                                    <select class="form-select" id="edit_correct_answer" name="correct_answer" required>
                                        <option value="">Select Answer</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
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
                            <label for="edit_explanation" class="form-label">Explanation</label>
                            <textarea class="form-control" id="edit_explanation" name="explanation" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Conversation</button>
                    </div>
                </form>
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
                                    <label for="display_order" class="form-label">Display Order</label>
                                    <input type="number" class="form-control category-input" id="display_order" name="display_order" value="0" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_points" class="form-label">Points *</label>
                                    <input type="number" class="form-control category-input" id="category_points" name="points" value="10" min="1" max="100" required autocomplete="off">
                                    <div class="form-text">Points must be between 1 and 100</div>
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
                    <h5 class="modal-title" id="bulkImportModalLabel">Bulk Import Conversations</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="bulkImportForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_import">
                        
                        <div class="alert alert-info">
                            <h6><i class="ri-information-line"></i> Import Instructions:</h6>
                            <ul class="mb-0">
                                <li>Upload a JSON file with conversation data</li>
                                <li>Each conversation should have: question_text, option_a, option_b, option_c, option_d, correct_answer</li>
                                <li>Optional fields: category_id, explanation, points, is_active</li>
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
    "question_text": "What is the main topic?",
    "option_a": "Option A",
    "option_b": "Option B", 
    "option_c": "Option C",
    "option_d": "Option D",
    "correct_answer": "A",
    "explanation": "Explanation here",
    "points": 10,
    "is_active": 1
  }
]</code></pre>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Import Conversations</button>
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

 
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        let conversationsTable;
        let categoriesDataTable;
        let categoryConversationsTable;
        let deleteItemId = null;
        let deleteItemType = null;
        let currentSelectedCategoryId = null; 
        let currentCategoryFilter = '';

        $(document).ready(function() { 
            initializeCategoriesDataTable();
             
            conversationsTable = $('#conversationsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_conversations';
                        d.category_filter = currentCategoryFilter;
                        return d;
                    },
                    dataSrc: function(json) {
                        console.log('DataTable received data:', json);
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
                        console.error('DataTable AJAX error:', error, thrown);
                        console.error('Response text:', xhr.responseText);
                        
                        // Try to show more specific error information
                        let errorMsg = 'Error loading data. ';
                        if (xhr.responseText) {
                            // Check if response contains PHP errors
                            if (xhr.responseText.includes('Fatal error') || xhr.responseText.includes('Parse error')) {
                                errorMsg += 'PHP error detected. Check server logs.';
                            } else if (xhr.responseText.includes('<!DOCTYPE') || xhr.responseText.includes('<html')) {
                                errorMsg += 'HTML response received instead of JSON. Check if session is valid.';
                            } else {
                                errorMsg += 'Response: ' + xhr.responseText.substring(0, 200);
                            }
                        }
                        showErrorToast(errorMsg);
                    }
                },
                drawCallback: function(settings) {
                    console.log('DataTable draw completed');
                },
                columns: [
                    { data: 'question_id' },
                    { 
                        data: 'question_text',
                        render: function(data, type, row) {
                            return data.length > 50 ? data.substring(0, 50) + '...' : data;
                        }
                    },
                    { data: 'category_name' },
                    { 
                        data: 'audio_file_name',
                        render: function(data, type, row) {
                            if (data) {
                                const playerId = 'audio-player-' + row.question_id;
                                return `
                                    <div class="audio-player-container" id="${playerId}">
                                        <button class="btn btn-sm btn-primary play-btn" onclick="toggleAudio(${row.question_id}, this)" title="Play Audio">
                                            <i class="ri-play-fill"></i>
                                        </button>
                                        <div class="audio-controls">
                                            <div class="audio-filename">${data.length > 25 ? data.substring(0, 25) + '...' : data}</div>
                                            <div class="audio-progress" onclick="seekAudio(event, ${row.question_id})" style="display: none;">
                                                <div class="audio-progress-bar" id="progress-${row.question_id}"></div>
                                            </div>
                                            <div class="audio-time" style="display: none;">
                                                <span id="current-time-${row.question_id}">0:00</span>
                                                <span id="duration-${row.question_id}">0:00</span>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            }
                            return '<span class="text-muted">No Audio</span>';
                        }
                    },
                    { 
                        data: null,
                        render: function(data, type, row) {
                            return `A: ${row.option_a}<br>B: ${row.option_b}<br>C: ${row.option_c}<br>D: ${row.option_d}`;
                        }
                    },
                    { 
                        data: 'correct_answer',
                        render: function(data, type, row) {
                            return `<span class="badge bg-success">${data}</span>`;
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
                order: [[0, 'desc']],
                pageLength: 10,
                responsive: true
            });

            // Load categories
            loadCategories();
            
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
                
                if (!$('#edit_question_text').val() || $('#edit_question_text').val().length < 10) {
                    showInlineError('edit_question_text', 'Question text must be at least 10 characters');
                    hasError = true;
                }
                
                if (!$('#edit_option_a').val()) {
                    showInlineError('edit_option_a', 'Option A is required');
                    hasError = true;
                }
                
                if (!$('#edit_option_b').val()) {
                    showInlineError('edit_option_b', 'Option B is required');
                    hasError = true;
                }
                
                if (!$('#edit_option_c').val()) {
                    showInlineError('edit_option_c', 'Option C is required');
                    hasError = true;
                }
                
                if (!$('#edit_option_d').val()) {
                    showInlineError('edit_option_d', 'Option D is required');
                    hasError = true;
                }
                
                if (!$('#edit_correct_answer').val()) {
                    showInlineError('edit_correct_answer', 'Please select the correct answer');
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
                            // Store quick panel state before modal hide
                            const quickCategoryValue = $('#quick_category_id').val();
                            const quickCategoryDisabled = $('#quick_category_id').prop('disabled');
                            const quickCategoryHasLockClass = $('#quick_category_id').hasClass('bg-light');
                            
                            $('#editConversationModal').modal('hide');
                            refreshDataTable();
                            
                            // Restore quick panel state after a short delay
                            setTimeout(function() {
                                if (quickCategoryValue) {
                                    $('#quick_category_id').val(quickCategoryValue);
                                    if (quickCategoryDisabled) {
                                        $('#quick_category_id').prop('disabled', true);
                                    }
                                    if (quickCategoryHasLockClass) {
                                        $('#quick_category_id').addClass('bg-light');
                                    }
                                }
                            }, 100);
                            
                            showSuccessToast('Conversation updated successfully!');
                        } else {
                            showErrorToast(result.message);
                            // Try to identify which field has the error
                            if (result.message.includes('category')) {
                                showInlineError('edit_category_id', result.message);
                            } else if (result.message.includes('question')) {
                                showInlineError('edit_question_text', result.message);
                            } else if (result.message.includes('option')) {
                                showInlineError('edit_option_a', result.message);
                            } else if (result.message.includes('answer')) {
                                showInlineError('edit_correct_answer', result.message);
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
                
                const points = parseInt($('#category_points').val());
                if (!points || points < 1 || points > 100) {
                    showInlineError('category_points', 'Points must be between 1 and 100');
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
                            // Refresh categories datatable if it exists
                            if (categoriesDataTable) {
                                categoriesDataTable.ajax.reload();
                            }
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
                
                if (!$('#quick_question_text').val() || $('#quick_question_text').val().length < 10) {
                    showInlineError('quick_question_text', 'Question text must be at least 10 characters');
                    hasError = true;
                }
                
                if (!$('#quick_option_a').val()) {
                    showInlineError('quick_option_a', 'Option A is required');
                    hasError = true;
                }
                
                if (!$('#quick_option_b').val()) {
                    showInlineError('quick_option_b', 'Option B is required');
                    hasError = true;
                }
                
                if (!$('#quick_option_c').val()) {
                    showInlineError('quick_option_c', 'Option C is required');
                    hasError = true;
                }
                
                if (!$('#quick_option_d').val()) {
                    showInlineError('quick_option_d', 'Option D is required');
                    hasError = true;
                }
                
                if (!$('#quick_correct_answer').val()) {
                    showInlineError('quick_correct_answer', 'Please select the correct answer');
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
                
                // Temporarily enable category field if it's disabled (for locked category scenario)
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
                                showSuccessToast('Conversation added successfully!');
                                
                                // Add success animation to form
                                $('#quickAddForm').addClass('form-success');
                                setTimeout(() => {
                                    $('#quickAddForm').removeClass('form-success');
                                }, 600);
                                
                                // Reset form but keep category selected
                                resetQuickFormExceptCategory();
                                
                                // Reload datatable with proper callback
                                refreshDataTable();
                                
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
                                } else if (result.message.includes('option')) {
                                    showInlineError('quick_option_a', result.message);
                                } else if (result.message.includes('answer')) {
                                    showInlineError('quick_correct_answer', result.message);
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
            
            // Initialize with categories view
            showCategoriesView();
        });

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
            
            // Scroll to the error (check if element exists first)
            if (field.length > 0 && field[0]) {
                field[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                field.focus();
            }
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

        function loadCategories() {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_categories' },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        // Update category dropdown
                        const categorySelect = $('#category_id');
                        categorySelect.empty().append('<option value="">Select Category</option>');
                        
                        result.data.forEach(function(category) {
                            categorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                        });
                        
                        // Update categories list in modal
                        let categoriesHtml = '';
                        result.data.forEach(function(category) {
                            categoriesHtml += `
                                <div class="card mb-2">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">${category.category_name}</h6>
                                                <small class="text-muted">${category.category_description || 'No description'}</small>
                                            </div>
                                            <div>
                                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editCategory(${category.category_id})">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(${category.category_id})">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        $('#categoriesList').html(categoriesHtml || '<p class="text-muted">No categories found. Add one to get started.</p>');
                    }
                }
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
                            $('#edit_question_text').val(data.question_text);
                            $('#edit_option_a').val(data.option_a);
                            $('#edit_option_b').val(data.option_b);
                            $('#edit_option_c').val(data.option_c);
                            $('#edit_option_d').val(data.option_d);
                            $('#edit_correct_answer').val(data.correct_answer);
                            $('#edit_explanation').val(data.explanation || ''); 
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
                        refreshDataTable();
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

        // Function to clean up modal backdrops
        function cleanupModalBackdrop() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            $('body').css('padding-right', '');
        }

        // Reset category form when modal is closed
        $('#categoryModal').on('hidden.bs.modal', function() {
            $('#categoryForm')[0].reset();
            $('#category_points').val('10'); // Reset points to default
            $('#categoryAction').val('add_category');
            $('#categoryModalLabel').text('Add Category');
            
            // Force remove any remaining backdrop
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            $('body').css('padding-right', '');
        });
        
        // Handle category modal cancel button specifically
        $('#categoryModal .btn-secondary').on('click', function() {
            $('#categoryModal').modal('hide');
        });
        
        // Handle category modal close button
        $('#categoryModal .btn-close').on('click', function() {
            $('#categoryModal').modal('hide');
        });
        
        // Global modal cleanup on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.modal').modal('hide');
                setTimeout(cleanupModalBackdrop, 300);
            }
        });
        
        // Ensure all modals clean up properly when hidden
        $('.modal').on('hidden.bs.modal', function() {
            setTimeout(cleanupModalBackdrop, 100);
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

        // New workflow functions
        function showQuickAddPanelSmart() {
            // Check if we're viewing a specific category
            if (currentSelectedCategoryId) {
                showQuickAddPanelForCategory();
            } else {
                showQuickAddPanel();
            }
        }

        function showQuickAddPanel() {
            // Ensure category selection is enabled and unlocked
            $('#quick_category_id').prop('disabled', false);
            $('#quick_category_id').removeClass('bg-light');
            
            // Remove locked indicator if present
            $('.locked-indicator').remove();
            
            $('#quickAddPanel').slideDown(400, function() {
                // Focus on question text after panel is fully shown
                $('#quick_question_text').focus();
                
                // Load categories if not already loaded
                if ($('#quick_category_id option').length <= 1) {
                    loadCategoriesForFilters();
                }
            });
            
            // Keep the conversations table visible below the quick add panel
            $('#conversationsTableView').show();
        }



        function clearQuickForm() {
            $('#quickAddForm')[0].reset(); 
            $('#quick_audio_preview').hide();
            $('#quick_question_text').focus();
        }

        function resetQuickFormExceptCategory() {
            // Store the selected category and its locked state
            const selectedCategory = $('#quick_category_id').val();
            const wasDisabled = $('#quick_category_id').prop('disabled');
            const hasLockedClass = $('#quick_category_id').hasClass('bg-light');
            
            // Reset all fields
            $('#quick_question_text').val('');
            $('#quick_option_a').val('');
            $('#quick_option_b').val('');
            $('#quick_option_c').val('');
            $('#quick_option_d').val('');
            $('#quick_correct_answer').val('');
            $('#quick_explanation').val('');
            $('#quick_audio_file').val('');
            $('#quick_audio_preview').hide(); 
            $('#quick_category_id').val(selectedCategory);
            if (wasDisabled) {
                $('#quick_category_id').prop('disabled', true);
            }
            if (hasLockedClass) {
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
                        // Update category filter dropdown
                        const categoryFilter = $('#categoryFilter');
                        if (categoryFilter.length) {
                            categoryFilter.empty().append('<option value="">All Categories</option>');
                        }
                        
                        // Update quick add category dropdown
                        const quickCategorySelect = $('#quick_category_id');
                        quickCategorySelect.empty().append('<option value="">Select Category</option>');
                        
                        // Update edit category dropdown
                        const editCategorySelect = $('#edit_category_id');
                        editCategorySelect.empty().append('<option value="">Select Category</option>');
                        
                        result.data.forEach(function(category) {
                            if (categoryFilter.length) {
                                categoryFilter.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                            }
                            quickCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                            editCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                        });
                        
                        // Restore quick panel state after loading categories
                        if (quickCategoryValue) {
                            quickCategorySelect.val(quickCategoryValue);
                            if (quickCategoryDisabled) {
                                quickCategorySelect.prop('disabled', true);
                            }
                            if (quickCategoryHasLockClass) {
                                quickCategorySelect.addClass('bg-light');
                            }
                        }
                    }
                }
            });
        }

        function filterByCategory() {
            const categoryId = $('#categoryFilter').val();
            currentCategoryFilter = categoryId;
            
            // Visual feedback for filter
            const filterSelect = $('#categoryFilter');
            if (categoryId && categoryId !== '') {
                filterSelect.addClass('filter-active');
            } else {
                filterSelect.removeClass('filter-active');
            }
            
            // Reload DataTable with new filter
            conversationsTable.ajax.reload(function() {
                console.log('DataTable filtered by category:', categoryId || 'All');
                
                // Show toast notification
                if (categoryId) {
                    const selectedText = $('#categoryFilter option:selected').text();
                    showSuccessToast('Filtered by: ' + selectedText);
                } else {
                    showSuccessToast('Showing all categories');
                }
            }, false);
        }

        // Enhanced refresh function with error handling (like users.php)
        function refreshDataTable() {
            try {
                const table = $('#conversationsTable').DataTable();
                if (table) {
                    table.ajax.reload(function() {
                        console.log('DataTable refreshed successfully');
                    }, false);
                } else {
                    console.error('DataTable not found');
                }
            } catch (error) {
                console.error('Error refreshing DataTable:', error);
                // Fallback: reload the page
                location.reload();
            }
        }

        function duplicateConversation(questionId) {
            if (confirm('Are you sure you want to duplicate this conversation?')) {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: { action: 'duplicate_conversation', question_id: questionId },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            refreshDataTable();
                            showSuccessToast('Conversation duplicated successfully!');
                        } else {
                            showErrorToast(result.message);
                        }
                    }
                });
            }
        }

        function exportConversations() {
            // Create export functionality
            const categoryId = $('#categoryFilter').val();
            let exportData = [];
            
            // Get current table data
            const tableData = conversationsTable.rows().data().toArray();
            
            tableData.forEach(function(row) {
                exportData.push({
                    category_id: row.category_id || 1,
                    question_text: row.question_text,
                    option_a: row.option_a,
                    option_b: row.option_b,
                    option_c: row.option_c,
                    option_d: row.option_d,
                    correct_answer: row.correct_answer,
                    explanation: row.explanation || '', 
                    is_active: row.is_active || 1
                });
            });
            
            // Download as JSON
            const dataStr = JSON.stringify(exportData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'conversations_export.json';
            link.click();
            URL.revokeObjectURL(url);
            
            showSuccessToast('Conversations exported successfully!');
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

        // New Category Management Functions
        function showCategoriesView() {
            $('#conversationsTableView').hide();
            $('#quickAddPanel').hide();
            $('#categoryConversationsView').hide();
            $('#categoriesView').show();
            
            // Show category management buttons, hide conversation management buttons
            $('#categoryManagementButtons').show();
            $('#conversationManagementButtons').hide();
            
            // Initialize categories datatable if not already done
            if (!categoriesDataTable) {
                initializeCategoriesDataTable();
            } else {
                categoriesDataTable.ajax.reload();
            }
        }

        function hideCategoriesView() {
            $('#categoriesView').hide();
            $('#conversationsTableView').show();
            
            // Show conversation management buttons, hide category management buttons
            $('#categoryManagementButtons').hide();
            $('#conversationManagementButtons').show();
        }

        function initializeCategoriesDataTable() {
            categoriesDataTable = $('#categoriesDataTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: { action: 'get_categories' },
                    dataSrc: function(json) {
                        if (json.success) {
                            return json.data;
                        }
                        return [];
                    }
                },
                columns: [
                    { data: 'category_id' },
                    { data: 'category_name' },
                    { data: 'category_description' },
                    { data: 'display_order' },
                    { 
                        data: 'points',
                        render: function(data, type, row) {
                            return `<span class="badge bg-info">${data}</span>`;
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
                        orderable: false,
                        render: function(data, type, row) {
                            return `
                                <button class="btn btn-sm btn-info me-1" onclick="viewCategoryConversations(${row.category_id}, '${row.category_name}')" title="View Conversations">
                                    <i class="ri-eye-line"></i> View
                                </button>
                                <button class="btn btn-sm btn-warning me-1" onclick="editCategoryInline(${row.category_id})" title="Edit">
                                    <i class="ri-edit-line"></i>
                                </button>
                                <button class="btn btn-sm btn-danger"onclick="deleteCategoryInline(${row.category_id})" title="Delete">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            `;
                        }
                    }
                    
                ],
                order: [[3, 'asc']],
                pageLength: 10,
                responsive: true
            });
        }

        function viewCategoryConversations(categoryId, categoryName) {
            currentSelectedCategoryId = categoryId;
            
            // Update page title
            $('h5.h4.text-primary').text(`${categoryName} - Conversations`);
            
            // Switch to conversations view
            $('#categoriesView').hide();
            $('#categoryConversationsView').hide();
            $('#conversationsTableView').show();
            
            // Show conversation management buttons, hide category management buttons
            $('#categoryManagementButtons').hide();
            $('#conversationManagementButtons').show();
            
            // Set the category filter and reload the main conversations table
            currentCategoryFilter = categoryId;
            if (conversationsTable) {
                conversationsTable.ajax.reload();
            }
            
            return; // Skip the old categoryConversationsTable initialization
            
            // Initialize category conversations datatable
            if (categoryConversationsTable) {
                categoryConversationsTable.destroy();
            }
            
            categoryConversationsTable = $('#categoryConversationsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_conversations';
                        d.category_filter = categoryId;
                        return d;
                    },
                    dataSrc: function(json) {
                        if (json.error) {
                            console.error('Server error:', json.error);
                            showErrorToast('Server error: ' + json.error);
                            return [];
                        }
                        if (json.data) {
                            return json.data;
                        }
                        return [];
                    }
                },
                columns: [
                    { data: 'question_id' },
                    { 
                        data: 'question_text',
                        render: function(data, type, row) {
                            return data.length > 50 ? data.substring(0, 50) + '...' : data;
                        }
                    },
                    { 
                        data: 'audio_file_name',
                        render: function(data, type, row) {
                            if (data) {
                                const playerId = 'cat-audio-player-' + row.question_id;
                                return `
                                    <div class="audio-player-container" id="${playerId}">
                                        <button class="btn btn-sm btn-primary play-btn" onclick="toggleAudio(${row.question_id}, this)" title="Play Audio">
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
                        data: null,
                        render: function(data, type, row) {
                            return `A: ${row.option_a}<br>B: ${row.option_b}<br>C: ${row.option_c}<br>D: ${row.option_d}`;
                        }
                    },
                    { 
                        data: 'correct_answer',
                        render: function(data, type, row) {
                            return `<span class="badge bg-success">${data}</span>`;
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
                order: [[0, 'desc']],
                pageLength: 10,
                responsive: true
            });
        }

        function backToCategoriesView() {
            $('#categoryConversationsView').hide();
            $('#conversationsTableView').hide();
            $('#quickAddPanel').hide();
            $('#categoriesView').show();
            currentSelectedCategoryId = null;
            
            // Show category management buttons, hide conversation management buttons
            $('#categoryManagementButtons').show();
            $('#conversationManagementButtons').hide();
            
            // Reset page title
            $('h5.h4.text-primary').text('Conversations - Listening');
        }

        function showQuickAddPanelForCategory() {
            if (!currentSelectedCategoryId) {
                showErrorToast('No category selected');
                return;
            }
            
            // Load categories and pre-select the current category
            loadCategoriesForFilters();
            
            setTimeout(() => {
                $('#quick_category_id').val(currentSelectedCategoryId);
                // Lock the category selection when viewing specific category
                $('#quick_category_id').prop('disabled', true);
                $('#quick_category_id').addClass('bg-light');
                
                // Add a visual indicator that category is locked
                const categoryLabel = $('#quick_category_id').closest('.mb-3').find('.form-label');
                if (!categoryLabel.find('.locked-indicator').length) {
                    categoryLabel.append(' <span class="locked-indicator text-muted"><i class="ri-lock-line"></i> (Locked to current category)</span>');
                }
                
                // Show the quick add panel
                $('#quickAddPanel').slideDown(400, function() {
                    $('#quick_question_text').focus();
                });
                
                // Keep the conversations table visible below the quick add panel
                $('#conversationsTableView').show();
                $('#categoryConversationsView').hide();
            }, 200);
        }

        function showAddCategoryModal() {
            $('#category_id_edit').val('');
            $('#category_name').val('');
            $('#category_description').val('');
            $('#display_order').val('0');
            $('#category_points').val('10');
            $('#categoryAction').val('add_category');
            $('#categoryModalLabel').text('Add Category');
            $('#categoryModal').modal('show');
        }

        function editCategoryInline(categoryId) {
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
                        $('#display_order').val(data.display_order);
                        $('#category_points').val(data.points || 10);
                        $('#categoryAction').val('update_category');
                        $('#categoryModalLabel').text('Edit Category');
                        $('#categoryModal').modal('show');
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        function deleteCategoryInline(categoryId) {
            deleteItemId = categoryId;
            deleteItemType = 'category';
            $('#deleteModal').modal('show');
        }

        // Override the existing delete confirmation to handle categories datatable
        function deleteCategoryConfirm(categoryId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_category', category_id: categoryId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#deleteModal').modal('hide');
                        if (categoriesDataTable) {
                            categoriesDataTable.ajax.reload();
                        }
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        function hideQuickAddPanel() {
            // Reset category field state when hiding panel
            $('#quick_category_id').prop('disabled', false);
            $('#quick_category_id').removeClass('bg-light');
            $('.locked-indicator').remove();
            
            $('#quickAddPanel').slideUp();
            if (currentSelectedCategoryId) {
                $('#categoryConversationsView').show();
            } else {
                $('#conversationsTableView').show();
            }
        }

    </script>

<?php require("../layout/Footer.php"); ?>
