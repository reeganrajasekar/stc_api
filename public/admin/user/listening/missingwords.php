<?php
require("../layout/Session.php");
require("../../config/db.php");
require("../../config/upload_config.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'get_misswords':
            getMissWords($conn);
            break;
        case 'get_missword':
            getMissWord($conn);
            break;
        case 'add_missword':
            addMissWord($conn);
            break;
        case 'update_missword':
            updateMissWord($conn);
            break;
        case 'delete_missword':
            deleteMissWord($conn);
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
        case 'get_misswords_by_category':
            getMissWordsByCategory($conn);
            break;
        case 'duplicate_missword':
            duplicateMissWord($conn);
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
    
    $sql = "SELECT audio_file, audio_file_name FROM listening_misswords_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['audio_file'])) {
            // Construct correct path to audio file
            $filePath = '../../../' . $row['audio_file'];
            
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

function getMissWords($conn) {
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
            0 => 'lm.question_id',
            1 => 'lm.question_text', 
            2 => 'lc.category_name',
            3 => 'lm.audio_file_name',
            4 => 'lm.total_blanks',
            5 => 'lm.is_active',
            6 => 'lm.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'lm.created_at') . ' ' . $orderDir;
        
        // Base filtering
        $where = " WHERE 1=1 ";
        
        // Apply category filter if provided
        $categoryFilter = $params['category_filter'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND lm.category_id = " . intval($categoryFilter) . " ";
        }
        
        // Total records count (with category filter applied)
        $totalSql = "SELECT COUNT(*) as total FROM listening_misswords_questions lm LEFT JOIN listening_misswords lc ON lm.category_id = lc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        // Apply search filter
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (lm.question_text LIKE '%$search%' OR lc.category_name LIKE '%$search%' OR lm.hint_text LIKE '%$search%') ";
        }
        
        // Filtered count
        $filteredSql = "SELECT COUNT(*) as total FROM listening_misswords_questions lm LEFT JOIN listening_misswords lc ON lm.category_id = lc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        // Pagination
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        // Main data query
        $sql = "SELECT lm.question_id, lm.question_text, lm.category_id, lm.correct_answers, lm.total_blanks, 
                       lm.hint_text, lm.is_active, lm.created_at, lm.audio_file_name, 
                       lm.audio_file_size, lc.category_name
                FROM listening_misswords_questions lm 
                LEFT JOIN listening_misswords lc ON lm.category_id = lc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Decode JSON answers
                $correctAnswers = json_decode($row['correct_answers'], true) ?? [];
                
                $data[] = [
                    'question_id' => $row['question_id'],
                    'question_text' => $row['question_text'],
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'category_id' => $row['category_id'],
                    'correct_answers' => $correctAnswers,
                    'total_blanks' => $row['total_blanks'],
                    'hint_text' => $row['hint_text'] ?? '',
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

function getMissWord($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    $sql = "SELECT lm.question_id, lm.category_id, lm.question_text, lm.correct_answers, lm.multiple_options, lm.total_blanks, 
                   lm.hint_text, lm.is_active, lm.audio_file_name, lm.audio_file_size, 
                   lm.created_at, lm.updated_at, lc.category_name 
            FROM listening_misswords_questions lm 
            LEFT JOIN listening_misswords lc ON lm.category_id = lc.category_id 
            WHERE lm.question_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Decode JSON answers and options
        $row['correct_answers'] = json_decode($row['correct_answers'], true) ?? [];
        $row['multiple_options'] = json_decode($row['multiple_options'], true) ?? [];
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing words question not found']);
    }
}

function addMissWord($conn) {
    try {
        // Validate and sanitize input data
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $question_text = trim($_POST['question_text'] ?? '');
        $correct_answers = $_POST['correct_answers'] ?? [];
        $multiple_options = $_POST['multiple_options'] ?? [];
        $hint_text = trim($_POST['hint_text'] ?? '');
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        // Input validation
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($question_text) || strlen($question_text) < 10) {
            throw new Exception('Question text must be at least 10 characters long');
        }
        
        // Count blanks in question text
        $blankCount = substr_count($question_text, '_____');
        if ($blankCount === 0) {
            throw new Exception('Question must contain at least one blank (marked with _____)');
        }
        
        // Validate correct answers
        if (empty($correct_answers) || !is_array($correct_answers)) {
            throw new Exception('Please provide correct answers for all blanks');
        }
        
        // Filter out empty answers and validate count
        $correct_answers = array_filter($correct_answers, function($answer) {
            return !empty(trim($answer));
        });
        
        if (count($correct_answers) !== $blankCount) {
            throw new Exception("Number of answers (" . count($correct_answers) . ") must match number of blanks ($blankCount)");
        }
        
        // Process multiple options (dummy/distractor options)
        $multiple_options = array_filter($multiple_options, function($option) {
            return !empty(trim($option));
        });
        $multipleOptionsJson = json_encode(array_values($multiple_options));
        
        // Handle audio file upload with industry standards
        $audioData = handleAudioUpload();
        
        // Begin database transaction for data integrity
        $conn->begin_transaction();
        
        try {
            // Check if audio file was actually uploaded
            $hasAudioFile = !empty($audioData['file_path']); 
            $correctAnswersJson = json_encode(array_values($correct_answers)); 
            $sql = "INSERT INTO listening_misswords_questions (category_id, question_text, audio_file, audio_file_name, audio_file_size, correct_answers, multiple_options, total_blanks, hint_text, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            if ($hasAudioFile) { 
                $stmt->bind_param("isssissisi", 
                    $category_id, $question_text, $audioData['file_path'], $audioData['original_name'], 
                    $audioData['file_size'], $correctAnswersJson, $multipleOptionsJson, $blankCount, $hint_text, $is_active
                );
            } else {
                // No audio file - insert empty values
                $emptyPath = '';
                $emptyFileName = '';
                $emptySize = 0;
                
                $stmt->bind_param("isssissisi", 
                    $category_id, $question_text, $emptyPath, $emptyFileName, 
                    $emptySize, $correctAnswersJson, $multipleOptionsJson, $blankCount, $hint_text, $is_active
                );
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $conn->commit();
               echo json_encode([
                'success' => true, 
                'message' => 'Missing words question added successfully', 
                'id' => $insertId,
                'audio_uploaded' => !empty($audioData['file_path']),
                'file_name' => $audioData['original_name'],
                'blanks_count' => $blankCount,
                'options_count' => count($multiple_options)
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
             if (!empty($audioData['file_path']) && file_exists('../../../' . $audioData['file_path'])) {
                unlink('../../../' . $audioData['file_path']);
                error_log("Cleaned up uploaded file after database error: " . $audioData['file_path']);
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add Missing Words Error: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateMissWord($conn) {
    try {
        $question_id = $_POST['question_id'] ?? 0;
        $category_id = $_POST['category_id'] ?? 0;
        $question_text = $_POST['question_text'] ?? '';
        $correct_answers = $_POST['correct_answers'] ?? [];
        $multiple_options = $_POST['multiple_options'] ?? [];
        $hint_text = $_POST['hint_text'] ?? '';
        $is_active = $_POST['is_active'] ?? 1;
        
        // Validate inputs
        if (empty($question_text) || strlen($question_text) < 10) {
            throw new Exception('Question text must be at least 10 characters long');
        }
        
        // Count blanks in question text
        $blankCount = substr_count($question_text, '_____');
        if ($blankCount === 0) {
            throw new Exception('Question must contain at least one blank (marked with _____)');
        }
        
        // Validate correct answers
        if (empty($correct_answers) || !is_array($correct_answers)) {
            throw new Exception('Please provide correct answers for all blanks');
        }
        
        // Filter out empty answers and validate count
        $correct_answers = array_filter($correct_answers, function($answer) {
            return !empty(trim($answer));
        });
        
        if (count($correct_answers) !== $blankCount) {
            throw new Exception("Number of answers (" . count($correct_answers) . ") must match number of blanks ($blankCount)");
        }
        
        // Prepare correct answers as JSON
        $correctAnswersJson = json_encode(array_values($correct_answers));
        
        // Process multiple options (dummy/distractor options)
        // Debug: Log what we received
        error_log("Update MissWord - Received multiple_options: " . print_r($multiple_options, true));
        
        if (is_array($multiple_options)) {
            $multiple_options = array_filter($multiple_options, function($option) {
                return !empty(trim($option));
            });
        } else {
            $multiple_options = [];
        }
        $multipleOptionsJson = json_encode(array_values($multiple_options));
        
        error_log("Update MissWord - Filtered multiple_options JSON: " . $multipleOptionsJson);
        
        // Handle audio file upload only if a file is actually uploaded
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] == 0) {
            try {
                $audioData = handleAudioUpload();
                
                if (!empty($audioData['file_path'])) {
                    // Store only the file path (not binary data)
                    $sql = "UPDATE listening_misswords_questions SET category_id=?, question_text=?, audio_file=?, audio_file_name=?, audio_file_size=?, correct_answers=?, multiple_options=?, total_blanks=?, hint_text=?, is_active=?, updated_at=NOW() WHERE question_id=?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare failed: ' . $conn->error);
                    }
                    
                    // Bind parameters with file path (i=int, s=string)
                    // category_id(i), question_text(s), audio_file(s), audio_file_name(s), audio_file_size(i), correct_answers(s), multiple_options(s), total_blanks(i), hint_text(s), is_active(i), question_id(i)
                    $stmt->bind_param("isssississi", $category_id, $question_text, $audioData['file_path'], $audioData['original_name'], $audioData['file_size'], $correctAnswersJson, $multipleOptionsJson, $blankCount, $hint_text, $is_active, $question_id);

                } else {
                    // No audio uploaded, update without audio fields
                    $sql = "UPDATE listening_misswords_questions SET category_id=?, question_text=?, correct_answers=?, multiple_options=?, total_blanks=?, hint_text=?, is_active=?, updated_at=NOW() WHERE question_id=?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare failed: ' . $conn->error);
                    }
                    // category_id(i), question_text(s), correct_answers(s), multiple_options(s), total_blanks(i), hint_text(s), is_active(i), question_id(i)
                    $stmt->bind_param("isssisii", $category_id, $question_text, $correctAnswersJson, $multipleOptionsJson, $blankCount, $hint_text, $is_active, $question_id);

                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error uploading audio: ' . $e->getMessage()]);
                return;
            }
        } else {
            // No audio file uploaded, update without audio fields
            $sql = "UPDATE listening_misswords_questions SET category_id=?, question_text=?, correct_answers=?, multiple_options=?, total_blanks=?, hint_text=?, is_active=?, updated_at=NOW() WHERE question_id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            // category_id(i), question_text(s), correct_answers(s), multiple_options(s), total_blanks(i), hint_text(s), is_active(i), question_id(i)
            $stmt->bind_param("isssisii", $category_id, $question_text, $correctAnswersJson, $multipleOptionsJson, $blankCount, $hint_text, $is_active, $question_id);

        }
        
        if ($stmt->execute()) {
            error_log("Update MissWord - Successfully updated question_id: $question_id with multiple_options: $multipleOptionsJson");
            echo json_encode(['success' => true, 'message' => 'Missing words question updated successfully']);
        } else {
            error_log("Update MissWord - Execute failed: " . $stmt->error);
            throw new Exception('Error updating missing words question: ' . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Update Missing Words Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteMissWord($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    // Get audio file path before deleting
    $getFileSql = "SELECT audio_file FROM listening_misswords_questions WHERE question_id = ?";
    $getStmt = $conn->prepare($getFileSql);
    $getStmt->bind_param("i", $question_id);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $audioFile = $result->fetch_assoc()['audio_file'] ?? '';
    
    $sql = "DELETE FROM listening_misswords_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    
    if ($stmt->execute()) {
        // Delete audio file if exists
        if (!empty($audioFile) && file_exists('../../../' . $audioFile)) {
            unlink('../../../' . $audioFile);
        }
        
        echo json_encode(['success' => true, 'message' => 'Missing words question deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting missing words question: ' . $conn->error]);
    }
}

function getCategories($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'listening_misswords'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    // For admin interface, show all categories (both active and inactive)
    $sql = "SELECT category_id, category_name, category_description, 
                   display_order, is_active, created_at , points
            FROM listening_misswords 
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

/**
 * Reorder all categories to have sequential display orders starting from 1
 */
function reorderAllCategories($conn) {
    $sql = "SELECT category_id FROM listening_misswords ORDER BY display_order ASC, category_id ASC";
    $result = $conn->query($sql);
    
    $order = 1;
    while ($row = $result->fetch_assoc()) {
        $updateSql = "UPDATE listening_misswords SET display_order = ? WHERE category_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ii", $order, $row['category_id']);
        $updateStmt->execute();
        $updateStmt->close();
        $order++;
    }
}

function getCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT category_id, category_name, category_description, 
                   display_order, is_active, created_at, points 
            FROM listening_misswords 
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

function addCategory($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'listening_misswords'");
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
            $adjustSql = "UPDATE listening_misswords SET display_order = display_order + 1 WHERE display_order >= ?";
            $adjustStmt = $conn->prepare($adjustSql);
            $adjustStmt->bind_param("i", $display_order);
            $adjustStmt->execute();
            $adjustStmt->close();
        }
        
        // Insert the new category
        $sql = "INSERT INTO listening_misswords (category_name, category_description, display_order, points) VALUES (?, ?, ?, ?)";
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
        $getCurrentSql = "SELECT display_order FROM listening_misswords WHERE category_id = ?";
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
                $adjustSql = "UPDATE listening_misswords SET display_order = display_order + 1 
                             WHERE display_order >= ? AND display_order < ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $new_display_order, $old_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            } else {
                // Moving DOWN (e.g., from 2 to 5)
                // Decrement positions 3, 4, 5 by 1 (old position + 1 to target position)
                $adjustSql = "UPDATE listening_misswords SET display_order = display_order - 1 
                             WHERE display_order > ? AND display_order <= ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $old_display_order, $new_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            }
        }
        
        // Update the category with new position
        $sql = "UPDATE listening_misswords SET category_name=?, category_description=?, display_order=?, points=? WHERE category_id=?";
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
    
    $sql = "DELETE FROM listening_misswords WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $conn->error]);
    }
}

function getMissWordsByCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT lm.*, lc.category_name 
            FROM listening_misswords_questions lm 
            LEFT JOIN listening_misswords lc ON lm.category_id = lc.category_id 
            WHERE lm.category_id = ? 
            ORDER BY lm.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $misswords = [];
    while ($row = $result->fetch_assoc()) {
        // Decode JSON answers
        $row['correct_answers'] = json_decode($row['correct_answers'], true) ?? [];
        $misswords[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $misswords]);
}

function duplicateMissWord($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    // Get original missing words question
    $sql = "SELECT * FROM listening_misswords_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Insert duplicate with modified title
        $new_question_text = $row['question_text'] . ' (Copy)';
        
        // Handle audio file duplication
        $new_audio_file = null;
        $new_audio_name = null;
        $new_audio_size = null;
        $hasAudio = false;
        
        if (!empty($row['audio_file']) && file_exists('../../../' . $row['audio_file'])) {
            $upload_dir = '../../../uploads/audio/';
            $original_path = '../../../' . $row['audio_file'];
            $file_extension = pathinfo($row['audio_file'], PATHINFO_EXTENSION);
            $unique_filename = 'audio_' . time() . '_' . uniqid() . '.' . $file_extension;
            $new_path = $upload_dir . $unique_filename;
            
            if (copy($original_path, $new_path)) {
                $new_audio_file = getRelativePath('audio', $unique_filename);
                $new_audio_name = $row['audio_file_name'];
                $new_audio_size = $row['audio_file_size'];
                $hasAudio = true;
            }
        }
        
        // Insert with or without audio based on whether audio exists
        if ($hasAudio) {
            // Store the new file path
            $sql = "INSERT INTO listening_misswords_questions (category_id, question_text, audio_file, audio_file_name, audio_file_size, correct_answers, total_blanks, hint_text, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssisisi", 
                $row['category_id'], 
                $new_question_text, 
                $new_audio_file, 
                $new_audio_name, 
                $new_audio_size, 
                $row['correct_answers'], 
                $row['total_blanks'], 
                $row['hint_text'], 
                $row['is_active']
            );
        } else {
            // No audio - insert with empty audio fields
            $sql = "INSERT INTO listening_misswords_questions (category_id, question_text, audio_file, audio_file_name, audio_file_size, correct_answers, total_blanks, hint_text, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $emptyPath = '';
            $emptyName = '';
            $emptySize = 0;
            $stmt->bind_param("isssisisi", 
                $row['category_id'], 
                $new_question_text, 
                $emptyPath,
                $emptyName,
                $emptySize,
                $row['correct_answers'], 
                $row['total_blanks'], 
                $row['hint_text'], 
                $row['is_active']
            );
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Missing words question duplicated successfully', 'new_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error duplicating missing words question: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Original missing words question not found']);
    }
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
    
    // Check for upload errors with detailed messages
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File too large (exceeds server limit of ' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds form limit)',
            UPLOAD_ERR_PARTIAL => 'File upload was interrupted',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: no temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Server error: cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension'
        ];
        throw new Exception($errorMessages[$file['error']] ?? 'Unknown upload error (code: ' . $file['error'] . ')');
    }
    
    // Validate file size (max 50MB for audio files)
    $maxSize = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $maxSize) {
        throw new Exception('Audio file too large. Maximum size allowed is ' . formatBytes($maxSize));
    }
    
    if ($file['size'] < 1024) { // Minimum 1KB
        throw new Exception('Audio file too small. Minimum size is 1KB');
    }
    
    // Validate MIME type using file content inspection (more secure than trusting browser)
    $allowedMimeTypes = [
        'audio/mpeg' => ['mp3'],
        'audio/mp3' => ['mp3'],
        'audio/wav' => ['wav'],
        'audio/x-wav' => ['wav'],
        'audio/wave' => ['wav'],
        'audio/ogg' => ['ogg'],
        'audio/mp4' => ['m4a', 'mp4'],
        'audio/m4a' => ['m4a'],
        'audio/webm' => ['webm'],
        'audio/flac' => ['flac']
    ];
    
    // Use finfo to detect actual MIME type from file content
    if (!function_exists('finfo_open')) {
        throw new Exception('Server error: File type detection not available');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!$detectedMimeType || !array_key_exists($detectedMimeType, $allowedMimeTypes)) {
        throw new Exception('Invalid audio file type. Allowed formats: MP3, WAV, OGG, M4A, WebM, FLAC');
    }
    
    // Validate file extension
    $originalName = basename($file['name']);
    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedMimeTypes[$detectedMimeType])) {
        throw new Exception('File extension does not match content type. Expected: ' . implode(', ', $allowedMimeTypes[$detectedMimeType]));
    }
    
    // Additional security: Check for executable content in audio files
    $fileContent = file_get_contents($file['tmp_name'], false, null, 0, 1024);
    $suspiciousPatterns = ['<?php', '#!/', '<script', 'eval(', 'exec('];
    foreach ($suspiciousPatterns as $pattern) {
        if (stripos($fileContent, $pattern) !== false) {
            throw new Exception('Security violation: Suspicious content detected in audio file');
        }
    }
    
    // Create secure upload directory structure
    $uploadDir = '../../../uploads/audio/';
    $yearMonth = date('Y/m');
    $fullUploadDir = $uploadDir . $yearMonth . '/';
    
    if (!file_exists($fullUploadDir)) {
        if (!mkdir($fullUploadDir, 0755, true)) {
            throw new Exception('Cannot create upload directory. Check server permissions.');
        }
        
        // Create .htaccess for security
        $htaccessContent = "# Prevent direct PHP execution\n";
        $htaccessContent .= "php_flag engine off\n";
        $htaccessContent .= "AddType text/plain .php .php3 .phtml .pht\n";
        $htaccessContent .= "# Allow audio files\n";
        $htaccessContent .= "AddType audio/mpeg .mp3\n";
        $htaccessContent .= "AddType audio/wav .wav\n";
        $htaccessContent .= "AddType audio/ogg .ogg\n";
        file_put_contents($fullUploadDir . '.htaccess', $htaccessContent);
    }
    
    // Generate cryptographically secure filename
    $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $sanitizedName = substr($sanitizedName, 0, 50); // Limit length
    
    // Create unique filename with timestamp and random component
    $timestamp = date('Ymd_His');
    $randomBytes = bin2hex(random_bytes(8));
    $secureFilename = "audio_{$timestamp}_{$randomBytes}_{$sanitizedName}.{$fileExtension}";
    
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

