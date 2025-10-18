<?php
require("../layout/Session.php");
require("../../config/db.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'get_passages':
            getPassages($conn);
            break;
        case 'get_passage':
            getPassage($conn);
            break;
        case 'add_passage':
            addPassage($conn);
            break;
        case 'update_passage':
            updatePassage($conn);
            break;
        case 'delete_passage':
            deletePassage($conn);
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
        case 'get_passages_by_category':
            getPassagesByCategory($conn);
            break;
        case 'duplicate_passage':
            duplicatePassage($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function getPassages($conn) {
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
            0 => 'ra.passage_id',
            1 => 'ra.passage_title', 
            2 => 'sc.category_name',
            3 => 'ra.timer_seconds',
            4 => 'ra.points',
            5 => 'ra.is_active',
            6 => 'ra.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'ra.created_at') . ' ' . $orderDir;
        
        // Base filtering
        $where = " WHERE 1=1 ";
        
        // Apply category filter if provided
        $categoryFilter = $params['category_filter'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND ra.category_id = " . intval($categoryFilter) . " ";
        }
        
        // Total records count (with category filter applied)
        $totalSql = "SELECT COUNT(*) as total FROM reading_speedread_questions ra LEFT JOIN reading_speedread sc ON ra.category_id = sc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        // Apply search filter
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (ra.passage_title LIKE '%$search%' OR ra.passage_text LIKE '%$search%' OR sc.category_name LIKE '%$search%') ";
        }
        
        // Filtered count
        $filteredSql = "SELECT COUNT(*) as total FROM reading_speedread_questions ra LEFT JOIN reading_speedread sc ON ra.category_id = sc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        // Pagination
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        // Main data query
        $sql = "SELECT ra.passage_id, ra.passage_title, ra.passage_text, ra.category_id, ra.timer_seconds, 
                       ra.points, ra.is_active, ra.created_at, sc.category_name
                FROM reading_speedread_questions ra 
                LEFT JOIN reading_speedread sc ON ra.category_id = sc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'passage_id' => $row['passage_id'],
                    'passage_title' => $row['passage_title'],
                    'passage_text' => $row['passage_text'],
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'category_id' => $row['category_id'],
                    'timer_seconds' => $row['timer_seconds'] ?? 0,
                    'points' => $row['points'],
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

function getPassage($conn) {
    $passage_id = $_POST['passage_id'] ?? 0;
    
    $sql = "SELECT ra.passage_id, ra.category_id, ra.passage_title, ra.passage_text, ra.timer_seconds,
                   ra.points, ra.is_active, ra.created_at, ra.updated_at, sc.category_name 
            FROM reading_speedread_questions ra 
            LEFT JOIN reading_speedread sc ON ra.category_id = sc.category_id 
            WHERE ra.passage_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $passage_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Passage not found']);
    }
}

function addPassage($conn) {
    try {
        // Validate and sanitize input data
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $passage_title = trim($_POST['passage_title'] ?? '');
        $passage_text = trim($_POST['passage_text'] ?? '');
        $timer_seconds = filter_var($_POST['timer_seconds'] ?? 60, FILTER_VALIDATE_INT);
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        // Input validation
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($passage_title) || strlen($passage_title) < 3) {
            throw new Exception('Passage title must be at least 3 characters long');
        }
        
        if (empty($passage_text) || strlen($passage_text) < 10) {
            throw new Exception('Passage text must be at least 10 characters long');
        }
        
        if (!$timer_seconds || $timer_seconds < 1) {
            throw new Exception('Timer seconds must be at least 1');
        }
        
        // Begin database transaction for data integrity
        $conn->begin_transaction();
        
        try {
            $sql = "INSERT INTO reading_speedread_questions (category_id, passage_title, passage_text, timer_seconds, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            $stmt->bind_param("issii", 
                $category_id, $passage_title, $passage_text, $timer_seconds, 
                $is_active
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $conn->commit();
            
            // Log successful operation
            error_log("Passage added successfully: ID $insertId, Category: $category_id, Timer: $timer_seconds seconds");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Passage added successfully', 
                'id' => $insertId,
                'timer_seconds' => $timer_seconds
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add Passage Error: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updatePassage($conn) {
    try {
        $passage_id = $_POST['passage_id'] ?? 0;
        $category_id = $_POST['category_id'] ?? 0;
        $passage_title = trim($_POST['passage_title'] ?? '');
        $passage_text = trim($_POST['passage_text'] ?? '');
        $timer_seconds = $_POST['timer_seconds'] ?? 60;
        $is_active = $_POST['is_active'] ?? 1;
        
        // Input validation
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($passage_title) || strlen($passage_title) < 3) {
            throw new Exception('Passage title must be at least 3 characters long');
        }
        
        if (empty($passage_text) || strlen($passage_text) < 10) {
            throw new Exception('Passage text must be at least 10 characters long');
        }
        
        $sql = "UPDATE reading_speedread_questions SET category_id=?, passage_title=?, passage_text=?, timer_seconds=?, is_active=?, updated_at=NOW() WHERE passage_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("issiii", $category_id, $passage_title, $passage_text, $timer_seconds, $is_active, $passage_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Passage updated successfully', 'timer_seconds' => $timer_seconds]);
        } else {
            throw new Exception('Error updating passage: ' . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Update Passage Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deletePassage($conn) {
    $passage_id = $_POST['passage_id'] ?? 0;
    
    $sql = "DELETE FROM reading_speedread_questions WHERE passage_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $passage_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Passage deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting passage: ' . $conn->error]);
    }
}

function getCategories($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'reading_speedread'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    // For admin interface, show all categories (both active and inactive)
    $sql = "SELECT category_id, category_name, category_description, 
                   display_order, is_active, created_at, points
            FROM reading_speedread 
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
            FROM reading_speedread 
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
    $tableCheck = $conn->query("SHOW TABLES LIKE 'reading_speedread'");
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
            $adjustSql = "UPDATE reading_speedread SET display_order = display_order + 1 WHERE display_order >= ?";
            $adjustStmt = $conn->prepare($adjustSql);
            $adjustStmt->bind_param("i", $display_order);
            $adjustStmt->execute();
            $adjustStmt->close();
        }
        
        // Insert the new category
        $sql = "INSERT INTO reading_speedread (category_name, category_description, display_order, points) VALUES (?, ?, ?, ?)";
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
        $getCurrentSql = "SELECT display_order FROM reading_speedread WHERE category_id = ?";
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
                $adjustSql = "UPDATE reading_speedread SET display_order = display_order + 1 
                             WHERE display_order >= ? AND display_order < ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $new_display_order, $old_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            } else {
                // Moving DOWN (e.g., from 2 to 5)
                // Decrement positions 3, 4, 5 by 1 (old position + 1 to target position)
                $adjustSql = "UPDATE reading_speedread SET display_order = display_order - 1 
                             WHERE display_order > ? AND display_order <= ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $old_display_order, $new_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            }
        }
        
        // Update the category with new position
        $sql = "UPDATE reading_speedread SET category_name=?, category_description=?, display_order=?, points=? WHERE category_id=?";
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
    
    $sql = "DELETE FROM reading_speedread WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $conn->error]);
    }
}

function getPassagesByCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT ra.*, sc.category_name 
            FROM reading_speedread_questions ra 
            LEFT JOIN reading_speedread sc ON ra.category_id = sc.category_id 
            WHERE ra.category_id = ? 
            ORDER BY ra.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $passages = [];
    while ($row = $result->fetch_assoc()) {
        $passages[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $passages]);
}

function duplicatePassage($conn) {
    $passage_id = $_POST['passage_id'] ?? 0;
    
    // Get original passage
    $sql = "SELECT * FROM reading_speedread_questions WHERE passage_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $passage_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Insert duplicate with modified title
        $new_passage_title = $row['passage_title'] . ' (Copy)';
        
        $sql = "INSERT INTO reading_speedread_questions (category_id, passage_title, passage_text, timer_seconds, is_active) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issii", 
            $row['category_id'], 
            $new_passage_title, 
            $row['passage_text'], 
            $row['timer_seconds'], 
            $row['is_active']
        );
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Passage duplicated successfully', 'new_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error duplicating passage: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Original passage not found']);
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
            $passage_text = $item['passage_text'] ?? '';
            $timer_seconds = $item['timer_seconds'] ?? 60;
            
            $sql = "INSERT INTO reading_speedread_questions (category_id, passage_title, passage_text, timer_seconds, is_active) VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issii", 
                $item['category_id'] ?? 1,
                $item['passage_title'] ?? '',
                $passage_text,
                $timer_seconds,
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


