<?php
require("../layout/Session.php");
require("../../config/db.php");
require("../../config/upload_config.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'get_stories':
            getStories($conn);
            break;
        case 'get_story':
            getStory($conn);
            break;
        case 'add_story':
            addStory($conn);
            break;
        case 'update_story':
            updateStory($conn);
            break;
        case 'delete_story':
            deleteStory($conn);
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
        case 'get_stories_by_category':
            getStoriesByCategory($conn);
            break;
        case 'duplicate_story':
            duplicateStory($conn);
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
    $story_id = $_POST['story_id'] ?? 0;
    
    $sql = "SELECT audio_file, audio_file_name FROM speaking_story20_questions WHERE story_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['audio_file'])) {
            // Construct correct path to audio file
            $filePath = '../../../' . $row['audio_file'];
             
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
            } else {
                error_log("Audio file not found: $filePath");
            }
        } else {
            error_log("No audio file path in database for story ID: $story_id");
        }
    } else {
        error_log("Story not found in database: $story_id");
    }
    
    http_response_code(404);
    echo "Audio file not found";
    exit;
}

function getStories($conn) {
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
            0 => 'ss.story_id',
            1 => 'ss.story_title', 
            2 => 'sc.category_name',
            3 => 'ss.total_sentences',
            4 => 'ss.time_limit',
            5 => 'ss.is_active',
            6 => 'ss.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'ss.created_at') . ' ' . $orderDir;
        
        $where = " WHERE 1=1 ";
        
        $categoryFilter = $params['category_filter'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND ss.category_id = " . intval($categoryFilter) . " ";
        }
        
        $totalSql = "SELECT COUNT(*) as total FROM speaking_story20_questions ss LEFT JOIN speaking_story20 sc ON ss.category_id = sc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (ss.story_title LIKE '%$search%' OR sc.category_name LIKE '%$search%' OR ss.tips LIKE '%$search%') ";
        }
        
        $filteredSql = "SELECT COUNT(*) as total FROM speaking_story20_questions ss LEFT JOIN speaking_story20 sc ON ss.category_id = sc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        $sql = "SELECT ss.story_id, ss.story_title, ss.category_id, ss.total_sentences, ss.time_limit,
                       ss.is_active, ss.created_at, ss.audio_file_name, 
                       ss.audio_file_size, sc.category_name, ss.tips
                FROM speaking_story20_questions ss 
                LEFT JOIN speaking_story20 sc ON ss.category_id = sc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'story_id' => $row['story_id'],
                    'story_title' => $row['story_title'],
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'category_id' => $row['category_id'],
                    'total_sentences' => $row['total_sentences'],
                    'time_limit' => $row['time_limit'],
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

function getStory($conn) {
    $story_id = $_POST['story_id'] ?? 0;
    
    $sql = "SELECT ss.story_id, ss.category_id, ss.story_title, ss.sentences, ss.total_sentences,
                   ss.time_limit, ss.tips, ss.points, ss.is_active, ss.audio_file_name, ss.audio_file_size, 
                   ss.created_at, ss.updated_at, sc.category_name 
            FROM speaking_story20_questions ss 
            LEFT JOIN speaking_story20 sc ON ss.category_id = sc.category_id 
            WHERE ss.story_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Decode sentences JSON
        $row['sentences'] = json_decode($row['sentences'], true) ?? [];
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Story not found']);
    }
}

