<?php
require("../layout/Session.php");
require("../../config/db.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'get_courses':
            getCourses($conn);
            break;
        case 'get_course':
            getCourse($conn);
            break;
        case 'add_course':
            addCourse($conn);
            break;
        case 'update_course':
            updateCourse($conn);
            break;
        case 'delete_course':
            deleteCourse($conn);
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
        case 'get_courses_by_category':
            getCoursesByCategory($conn);
            break;
        case 'duplicate_course':
            duplicateCourse($conn);
            break;
        case 'get_quizzes':
            getQuizzes($conn);
            break;
        case 'get_quiz':
            getQuiz($conn);
            break;
        case 'add_quiz':
            addQuiz($conn);
            break;
        case 'update_quiz':
            updateQuiz($conn);
            break;
        case 'delete_quiz':
            deleteQuiz($conn);
            break;
        case 'get_quiz_questions':
            getQuizQuestions($conn);
            break;
        case 'add_quiz_question':
            addQuizQuestion($conn);
            break;
        case 'update_quiz_question':
            updateQuizQuestion($conn);
            break;
        case 'delete_quiz_question':
            deleteQuizQuestion($conn);
            break;
        case 'get_quiz_question':
            getQuizQuestion($conn);
            break;
        case 'import_quiz_questions':
            importQuizQuestions($conn);
            break;
        case 'upload_course_quize_image':
            uploadCourseQuizImage($conn);
            break;
        case 'delete_course_quize_image':
            deleteCourseQuizImage($conn);
            break;
        case 'get_assessments':
            getAssessments($conn);
            break;
        case 'get_assessment':
            getAssessment($conn);
            break;
        case 'add_assessment':
            addAssessment($conn);
            break;
        case 'update_assessment':
            updateAssessment($conn);
            break;
        case 'delete_assessment':
            deleteAssessment($conn);
            break;
        case 'get_assessment_questions':
            getAssessmentQuestions($conn);
            break;
        case 'add_assessment_question':
            addAssessmentQuestion($conn);
            break;
        case 'update_assessment_question':
            updateAssessmentQuestion($conn);
            break;
        case 'delete_assessment_question':
            deleteAssessmentQuestion($conn);
            break;
        case 'get_assessment_question':
            getAssessmentQuestion($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function getCourses($conn) {
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
            0 => 'c.course_id',
            1 => 'c.course_name', 
            2 => 'cc.category_name',
            3 => 'c.is_active',
            4 => 'c.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'c.display_order, c.created_at') . ' ' . $orderDir;
        
        // Base filtering
        $where = " WHERE 1=1 ";
        
        // Apply category filter if provided
        $categoryFilter = $params['category_filter'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND c.course_category_id = " . intval($categoryFilter) . " ";
        }
        
        // Total records count (with category filter applied)
        $totalSql = "SELECT COUNT(*) as total FROM courses c LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        // Apply search filter
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (c.course_name LIKE '%$search%' OR c.course_subtitle LIKE '%$search%' OR cc.category_name LIKE '%$search%') ";
        }
        
        // Filtered count
        $filteredSql = "SELECT COUNT(*) as total FROM courses c LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        // Pagination
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        // Main data query
        $sql = "SELECT c.course_id, c.course_name, c.course_subtitle, c.course_category_id, c.course_image,
                       c.level, c.is_active, c.display_order, c.created_at, cc.category_name
                FROM courses c 
                LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'course_id' => $row['course_id'],
                    'course_name' => $row['course_name'],
                    'course_subtitle' => $row['course_subtitle'] ?? '',
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'course_category_id' => $row['course_category_id'],
                    'course_image' => $row['course_image'] ?? '',
                    'level' => $row['level'] ?? '',
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

function getCourse($conn) {
    $course_id = $_POST['course_id'] ?? 0;
    
    $sql = "SELECT c.*, cc.category_name 
            FROM courses c 
            LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
            WHERE c.course_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Debug: Log quize_image value
        error_log("getCourse - course_id: {$course_id}, quize_image: " . ($row['quize_image'] ?? 'NULL'));
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
    }
}

function addCourse($conn) {
    try {
        // Validate and sanitize input data
        $course_category_id = filter_var($_POST['course_category_id'] ?? 0, FILTER_VALIDATE_INT);
        $course_name = trim($_POST['course_name'] ?? '');
        $course_subtitle = trim($_POST['course_subtitle'] ?? '');
        $course_overview = trim($_POST['course_overview'] ?? '');
        $course_outcomes = trim($_POST['course_outcomes'] ?? '');
        $level = trim($_POST['level'] ?? '');
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        $display_order = filter_var($_POST['display_order'] ?? 0, FILTER_VALIDATE_INT);
        
        // Input validation
        if (!$course_category_id || $course_category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($course_name) || strlen($course_name) < 3) {
            throw new Exception('Course name must be at least 3 characters long');
        }
        
        if (empty($level)) {
            throw new Exception('Please select a level');
        }
        
        // Validate level
        $valid_levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D1', 'D2'];
        if (!in_array($level, $valid_levels)) {
            throw new Exception('Invalid level selected');
        }
        
        // Handle file upload
        $course_image = '';
        $course_image_type = '';
        $upload_dir = '../../../uploads/courses/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Handle image upload
        if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] == 0) {
            $allowed_image = ['jpg', 'jpeg', 'png'];
            $file_ext = strtolower(pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_image)) {
                $course_image = 'course_' . time() . '_' . uniqid() . '.' . $file_ext;
                $course_image_type = 'image/' . ($file_ext === 'jpg' ? 'jpeg' : $file_ext);
                move_uploaded_file($_FILES['course_image']['tmp_name'], $upload_dir . $course_image);
            }
        }
        
        // Begin database transaction
        $conn->begin_transaction();
        
        try {
            $sql = "INSERT INTO courses (course_category_id, course_name, course_subtitle, course_overview, course_outcomes, level, course_image, course_image_type, is_active, display_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            $stmt->bind_param("isssssssii", 
                $course_category_id, $course_name, $course_subtitle, $course_overview, $course_outcomes,
                $level, $course_image, $course_image_type, $is_active, $display_order
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $conn->commit();
            
            error_log("Course added successfully: ID $insertId, Category: $course_category_id");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Course added successfully', 
                'id' => $insertId
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add Course Error: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateCourse($conn) {
    try {
        $course_id = $_POST['course_id'] ?? 0;
        $course_category_id = $_POST['course_category_id'] ?? 0;
        $course_name = trim($_POST['course_name'] ?? '');
        $course_subtitle = trim($_POST['course_subtitle'] ?? '');
        $course_overview = trim($_POST['course_overview'] ?? '');
        $course_outcomes = trim($_POST['course_outcomes'] ?? '');
        $level = trim($_POST['level'] ?? '');
        $is_active = $_POST['is_active'] ?? 1;
        $display_order = $_POST['display_order'] ?? 0;
        
        // Input validation
        if (!$course_category_id || $course_category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($course_name) || strlen($course_name) < 3) {
            throw new Exception('Course name must be at least 3 characters long');
        }
        
        if (empty($level)) {
            throw new Exception('Please select a level');
        }
        
        // Validate level
        $valid_levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D1', 'D2'];
        if (!in_array($level, $valid_levels)) {
            throw new Exception('Invalid level selected');
        }
        
        // Get existing course data
        $sql = "SELECT course_image FROM courses WHERE course_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        
        $course_image = $existing['course_image'] ?? '';
        $course_image_type = '';
        $upload_dir = '../../../uploads/courses/';
        
        // Handle image upload
        if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] == 0) {
            $allowed_image = ['jpg', 'jpeg', 'png'];
            $file_ext = strtolower(pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_image)) {
                // Delete old image
                if ($course_image && file_exists($upload_dir . $course_image)) {
                    unlink($upload_dir . $course_image);
                }
                $course_image = 'course_' . time() . '_' . uniqid() . '.' . $file_ext;
                $course_image_type = 'image/' . ($file_ext === 'jpg' ? 'jpeg' : $file_ext);
                move_uploaded_file($_FILES['course_image']['tmp_name'], $upload_dir . $course_image);
            }
        } else {
            // Keep existing image type
            $sql = "SELECT course_image_type FROM courses WHERE course_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $course_image_type = $row['course_image_type'] ?? '';
        }
        
        $sql = "UPDATE courses SET course_category_id=?, course_name=?, course_subtitle=?, course_overview=?, course_outcomes=?, level=?, course_image=?, course_image_type=?, is_active=?, display_order=?, updated_at=NOW() WHERE course_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("isssssssiiii", $course_category_id, $course_name, $course_subtitle, $course_overview, $course_outcomes, $level, $course_image, $course_image_type, $is_active, $display_order, $course_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
        } else {
            throw new Exception('Error updating course: ' . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Update Course Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteCourse($conn) {
    $course_id = $_POST['course_id'] ?? 0;
    
    // Get file paths before deleting
    $sql = "SELECT course_image FROM courses WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    
    $sql = "DELETE FROM courses WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    
    if ($stmt->execute()) {
        // Delete files
        $upload_dir = '../../../uploads/courses/';
        if ($course['course_image'] && file_exists($upload_dir . $course['course_image'])) {
            unlink($upload_dir . $course['course_image']);
        }
        echo json_encode(['success' => true, 'message' => 'Course deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting course: ' . $conn->error]);
    }
}

function getCategories($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'course_categories'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    // For admin interface, show all categories (both active and inactive)
    $sql = "SELECT category_id, category_name, 
                   '' as category_description,
                   category_order as display_order, 
                   1 as is_active, 
                   created_at
            FROM course_categories 
            ORDER BY category_order ASC, category_name ASC";
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



function addCategory($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'course_categories'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    $category_name = $_POST['category_name'] ?? '';
    $category_description = $_POST['category_description'] ?? '';
    $display_order = intval($_POST['display_order'] ?? 0);
    
    if (empty($category_name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        return;
    }
    
    try {
        // Use the actual table structure with category_order instead of display_order
        $sql = "INSERT INTO course_categories (category_name, category_order) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $category_name, $display_order);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Category added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding category: ' . $conn->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function updateCategory($conn) {
    $category_id = intval($_POST['category_id'] ?? 0);
    $category_name = $_POST['category_name'] ?? '';
    $category_description = $_POST['category_description'] ?? '';
    $display_order = intval($_POST['display_order'] ?? 0);
    
    try {
        // Use the actual table structure with category_order instead of display_order
        $sql = "UPDATE course_categories SET category_name=?, category_order=? WHERE category_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $category_name, $display_order, $category_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating category: ' . $conn->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function deleteCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "DELETE FROM course_categories WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $conn->error]);
    }
}

function getCoursesByCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT c.*, cc.category_name 
            FROM courses c 
            LEFT JOIN course_categories cc ON c.course_category_id = cc.category_id 
            WHERE c.course_category_id = ? 
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

function duplicateCourse($conn) {
    $course_id = $_POST['course_id'] ?? 0;
    
    // Get original course
    $sql = "SELECT * FROM courses WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Insert duplicate with modified name
        $new_course_name = $row['course_name'] . ' (Copy)';
        
        $sql = "INSERT INTO courses (course_category_id, course_name, course_subtitle, course_overview, course_outcomes, course_image, course_image_type, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssiii", 
            $row['course_category_id'], 
            $new_course_name, 
            $row['course_subtitle'], 
            $row['course_overview'], 
            $row['course_outcomes'], 
            $row['course_image'], 
            $row['course_image_type'], 
            $row['is_active'],
            $row['display_order']
        );
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Course duplicated successfully', 'new_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error duplicating course: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Original course not found']);
    }
}

function getCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT category_id, category_name, 
                   '' as category_description,
                   category_order as display_order, 
                   1 as is_active, 
                   created_at 
            FROM course_categories 
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

// ==================== QUIZ FUNCTIONS ====================

function getQuizzes($conn) {
    $course_id = $_POST['course_id'] ?? 0;
    
    $sql = "SELECT q.*, c.course_name, c.level as course_level 
            FROM quiz q 
            LEFT JOIN courses c ON q.course_id = c.course_id 
            WHERE q.course_id = ? 
            ORDER BY q.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $quizzes = [];
    while ($row = $result->fetch_assoc()) {
        $quizzes[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $quizzes]);
}

function getQuiz($conn) {
    $quiz_id = $_POST['quiz_id'] ?? 0;
    
    $sql = "SELECT * FROM quiz WHERE quiz_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Quiz not found']);
    }
}

