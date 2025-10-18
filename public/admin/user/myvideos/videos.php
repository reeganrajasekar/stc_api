<?php
require("../layout/Session.php");
require("../../config/db.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'get_videos':
            getVideos($conn);
            break;
        case 'get_video':
            getVideo($conn);
            break;
        case 'add_video':
            addVideo($conn);
            break;
        case 'update_video':
            updateVideo($conn);
            break;
        case 'delete_video':
            deleteVideo($conn);
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
        case 'get_categories_with_count':
            getCategoriesWithCount($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function getVideos($conn) {
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
            0 => 'v.id',
            1 => 'v.video_title', 
            2 => 'vc.category_name',
            3 => 'v.video_file_name',
            4 => 'v.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'v.created_at') . ' ' . $orderDir;
        
        // Base filtering
        $where = " WHERE 1=1 ";
        
        // Apply category filter if provided
        $categoryFilter = $params['category_filter'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND v.category_id = " . intval($categoryFilter) . " ";
        }
        
        // Total records count
        $totalSql = "SELECT COUNT(*) as total FROM videos v LEFT JOIN video_category vc ON v.category_id = vc.id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        // Apply search filter
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (v.video_title LIKE '%$search%' OR v.description LIKE '%$search%' OR vc.category_name LIKE '%$search%') ";
        }
        
        // Filtered count
        $filteredSql = "SELECT COUNT(*) as total FROM videos v LEFT JOIN video_category vc ON v.category_id = vc.id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        // Pagination
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        // Main data query
        $sql = "SELECT v.id, v.video_title, v.description, v.category_id, v.video_file_path, 
                       v.video_file_name, v.created_at, vc.category_name
                FROM videos v 
                LEFT JOIN video_category vc ON v.category_id = vc.id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'id' => $row['id'],
                    'video_title' => $row['video_title'],
                    'description' => $row['description'] ?? '',
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'category_id' => $row['category_id'],
                    'video_file_path' => $row['video_file_path'],
                    'video_file_name' => $row['video_file_name'],
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

function getVideo($conn) {
    $video_id = $_POST['video_id'] ?? 0;
    
    $sql = "SELECT v.id, v.category_id, v.video_title, v.description, v.video_file_path,
                   v.video_file_name, v.created_at, v.updated_at, vc.category_name 
            FROM videos v 
            LEFT JOIN video_category vc ON v.category_id = vc.id 
            WHERE v.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $video_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Video not found']);
    }
}

function addVideo($conn) {
    try {
        // Validate input data
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $video_title = trim($_POST['video_title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Input validation
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($video_title) || strlen($video_title) < 3) {
            throw new Exception('Video title must be at least 3 characters long');
        }
        
        // Handle video file upload
        if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] != 0) {
            throw new Exception('Please upload a video file');
        }
        
        $video_file = $_FILES['video_file'];
        $allowed_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'];
        $file_extension = strtolower(pathinfo($video_file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Invalid video format. Allowed: ' . implode(', ', $allowed_extensions));
        }
        
        // Check file size (max 500MB)
        $max_size = 500 * 1024 * 1024;
        if ($video_file['size'] > $max_size) {
            throw new Exception('Video file size must be less than 500MB');
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = __DIR__ . '/../../../uploads/video/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $unique_filename;
        $db_path = 'uploads/video/' . $unique_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($video_file['tmp_name'], $upload_path)) {
            throw new Exception('Failed to upload video file');
        }
        
        // Begin database transaction
        $conn->begin_transaction();
        
        try {
            $sql = "INSERT INTO videos (category_id, video_title, description, video_file_path, video_file_name, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            $original_filename = $video_file['name'];
            $stmt->bind_param("issss", 
                $category_id, $video_title, $description, $db_path, $original_filename
            );
            
            if (!$stmt->execute()) {
                // Delete uploaded file if database insert fails
                unlink($upload_path);
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $conn->commit();
            
            error_log("Video added successfully: ID $insertId, Category: $category_id");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Video uploaded successfully', 
                'id' => $insertId
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            // Delete uploaded file on error
            if (file_exists($upload_path)) {
                unlink($upload_path);
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add Video Error: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateVideo($conn) {
    try {
        $video_id = $_POST['video_id'] ?? 0;
        $category_id = $_POST['category_id'] ?? 0;
        $video_title = trim($_POST['video_title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Input validation
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($video_title) || strlen($video_title) < 3) {
            throw new Exception('Video title must be at least 3 characters long');
        }
        
        // Check if new video file is uploaded
        $update_file = false;
        $new_file_path = '';
        $new_file_name = '';
        
        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] == 0) {
            $video_file = $_FILES['video_file'];
            $allowed_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'];
            $file_extension = strtolower(pathinfo($video_file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid video format. Allowed: ' . implode(', ', $allowed_extensions));
            }
            
            // Check file size (max 500MB)
            $max_size = 500 * 1024 * 1024;
            if ($video_file['size'] > $max_size) {
                throw new Exception('Video file size must be less than 500MB');
            }
            
            // Get old file path to delete later
            $old_file_sql = "SELECT video_file_path FROM videos WHERE id = ?";
            $old_stmt = $conn->prepare($old_file_sql);
            $old_stmt->bind_param("i", $video_id);
            $old_stmt->execute();
            $old_result = $old_stmt->get_result();
            $old_file_path = '';
            if ($old_row = $old_result->fetch_assoc()) {
                $old_file_path = __DIR__ . '/../../../' . $old_row['video_file_path'];
            }
            
            // Upload new file
            $upload_dir = __DIR__ . '/../../../uploads/video/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $unique_filename;
            $new_file_path = 'uploads/video/' . $unique_filename;
            $new_file_name = $video_file['name'];
            
            if (!move_uploaded_file($video_file['tmp_name'], $upload_path)) {
                throw new Exception('Failed to upload video file');
            }
            
            $update_file = true;
            
            // Delete old file
            if (!empty($old_file_path) && file_exists($old_file_path)) {
                unlink($old_file_path);
            }
        }
        
        // Update database
        if ($update_file) {
            $sql = "UPDATE videos SET category_id=?, video_title=?, description=?, video_file_path=?, video_file_name=?, updated_at=NOW() WHERE id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("issssi", $category_id, $video_title, $description, $new_file_path, $new_file_name, $video_id);
        } else {
            $sql = "UPDATE videos SET category_id=?, video_title=?, description=?, updated_at=NOW() WHERE id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("issi", $category_id, $video_title, $description, $video_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Video updated successfully']);
        } else {
            throw new Exception('Error updating video: ' . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Update Video Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteVideo($conn) {
    $video_id = $_POST['video_id'] ?? 0;
    
    // Get file path before deleting from database
    $file_sql = "SELECT video_file_path FROM videos WHERE id = ?";
    $file_stmt = $conn->prepare($file_sql);
    $file_stmt->bind_param("i", $video_id);
    $file_stmt->execute();
    $file_result = $file_stmt->get_result();
    
    if ($file_row = $file_result->fetch_assoc()) {
        $file_path = __DIR__ . '/../../../' . $file_row['video_file_path'];
        
        // Delete from database
        $sql = "DELETE FROM videos WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $video_id);
        
        if ($stmt->execute()) {
            // Delete physical file
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            echo json_encode(['success' => true, 'message' => 'Video deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting video: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Video not found']);
    }
}

function getCategories($conn) {
    $sql = "SELECT * FROM video_category ORDER BY category_name";
    $result = $conn->query($sql);
    $categories = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $categories]);
}

function getCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT * FROM video_category WHERE id = ?";
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
    $category_name = trim($_POST['category_name'] ?? '');
    
    if (empty($category_name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        return;
    }
    
    $sql = "INSERT INTO video_category (category_name, created_at) VALUES (?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $category_name);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding category: ' . $conn->error]);
    }
}

function updateCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    $category_name = trim($_POST['category_name'] ?? '');
    
    if (empty($category_name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        return;
    }
    
    $sql = "UPDATE video_category SET category_name=?, updated_at=NOW() WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $category_name, $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating category: ' . $conn->error]);
    }
}

function deleteCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    // Check if category has videos
    $check_sql = "SELECT COUNT(*) as count FROM videos WHERE category_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    
    if ($check_row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete category with existing videos']);
        return;
    }
    
    $sql = "DELETE FROM video_category WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $conn->error]);
    }
}

function getCategoriesWithCount($conn) {
    $sql = "SELECT vc.id, vc.category_name, vc.created_at, 
                   COUNT(v.id) as video_count
            FROM video_category vc 
            LEFT JOIN videos v ON vc.id = v.category_id 
            GROUP BY vc.id, vc.category_name, vc.created_at
            ORDER BY vc.category_name";
    
    $result = $conn->query($sql);
    $categories = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $categories]);
}


?>

<?php include '../layout/Header.php'; ?>
 
<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">My Videos - Video Library</h5>
     
    </div>
</div>

<!-- Categories View Action Panel -->
<div class="row mb-3" id="categoriesActionPanel">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        
                        <button class="btn btn-outline-secondary" onclick="showAddCategoryForm()">
                           Add Categories
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

<!-- Videos View Action Panel -->
<div class="row mb-3" id="videosActionPanel" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-8">
                      
                            <button class="btn btn-primary" onclick="showQuickAddPanel()">
                                <i class="ri-add-line"></i> Upload Video
                            </button>
                            <button class="btn btn-outline-secondary" onclick="showCategoriesView()">
                <i class="ri-arrow-left-line"></i> Back to Categories
            </button>
                        <button class="btn btn-outline-success" onclick="refreshDataTable()">
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
                <h6 class="mb-0"><i class="ri-upload-line"></i> Upload Video</h6>
                <button class="btn btn-sm btn-outline-light" onclick="hideQuickAddPanel()">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="card-body">
                <form id="quickAddForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" id="quick_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Video Title *</label>
                        <input type="text" class="form-control" id="quick_video_title" name="video_title" required placeholder="Enter video title...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="quick_description" name="description" rows="3" placeholder="Enter video description..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Video File * (Max 500MB)</label>
                        <input type="file" class="form-control" id="quick_video_file" name="video_file" accept="video/*" required>
                        <small class="text-muted">Supported formats: MP4, AVI, MOV, WMV, FLV, MKV, WEBM</small>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success" id="addContinueBtn">
                            <span class="btn-text">
                                <i class="ri-upload-line"></i> Upload Video
                            </span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Uploading...
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
<div class="row" id="categoriesTableContainer">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Video Categories</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="categoriesTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Video Count</th>
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

<!-- Videos Table -->
<div class="row" id="videosTableContainer" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0" id="videosTableTitle">Videos in Category</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="videosTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>File Name</th>
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

    <!-- Edit Video Modal -->
    <div class="modal fade" id="editVideoModal" tabindex="-1" aria-labelledby="editVideoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editVideoModalLabel">Edit Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editVideoForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="edit_video_id" name="video_id">
                        <input type="hidden" name="action" value="update_video">
                        
                        <div class="mb-3">
                            <label for="edit_category_id" class="form-label">Category *</label>
                            <select class="form-select" id="edit_category_id" name="category_id" required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_video_title" class="form-label">Video Title *</label>
                            <input type="text" class="form-control" id="edit_video_title" name="video_title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_video_file" class="form-label">Replace Video File (Optional)</label>
                            <input type="file" class="form-control" id="edit_video_file" name="video_file" accept="video/*">
                            <small class="text-muted">Leave empty to keep current video. Max 500MB</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current File:</label>
                            <p class="text-muted" id="current_file_name">-</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="updateVideoBtn">
                            <span class="btn-text">
                                <i class="ri-save-line"></i> Update Video
                            </span>
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
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true" data-bs-backdrop="static">
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
                            <input type="text" class="form-control" id="category_name" name="category_name" required>
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



    <!-- Video Preview Modal -->
    <div class="modal fade" id="videoPreviewModal" tabindex="-1" aria-labelledby="videoPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="videoPreviewModalLabel">Video Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ratio ratio-16x9">
                        <video id="previewVideoPlayer" controls controlsList="nodownload">
                            <source id="previewVideoSource" src="" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                    <div class="mt-3">
                        <p class="mb-1"><strong>Title:</strong> <span id="previewVideoTitle">-</span></p>
                        <p class="mb-1"><strong>Description:</strong> <span id="previewVideoDescription">-</span></p>
                        <p class="mb-0"><strong>Category:</strong> <span id="previewVideoCategory">-</span></p>
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
 
    <script>
        let videosTable;
        let categoriesTable;
        let deleteItemId = null;
        let deleteItemType = null;
        let currentViewCategory = null;
        
        // Global variables for filtering
        let currentCategoryFilter = '';

        $(document).ready(function() {
            // Initialize Categories DataTable
            categoriesTable = $('#categoriesTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: { action: 'get_categories_with_count' },
                    dataSrc: function(json) {
                        console.log('Categories DataTable received data:', json);
                        if (json.success && json.data) {
                            return json.data;
                        }
                        return [];
                    },
                    error: function(xhr, error, thrown) {
                        console.error('Categories DataTable AJAX error:', error, thrown);
                        showErrorToast('Error loading categories: ' + error);
                    }
                },
                columns: [
                    { data: 'id' },
                    { data: 'category_name' },
                    { 
                        data: 'video_count',
                        render: function(data, type, row) {
                            return data || 0;
                        }
                    },
                    { 
                        data: 'created_at',
                        render: function(data, type, row) {
                            return data ? new Date(data).toLocaleDateString() : '-';
                        }
                    },
                    { 
                        data: null,
                        render: function(data, type, row) {
                            // Escape category name for safe JavaScript
                            const safeCategoryName = row.category_name.replace(/'/g, "\\'").replace(/"/g, '\\"');
                            return `
                                
                                    <button class="btn btn-primary me-2" onclick="viewCategoryVideos(${row.id}, '${safeCategoryName}')" title="View Videos">
                                        <i class="ri-play-line"></i> View Videos
                                    </button>
                                    <button class="btn btn-warning me-2" onclick="editCategory(${row.id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-danger me-2" onclick="deleteCategory(${row.id})" title="Delete">
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

            // Initialize Videos DataTable (but don't load data yet)
            videosTable = $('#videosTable').DataTable({
                processing: true,
                serverSide: true,
                deferLoading: 0, // Don't load data initially
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_videos';
                        d.category_filter = currentViewCategory;
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
                    { data: 'id' },
                    { 
                        data: 'video_title',
                        render: function(data, type, row) {
                            return data.length > 50 ? data.substring(0, 50) + '...' : data;
                        }
                    },
                    { 
                        data: 'video_file_name',
                        render: function(data, type, row) {
                            return data ? (data.length > 30 ? data.substring(0, 30) + '...' : data) : '-';
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
                                    <button class="btn btn-primary me-2" onclick="viewVideo(${row.id})" title="View">
                                        <i class="ri-play-line"></i>
                                    </button>
                                    <button class="btn btn-warning me-2" onclick="editVideo(${row.id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-danger me-2" onclick="deleteVideo(${row.id})" title="Delete">
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

            // Load categories for quick add
            loadCategoriesForFilters();

            // Edit video form submission
            $('#editVideoForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('editVideoForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#edit_category_id').val()) {
                    showInlineError('edit_category_id', 'Please select a category');
                    hasError = true;
                }
                
                if (!$('#edit_video_title').val() || $('#edit_video_title').val().length < 3) {
                    showInlineError('edit_video_title', 'Video title must be at least 3 characters');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const updateBtn = $('#updateVideoBtn');
                updateBtn.prop('disabled', true);
                updateBtn.find('.btn-text').hide();
                updateBtn.find('.btn-loading').show();
                
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
                            $('#editVideoModal').modal('hide');
                            refreshDataTable();
                            showSuccessToast('Video updated successfully!');
                        } else {
                            showErrorToast(result.message);
                            if (result.message.includes('category')) {
                                showInlineError('edit_category_id', result.message);
                            } else if (result.message.includes('title')) {
                                showInlineError('edit_video_title', result.message);
                            }
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while updating the video.');
                    },
                    complete: function() {
                        // Hide loading state
                        updateBtn.prop('disabled', false);
                        updateBtn.find('.btn-loading').hide();
                        updateBtn.find('.btn-text').show();
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
                            // Wait for modal to hide, then show manage categories modal again
                            setTimeout(function() {
                                $('#manageCategoriesModal').modal('show');
                                loadCategories();
                                refreshCategoriesTable();
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
                if (deleteItemType === 'video') {
                    deleteVideoConfirm(deleteItemId);
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
                
                if (!$('#quick_video_title').val() || $('#quick_video_title').val().length < 3) {
                    showInlineError('quick_video_title', 'Video title must be at least 3 characters');
                    hasError = true;
                }
                
                if (!$('#quick_video_file')[0].files.length) {
                    showInlineError('quick_video_file', 'Please select a video file');
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
                
                // Handle disabled category field
                const categoryField = $('#quick_category_id');
                const wasDisabled = categoryField.prop('disabled');
                if (wasDisabled) {
                    categoryField.prop('disabled', false);
                }
                
                const formData = new FormData(this);
                formData.append('action', 'add_video');
                
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
                                showSuccessToast('Video uploaded successfully!');
                                
                                $('#quickAddForm').addClass('form-success');
                                setTimeout(() => {
                                    $('#quickAddForm').removeClass('form-success');
                                }, 600);
                                
                                resetQuickFormExceptCategory();
                                refreshDataTable();
                                
                                setTimeout(() => {
                                    $('#quick_video_title').focus();
                                }, 200);
                            } else {
                                showErrorToast(result.message);
                                if (result.message.includes('category')) {
                                    showInlineError('quick_category_id', result.message);
                                } else if (result.message.includes('title')) {
                                    showInlineError('quick_video_title', result.message);
                                } else if (result.message.includes('file') || result.message.includes('video')) {
                                    showInlineError('quick_video_file', result.message);
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            showErrorToast('Error processing server response');
                        }
                    },
                    error: function(xhr) {
                        showErrorToast('An error occurred while uploading the video.');
                        console.error('AJAX error:', xhr.responseText);
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false);
                        submitBtn.find('.btn-loading').hide();
                        submitBtn.find('.btn-text').show();
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
                            categorySelect.append(`<option value="${category.id}">${category.category_name}</option>`);
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
                                            </div>
                                            <div>
                                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editCategory(${category.id})">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(${category.id})">
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

        function viewVideo(videoId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_video', video_id: videoId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        // Set video source
                        const videoPath = '../../../' + data.video_file_path;
                        $('#previewVideoSource').attr('src', videoPath);
                        $('#previewVideoPlayer')[0].load();
                        
                        // Set video details
                        $('#previewVideoTitle').text(data.video_title);
                        $('#previewVideoDescription').text(data.description || 'No description');
                        $('#previewVideoCategory').text(data.category_name || 'No category');
                        
                        // Show modal
                        $('#videoPreviewModal').modal('show');
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading video');
                }
            });
        }

        function editVideo(videoId) {
            loadCategoriesForFilters();
            
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_video', video_id: videoId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        setTimeout(function() {
                            $('#edit_video_id').val(data.id);
                            $('#edit_category_id').val(data.category_id);
                            $('#edit_video_title').val(data.video_title);
                            $('#edit_description').val(data.description || '');
                            $('#current_file_name').text(data.video_file_name || '-');
                            
                            $('#editVideoModal').modal('show');
                        }, 200);
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading video data');
                }
            });
        }

        function deleteVideo(videoId) {
            deleteItemId = videoId;
            deleteItemType = 'video';
            $('#deleteModal').modal('show');
        }

        function deleteVideoConfirm(videoId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_video', video_id: videoId },
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

        function showAddCategoryForm() {
            // Hide the manage categories modal first
            $('#manageCategoriesModal').modal('hide');
            
            // Wait for the modal to fully hide before showing the new one
            setTimeout(function() {
                $('#category_id_edit').val('');
                $('#category_name').val('');
                $('#categoryAction').val('add_category');
                $('#categoryModalLabel').text('Add Category');
                
                // Show the category modal
                $('#categoryModal').modal('show');
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
                            $('#category_id_edit').val(data.id);
                            $('#category_name').val(data.category_name);
                            $('#categoryAction').val('update_category');
                            $('#categoryModalLabel').text('Edit Category');
                            
                            // Show the category modal
                            $('#categoryModal').modal('show');
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
                        loadCategories();
                        refreshCategoriesTable();
                        showSuccessToast(result.message);
                    } else {
                        showErrorToast(result.message);
                    }
                }
            });
        }

        // Load categories when manage categories modal is opened
        $('#manageCategoriesModal').on('show.bs.modal', function() {
            loadCategories();
        });
        


        // Enhanced modal event handlers
        $('#categoryModal').on('shown.bs.modal', function() {
            setTimeout(function() {
                $('#category_name').focus();
            }, 150);
        });

        // Reset edit form when modal is closed
        $('#editVideoModal').on('hidden.bs.modal', function() {
            $('#editVideoForm')[0].reset();
        });

        // Stop video when preview modal is closed
        $('#videoPreviewModal').on('hidden.bs.modal', function() {
            const videoPlayer = $('#previewVideoPlayer')[0];
            videoPlayer.pause();
            videoPlayer.currentTime = 0;
            $('#previewVideoSource').attr('src', '');
        });

        // Reset category form when modal is closed
        $('#categoryModal').on('hidden.bs.modal', function() {
            $('#categoryForm')[0].reset();
            $('#categoryAction').val('add_category');
            $('#categoryModalLabel').text('Add Category');
        });
        
        // Handle label clicks to focus inputs
        $(document).on('click', '.modal .form-label', function() {
            const targetId = $(this).attr('for');
            if (targetId) {
                $('#' + targetId).focus();
            }
        });

        // New workflow functions
        function showQuickAddPanel() {
            // Always ensure categories are loaded before showing the panel
            if ($('#quick_category_id option').length <= 1) {
                // Categories not loaded yet, load them first
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: { action: 'get_categories' },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            const quickCategorySelect = $('#quick_category_id');
                            const currentValue = quickCategorySelect.val();
                            const isDisabled = quickCategorySelect.prop('disabled');
                            
                            quickCategorySelect.empty().append('<option value="">Select Category</option>');
                            
                            result.data.forEach(function(category) {
                                quickCategorySelect.append(`<option value="${category.id}">${category.category_name}</option>`);
                            });
                            
                            // Restore previous state if it was locked to a category
                            if (currentValue && isDisabled) {
                                quickCategorySelect.val(currentValue);
                                quickCategorySelect.prop('disabled', true);
                                quickCategorySelect.addClass('bg-light');
                            }
                            
                            // Now show the panel
                            $('#quickAddPanel').slideDown(400, function() {
                                $('#quick_video_title').focus();
                            });
                        }
                    },
                    error: function() {
                        showErrorToast('Error loading categories');
                    }
                });
            } else {
                // Categories already loaded, just show the panel
                $('#quickAddPanel').slideDown(400, function() {
                    $('#quick_video_title').focus();
                });
            }
        }
        
        function showQuickAddPanelForCategory() {
            // This function is called when viewing a specific category
            // The category should already be set and locked
            showQuickAddPanel();
        }

        function hideQuickAddPanel() {
            $('#quickAddPanel').slideUp();
        }

        function clearQuickForm() {
            $('#quickAddForm')[0].reset();
            $('#quick_video_title').focus();
        }

        function resetQuickFormExceptCategory() {
            // Store the selected category and its state
            const selectedCategory = $('#quick_category_id').val();
            const isDisabled = $('#quick_category_id').prop('disabled');
            const hasLockClass = $('#quick_category_id').hasClass('bg-light');
            
            // Reset form fields
            $('#quick_video_title').val('');
            $('#quick_description').val('');
            $('#quick_video_file').val('');
            
            // Restore category selection and state
            $('#quick_category_id').val(selectedCategory);
            if (isDisabled) {
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
                            categoryFilter.append(`<option value="${category.id}">${category.category_name}</option>`);
                            quickCategorySelect.append(`<option value="${category.id}">${category.category_name}</option>`);
                            editCategorySelect.append(`<option value="${category.id}">${category.category_name}</option>`);
                        });
                    }
                }
            });
        }

        // New functions for category/video view switching
        function viewCategoryVideos(categoryId, categoryName) {
            console.log('Switching to videos view for category:', categoryId, categoryName);
            currentViewCategory = categoryId;
            
            // Update UI
            $('#categoriesTableContainer').hide();
            $('#categoriesActionPanel').hide();
            $('#videosTableContainer').show();
            $('#videosActionPanel').show();
            $('#breadcrumb').show();
            $('#videosTableTitle').text('Videos in "' + categoryName + '"');
            
            // Load categories first, then set and lock the selected category
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
                        
                        result.data.forEach(function(category) {
                            quickCategorySelect.append(`<option value="${category.id}">${category.category_name}</option>`);
                        });
                        
                        // Now set and lock the selected category
                        $('#quick_category_id').val(categoryId);
                        $('#quick_category_id').prop('disabled', true);
                        $('#quick_category_id').addClass('bg-light');
                    }
                }
            });
            
            // Reload videos table with category filter
            if (videosTable) {
                videosTable.ajax.reload(null, false);
            } else {
                console.error('Videos table not initialized');
            }
        }
        
        function showCategoriesView() {
            console.log('Switching back to categories view');
            currentViewCategory = null;
            
            // Update UI
            $('#videosTableContainer').hide();
            $('#videosActionPanel').hide();
            $('#quickAddPanel').hide();
            $('#categoriesTableContainer').show();
            $('#categoriesActionPanel').show();
            $('#breadcrumb').hide();
            
            // Unlock category dropdown when going back to categories view
            $('#quick_category_id').prop('disabled', false);
            $('#quick_category_id').removeClass('bg-light');
            $('#quick_category_id').val(''); // Clear selection
            
            // Reload categories table
            if (categoriesTable) {
                categoriesTable.ajax.reload(null, false);
            } else {
                console.error('Categories table not initialized');
            }
        }
        
        function refreshCategoriesTable() {
            if (categoriesTable) {
                categoriesTable.ajax.reload();
            }
        }

        // Enhanced refresh function with error handling
        function refreshDataTable() {
            try {
                const table = $('#videosTable').DataTable();
                if (table) {
                    table.ajax.reload(function() {
                        console.log('DataTable refreshed successfully');
                    }, false);
                } else {
                    console.error('DataTable not found');
                }
            } catch (error) {
                console.error('Error refreshing DataTable:', error);
                location.reload();
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