/**
 * Format bytes into human readable format
 */
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

/**
 * Get relative path for uploaded files
 */
if (!function_exists('getRelativePath')) {
    function getRelativePath($type, $filename) {
        return "uploads/{$type}/{$filename}";
    }
}


?>

<?php include '../layout/Header.php'; ?>
 

<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">Missing Words - Listening</h5>
    </div>
</div>
<!-- Top Action Buttons - Categories View -->
<div class="row mb-3" id="categoriesActionButtons">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-12">
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

<!-- Top Action Buttons - Missing Words View -->
<div class="row mb-3" id="misswordsActionButtons" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <button class="btn btn-primary me-2" onclick="showQuickAddPanelForCategory()">
                            <i class="ri-add-line"></i> Add Missing Words
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

<!-- Category Missing Words View (Initially Hidden) -->
<div class="row mb-3" id="categoryMisswordsView" style="display: none;">
    <div class="col-12">
        <div class="card border-success">
            
            <div class="card-body">
                <div class="table-responsive">
                    <table id="categoryMisswordsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Question</th>
                                <th>Audio</th>
                                <th>Blanks</th>
                                <th>Answers</th> 
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
                        <label class="form-label">Question Text with Blanks *</label>
                        <div class="question-builder">
                            <div class="d-flex align-items-center mb-2">
                                <textarea class="form-control" id="quick_question_text" name="question_text" rows="3" required placeholder="Type your question and click 'Add Blank' to insert blanks where students should fill in missing words..."></textarea>
                                <button type="button" class="btn btn-outline-primary ms-2" onclick="addBlankToQuestion('quick_question_text')">
                                    <i class="ri-add-line"></i> Add Blank
                                </button>
                            </div>
                         </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Correct Answers *</label>
                        <div id="quick_answers_container">
                            <div class="alert alert-info">
                                <i class="ri-information-line"></i> Add blanks to your question first, then the answer fields will appear automatically.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dummy Options (Optional) <small class="text-muted">- Distractor options for multiple choice</small></label>
                        <div id="quick_options_container">
                            <div class="option-input-group mb-2">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                                    <button type="button" class="btn btn-success" onclick="addQuickOption()" title="Add more option">
                                        <i class="ri-add-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">Add distractor options that will be shown alongside correct answers in multiple choice format.</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Hint Text (Optional)</label>
                                <textarea class="form-control" id="quick_hint_text" name="hint_text" rows="2" placeholder="Provide a helpful hint for students..."></textarea>
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
                    

                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success" id="addContinueBtn">
                            <span class="btn-text">
                                <i class="ri-add-line"></i> Add Missing Words & Continue
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