function addStory($conn) {
    try {
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $story_title = trim($_POST['story_title'] ?? '');
        $sentences_json = $_POST['sentences'] ?? '[]';
        $time_limit = filter_var($_POST['time_limit'] ?? 20, FILTER_VALIDATE_INT);
        $tips = trim($_POST['tips'] ?? '');
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($story_title) || strlen($story_title) < 3) {
            throw new Exception('Story title must be at least 3 characters long');
        }
        
        $sentences = json_decode($sentences_json, true);
        if (!is_array($sentences) || count($sentences) < 1) {
            throw new Exception('Please add at least one sentence to the story');
        }
        
        $total_sentences = count($sentences);
        
        // Validate audio file is provided
        if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception('Audio file is required');
        }
        
        $audioData = handleAudioUpload();
        
        $conn->begin_transaction();
        
        try {
            $hasAudioFile = !empty($audioData['file_path']);
            
            $sql = "INSERT INTO speaking_story20_questions (category_id, story_title, sentences, total_sentences, time_limit, audio_file, audio_file_name, audio_file_size, tips, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            if ($hasAudioFile) {
                $stmt->bind_param("isssissisi", 
                    $category_id, $story_title, $sentences_json, $total_sentences, $time_limit,
                    $audioData['file_path'], $audioData['original_name'], $audioData['file_size'],
                    $tips, $is_active
                );
            } else {
                $emptyPath = '';
                $emptyFileName = '';
                $emptySize = 0;
                
                $stmt->bind_param("isssissisi", 
                    $category_id, $story_title, $sentences_json, $total_sentences, $time_limit,
                    $emptyPath, $emptyFileName, $emptySize,
                    $tips, $is_active
                );
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $conn->commit();
            
            error_log("Story added successfully: ID $insertId, Category: $category_id, Sentences: $total_sentences");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Story added successfully', 
                'id' => $insertId,
                'audio_uploaded' => !empty($audioData['file_path']),
                'file_name' => $audioData['original_name']
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
        error_log("Add Story Error: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateStory($conn) {
    try {
        $story_id = $_POST['story_id'] ?? 0;
        $category_id = $_POST['category_id'] ?? 0;
        $story_title = $_POST['story_title'] ?? '';
        $sentences_json = $_POST['sentences'] ?? '[]';
        $time_limit = $_POST['time_limit'] ?? 20;
        $tips = $_POST['tips'] ?? '';
        $is_active = $_POST['is_active'] ?? 1;
        
        $sentences = json_decode($sentences_json, true);
        $total_sentences = is_array($sentences) ? count($sentences) : 0;
        
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] == 0) {
            try {
                $audioData = handleAudioUpload();
                
                if (!empty($audioData['file_path'])) {
                    $sql = "UPDATE speaking_story20_questions SET category_id=?, story_title=?, sentences=?, total_sentences=?, time_limit=?, audio_file=?, audio_file_name=?, audio_file_size=?, tips=?, is_active=?, updated_at=NOW() WHERE story_id=?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare failed: ' . $conn->error);
                    }
                    
                    $stmt->bind_param("isssissisii", $category_id, $story_title, $sentences_json, $total_sentences, $time_limit, $audioData['file_path'], $audioData['original_name'], $audioData['file_size'], $tips, $is_active, $story_id);
                } else {
                    $sql = "UPDATE speaking_story20_questions SET category_id=?, story_title=?, sentences=?, total_sentences=?, time_limit=?, tips=?, is_active=?, updated_at=NOW() WHERE story_id=?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare failed: ' . $conn->error);
                    }
                    $stmt->bind_param("isssisii", $category_id, $story_title, $sentences_json, $total_sentences, $time_limit, $tips, $is_active, $story_id);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error uploading audio: ' . $e->getMessage()]);
                return;
            }
        } else {
            $sql = "UPDATE speaking_story20_questions SET category_id=?, story_title=?, sentences=?, total_sentences=?, time_limit=?, tips=?, is_active=?, updated_at=NOW() WHERE story_id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("isssisii", $category_id, $story_title, $sentences_json, $total_sentences, $time_limit, $tips, $is_active, $story_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Story updated successfully']);
        } else {
            throw new Exception('Error updating story: ' . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Update Story Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteStory($conn) {
    $story_id = $_POST['story_id'] ?? 0;
    
    $getFileSql = "SELECT audio_file FROM speaking_story20_questions WHERE story_id = ?";
    $getStmt = $conn->prepare($getFileSql);
    $getStmt->bind_param("i", $story_id);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $audioFile = $result->fetch_assoc()['audio_file'] ?? '';
    
    $sql = "DELETE FROM speaking_story20_questions WHERE story_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $story_id);
    
    if ($stmt->execute()) {
        if (!empty($audioFile) && file_exists('../../../' . $audioFile)) {
            unlink('../../../' . $audioFile);
        }
        
        echo json_encode(['success' => true, 'message' => 'Story deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting story: ' . $conn->error]);
    }
}

function getCategories($conn) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'speaking_story20'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    // For admin interface, show all categories (both active and inactive)
    $sql = "SELECT category_id, category_name, category_description, 
                   display_order, is_active, created_at, points
            FROM speaking_story20 
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
    
    $sql = "SELECT category_id, category_name, category_description, 
                   display_order, is_active, created_at, points 
            FROM speaking_story20 
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
    $tableCheck = $conn->query("SHOW TABLES LIKE 'speaking_story20'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    $category_name = $_POST['category_name'] ?? '';
    $category_description = $_POST['category_description'] ?? '';
    $display_order = intval($_POST['display_order'] ?? 0);
    $points = intval($_POST['points'] ?? 20);
    
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
            $adjustSql = "UPDATE speaking_story20 SET display_order = display_order + 1 WHERE display_order >= ?";
            $adjustStmt = $conn->prepare($adjustSql);
            $adjustStmt->bind_param("i", $display_order);
            $adjustStmt->execute();
            $adjustStmt->close();
        }
        
        // Insert the new category
        $sql = "INSERT INTO speaking_story20 (category_name, category_description, display_order, points) VALUES (?, ?, ?, ?)";
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
    $points = intval($_POST['points'] ?? 20);
    
    // Validate points
    if ($points < 1 || $points > 100) {
        echo json_encode(['success' => false, 'message' => 'Points must be between 1 and 100']);
        return;
    }
    
    try {
        $conn->begin_transaction();
        
        // Get current display order
        $getCurrentSql = "SELECT display_order FROM speaking_story20 WHERE category_id = ?";
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
                $adjustSql = "UPDATE speaking_story20 SET display_order = display_order + 1 
                             WHERE display_order >= ? AND display_order < ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $new_display_order, $old_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            } else {
                // Moving DOWN (e.g., from 2 to 5)
                // Decrement positions 3, 4, 5 by 1 (old position + 1 to target position)
                $adjustSql = "UPDATE speaking_story20 SET display_order = display_order - 1 
                             WHERE display_order > ? AND display_order <= ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $old_display_order, $new_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            }
        }
        
        // Update the category with new position
        $sql = "UPDATE speaking_story20 SET category_name=?, category_description=?, display_order=?, points=? WHERE category_id=?";
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
    
    $sql = "DELETE FROM speaking_story20 WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $conn->error]);
    }
}

function getStoriesByCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT ss.*, sc.category_name 
            FROM speaking_story20_questions ss 
            LEFT JOIN speaking_story20 sc ON ss.category_id = sc.category_id 
            WHERE ss.category_id = ? 
            ORDER BY ss.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stories = [];
    while ($row = $result->fetch_assoc()) {
        // Decode sentences JSON
        $row['sentences'] = json_decode($row['sentences'], true) ?? [];
        $stories[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $stories]);
}