?>

<?php include '../layout/Header.php'; ?>
 
<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">Speed Reader - Timed Reading Practice</h5>
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

<!-- Top Action Buttons - Passages View -->
<div class="row mb-3" id="passagesActionButtons" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <button class="btn btn-primary me-2" onclick="showQuickAddPanelForCategory()">
                            <i class="ri-add-line"></i> Add Passage
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
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Timer (seconds) *</label>
                                    <input type="number" class="form-control" id="quick_timer_seconds" name="timer_seconds" value="60" min="1" required>
                                    <small class="text-muted">Time limit for reading</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Passage Title *</label>
                            <input type="text" class="form-control" id="quick_passage_title" name="passage_title" required placeholder="Enter passage title...">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Passage Text *</label>
                            <textarea class="form-control" id="quick_passage_text" name="passage_text" rows="4" required placeholder="Enter the passage text for speed reading..."></textarea>
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

<!-- Category Passages View (Initially Hidden) -->
<div class="row mb-3" id="categoryPassagesView" style="display: none;">
    <div class="col-12">
        <div class="card border-success">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="categoryPassagesTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Timer</th>
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

    <!-- Edit Passage Modal -->
    <div class="modal fade" id="editPassageModal" tabindex="-1" aria-labelledby="editPassageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPassageModalLabel">Edit Passage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editPassageForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit_passage_id" name="passage_id">
                        <input type="hidden" name="action" value="update_passage">
                        
                        <input type="hidden" id="edit_category_id" name="category_id">
                        
                        <div class="mb-3">
                            <label for="edit_timer_seconds" class="form-label">Timer (seconds) *</label>
                            <input type="number" class="form-control" id="edit_timer_seconds" name="timer_seconds" value="60" min="1" required>
                            <small class="text-muted">Time limit for reading</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_passage_title" class="form-label">Passage Title *</label>
                            <input type="text" class="form-control" id="edit_passage_title" name="passage_title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_passage_text" class="form-label">Passage Text *</label>
                            <textarea class="form-control" id="edit_passage_text" name="passage_text" rows="5" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_is_active" class="form-label">Status</label>
                            <select class="form-select" id="edit_is_active" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Passage</button>
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
                        
                        <div class="mb-3">
                            <label for="display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control category-input" id="display_order" name="display_order" value="0" autocomplete="off">
                        </div>
                        
                        <div class="mb-3">
                            <label for="points" class="form-label">Points *</label>
                            <input type="number" class="form-control category-input" id="points" name="points" value="10" min="1" max="100" required autocomplete="off">
                            <small class="text-muted">Points awarded for completing passages in this category</small>
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
                    <h5 class="modal-title" id="bulkImportModalLabel">Bulk Import Passages</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="bulkImportForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_import">
                        
                        <div class="alert alert-info">
                            <h6><i class="ri-information-line"></i> Import Instructions:</h6>
                            <ul class="mb-0">
                                <li>Upload a JSON file with passage data</li>
                                <li>Each passage should have: passage_title, passage_text, timer_seconds</li>
                                <li>Optional fields: category_id, points, is_active</li>
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
    "passage_title": "Speed Reading Exercise 1",
    "passage_text": "The quick brown fox jumps over the lazy dog. This sentence contains every letter of the alphabet.",
    "timer_seconds": 60,
    "points": 10,
    "is_active": 1
  }
]</code></pre>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Import Passages</button>
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
        let passagesTable;
        let categoriesDataTable;
        let categoryPassagesTable;
        let deleteItemId = null;
        let deleteItemType = null;
        let currentSelectedCategoryId = null;
        
        // Global variables for filtering
        let currentCategoryFilter = '';

        $(document).ready(function() {
            // Initialize Categories DataTable first (show categories by default)
            initializeCategoriesDataTable();
            
            // Don't initialize passages table on page load - it will be initialized when viewing a category
            // The table will be created dynamically when user clicks "View" on a category

            // Load categories for filters and quick add
            loadCategoriesForFilters();

            // Don't initialize category passages table here - it will be initialized when needed
            // Load categories for modals - removed duplicate DataTable initialization

            // Edit passage form submission
            $('#editPassageForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('editPassageForm');
                
                // Client-side validation
                let hasError = false;
                

                
                if (!$('#edit_passage_title').val() || $('#edit_passage_title').val().length < 3) {
                    showInlineError('edit_passage_title', 'Passage title must be at least 3 characters');
                    hasError = true;
                }
                
                if (!$('#edit_passage_text').val() || $('#edit_passage_text').val().length < 10) {
                    showInlineError('edit_passage_text', 'Passage text must be at least 10 characters');
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
                            $('#editPassageModal').modal('hide');
                            refreshCurrentView();
                            showSuccessToast('Passage updated successfully!');
                        } else {
                            showErrorToast(result.message);
                            // Try to identify which field has the error
                            if (result.message.includes('category')) {
                                showInlineError('edit_category_id', result.message);
                            } else if (result.message.includes('title')) {
                                showInlineError('edit_passage_title', result.message);
                            } else if (result.message.includes('text')) {
                                showInlineError('edit_passage_text', result.message);
                            }
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while updating the passage.');
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
                            // Wait for modal to hide, then refresh and reload categories
                            setTimeout(function() {
                                loadCategoriesForFilters();
                                refreshCurrentView();
                            }, 300);
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
                if (deleteItemType === 'passage') {
                    deletePassageConfirm(deleteItemId);
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
                
                if (!$('#quick_passage_title').val() || $('#quick_passage_title').val().length < 3) {
                    showInlineError('quick_passage_title', 'Passage title must be at least 3 characters');
                    hasError = true;
                }
                
                if (!$('#quick_passage_text').val() || $('#quick_passage_text').val().length < 10) {
                    showInlineError('quick_passage_text', 'Passage text must be at least 10 characters');
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
                
                // Temporarily enable category field if disabled for form submission
                const categoryField = $('#quick_category_id');
                const wasDisabled = categoryField.prop('disabled');
                if (wasDisabled) {
                    categoryField.prop('disabled', false);
                }
                
                const formData = new FormData(this);
                formData.append('action', 'add_passage');
                
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
                                showSuccessToast('Passage added successfully!');
                                
                                // Add success animation to form
                                $('#quickAddForm').addClass('form-success');
                                setTimeout(() => {
                                    $('#quickAddForm').removeClass('form-success');
                                }, 600);
                                
                                // Reset form but keep category selected
                                resetQuickFormExceptCategory();
                                
                                // Reload datatable with proper callback
                                refreshCurrentView();
                                
                                // Focus on passage title for next entry
                                setTimeout(() => {
                                    $('#quick_passage_title').focus();
                                }, 200);
                            } else {
                                showErrorToast(result.message);
                                // Try to identify which field has the error
                                if (result.message.includes('category')) {
                                    showInlineError('quick_category_id', result.message);
                                } else if (result.message.includes('title')) {
                                    showInlineError('quick_passage_title', result.message);
                                } else if (result.message.includes('text')) {
                                    showInlineError('quick_passage_text', result.message);
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            showErrorToast('Error processing server response');
                        }
                    },
                    error: function(xhr) {
                        showErrorToast('An error occurred while saving the passage.');
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
                            refreshCurrentView();
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

        // New Category Management Functions
        function showCategoriesView() {
            $('#categoryPassagesView').hide();
            $('#quickAddPanel').hide();
            $('#categoriesView').show();
            $('#categoriesActionButtons').show();
            $('#passagesActionButtons').hide();
            
            // Reset category selection in quick add panel
            $('#quick_category_id').prop('disabled', false);
            $('#quick_category_id').removeClass('bg-light');
            $('#quick_category_id').val('');
            
            // Initialize categories datatable if not already done
            if (!categoriesDataTable) {
                initializeCategoriesDataTable();
            } else {
                categoriesDataTable.ajax.reload();
            }
        }

        function backToCategoriesView() {
            showCategoriesView();
        }

        function showQuickAddPanelForCategory() {
            $('#quickAddPanel').slideDown(400, function() {
                // Focus on passage title after panel is fully shown
                $('#quick_passage_title').focus();
            });
        }

        function viewCategoryPassages(categoryId, categoryName) {
            currentSelectedCategoryId = categoryId;
            
            // Update UI
            $('#categoriesView').hide();
            $('#categoriesActionButtons').hide();
            $('#passagesActionButtons').show();
            $('#categoryPassagesView').show();
            
            // Set category in quick add panel and lock it
            $('#quick_category_id').val(categoryId);
            $('#quick_category_id').prop('disabled', true);
            $('#quick_category_id').addClass('bg-light');
            $('#edit_category_id').val(categoryId);
            
            // Initialize category passages table if not already done
            if (!categoryPassagesTable) {
                initializeCategoryPassagesTable();
            } else {
                categoryPassagesTable.ajax.reload();
            }
        }

        function initializeCategoriesDataTable() {
            // Check if DataTable already exists and destroy it first
            if ($.fn.DataTable.isDataTable('#categoriesDataTable')) {
                $('#categoriesDataTable').DataTable().destroy();
            }
            
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
                               
                                    <button class="btn btn-sm btn-info me-1" onclick="viewCategoryPassages(${row.category_id}, '${row.category_name}')" title="View Passages">
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
        }

        function initializeCategoryPassagesTable() {
            // Check if DataTable already exists and destroy it first
            if ($.fn.DataTable.isDataTable('#categoryPassagesTable')) {
                $('#categoryPassagesTable').DataTable().destroy();
            }
            
            categoryPassagesTable = $('#categoryPassagesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_passages';
                        d.category_filter = currentSelectedCategoryId;
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
                    { data: 'passage_id' },
                    { 
                        data: 'passage_title',
                        render: function(data, type, row) {
                            return data.length > 40 ? data.substring(0, 40) + '...' : data;
                        }
                    },
                    { 
                        data: 'timer_seconds',
                        render: function(data, type, row) {
                            return data ? data + ' sec' : '0 sec';
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
                                 
                                    <button class="btn btn-sm btn-info me-1" onclick="editPassage(${row.passage_id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning me-1" onclick="duplicatePassage(${row.passage_id})" title="Duplicate">
                                        <i class="ri-file-copy-line"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger me-1" onclick="deletePassage(${row.passage_id})" title="Delete">
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

        function showAddCategoryModal() {
            $('#category_id_edit').val('');
            $('#category_name').val('');
            $('#category_description').val('');
            $('#display_order').val('0');
            $('#points').val('10'); // Reset points to default
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
        }

        function refreshCategoriesTable() {
            if (categoriesDataTable) {
                categoriesDataTable.ajax.reload();
            }
        }

        function refreshCurrentDataTable() {
            if (categoryPassagesTable) {
                categoryPassagesTable.ajax.reload();
            }
        }

        function loadCategoriesForFilters() {
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
                    }
                }
            });
        }

        function editPassage(passageId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_passage', passage_id: passageId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        $('#edit_passage_id').val(data.passage_id);
                        $('#edit_category_id').val(data.category_id);
                        $('#edit_passage_title').val(data.passage_title);
                        $('#edit_passage_text').val(data.passage_text);
                        $('#edit_timer_seconds').val(data.timer_seconds || 60);
                        $('#edit_is_active').val(data.is_active);
                        
                        $('#editPassageModal').modal('show');
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading passage data');
                }
            });
        }

        function deletePassage(passageId) {
            deleteItemId = passageId;
            deleteItemType = 'passage';
            $('#deleteModal').modal('show');
        }

        function deletePassageConfirm(passageId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_passage', passage_id: passageId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#deleteModal').modal('hide');
                        refreshCurrentView();
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
                $('#points').val('10'); // Reset points to default
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
            // Hide the manage categories modal first
            $('#manageCategoriesModal').modal('hide');
            
            // Wait for the modal to fully hide before showing the new one
            setTimeout(function() {
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
            }, 300);
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
                        loadCategoriesForFilters();
                        refreshCurrentView();
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        // Load categories when manage categories modal is opened
        $('#manageCategoriesModal').on('show.bs.modal', function() {
            loadCategoriesForModals();
        });
        


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
        $('#editPassageModal').on('hidden.bs.modal', function() {
            $('#editPassageForm')[0].reset();
        });

        // Reset category form when modal is closed
        $('#categoryModal').on('hidden.bs.modal', function() {
            $('#categoryForm')[0].reset();
            $('#categoryAction').val('add_category');
            $('#categoryModalLabel').text('Add Category');
            // Reset points field to default value
            $('#points').val('10');
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
            $('#quickAddPanel').slideDown(400, function() {
                // Focus on passage title after panel is fully shown
                $('#quick_passage_title').focus();
                
                // Load categories if not already loaded
                if ($('#quick_category_id option').length <= 1) {
                    loadCategoriesForFilters();
                }
            });
        }

        function hideQuickAddPanel() {
            $('#quickAddPanel').slideUp();
        }

        function clearQuickForm() {
            // Store current category state
            const selectedCategory = $('#quick_category_id').val();
            const wasDisabled = $('#quick_category_id').prop('disabled');
            const hasLockClass = $('#quick_category_id').hasClass('bg-light');
            
            // Reset form
            $('#quickAddForm')[0].reset();
            $('#quick_timer_seconds').val('60');
            
            // Restore category state
            $('#quick_category_id').val(selectedCategory);
            if (wasDisabled) {
                $('#quick_category_id').prop('disabled', true);
            }
            if (hasLockClass) {
                $('#quick_category_id').addClass('bg-light');
            }
            
            $('#quick_passage_title').focus();
        }

        function resetQuickFormExceptCategory() {
            // Store the selected category and its state
            const selectedCategory = $('#quick_category_id').val();
            const wasDisabled = $('#quick_category_id').prop('disabled');
            const hasLockClass = $('#quick_category_id').hasClass('bg-light');
            
            // Reset all fields
            $('#quick_passage_title').val('');
            $('#quick_passage_text').val('');
            $('#quick_timer_seconds').val('60');
            
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
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_categories' },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        // Update category filter dropdown
                        const categoryFilter = $('#categoryFilter');
                        categoryFilter.empty().append('<option value="">All Categories</option>');
                        
                        // Update quick add category dropdown
                        const quickCategorySelect = $('#quick_category_id');
                        quickCategorySelect.empty().append('<option value="">Select Category</option>');
                        
                        // Update edit category dropdown
                        const editCategorySelect = $('#edit_category_id');
                        editCategorySelect.empty().append('<option value="">Select Category</option>');
                        
                        result.data.forEach(function(category) {
                            categoryFilter.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                            quickCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                            editCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                        });
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

        // Enhanced refresh function with error handling
        function refreshDataTable() {
            try {
                if (categoryPassagesTable) {
                    categoryPassagesTable.ajax.reload(function() {
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

        // Function to refresh the currently active view
        function refreshCurrentView() {
            try {
                // Check which view is currently visible
                if ($('#categoriesView').is(':visible')) {
                    // Categories view is active
                    if (categoriesDataTable) {
                        categoriesDataTable.ajax.reload(function() {
                            console.log('Categories DataTable refreshed successfully');
                        }, false);
                    }
                } else if ($('#categoryPassagesView').is(':visible')) {
                    // Category passages view is active
                    if (categoryPassagesTable) {
                        categoryPassagesTable.ajax.reload(function() {
                            console.log('Category passages DataTable refreshed successfully');
                        }, false);
                    }
                } else {
                    // Fallback to categories view
                    if (categoriesDataTable) {
                        categoriesDataTable.ajax.reload();
                    }
                }
            } catch (error) {
                console.error('Error refreshing current view:', error);
                // Fallback: reload the page
                location.reload();
            }
        }

        function duplicatePassage(passageId) {
            if (confirm('Are you sure you want to duplicate this passage?')) {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: { action: 'duplicate_passage', passage_id: passageId },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            refreshCurrentView();
                            showSuccessToast('Passage duplicated successfully!');
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

    </script>

<?php require("../layout/Footer.php"); ?>
 