<!-- Missing Words Table (Initially Hidden) -->
<div class="row" id="misswordsTableView" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="misswordsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Question</th>
                                <th>Category</th>
                                <th>Audio</th>
                                <th>Blanks</th>
                                <th>Answers</th> 
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

    <!-- Edit Missing Words Modal -->
    <div class="modal fade" id="editMisswordModal" tabindex="-1" aria-labelledby="editMisswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMisswordModalLabel">Edit Missing Words Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editMisswordForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="edit_question_id" name="question_id">
                        <input type="hidden" name="action" value="update_missword">
                        
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
                            <label for="edit_question_text" class="form-label">Question Text with Blanks *</label>
                            <div class="question-builder">
                                <div class="d-flex align-items-center mb-2">
                                    <textarea class="form-control" id="edit_question_text" name="question_text" rows="3" required></textarea>
                                    <button type="button" class="btn btn-outline-primary ms-2" onclick="addBlankToQuestion('edit_question_text')">
                                        <i class="ri-add-line"></i> Add Blank
                                    </button>
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
                            <label class="form-label">Correct Answers *</label>
                            <div id="edit_answers_container">
                                <!-- Answer fields will be generated dynamically -->
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Dummy Options (Optional) <small class="text-muted">- Distractor options for multiple choice</small></label>
                            <div id="edit_options_container">
                                <div class="option-input-group mb-2">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                                        <button type="button" class="btn btn-success" onclick="addEditOption()" title="Add more option">
                                            <i class="ri-add-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted">Add distractor options that will be shown alongside correct answers in multiple choice format.</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_hint_text" class="form-label">Hint Text (Optional)</label>
                                    <textarea class="form-control" id="edit_hint_text" name="hint_text" rows="2"></textarea>
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
                        

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Missing Words Question</button>
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
        .option-input-group {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .option-input-group .input-group {
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 4px;
        }
        
        .option-input-group .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .form-success {
            animation: formSuccess 0.6s ease-out;
        }
        
        @keyframes formSuccess {
            0% { background-color: transparent; }
            50% { background-color: rgba(25, 135, 84, 0.1); }
            100% { background-color: transparent; }
        }
    </style>
 
    <script>
        let misswordsTable;
        let categoriesDataTable;
        let categoryMisswordsTable;
        let deleteItemId = null;
        let deleteItemType = null;
        let currentSelectedCategoryId = null;
        
        // Global variables for filtering
        let currentCategoryFilter = '';

        $(document).ready(function() {
            // Initialize Categories DataTable first (show categories by default)
            initializeCategoriesDataTable();
            
            // Don't initialize misswords table on page load - it will be initialized when viewing a category
            // The table will be created dynamically when user clicks "View" on a category

            // Load categories for filters and quick add
            loadCategoriesForFilters();


            // Edit missing words form submission
            $('#editMisswordForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('editMisswordForm');
                
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
                
                // Validate blanks and answers
                const questionText = $('#edit_question_text').val();
                const blankCount = (questionText.match(/_____/g) || []).length;
                
                if (blankCount === 0) {
                    showInlineError('edit_question_text', 'Question must contain at least one blank (_____)');
                    hasError = true;
                }
                
                // Validate answers
                const answers = [];
                $('#edit_answers_container input[name="correct_answers[]"]').each(function() {
                    const value = $(this).val().trim();
                    if (value) {
                        answers.push(value);
                    }
                });
                
                if (answers.length !== blankCount) {
                    showErrorToast(`Please provide ${blankCount} answers for ${blankCount} blanks`);
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                const formData = new FormData(this);
                
                // Debug: Log multiple_options being sent
                const options = formData.getAll('multiple_options[]');
                console.log('Edit Form - Sending multiple_options:', options);
                
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
                            
                            $('#editMisswordModal').modal('hide');
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
                            
                            showSuccessToast('Missing words question updated successfully!');
                        } else {
                            showErrorToast(result.message);
                            if (result.message.includes('category')) {
                                showInlineError('edit_category_id', result.message);
                            } else if (result.message.includes('question')) {
                                showInlineError('edit_question_text', result.message);
                            }
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while updating the missing words question.');
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
                if (deleteItemType === 'missword') {
                    deleteMisswordConfirm(deleteItemId);
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
                
                // Validate blanks and answers
                const questionText = $('#quick_question_text').val();
                const blankCount = (questionText.match(/_____/g) || []).length;
                
                if (blankCount === 0) {
                    showInlineError('quick_question_text', 'Question must contain at least one blank (_____)');
                    hasError = true;
                }
                
                // Validate answers
                const answers = [];
                $('#quick_answers_container input[name="correct_answers[]"]').each(function() {
                    const value = $(this).val().trim();
                    if (value) {
                        answers.push(value);
                    }
                });
                
                if (answers.length !== blankCount) {
                    showErrorToast(`Please provide ${blankCount} answers for ${blankCount} blanks`);
                    hasError = true;
                }
                
                // Points validation removed - not applicable for missing words form
                
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
                const categoryField = $('#quick_category_id');
                const wasDisabled = categoryField.prop('disabled');
                if (wasDisabled) {
                    categoryField.prop('disabled', false);
                }
                
                const formData = new FormData(this);
                formData.append('action', 'add_missword');
                
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
                                showSuccessToast('Missing words question added successfully!');
                                
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
                        showErrorToast('An error occurred while saving the missing words question.');
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

        // Helper function to show success toast
        function showSuccessToast(message) {
            // Create toast container if it doesn't exist
            if ($('#successToastContainer').length === 0) {
                $('body').append(`
                    <div id="successToastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
                    </div>
                `);
            }
            
            const toastId = 'toast-success-' + Date.now();
            const toast = `
                <div id="${toastId}" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="ri-check-line me-2"></i>${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            $('#successToastContainer').append(toast);
            const toastElement = new bootstrap.Toast(document.getElementById(toastId), { delay: 3000 });
            toastElement.show();
            
            // Remove toast element after it's hidden
            $('#' + toastId).on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }

        // Helper function to show inline error message
        function showInlineError(fieldId, message) {
            const field = $('#' + fieldId);
            
            // Check if field exists
            if (field.length === 0) {
                console.warn('Field not found:', fieldId);
                return;
            }
            
            const formGroup = field.closest('.mb-3');
            
            // Remove any existing error
            formGroup.find('.invalid-feedback').remove();
            field.removeClass('is-invalid');
            
            // Add error styling and message
            field.addClass('is-invalid');
            formGroup.append(`<div class="invalid-feedback d-block">${message}</div>`);
            
            // Scroll to the error (check if element exists first)
            if (field.length > 0 && field[0]) {
                try {
                    field[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    field.focus();
                } catch (e) {
                    console.warn('Error scrolling to field:', fieldId, e);
                    // Fallback: just focus without scrolling
                    field.focus();
                }
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



        // Function to add blank to question text
        function addBlankToQuestion(textareaId) {
            // First, preserve existing answer values
            const existingAnswers = [];
            let containerId;
            if (textareaId === 'quick_question_text') {
                containerId = 'quick_answers_container';
            } else if (textareaId === 'edit_question_text') {
                containerId = 'edit_answers_container';
            }
            
            // Collect existing answer values
            $(`#${containerId} input[name="correct_answers[]"]`).each(function() {
                existingAnswers.push($(this).val());
            });
            
            const textarea = document.getElementById(textareaId);
            const cursorPos = textarea.selectionStart;
            const textBefore = textarea.value.substring(0, cursorPos);
            const textAfter = textarea.value.substring(cursorPos);
            
            textarea.value = textBefore + '_____' + textAfter;
            textarea.focus();
            textarea.setSelectionRange(cursorPos + 5, cursorPos + 5);
            
            // Update answer fields with preserved values
            updateAnswerFields(textareaId, existingAnswers);
        }
        
        // Function to update answer fields based on blanks count
        function updateAnswerFields(textareaId, existingAnswers = []) {
            const questionText = document.getElementById(textareaId).value;
            const blankCount = (questionText.match(/_____/g) || []).length;
            
            console.log('updateAnswerFields called:', textareaId, 'blanks:', blankCount, 'existing:', existingAnswers);
            
            let containerId;
            if (textareaId === 'quick_question_text') {
                containerId = 'quick_answers_container';
            } else if (textareaId === 'edit_question_text') {
                containerId = 'edit_answers_container';
            }
            
            const container = document.getElementById(containerId);
            
            if (blankCount === 0) {
                container.innerHTML = `
                    <div class="alert alert-info">
                        <i class="ri-information-line"></i> Add blanks to your question first, then the answer fields will appear automatically.
                    </div>
                `;
                return;
            }
            
            let html = '';
            for (let i = 0; i < blankCount; i++) {
                // Use existing answer if available, otherwise empty string
                const existingValue = (existingAnswers[i] || '').replace(/"/g, '&quot;'); // Escape quotes for HTML
                html += `
                    <div class="mb-2">
                        <label class="form-label">Answer ${i + 1} *</label>
                        <input type="text" class="form-control" name="correct_answers[]" value="${existingValue}" placeholder="Enter the correct word/phrase for blank ${i + 1}" required>
                    </div>
                `;
            }
            
            container.innerHTML = html;
            console.log('Answer fields updated, preserved', existingAnswers.length, 'existing answers for', blankCount, 'blanks');
        }
        
        // Listen for changes in question text to update answer fields
        $(document).on('input', '#quick_question_text, #edit_question_text', function() {
            // Always preserve existing answers when possible
            const existingAnswers = [];
            let containerId;
            if (this.id === 'quick_question_text') {
                containerId = 'quick_answers_container';
            } else if (this.id === 'edit_question_text') {
                containerId = 'edit_answers_container';
            }
            
            // Collect existing answer values
            $(`#${containerId} input[name="correct_answers[]"]`).each(function() {
                existingAnswers.push($(this).val());
            });
            
            updateAnswerFields(this.id, existingAnswers);
        });

        function editMissword(questionId) {
            // Store current quick panel state before loading categories
            const quickCategoryValue = $('#quick_category_id').val();
            const quickCategoryDisabled = $('#quick_category_id').prop('disabled');
            const quickCategoryHasLockClass = $('#quick_category_id').hasClass('bg-light');
            
            // First ensure categories are loaded in edit modal
            loadCategoriesForFilters();
            
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_missword', question_id: questionId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        // Wait a bit for categories to load, then populate form
                        setTimeout(function() {
                            $('#edit_question_id').val(data.question_id);
                            $('#edit_category_id').val(data.category_id);
                            $('#edit_question_text').val(data.question_text);
                            $('#edit_hint_text').val(data.hint_text || '');
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
                            
                            // Update answer fields and populate them with existing answers
                            console.log('Populating edit form with answers:', data.correct_answers);
                            updateAnswerFields('edit_question_text', data.correct_answers || []);
                            
                            // Populate multiple options
                            console.log('Populating edit form with options:', data.multiple_options);
                            populateEditOptions(data.multiple_options || []);
                            
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
                            
                            $('#editMisswordModal').modal('show');
                        }, 200);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading missing words question data');
                }
            });
        }

        // Function to populate edit options
        function populateEditOptions(options) {
            const container = $('#edit_options_container');
            container.empty();
            
            if (options && options.length > 0) {
                options.forEach(function(option, index) {
                    const optionHtml = `
                        <div class="option-input-group mb-2">
                            <div class="input-group">
                                <input type="text" class="form-control" name="multiple_options[]" value="${option.replace(/"/g, '&quot;')}" placeholder="Enter a dummy option...">
                                <button type="button" class="btn btn-danger" onclick="removeEditOption(this)" title="Remove option">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    container.append(optionHtml);
                });
            }
            
            // Always add one empty option at the end
            const emptyOptionHtml = `
                <div class="option-input-group mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                        <button type="button" class="btn btn-success" onclick="addEditOption()" title="Add more option">
                            <i class="ri-add-line"></i>
                        </button>
                    </div>
                </div>
            `;
            container.append(emptyOptionHtml);
        }

        // Edit option functions are defined later in the file

        // Function to add quick option
        function addQuickOption() {
            const container = $('#quick_options_container');
            
            // Remove all existing + buttons and replace with - buttons
            container.find('.btn-success').each(function() {
                $(this).removeClass('btn-success').addClass('btn-danger')
                    .attr('onclick', 'removeQuickOption(this)')
                    .attr('title', 'Remove option')
                    .html('<i class="ri-close-line"></i>');
            });
            
            // Add new input with + button
            const newOptionHtml = `
                <div class="option-input-group mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                        <button type="button" class="btn btn-success" onclick="addQuickOption()" title="Add more option">
                            <i class="ri-add-line"></i>
                        </button>
                    </div>
                </div>
            `;
            container.append(newOptionHtml);
        }

        // Function to remove quick option
        function removeQuickOption(button) {
            const container = $('#quick_options_container');
            $(button).closest('.option-input-group').remove();
            
            // Ensure at least one option input remains
            if (container.find('.option-input-group').length === 0) {
                const optionHtml = `
                    <div class="option-input-group mb-2">
                        <div class="input-group">
                            <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                            <button type="button" class="btn btn-success" onclick="addQuickOption()" title="Add more option">
                                <i class="ri-add-line"></i>
                            </button>
                        </div>
                    </div>
                `;
                container.append(optionHtml);
            } else {
                // Make sure the last remaining input has the + button
                const lastInput = container.find('.option-input-group:last');
                if (lastInput.find('.btn-success').length === 0) {
                    lastInput.find('.btn-danger').removeClass('btn-danger').addClass('btn-success')
                        .attr('onclick', 'addQuickOption()')
                        .attr('title', 'Add more option')
                        .html('<i class="ri-add-line"></i>');
                }
            }
        }

        function deleteMissword(questionId) {
            deleteItemId = questionId;
            deleteItemType = 'missword';
            $('#deleteModal').modal('show');
        }

        function deleteMisswordConfirm(questionId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_missword', question_id: questionId },
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

        // New Category Management Functions
        function showCategoriesView() {
            $('#misswordsTableView').hide();
            $('#quickAddPanel').hide();
            $('#categoryMisswordsView').hide();
            $('#categoriesView').show();
            
            // Initialize categories datatable if not already done
            if (!categoriesDataTable) {
                initializeCategoriesDataTable();
            } else {
                categoriesDataTable.ajax.reload();
            }
        }

        function hideCategoriesView() {
            $('#categoriesView').hide();
            $('#misswordsTableView').show();
            
            // Initialize main misswords table if not already done
            if (!misswordsTable) {
                initializeMisswordsDataTable();
            } else {
                misswordsTable.ajax.reload();
            }
        }

        function initializeMisswordsDataTable() {
            misswordsTable = $('#misswordsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_misswords';
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
                        data: 'total_blanks',
                        render: function(data, type, row) {
                            return `<span class="badge bg-info">${data} blanks</span>`;
                        }
                    },
                    { 
                        data: 'correct_answers',
                        render: function(data, type, row) {
                            if (data && data.length > 0) {
                                const answers = data.slice(0, 3); // Show first 3 answers
                                const displayText = answers.join(', ');
                                const moreText = data.length > 3 ? ` (+${data.length - 3} more)` : '';
                                return `<small>${displayText}${moreText}</small>`;
                            }
                            return '<span class="text-muted">No answers</span>';
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
                               
                                    <button class="btn btn-sm btn-info me-1" onclick="editMissword(${row.question_id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning me-1" onclick="duplicateMissword(${row.question_id})" title="Duplicate">
                                        <i class="ri-file-copy-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger me-1" onclick="deleteMissword(${row.question_id})" title="Delete">
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

        function initializeCategoriesDataTable() {
            categoriesDataTable = $('#categoriesDataTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: { action: 'get_categories' },
                    dataSrc: function(json) {
                        console.log('Categories response:', json);
                        if (json.success) {
                            return json.data;
                        } else {
                            console.error('Categories error:', json.message);
                            showErrorToast(json.message || 'Error loading categories');
                            return [];
                        }
                    },
                    error: function(xhr, error, thrown) {
                        console.error('Categories AJAX error:', error, thrown);
                        console.error('Response text:', xhr.responseText);
                        showErrorToast('Error loading categories: ' + error);
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
                            if (data) {
                                return new Date(data).toLocaleDateString();
                            }
                            return 'N/A';
                        }
                    },
                    { 
                        data: null,
                        render: function(data, type, row) {
                            return `
                               
                                    <button class="btn btn-sm btn-info me-1" onclick="viewCategoryMisswords(${row.category_id}, '${row.category_name.replace(/'/g, '\\\'')}')" title="View Missing Words">
                                        <i class="ri-eye-line"></i> View
                                    </button>
                                    <button class="btn btn-sm btn-warning me-1" onclick="editCategoryInline(${row.category_id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger me-1" onclick="deleteCategoryInline(${row.category_id})" title="Delete">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                               
                            `;
                        }
                    }
                ],
                order: [[4, 'asc']],
                pageLength: 10,
                responsive: true,
                language: {
                    emptyTable: "No categories found. Click 'Add Category' to create your first category.",
                    zeroRecords: "No categories match your search criteria."
                }
            });
        }

        function viewCategoryMisswords(categoryId, categoryName) {
            currentSelectedCategoryId = categoryId;
            
            // Update page title
            $('h5.h4.text-primary').text(`${categoryName} - Missing Words`);
            
            // Switch to misswords view
            $('#categoriesView').hide();
            $('#categoryMisswordsView').hide();
            $('#misswordsTableView').show();
            
            // Show misswords management buttons, hide category management buttons
            $('#categoriesActionButtons').hide();
            $('#misswordsActionButtons').show();
            
            // Set the category filter and reload the main misswords table
            currentCategoryFilter = categoryId;
            if (misswordsTable) {
                misswordsTable.ajax.reload();
            } else {
                // Initialize the main misswords table if it doesn't exist
                initializeMisswordsDataTable();
            }
            
            return; // Skip the old categoryMisswordsTable initialization
            
            // Initialize category missing words datatable
            if (categoryMisswordsTable) {
                categoryMisswordsTable.destroy();
            }
            
            categoryMisswordsTable = $('#categoryMisswordsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_misswords';
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
                        data: 'total_blanks',
                        render: function(data, type, row) {
                            return `<span class="badge bg-info">${data} blanks</span>`;
                        }
                    },
                    { 
                        data: 'correct_answers',
                        render: function(data, type, row) {
                            if (data && data.length > 0) {
                                const answers = data.slice(0, 3); // Show first 3 answers
                                const displayText = answers.join(', ');
                                const moreText = data.length > 3 ? ` (+${data.length - 3} more)` : '';
                                return `<small>${displayText}${moreText}</small>`;
                            }
                            return '<span class="text-muted">No answers</span>';
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
                                
                                    <button class="btn btn-sm btn-info me-1" onclick="editMissword(${row.question_id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning me-1" onclick="duplicateMissword(${row.question_id})" title="Duplicate">
                                        <i class="ri-file-copy-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger me-1" onclick="deleteMissword(${row.question_id})" title="Delete">
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
            $('#categoryMisswordsView').hide();
            $('#misswordsTableView').hide();
            $('#misswordsActionButtons').hide();
            $('#quickAddPanel').hide();
            $('#categoriesView').show();
            $('#categoriesActionButtons').show();
            currentSelectedCategoryId = null;
            
            // Reset page title
            $('h5.h4.text-primary').text('Missing Words - Listening');
            
            // Reset category filter
            currentCategoryFilter = '';
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
                
                // Keep the misswords table visible below the quick add panel
                $('#misswordsTableView').show();
                $('#categoryMisswordsView').hide();
            }, 200);
        }

        function showAddCategoryModal() {
            $('#category_id_edit').val('');
            $('#category_name').val('');
            $('#category_description').val('');
            $('#display_order').val('0');
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
        $('#editMisswordModal').on('hidden.bs.modal', function() {
            $('#editMisswordForm')[0].reset();
            $('#edit_audio_preview').hide();
            $('#edit_current_audio').hide();
            $('#edit_answers_container').html('');
            
            // Reset multiple options to show only one empty input
            $('#edit_options_container').html(`
                <div class="option-input-group mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                        <button type="button" class="btn btn-success" onclick="addEditOption()" title="Add more option">
                            <i class="ri-add-line"></i>
                        </button>
                    </div>
                </div>
            `);
            
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
            
            // Keep the misswords table visible below the quick add panel
            $('#misswordsTableView').show();
        }

        function hideQuickAddPanel() {
            // Reset category field state when hiding panel
            $('#quick_category_id').prop('disabled', false);
            $('#quick_category_id').removeClass('bg-light');
            $('.locked-indicator').remove();
            
            $('#quickAddPanel').slideUp();
            if (currentSelectedCategoryId) {
                $('#categoryMisswordsView').show();
            } else {
                $('#categoriesView').show();
            }
        }

        function clearQuickForm() {
            $('#quickAddForm')[0].reset(); 
            $('#quick_audio_preview').hide();
            $('#quick_answers_container').html(`
                <div class="alert alert-info">
                    <i class="ri-information-line"></i> Add blanks to your question first, then the answer fields will appear automatically.
                </div>
            `);
            
            // Reset multiple options to show only one empty input
            $('#quick_options_container').html(`
                <div class="option-input-group mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                        <button type="button" class="btn btn-success" onclick="addQuickOption()" title="Add more option">
                            <i class="ri-add-line"></i>
                        </button>
                    </div>
                </div>
            `);
            
            $('#quick_question_text').focus();
        }

        function resetQuickFormExceptCategory() {
            // Store the selected category and its locked state
            const selectedCategory = $('#quick_category_id').val();
            const wasDisabled = $('#quick_category_id').prop('disabled');
            const hasLockedClass = $('#quick_category_id').hasClass('bg-light');
            const hasLockedIndicator = $('.locked-indicator').length > 0;
            
            // Reset all fields except category
            $('#quick_question_text').val('');
            $('#quick_hint_text').val('');
            $('#quick_audio_file').val('');
            $('#quick_audio_preview').hide(); 
            $('#quick_answers_container').html(`
                <div class="alert alert-info">
                    <i class="ri-information-line"></i> Add blanks to your question first, then the answer fields will appear automatically.
                </div>
            `);
            
            // Reset multiple options to show only one empty input
            $('#quick_options_container').html(`
                <div class="option-input-group mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                        <button type="button" class="btn btn-success" onclick="addQuickOption()" title="Add more option">
                            <i class="ri-add-line"></i>
                        </button>
                    </div>
                </div>
            `);
            
            // Clear any validation errors
            clearInlineErrors('quickAddForm');
            
            // Restore the category selection and its locked state
            $('#quick_category_id').val(selectedCategory);
            if (wasDisabled) {
                $('#quick_category_id').prop('disabled', true);
            }
            if (hasLockedClass) {
                $('#quick_category_id').addClass('bg-light');
            }
            
            // Focus on question text for next entry
            $('#quick_question_text').focus();
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
            
            // Only reload if misswords table is initialized and visible
            if (misswordsTable && $('#misswordsTableView').is(':visible')) {
                misswordsTable.ajax.reload(function() {
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
        }

        // Enhanced refresh function with error handling
        function refreshDataTable() {
            try {
                // Check which table is currently active
                if (currentSelectedCategoryId && categoryMisswordsTable) {
                    // Refresh category misswords table
                    categoryMisswordsTable.ajax.reload(function() {
                        console.log('Category MissWords DataTable refreshed successfully');
                    }, false);
                } else if (misswordsTable) {
                    // Refresh main misswords table
                    const table = $('#misswordsTable').DataTable();
                    if (table) {
                        table.ajax.reload(function() {
                            console.log('DataTable refreshed successfully');
                        }, false);
                    } else {
                        console.error('DataTable not found');
                    }
                } else {
                    console.log('No active table to refresh');
                }
            } catch (error) {
                console.error('Error refreshing DataTable:', error);
                // Fallback: reload the page
                location.reload();
            }
        }

        function refreshCategoriesTable() {
            if (categoriesDataTable) {
                categoriesDataTable.ajax.reload();
            }
        }

        // Function to refresh the currently active data table
        function refreshCurrentDataTable() {
            try {
                if (currentSelectedCategoryId && categoryMisswordsTable) {
                    // We're viewing category misswords
                    categoryMisswordsTable.ajax.reload(function() {
                        console.log('Category misswords table refreshed successfully');
                    }, false);
                } else if (categoriesDataTable) {
                    // We're viewing categories
                    categoriesDataTable.ajax.reload(function() {
                        console.log('Categories table refreshed successfully');
                    }, false);
                } else if (misswordsTable) {
                    // Fallback to main misswords table
                    misswordsTable.ajax.reload(function() {
                        console.log('Misswords table refreshed successfully');
                    }, false);
                }
            } catch (error) {
                console.error('Error refreshing current DataTable:', error);
                // Fallback: reload the page
                location.reload();
            }
        }

        function duplicateMissword(questionId) {
            if (confirm('Are you sure you want to duplicate this missing words question?')) {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: { action: 'duplicate_missword', question_id: questionId },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            refreshDataTable();
                            showSuccessToast('Missing words question duplicated successfully!');
                        } else {
                            showErrorToast(result.message);
                        }
                    }
                });
            }
        }

        function exportMisswords() {
            // Create export functionality
            const categoryId = $('#categoryFilter').val();
            let exportData = [];
            
            // Get current table data
            const tableData = misswordsTable.rows().data().toArray();
            
            tableData.forEach(function(row) {
                exportData.push({
                    category_id: row.category_id || 1,
                    question_text: row.question_text,
                    correct_answers: row.correct_answers || [],
                    total_blanks: row.total_blanks || 0,
                    hint_text: row.hint_text || '',
                    is_active: row.is_active || 1
                });
            });
            
            // Download as JSON
            const dataStr = JSON.stringify(exportData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'misswords_export.json';
            link.click();
            URL.revokeObjectURL(url);
            
            showSuccessToast('Missing words questions exported successfully!');
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

        // Multiple Options Management Functions

        function addEditOption() {
            const container = $('#edit_options_container');
            
            // Remove all existing + buttons and replace with - buttons
            container.find('.btn-success').each(function() {
                $(this).removeClass('btn-success').addClass('btn-danger')
                    .attr('onclick', 'removeEditOption(this)')
                    .attr('title', 'Remove option')
                    .html('<i class="ri-close-line"></i>');
            });
            
            // Add new input with + button
            const optionHtml = `
                <div class="option-input-group mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                        <button type="button" class="btn btn-success" onclick="addEditOption()" title="Add more option">
                            <i class="ri-add-line"></i>
                        </button>
                    </div>
                </div>
            `;
            container.append(optionHtml);
        }

        function removeEditOption(button) {
            const container = $('#edit_options_container');
            $(button).closest('.option-input-group').remove();
            
            // Ensure at least one option input remains
            if (container.find('.option-input-group').length === 0) {
                const optionHtml = `
                    <div class="option-input-group mb-2">
                        <div class="input-group">
                            <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                            <button type="button" class="btn btn-success" onclick="addEditOption()" title="Add more option">
                                <i class="ri-add-line"></i>
                            </button>
                        </div>
                    </div>
                `;
                container.append(optionHtml);
            } else {
                // Make sure the last remaining input has the + button
                const lastInput = container.find('.option-input-group:last');
                if (lastInput.find('.btn-success').length === 0) {
                    lastInput.find('.btn-danger').removeClass('btn-danger').addClass('btn-success')
                        .attr('onclick', 'addEditOption()')
                        .attr('title', 'Add more option')
                        .html('<i class="ri-add-line"></i>');
                }
            }
        }

        // Populate edit modal with existing options
        function populateEditOptions(options) {
            const container = $('#edit_options_container');
            container.empty();
            
            if (options && options.length > 0) {
                options.forEach((option, index) => {
                    const isLast = (index === options.length - 1);
                    const buttonClass = isLast ? 'btn-success' : 'btn-danger';
                    const buttonOnclick = isLast ? 'addEditOption()' : 'removeEditOption(this)';
                    const buttonTitle = isLast ? 'Add more option' : 'Remove option';
                    const buttonIcon = isLast ? 'ri-add-line' : 'ri-close-line';
                    
                    const optionHtml = `
                        <div class="option-input-group mb-2">
                            <div class="input-group">
                                <input type="text" class="form-control" name="multiple_options[]" value="${option.replace(/"/g, '&quot;')}" placeholder="Enter a dummy option...">
                                <button type="button" class="btn ${buttonClass}" onclick="${buttonOnclick}" title="${buttonTitle}">
                                    <i class="${buttonIcon}"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    container.append(optionHtml);
                });
            } else {
                // Add one empty option field
                const optionHtml = `
                    <div class="option-input-group mb-2">
                        <div class="input-group">
                            <input type="text" class="form-control" name="multiple_options[]" placeholder="Enter a dummy option...">
                            <button type="button" class="btn btn-success" onclick="addEditOption()" title="Add more option">
                                <i class="ri-add-line"></i>
                            </button>
                        </div>
                    </div>
                `;
                container.append(optionHtml);
            }
        }




    </script>

<?php require("../layout/Footer.php"); ?>