function duplicateStory($conn) {
    $story_id = $_POST['story_id'] ?? 0;
    
    $sql = "SELECT * FROM speaking_story20_questions WHERE story_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $new_story_title = $row['story_title'] . ' (Copy)';
        
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
        
        if ($hasAudio) {
            $sql = "INSERT INTO speaking_story20_questions (category_id, story_title, sentences, total_sentences, time_limit, audio_file, audio_file_name, audio_file_size, tips, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssissisi", 
                $row['category_id'], 
                $new_story_title, 
                $row['sentences'],
                $row['total_sentences'],
                $row['time_limit'],
                $new_audio_file, 
                $new_audio_name, 
                $new_audio_size, 
                $row['tips'], 
                $row['is_active']
            );
        } else {
            $sql = "INSERT INTO speaking_story20_questions (category_id, story_title, sentences, total_sentences, time_limit, audio_file, audio_file_name, audio_file_size, tips, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $emptyPath = '';
            $emptyName = '';
            $emptySize = 0;
            $stmt->bind_param("isssissisi", 
                $row['category_id'], 
                $new_story_title, 
                $row['sentences'],
                $row['total_sentences'],
                $row['time_limit'],
                $emptyPath,
                $emptyName,
                $emptySize,
                $row['tips'], 
                $row['is_active']
            );
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Story duplicated successfully', 'new_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error duplicating story: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Original story not found']);
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
        <h5 class="h4 text-primary fw-bolder m-0">Story in 20 Seconds - Speaking Practice</h5>
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

<!-- Stories Action Buttons (Initially Hidden) -->
<div class="row mb-3" id="storiesActionButtons" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <button class="btn btn-primary me-2" onclick="showQuickAddPanelForCategory()">
                            <i class="ri-add-line"></i> Add Story
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

<!-- Quick Add Panel (Initially Hidden) -->
<div class="row mb-3" id="quickAddPanel" style="display: none;">
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="ri-lightning-line"></i> Quick Add Story Panel</h6>
                <button class="btn btn-sm btn-outline-light" onclick="hideQuickAddPanel()">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="card-body">
                <form id="quickAddStoryForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" id="quick_story_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Story Title *</label>
                                <input type="text" class="form-control" id="quick_story_title" name="story_title" required placeholder="Enter story title...">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sentence Builder Section -->
                    <div class="card border-info mb-3">
                        <div class="card-header text-white" style="background: #ccccccff">
                            <h6 class="mb-0">Build Your Story</h6>
                        </div>
                        <div class="card-body">
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="quick_new_sentence_input" placeholder="Type a sentence and click Add..." onkeypress="if(event.key==='Enter'){event.preventDefault();addQuickSentenceToList();}">
                                <button class="btn btn-success" type="button" onclick="addQuickSentenceToList()">
                                    <i class="ri-add-line"></i> Add Sentence
                                </button>
                            </div>
                            
                            <div id="quick_sentences_list" class="border rounded p-3 bg-light" style="min-height: 150px; max-height: 300px; overflow-y: auto;">
                                <p class="text-muted text-center mb-0" id="quick_empty_sentences_msg">No sentences added yet. Start typing above!</p>
                            </div>
                            
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="ri-information-line"></i> Total Sentences: <strong id="quick_sentence_count">0</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Time Limit (seconds)</label>
                                <input type="number" class="form-control" id="quick_time_limit" name="time_limit" value="20" min="10" max="60">
                            </div>
                        </div>
                           <div class="col-md-4">
                            <label class="form-label">Hints/Tips (Optional)</label>
                             <textarea class="form-control" id="quick_story_tips" name="tips" rows="2" placeholder="Add helpful hints or tips for this story..."></textarea>
                         </div>
                    
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="quick_story_is_active" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                  
                            <div class="mb-3">
                                <label class="form-label">Audio File *</label>
                                <input type="file" class="form-control" id="quick_story_audio_file" name="audio_file" accept="audio/*" required onchange="previewQuickStoryAudio(this)">
                                <div id="quick_story_audio_preview" style="display: none; margin-top: 10px;">
                                    <div class="audio-player-container">
                                        <div class="audio-controls">
                                            <div class="audio-filename" id="quick_story_audio_filename"></div>
                                            <audio id="quick_story_preview_audio" controls style="width: 100%; height: 30px;"></audio>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="removeQuickStoryAudio()" title="Remove">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div> 
                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success" id="addStoryAndContinueBtn">
                            <span class="btn-text">
                                <i class="ri-add-line"></i> Add Story & Continue
                            </span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Processing...
                            </span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearQuickStoryForm()">
                            <i class="ri-refresh-line"></i> Clear Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Stories Section (Initially Hidden) -->
<div class="row mb-3" id="storiesSection" style="display: none;">
    <div class="col-12">
        <div class="card">
         
            <div class="card-body">
                <div class="table-responsive">
                    <table id="storiesTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Story Title</th>
                                <th>Audio</th>
                                <th>Sentences</th>
                                <th>Time Limit</th>
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

<!-- Add/Edit Story Modal -->
<div class="modal fade" id="addStoryModal" tabindex="-1" aria-labelledby="addStoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStoryModalLabel">Add New Story</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addStoryForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="story_id" name="story_id">
                    <input type="hidden" id="story_category_id" name="category_id">
                    <input type="hidden" id="sentences_json" name="sentences">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <input type="text" class="form-control" id="story_category_name" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Story Title *</label>
                                <input type="text" class="form-control" id="story_title" name="story_title" required placeholder="Enter story title...">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sentence Builder Section -->
                    <div class="card border-info mb-3">
                        <div class="card-header text-white" style="background: #dbdbd9">
                            <h6 class="mb-0">Build Your Story</h6>
                        </div>
                        <div class="card-body">
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="new_sentence_input" placeholder="Type a sentence and click Add..." onkeypress="if(event.key==='Enter'){event.preventDefault();addSentenceToList();}">
                                <button class="btn btn-success" type="button" onclick="addSentenceToList()">
                                    <i class="ri-add-line"></i> Add Sentence
                                </button>
                            </div>
                            
                            <div id="sentences_list" class="border rounded p-3 bg-light" style="min-height: 150px; max-height: 300px; overflow-y: auto;">
                                <p class="text-muted text-center mb-0" id="empty_sentences_msg">No sentences added yet. Start typing above!</p>
                            </div>
                            
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="ri-information-line"></i> Total Sentences: <strong id="sentence_count">0</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Time Limit (seconds)</label>
                                <input type="number" class="form-control" id="time_limit" name="time_limit" value="20" min="10" max="60">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="story_is_active" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Audio File *</label>
                        <input type="file" class="form-control" id="story_audio_file" name="audio_file" accept="audio/*" onchange="previewStoryAudio(this)">
                        <div class="form-text">Supported formats: MP3, WAV, M4A (Required for new stories)</div>
                        
                        <!-- Current audio player (shown when editing) -->
                        <div id="story_current_audio" style="display: none; margin-top: 10px;">
                            <small class="text-muted">Current Audio:</small>
                            <div class="audio-player-container mt-2">
                                <button type="button" class="btn btn-sm btn-primary play-btn" onclick="toggleStoryCurrentAudio(this)">
                                    <i class="ri-play-fill"></i>
                                </button>
                                <div class="audio-controls">
                                    <div class="audio-filename" id="story_current_filename"></div>
                                    <div class="audio-progress" onclick="seekStoryAudio(event)">
                                        <div class="audio-progress-bar" id="story_current_progress"></div>
                                    </div>
                                    <div class="audio-time">
                                        <span id="story_current_time">0:00</span>
                                        <span id="story_current_duration">0:00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- New audio preview -->
                        <div id="story_audio_preview" style="display: none; margin-top: 10px;">
                            <small class="text-success">New Audio Preview:</small>
                            <div class="audio-player-container mt-2">
                                <div class="audio-controls">
                                    <div class="audio-filename" id="story_audio_filename"></div>
                                    <audio id="story_preview_audio" controls style="width: 100%; height: 30px;"></audio>
                                </div>
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeStoryAudio()" title="Remove">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Hints/Tips (Optional)</label>
                        <textarea class="form-control" id="story_tips" name="tips" rows="2" placeholder="Add helpful hints or tips for this story..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveStoryBtn">
                        <i class="ri-save-line"></i> Save Story
                    </button>
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
                                    <label for="points" class="form-label">Points *</label>
                                    <input type="number" class="form-control category-input" id="points" name="points" value="20" min="1" max="100" required autocomplete="off">
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
        let storiesTable;
        let deleteItemId = null;
        let deleteItemType = null;
        let currentCategoryId = null;
        let currentCategoryName = '';
        let sentencesList = [];
        let quickSentencesList = [];

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
                               
                                    <button class="btn btn-sm btn-info me-1" onclick="viewCategoryStories(${row.category_id}, '${row.category_name}')" title="View Stories">
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

            // Add/Edit story form submission
            $('#addStoryForm').on('submit', function(e) {
                e.preventDefault();
                
                clearInlineErrors('addStoryForm');
                
                let hasError = false;
                
                if (!$('#story_title').val() || $('#story_title').val().length < 3) {
                    showInlineError('story_title', 'Story title must be at least 3 characters');
                    hasError = true;
                }
                
                if (sentencesList.length < 1) {
                    showErrorToast('Please add at least one sentence to the story');
                    hasError = true;
                }
                
                // Check if audio is required for new stories
                const storyId = $('#story_id').val();
                if (!storyId && (!$('#story_audio_file')[0].files || $('#story_audio_file')[0].files.length === 0)) {
                    showInlineError('story_audio_file', 'Audio file is required for new stories');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Update hidden field with sentences JSON
                $('#sentences_json').val(JSON.stringify(sentencesList));
                
                const formData = new FormData(this);
                formData.append('action', storyId ? 'update_story' : 'add_story');
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#addStoryModal').modal('hide');
                            refreshStoriesTable();
                            showSuccessToast(result.message);
                        } else {
                            showErrorToast(result.message);
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while saving the story.');
                    }
                });
            });

            // Delete confirmation
            $('#confirmDelete').on('click', function() {
                if (deleteItemType === 'story') {
                    deleteStoryConfirm(deleteItemId);
                } else if (deleteItemType === 'category') {
                    deleteCategoryConfirm(deleteItemId);
                }
            });

            // Quick add story form submission
            $('#quickAddStoryForm').on('submit', function(e) {
                e.preventDefault();
                
                clearInlineErrors('quickAddStoryForm');
                
                let hasError = false;
                
                if (!$('#quick_story_category_id').val()) {
                    showInlineError('quick_story_category_id', 'Please select a category');
                    hasError = true;
                }
                
                if (!$('#quick_story_title').val() || $('#quick_story_title').val().length < 3) {
                    showInlineError('quick_story_title', 'Story title must be at least 3 characters');
                    hasError = true;
                }
                
                if (quickSentencesList.length < 1) {
                    showErrorToast('Please add at least one sentence to the story');
                    hasError = true;
                }
                
                // Audio file is mandatory
                if (!$('#quick_story_audio_file')[0].files || $('#quick_story_audio_file')[0].files.length === 0) {
                    showInlineError('quick_story_audio_file', 'Audio file is required');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = $('#addStoryAndContinueBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                const categoryField = $('#quick_story_category_id');
                const wasDisabled = categoryField.prop('disabled');
                if (wasDisabled) {
                    categoryField.prop('disabled', false);
                }
                
                const formData = new FormData(this);
                formData.append('action', 'add_story');
                formData.append('sentences', JSON.stringify(quickSentencesList));
                
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
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                showSuccessToast('Story added successfully!');
                                
                                // Add success animation to form
                                $('#quickAddStoryForm').addClass('form-success');
                                setTimeout(() => {
                                    $('#quickAddStoryForm').removeClass('form-success');
                                }, 600);
                                
                                // Reset form but keep category selected
                                resetQuickStoryFormExceptCategory();
                                
                                // Reload stories table
                                refreshStoriesTable();
                                
                                // Focus on story title for next entry
                                setTimeout(() => {
                                    $('#quick_story_title').focus();
                                }, 200);
                            } else {
                                showErrorToast(result.message);
                                if (result.message.includes('category')) {
                                    showInlineError('quick_story_category_id', result.message);
                                } else if (result.message.includes('title')) {
                                    showInlineError('quick_story_title', result.message);
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            showErrorToast('Error processing server response');
                        }
                    },
                    error: function(xhr) {
                        showErrorToast('An error occurred while saving the story.');
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
            $('#points').val('20');
            $('#display_order').val('0');
            $('#categoryAction').val('add_category');
            $('#categoryModalLabel').text('Add Category');
            $('#categoryModal').modal('show');
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
                        $('#categoryModal').modal('show');
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

        function refreshCategoriesTable() {
            if (categoriesTable) {
                categoriesTable.ajax.reload();
            }
        }

        // Stories Management Functions
        function viewCategoryStories(categoryId, categoryName) {
            currentCategoryId = categoryId;
            currentCategoryName = categoryName;
            
            $('#selectedCategoryName').text(categoryName);
            $('#storiesSection').slideDown();
            
            // Initialize or reload stories table
            if (storiesTable) {
                storiesTable.destroy();
            }
            
            storiesTable = $('#storiesTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: { action: 'get_stories_by_category', category_id: categoryId },
                    dataSrc: function(json) {
                        if (json.success && json.data) {
                            return json.data;
                        }
                        return [];
                    }
                },
                columns: [
                    { data: 'story_id' },
                    { 
                        data: 'story_title',
                        render: function(data, type, row) {
                            return data.length > 50 ? data.substring(0, 50) + '...' : data;
                        }
                    },
                    { 
                        data: 'audio_file_name',
                        render: function(data, type, row) {
                            if (data) {
                                return `
                                    <div class="audio-player-container">
                                        <button class="btn btn-sm btn-primary play-btn" onclick="toggleStoryAudio(${row.story_id}, this)" title="Play Audio">
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
                        data: 'total_sentences',
                        render: function(data, type, row) {
                            return `<span class="badge bg-info">${data} sentences</span>`;
                        }
                    },
                    { 
                        data: 'time_limit',
                        render: function(data, type, row) {
                            return data + 's';
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
                              
                                    <button class="btn btn-sm btn-info me-1" onclick="editStory(${row.story_id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning me-1" onclick="duplicateStory(${row.story_id})" title="Duplicate">
                                        <i class="ri-file-copy-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger me-1" onclick="deleteStory(${row.story_id})" title="Delete">
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

        function hideStoriesSection() {
            $('#storiesSection').slideUp();
            currentCategoryId = null;
            currentCategoryName = '';
            if (storiesTable) {
                storiesTable.destroy();
                storiesTable = null;
            }
        }

        function showAddStoryModal() {
            if (!currentCategoryId) {
                showErrorToast('Please select a category first');
                return;
            }
            
            sentencesList = [];
            $('#addStoryForm')[0].reset();
            $('#story_id').val('');
            $('#story_category_id').val(currentCategoryId);
            $('#story_category_name').val(currentCategoryName);
            $('#addStoryModalLabel').text('Add New Story');
            renderSentencesList();
            updateSentenceCount();
            $('#story_current_audio').hide();
            $('#story_audio_preview').hide();
            $('#addStoryModal').modal('show');
        }

        function refreshStoriesTable() {
            if (storiesTable) {
                storiesTable.ajax.reload();
            }
        }

        function editStory(storyId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_story', story_id: storyId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        $('#story_id').val(data.story_id);
                        $('#story_category_id').val(data.category_id);
                        $('#story_category_name').val(data.category_name);
                        $('#story_title').val(data.story_title);
                        $('#time_limit').val(data.time_limit);
                        $('#story_tips').val(data.tips || '');
                        $('#story_is_active').val(data.is_active);
                        
                        sentencesList = data.sentences || [];
                        renderSentencesList();
                        updateSentenceCount();
                        
                        // Show current audio if exists
                        if (data.audio_file_name) {
                            $('#story_current_filename').text(data.audio_file_name);
                            $('#story_current_audio').show();
                            // Set audio source for current audio player
                            const audioUrl = `?action=get_audio&story_id=${data.story_id}`;
                            $('#story_current_audio_player').attr('src', audioUrl);
                        } else {
                            $('#story_current_audio').hide();
                        }
                        
                        // Hide preview and reset
                        $('#story_audio_preview').hide();
                        $('#story_audio_file').val('');
                        
                        $('#addStoryModalLabel').text('Edit Story');
                        $('#addStoryModal').modal('show');
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading story data');
                }
            });
        }

        function deleteStory(storyId) {
            deleteItemId = storyId;
            deleteItemType = 'story';
            $('#deleteModal').modal('show');
        }

        function deleteStoryConfirm(storyId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_story', story_id: storyId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#deleteModal').modal('hide');
                        refreshStoriesTable();
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        function duplicateStory(storyId) {
            if (confirm('Are you sure you want to duplicate this story?')) {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: { action: 'duplicate_story', story_id: storyId },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            refreshStoriesTable();
                            showSuccessToast('Story duplicated successfully!');
                        } else {
                            showErrorToast(result.message);
                        }
                    }
                });
            }
        }

        // Audio Functions
        function toggleStoryAudio(storyId, button) {
            const icon = button.querySelector('i');
            
            // Create form data for POST request
            const formData = new FormData();
            formData.append('action', 'get_audio');
            formData.append('story_id', storyId);
            
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

        function previewStoryAudio(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const audioUrl = URL.createObjectURL(file);
                
                $('#story_audio_filename').text(file.name);
                $('#story_preview_audio').attr('src', audioUrl);
                $('#story_audio_preview').slideDown();
                
                // Hide current audio when new one is selected
                $('#story_current_audio').slideUp();
            }
        }

        function removeStoryAudio() {
            $('#story_audio_file').val('');
            $('#story_preview_audio').attr('src', '');
            $('#story_audio_preview').slideUp();
            
            // Show current audio again if it exists
            if ($('#story_current_filename').text()) {
                $('#story_current_audio').slideDown();
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

        // Sentence Builder Functions
        function addSentenceToList() {
            const sentenceInput = $('#new_sentence_input');
            const sentence = sentenceInput.val().trim();
            
            if (!sentence) {
                showErrorToast('Please enter a sentence');
                return;
            }
            
            if (sentence.length < 3) {
                showErrorToast('Sentence must be at least 3 characters');
                return;
            }
            
            sentencesList.push(sentence);
            sentenceInput.val('');
            renderSentencesList();
            updateSentenceCount();
            sentenceInput.focus();
        }
        
        function renderSentencesList() {
            const container = $('#sentences_list');
            
            if (sentencesList.length === 0) {
                container.html('<p class="text-muted text-center mb-0" id="empty_sentences_msg">No sentences added yet. Start typing above!</p>');
                return;
            }
            
            let html = '<div class="list-group">';
            sentencesList.forEach((sentence, index) => {
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <span class="badge bg-primary me-2">${index + 1}</span>
                            <span>${sentence}</span>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" onclick="moveSentenceUp(${index})" ${index === 0 ? 'disabled' : ''}>
                                <i class="ri-arrow-up-line"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="moveSentenceDown(${index})" ${index === sentencesList.length - 1 ? 'disabled' : ''}>
                                <i class="ri-arrow-down-line"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="removeSentence(${index})">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            container.html(html);
        }
        
        function removeSentence(index) {
            sentencesList.splice(index, 1);
            renderSentencesList();
            updateSentenceCount();
        }
        
        function moveSentenceUp(index) {
            if (index > 0) {
                [sentencesList[index], sentencesList[index - 1]] = [sentencesList[index - 1], sentencesList[index]];
                renderSentencesList();
            }
        }
        
        function moveSentenceDown(index) {
            if (index < sentencesList.length - 1) {
                [sentencesList[index], sentencesList[index + 1]] = [sentencesList[index + 1], sentencesList[index]];
                renderSentencesList();
            }
        }
        
        function updateSentenceCount() {
            $('#sentence_count').text(sentencesList.length);
        }
        // Toast Functions
        function showErrorToast(message) {
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
            setTimeout(() => toast.remove(), 5000);
        }

        function showSuccessToast(message) {
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

        

        // Sentence builder functions
        function addSentenceToList() {
            const sentenceInput = $('#new_sentence_input');
            const sentence = sentenceInput.val().trim();
            
            if (!sentence) {
                showErrorToast('Please enter a sentence');
                return;
            }
            
            if (sentence.length < 3) {
                showErrorToast('Sentence must be at least 3 characters');
                return;
            }
            
            sentencesList.push(sentence);
            sentenceInput.val('');
            renderSentencesList();
            updateSentenceCount();
            sentenceInput.focus();
        }
        
        function renderSentencesList() {
            const container = $('#sentences_list');
            
            if (sentencesList.length === 0) {
                container.html('<p class="text-muted text-center mb-0" id="empty_sentences_msg">No sentences added yet. Start typing above!</p>');
                return;
            }
            
            let html = '<div class="list-group">';
            sentencesList.forEach((sentence, index) => {
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <span class="badge bg-primary me-2">${index + 1}</span>
                            <span>${sentence}</span>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" onclick="moveSentenceUp(${index})" ${index === 0 ? 'disabled' : ''}>
                                <i class="ri-arrow-up-line"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="moveSentenceDown(${index})" ${index === sentencesList.length - 1 ? 'disabled' : ''}>
                                <i class="ri-arrow-down-line"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="removeSentence(${index})">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            container.html(html);
        }
        
        function removeSentence(index) {
            sentencesList.splice(index, 1);
            renderSentencesList();
            updateSentenceCount();
        }
        
        function moveSentenceUp(index) {
            if (index > 0) {
                [sentencesList[index], sentencesList[index - 1]] = [sentencesList[index - 1], sentencesList[index]];
                renderSentencesList();
            }
        }
        
        function moveSentenceDown(index) {
            if (index < sentencesList.length - 1) {
                [sentencesList[index], sentencesList[index + 1]] = [sentencesList[index + 1], sentencesList[index]];
                renderSentencesList();
            }
        }
        
        function updateSentenceCount() {
            $('#sentence_count').text(sentencesList.length);
        }
        
        // Missing functions that are needed
        function loadCategoriesForFilters() {
            // Store current quick panel state
            const quickCategoryValue = $('#quick_story_category_id').val();
            const quickCategoryDisabled = $('#quick_story_category_id').prop('disabled');
            const quickCategoryHasLockClass = $('#quick_story_category_id').hasClass('bg-light');
            
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_categories' },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        // Update category dropdowns in modals
                        const storyCategorySelect = $('#story_category_id');
                        if (storyCategorySelect.length) {
                            storyCategorySelect.empty().append('<option value="">Select Category</option>');
                            result.data.forEach(function(category) {
                                storyCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                            });
                        }
                        
                        // Update quick add category dropdown
                        const quickCategorySelect = $('#quick_story_category_id');
                        quickCategorySelect.empty().append('<option value="">Select Category</option>');
                        
                        result.data.forEach(function(category) {
                            quickCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
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

        // Quick sentence builder functions
        function addQuickSentenceToList() {
            const sentenceInput = $('#quick_new_sentence_input');
            const sentence = sentenceInput.val().trim();
            
            if (!sentence) {
                showErrorToast('Please enter a sentence');
                return;
            }
            
            if (sentence.length < 3) {
                showErrorToast('Sentence must be at least 3 characters');
                return;
            }
            
            quickSentencesList.push(sentence);
            sentenceInput.val('');
            renderQuickSentencesList();
            updateQuickSentenceCount();
            sentenceInput.focus();
        }
        
        function renderQuickSentencesList() {
            const container = $('#quick_sentences_list');
            
            if (quickSentencesList.length === 0) {
                container.html('<p class="text-muted text-center mb-0" id="quick_empty_sentences_msg">No sentences added yet. Start typing above!</p>');
                return;
            }
            
            let html = '<div class="list-group">';
            quickSentencesList.forEach((sentence, index) => {
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <span class="badge bg-primary me-2">${index + 1}</span>
                            <span>${sentence}</span>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" onclick="moveQuickSentenceUp(${index})" ${index === 0 ? 'disabled' : ''}>
                                <i class="ri-arrow-up-line"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="moveQuickSentenceDown(${index})" ${index === quickSentencesList.length - 1 ? 'disabled' : ''}>
                                <i class="ri-arrow-down-line"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="removeQuickSentence(${index})">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            container.html(html);
        }
        
        function removeQuickSentence(index) {
            quickSentencesList.splice(index, 1);
            renderQuickSentencesList();
            updateQuickSentenceCount();
        }
        
        function moveQuickSentenceUp(index) {
            if (index > 0) {
                [quickSentencesList[index], quickSentencesList[index - 1]] = [quickSentencesList[index - 1], quickSentencesList[index]];
                renderQuickSentencesList();
            }
        }
        
        function moveQuickSentenceDown(index) {
            if (index < quickSentencesList.length - 1) {
                [quickSentencesList[index], quickSentencesList[index + 1]] = [quickSentencesList[index + 1], quickSentencesList[index]];
                renderQuickSentencesList();
            }
        }
        
        function updateQuickSentenceCount() {
            $('#quick_sentence_count').text(quickSentencesList.length);
        }

        // Quick audio preview functions
        function previewQuickStoryAudio(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const audioUrl = URL.createObjectURL(file);
                
                $('#quick_story_audio_filename').text(file.name);
                $('#quick_story_preview_audio').attr('src', audioUrl);
                $('#quick_story_audio_preview').slideDown();
            }
        }

        function removeQuickStoryAudio() {
            $('#quick_story_audio_file').val('');
            $('#quick_story_preview_audio').attr('src', '');
            $('#quick_story_audio_preview').slideUp();
        }

        // Story edit modal audio preview functions
        let storyCurrentAudio = null;

        function toggleStoryCurrentAudio(button) {
            const storyId = $('#story_id').val();
            
            if (!storyCurrentAudio) {
                // Create audio player for current audio
                const formData = new FormData();
                formData.append('action', 'get_audio');
                formData.append('story_id', storyId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.blob())
                .then(blob => {
                    const audioUrl = URL.createObjectURL(blob);
                    storyCurrentAudio = new Audio(audioUrl);
                    
                    $(button).html('<i class="ri-pause-fill"></i>');
                    $(button).removeClass('btn-primary').addClass('btn-success');
                    
                    storyCurrentAudio.addEventListener('timeupdate', function() {
                        const progress = (storyCurrentAudio.currentTime / storyCurrentAudio.duration) * 100;
                        $('#story_current_progress').css('width', progress + '%');
                        $('#story_current_time').text(formatTime(storyCurrentAudio.currentTime));
                    });
                    
                    storyCurrentAudio.addEventListener('loadedmetadata', function() {
                        $('#story_current_duration').text(formatTime(storyCurrentAudio.duration));
                    });
                    
                    storyCurrentAudio.addEventListener('ended', function() {
                        $(button).html('<i class="ri-play-fill"></i>');
                        $(button).removeClass('btn-success').addClass('btn-primary');
                        $('#story_current_progress').css('width', '0%');
                    });
                    
                    storyCurrentAudio.play();
                })
                .catch(err => {
                    console.error('Error loading audio:', err);
                    showErrorToast('Error loading audio file');
                });
            } else {
                if (storyCurrentAudio.paused) {
                    storyCurrentAudio.play();
                    $(button).html('<i class="ri-pause-fill"></i>');
                    $(button).removeClass('btn-primary').addClass('btn-success');
                } else {
                    storyCurrentAudio.pause();
                    $(button).html('<i class="ri-play-fill"></i>');
                    $(button).removeClass('btn-success').addClass('btn-primary');
                }
            }
        }

        function seekStoryAudio(event) {
            if (!storyCurrentAudio) return;
            
            const progressBar = $(event.currentTarget);
            const clickX = event.offsetX;
            const width = progressBar.width();
            const percentage = clickX / width;
            
            storyCurrentAudio.currentTime = percentage * storyCurrentAudio.duration;
        }

        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return mins + ':' + (secs < 10 ? '0' : '') + secs;
        }

        function duplicateStory(storyId) {
            if (confirm('Are you sure you want to duplicate this story?')) {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: { action: 'duplicate_story', story_id: storyId },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            refreshStoriesTable();
                            showSuccessToast('Story duplicated successfully!');
                        } else {
                            showErrorToast(result.message);
                        }
                    }
                });
            }
        }

        // Enhanced modal event handlers
        $('#categoryModal').on('shown.bs.modal', function() {
            setTimeout(function() {
                $('#category_name').focus();
            }, 150);
        });

        // Reset category form when modal is closed
        $('#categoryModal').on('hidden.bs.modal', function() {
            $('#categoryForm')[0].reset();
            $('#categoryAction').val('add_category');
            $('#categoryModalLabel').text('Add Category');
        });

        // Reset story form when modal is closed
        $('#addStoryModal').on('hidden.bs.modal', function() {
            $('#addStoryForm')[0].reset();
            $('#story_audio_preview').hide();
            $('#story_current_audio').hide();
            sentencesList = [];
            renderSentencesList();
            updateSentenceCount();
            if (storyCurrentAudio) {
                storyCurrentAudio.pause();
                storyCurrentAudio = null;
            }
        });

        // Functions to match missingwords.php pattern
        function viewCategoryStories(categoryId, categoryName) {
            // Hide categories view and action buttons
            $('#categoriesView').hide();
            $('#categoriesActionButtons').hide();
            
            // Show stories section and action buttons
            $('#storiesSection').show();
            $('#storiesActionButtons').show();
            
            // Update the selected category info
            currentCategoryId = categoryId;
            currentCategoryName = categoryName;
            $('#selectedCategoryName').text(categoryName);
            
            // Initialize or reload stories table for this category
            if (storiesTable) {
                storiesTable.destroy();
            }
            
            storiesTable = $('#storiesTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: { action: 'get_stories_by_category', category_id: categoryId },
                    dataSrc: function(json) {
                        if (json.success && json.data) {
                            return json.data;
                        }
                        return [];
                    }
                },
                columns: [
                    { data: 'story_id' },
                    { 
                        data: 'story_title',
                        render: function(data, type, row) {
                            return data.length > 50 ? data.substring(0, 50) + '...' : data;
                        }
                    },
                    { 
                        data: 'audio_file_name',
                        render: function(data, type, row) {
                            if (data) {
                                return `
                                    <div class="audio-player-container">
                                        <button class="btn btn-sm btn-primary play-btn" onclick="toggleStoryAudio(${row.story_id}, this)" title="Play Audio">
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
                        data: 'total_sentences',
                        render: function(data, type, row) {
                            return `<span class="badge bg-info">${data} sentences</span>`;
                        }
                    },
                    { 
                        data: 'time_limit',
                        render: function(data, type, row) {
                            return data + 's';
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
                                
                                    <button class="btn btn-sm btn-info me-1" onclick="editStory(${row.story_id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning me-1" onclick="duplicateStory(${row.story_id})" title="Duplicate">
                                        <i class="ri-file-copy-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger me-1" onclick="deleteStory(${row.story_id})" title="Delete">
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
            // Hide stories section and action buttons
            $('#storiesSection').hide();
            $('#storiesActionButtons').hide();
            
            // Show categories view and action buttons
            $('#categoriesView').show();
            $('#categoriesActionButtons').show();
            
            // Clean up stories table
            if (storiesTable) {
                storiesTable.destroy();
                storiesTable = null;
            }
            
            currentCategoryId = null;
            currentCategoryName = '';
        }

        function hideStoriesSection() {
            // Same as backToCategoriesView - hide stories and show categories
            backToCategoriesView();
        }

        function showQuickAddPanelForCategory() {
            if (!currentCategoryId) {
                showErrorToast('No category selected');
                return;
            }
            
            // Load categories and pre-select the current category
            loadCategoriesForFilters();
            
            setTimeout(() => {
                $('#quick_story_category_id').val(currentCategoryId);
                // Lock the category selection when viewing specific category
                $('#quick_story_category_id').prop('disabled', true);
                $('#quick_story_category_id').addClass('bg-light');
                
                // Add a visual indicator that category is locked
                const categoryLabel = $('#quick_story_category_id').closest('.mb-3').find('.form-label');
                if (!categoryLabel.find('.locked-indicator').length) {
                    categoryLabel.append(' <span class="locked-indicator text-muted"><i class="ri-lock-line"></i> (Locked to current category)</span>');
                }
                
                // Show the quick add panel
                $('#quickAddPanel').slideDown(400, function() {
                    $('#quick_story_title').focus();
                });
                
                // Keep the stories table visible below the quick add panel
                $('#storiesSection').show();
            }, 200);
        }

        function hideQuickAddPanel() {
            // Reset category field state when hiding panel
            $('#quick_story_category_id').prop('disabled', false);
            $('#quick_story_category_id').removeClass('bg-light');
            $('.locked-indicator').remove();
            
            $('#quickAddPanel').slideUp();
            $('#storiesSection').show();
        }

        function clearQuickStoryForm() {
            $('#quickAddStoryForm')[0].reset(); 
            $('#quick_story_audio_preview').hide();
            quickSentencesList = [];
            renderQuickSentencesList();
            updateQuickSentenceCount();
            $('#quick_story_title').focus();
        }

        function resetQuickStoryFormExceptCategory() {
            // Store the selected category and its locked state
            const selectedCategory = $('#quick_story_category_id').val();
            const wasDisabled = $('#quick_story_category_id').prop('disabled');
            const hasLockedClass = $('#quick_story_category_id').hasClass('bg-light');
            const hasLockedIndicator = $('.locked-indicator').length > 0;
            
            // Reset all fields except category
            $('#quick_story_title').val('');
            $('#quick_story_tips').val('');
            $('#quick_story_audio_file').val('');
            $('#quick_story_audio_preview').hide(); 
            $('#quick_time_limit').val('20');
            $('#quick_story_is_active').val('1');
            quickSentencesList = [];
            renderQuickSentencesList();
            updateQuickSentenceCount();
            
            // Clear any validation errors
            clearInlineErrors('quickAddStoryForm');
            
            // Restore the category selection and its locked state
            $('#quick_story_category_id').val(selectedCategory);
            if (wasDisabled) {
                $('#quick_story_category_id').prop('disabled', true);
            }
            if (hasLockedClass) {
                $('#quick_story_category_id').addClass('bg-light');
            }
            
            // Focus on story title for next entry
            $('#quick_story_title').focus();
        }

        function refreshCurrentDataTable() {
            if (storiesTable) {
                storiesTable.ajax.reload();
            }
        }

        function refreshCategoriesTable() {
            if (categoriesTable) {
                categoriesTable.ajax.reload();
            }
        }

    </script>

<?php require("../layout/Footer.php"); ?>
