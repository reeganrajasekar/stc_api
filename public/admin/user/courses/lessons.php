<?php
// Start output buffering to prevent any accidental output
ob_start();

require("../layout/Session.php");
require("../../config/db.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'get_lessons':
            getLessons($conn);
            break;
        case 'get_lesson':
            getLesson($conn);
            break;
        case 'add_lesson':
            addLesson($conn);
            break;
        case 'update_lesson':
            updateLesson($conn);
            break;
        case 'delete_lesson':
            deleteLesson($conn);
            break;
        case 'get_categories':
            getCategories($conn);
            break;
        case 'get_courses_by_category':
            getCoursesByCategory($conn);
            break;
        case 'duplicate_lesson':
            duplicateLesson($conn);
            break;
        case 'get_audio_file':
            getAudioFile($conn);
            break;
        default:
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Clear output buffer for AJAX responses
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    ob_end_clean();
}

function getLessons($conn) {
    ob_end_clean(); // Clear output buffer first
    header('Content-Type: application/json');
    
    try {
        
        $params = $_REQUEST; 
        $draw = intval($params['draw'] ?? 1);
        $start = intval($params['start'] ?? 0);
        $length = intval($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';
        $orderColumn = intval($params['order'][0]['column'] ?? 0);
        $orderDir = $params['order'][0]['dir'] ?? 'desc';
        
        // Column mapping for ordering
        $columns = [
            0 => 'l.lesson_id',
            1 => 'l.lesson_number',
            2 => 'l.display_order',
            3 => 'l.lesson_title',
            4 => 'c.course_name',
            5 => 'l.points',
            6 => 'l.is_active',
            7 => 'l.created_at'
        ];
        
        // Default ordering by display_order and lesson_number
        $orderBy = ($columns[$orderColumn] ?? 'l.display_order') . ' ' . $orderDir . ', l.lesson_number ASC';
        
        // Base filtering
        $where = " WHERE 1=1 ";
        
        // Apply course filter if provided
        $courseFilter = $params['course_filter'] ?? '';
        if (!empty($courseFilter) && is_numeric($courseFilter)) {
            $where .= " AND l.course_id = " . intval($courseFilter) . " ";
        }
        
        // Total records count
        $totalSql = "SELECT COUNT(*) as total FROM lessons l 
                     LEFT JOIN courses c ON l.course_id = c.course_id 
                     LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
                     $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        // Apply search filter
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (l.lesson_title LIKE '%$search%' OR l.lesson_overview LIKE '%$search%' OR c.course_name LIKE '%$search%') ";
        }
        
        // Filtered count
        $filteredSql = "SELECT COUNT(*) as total FROM lessons l 
                        LEFT JOIN courses c ON l.course_id = c.course_id 
                        LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
                        $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        // Pagination
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        // Main data query
        $sql = "SELECT l.*, c.course_name, cc.category_name
                FROM lessons l 
                LEFT JOIN courses c ON l.course_id = c.course_id 
                LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'lesson_id' => $row['lesson_id'],
                    'course_id' => $row['course_id'],
                    'lesson_number' => $row['lesson_number'],
                    'lesson_title' => $row['lesson_title'],
                    'lesson_overview' => $row['lesson_overview'] ?? '',
                    'course_name' => $row['course_name'] ?? 'No Course',
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'audio_file_name' => $row['audio_file_name'] ?? '',
                    'points' => $row['points'],
                    'is_active' => $row['is_active'],
                    'display_order' => $row['display_order'],
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
/**
 * Sync lesson numbers to match display order for all lessons in a course
 * This does NOT change display_order, only updates lesson_number to match it
 */
function syncLessonNumbers($conn, $course_id) {
    $sql = "SELECT lesson_id, display_order FROM lessons WHERE course_id = ? ORDER BY display_order ASC, lesson_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lessonNumber = 1;
    while ($row = $result->fetch_assoc()) {
        $updateSql = "UPDATE lessons SET lesson_number = ? WHERE lesson_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ii", $lessonNumber, $row['lesson_id']);
        $updateStmt->execute();
        $updateStmt->close();
        $lessonNumber++;
    }
    $stmt->close();
}

/**
 * Reorder all lessons within a course to have sequential display orders starting from 1
 * This also syncs lesson numbers to match
 */
function reorderAllLessons($conn, $course_id) {
    $sql = "SELECT lesson_id FROM lessons WHERE course_id = ? ORDER BY display_order ASC, lesson_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $order = 1;
    while ($row = $result->fetch_assoc()) {
        $updateSql = "UPDATE lessons SET display_order = ?, lesson_number = ? WHERE lesson_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("iii", $order, $order, $row['lesson_id']);
        $updateStmt->execute();
        $updateStmt->close();
        $order++;
    }
    $stmt->close();
}

function getLesson($conn) {
    ob_end_clean(); // Clear output buffer first
    header('Content-Type: application/json');
    
    try {
        if (!isset($_POST['lesson_id'])) {
            echo json_encode(['success' => false, 'message' => 'lesson_id parameter is required']);
            return;
        }
        
        $lesson_id = intval($_POST['lesson_id']);
        
        $sql = "SELECT l.*, c.course_name, c.course_category_id, cc.category_name 
                FROM lessons l 
                LEFT JOIN courses c ON l.course_id = c.course_id 
                LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
                WHERE l.lesson_id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
            return;
        }
        
        $stmt->bind_param("i", $lesson_id);
        
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Query execution failed: ' . $stmt->error]);
            $stmt->close();
            return;
        }
        
        $result = $stmt->get_result();
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Failed to get result: ' . $stmt->error]);
            $stmt->close();
            return;
        }
        
        $row = $result->fetch_assoc();
        
        if ($row) {
            // Don't send the actual audio_file blob data to the frontend
            // Only send metadata
            if (isset($row['audio_file'])) {
                unset($row['audio_file']); // Remove binary data
            }
            
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lesson not found with ID: ' . $lesson_id]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function addLesson($conn) {
    ob_end_clean(); // Clear output buffer first
    header('Content-Type: application/json');
    
    try {
        // Validate and sanitize input data
        $course_id = filter_var($_POST['course_id'] ?? 0, FILTER_VALIDATE_INT);
        $lesson_title = trim($_POST['lesson_title'] ?? '');
        $lesson_overview = trim($_POST['lesson_overview'] ?? '');
        $lesson_content = $_POST['lesson_content'] ?? ''; // Rich text content
        $points = filter_var($_POST['points'] ?? 10, FILTER_VALIDATE_INT);
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        $display_order = filter_var($_POST['display_order'] ?? 0, FILTER_VALIDATE_INT);
        
        // Input validation
        if (!$course_id || $course_id <= 0) {
            throw new Exception('Please select a valid course');
        }
        
        if (empty($lesson_title) || strlen($lesson_title) < 3) {
            throw new Exception('Lesson title must be at least 3 characters long');
        }
        
        // Auto-generate the next lesson number for this course
        $maxSql = "SELECT COALESCE(MAX(lesson_number), 0) + 1 as next_number FROM lessons WHERE course_id = ?";
        $maxStmt = $conn->prepare($maxSql);
        $maxStmt->bind_param("i", $course_id);
        $maxStmt->execute();
        $maxResult = $maxStmt->get_result();
        $maxRow = $maxResult->fetch_assoc();
        $lesson_number = $maxRow['next_number'];
        $maxStmt->close();
        
        // Handle audio file upload (optional)
        $audioData = handleAudioUpload();
        
        // Handle file uploads
        $lesson_image = '';
        
        // Create upload directories
        $image_upload_dir = '../../../uploads/lessons/images/';
        
        if (!file_exists($image_upload_dir)) {
            mkdir($image_upload_dir, 0755, true);
        }
        
        // Handle image upload
        if (isset($_FILES['lesson_image']) && $_FILES['lesson_image']['error'] == 0) {
            $allowed_image = ['jpg', 'jpeg', 'png'];
            $file_ext = strtolower(pathinfo($_FILES['lesson_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_image)) {
                $lesson_image = 'lesson_' . time() . '_' . uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['lesson_image']['tmp_name'], $image_upload_dir . $lesson_image);
            }
        }
        
        // Auto-generate the next lesson number for this course
        $maxSql = "SELECT COALESCE(MAX(lesson_number), 0) + 1 as next_number FROM lessons WHERE course_id = ?";
        $maxStmt = $conn->prepare($maxSql);
        $maxStmt->bind_param("i", $course_id);
        $maxStmt->execute();
        $maxResult = $maxStmt->get_result();
        $maxRow = $maxResult->fetch_assoc();
        $lesson_number = $maxRow['next_number'];
        $maxStmt->close();
        
        // Begin database transaction
        $conn->begin_transaction();
        
        try {
            // Adjust existing display orders if inserting at specific position
            if ($display_order > 0) {
                // Increment all positions >= target position by 1 within the same course
                $adjustSql = "UPDATE lessons SET display_order = display_order + 1 
                             WHERE course_id = ? AND display_order >= ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("ii", $course_id, $display_order);
                $adjustStmt->execute();
                $adjustStmt->close();
            }
            
            $hasAudioFile = !empty($audioData['file_path']);
            
            // Store file PATH not BLOB
            $sql = "INSERT INTO lessons (course_id, lesson_number, lesson_title, lesson_overview, lesson_content, 
                    audio_file, audio_file_name, audio_mime_type, points, is_active, 
                    display_order, lesson_image, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            if ($hasAudioFile) {
                $stmt->bind_param("iissssssiiis", 
                    $course_id, $lesson_number, $lesson_title, $lesson_overview, $lesson_content,
                    $audioData['file_path'], $audioData['original_name'], $audioData['mime_type'], $points, 
                    $is_active, $display_order, $lesson_image
                );
            } else {
                $emptyPath = '';
                $emptyName = '';
                $emptyMime = '';
                $stmt->bind_param("iissssssiiis", 
                    $course_id, $lesson_number, $lesson_title, $lesson_overview, $lesson_content,
                    $emptyPath, $emptyName, $emptyMime, $points, 
                    $is_active, $display_order, $lesson_image
                );
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $stmt->close();
            
            // Only sync lesson numbers to match display order (doesn't change display_order)
            syncLessonNumbers($conn, $course_id);
            
            $conn->commit();
            
            error_log("Lesson added successfully: ID $insertId, Course: $course_id, Lesson Number: $lesson_number, Audio: " . ($audioData['file_path'] ? 'Yes' : 'No'));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Lesson added successfully at position ' . $display_order, 
                'id' => $insertId,
                'display_order' => $display_order,
                'audio_uploaded' => !empty($audioData['file_path'])
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            // Clean up uploaded files on error
            if (!empty($audioData['file_path']) && file_exists('../../../' . $audioData['file_path'])) {
                unlink('../../../' . $audioData['file_path']);
            }
            if (!empty($lesson_image) && file_exists($image_upload_dir . $lesson_image)) {
                unlink($image_upload_dir . $lesson_image);
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add Lesson Error: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateLesson($conn) {
    ob_end_clean(); // Clear output buffer first
    header('Content-Type: application/json');
    
    try {
        $lesson_id = $_POST['lesson_id'] ?? 0;
        $course_id = filter_var($_POST['course_id'] ?? 0, FILTER_VALIDATE_INT);
        $lesson_number = filter_var($_POST['lesson_number'] ?? 1, FILTER_VALIDATE_INT);
        $lesson_title = trim($_POST['lesson_title'] ?? '');
        $lesson_overview = trim($_POST['lesson_overview'] ?? '');
        $lesson_content = $_POST['lesson_content'] ?? '';
        $points = filter_var($_POST['points'] ?? 10, FILTER_VALIDATE_INT);
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        $display_order = filter_var($_POST['display_order'] ?? 0, FILTER_VALIDATE_INT);
        
        // Input validation
        if (!$course_id || $course_id <= 0) {
            throw new Exception('Please select a valid course');
        }
        
        if (empty($lesson_title) || strlen($lesson_title) < 3) {
            throw new Exception('Lesson title must be at least 3 characters long');
        }
        
        // Get existing lesson data
        $sql = "SELECT lesson_image, audio_file, display_order, course_id FROM lessons WHERE lesson_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        
        $old_display_order = $existing['display_order'] ?? 0;
        $old_course_id = $existing['course_id'] ?? 0;
        $lesson_image = $existing['lesson_image'] ?? '';
        $upload_dir = '../../../uploads/lessons/images/';
        
        // Begin transaction for display order adjustment
        $conn->begin_transaction();
        
        try {
            // First, temporarily set current item to a very high number to avoid conflicts
            if ($display_order != $old_display_order && $display_order > 0 && $old_display_order > 0) {
                $tempSql = "UPDATE lessons SET display_order = 99999 WHERE lesson_id = ?";
                $tempStmt = $conn->prepare($tempSql);
                $tempStmt->bind_param("i", $lesson_id);
                $tempStmt->execute();
                $tempStmt->close();
            }
            
            // Adjust display orders if changed and within same course
            if ($course_id == $old_course_id && $display_order != $old_display_order && $display_order > 0 && $old_display_order > 0) {
                if ($display_order < $old_display_order) {
                    // Moving up (e.g., from 4 to 2): increment orders between new and old position
                    $adjustSql = "UPDATE lessons SET display_order = display_order + 1 
                                 WHERE course_id = ? AND display_order >= ? AND display_order < ?";
                    $adjustStmt = $conn->prepare($adjustSql);
                    $adjustStmt->bind_param("iii", $course_id, $display_order, $old_display_order);
                    $adjustStmt->execute();
                    $adjustStmt->close();
                } else {
                    // Moving down (e.g., from 2 to 4): decrement orders between old and new position
                    $adjustSql = "UPDATE lessons SET display_order = display_order - 1 
                                 WHERE course_id = ? AND display_order > ? AND display_order <= ?";
                    $adjustStmt = $conn->prepare($adjustSql);
                    $adjustStmt->bind_param("iii", $course_id, $old_display_order, $display_order);
                    $adjustStmt->execute();
                    $adjustStmt->close();
                }
            } elseif ($course_id != $old_course_id && $old_display_order > 0) {
                // Moving to different course: adjust both courses
                // Decrement old course orders
                $adjustOldSql = "UPDATE lessons SET display_order = display_order - 1 
                                WHERE course_id = ? AND display_order > ?";
                $adjustOldStmt = $conn->prepare($adjustOldSql);
                $adjustOldStmt->bind_param("ii", $old_course_id, $old_display_order);
                $adjustOldStmt->execute();
                $adjustOldStmt->close();
                
                // Increment new course orders
                if ($display_order > 0) {
                    $adjustNewSql = "UPDATE lessons SET display_order = display_order + 1 
                                    WHERE course_id = ? AND display_order >= ?";
                    $adjustNewStmt = $conn->prepare($adjustNewSql);
                    $adjustNewStmt->bind_param("ii", $course_id, $display_order);
                    $adjustNewStmt->execute();
                    $adjustNewStmt->close();
                }
            }
        
        // Handle image upload
        if (isset($_FILES['lesson_image']) && $_FILES['lesson_image']['error'] == 0) {
            $allowed_image = ['jpg', 'jpeg', 'png'];
            $file_ext = strtolower(pathinfo($_FILES['lesson_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_image)) {
                // Delete old image
                if ($lesson_image && file_exists($upload_dir . $lesson_image)) {
                    unlink($upload_dir . $lesson_image);
                }
                $lesson_image = 'lesson_' . time() . '_' . uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['lesson_image']['tmp_name'], $upload_dir . $lesson_image);
            }
        }
        
        // Handle audio upload only if a file is actually uploaded
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] == 0) {
            try {
                $audioData = handleAudioUpload();
                
                if (!empty($audioData['file_path'])) {
                    // Delete old audio file
                    if (!empty($existing['audio_file']) && file_exists('../../../' . $existing['audio_file'])) {
                        unlink('../../../' . $existing['audio_file']);
                    }
                    
                    // Store file path
                    $sql = "UPDATE lessons SET course_id=?, lesson_number=?, lesson_title=?, lesson_overview=?, 
                            lesson_content=?, audio_file=?, audio_file_name=?, audio_mime_type=?, 
                            points=?, is_active=?, display_order=?, lesson_image=?, 
                            updated_at=NOW() WHERE lesson_id=?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare failed: ' . $conn->error);
                    }
                    
                    $stmt->bind_param("iissssssiiisi", $course_id, $lesson_number, $lesson_title, $lesson_overview, 
                        $lesson_content, $audioData['file_path'], $audioData['original_name'], $audioData['mime_type'], 
                        $points, $is_active, $display_order, $lesson_image, $lesson_id);
                } else {
                    // No audio uploaded, update without audio fields
                    $sql = "UPDATE lessons SET course_id=?, lesson_number=?, lesson_title=?, lesson_overview=?, 
                            lesson_content=?, points=?, is_active=?, display_order=?, 
                            lesson_image=?, updated_at=NOW() WHERE lesson_id=?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare failed: ' . $conn->error);
                    }
                    $stmt->bind_param("iissiiiisi", $course_id, $lesson_number, $lesson_title, $lesson_overview, 
                        $lesson_content, $points, $is_active, $display_order, $lesson_image, $lesson_id);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error uploading audio: ' . $e->getMessage()]);
                return;
            }
        } else {
            // No audio file uploaded, update without audio fields
            $sql = "UPDATE lessons SET course_id=?, lesson_number=?, lesson_title=?, lesson_overview=?, 
                    lesson_content=?, points=?, is_active=?, display_order=?, 
                    lesson_image=?, updated_at=NOW() WHERE lesson_id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("iissiiiisi", $course_id, $lesson_number, $lesson_title, $lesson_overview, 
                $lesson_content, $points, $is_active, $display_order, $lesson_image, $lesson_id);
        }
        
        if ($stmt->execute()) {
                // Only sync lesson numbers to match display order (doesn't change display_order)
                syncLessonNumbers($conn, $course_id);
                
                // If course changed, also sync the old course
                if ($course_id != $old_course_id) {
                    syncLessonNumbers($conn, $old_course_id);
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Lesson updated successfully at position ' . $display_order]);
            } else {
                $conn->rollback();
                throw new Exception('Error updating lesson: ' . $conn->error);
            }
        } catch (Exception $e) {
            if ($conn->connect_errno === 0) {
                $conn->rollback();
            }
            throw $e;
        }
    } catch (Exception $e) {
        error_log("Update Lesson Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteLesson($conn) {
    ob_end_clean(); // Clear output buffer first
    header('Content-Type: application/json');
    $lesson_id = $_POST['lesson_id'] ?? 0;
    
    // Get file paths and course_id before deleting
    $sql = "SELECT lesson_image, course_id FROM lessons WHERE lesson_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lesson = $result->fetch_assoc();
    $course_id = $lesson['course_id'] ?? 0;
    
    $conn->begin_transaction();
    
    try {
        $sql = "DELETE FROM lessons WHERE lesson_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $lesson_id);
        
        if ($stmt->execute()) {
            // Sync lesson numbers for remaining lessons (doesn't change display_order)
            if ($course_id > 0) {
                syncLessonNumbers($conn, $course_id);
            }
            
            $conn->commit();
            
            // Delete files
            $upload_dir = '../../../uploads/lessons/';
            if ($lesson['lesson_image'] && file_exists($upload_dir . $lesson['lesson_image'])) {
                unlink($upload_dir . $lesson['lesson_image']);
            }
            echo json_encode(['success' => true, 'message' => 'Lesson deleted successfully. Lesson numbers updated.']);
        } else {
            $conn->rollback();
            throw new Exception('Error deleting lesson: ' . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getCategories($conn) {
    ob_end_clean(); // Clear output buffer first
    header('Content-Type: application/json');
    $sql = "SELECT * FROM course_categories ORDER BY category_name";
    $result = $conn->query($sql);
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $categories]);
}

function getCoursesByCategory($conn) {
    ob_end_clean(); // Clear output buffer first
    header('Content-Type: application/json');
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT c.*, cc.category_name 
            FROM courses c 
            LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
            WHERE c.course_category_id = ? AND c.is_active = 1
            ORDER BY c.display_order, c.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $courses]);
}

function duplicateLesson($conn) {
    ob_end_clean(); // Clear output buffer first
    header('Content-Type: application/json');
    $lesson_id = $_POST['lesson_id'] ?? 0;
    
    // Get original lesson
    $sql = "SELECT * FROM lessons WHERE lesson_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Insert duplicate with modified title
        $new_lesson_title = $row['lesson_title'] . ' (Copy)';
        
        // Get the next available lesson number for this course
        $maxSql = "SELECT COALESCE(MAX(lesson_number), 0) + 1 as next_number FROM lessons WHERE course_id = ?";
        $maxStmt = $conn->prepare($maxSql);
        $maxStmt->bind_param("i", $row['course_id']);
        $maxStmt->execute();
        $maxResult = $maxStmt->get_result();
        $maxRow = $maxResult->fetch_assoc();
        $new_lesson_number = $maxRow['next_number'];
        $maxStmt->close();
        
        // Handle audio file duplication if it exists
        $new_audio_file = '';
        $new_audio_name = '';
        $new_audio_mime = '';
        
        if (!empty($row['audio_file']) && file_exists('../../../' . $row['audio_file'])) {
            $upload_dir = '../../../uploads/lessons/audio/';
            $yearMonth = date('Y/m');
            $full_upload_dir = $upload_dir . $yearMonth . '/';
            
            if (!file_exists($full_upload_dir)) {
                mkdir($full_upload_dir, 0755, true);
            }
            
            $original_path = '../../../' . $row['audio_file'];
            $file_extension = pathinfo($row['audio_file'], PATHINFO_EXTENSION);
            $unique_filename = 'audio_' . time() . '_' . uniqid() . '.' . $file_extension;
            $new_path = $full_upload_dir . $unique_filename;
            
            if (copy($original_path, $new_path)) {
                $new_audio_file = 'uploads/lessons/audio/' . $yearMonth . '/' . $unique_filename;
                $new_audio_name = $row['audio_file_name'];
                $new_audio_mime = $row['audio_mime_type'];
            }
        }
        
        $sql = "INSERT INTO lessons (course_id, lesson_number, lesson_title, lesson_overview, lesson_content, 
                audio_file, audio_file_name, audio_mime_type, points, is_active, 
                display_order, lesson_image) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssssiiis", 
            $row['course_id'], 
            $new_lesson_number, 
            $new_lesson_title, 
            $row['lesson_overview'], 
            $row['lesson_content'], 
            $new_audio_file, 
            $new_audio_name, 
            $new_audio_mime, 
            $row['points'], 
            $row['is_active'],
            $row['display_order'],
            $row['lesson_image']
        );
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Lesson duplicated successfully as Lesson #' . $new_lesson_number, 'new_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error duplicating lesson: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Original lesson not found']);
    }
}

function getAudioFile($conn) {
    ob_end_clean(); // Clear output buffer
    
    $lesson_id = $_GET['lesson_id'] ?? $_POST['lesson_id'] ?? 0;
    
    if (!$lesson_id) {
        http_response_code(400);
        echo "Lesson ID is required";
        return;
    }
    
    $sql = "SELECT audio_file, audio_file_name FROM lessons WHERE lesson_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $lesson_id);
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
                
                // Set headers for audio streaming
                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . filesize($filePath));
                header('Content-Disposition: inline; filename="' . $row['audio_file_name'] . '"');
                header('Accept-Ranges: bytes');
                header('Cache-Control: public, max-age=3600');
                
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
    
    // Use centralized upload configuration
    require_once('../../config/upload_config.php');
    
    $uploadDir = getUploadDir('lessons_audio');
    $yearMonth = date('Y/m');
    $fullUploadDir = $uploadDir . $yearMonth . '/';
    
    createSecureUploadDir($fullUploadDir);
    
    // Generate cryptographically secure filename
    $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $sanitizedName = substr($sanitizedName, 0, 50); // Limit length
    
    // Create unique filename with timestamp and random component
    $timestamp = date('Ymd_His');
    $randomBytes = bin2hex(random_bytes(8));
    $secureFilename = "audio_{$timestamp}_{$randomBytes}_{$sanitizedName}.{$fileExtension}";
    
    $uploadPath = $fullUploadDir . $secureFilename;
    $relativePath = getRelativePath('lessons_audio', $yearMonth . '/' . $secureFilename);
    
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

// formatBytes() function is now defined in upload_config.php

?>

<?php include '../layout/Header.php'; ?>

<!-- Quill Rich Text Editor CSS -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<style>
/* Audio Player Styles */
.audio-player-container {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background-color: #f8f9fa;
}

.audio-controls {
    flex: 1;
    min-width: 0;
}

.audio-filename {
    font-size: 0.875rem;
    font-weight: 500;
    color: #495057;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.audio-progress {
    height: 4px;
    background-color: #e9ecef;
    border-radius: 2px;
    cursor: pointer;
    margin-bottom: 4px;
    position: relative;
}

.audio-progress-bar {
    height: 100%;
    background-color: #0d6efd;
    border-radius: 2px;
    transition: width 0.1s ease;
}

.audio-time {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #6c757d;
}

.play-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.play-btn i {
    font-size: 16px;
}

/* Form success animation */
.form-success {
    animation: successPulse 0.6s ease-in-out;
}

@keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

/* Filter active state */
.filter-active {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}
</style>
 
<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">Lessons Management</h5>
    </div>
</div>

<!-- Enhanced Action Panel -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                      
                            <button class="btn btn-primary me-2" onclick="showQuickAddPanel()">
                                <i class="ri-add-line"></i> Add Lesson
                            </button>
                     
                        <button class="btn btn-outline-success" onclick="refreshDataTable()">
                            <i class="ri-refresh-line"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label mb-1 small">Filter by Category:</label>
                                <select class="form-select form-select-sm" id="categoryFilter" onchange="onCategoryChange()">
                                    <option value="">All Categories</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mb-1 small">Filter by Course:</label>
                                <select class="form-select form-select-sm" id="courseFilter" onchange="filterByCourse()" disabled>
                                    <option value="">Select category first</option>
                                </select>
                            </div>
                        </div>
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
                <h6 class="mb-0"><i class="ri-lightning-line"></i> Quick Add Lesson</h6>
                <button class="btn btn-sm btn-outline-light" onclick="hideQuickAddPanel()">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="card-body">
                <form id="quickAddForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" id="quick_category_id" onchange="loadCoursesForQuickAdd()" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Course *</label>
                                <select class="form-select" id="quick_course_id" name="course_id" required disabled>
                                    <option value="">Select category first</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Lesson Image</label>
                        <input type="file" class="form-control" id="quick_lesson_image" name="lesson_image" accept="image/*">
                        <small class="text-muted">JPG, PNG only</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Lesson Title *</label>
                        <input type="text" class="form-control" id="quick_lesson_title" name="lesson_title" required placeholder="Enter lesson title...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Lesson Overview</label>
                        <textarea class="form-control" id="quick_lesson_overview" name="lesson_overview" rows="2" placeholder="Brief overview of the lesson..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Lesson Content *</label>
                        <div id="quick_lesson_content_editor" style="height: 300px;"></div>
                        <input type="hidden" id="quick_lesson_content" name="lesson_content">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Audio File *</label>
                        <input type="file" class="form-control" id="quick_audio_file" name="audio_file" accept="audio/*" required onchange="previewQuickAudio(this)">
                        <small class="text-muted">MP3, WAV, OGG, M4A (Required)</small>
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
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Points</label>
                                <input type="number" class="form-control" id="quick_points" name="points" value="10" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="quick_display_order" name="display_order" value="0" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="quick_is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="quick_is_active">
                                Active
                            </label>
                        </div>
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

<!-- Lessons Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="lessonsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Lesson #</th>
                                <th>Display Order</th>
                                <th>Lesson Title</th>
                                <th>Course</th>
                                <th>Audio</th>
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

<!-- Edit Lesson Modal -->
<div class="modal fade" id="editLessonModal" tabindex="-1" aria-labelledby="editLessonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLessonModalLabel">Edit Lesson</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editLessonForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit_lesson_id" name="lesson_id">
                    <input type="hidden" name="action" value="update_lesson">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_category_id" class="form-label">Category *</label>
                                <select class="form-select" id="edit_category_id" onchange="loadCoursesForEdit()" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_course_id" class="form-label">Course *</label>
                                <select class="form-select" id="edit_course_id" name="course_id" required disabled>
                                    <option value="">Select category first</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_lesson_image" class="form-label">Lesson Image</label>
                        <input type="file" class="form-control" id="edit_lesson_image" name="lesson_image" accept="image/*">
                        <small class="text-muted">Leave empty to keep current image</small>
                        <div id="current_lesson_image"></div>
                    </div>
                    <input type="hidden" id="edit_lesson_number" name="lesson_number">
                    
                    <div class="mb-3">
                        <label for="edit_lesson_title" class="form-label">Lesson Title *</label>
                        <input type="text" class="form-control" id="edit_lesson_title" name="lesson_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_lesson_overview" class="form-label">Lesson Overview</label>
                        <textarea class="form-control" id="edit_lesson_overview" name="lesson_overview" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Lesson Content *</label>
                        <div id="edit_lesson_content_editor" style="height: 300px;"></div>
                        <input type="hidden" id="edit_lesson_content" name="lesson_content">
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
                                <label for="edit_points" class="form-label">Points</label>
                                <input type="number" class="form-control" id="edit_points" name="points" value="10" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="edit_display_order" name="display_order" value="0" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                            <label class="form-check-label" for="edit_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="updateLessonBtn">
                        <span class="btn-text">Update Lesson</span>
                        <span class="btn-loading" style="display: none;">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Updating...
                        </span>
                    </button>
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
                <p>Are you sure you want to delete this lesson? This action cannot be undone.</p>
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
<!-- Quill Rich Text Editor JS -->
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<script>
    let lessonsTable;
    let deleteItemId = null;
    let currentCourseFilter = '';
    
    // Quill editors
    let quickContentEditor;
    let editContentEditor;

    $(document).ready(function() {
        // Initialize Quill editors
        quickContentEditor = new Quill('#quick_lesson_content_editor', {
            theme: 'snow',
            placeholder: 'Enter lesson content here...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'indent': '-1'}, { 'indent': '+1' }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            }
        });
        
        editContentEditor = new Quill('#edit_lesson_content_editor', {
            theme: 'snow',
            placeholder: 'Enter lesson content here...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'indent': '-1'}, { 'indent': '+1' }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            }
        });

        // Initialize DataTable
        lessonsTable = $('#lessonsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '',
                type: 'POST',
                data: function(d) {
                    d.action = 'get_lessons';
                    d.course_filter = currentCourseFilter;
                    return d;
                },
                dataSrc: function(json) {
                    if (json.error) {
                        showErrorToast('Server error: ' + json.error);
                        return [];
                    }
                    return json.data || [];
                },
                error: function(xhr, error, thrown) {
                    showErrorToast('Error loading data');
                }
            },
            columns: [
                { data: 'lesson_id' },
                { data: 'lesson_number' },
                { 
                    data: 'display_order',
                    render: function(data, type, row) {
                        return '<span class="badge bg-info">' + data + '</span>';
                    }
                },
                { 
                    data: 'lesson_title',
                    render: function(data, type, row) {
                        return data.length > 50 ? data.substring(0, 50) + '...' : data;
                    }
                },
                { data: 'course_name' },
                { 
                    data: 'audio_file_name',
                    render: function(data, type, row) {
                        if (data) {
                            const playerId = 'audio-player-' + row.lesson_id;
                            return `
                                <div class="audio-player-container" id="${playerId}">
                                    <button class="btn btn-sm btn-primary play-btn" onclick="toggleLessonAudio(${row.lesson_id}, this)" title="Play Audio">
                                        <i class="ri-play-fill"></i>
                                    </button>
                                    <div class="audio-controls">
                                        <div class="audio-filename">${data.length > 25 ? data.substring(0, 25) + '...' : data}</div>
                                        <div class="audio-progress" onclick="seekLessonAudio(event, ${row.lesson_id})" style="display: none;">
                                            <div class="audio-progress-bar" id="progress-${row.lesson_id}"></div>
                                        </div>
                                        <div class="audio-time" style="display: none;">
                                            <span id="current-time-${row.lesson_id}">0:00</span>
                                            <span id="duration-${row.lesson_id}">0:00</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        return '<span class="text-muted">No audio</span>';
                    }
                },
                { data: 'points' },
                { 
                    data: 'is_active',
                    render: function(data) {
                        return data == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                    }
                },
                { 
                    data: 'created_at',
                    render: function(data) {
                        return new Date(data).toLocaleDateString();
                    }
                },
                { 
                    data: null,
                    render: function(data, type, row) {
                        return `
                            
                                <button class="btn btn-sm btn-info me-1" onclick="editLesson(${row.lesson_id})" title="Edit">
                                    <i class="ri-edit-line"></i>
                                </button>
                                <button class="btn btn-sm btn-warning me-1" onclick="duplicateLesson(${row.lesson_id})" title="Duplicate">
                                    <i class="ri-file-copy-line"></i>
                                </button>
                                <button class="btn btn-sm btn-danger me-1" onclick="deleteLesson(${row.lesson_id})" title="Delete">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                           
                        `;
                    }
                }
            ],
            order: [[2, 'asc']], // Sort by display_order (column index 2) ascending
            pageLength: 10,
            responsive: true
        });

        // Load categories
        loadCategories();

        // Quick add form submission
        $('#quickAddForm').on('submit', function(e) {
            e.preventDefault();
            
            // Get content from Quill editor
            const content = quickContentEditor.root.innerHTML;
            $('#quick_lesson_content').val(content);
            
            // Clear previous errors
            clearInlineErrors('quickAddForm');
            
            // Client-side validation
            let hasError = false;
            
            if (!$('#quick_course_id').val()) {
                showInlineError('quick_course_id', 'Please select a course');
                hasError = true;
            }
            
            if (!$('#quick_lesson_title').val() || $('#quick_lesson_title').val().length < 3) {
                showInlineError('quick_lesson_title', 'Lesson title must be at least 3 characters');
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
            
            const formData = new FormData(this);
            formData.append('action', 'add_lesson');
            formData.set('is_active', $('#quick_is_active').is(':checked') ? 1 : 0);
            
            $.ajax({
                url: '',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(result) {
                    console.log('Add lesson result:', result);
                    if (result.success) {
                        showSuccessToast(result.message || 'Lesson added successfully!');
                        resetQuickFormExceptCourse();
                        refreshDataTable();
                        setTimeout(() => {
                            $('#quick_lesson_title').focus();
                        }, 200);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function(xhr) {
                    showErrorToast('An error occurred while saving the lesson.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false);
                    submitBtn.find('.btn-loading').hide();
                    submitBtn.find('.btn-text').show();
                }
            });
        });

        // Edit lesson form submission
        $('#editLessonForm').on('submit', function(e) {
            e.preventDefault();
            
            // Get content from Quill editor
            const content = editContentEditor.root.innerHTML;
            $('#edit_lesson_content').val(content);
            
            clearInlineErrors('editLessonForm');
            
            const submitBtn = $('#updateLessonBtn');
            submitBtn.prop('disabled', true);
            submitBtn.find('.btn-text').hide();
            submitBtn.find('.btn-loading').show();
            
            const formData = new FormData(this);
            formData.set('is_active', $('#edit_is_active').is(':checked') ? 1 : 0);
            
            $.ajax({
                url: '',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(result) {
                    if (result.success) {
                        $('#editLessonModal').modal('hide');
                        refreshDataTable();
                        showSuccessToast('Lesson updated successfully!');
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('An error occurred while updating the lesson.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false);
                    submitBtn.find('.btn-loading').hide();
                    submitBtn.find('.btn-text').show();
                }
            });
        });

        // Delete confirmation
        $('#confirmDelete').on('click', function() {
            deleteLessonConfirm(deleteItemId);
        });
    });

    // Helper functions
    function showInlineError(fieldId, message) {
        const field = $('#' + fieldId);
        const formGroup = field.closest('.mb-3');
        formGroup.find('.invalid-feedback').remove();
        field.removeClass('is-invalid');
        field.addClass('is-invalid');
        formGroup.append(`<div class="invalid-feedback d-block">${message}</div>`);
        field[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        field.focus();
    }
    
    function clearInlineErrors(formId) {
        const form = $('#' + formId);
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').remove();
    }
    
    $(document).on('input change', '.form-control, .form-select', function() {
        $(this).removeClass('is-invalid');
        $(this).closest('.mb-3').find('.invalid-feedback').remove();
    });

    function loadCategories() {
        $.ajax({
            url: '',
            type: 'POST',
            dataType: 'json',
            data: { action: 'get_categories' },
            success: function(result) {
                if (result.success) {
                    const categoryFilter = $('#categoryFilter');
                    const quickCategory = $('#quick_category_id');
                    const editCategory = $('#edit_category_id');
                    
                    categoryFilter.empty().append('<option value="">All Categories</option>');
                    quickCategory.empty().append('<option value="">Select Category</option>');
                    editCategory.empty().append('<option value="">Select Category</option>');
                    
                    result.data.forEach(function(category) {
                        categoryFilter.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                        quickCategory.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                        editCategory.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading categories:', error);
                console.log('Response:', xhr.responseText);
                showErrorToast('Error loading categories');
            }
        });
    }

    function onCategoryChange() {
        const categoryId = $('#categoryFilter').val();
        const courseFilter = $('#courseFilter');
        
        if (categoryId) {
            $.ajax({
                url: '',
                type: 'POST',
                dataType: 'json',
                data: { action: 'get_courses_by_category', category_id: categoryId },
                success: function(result) {
                    if (result.success) {
                        courseFilter.empty().append('<option value="">All Courses</option>');
                        result.data.forEach(function(course) {
                            courseFilter.append(`<option value="${course.course_id}">${course.course_name}</option>`);
                        });
                        courseFilter.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    console.log('Response:', xhr.responseText);
                    showErrorToast('Error loading courses');
                }
            });
        } else {
            courseFilter.empty().append('<option value="">Select category first</option>');
            courseFilter.prop('disabled', true);
            currentCourseFilter = '';
            refreshDataTable();
        }
    }

    function filterByCourse() {
        currentCourseFilter = $('#courseFilter').val();
        refreshDataTable();
        
        if (currentCourseFilter) {
            const selectedText = $('#courseFilter option:selected').text();
            showSuccessToast('Filtered by: ' + selectedText);
        } else {
            showSuccessToast('Showing all lessons');
        }
    }

    function loadCoursesForQuickAdd() {
        const categoryId = $('#quick_category_id').val();
        const courseSelect = $('#quick_course_id');
        
        if (categoryId) {
            $.ajax({
                url: '',
                type: 'POST',
                dataType: 'json',
                data: { action: 'get_courses_by_category', category_id: categoryId },
                success: function(result) {
                    if (result.success) {
                        courseSelect.empty().append('<option value="">Select Course</option>');
                        result.data.forEach(function(course) {
                            courseSelect.append(`<option value="${course.course_id}">${course.course_name}</option>`);
                        });
                        courseSelect.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    console.log('Response:', xhr.responseText);
                    showErrorToast('Error loading courses');
                }
            });
        } else {
            courseSelect.empty().append('<option value="">Select category first</option>');
            courseSelect.prop('disabled', true);
        }
    }

    function loadCoursesForEdit() {
        const categoryId = $('#edit_category_id').val();
        const courseSelect = $('#edit_course_id');
        
        if (categoryId) {
            $.ajax({
                url: '',
                type: 'POST',
                dataType: 'json',
                data: { action: 'get_courses_by_category', category_id: categoryId },
                success: function(result) {
                    if (result.success) {
                        courseSelect.empty().append('<option value="">Select Course</option>');
                        result.data.forEach(function(course) {
                            courseSelect.append(`<option value="${course.course_id}">${course.course_name}</option>`);
                        });
                        courseSelect.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    console.log('Response:', xhr.responseText);
                    showErrorToast('Error loading courses');
                }
            });
        } else {
            courseSelect.empty().append('<option value="">Select category first</option>');
            courseSelect.prop('disabled', true);
        }
    }

    function showQuickAddPanel() {
        $('#quickAddPanel').slideDown(400, function() {
            $('#quick_lesson_title').focus();
        });
    }

    function hideQuickAddPanel() {
        $('#quickAddPanel').slideUp();
    }

    function clearQuickForm() {
        $('#quickAddForm')[0].reset();
        quickContentEditor.setContents([]);
        $('#quick_is_active').prop('checked', true);
        $('#quick_points').val('10');
        $('#quick_display_order').val('0');
        $('#quick_course_id').prop('disabled', true).empty().append('<option value="">Select category first</option>');
        $('#quick_audio_preview').hide(); // Hide audio preview
        $('#quick_preview_audio').attr('src', ''); // Clear audio source
        $('#quick_lesson_title').focus();
    }

    function resetQuickFormExceptCourse() {
        const selectedCategory = $('#quick_category_id').val();
        const selectedCourse = $('#quick_course_id').val();
        
        $('#quick_lesson_title').val('');
        $('#quick_lesson_overview').val('');
        quickContentEditor.setContents([]);
        $('#quick_lesson_image').val('');
        $('#quick_audio_file').val('');
        $('#quick_audio_preview').hide(); // Hide audio preview
        $('#quick_preview_audio').attr('src', ''); // Clear audio source
        $('#quick_points').val('10');
        $('#quick_display_order').val('0');
        $('#quick_is_active').prop('checked', true);
        
        $('#quick_category_id').val(selectedCategory);
        $('#quick_course_id').val(selectedCourse);
    }

    function editLesson(lessonId) {
        console.log('Fetching lesson with ID:', lessonId);
        
        $.ajax({
            url: '',
            type: 'POST',
            dataType: 'json',
            data: { action: 'get_lesson', lesson_id: lessonId },
            success: function(result) {
                console.log('Get lesson response:', result);
                
                if (result.success) {
                    const data = result.data;
                    
                    // Set basic form values first
                    $('#edit_lesson_id').val(data.lesson_id);
                    $('#edit_category_id').val(data.course_category_id);
                    
                    // Load courses for this category, then populate the rest
                    $.ajax({
                        url: '',
                        type: 'POST',
                        dataType: 'json',
                        data: { action: 'get_courses_by_category', category_id: data.course_category_id },
                        success: function(coursesResult) {
                            if (coursesResult.success) {
                                const courseSelect = $('#edit_course_id');
                                courseSelect.empty().append('<option value="">Select Course</option>');
                                coursesResult.data.forEach(function(course) {
                                    courseSelect.append(`<option value="${course.course_id}">${course.course_name}</option>`);
                                });
                                courseSelect.prop('disabled', false);
                                courseSelect.val(data.course_id);
                                
                                // Now populate the rest of the form after course dropdown is ready
                                $('#edit_lesson_number').val(data.lesson_number); // Hidden field to preserve lesson number
                                $('#edit_lesson_title').val(data.lesson_title);
                                $('#edit_lesson_overview').val(data.lesson_overview || '');
                                
                                // Set Quill editor content - wait a bit for editor to be ready
                                setTimeout(function() {
                                    if (data.lesson_content && data.lesson_content.trim() !== '') {
                                        editContentEditor.root.innerHTML = data.lesson_content;
                                    } else {
                                        editContentEditor.setContents([]);
                                    }
                                }, 100);
                                
                                $('#edit_points').val(data.points);
                                $('#edit_display_order').val(data.display_order);
                                $('#edit_is_active').prop('checked', data.is_active == 1);
                                
                                if (data.lesson_image) {
                                    $('#current_lesson_image').html('<small class="text-success">Current: ' + data.lesson_image + '</small>');
                                } else {
                                    $('#current_lesson_image').html('');
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
                                $('#edit_preview_audio').attr('src', '');
                                
                                // Stop and cleanup any existing audio
                                if (editCurrentAudio) {
                                    editCurrentAudio.pause();
                                    editCurrentAudio = null;
                                }
                                
                                // Reset play button state
                                $('#edit_current_audio .play-btn').html('<i class="ri-play-fill"></i>').removeClass('btn-success').addClass('btn-primary');
                                
                                // Show modal after everything is populated
                                $('#editLessonModal').modal('show');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error loading courses:', error);
                            showErrorToast('Error loading courses for this category');
                        }
                    });
                } else {
                    showErrorToast(result.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                showErrorToast('Error loading lesson data');
            }
        });
    }

    function deleteLesson(lessonId) {
        deleteItemId = lessonId;
        $('#deleteModal').modal('show');
    }

    function deleteLessonConfirm(lessonId) {
        $.ajax({
            url: '',
            type: 'POST',
            dataType: 'json',
            data: { action: 'delete_lesson', lesson_id: lessonId },
            success: function(result) {
                if (result.success) {
                    $('#deleteModal').modal('hide');
                    refreshDataTable();
                    showSuccessToast(result.message);
                } else {
                    showErrorToast(result.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showErrorToast('Error deleting lesson');
            }
        });
    }

    function duplicateLesson(lessonId) {
        if (confirm('Are you sure you want to duplicate this lesson?')) {
            $.ajax({
                url: '',
                type: 'POST',
                dataType: 'json',
                data: { action: 'duplicate_lesson', lesson_id: lessonId },
                success: function(result) {
                    if (result.success) {
                        refreshDataTable();
                        showSuccessToast('Lesson duplicated successfully!');
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    showErrorToast('Error duplicating lesson');
                }
            });
        }
    }

    function refreshDataTable() {
        try {
            if (lessonsTable) {
                lessonsTable.ajax.reload(null, false);
            }
        } catch (error) {
            location.reload();
        }
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
        setTimeout(() => toast.remove(), 4000);
    }

    // Reset edit form when modal is closed
    $('#editLessonModal').on('hidden.bs.modal', function() {
        $('#editLessonForm')[0].reset();
        editContentEditor.setContents([]);
        const submitBtn = $('#updateLessonBtn');
        submitBtn.prop('disabled', false);
        submitBtn.find('.btn-loading').hide();
        submitBtn.find('.btn-text').show();
        $('#current_lesson_image').html('');
        $('#edit_current_audio').hide();
        $('#edit_audio_preview').hide();
        $('#edit_audio_file').val('');
        $('#edit_preview_audio').attr('src', '');
        
        // Stop and cleanup current audio if playing
        if (editCurrentAudio) {
            editCurrentAudio.pause();
            editCurrentAudio = null;
        }
    });

    // Audio preview functions for Quick Add
    function previewQuickAudio(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const fileSize = file.size;
            const maxSize = 50 * 1024 * 1024; // 50MB
            
            if (fileSize > maxSize) {
                showErrorToast('Audio file too large. Maximum size is 50MB');
                input.value = '';
                return;
            }
            
            const allowedTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/mp4'];
            if (!allowedTypes.includes(file.type) && !file.name.match(/\.(mp3|wav|ogg|m4a)$/i)) {
                showErrorToast('Invalid audio format. Allowed: MP3, WAV, OGG, M4A');
                input.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#quick_audio_filename').text(file.name);
                $('#quick_preview_audio').attr('src', e.target.result);
                $('#quick_audio_preview').show();
            };
            reader.readAsDataURL(file);
        }
    }

    function removeQuickAudio() {
        $('#quick_audio_file').val('');
        $('#quick_audio_preview').hide();
        $('#quick_preview_audio').attr('src', '');
    }

    // Audio preview functions for Edit Modal
    function previewEditAudio(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const fileSize = file.size;
            const maxSize = 50 * 1024 * 1024; // 50MB
            
            if (fileSize > maxSize) {
                showErrorToast('Audio file too large. Maximum size is 50MB');
                input.value = '';
                return;
            }
            
            const allowedTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/mp4'];
            if (!allowedTypes.includes(file.type) && !file.name.match(/\.(mp3|wav|ogg|m4a)$/i)) {
                showErrorToast('Invalid audio format. Allowed: MP3, WAV, OGG, M4A');
                input.value = '';
                return;
            }
            
            // Stop and cleanup current audio if playing
            if (editCurrentAudio) {
                editCurrentAudio.pause();
                editCurrentAudio = null;
            }
            
            // Hide current audio player when new audio is selected
            $('#edit_current_audio').hide();
            
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#edit_audio_filename').text(file.name);
                $('#edit_preview_audio').attr('src', e.target.result);
                $('#edit_audio_preview').show();
            };
            reader.readAsDataURL(file);
        }
    }

    function removeEditAudio() {
        $('#edit_audio_file').val('');
        $('#edit_audio_preview').hide();
        $('#edit_preview_audio').attr('src', '');
        
        // Show current audio player again if it was hidden
        const currentFilename = $('#edit_current_filename').text();
        if (currentFilename) {
            $('#edit_current_audio').show();
        }
    }

    function togglePreviewAudio(audioId, button) {
        const audio = document.getElementById(audioId);
        if (audio.paused) {
            audio.play();
            $(button).html('<i class="ri-pause-fill"></i>');
        } else {
            audio.pause();
            $(button).html('<i class="ri-play-fill"></i>');
        }
    }

    // Edit modal current audio player
    let editCurrentAudio = null;

    // Audio player functionality for DataTable (like repeatafterme.php)
    let lessonAudioPlayers = {};
    let currentPlayingLessonId = null;

    function toggleLessonAudio(lessonId, button) {
        // Stop currently playing audio if different
        if (currentPlayingLessonId && currentPlayingLessonId !== lessonId) {
            stopLessonAudio(currentPlayingLessonId);
        }

        // If this audio is already playing, pause it
        if (lessonAudioPlayers[lessonId] && !lessonAudioPlayers[lessonId].paused) {
            pauseLessonAudio(lessonId, button);
            return;
        }

        // If audio exists but is paused, resume it
        if (lessonAudioPlayers[lessonId]) {
            resumeLessonAudio(lessonId, button);
            return;
        }

        // Create new audio player
        createLessonAudioPlayer(lessonId, button);
    }

    function createLessonAudioPlayer(lessonId, button) {
        // Show progress bar and time display
        $(`#audio-player-${lessonId} .audio-progress`).fadeIn(200);
        $(`#audio-player-${lessonId} .audio-time`).fadeIn(200);
        
        // Create form data for audio request
        const formData = new FormData();
        formData.append('action', 'get_audio_file');
        formData.append('lesson_id', lessonId);

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
            
            lessonAudioPlayers[lessonId] = audio;
            currentPlayingLessonId = lessonId;

            // Update button to pause icon
            $(button).html('<i class="ri-pause-fill"></i>');
            $(button).removeClass('btn-primary').addClass('btn-success');

            // Set up event listeners
            audio.addEventListener('loadedmetadata', function() {
                updateLessonDuration(lessonId, audio.duration);
            });

            audio.addEventListener('timeupdate', function() {
                updateLessonProgress(lessonId, audio.currentTime, audio.duration);
            });

            audio.addEventListener('ended', function() {
                resetLessonAudioPlayer(lessonId, button);
                URL.revokeObjectURL(audioUrl);
            });

            audio.addEventListener('error', function(e) {
                console.error('Audio error:', e);
                showErrorToast('Error playing audio file');
                resetLessonAudioPlayer(lessonId, button);
            });

            // Play audio
            audio.play().catch(err => {
                console.error('Play error:', err);
                showErrorToast('Error playing audio: ' + err.message);
                resetLessonAudioPlayer(lessonId, button);
            });
        })
        .catch(err => {
            console.error('Fetch error:', err);
            showErrorToast('Error loading audio file');
        });
    }

    function pauseLessonAudio(lessonId, button) {
        if (lessonAudioPlayers[lessonId]) {
            lessonAudioPlayers[lessonId].pause();
            $(button).html('<i class="ri-play-fill"></i>');
            $(button).removeClass('btn-success').addClass('btn-primary');
            currentPlayingLessonId = null;
        }
    }

    function resumeLessonAudio(lessonId, button) {
        if (lessonAudioPlayers[lessonId]) {
            lessonAudioPlayers[lessonId].play();
            $(button).html('<i class="ri-pause-fill"></i>');
            $(button).removeClass('btn-primary').addClass('btn-success');
            currentPlayingLessonId = lessonId;
        }
    }

    function stopLessonAudio(lessonId) {
        if (lessonAudioPlayers[lessonId]) {
            lessonAudioPlayers[lessonId].pause();
            lessonAudioPlayers[lessonId].currentTime = 0;
            
            const button = $(`#audio-player-${lessonId} .play-btn`);
            $(button).html('<i class="ri-play-fill"></i>');
            $(button).removeClass('btn-success').addClass('btn-primary');
            
            updateLessonProgress(lessonId, 0, lessonAudioPlayers[lessonId].duration);
        }
    }

    function resetLessonAudioPlayer(lessonId, button) {
        $(button).html('<i class="ri-play-fill"></i>');
        $(button).removeClass('btn-success').addClass('btn-primary');
        updateLessonProgress(lessonId, 0, 0);
        currentPlayingLessonId = null;
        
        if (lessonAudioPlayers[lessonId]) {
            delete lessonAudioPlayers[lessonId];
        }
    }

    function updateLessonProgress(lessonId, currentTime, duration) {
        const progress = duration > 0 ? (currentTime / duration) * 100 : 0;
        $(`#progress-${lessonId}`).css('width', progress + '%');
        $(`#current-time-${lessonId}`).text(formatTime(currentTime));
    }

    function updateLessonDuration(lessonId, duration) {
        $(`#duration-${lessonId}`).text(formatTime(duration));
    }

    function seekLessonAudio(event, lessonId) {
        if (!lessonAudioPlayers[lessonId]) return;

        const progressBar = $(event.currentTarget);
        const clickX = event.offsetX;
        const width = progressBar.width();
        const percentage = clickX / width;
        
        const audio = lessonAudioPlayers[lessonId];
        audio.currentTime = percentage * audio.duration;
    }

    function formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    function toggleEditCurrentAudio(button) {
        const lessonId = $('#edit_lesson_id').val();
        
        if (!editCurrentAudio) {
            // Create form data for audio request
            const formData = new FormData();
            formData.append('action', 'get_audio_file');
            formData.append('lesson_id', lessonId);

            // Fetch audio file
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


</script>
 

<?php require("../layout/Footer.php"); ?>