function addQuiz($conn) {
    try {
        $course_id = filter_var($_POST['course_id'] ?? 0, FILTER_VALIDATE_INT);
        $quiz_name = trim($_POST['quiz_name'] ?? '');
        $quiz_description = trim($_POST['quiz_description'] ?? '');
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        if (!$course_id || $course_id <= 0) {
            throw new Exception('Invalid course ID');
        }
        
        if (empty($quiz_name)) {
            throw new Exception('Quiz name is required');
        }
        
        // Handle image upload
        $quiz_image = '';
        if (isset($_FILES['quiz_image']) && $_FILES['quiz_image']['error'] == 0) {
            $upload_dir = '../../../uploads/quizzes/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_image = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['quiz_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_image)) {
                $quiz_image = 'quiz_' . time() . '_' . uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['quiz_image']['tmp_name'], $upload_dir . $quiz_image);
            }
        }
        
        $sql = "INSERT INTO quiz (course_id, quiz_name, quiz_description, quiz_image, is_active) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("isssi", $course_id, $quiz_name, $quiz_description, $quiz_image, $is_active);
        
        if (!$stmt->execute()) {
            throw new Exception('Database insert failed: ' . $stmt->error);
        }
        
        echo json_encode(['success' => true, 'message' => 'Quiz added successfully', 'id' => $conn->insert_id]);
        
    } catch (Exception $e) {
        error_log("Add Quiz Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateQuiz($conn) {
    try {
        $quiz_id = filter_var($_POST['quiz_id'] ?? 0, FILTER_VALIDATE_INT);
        $quiz_name = trim($_POST['quiz_name'] ?? '');
        $quiz_description = trim($_POST['quiz_description'] ?? '');
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        if (!$quiz_id || $quiz_id <= 0) {
            throw new Exception('Invalid quiz ID');
        }
        
        if (empty($quiz_name)) {
            throw new Exception('Quiz name is required');
        }
        
        // Get existing image
        $sql = "SELECT quiz_image FROM quiz WHERE quiz_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $quiz_image = $existing['quiz_image'] ?? '';
        
        // Handle new image upload
        if (isset($_FILES['quiz_image']) && $_FILES['quiz_image']['error'] == 0) {
            $upload_dir = '../../../uploads/quizzes/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_image = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['quiz_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_image)) {
                // Delete old image
                if ($quiz_image && file_exists($upload_dir . $quiz_image)) {
                    unlink($upload_dir . $quiz_image);
                }
                $quiz_image = 'quiz_' . time() . '_' . uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['quiz_image']['tmp_name'], $upload_dir . $quiz_image);
            }
        }
        
        $sql = "UPDATE quiz SET quiz_name=?, quiz_description=?, quiz_image=?, is_active=?, updated_at=NOW() WHERE quiz_id=?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("sssii", $quiz_name, $quiz_description, $quiz_image, $is_active, $quiz_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Database update failed: ' . $stmt->error);
        }
        
        echo json_encode(['success' => true, 'message' => 'Quiz updated successfully']);
        
    } catch (Exception $e) {
        error_log("Update Quiz Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteQuiz($conn) {
    $quiz_id = $_POST['quiz_id'] ?? 0;
    
    // Get image file before deleting
    $sql = "SELECT quiz_image FROM quiz WHERE quiz_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $quiz = $result->fetch_assoc();
    
    $sql = "DELETE FROM quiz WHERE quiz_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $quiz_id);
    
    if ($stmt->execute()) {
        // Delete image file if exists
        if ($quiz['quiz_image'] && file_exists('../../../uploads/quizzes/' . $quiz['quiz_image'])) {
            unlink('../../../uploads/quizzes/' . $quiz['quiz_image']);
        }
        echo json_encode(['success' => true, 'message' => 'Quiz deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting quiz: ' . $conn->error]);
    }
}

// ==================== QUIZ QUESTION FUNCTIONS ====================
function getQuizQuestions($conn) {
    $quiz_id = $_POST['quiz_id'] ?? 0;
    
    $sql = "SELECT * FROM quiz_question WHERE quiz_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $questions]);
}

function addQuizQuestion($conn) {
    try {
        $quiz_id = filter_var($_POST['quiz_id'] ?? 0, FILTER_VALIDATE_INT);
        $question_text = trim($_POST['question_text'] ?? '');
        $option_a = trim($_POST['option_a'] ?? '');
        $option_b = trim($_POST['option_b'] ?? '');
        $option_c = trim($_POST['option_c'] ?? '');
        $option_d = trim($_POST['option_d'] ?? '');
        $correct_answer = $_POST['correct_answer'] ?? '';
        $points = filter_var($_POST['points'] ?? 10, FILTER_VALIDATE_INT);
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        if (!$quiz_id || $quiz_id <= 0) {
            throw new Exception('Invalid quiz ID');
        }
        
        if (empty($question_text)) {
            throw new Exception('Question text is required');
        }
        
        if (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
            throw new Exception('All options are required');
        }
        
        if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
            throw new Exception('Please select a valid correct answer');
        }
        
        $sql = "INSERT INTO quiz_question (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("issssssii", $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $points, $is_active);
        
        if (!$stmt->execute()) {
            throw new Exception('Database insert failed: ' . $stmt->error);
        }
        
        echo json_encode(['success' => true, 'message' => 'Quiz question added successfully', 'id' => $conn->insert_id]);
        
    } catch (Exception $e) {
        error_log("Add Quiz Question Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateQuizQuestion($conn) {
    try {
        $question_id = filter_var($_POST['question_id'] ?? 0, FILTER_VALIDATE_INT);
        $question_text = trim($_POST['question_text'] ?? '');
        $option_a = trim($_POST['option_a'] ?? '');
        $option_b = trim($_POST['option_b'] ?? '');
        $option_c = trim($_POST['option_c'] ?? '');
        $option_d = trim($_POST['option_d'] ?? '');
        $correct_answer = $_POST['correct_answer'] ?? '';
        $points = filter_var($_POST['points'] ?? 10, FILTER_VALIDATE_INT);
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        if (!$question_id || $question_id <= 0) {
            throw new Exception('Invalid question ID');
        }
        
        if (empty($question_text)) {
            throw new Exception('Question text is required');
        }
        
        if (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
            throw new Exception('All options are required');
        }
        
        if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
            throw new Exception('Please select a valid correct answer');
        }
        
        $sql = "UPDATE quiz_question SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, points=?, is_active=?, updated_at=NOW() WHERE question_id=?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("ssssssiii", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $points, $is_active, $question_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Database update failed: ' . $stmt->error);
        }
        
        echo json_encode(['success' => true, 'message' => 'Quiz question updated successfully']);
        
    } catch (Exception $e) {
        error_log("Update Quiz Question Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteQuizQuestion($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    $sql = "DELETE FROM quiz_question WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Quiz question deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting quiz question: ' . $conn->error]);
    }
}

function getQuizQuestion($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    $sql = "SELECT * FROM quiz_question WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Quiz question not found']);
    }
}

function importQuizQuestions($conn) {
    try {
        $quiz_id = filter_var($_POST['quiz_id'] ?? 0, FILTER_VALIDATE_INT);
        
        if (!$quiz_id || $quiz_id <= 0) {
            throw new Exception('Invalid quiz ID');
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
            throw new Exception('Please upload a valid CSV file');
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $file_extension = pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION);
     
 if (strtolower($file_extension) !== 'csv') {
            throw new Exception('Only CSV files are allowed');
        }
        
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        if (($handle = fopen($file, "r")) !== FALSE) {
            $row_number = 0;
            
            // Skip header row
            $header = fgetcsv($handle, 10000, ",");
            
            while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                $row_number++;
                
                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }
                
                // Expected CSV format: Question, Option A, Option B, Option C, Option D, Correct Answer, Points
                $question_text = isset($data[0]) ? trim($data[0]) : '';
                $option_a = isset($data[1]) ? trim($data[1]) : '';
                $option_b = isset($data[2]) ? trim($data[2]) : '';
                $option_c = isset($data[3]) ? trim($data[3]) : '';
                $option_d = isset($data[4]) ? trim($data[4]) : '';
                $correct_answer = isset($data[5]) ? strtoupper(trim($data[5])) : '';
                $points = isset($data[6]) ? intval($data[6]) : 10;
                
                // Validate required fields
                $missing_fields = [];
                if (empty($question_text)) $missing_fields[] = 'question';
                if (empty($option_a)) $missing_fields[] = 'option_a';
                if (empty($option_b)) $missing_fields[] = 'option_b';
                if (empty($option_c)) $missing_fields[] = 'option_c';
                if (empty($option_d)) $missing_fields[] = 'option_d';
                if (empty($correct_answer)) $missing_fields[] = 'correct_answer';
                
                if (!empty($missing_fields)) {
                    $skipped++;
                    $errors[] = "Row " . ($row_number + 1) . ": Missing " . implode(', ', $missing_fields);
                    continue;
                }
                
                // Validate correct answer
                if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
                    $skipped++;
                    $errors[] = "Row " . ($row_number + 1) . ": Invalid correct answer '$correct_answer'. Must be A, B, C, or D";
                    continue;
                }
                
                // Validate points
                if ($points <= 0) {
                    $points = 10; // Default points
                }
                
                // Insert question
                $sql = "INSERT INTO quiz_question (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    $skipped++;
                    $errors[] = "Row " . ($row_number + 1) . ": Database prepare failed";
                    continue;
                }
                
                $stmt->bind_param("issssssi", $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $points);
                
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $skipped++;
                    $errors[] = "Row " . ($row_number + 1) . ": " . $stmt->error;
                }
            }
            
            fclose($handle);
        }
        
        $message = "Import completed: $imported questions imported, $skipped skipped";
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function saveQuizQuestion($conn, $quiz_id, $question_text, $options, $correct_answer) {
    try {
        if (empty($question_text)) {
            return ['success' => false, 'error' => 'Question text is empty'];
        }
        
        if (count($options) < 4) {
            return ['success' => false, 'error' => 'Not enough options provided'];
        }
        
        if (empty($correct_answer) || !isset($options[$correct_answer])) {
            return ['success' => false, 'error' => 'Invalid correct answer'];
        }
        
        $option_a = $options['A'] ?? '';
        $option_b = $options['B'] ?? '';
        $option_c = $options['C'] ?? '';
        $option_d = $options['D'] ?? '';
        
        $sql = "INSERT INTO quiz_question (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 10, 1)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            return ['success' => false, 'error' => 'Database prepare failed: ' . $conn->error];
        }
        
        $stmt->bind_param("issssss", $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Database insert failed: ' . $stmt->error];
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
function uploadCourseQuizImage($conn) {
    try {
        $course_id = filter_var($_POST['course_id'] ?? 0, FILTER_VALIDATE_INT);
        
        if (!$course_id || $course_id <= 0) {
            throw new Exception('Invalid course ID');
        }
        
        // Check if course exists
        $sql = "SELECT course_id, quize_image FROM courses WHERE course_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Course not found');
        }
        
        $course = $result->fetch_assoc();
        $old_quize_image = $course['quize_image'] ?? '';
        
        // Handle file upload
        if (!isset($_FILES['quize_image']) || $_FILES['quize_image']['error'] !== 0) {
            throw new Exception('Please select a valid image file');
        }
        
        $upload_dir = '../../../uploads/quizzes/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed_image = ['jpg', 'jpeg', 'png', 'gif'];
        $file_ext = strtolower(pathinfo($_FILES['quize_image']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_image)) {
            throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed');
        }
        
        // Generate unique filename
        $quize_image = 'quiz_' . time() . '_' . uniqid() . '.' . $file_ext;
        
        // Upload new image
        if (!move_uploaded_file($_FILES['quize_image']['tmp_name'], $upload_dir . $quize_image)) {
            throw new Exception('Failed to upload image file');
        }
        
        // Update database
        $sql = "UPDATE courses SET quize_image = ?, updated_at = NOW() WHERE course_id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            // Delete uploaded file if database update fails
            if (file_exists($upload_dir . $quize_image)) {
                unlink($upload_dir . $quize_image);
            }
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("si", $quize_image, $course_id);
        
        if (!$stmt->execute()) {
            // Delete uploaded file if database update fails
            if (file_exists($upload_dir . $quize_image)) {
                unlink($upload_dir . $quize_image);
            }
            throw new Exception('Failed to update database: ' . $stmt->error);
        }
        
        // Delete old image if it exists
        if ($old_quize_image && file_exists($upload_dir . $old_quize_image)) {
            unlink($upload_dir . $old_quize_image);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Quiz image uploaded successfully',
            'filename' => $quize_image
        ]);
        
    } catch (Exception $e) {
        error_log("Upload Course Quiz Image Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteCourseQuizImage($conn) {
    try {
        $course_id = filter_var($_POST['course_id'] ?? 0, FILTER_VALIDATE_INT);
        
        if (!$course_id || $course_id <= 0) {
            throw new Exception('Invalid course ID');
        }
        
        // Get existing quiz image
        $sql = "SELECT quize_image FROM courses WHERE course_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Course not found');
        }
        
        $course = $result->fetch_assoc();
        $quize_image = $course['quize_image'] ?? '';
        
        if (empty($quize_image)) {
            throw new Exception('No quiz image to delete');
        }
        
        // Update database to remove quize_image
        $sql = "UPDATE courses SET quize_image = NULL, updated_at = NOW() WHERE course_id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $course_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update database: ' . $stmt->error);
        }
        
        // Delete the physical file
        $upload_dir = '../../../uploads/quizzes/';
        if (file_exists($upload_dir . $quize_image)) {
            unlink($upload_dir . $quize_image);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Quiz image deleted successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Delete Course Quiz Image Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ==================== ASSESSMENT FUNCTIONS ====================
function getAssessments($conn) {
    // First check if course_id column exists in assessments table
    $checkColumn = $conn->query("SHOW COLUMNS FROM assessments LIKE 'course_id'");
    
    if ($checkColumn->num_rows == 0) {
        // Add course_id column if it doesn't exist
        $conn->query("ALTER TABLE assessments ADD COLUMN course_id INT(11) NULL AFTER assessment_id");
        $conn->query("ALTER TABLE assessments ADD FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE SET NULL");
    }
    
    $course_id = $_POST['course_id'] ?? 0;
    
    if ($course_id) {
        $sql = "SELECT a.*, c.course_name, c.level as course_level 
                FROM assessments a 
                LEFT JOIN courses c ON a.course_id = c.course_id 
                WHERE a.course_id = ? 
                ORDER BY a.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = "SELECT a.*, c.course_name, c.level as course_level 
                FROM assessments a 
                LEFT JOIN courses c ON a.course_id = c.course_id 
                ORDER BY a.created_at DESC";
        $result = $conn->query($sql);
    }
    
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function getAssessment($conn) {
    $assessment_id = $_POST['assessment_id'] ?? 0;
    
    $sql = "SELECT * FROM assessments WHERE assessment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Assessment not found']);
    }
}

function addAssessment($conn) {
    try {
        // Debug: Log received POST data
        error_log("addAssessment - Received POST data: " . print_r($_POST, true));
        
        $course_id = $_POST['course_id'] ?? 0;
        $assessment_name = trim($_POST['assessment_name'] ?? '');
        $assessment_description = trim($_POST['assessment_description'] ?? '');
        $is_active = intval($_POST['is_active'] ?? 1);
         
        if (!$course_id || $course_id <= 0) {
            throw new Exception('Invalid course ID - received: ' . ($_POST['course_id'] ?? 'null'));
        }
        
        if (empty($assessment_name)) {
            throw new Exception('Assessment name is required');
        }
        
        // Get the level from the course
        $sql = "SELECT level FROM courses WHERE course_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $course = $result->fetch_assoc();
        
        if (!$course) {
            throw new Exception('Course not found');
        }
        
        $level = $course['level'];
        
        // Handle image upload
        $assessment_image = '';
        if (isset($_FILES['assessment_image']) && $_FILES['assessment_image']['error'] == 0) {
            $upload_dir = '../../../uploads/assessments/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_image = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['assessment_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_image)) {
                $assessment_image = 'assessment_' . time() . '_' . uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['assessment_image']['tmp_name'], $upload_dir . $assessment_image);
            }
        }
        
        $sql = "INSERT INTO assessments (course_id, assessment_name, assessment_description, assessment_image, level, is_active) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssi", $course_id, $assessment_name, $assessment_description, $assessment_image, $level, $is_active);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Assessment added successfully', 'id' => $conn->insert_id]);
        } else {
            throw new Exception('Error adding assessment: ' . $conn->error);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateAssessment($conn) {
    try {
        $assessment_id = intval($_POST['assessment_id'] ?? 0);
        $course_id = filter_var($_POST['course_id'] ?? 0, FILTER_VALIDATE_INT);
        $assessment_name = trim($_POST['assessment_name'] ?? '');
        $assessment_description = trim($_POST['assessment_description'] ?? '');
        $is_active = intval($_POST['is_active'] ?? 1);
        
        if (!$course_id || $course_id <= 0) {
            throw new Exception('Invalid course ID');
        }
        
        if (empty($assessment_name)) {
            throw new Exception('Assessment name is required');
        }
        
        // Get the level from the course
        $sql = "SELECT level FROM courses WHERE course_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $course = $result->fetch_assoc();
        
        if (!$course) {
            throw new Exception('Course not found');
        }
        
        $level = $course['level'];
        
        // Get existing image
        $sql = "SELECT assessment_image FROM assessments WHERE assessment_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $assessment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $assessment_image = $existing['assessment_image'] ?? '';
        
        // Handle new image upload
        if (isset($_FILES['assessment_image']) && $_FILES['assessment_image']['error'] == 0) {
            $upload_dir = '../../../uploads/assessments/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_image = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['assessment_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_image)) {
                // Delete old image
                if ($assessment_image && file_exists($upload_dir . $assessment_image)) {
                    unlink($upload_dir . $assessment_image);
                }
                $assessment_image = 'assessment_' . time() . '_' . uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['assessment_image']['tmp_name'], $upload_dir . $assessment_image);
            }
        }
        
        $sql = "UPDATE assessments SET course_id=?, assessment_name=?, assessment_description=?, assessment_image=?, level=?, is_active=? WHERE assessment_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssiii", $course_id, $assessment_name, $assessment_description, $assessment_image, $level, $is_active, $assessment_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Assessment updated successfully']);
        } else {
            throw new Exception('Error updating assessment: ' . $conn->error);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteAssessment($conn) {
    $assessment_id = $_POST['assessment_id'] ?? 0;
    
    // Get image file
    $sql = "SELECT assessment_image FROM assessments WHERE assessment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $sql = "DELETE FROM assessments WHERE assessment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $assessment_id);
    
    if ($stmt->execute()) {
        // Delete image file
        if ($row['assessment_image'] && file_exists('../../../uploads/assessments/' . $row['assessment_image'])) {
            unlink('../../../uploads/assessments/' . $row['assessment_image']);
        }
        echo json_encode(['success' => true, 'message' => 'Assessment deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting assessment: ' . $conn->error]);
    }
}

