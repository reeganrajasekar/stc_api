<?php
require("../layout/Session.php");
require("../../config/db.php");

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
        case 'get_image':
            getImageFile($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function getImageFile($conn) {
    $question_id = $_POST['question_id'] ?? 0;
    
    $sql = "SELECT image_files FROM picture_capture_questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['image_files'])) {
            $imagePath = $row['image_files'];
            $filePath = '../../../' . $imagePath;
            
            if (file_exists($filePath)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                
                if (!$mimeType || strpos($mimeType, 'image/') !== 0) {
                    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $mimeType = match($extension) {
                        'jpg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        default => 'image/jpeg'
                    };
                }
                
                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . filesize($filePath));
                header('Cache-Control: public, max-age=3600');
                readfile($filePath);
                exit;
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
        $draw = intval($params['draw'] ?? 1);
        $start = intval($params['start'] ?? 0);
        $length = intval($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';
        $orderColumn = intval($params['order'][0]['column'] ?? 0);
        $orderDir = $params['order'][0]['dir'] ?? 'desc';
        
        $columns = [
            0 => 'pq.question_id',
            1 => 'pq.question_text', 
            2 => 'pc.category_name',
            3 => 'pq.image_files',
            4 => 'pq.hint',
            5 => 'pq.is_active',
            6 => 'pq.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'pq.created_at') . ' ' . $orderDir;
        $where = " WHERE 1=1 ";
        
        $categoryFilter = $params['category_filter'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND pq.category_id = " . intval($categoryFilter) . " ";
        }
        
        $totalSql = "SELECT COUNT(*) as total FROM picture_capture_questions pq LEFT JOIN picture_capture pc ON pq.category_id = pc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (pq.question_text LIKE '%$search%' OR pc.category_name LIKE '%$search%' OR pq.hint LIKE '%$search%') ";
        }
        
        $filteredSql = "SELECT COUNT(*) as total FROM picture_capture_questions pq LEFT JOIN picture_capture pc ON pq.category_id = pc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        $sql = "SELECT pq.question_id, pq.question_text, pq.category_id, pq.image_files, pq.hint, 
                       pq.is_active, pq.created_at, pc.category_name
                FROM picture_capture_questions pq 
                LEFT JOIN picture_capture pc ON pq.category_id = pc.category_id 
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
                    'image_files' => $row['image_files'],
                    'hint' => $row['hint'] ?? '',
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
    
    $sql = "SELECT pq.question_id, pq.category_id, pq.question_text, pq.image_files, pq.hint, 
                   pq.is_active, pq.created_at, pc.category_name 
            FROM picture_capture_questions pq 
            LEFT JOIN picture_capture pc ON pq.category_id = pc.category_id 
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
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $question_text = trim($_POST['question_text'] ?? '');
        $hint = trim($_POST['hint'] ?? '');
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($question_text)) {
            throw new Exception('Question text is required');
        }
        
        // Handle multiple image files upload
        $imageData = handleImageUpload();
        if (empty($imageData)) {
            throw new Exception('At least one image file is required');
        }
        
        // Begin database transaction
        $conn->begin_transaction();
        
        try {
            // Store image files as JSON
            $imageFilesJson = json_encode($imageData);
            
            $sql = "INSERT INTO picture_capture_questions (category_id, question_text, image_files, hint, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            $stmt->bind_param("isssi", $category_id, $question_text, $imageFilesJson, $hint, $is_active);
            
            if (!$stmt->execute()) {
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $conn->commit();
            
            error_log("Question added successfully: ID $insertId, Category: $category_id, Images: " . count($imageData));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Question added successfully', 
                'id' => $insertId,
                'images_uploaded' => count($imageData)
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            // Clean up uploaded files if database insert failed
            foreach ($imageData as $imagePath) {
                if (file_exists('../../../' . $imagePath)) {
                    unlink('../../../' . $imagePath);
                }
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add Question Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateQuestion($conn) {
    try {
        $question_id = $_POST['question_id'] ?? 0;
        $category_id = $_POST['category_id'] ?? 0;
        $question_text = $_POST['question_text'] ?? '';
        $hint = $_POST['hint'] ?? '';
        $is_active = $_POST['is_active'] ?? 1;
        
        // Get current data
        $currentSql = "SELECT image_files FROM picture_capture_questions WHERE question_id = ?";
        $currentStmt = $conn->prepare($currentSql);
        $currentStmt->bind_param("i", $question_id);
        $currentStmt->execute();
        $currentResult = $currentStmt->get_result();
        $currentData = $currentResult->fetch_assoc();
        
        $imageFilesJson = $currentData['image_files'] ?? '[]';
        
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
        
        // Add any new uploaded images
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
        
        // Update question
        $sql = "UPDATE picture_capture_questions SET category_id=?, question_text=?, image_files=?, hint=?, is_active=? WHERE question_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("isssii", $category_id, $question_text, $imageFilesJson, $hint, $is_active, $question_id);
        
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
    $getFilesSql = "SELECT image_files FROM picture_capture_questions WHERE question_id = ?";
    $getStmt = $conn->prepare($getFilesSql);
    $getStmt->bind_param("i", $question_id);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $fileData = $result->fetch_assoc();
    
    $sql = "DELETE FROM picture_capture_questions WHERE question_id = ?";
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
        
        echo json_encode(['success' => true, 'message' => 'Question deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting question: ' . $conn->error]);
    }
}

function getCategories($conn) {
    $sql = "SELECT category_id, category_name, category_description, 
                   display_order, is_active, created_at, points
            FROM picture_capture 
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
    
    $sql = "SELECT * FROM picture_capture WHERE category_id = ?";
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
            $adjustSql = "UPDATE picture_capture SET display_order = display_order + 1 WHERE display_order >= ?";
            $adjustStmt = $conn->prepare($adjustSql);
            $adjustStmt->bind_param("i", $display_order);
            $adjustStmt->execute();
            $adjustStmt->close();
        }
        
        // Insert the new category
        $sql = "INSERT INTO picture_capture (category_name, category_description, display_order, points) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $category_name, $category_description, $display_order, $points);
        
        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Category added successfully']);
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
        $getCurrentSql = "SELECT display_order FROM picture_capture WHERE category_id = ?";
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
                $adjustSql = "UPDATE picture_capture SET display_order = display_order + 1 
                             WHERE display_order >= ? AND display_order < ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $new_display_order, $old_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            } else {
                // Moving DOWN (e.g., from 2 to 5)
                // Decrement positions 3, 4, 5 by 1 (old position + 1 to target position)
                $adjustSql = "UPDATE picture_capture SET display_order = display_order - 1 
                             WHERE display_order > ? AND display_order <= ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $old_display_order, $new_display_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            }
        }
        
        // Update the category with new position
        $sql = "UPDATE picture_capture SET category_name=?, category_description=?, display_order=?, points=? WHERE category_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiii", $category_name, $category_description, $new_display_order, $points, $category_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
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
    
    $sql = "DELETE FROM picture_capture WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $conn->error]);
    }
}

/**
 * Handle multiple image files upload
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
        
        // Validation
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        
        // Check file extension
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Image " . ($i + 1) . ": Invalid file type. Allowed: " . implode(', ', $allowedExtensions));
        }
        
        // Check file size
        if ($file['size'] > $maxFileSize) {
            throw new Exception("Image " . ($i + 1) . ": File too large. Maximum size: 10MB");
        }
        
        // Validate image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception("Image " . ($i + 1) . ": Invalid image file or corrupted");
        }
        
        // Verify dimensions
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
        
        error_log("Image file uploaded successfully: {$relativePath}");
    }
    
    return $imageFiles;
}

?>

<?php include '../layout/Header.php'; ?>

<style>
#quickAddPanel {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.img-thumbnail {
    border: 2px solid #dee2e6;
    transition: all 0.3s ease;
}

.img-thumbnail:hover {
    border-color: #0d6efd;
    transform: scale(1.05);
}

.image-viewer-item img {
    transition: all 0.3s ease;
}

.image-viewer-item img:hover {
    transform: scale(1.05);
    border-color: #0d6efd !important;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}
</style>
 
<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">Picture Capture - Speaking Practice</h5>
    </div>
</div>

<!-- Top Action Buttons - Categories View -->
<div class="row mb-3" id="categoriesActionButtons">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
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

<!-- Top Action Buttons - Questions View -->
<div class="row mb-3" id="questionsActionButtons" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <button class="btn btn-success me-2" onclick="showAddQuestionModal()">
                    <i class="ri-add-line"></i> Add Question
                </button>
                <button class="btn btn-outline-secondary me-2" onclick="backToCategoriesView()">
                    <i class="ri-arrow-left-line"></i> Back to Categories
                </button>
                <button class="btn btn-outline-success" onclick="refreshQuestionsTable()">
                    <i class="ri-refresh-line"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Categories View (Initially Visible) -->
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
                                <th>Display Order</th>
                                <th>Points</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
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
                <h6 class="mb-0"><i class="ri-lightning-line"></i> Quick Add Question</h6>
                <button class="btn btn-sm btn-outline-light" onclick="hideQuickAddPanel()">
                    <i class="ri-close-line"></i> Close
                </button>
            </div>
            <div class="card-body">
                <form id="addQuestionForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_question">
                    <input type="hidden" id="hidden_category_id" name="hidden_category_id" value="">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" id="quick_category_select" name="category_id" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Question Text *</label>
                        <textarea class="form-control" name="question_text" rows="3" required placeholder="Enter the question or prompt for the speaking exercise..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Image Files * (Multiple)</label>
                        <input type="file" class="form-control" name="image_files[]" id="quick_image_files" accept="image/*" multiple onchange="previewQuickImages(this)">
                        <div class="form-text">Supported: JPG, PNG, GIF, WebP (Max 10MB per image). Select multiple images at once.</div>
                        <div id="quick_image_preview" class="mt-3" style="display: none;">
                            <div class="row g-2" id="quick_preview_container"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Hint (Optional)</label>
                        <textarea class="form-control" name="hint" rows="2" placeholder="Provide a helpful hint for students..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" onclick="hideQuickAddPanel()">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="ri-add-line"></i> Add Question
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Questions View (Initially Hidden) -->
<div class="row" id="questionsView" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="questionsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Question</th>
                                <th>Category</th>
                                <th>Image</th>
                                <th>Hint</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Question Modal -->
<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editQuestionForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_question">
                    <input type="hidden" name="question_id" id="edit_question_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select class="form-select" name="category_id" id="edit_category_id" required>
                            <option value="">Select Category</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Question Text *</label>
                        <textarea class="form-control" name="question_text" id="edit_question_text" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Image</label>
                        <div id="current_image_preview"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Add More Images (Optional)</label>
                        <input type="file" class="form-control" name="image_files[]" id="edit_image_files" accept="image/*" multiple onchange="previewEditImages(this)">
                        <div class="form-text">Select additional images to add. You can remove existing images by clicking the X button.</div>
                        <div id="edit_image_preview" class="mt-3" style="display: none;">
                            <label class="form-label text-success">New Images:</label>
                            <div class="row g-2" id="edit_preview_container"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Hint (Optional)</label>
                        <textarea class="form-control" name="hint" id="edit_hint" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="edit_is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
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

<!-- Add Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalLabel">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="categoryForm">
                <div class="modal-body">
                    <input type="hidden" name="category_id" id="category_id">
                    <input type="hidden" name="action" id="categoryAction" value="add_category">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" class="form-control" name="category_name" id="category_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="category_description" id="category_description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" class="form-control" name="display_order" id="display_order" min="0" value="0">
                                <div class="form-text">0 = Auto (append to end)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Points *</label>
                                <input type="number" class="form-control" name="points" id="points" min="1" max="100" value="10" required>
                                <div class="form-text">Points: 1-100</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="category_is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
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

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageViewerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Question Images</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 id="imageViewerQuestionText"></h6>
                </div>
                <div class="row g-3" id="imageViewerContainer">
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
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
let questionsTable;
let deleteItemId = null;
let deleteItemType = null;
let currentCategoryId = null;
let currentCategoryName = null;

$(document).ready(function() {
    // Initialize Categories DataTable
    categoriesTable = $('#categoriesTable').DataTable({
        processing: true,
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
            { 
                data: 'category_description',
                render: function(data) {
                    return data || '-';
                }
            },
            { 
                data: 'display_order',
                render: function(data) {
                    return data || '0';
                }
            },
            { 
                data: 'points',
                render: function(data) {
                    return data || '10';
                }
            },
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
                        <button class="btn btn-sm btn-primary me-1" onclick="viewCategoryQuestions(${row.category_id}, '${row.category_name.replace(/'/g, "\\'")}')" title="View Questions">
                            <i class="ri-eye-line"></i> View
                        </button>
                        <button class="btn btn-sm btn-warning me-1" onclick="editCategory(${row.category_id})" title="Edit">
                            <i class="ri-edit-line"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteCategory(${row.category_id})" title="Delete">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    `;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10
    });
    
    // Initialize Questions DataTable (will be loaded when viewing a category)
    questionsTable = $('#questionsTable').DataTable({
        processing: true,
        serverSide: true,
        deferLoading: 0, // Don't load data on initialization
        ajax: {
            url: '',
            type: 'POST',
            data: function(d) {
                d.action = 'get_questions';
                d.category_filter = currentCategoryId;
                return d;
            }
        },
        columns: [
            { data: 'question_id' },
            { 
                data: 'question_text',
                render: function(data) {
                    return data.length > 50 ? data.substring(0, 50) + '...' : data;
                }
            },
            { data: 'category_name' },
            { 
                data: 'image_files',
                render: function(data, type, row) {
                    if (data) {
                        try {
                            const imageFiles = JSON.parse(data);
                            if (Array.isArray(imageFiles) && imageFiles.length > 0) {
                                // Escape data for onclick
                                const questionText = (row.question_text || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                                const imageFilesEscaped = data.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                                
                                return `
                                    <button class="btn btn-sm btn-primary" onclick='viewQuestionImages(${row.question_id}, "${questionText}", \`${imageFilesEscaped}\`)' title="View Images">
                                        <i class="ri-image-line"></i> ${imageFiles.length} Image${imageFiles.length > 1 ? 's' : ''}
                                    </button>
                                `;
                            }
                        } catch (e) {
                            console.error('Error parsing image files:', e);
                        }
                    }
                    return '<span class="text-muted">No Images</span>';
                }
            },
            { 
                data: 'hint',
                render: function(data) {
                    return data ? (data.length > 30 ? data.substring(0, 30) + '...' : data) : '-';
                }
            },
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
                        <button class="btn btn-sm btn-warning me-1" onclick="editQuestion(${row.question_id})" title="Edit">
                            <i class="ri-edit-line"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteQuestion(${row.question_id})" title="Delete">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    `;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10
    });
    
    loadCategories();
    
    $('#addQuestionForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validate images
        if (quickImageFiles.length === 0) {
            showToast('error', 'Please select at least one image');
            return;
        }
        
        const formData = new FormData();
        
        // Manually add form fields (excluding file input)
        formData.append('action', 'add_question');
        formData.append('question_text', $('textarea[name="question_text"]').val());
        formData.append('hint', $('textarea[name="hint"]').val());
        formData.append('is_active', $('select[name="is_active"]').val());
        
        // Handle category - use hidden field if select is disabled
        const categorySelect = $('#quick_category_select');
        const hiddenCategoryId = $('#hidden_category_id');
        
        if (categorySelect.prop('disabled') && hiddenCategoryId.val()) {
            formData.append('category_id', hiddenCategoryId.val());
        } else {
            formData.append('category_id', categorySelect.val());
        }
        
        // Add image files from array
        quickImageFiles.forEach((file, index) => {
            formData.append('image_files[]', file);
        });
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    // Don't hide panel, just reset form for continuous adding
                    $('#addQuestionForm')[0].reset();
                    $('#quick_image_preview').hide();
                    $('#quick_preview_container').empty();
                    quickImageFiles = [];
                    
                    // Re-lock category if viewing specific category
                    if (currentCategoryId) {
                        setTimeout(function() {
                            categorySelect.val(currentCategoryId);
                            categorySelect.prop('disabled', true);
                            categorySelect.addClass('bg-light');
                            hiddenCategoryId.val(currentCategoryId);
                        }, 100);
                    }
                    
                    questionsTable.ajax.reload();
                    showToast('success', result.message);
                } else {
                    showToast('error', result.message);
                }
            },
            error: function() {
                showToast('error', 'An error occurred while adding the question');
            }
        });
    });
    
    $('#editQuestionForm').on('submit', function(e) {
        e.preventDefault();
        
        // Store quick panel state before submission
        const quickCategoryValue = $('#quick_category_select').val();
        const quickCategoryDisabled = $('#quick_category_select').prop('disabled');
        const quickCategoryHasLockClass = $('#quick_category_select').hasClass('bg-light');
        const quickHiddenCategoryValue = $('#hidden_category_id').val();
        
        const formData = new FormData(this);
        
        // Collect remaining current images (after removals)
        const remainingIndices = [];
        $('#current_image_preview .col-md-3[id^="current_image_"]').each(function() {
            const id = $(this).attr('id');
            const index = parseInt(id.replace('current_image_', ''));
            if (!isNaN(index)) {
                remainingIndices.push(index);
            }
        });
        
        // Add kept indices to form data
        formData.append('keep_current_image_indices', JSON.stringify(remainingIndices));
        
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
                    
                    // Restore quick panel state after modal closes
                    setTimeout(function() {
                        if (quickCategoryValue) {
                            $('#quick_category_select').val(quickCategoryValue);
                            if (quickCategoryDisabled) {
                                $('#quick_category_select').prop('disabled', true);
                            }
                            if (quickCategoryHasLockClass) {
                                $('#quick_category_select').addClass('bg-light');
                            }
                            if (quickHiddenCategoryValue) {
                                $('#hidden_category_id').val(quickHiddenCategoryValue);
                            }
                        }
                    }, 100);
                    
                    questionsTable.ajax.reload();
                    showToast('success', result.message);
                    keptImageIndices = []; // Reset
                } else {
                    showToast('error', result.message);
                }
            }
        });
    });
    
    $('#categoryForm').on('submit', function(e) {
        e.preventDefault();
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
                    categoriesTable.ajax.reload();
                    loadCategories();
                    showToast('success', result.message);
                    $('#categoryForm')[0].reset();
                } else {
                    showToast('error', result.message);
                }
            }
        });
    });
    
    $('#confirmDelete').on('click', function() {
        if (deleteItemType === 'question') {
            deleteQuestionConfirm(deleteItemId);
        } else if (deleteItemType === 'category') {
            deleteCategoryConfirm(deleteItemId);
        }
    });
});

function loadCategories(callback) {
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'get_categories' },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                const categorySelect = $('select[name="category_id"]');
                
                categorySelect.empty().append('<option value="">Select Category</option>');
                
                result.data.forEach(function(category) {
                    categorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                });
                
                // Execute callback if provided
                if (typeof callback === 'function') {
                    callback();
                }
            }
        }
    });
}

function viewCategoryQuestions(categoryId, categoryName) {
    currentCategoryId = categoryId;
    currentCategoryName = categoryName;
    
    // Hide categories view, show questions view
    $('#categoriesView').hide();
    $('#categoriesActionButtons').hide();
    $('#questionsView').show();
    $('#questionsActionButtons').show();
    
    // Reload questions table with category filter
    questionsTable.ajax.reload();
}

function backToCategoriesView() {
    currentCategoryId = null;
    currentCategoryName = null;
    
    // Show categories view, hide questions view
    $('#questionsView').hide();
    $('#questionsActionButtons').hide();
    $('#categoriesView').show();
    $('#categoriesActionButtons').show();
    
    // Reload categories table
    categoriesTable.ajax.reload();
}

function refreshCategoriesTable() {
    categoriesTable.ajax.reload();
}

function refreshQuestionsTable() {
    questionsTable.ajax.reload();
}

function showAddQuestionModal() {
    // Load categories first, then set the category after loading completes
    loadCategories(function() {
        const categorySelect = $('#quick_category_select');
        const hiddenCategoryId = $('#hidden_category_id');
        
        // Pre-select and lock current category if viewing questions from a specific category
        if (currentCategoryId) {
            categorySelect.val(currentCategoryId);
            categorySelect.prop('disabled', true);
            categorySelect.addClass('bg-light');
            hiddenCategoryId.val(currentCategoryId);
        } else {
            // Unlock category if not viewing specific category
            categorySelect.prop('disabled', false);
            categorySelect.removeClass('bg-light');
            hiddenCategoryId.val('');
        }
    });
    
    // Show quick add panel
    $('#quickAddPanel').slideDown();
    
    // Scroll to the panel
    $('html, body').animate({
        scrollTop: $('#quickAddPanel').offset().top - 100
    }, 500);
}

function hideQuickAddPanel() {
    $('#quickAddPanel').slideUp();
    $('#addQuestionForm')[0].reset();
    $('#quick_image_preview').hide();
    $('#quick_preview_container').empty();
    quickImageFiles = []; // Reset image array
    
    // Unlock category field
    const categorySelect = $('#quick_category_select');
    const hiddenCategoryId = $('#hidden_category_id');
    
    categorySelect.prop('disabled', false);
    categorySelect.removeClass('bg-light');
    hiddenCategoryId.val('');
}

// Track which current images to keep (for edit form)
let keptImageIndices = [];

function removeCurrentImage(index) {
    // Remove from visual display
    $(`#current_image_${index}`).fadeOut(300, function() {
        $(this).remove();
        
        // Update the count badge
        const remainingCount = $('#current_image_preview .col-md-3').length;
        $('#current_image_preview .badge').text(`${remainingCount} current image(s)`);
        
        if (remainingCount === 0) {
            $('#current_image_preview').html('<span class="text-muted">No images (at least one image required)</span>');
        }
    });
    
    // Remove from kept indices array
    const indexPos = keptImageIndices.indexOf(index);
    if (indexPos > -1) {
        keptImageIndices.splice(indexPos, 1);
    }
}

// Global array to store selected files for quick add
let quickImageFiles = [];

function previewQuickImages(input) {
    const previewContainer = $('#quick_preview_container');
    const previewSection = $('#quick_image_preview');
    
    // Add new files to the array
    if (input.files && input.files.length > 0) {
        Array.from(input.files).forEach(file => {
            quickImageFiles.push(file);
        });
    }
    
    // Clear input to allow selecting same files again
    input.value = '';
    
    // Update preview
    updateQuickImagesPreview();
}

function updateQuickImagesPreview() {
    const previewContainer = $('#quick_preview_container');
    const previewSection = $('#quick_image_preview');
    
    previewContainer.empty();
    
    if (quickImageFiles.length > 0) {
        previewSection.show();
        
        // Show count badge
        const countBadge = `<div class="col-12"><span class="badge bg-primary">${quickImageFiles.length} image(s) selected</span></div>`;
        previewContainer.append(countBadge);
        
        // Loop through all files
        quickImageFiles.forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const imageHtml = `
                    <div class="col-md-2 col-sm-3 col-3" id="quick_image_${index}">
                        <div class="position-relative">
                            <img src="${e.target.result}" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: contain;">
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" onclick="removeQuickImage(${index})" title="Remove">
                                <i class="ri-close-line"></i>
                            </button>
                            <div class="mt-1 small text-muted text-truncate" title="${file.name}">Image ${index + 1}</div>
                        </div>
                    </div>
                `;
                previewContainer.append(imageHtml);
            };
            
            reader.readAsDataURL(file);
        });
    } else {
        previewSection.hide();
    }
}

function removeQuickImage(index) {
    // Remove from array
    quickImageFiles.splice(index, 1);
    
    // Update preview
    updateQuickImagesPreview();
}

function previewEditImages(input) {
    const previewContainer = $('#edit_preview_container');
    const previewSection = $('#edit_image_preview');
    
    previewContainer.empty();
    
    if (input.files && input.files.length > 0) {
        previewSection.show();
        
        // Loop through all selected files
        Array.from(input.files).forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const imageHtml = `
                    <div class="col-md-3 col-sm-4 col-6">
                        <div class="position-relative">
                            <img src="${e.target.result}" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                            <div class="mt-1 small text-muted text-truncate" title="${file.name}">New Image ${index + 1}</div>
                        </div>
                    </div>
                `;
                previewContainer.append(imageHtml);
            };
            
            reader.readAsDataURL(file);
        });
        
        // Show count
        const countBadge = `<div class="col-12"><span class="badge bg-success">${input.files.length} new image(s) will be added</span></div>`;
        previewContainer.prepend(countBadge);
    } else {
        previewSection.hide();
    }
}

function showAddCategoryModal() {
    $('#category_id').val('');
    $('#category_name').val('');
    $('#category_description').val('');
    $('#display_order').val('0');
    $('#points').val('10');
    $('#category_is_active').val('1');
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
                $('#category_id').val(data.category_id);
                $('#category_name').val(data.category_name);
                $('#category_description').val(data.category_description || '');
                $('#display_order').val(data.display_order || 0);
                $('#points').val(data.points || 10);
                $('#category_is_active').val(data.is_active);
                $('#categoryAction').val('update_category');
                $('#categoryModalLabel').text('Edit Category');
                $('#categoryModal').modal('show');
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
                categoriesTable.ajax.reload();
                showToast('success', result.message);
            } else {
                showToast('error', result.message);
            }
        }
    });
}

function editQuestion(questionId) {
    // Store current quick panel state before loading categories
    const quickCategoryValue = $('#quick_category_select').val();
    const quickCategoryDisabled = $('#quick_category_select').prop('disabled');
    const quickCategoryHasLockClass = $('#quick_category_select').hasClass('bg-light');
    const quickHiddenCategoryValue = $('#hidden_category_id').val();
    
    // Load categories for edit modal
    loadCategories(function() {
        // Restore quick panel state after categories are loaded
        if (quickCategoryValue) {
            $('#quick_category_select').val(quickCategoryValue);
            if (quickCategoryDisabled) {
                $('#quick_category_select').prop('disabled', true);
            }
            if (quickCategoryHasLockClass) {
                $('#quick_category_select').addClass('bg-light');
            }
            if (quickHiddenCategoryValue) {
                $('#hidden_category_id').val(quickHiddenCategoryValue);
            }
        }
    });
    
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'get_question', question_id: questionId },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                const data = result.data;
                
                // Clear previous preview
                $('#edit_image_preview').hide();
                $('#edit_preview_container').empty();
                $('#edit_image_file').val('');
                
                // Set form values
                $('#edit_question_id').val(data.question_id);
                
                // Set category after a short delay to ensure categories are loaded
                setTimeout(function() {
                    $('#edit_category_id').val(data.category_id);
                    
                    // Restore quick panel state again (in case it was reset)
                    if (quickCategoryValue) {
                        $('#quick_category_select').val(quickCategoryValue);
                        if (quickCategoryDisabled) {
                            $('#quick_category_select').prop('disabled', true);
                        }
                        if (quickCategoryHasLockClass) {
                            $('#quick_category_select').addClass('bg-light');
                        }
                        if (quickHiddenCategoryValue) {
                            $('#hidden_category_id').val(quickHiddenCategoryValue);
                        }
                    }
                }, 200);
                
                $('#edit_question_text').val(data.question_text);
                $('#edit_hint').val(data.hint || '');
                $('#edit_is_active').val(data.is_active);
                
                // Show current images
                if (data.image_files) {
                    try {
                        const imageFiles = JSON.parse(data.image_files);
                        if (Array.isArray(imageFiles) && imageFiles.length > 0) {
                            let imagesHtml = '<div class="row g-2">';
                            imagesHtml += `<div class="col-12"><span class="badge bg-info">${imageFiles.length} current image(s)</span></div>`;
                            imageFiles.forEach((imagePath, index) => {
                                imagesHtml += `
                                    <div class="col-md-3 col-sm-4 col-6" id="current_image_${index}">
                                        <div class="position-relative">
                                            <img src="../../../${imagePath}" class="img-thumbnail" style="width: 100%; height: 120px; object-fit: contain;" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2RkZCIvPjwvc3ZnPg=='">
                                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" onclick="removeCurrentImage(${index})" title="Remove">
                                                <i class="ri-close-line"></i>
                                            </button>
                                            <div class="mt-1 small text-muted">Image ${index + 1}</div>
                                        </div>
                                    </div>
                                `;
                            });
                            imagesHtml += '</div>';
                            $('#current_image_preview').html(imagesHtml);
                        } else {
                            $('#current_image_preview').html('<span class="text-muted">No images</span>');
                        }
                    } catch (e) {
                        console.error('Error parsing image files:', e);
                        $('#current_image_preview').html('<span class="text-danger">Error loading images</span>');
                    }
                } else {
                    $('#current_image_preview').html('<span class="text-muted">No images</span>');
                }
                
                $('#editQuestionModal').modal('show');
            }
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
                questionsTable.ajax.reload();
                showToast('success', result.message);
            } else {
                showToast('error', result.message);
            }
        }
    });
}

function filterByCategory() {
    questionsTable.ajax.reload();
}

function refreshTable() {
    questionsTable.ajax.reload();
}

function viewQuestionImages(questionId, questionText, imageFilesJson) {
    $('#imageViewerModalLabel').text(`Question Images - ID: ${questionId}`);
    $('#imageViewerQuestionText').text(questionText);
    
    // Clear previous images
    const container = $('#imageViewerContainer');
    container.empty();
    
    try {
        const imageFiles = JSON.parse(imageFilesJson);
        if (Array.isArray(imageFiles) && imageFiles.length > 0) {
            imageFiles.forEach((imagePath, index) => {
                const imageDiv = $(`
                    <div class="col-md-4 col-sm-6">
                        <div class="image-viewer-item">
                            <img src="../../../${imagePath}" alt="Image ${index + 1}" class="img-fluid rounded" style="width: 100%; height: 200px; object-fit: contain; cursor: pointer; border: 2px solid #dee2e6;" onclick="openImageFullscreen(this)" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjE1MCIgeT0iMTAwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTYiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5JbWFnZSBOb3QgRm91bmQ8L3RleHQ+PC9zdmc+'">
                            <div class="text-center mt-2">
                                <small class="text-muted">Image ${index + 1}</small>
                            </div>
                        </div>
                    </div>
                `);
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
                max-width: 90%;
                max-height: 90%;
                object-fit: contain;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            ">
            <div style="
                position: absolute;
                top: 20px;
                right: 20px;
                color: white;
                font-size: 32px;
                cursor: pointer;
                background: rgba(0,0,0,0.5);
                width: 50px;
                height: 50px;
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
        if (e.target === this || e.target.tagName === 'IMG') {
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

function showToast(type, message) {
    const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
    const icon = type === 'success' ? 'ri-check-line' : 'ri-error-warning-line';
    
    const toast = $(`
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div class="toast show" role="alert">
                <div class="toast-header ${bgClass} text-white">
                    <i class="${icon} me-2"></i>
                    <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
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