function getAssessmentQuestions($conn) {
    $assessment_id = $_POST['assessment_id'] ?? 0;
    
    $sql = "SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $questions]);
}

function addAssessmentQuestion($conn) {
    try {
        $assessment_id = filter_var($_POST['assessment_id'] ?? 0, FILTER_VALIDATE_INT);
        $question_text = trim($_POST['question_text'] ?? '');
        $option_a = trim($_POST['option_a'] ?? '');
        $option_b = trim($_POST['option_b'] ?? '');
        $option_c = trim($_POST['option_c'] ?? '');
        $option_d = trim($_POST['option_d'] ?? '');
        $correct_option = $_POST['correct_option'] ?? '';
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        if (!$assessment_id || $assessment_id <= 0) {
            throw new Exception('Invalid assessment ID');
        }
        
        if (empty($question_text)) {
            throw new Exception('Question text is required');
        }
        
        if (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
            throw new Exception('All options are required');
        }
        
        if (!in_array($correct_option, ['A', 'B', 'C', 'D'])) {
            throw new Exception('Please select a valid correct option');
        }
        
        $sql = "INSERT INTO assessment_questions (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("issssssi", $assessment_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $is_active);
        
        if (!$stmt->execute()) {
            throw new Exception('Database insert failed: ' . $stmt->error);
        }
        
        echo json_encode(['success' => true, 'message' => 'Assessment question added successfully', 'id' => $conn->insert_id]);
        
    } catch (Exception $e) {
        error_log("Add Assessment Question Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateAssessmentQuestion($conn) {
    try {
        $question_id = filter_var($_POST['question_id'] ?? 0, FILTER_VALIDATE_INT);
        $question_text = trim($_POST['question_text'] ?? '');
        $option_a = trim($_POST['option_a'] ?? '');
        $option_b = trim($_POST['option_b'] ?? '');
        $option_c = trim($_POST['option_c'] ?? '');
        $option_d = trim($_POST['option_d'] ?? '');
        $correct_option = $_POST['correct_option'] ?? '';
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        if (!$question_id || $question_id <= 0) {
            throw new Exception('Invalid question ID');
        }
        
        if (empty($question_text)) {
            throw new Exception('Question text is required');
        }
        
        if (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
            throw new Exception('All options are required');
        }
        
        if (!in_array($correct_option, ['A', 'B', 'C', 'D'])) {
            throw new Exception('Please select a valid correct option');
        }
        
        $sql = "UPDATE assessment_questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=?, is_active=? WHERE question_id=?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("ssssssii", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $is_active, $question_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Database update failed: ' . $stmt->error);
        }
        
        echo json_encode(['success' => true, 'message' => 'Assessment question updated successfully']);
        
    } catch (Exception $e) {
        error_log("Update Assessment Question Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteAssessmentQuestion($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    $sql = "DELETE FROM assessment_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Assessment question deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting assessment question: ' . $conn->error]);
    }
}

function getAssessmentQuestion($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    $sql = "SELECT * FROM assessment_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Assessment question not found']);
    }
}

?>

<?php include '../layout/Header.php'; ?>
 
<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">Courses Management</h5>
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

<!-- Top Action Buttons - Courses View -->
<div class="row mb-3" id="coursesActionButtons" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <button class="btn btn-primary me-2" onclick="showQuickAddPanelForCategory()">
                            <i class="ri-add-line"></i> Add Course
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
                <h6 class="mb-0"><i class="ri-lightning-line"></i> Quick Add Course</h6>
                <button class="btn btn-sm btn-outline-light" onclick="hideQuickAddPanel()">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="card-body">
                <form id="quickAddForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" id="quick_course_category_id" name="course_category_id" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Level *</label>
                                <select class="form-select" id="quick_level" name="level" required>
                                    <option value="">Select Level</option>
                                    <option value="A1">A1</option>
                                    <option value="A2">A2</option>
                                    <option value="B1">B1</option>
                                    <option value="B2">B2</option>
                                    <option value="C1">C1</option>
                                    <option value="C2">C2</option>
                                    <option value="D1">D1</option>
                                    <option value="D2">D2</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Course Image</label>
                                <input type="file" class="form-control" id="quick_course_image" name="course_image" accept="image/*">
                                <small class="text-muted">JPG, PNG only</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Course Name *</label>
                        <input type="text" class="form-control" id="quick_course_name" name="course_name" required placeholder="Enter course name...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Course Subtitle</label>
                        <input type="text" class="form-control" id="quick_course_subtitle" name="course_subtitle" placeholder="Enter course subtitle...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Course Overview</label>
                        <textarea class="form-control" id="quick_course_overview" name="course_overview" rows="3" placeholder="Enter course overview..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Course Outcomes</label>
                        <textarea class="form-control" id="quick_course_outcomes" name="course_outcomes" rows="3" placeholder="Enter course outcomes..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="quick_display_order" name="display_order" value="0" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" id="quick_is_active" name="is_active" value="1" checked>
                                    <label class="form-check-label" for="quick_is_active">
                                        Active
                                    </label>
                                </div>
                            </div>
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

<!-- Category Courses View (Initially Hidden) -->
<div class="row mb-3" id="categoryCoursesView" style="display: none;">
    <div class="col-12">
        <div class="card border-success">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="categoryCoursesTable" class="table table-striped table-bordered w-100">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Course Name</th>
                                <th>Subtitle</th>
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

<!-- Courses Table (Initially Hidden) -->
<div class="row" id="coursesTableView" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="coursesTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Course Name</th>
                                <th>Category</th>
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

    <!-- Edit Course Modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1" aria-labelledby="editCourseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCourseModalLabel">Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editCourseForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="edit_course_id" name="course_id">
                        <input type="hidden" name="action" value="update_course">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_course_category_id" class="form-label">Category *</label>
                                    <select class="form-select" id="edit_course_category_id" name="course_category_id" required>
                                        <option value="">Select Category</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_level" class="form-label">Level *</label>
                                    <select class="form-select" id="edit_level" name="level" required>
                                        <option value="">Select Level</option>
                                        <option value="A1">A1</option>
                                        <option value="A2">A2</option>
                                        <option value="B1">B1</option>
                                        <option value="B2">B2</option>
                                        <option value="C1">C1</option>
                                        <option value="C2">C2</option>
                                        <option value="D1">D1</option>
                                        <option value="D2">D2</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_course_image" class="form-label">Course Image</label>
                                    <input type="file" class="form-control" id="edit_course_image" name="course_image" accept="image/*">
                                    <small class="text-muted">Leave empty to keep current image</small>
                                    <div id="current_course_image"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_course_name" class="form-label">Course Name *</label>
                            <input type="text" class="form-control" id="edit_course_name" name="course_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_course_subtitle" class="form-label">Course Subtitle</label>
                            <input type="text" class="form-control" id="edit_course_subtitle" name="course_subtitle">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_course_overview" class="form-label">Course Overview</label>
                            <textarea class="form-control" id="edit_course_overview" name="course_overview" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_course_outcomes" class="form-label">Course Outcomes</label>
                            <textarea class="form-control" id="edit_course_outcomes" name="course_outcomes" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_display_order" class="form-label">Display Order</label>
                                    <input type="number" class="form-control" id="edit_display_order" name="display_order" value="0" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                                        <label class="form-check-label" for="edit_is_active">
                                            Active
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="updateCourseBtn">
                            <span class="btn-text">Update Course</span>
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
                        
                        <div class="mb-3">
                            <label for="display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control category-input" id="display_order" name="display_order" value="0" autocomplete="off">
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
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true" style="z-index: 1060;">
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

    <!-- Manage Quizzes Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="manageQuizzesModal" aria-labelledby="manageQuizzesModalLabel" style="width: 50%; max-width: 1200px;">
        <div class="offcanvas-header bg-light border-bottom">
            <h5 class="offcanvas-title text-dark" id="manageQuizzesModalLabel">
                <i class="ri-questionnaire-line"></i> Manage Quizzes - <span id="quizzesCourseName"></span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <input type="hidden" id="current_quiz_course_id">
            
            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-primary" onclick="showAddQuizModal()">
                        <i class="ri-add-line"></i> Add Quiz
                    </button>
                </div>
            </div>
            
            <!-- Quizzes List -->
            <div id="quizzesList" class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="5%">ID</th>
                            <th width="25%">Quiz Name</th>
                            <th width="30%">Description</th>
                            <th width="10%">Image</th>
                            <th width="10%">Status</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="quizzesTableBody">
                        <!-- Quizzes will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>



    <!-- Manage Quiz Questions Modal -->
    <div class="modal fade" id="manageQuizQuestionsModal" tabindex="-1" aria-labelledby="manageQuizQuestionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="manageQuizQuestionsModalLabel">
                        <i class="ri-question-line"></i> Manage Questions - <span id="quizQuestionsName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="current_quiz_id">
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary" onclick="showQuizQuestionQuickAddPanel()">
                                <i class="ri-add-line"></i> Add Question
                            </button>
                            <button class="btn btn-sm btn-success" onclick="openQuizImportModal()">
                                <i class="ri-upload-line"></i> Import CSV
                            </button>
                        </div>
                    </div>
                    
                    <!-- Quick Add Question Panel (Initially Hidden) -->
                    <div id="quizQuestionQuickAddPanel" style="display: none;" class="mb-3">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="ri-lightning-line"></i> Quick Add Question</h6>
                                <button class="btn btn-sm btn-outline-light" onclick="hideQuizQuestionQuickAddPanel()">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <form id="quizQuestionQuickAddForm">
                                    <input type="hidden" id="question_quiz_id" name="quiz_id">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Question Text *</label>
                                        <textarea class="form-control" id="quick_quiz_question_text" name="question_text" rows="2" required placeholder="Enter your question..."></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Option A *</label>
                                                <input type="text" class="form-control" id="quick_quiz_option_a" name="option_a" required placeholder="Option A">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Option B *</label>
                                                <input type="text" class="form-control" id="quick_quiz_option_b" name="option_b" required placeholder="Option B">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Option C *</label>
                                                <input type="text" class="form-control" id="quick_quiz_option_c" name="option_c" required placeholder="Option C">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Option D *</label>
                                                <input type="text" class="form-control" id="quick_quiz_option_d" name="option_d" required placeholder="Option D">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Correct Answer *</label>
                                                <select class="form-select" id="quick_quiz_correct_answer" name="correct_answer" required>
                                                    <option value="">Select Correct Answer</option>
                                                    <option value="A">A</option>
                                                    <option value="B">B</option>
                                                    <option value="C">C</option>
                                                    <option value="D">D</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Points</label>
                                                <input type="number" class="form-control" id="quick_quiz_question_points" name="points" value="10" min="1">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label d-block">Status</label>
                                                <div class="form-check form-switch mt-2">
                                                    <input class="form-check-input" type="checkbox" id="quick_quiz_question_is_active" name="is_active" value="1" checked>
                                                    <label class="form-check-label" for="quick_quiz_question_is_active">Active</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" class="btn btn-success" id="addQuizQuestionContinueBtn">
                                            <span class="btn-text">
                                                <i class="ri-add-line"></i> Add & Continue
                                            </span>
                                            <span class="btn-loading" style="display: none;">
                                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                                Processing...
                                            </span>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearQuizQuestionQuickForm()">
                                            <i class="ri-refresh-line"></i> Clear Form
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Questions List -->
                    <div id="quizQuestionsList" class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="5%">ID</th>
                                    <th width="40%">Question</th>
                                    <th width="15%">Correct Answer</th>
                                    <th width="10%">Points</th>
                                    <th width="10%">Status</th>
                                    <th width="20%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="quizQuestionsTableBody">
                                <!-- Questions will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Add/Edit Quiz Modal -->
    <div class="modal fade" id="quizModal" tabindex="-1" aria-labelledby="quizModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quizModalLabel">Add Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="quizForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="quiz_id" name="quiz_id">
                        <input type="hidden" id="quiz_course_id" name="course_id">
                        <input type="hidden" name="action" id="quizAction" value="add_quiz">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="quiz_name" class="form-label">Quiz Name *</label>
                                    <input type="text" class="form-control" id="quiz_name" name="quiz_name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Course</label>
                                    <input type="text" class="form-control" id="quiz_course_display" readonly style="background-color: #f8f9fa;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="quiz_description" class="form-label">Description</label>
                            <textarea class="form-control" id="quiz_description" name="quiz_description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="quiz_image" class="form-label">Quiz Image</label>
                                    <input type="file" class="form-control" id="quiz_image" name="quiz_image" accept="image/*" onchange="previewQuizImage(this)">
                                    <small class="text-muted">JPG, PNG, GIF only</small>
                                    <div id="quiz_image_preview" class="mt-2"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label d-block">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="quiz_is_active" name="is_active" value="1" checked>
                                        <label class="form-check-label" for="quiz_is_active">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveQuizBtn">
                            <span class="btn-text">Save Quiz</span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Saving...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Quiz Modal -->
    <div class="modal fade" id="editQuizModal" tabindex="-1" aria-labelledby="editQuizModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editQuizModalLabel">Edit Quiz Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editQuizForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit_quiz_id" name="quiz_id">
                        <input type="hidden" id="edit_quiz_course_id" name="course_id">
                        <input type="hidden" name="action" value="update_quiz">
                        
                
                        
                        <div class="mb-3">
                            <label for="edit_question_text" class="form-label">Question Text *</label>
                            <textarea class="form-control" id="edit_question_text" name="question_text" rows="3" required></textarea>
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
                            <div class="col-md-4">
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
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_quiz_points" class="form-label">Points</label>
                                    <input type="number" class="form-control" id="edit_quiz_points" name="points" value="10" min="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label d-block">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="edit_quiz_is_active" name="is_active" value="1" checked>
                                        <label class="form-check-label" for="edit_quiz_is_active">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="updateQuizBtn">
                            <span class="btn-text">Update Question</span>
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

    <!-- Manage Assessments Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="manageAssessmentsModal" aria-labelledby="manageAssessmentsModalLabel" style="width: 50%; max-width: 1200px;">
        <div class="offcanvas-header bg-primary text-white border-bottom">
            <h5 class="offcanvas-title" id="manageAssessmentsModalLabel">
                <i class="ri-file-list-3-line"></i> Manage Assessments
            </h5>
            <button type="button" class="btn-close " data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-primary" onclick="showAddAssessmentModal()">
                        <i class="ri-add-line"></i> Add Assessment
                    </button>
                </div>
            </div>
            
            <!-- Assessments List -->
            <div id="assessmentsList" class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="5%">ID</th>
                            <th width="25%">Assessment Name</th>
                            <th width="20%">Description</th>
                            <th width="10%">Level</th>
                            <th width="10%">Image</th>
                            <th width="10%">Status</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="assessmentsTableBody">
                        <!-- Assessments will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Assessment Modal -->
    <div class="modal fade" id="assessmentModal" tabindex="-1" aria-labelledby="assessmentModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assessmentModalLabel">Add Assessment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="assessmentForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="assessment_id" name="assessment_id">
                        <input type="hidden" id="assessment_course_id" name="course_id">
                        <input type="hidden" name="action" id="assessmentAction" value="add_assessment">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="assessment_name" class="form-label">Assessment Name *</label>
                                    <input type="text" class="form-control" id="assessment_name" name="assessment_name" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Course</label>
                                    <input type="text" class="form-control" id="assessment_course_display" readonly style="background-color: #f8f9fa;">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Level</label>
                                    <input type="text" class="form-control" id="assessment_level_display" readonly style="background-color: #f8f9fa;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="assessment_description" class="form-label">Description</label>
                            <textarea class="form-control" id="assessment_description" name="assessment_description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="assessment_image" class="form-label">Assessment Image</label>
                                    <input type="file" class="form-control" id="assessment_image" name="assessment_image" accept="image/*" onchange="previewAssessmentImage(this)">
                                    <small class="text-muted">JPG, PNG, GIF only</small>
                                    <div id="assessment_image_preview" class="mt-2"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label d-block">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="assessment_is_active" name="is_active" value="1" checked>
                                        <label class="form-check-label" for="assessment_is_active">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveAssessmentBtn">
                            <span class="btn-text">Save Assessment</span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Saving...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Assessment Questions Modal -->
    <div class="modal fade" id="manageAssessmentQuestionsModal" tabindex="-1" aria-labelledby="manageAssessmentQuestionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h5 class="modal-title" id="manageAssessmentQuestionsModalLabel">
                        <i class="ri-question-line"></i> Manage Questions - <span id="assessmentQuestionsName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="current_assessment_id">
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary" onclick="showAssessmentQuestionQuickAddPanel()">
                                <i class="ri-add-line"></i> Add Question
                            </button>
                        </div>
                    </div>
                    
                    <!-- Quick Add Question Panel (Initially Hidden) -->
                    <div id="assessmentQuestionQuickAddPanel" style="display: none;" class="mb-3">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="ri-lightning-line"></i> Quick Add Question</h6>
                                <button class="btn btn-sm btn-outline-light" onclick="hideAssessmentQuestionQuickAddPanel()">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <form id="assessmentQuestionQuickAddForm">
                                    <input type="hidden" id="question_assessment_id" name="assessment_id">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Question Text *</label>
                                        <textarea class="form-control" id="quick_assessment_question_text" name="question_text" rows="2" required placeholder="Enter your question..."></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Option A *</label>
                                                <input type="text" class="form-control" id="quick_assessment_option_a" name="option_a" required placeholder="Option A">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Option B *</label>
                                                <input type="text" class="form-control" id="quick_assessment_option_b" name="option_b" required placeholder="Option B">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Option C *</label>
                                                <input type="text" class="form-control" id="quick_assessment_option_c" name="option_c" required placeholder="Option C">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Option D *</label>
                                                <input type="text" class="form-control" id="quick_assessment_option_d" name="option_d" required placeholder="Option D">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Correct Option *</label>
                                                <select class="form-select" id="quick_assessment_correct_option" name="correct_option" required>
                                                    <option value="">Select Correct Option</option>
                                                    <option value="A">A</option>
                                                    <option value="B">B</option>
                                                    <option value="C">C</option>
                                                    <option value="D">D</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label d-block">Status</label>
                                                <div class="form-check form-switch mt-2">
                                                    <input class="form-check-input" type="checkbox" id="quick_assessment_question_is_active" name="is_active" value="1" checked>
                                                    <label class="form-check-label" for="quick_assessment_question_is_active">Active</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" class="btn btn-success" id="addAssessmentQuestionContinueBtn">
                                            <span class="btn-text">
                                                <i class="ri-add-line"></i> Add & Continue
                                            </span>
                                            <span class="btn-loading" style="display: none;">
                                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                                Processing...
                                            </span>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearAssessmentQuestionQuickForm()">
                                            <i class="ri-refresh-line"></i> Clear Form
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Questions List -->
                    <div id="assessmentQuestionsList" class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="5%">ID</th>
                                    <th width="40%">Question</th>
                                    <th width="15%">Correct Option</th>
                                    <th width="10%">Status</th>
                                    <th width="30%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="assessmentQuestionsTableBody">
                                <!-- Questions will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Assessment Question Modal -->
    <div class="modal fade" id="editAssessmentQuestionModal" tabindex="-1" aria-labelledby="editAssessmentQuestionModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAssessmentQuestionModalLabel">Edit Assessment Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAssessmentQuestionForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit_assessment_question_id" name="question_id">
                        <input type="hidden" name="action" value="update_assessment_question">
                        
                        <div class="mb-3">
                            <label for="edit_assessment_question_text" class="form-label">Question Text *</label>
                            <textarea class="form-control" id="edit_assessment_question_text" name="question_text" rows="3" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_assessment_option_a" class="form-label">Option A *</label>
                                    <input type="text" class="form-control" id="edit_assessment_option_a" name="option_a" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_assessment_option_b" class="form-label">Option B *</label>
                                    <input type="text" class="form-control" id="edit_assessment_option_b" name="option_b" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_assessment_option_c" class="form-label">Option C *</label>
                                    <input type="text" class="form-control" id="edit_assessment_option_c" name="option_c" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_assessment_option_d" class="form-label">Option D *</label>
                                    <input type="text" class="form-control" id="edit_assessment_option_d" name="option_d" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_assessment_correct_option" class="form-label">Correct Option *</label>
                                    <select class="form-select" id="edit_assessment_correct_option" name="correct_option" required>
                                        <option value="">Select Option</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label d-block">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="edit_assessment_question_is_active" name="is_active" value="1" checked>
                                        <label class="form-check-label" for="edit_assessment_question_is_active">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="updateAssessmentQuestionBtn">
                            <span class="btn-text">Update Question</span>
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

    <!-- Quiz Questions Import Modal -->
    <div class="modal fade" id="quizImportModal" tabindex="-1" aria-labelledby="quizImportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quizImportModalLabel">
                        <i class="ri-upload-line me-2"></i>Import Quiz Questions from CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="quizImportForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="import_quiz_id" name="quiz_id">
                        
                        <div class="alert alert-info">
                            <h6><i class="ri-information-line me-2"></i>CSV Format Instructions</h6>
                            <p class="mb-2">Your CSV file should have the following columns (with header row):</p>
                            <ul class="mb-2">
                                <li><strong>Question:</strong> The question text</li>
                                <li><strong>Option A:</strong> First option</li>
                                <li><strong>Option B:</strong> Second option</li>
                                <li><strong>Option C:</strong> Third option</li>
                                <li><strong>Option D:</strong> Fourth option</li>
                                <li><strong>Correct Answer:</strong> A, B, C, or D</li>
                                <li><strong>Points:</strong> Points for the question (optional, default: 10)</li>
                            </ul>
                            <small class="text-muted">Example: "Which syllable is stressed?","pho","to","graph","o","A","10"</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">Select CSV File *</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            <small class="text-muted">Only CSV files are allowed</small>
                        </div>
                        
                        <div id="importResults" style="display: none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="importQuizQuestionsBtn" onclick="importQuizQuestions()">
                            <span class="btn-text">
                                <i class="ri-upload-line me-2"></i>Import Questions
                            </span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Importing...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Assessment specific styles */
        .assessment-card {
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
        }
        
        .assessment-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .assessment-image-preview {
            max-width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .question-options {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .option-badge {
            display: inline-block;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: #6c757d;
            color: white;
            text-align: center;
            line-height: 25px;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .option-badge.correct {
            background: #198754;
        }
        
        .btn-group-sm .btn {
            margin: 0 1px;
        }
        
        .modal-xl .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .badge {
            font-size: 0.75em;
        }
        
        .assessment-level-badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
        }

        .form-success {
            animation: formSuccess 0.6s ease-out;
        }
        
        @keyframes formSuccess {
            0% { background-color: transparent; }
            50% { background-color: rgba(25, 135, 84, 0.1); }
            100% { background-color: transparent; }
        }

        /* Modal z-index fixes */
        #deleteModal {
            z-index: 1060 !important;
        }
        
        #deleteModal .modal-backdrop {
            z-index: 1059 !important;
        }
        
        /* Ensure delete modal appears above other modals */
        .modal-backdrop.show {
            z-index: 1050;
        }
        
        #deleteModal.show {
            z-index: 1060 !important;
        }
    </style>
 
    <script>
        let coursesTable;
        let categoriesDataTable;
        let categoryCoursesTable;
        let deleteItemId = null;
        let deleteItemType = null;
        let currentSelectedCategoryId = null;

        $(document).ready(function() {
            // Prevent any form submissions that don't have specific handlers
            $('form').on('submit', function(e) {
                const formId = $(this).attr('id');
                const allowedForms = [
                    'editCourseForm', 'categoryForm', 'quickAddForm', 'quizForm', 
                    'quizQuestionQuickAddForm', 'editQuizForm', 'assessmentForm', 
                    'assessmentQuestionQuickAddForm', 'editAssessmentQuestionForm'
                ];
                
                if (!allowedForms.includes(formId)) {
                    console.log('Preventing form submission for:', formId);
                    e.preventDefault();
                    return false;
                }
            });
            
            // Initialize Categories DataTable first (show categories by default)
            initializeCategoriesDataTable();
            
            // Load categories for filters and quick add
            loadCategoriesForFilters();

            // Edit course form submission
            $('#editCourseForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('editCourseForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#edit_course_category_id').val()) {
                    showInlineError('edit_course_category_id', 'Please select a category');
                    hasError = true;
                }
                
                if (!$('#edit_course_name').val() || $('#edit_course_name').val().length < 3) {
                    showInlineError('edit_course_name', 'Course name must be at least 3 characters');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = $('#updateCourseBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                const formData = new FormData(this);
                
                // Handle checkboxes
                formData.set('is_active', $('#edit_is_active').is(':checked') ? 1 : 0);
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#editCourseModal').modal('hide');
                            refreshCurrentDataTable();
                            showSuccessToast('Course updated successfully!');
                        } else {
                            showErrorToast(result.message);
                            // Try to identify which field has the error
                            if (result.message.includes('category')) {
                                showInlineError('edit_course_category_id', result.message);
                            } else if (result.message.includes('name')) {
                                showInlineError('edit_course_name', result.message);
                            }
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while updating the course.');
                    },
                    complete: function() {
                        // Hide loading state
                        submitBtn.prop('disabled', false);
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
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
                            // Also refresh the category dropdown in forms
                            loadCategoriesForFilters();
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
                if (deleteItemType === 'course') {
                    deleteCourseConfirm(deleteItemId);
                } else if (deleteItemType === 'category') {
                    deleteCategoryConfirm(deleteItemId);
                } else if (deleteItemType === 'quiz') {
                    deleteQuizConfirm(deleteItemId);
                } else if (deleteItemType === 'assessment') {
                    deleteAssessmentConfirm(deleteItemId);
                } else if (deleteItemType === 'assessment_question') {
                    deleteAssessmentQuestionConfirm(deleteItemId);
                } else if (deleteItemType === 'quiz_question') {
                    deleteQuizQuestionConfirm(deleteItemId);
                }
            });
            
            // Quick add form submission with loading state
            $('#quickAddForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('quickAddForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#quick_course_category_id').val()) {
                    showInlineError('quick_course_category_id', 'Please select a category');
                    hasError = true;
                }
                
                if (!$('#quick_course_name').val() || $('#quick_course_name').val().length < 3) {
                    showInlineError('quick_course_name', 'Course name must be at least 3 characters');
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
                formData.append('action', 'add_course');
                
                // Handle checkboxes
                formData.set('is_active', $('#quick_is_active').is(':checked') ? 1 : 0);
                
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
                                showSuccessToast('Course added successfully!');
                                
                                // Add success animation to form
                                $('#quickAddForm').addClass('form-success');
                                setTimeout(() => {
                                    $('#quickAddForm').removeClass('form-success');
                                }, 600);
                                
                                // Reset form but keep category selected
                                resetQuickFormExceptCategory();
                                
                                // Reload datatable with proper callback
                                refreshCurrentDataTable();
                                
                                // Focus on course name for next entry
                                setTimeout(() => {
                                    $('#quick_course_name').focus();
                                }, 200);
                            } else {
                                showErrorToast(result.message);
                                // Try to identify which field has the error
                                if (result.message.includes('category')) {
                                    showInlineError('quick_course_category_id', result.message);
                                } else if (result.message.includes('name')) {
                                    showInlineError('quick_course_name', result.message);
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            showErrorToast('Error processing server response');
                        }
                    },
                    error: function(xhr) {
                        showErrorToast('An error occurred while saving the course.');
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

            // Quiz form submission
            $('#quizForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('quizForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#quiz_name').val()) {
                    showInlineError('quiz_name', 'Quiz name is required');
                    hasError = true;
                }
                
                if (!$('#quiz_course_id').val()) {
                    showErrorToast('Course information is missing. Please try again.');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = $('#saveQuizBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                const formData = new FormData(this);
                formData.set('is_active', $('#quiz_is_active').is(':checked') ? 1 : 0);
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#quizModal').modal('hide');
                            loadQuizzes(currentQuizCourseId);
                            showSuccessToast(result.message);
                        } else {
                            showErrorToast(result.message);
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while saving the quiz.');
                    },
                    complete: function() {
                        // Hide loading state
                        submitBtn.prop('disabled', false);
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                    }
                });
            });

            // Upload quiz image form submission
            $('#uploadQuizImageForm').on('submit', function(e) {
                e.preventDefault();
                
                if (!$('#upload_quize_image')[0].files[0]) {
                    showErrorToast('Please select an image file');
                    return;
                }
                
                // Show loading state
                const submitBtn = $('#uploadQuizImageBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                const formData = new FormData(this);
                formData.append('action', 'upload_course_quize_image');
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#uploadQuizImageModal').modal('hide');
                            loadQuizImage($('#current_course_id').val());
                            showSuccessToast(result.message);
                        } else {
                            showErrorToast(result.message);
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while uploading the image.');
                    },
                    complete: function() {
                        // Hide loading state
                        submitBtn.prop('disabled', false);
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                    }
                });
            });

            // Quiz question quick add form submission
            $('#quizQuestionQuickAddForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('quizQuestionQuickAddForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#quick_quiz_question_text').val()) {
                    showInlineError('quick_quiz_question_text', 'Question text is required');
                    hasError = true;
                }
                
                if (!$('#quick_quiz_option_a').val() || !$('#quick_quiz_option_b').val() || !$('#quick_quiz_option_c').val() || !$('#quick_quiz_option_d').val()) {
                    showErrorToast('All options are required');
                    hasError = true;
                }
                
                if (!$('#quick_quiz_correct_answer').val()) {
                    showInlineError('quick_quiz_correct_answer', 'Please select the correct answer');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = $('#addQuizQuestionContinueBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                const formData = new FormData(this);
                formData.append('action', 'add_quiz_question');
                formData.set('is_active', $('#quick_quiz_question_is_active').is(':checked') ? 1 : 0);
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            showSuccessToast('Quiz question added successfully!');
                            clearQuizQuestionQuickForm();
                            loadQuizQuestions($('#current_quiz_id').val());
                            
                            // Focus on question text for next entry
                            setTimeout(() => {
                                $('#quick_quiz_question_text').focus();
                            }, 200);
                        } else {
                            showErrorToast(result.message);
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while saving the quiz question.');
                    },
                    complete: function() {
                        // Hide loading state
                        submitBtn.prop('disabled', false);
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                    }
                });
            });

            // Edit quiz form submission (for quiz questions)
            $('#editQuizForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('editQuizForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#edit_question_text').val()) {
                    showInlineError('edit_question_text', 'Question text is required');
                    hasError = true;
                }
                
                if (!$('#edit_option_a').val() || !$('#edit_option_b').val() || !$('#edit_option_c').val() || !$('#edit_option_d').val()) {
                    showErrorToast('All options are required');
                    hasError = true;
                }
                
                if (!$('#edit_correct_answer').val()) {
                    showInlineError('edit_correct_answer', 'Please select the correct answer');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = $('#updateQuizBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                const formData = new FormData(this);
                formData.append('action', 'update_quiz_question');
                formData.append('question_id', $('#edit_quiz_id').val()); // This is actually question_id
                formData.set('is_active', $('#edit_quiz_is_active').is(':checked') ? 1 : 0);
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#editQuizModal').modal('hide');
                            loadQuizQuestions($('#current_quiz_id').val());
                            showSuccessToast(result.message);
                        } else {
                            showErrorToast(result.message);
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while updating the quiz question.');
                    },
                    complete: function() {
                        // Hide loading state
                        submitBtn.prop('disabled', false);
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                    }
                });
            });

            // Assessment form submission
            $('#assessmentForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('assessmentForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#assessment_name').val()) {
                    showInlineError('assessment_name', 'Assessment name is required');
                    hasError = true;
                }
                
                if (!$('#assessment_course_id').val()) {
                    showErrorToast('Course information is missing. Please try again.');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = $('#saveAssessmentBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                const formData = new FormData(this);
                formData.set('is_active', $('#assessment_is_active').is(':checked') ? 1 : 0);
                
                // Debug: Log form data
                console.log('Assessment form data:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
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
                            $('#assessmentModal').modal('hide');
                            loadAssessments();
                            showSuccessToast(result.message);
                        } else {
                            showErrorToast(result.message);
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while saving the assessment.');
                    },
                    complete: function() {
                        // Hide loading state
                        submitBtn.prop('disabled', false);
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                    }
                });
            });

            // Assessment question quick add form submission
            $('#assessmentQuestionQuickAddForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('assessmentQuestionQuickAddForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#quick_assessment_question_text').val()) {
                    showInlineError('quick_assessment_question_text', 'Question text is required');
                    hasError = true;
                }
                
                if (!$('#quick_assessment_option_a').val() || !$('#quick_assessment_option_b').val() || 
                    !$('#quick_assessment_option_c').val() || !$('#quick_assessment_option_d').val()) {
                    showErrorToast('All options are required');
                    hasError = true;
                }
                
                if (!$('#quick_assessment_correct_option').val()) {
                    showInlineError('quick_assessment_correct_option', 'Please select the correct option');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = $('#addAssessmentQuestionContinueBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                const formData = new FormData(this);
                formData.append('action', 'add_assessment_question');
                formData.set('is_active', $('#quick_assessment_question_is_active').is(':checked') ? 1 : 0);
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            showSuccessToast('Assessment question added successfully!');
                            clearAssessmentQuestionQuickForm();
                            loadAssessmentQuestions($('#current_assessment_id').val());
                            
                            // Focus on question text for next entry
                            setTimeout(() => {
                                $('#quick_assessment_question_text').focus();
                            }, 200);
                        } else {
                            showErrorToast(result.message);
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while saving the assessment question.');
                    },
                    complete: function() {
                        // Hide loading state
                        submitBtn.prop('disabled', false);
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                    }
                });
            });

            // Quiz import form submission
            $('#quizImportForm').on('submit', function(e) {
                e.preventDefault();
                importQuizQuestions();
            });

            // Edit assessment question form submission
            $('#editAssessmentQuestionForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('editAssessmentQuestionForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#edit_assessment_question_text').val()) {
                    showInlineError('edit_assessment_question_text', 'Question text is required');
                    hasError = true;
                }
                
                if (!$('#edit_assessment_option_a').val() || !$('#edit_assessment_option_b').val() || 
                    !$('#edit_assessment_option_c').val() || !$('#edit_assessment_option_d').val()) {
                    showErrorToast('All options are required');
                    hasError = true;
                }
                
                if (!$('#edit_assessment_correct_option').val()) {
                    showInlineError('edit_assessment_correct_option', 'Please select the correct option');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = $('#updateAssessmentQuestionBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                const formData = new FormData(this);
                formData.set('is_active', $('#edit_assessment_question_is_active').is(':checked') ? 1 : 0);
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#editAssessmentQuestionModal').modal('hide');
                            loadAssessmentQuestions($('#current_assessment_id').val());
                            showSuccessToast(result.message);
                        } else {
                            showErrorToast(result.message);
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while updating the assessment question.');
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

        // Categories DataTable initialization
        function initializeCategoriesDataTable() {
            categoriesDataTable = $('#categoriesDataTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: { action: 'get_categories' },
                    dataSrc: function(json) {
                        console.log('Categories DataTable received data:', json);
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
                        console.error('Categories DataTable AJAX error:', error, thrown);
                        showErrorToast('Error loading categories data');
                    }
                },
                columns: [
                    { data: 'category_id', width: '5%' },
                    { data: 'category_name', width: '25%' },
                    { data: 'category_description', width: '30%', defaultContent: '' },
                    { data: 'display_order', width: '10%', defaultContent: '0' },
                    { 
                        data: 'is_active', 
                        width: '10%',
                        render: function(data) {
                            return data == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                        }
                    },
                    { 
                        data: 'created_at', 
                        width: '10%',
                        render: function(data) {
                            return data ? new Date(data).toLocaleDateString() : '';
                        }
                    },
                    {
                        data: null,
                        width: '10%',
                        orderable: false,
                        render: function(data, type, row) {
                            return `
                              
                                    <button class="btn btn-sm btn-info me-1" onclick="viewCategoryQuestions(${row.category_id}, '${row.category_name}')" title="View Courses">
                                        <i class="ri-eye-line"></i>
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
                order: [[3, 'asc'], [1, 'asc']], // Order by display_order, then category_name
                pageLength: 25,
                responsive: true,
                language: {
                    emptyTable: "No categories found. Click 'Add Category' to create one.",
                    zeroRecords: "No categories match your search criteria."
                }
            });
        }

        // Function to view courses in a specific category
        function viewCategoryQuestions(categoryId, categoryName) {
            currentSelectedCategoryId = categoryId;
            
            // Hide categories view and show courses view
            $('#categoriesView').hide();
            $('#categoriesActionButtons').hide();
            $('#categoryCoursesView').show();
            $('#coursesActionButtons').show();
            
            // Update the quick add panel category
            $('#quick_course_category_id').val(categoryId);
            
            // Initialize or reload the category courses table
            if (categoryCoursesTable) {
                categoryCoursesTable.destroy();
            }
            
            categoryCoursesTable = $('#categoryCoursesTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: { 
                        action: 'get_courses_by_category', 
                        category_id: categoryId 
                    },
                    dataSrc: function(json) {
                        console.log('Category Courses DataTable received data:', json);
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
                        console.error('Category Courses DataTable AJAX error:', error, thrown);
                        showErrorToast('Error loading courses data');
                    }
                },
                columns: [
                    { data: 'course_id', width: '5%' },
                    { data: 'course_name', width: '30%' },
                    { data: 'course_subtitle', width: '25%', defaultContent: '' },
                    { 
                        data: 'is_active', 
                        width: '10%',
                        render: function(data) {
                            return data == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                        }
                    },
                    { 
                        data: 'created_at', 
                        width: '15%',
                        render: function(data) {
                            return data ? new Date(data).toLocaleDateString() : '';
                        }
                    },
                    {
                        data: null,
                        width: '15%',
                        orderable: false,
                        render: function(data, type, row) {
                            return `
                              
                                    <button class="btn btn-sm btn-info me-1" onclick="manageQuizzes(${row.course_id}, '${row.course_name}')" title="Manage Quizzes">
                                        <i class="ri-questionnaire-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning me-1" onclick="manageAssessments(${row.course_id}, '${row.course_name}', '${row.level || 'N/A'}')" title="Manage Assessments">
                                        <i class="ri-file-list-3-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-primary me-1" onclick="editCourse(${row.course_id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary me-1" onclick="duplicateCourse(${row.course_id})" title="Duplicate">
                                        <i class="ri-file-copy-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger me-1" onclick="deleteCourse(${row.course_id})" title="Delete">
                                        <i class="ri-delete-bin-line"></i>
                                    </button> 
                            `;
                        }
                    }
                ],
                order: [[0, 'desc']], // Order by course_id desc (newest first)
                pageLength: 25,
                responsive: true,
                language: {
                    emptyTable: `No courses found in "${categoryName}". Click 'Add Course' to create one.`,
                    zeroRecords: "No courses match your search criteria."
                }
            });
        }

        // Function to go back to categories view
        function backToCategoriesView() {
            currentSelectedCategoryId = null;
            
            // Show categories view and hide courses view
            $('#categoryCoursesView').hide();
            $('#coursesActionButtons').hide();
            $('#categoriesView').show();
            $('#categoriesActionButtons').show();
            
            // Hide quick add panel
            hideQuickAddPanel();
            
            // Destroy category courses table
            if (categoryCoursesTable) {
                categoryCoursesTable.destroy();
                categoryCoursesTable = null;
            }
        }

        // Function to show quick add panel for the selected category
        function showQuickAddPanelForCategory() {
            if (!currentSelectedCategoryId) {
                showErrorToast('Please select a category first');
                return;
            }
            
            // Set the category in the quick add form
            $('#quick_course_category_id').val(currentSelectedCategoryId);
            
            // Show the quick add panel
            $('#quickAddPanel').slideDown(300);
            
            // Focus on course name field
            setTimeout(() => {
                $('#quick_course_name').focus();
            }, 350);
        }

        // Function to refresh current data table
        function refreshCurrentDataTable() {
            if (currentSelectedCategoryId && categoryCoursesTable) {
                categoryCoursesTable.ajax.reload();
            } else if (categoriesDataTable) {
                categoriesDataTable.ajax.reload();
            }
        }

        // Function to refresh categories table
        function refreshCategoriesTable() {
            if (categoriesDataTable) {
                categoriesDataTable.ajax.reload();
            }
        }

        // Edit category function
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
                        $('#category_description').val(data.category_description || '');
                        $('#display_order').val(data.display_order || 0);
                        
                        $('#categoryAction').val('update_category');
                        $('#categoryModalLabel').text('Edit Category');
                        
                        $('#categoryModal').modal('show');
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading category data');
                }
            });
        }

        // Show add category modal
        function showAddCategoryModal() {
            // Reset form
            $('#categoryForm')[0].reset();
            $('#categoryAction').val('add_category');
            $('#categoryModalLabel').text('Add Category');
            $('#category_id_edit').val('');
            
            $('#categoryModal').modal('show');
        }

        // Load categories for dropdowns
        function loadCategoriesForFilters() {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_categories' },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const categories = result.data;
                        
                        // Update all category dropdowns
                        const categorySelects = ['#quick_course_category_id', '#edit_course_category_id'];
                        
                        categorySelects.forEach(function(selectId) {
                            const $select = $(selectId);
                            const currentValue = $select.val();
                            
                            $select.empty().append('<option value="">Select Category</option>');
                            
                            categories.forEach(function(category) {
                                $select.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                            });
                            
                            // Restore previous selection if it still exists
                            if (currentValue) {
                                $select.val(currentValue);
                            }
                        });
                    }
                },
                error: function() {
                    console.error('Error loading categories for filters');
                }
            });
        }

        // Edit course function
        function editCourse(courseId) {
            // First ensure categories are loaded in edit modal
            loadCategoriesForFilters();
            
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_course', course_id: courseId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        // Wait a bit for categories to load, then populate form
                        setTimeout(function() {
                            $('#edit_course_id').val(data.course_id);
                            $('#edit_course_category_id').val(data.course_category_id);
                            $('#edit_course_name').val(data.course_name);
                            $('#edit_course_subtitle').val(data.course_subtitle || '');
                            $('#edit_course_overview').val(data.course_overview || '');
                            $('#edit_course_outcomes').val(data.course_outcomes || '');
                            $('#edit_display_order').val(data.display_order);
                            $('#edit_is_active').prop('checked', data.is_active == 1);
                            
                            // Show current image
                            if (data.course_image) {
                                $('#current_course_image').html('<small class="text-success">Current: ' + data.course_image + '</small>');
                            } else {
                                $('#current_course_image').html('');
                            }
                            
                            $('#editCourseModal').modal('show');
                        }, 200);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading course data');
                }
            });
        }

        // Delete functions
        function deleteCourse(courseId) {
            deleteItemId = courseId;
            deleteItemType = 'course';
            $('#deleteModal').modal('show');
        }

        function deleteCourseConfirm(courseId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_course', course_id: courseId },
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
                        if (categoriesDataTable) {
                            categoriesDataTable.ajax.reload();
                        }
                        loadCategoriesForFilters();
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        // Assessment management functions
        let currentCourseId = null;
        let currentCourseName = '';
        let currentCourseLevel = '';

        function manageAssessments(courseId, courseName, courseLevel) {
            currentCourseId = courseId;
            currentCourseName = courseName;
            currentCourseLevel = courseLevel;
            
            // Update modal title
            $('#manageAssessmentsModalLabel').html(`<i class="ri-file-list-3-line"></i> Manage Assessments - ${courseName} (${courseLevel})`);
            
            loadAssessments(courseId);
            $('#manageAssessmentsModal').offcanvas('show');
        }

        function loadAssessments(courseId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { 
                    action: 'get_assessments',
                    course_id: courseId 
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const assessments = result.data;
                        let html = '';
                        
                        if (assessments.length === 0) {
                            html = '<tr><td colspan="7" class="text-center text-muted">No assessments found. Click "Add Assessment" to create one.</td></tr>';
                        } else {
                            assessments.forEach(function(assessment) {
                                const statusBadge = assessment.is_active == 1 ? 
                                    '<span class="badge bg-success">Active</span>' : 
                                    '<span class="badge bg-secondary">Inactive</span>';
                                
                                const imagePreview = assessment.assessment_image ? 
                                    `<img src="../../../uploads/assessments/${assessment.assessment_image}" style="max-width: 50px; max-height: 50px;" class="img-thumbnail">` : 
                                    '-';
                                
                                html += `
                                    <tr>
                                        <td>${assessment.assessment_id}</td>
                                        <td>${assessment.assessment_name}</td>
                                        <td>${assessment.assessment_description || '-'}</td>
                                        <td><span class="badge bg-info">${assessment.level}</span></td>
                                        <td>${imagePreview}</td>
                                        <td>${statusBadge}</td>
                                        <td>
                                             
                                                <button class="btn btn-sm btn-info me-1" onclick="manageAssessmentQuestions(${assessment.assessment_id}, '${assessment.assessment_name}')" title="Manage Questions">
                                                    <i class="ri-question-line"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning me-1" onclick="editAssessment(${assessment.assessment_id})" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger me-1" onclick="deleteAssessment(${assessment.assessment_id})" title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button> 
                                        </td>
                                    </tr>
                                `;
                            });
                        }
                        
                        $('#assessmentsTableBody').html(html);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading assessments');
                }
            });
        }

        function showAddAssessmentModal() {
            $('#assessmentForm')[0].reset();
            $('#assessmentAction').val('add_assessment');
            $('#assessmentModalLabel').text(`Add Assessment for ${currentCourseName}`);
            $('#assessment_id').val('');
            $('#assessment_course_id').val(currentCourseId);
            $('#assessment_course_display').val(currentCourseName);
            $('#assessment_level_display').val(currentCourseLevel);
            $('#assessment_image_preview').html('');
            $('#assessment_is_active').prop('checked', true);
            $('#assessmentModal').modal('show');
        }

        function editAssessment(assessmentId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_assessment', assessment_id: assessmentId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        $('#assessment_id').val(data.assessment_id);
                        $('#assessment_name').val(data.assessment_name);
                        $('#assessment_description').val(data.assessment_description || '');
                        $('#assessment_course_id').val(data.course_id || currentCourseId);
                        $('#assessment_course_display').val(data.course_name || currentCourseName);
                        $('#assessment_level_display').val(data.course_level || data.level || currentCourseLevel);
                        $('#assessment_is_active').prop('checked', data.is_active == 1);
                        
                        if (data.assessment_image) {
                            $('#assessment_image_preview').html(`<img src="../../../uploads/assessments/${data.assessment_image}" class="img-thumbnail" style="max-width: 150px;">`);
                        } else {
                            $('#assessment_image_preview').html('');
                        }
                        
                        $('#assessmentAction').val('update_assessment');
                        $('#assessmentModalLabel').text('Edit Assessment');
                        $('#assessmentModal').modal('show');
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading assessment data');
                }
            });
        }

        function deleteAssessment(assessmentId) {
            deleteItemId = assessmentId;
            deleteItemType = 'assessment';
            
            // Ensure delete modal appears above other modals
            $('#deleteModal').css('z-index', 1060);
            $('#deleteModal').modal('show');
            
            // Fix backdrop z-index after modal is shown
            $('#deleteModal').on('shown.bs.modal', function() {
                $('.modal-backdrop').last().css('z-index', 1059);
            });
        }

        function deleteAssessmentConfirm(assessmentId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_assessment', assessment_id: assessmentId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#deleteModal').modal('hide');
                        loadAssessments();
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        // Assessment Questions management
        function manageAssessmentQuestions(assessmentId, assessmentName) {
            $('#current_assessment_id').val(assessmentId);
            $('#question_assessment_id').val(assessmentId);
            $('#assessmentQuestionsName').text(assessmentName);
            
            loadAssessmentQuestions(assessmentId);
            $('#manageAssessmentQuestionsModal').modal('show');
        }

        function loadAssessmentQuestions(assessmentId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_assessment_questions', assessment_id: assessmentId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const questions = result.data;
                        let html = ''; 
                        if (questions.length === 0) {
                            html = '<tr><td colspan="5" class="text-center text-muted">No questions found. Click "Add Question" to create one.</td></tr>';
                        } else {
                            questions.forEach(function(question) {
                                const statusBadge = question.is_active == 1 ? 
                                    '<span class="badge bg-success">Active</span>' : 
                                    '<span class="badge bg-secondary">Inactive</span>';
                                console.log(question)
                                html += `
                                    <tr>
                                        <td>${question.question_id}</td>
                                        <td>${question.question_text.substring(0, 100)}${question.question_text.length > 100 ? '...' : ''}</td>
                                        <td><span class="badge bg-success">${question.correct_option}</span></td>
                                        <td>${statusBadge}</td>
                                        <td>
                                           
                                                <button class="btn btn-sm btn-warning me-1" onclick="editAssessmentQuestion(${question.question_id})" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger me-1" onclick="deleteAssessmentQuestion(${question.question_id})" title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button> 
                                        </td>
                                    </tr>
                                `;
                            });
                        }
                        
                        $('#assessmentQuestionsTableBody').html(html);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading assessment questions');
                }
            });
        }

        function showAssessmentQuestionQuickAddPanel() {
            $('#assessmentQuestionQuickAddPanel').slideDown(300);
            setTimeout(() => {
                $('#quick_assessment_question_text').focus();
            }, 350);
        }

        function hideAssessmentQuestionQuickAddPanel() {
            $('#assessmentQuestionQuickAddPanel').slideUp(300);
        }

        function clearAssessmentQuestionQuickForm() {
            $('#assessmentQuestionQuickAddForm')[0].reset();
            $('#quick_assessment_question_is_active').prop('checked', true);
        }

        function editAssessmentQuestion(questionId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_assessment_question', question_id: questionId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        $('#edit_assessment_question_id').val(data.question_id);
                        $('#edit_assessment_question_text').val(data.question_text);
                        $('#edit_assessment_option_a').val(data.option_a);
                        $('#edit_assessment_option_b').val(data.option_b);
                        $('#edit_assessment_option_c').val(data.option_c);
                        $('#edit_assessment_option_d').val(data.option_d);
                        $('#edit_assessment_correct_option').val(data.correct_option);
                        $('#edit_assessment_question_is_active').prop('checked', data.is_active == 1);
                        
                        $('#editAssessmentQuestionModal').modal('show');
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading assessment question data');
                }
            });
        }

        function deleteAssessmentQuestion(questionId) {
            deleteItemId = questionId;
            deleteItemType = 'assessment_question';
            
            // Ensure delete modal appears above other modals
            $('#deleteModal').css('z-index', 1060);
            $('#deleteModal').modal('show');
            
            // Fix backdrop z-index after modal is shown
            $('#deleteModal').on('shown.bs.modal', function() {
                $('.modal-backdrop').last().css('z-index', 1059);
            });
        }

        function deleteAssessmentQuestionConfirm(questionId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_assessment_question', question_id: questionId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#deleteModal').modal('hide');
                        loadAssessmentQuestions($('#current_assessment_id').val());
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        // Preview function for assessment image
        function previewAssessmentImage(input) {
            const preview = $('#assessment_image_preview');
            preview.html('');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.html(`<img src="${e.target.result}" class="img-thumbnail" style="max-width: 150px;">`);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Preview function for quiz image
        function previewQuizImage(input) {
            const preview = $('#quiz_image_preview');
            preview.html('');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.html(`<img src="${e.target.result}" class="img-thumbnail" style="max-width: 150px;">`);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Quiz import functions
        function openQuizImportModal() {
            $('#quizImportForm')[0].reset();
            $('#importResults').hide();
            $('#import_quiz_id').val($('#current_quiz_id').val());
            $('#quizImportModal').modal('show');
        }

        function importQuizQuestions() {
            const fileInput = document.getElementById('csv_file');
            
            if (!fileInput.files || fileInput.files.length === 0) {
                showErrorToast('Please select a CSV file to upload');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'import_quiz_questions');
            formData.append('quiz_id', $('#import_quiz_id').val());
            formData.append('csv_file', fileInput.files[0]);
            
            const submitBtn = $('#importQuizQuestionsBtn');
            const originalText = submitBtn.find('.btn-text').html();
            
            submitBtn.find('.btn-text').hide();
            submitBtn.find('.btn-loading').show();
            $('#importResults').hide();
            
            $.ajax({
                url: '',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    const result = JSON.parse(response);
                    
                    if (result.success) {
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').html('<i class="ri-check-line me-2"></i>Import Complete!').show();
                        
                        // Show detailed results
                        let resultsHtml = `
                            <div class="alert alert-success">
                                <h6><i class="ri-check-circle-line me-2"></i>Import Summary</h6>
                                <p class="mb-1"><strong>Imported:</strong> ${result.imported} questions</p>
                                <p class="mb-0"><strong>Skipped:</strong> ${result.skipped} questions</p>
                            </div>
                        `;
                        
                        if (result.errors && result.errors.length > 0) {
                            resultsHtml += `
                                <div class="alert alert-warning">
                                    <h6><i class="ri-alert-triangle-line me-2"></i>Errors/Warnings</h6>
                                    <ul class="mb-0 small">
                                        ${result.errors.map(err => `<li>${err}</li>`).join('')}
                                    </ul>
                                </div>
                            `;
                        }
                        
                        $('#importResults').html(resultsHtml).show();
                        
                        showSuccessToast(result.message);
                        
                        // Refresh questions list after 3 seconds
                        setTimeout(function() {
                            loadQuizQuestions($('#current_quiz_id').val());
                            $('#quizImportModal').modal('hide');
                            submitBtn.find('.btn-text').html(originalText);
                        }, 3000);
                    } else {
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                        
                        $('#importResults').html(`
                            <div class="alert alert-danger">
                                <i class="ri-error-warning-line me-2"></i>${result.message}
                            </div>
                        `).show();
                        
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    submitBtn.find('.btn-loading').hide();
                    submitBtn.find('.btn-text').show();
                    
                    $('#importResults').html(`
                        <div class="alert alert-danger">
                            <i class="ri-error-warning-line me-2"></i>An error occurred while importing questions
                        </div>
                    `).show();
                    
                    showErrorToast('An error occurred while importing questions');
                }
            });
        }

        // Quiz Questions Import Functions
        function openQuizImportModal() {
            $('#quizImportForm')[0].reset();
            $('#importResults').hide();
            $('#import_quiz_id').val($('#current_quiz_id').val());
            $('#quizImportModal').modal('show');
        }

        function importQuizQuestions() {
            const fileInput = document.getElementById('csv_file');
            
            if (!fileInput.files || fileInput.files.length === 0) {
                showErrorToast('Please select a CSV file to upload');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'import_quiz_questions');
            formData.append('quiz_id', $('#import_quiz_id').val());
            formData.append('csv_file', fileInput.files[0]);
            
            const submitBtn = $('#importQuizQuestionsBtn');
            submitBtn.find('.btn-text').hide();
            submitBtn.find('.btn-loading').show();
            $('#importResults').hide();
            
            $.ajax({
                url: '',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    const result = JSON.parse(response);
                    
                    if (result.success) {
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                        
                        // Show detailed results
                        let resultsHtml = `
                            <div class="alert alert-success">
                                <h6><i class="ri-check-circle-line me-2"></i>Import Summary</h6>
                                <p class="mb-1"><strong>Imported:</strong> ${result.imported} questions</p>
                                <p class="mb-0"><strong>Skipped:</strong> ${result.skipped} questions</p>
                            </div>
                        `;
                        
                        if (result.errors && result.errors.length > 0) {
                            resultsHtml += `
                                <div class="alert alert-warning">
                                    <h6><i class="ri-alert-triangle-line me-2"></i>Errors/Warnings</h6>
                                    <ul class="mb-0 small">
                                        ${result.errors.map(err => `<li>${err}</li>`).join('')}
                                    </ul>
                                </div>
                            `;
                        }
                        
                        $('#importResults').html(resultsHtml).show();
                        
                        showSuccessToast(result.message);
                        
                        // Refresh questions list after 3 seconds
                        setTimeout(function() {
                            loadQuizQuestions($('#current_quiz_id').val());
                            $('#quizImportModal').modal('hide');
                            submitBtn.find('.btn-text').html(originalText);
                        }, 3000);
                    } else {
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                        
                        $('#importResults').html(`
                            <div class="alert alert-danger">
                                <i class="ri-error-warning-line me-2"></i>${result.message}
                            </div>
                        `).show();
                        
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    submitBtn.find('.btn-loading').hide();
                    submitBtn.find('.btn-text').show();
                    
                    $('#importResults').html(`
                        <div class="alert alert-danger">
                            <i class="ri-error-warning-line me-2"></i>An error occurred while importing questions
                        </div>
                    `).show();
                    
                    showErrorToast('An error occurred while importing questions');
                }
            });
        }

        // Duplicate course function
        function duplicateCourse(courseId) {
            if (!confirm('Are you sure you want to duplicate this course?')) {
                return;
            }
            
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'duplicate_course', course_id: courseId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        refreshCurrentDataTable();
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error duplicating course');
                }
            });
        }

        // Quiz management functions
        let currentQuizCourseId = null;
        let currentQuizCourseName = '';

        function manageQuizzes(courseId, courseName) {
            currentQuizCourseId = courseId;
            currentQuizCourseName = courseName;
            
            $('#current_quiz_course_id').val(courseId);
            $('#quizzesCourseName').text(courseName);
            
            loadQuizzes(courseId);
            $('#manageQuizzesModal').offcanvas('show');
        }

        function loadQuizzes(courseId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { 
                    action: 'get_quizzes',
                    course_id: courseId 
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const quizzes = result.data;
                        let html = '';
                        
                        if (quizzes.length === 0) {
                            html = '<tr><td colspan="6" class="text-center text-muted">No quizzes found. Click "Add Quiz" to create one.</td></tr>';
                        } else {
                            quizzes.forEach(function(quiz) {
                                const statusBadge = quiz.is_active == 1 ? 
                                    '<span class="badge bg-success">Active</span>' : 
                                    '<span class="badge bg-secondary">Inactive</span>';
                                
                                const imagePreview = quiz.quiz_image ? 
                                    `<img src="../../../uploads/quizzes/${quiz.quiz_image}" style="max-width: 50px; max-height: 50px;" class="img-thumbnail">` : 
                                    '-';
                                
                                html += `
                                    <tr>
                                        <td>${quiz.quiz_id}</td>
                                        <td>${quiz.quiz_name}</td>
                                        <td>${quiz.quiz_description || '-'}</td>
                                        <td>${imagePreview}</td>
                                        <td>${statusBadge}</td>
                                        <td>
                                           
                                                <button class="btn btn-sm btn-info me-1" onclick="manageQuizQuestions(${quiz.quiz_id}, '${quiz.quiz_name}')" title="Manage Questions">
                                                    <i class="ri-question-line"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning me-1" onclick="editQuiz(${quiz.quiz_id})" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger me-1" onclick="deleteQuiz(${quiz.quiz_id})" title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button> 
                                        </td>
                                    </tr>
                                `;
                            });
                        }
                        
                        $('#quizzesTableBody').html(html);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading quizzes');
                }
            });
        }

        function showAddQuizModal() {
            $('#quizForm')[0].reset();
            $('#quizAction').val('add_quiz');
            $('#quizModalLabel').text(`Add Quiz for ${currentQuizCourseName}`);
            $('#quiz_id').val('');
            $('#quiz_course_id').val(currentQuizCourseId);
            $('#quiz_course_display').val(currentQuizCourseName);
            $('#quiz_image_preview').html('');
            $('#quiz_is_active').prop('checked', true);
            $('#quizModal').modal('show');
        }

        function loadQuizImage(courseId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_course', course_id: courseId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success && result.data.quize_image) {
                        const imagePath = '../../../uploads/quizzes/' + result.data.quize_image;
                        $('#quizImagePreview').attr('src', imagePath);
                        $('#quizImageFilename').text(result.data.quize_image);
                        $('#quizImageDisplay').show();
                    } else {
                        $('#quizImageDisplay').hide();
                    }
                }
            });
        }

        function showQuizQuickAddPanel() {
            $('#quizQuickAddPanel').slideDown(300);
            setTimeout(() => {
                $('#quick_question_text').focus();
            }, 350);
        }

        function hideQuizQuickAddPanel() {
            $('#quizQuickAddPanel').slideUp(300);
        }

        function clearQuizQuickForm() {
            $('#quizQuickAddForm')[0].reset();
            $('#quick_quiz_is_active').prop('checked', true);
        }

        function showUploadQuizImageModal() {
            $('#uploadQuizImageModal').modal('show');
        }

        function editQuiz(quizId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_quiz', quiz_id: quizId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        $('#quiz_id').val(data.quiz_id);
                        $('#quiz_course_id').val(data.course_id);
                        $('#quiz_name').val(data.quiz_name);
                        $('#quiz_description').val(data.quiz_description || '');
                        $('#quiz_course_display').val(currentQuizCourseName);
                        $('#quiz_is_active').prop('checked', data.is_active == 1);
                        
                        if (data.quiz_image) {
                            $('#quiz_image_preview').html(`<img src="../../../uploads/quizzes/${data.quiz_image}" class="img-thumbnail" style="max-width: 150px;">`);
                        } else {
                            $('#quiz_image_preview').html('');
                        }
                        
                        $('#quizAction').val('update_quiz');
                        $('#quizModalLabel').text('Edit Quiz');
                        $('#quizModal').modal('show');
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading quiz data');
                }
            });
        }

        function deleteQuiz(quizId) {
            deleteItemId = quizId;
            deleteItemType = 'quiz';
            
            // Ensure delete modal appears above other modals
            $('#deleteModal').css('z-index', 1060);
            $('#deleteModal').modal('show');
            
            // Fix backdrop z-index after modal is shown
            $('#deleteModal').on('shown.bs.modal', function() {
                $('.modal-backdrop').last().css('z-index', 1059);
            });
        }

        function deleteQuizConfirm(quizId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_quiz', quiz_id: quizId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#deleteModal').modal('hide');
                        loadQuizzes(currentQuizCourseId);
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        // Quiz Questions management
        function manageQuizQuestions(quizId, quizName) {
            $('#current_quiz_id').val(quizId);
            $('#question_quiz_id').val(quizId);
            $('#quizQuestionsName').text(quizName);
            
            loadQuizQuestions(quizId);
            $('#manageQuizQuestionsModal').modal('show');
        }

        function loadQuizQuestions(quizId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_quiz_questions', quiz_id: quizId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const questions = result.data;
                        let html = '';
                        
                        if (questions.length === 0) {
                            html = '<tr><td colspan="6" class="text-center text-muted">No questions found. Click "Add Question" to create one.</td></tr>';
                        } else {
                            questions.forEach(function(question) {
                                const statusBadge = question.is_active == 1 ? 
                                    '<span class="badge bg-success">Active</span>' : 
                                    '<span class="badge bg-secondary">Inactive</span>';
                                
                                html += `
                                    <tr>
                                        <td>${question.question_id}</td>
                                        <td>${question.question_text.substring(0, 100)}${question.question_text.length > 100 ? '...' : ''}</td>
                                        <td><span class="badge bg-success">${question.correct_answer}</span></td>
                                        <td>${question.points}</td>
                                        <td>${statusBadge}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-secondary" onclick="editQuizQuestion(${question.question_id})" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteQuizQuestion(${question.question_id})" title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            });
                        }
                        
                        $('#quizQuestionsTableBody').html(html);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading quiz questions');
                }
            });
        }

        function showQuizQuestionQuickAddPanel() {
            $('#quizQuestionQuickAddPanel').slideDown(300);
            setTimeout(() => {
                $('#quick_quiz_question_text').focus();
            }, 350);
        }

        function hideQuizQuestionQuickAddPanel() {
            $('#quizQuestionQuickAddPanel').slideUp(300);
        }

        function clearQuizQuestionQuickForm() {
            $('#quizQuestionQuickAddForm')[0].reset();
            $('#quick_quiz_question_is_active').prop('checked', true);
        }

        function openQuizImportModal() {
            $('#quizImportForm')[0].reset();
            $('#importResults').hide();
            $('#import_quiz_id').val($('#current_quiz_id').val());
            $('#quizImportModal').modal('show');
        }

        function importQuizQuestions() {
            const fileInput = document.getElementById('csv_file');
            
            if (!fileInput.files || fileInput.files.length === 0) {
                showErrorToast('Please select a CSV file to upload');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'import_quiz_questions');
            formData.append('quiz_id', $('#current_quiz_id').val());
            formData.append('csv_file', fileInput.files[0]);
            
            const submitBtn = $('#importQuizQuestionsBtn');
            submitBtn.find('.btn-text').hide();
            submitBtn.find('.btn-loading').show();
            submitBtn.prop('disabled', true);
            
            $('#importResults').hide();
            
            $.ajax({
                url: '',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    const result = JSON.parse(response);
                    
                    if (result.success) {
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').html('<i class="ri-check-line me-2"></i>Import Complete!');
                        submitBtn.find('.btn-text').show();
                        submitBtn.removeClass('btn-primary').addClass('btn-success');
                        
                        // Show detailed results
                        let resultsHtml = `
                            <div class="alert alert-success">
                                <h6><i class="ri-check-circle-line me-2"></i>Import Summary</h6>
                                <p class="mb-1"><strong>Imported:</strong> ${result.imported} questions</p>
                                <p class="mb-0"><strong>Skipped:</strong> ${result.skipped} questions</p>
                            </div>
                        `;
                        
                        if (result.errors && result.errors.length > 0) {
                            resultsHtml += `
                                <div class="alert alert-warning">
                                    <h6><i class="ri-alert-triangle-line me-2"></i>Errors/Warnings</h6>
                                    <ul class="mb-0 small">
                                        ${result.errors.map(err => `<li>${err}</li>`).join('')}
                                    </ul>
                                </div>
                            `;
                        }
                        
                        $('#importResults').html(resultsHtml).show();
                        
                        showSuccessToast(result.message);
                        
                        // Refresh questions list after 3 seconds
                        setTimeout(function() {
                            loadQuizQuestions($('#current_quiz_id').val());
                            $('#quizImportModal').modal('hide');
                            
                            // Reset button
                            submitBtn.find('.btn-text').html('<i class="ri-upload-line me-2"></i>Import Questions');
                            submitBtn.removeClass('btn-success').addClass('btn-primary');
                            submitBtn.prop('disabled', false);
                        }, 3000);
                    } else {
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                        submitBtn.prop('disabled', false);
                        
                        $('#importResults').html(`
                            <div class="alert alert-danger">
                                <i class="ri-error-warning-line me-2"></i>${result.message}
                            </div>
                        `).show();
                        
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    submitBtn.find('.btn-loading').hide();
                    submitBtn.find('.btn-text').show();
                    submitBtn.prop('disabled', false);
                    
                    $('#importResults').html(`
                        <div class="alert alert-danger">
                            <i class="ri-error-warning-line me-2"></i>An error occurred while importing questions
                        </div>
                    `).show();
                    
                    showErrorToast('An error occurred while importing questions');
                }
            });
        }

        function openQuizImportModal() {
            $('#quizImportForm')[0].reset();
            $('#importResults').hide();
            $('#import_quiz_id').val($('#current_quiz_id').val());
            $('#quizImportModal').modal('show');
        }

        function importQuizQuestions() {
            const fileInput = document.getElementById('csv_file');
            
            if (!fileInput.files || fileInput.files.length === 0) {
                showErrorToast('Please select a CSV file to upload');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'import_quiz_questions');
            formData.append('quiz_id', $('#import_quiz_id').val());
            formData.append('csv_file', fileInput.files[0]);
            
            const submitBtn = $('#importQuizQuestionsBtn');
            submitBtn.find('.btn-text').hide();
            submitBtn.find('.btn-loading').show();
            submitBtn.prop('disabled', true);
            
            $('#importResults').hide();
            
            $.ajax({
                url: '',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    const result = JSON.parse(response);
                    
                    if (result.success) {
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').html('<i class="ri-check-line me-2"></i>Import Complete!').show();
                        submitBtn.removeClass('btn-primary').addClass('btn-success');
                        
                        // Show detailed results
                        let resultsHtml = `
                            <div class="alert alert-success">
                                <h6><i class="ri-check-circle-line me-2"></i>Import Summary</h6>
                                <p class="mb-1"><strong>Imported:</strong> ${result.imported} questions</p>
                                <p class="mb-0"><strong>Skipped:</strong> ${result.skipped} questions</p>
                            </div>
                        `;
                        
                        if (result.errors && result.errors.length > 0) {
                            resultsHtml += `
                                <div class="alert alert-warning">
                                    <h6><i class="ri-alert-triangle-line me-2"></i>Errors/Warnings</h6>
                                    <ul class="mb-0 small">
                                        ${result.errors.map(err => `<li>${err}</li>`).join('')}
                                    </ul>
                                </div>
                            `;
                        }
                        
                        $('#importResults').html(resultsHtml).show();
                        
                        showSuccessToast(result.message);
                        
                        // Refresh questions list after 3 seconds
                        setTimeout(function() {
                            loadQuizQuestions($('#current_quiz_id').val());
                            $('#quizImportModal').modal('hide');
                            
                            // Reset button
                            submitBtn.find('.btn-text').html('<i class="ri-upload-line me-2"></i>Import Questions');
                            submitBtn.removeClass('btn-success').addClass('btn-primary');
                            submitBtn.prop('disabled', false);
                        }, 3000);
                    } else {
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
                        submitBtn.prop('disabled', false);
                        
                        $('#importResults').html(`
                            <div class="alert alert-danger">
                                <i class="ri-error-warning-line me-2"></i>${result.message}
                            </div>
                        `).show();
                        
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    submitBtn.find('.btn-loading').hide();
                    submitBtn.find('.btn-text').show();
                    submitBtn.prop('disabled', false);
                    
                    $('#importResults').html(`
                        <div class="alert alert-danger">
                            <i class="ri-error-warning-line me-2"></i>An error occurred while importing questions
                        </div>
                    `).show();
                    
                    showErrorToast('An error occurred while importing questions');
                }
            });
        }

        function editQuizQuestion(questionId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_quiz_question', question_id: questionId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        $('#edit_quiz_id').val(data.question_id); // Store question_id for update
                        $('#edit_question_text').val(data.question_text);
                        $('#edit_option_a').val(data.option_a);
                        $('#edit_option_b').val(data.option_b);
                        $('#edit_option_c').val(data.option_c);
                        $('#edit_option_d').val(data.option_d);
                        $('#edit_correct_answer').val(data.correct_answer);
                        $('#edit_quiz_points').val(data.points);
                        $('#edit_quiz_is_active').prop('checked', data.is_active == 1);
                        
                        $('#editQuizModal').modal('show');
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading quiz question data');
                }
            });
        }

        function deleteQuizQuestion(questionId) {
            deleteItemId = questionId;
            deleteItemType = 'quiz_question';
            
            // Ensure delete modal appears above other modals
            $('#deleteModal').css('z-index', 1060);
            $('#deleteModal').modal('show');
            
            // Fix backdrop z-index after modal is shown
            $('#deleteModal').on('shown.bs.modal', function() {
                $('.modal-backdrop').last().css('z-index', 1059);
            });
        }

        function deleteQuizQuestionConfirm(questionId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_quiz_question', question_id: questionId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#deleteModal').modal('hide');
                        loadQuizQuestions($('#current_quiz_id').val());
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        function deleteQuizImage() {
            if (!confirm('Are you sure you want to delete the quiz image?')) {
                return;
            }
            
            const courseId = $('#current_course_id').val();
            
            $.ajax({
                url: '',
                type: 'POST',
                data: { 
                    action: 'delete_course_quize_image', 
                    course_id: courseId 
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#quizImageDisplay').hide();
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error deleting quiz image');
                }
            });
        }

        // Utility functions
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
            const toastElement = new bootstrap.Toast(document.getElementById(toastId), { delay: 4000 });
            toastElement.show();
            
            // Remove toast element after it's hidden
            $('#' + toastId).on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }

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

        function hideQuickAddPanel() {
            $('#quickAddPanel').slideUp(300);
        }

        function clearQuickForm() {
            $('#quickAddForm')[0].reset();
            $('#quick_is_active').prop('checked', true);
        }

        function resetQuickFormExceptCategory() {
            const categoryValue = $('#quick_course_category_id').val();
            $('#quickAddForm')[0].reset();
            $('#quick_course_category_id').val(categoryValue);
            $('#quick_is_active').prop('checked', true);
        }

        // Helper function to show inline error message
        function showInlineError(fieldId, message) {
            const field = $('#' + fieldId);
            
            // Check if field exists
            if (field.length === 0) {
                console.warn('Field not found:', fieldId);
                showErrorToast(message);
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

        // Function to clean up modal backdrops
        function cleanupModalBackdrop() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            $('body').css('padding-right', '');
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
        $('#editCourseModal').on('hidden.bs.modal', function() {
            $('#editCourseForm')[0].reset();
            // Reset button state
            const submitBtn = $('#updateCourseBtn');
            submitBtn.prop('disabled', false);
            submitBtn.find('.btn-loading').hide();
            submitBtn.find('.btn-text').show();
            // Clear file info
            $('#current_course_image').html('');
        });

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
    </script>

<?php include '../layout/Footer.php'; ?>
      