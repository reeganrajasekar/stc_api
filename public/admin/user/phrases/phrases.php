<?php
require("../layout/Session.php");
require("../../config/db.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        // Main Phrases
        case 'get_main_phrases':
            getMainPhrases($conn);
            break;
        case 'get_main_phrase':
            getMainPhrase($conn);
            break;
        case 'add_main_phrase':
            addMainPhrase($conn);
            break;
        case 'update_main_phrase':
            updateMainPhrase($conn);
            break;
        case 'delete_main_phrase':
            deleteMainPhrase($conn);
            break;
            
        // Subcategories
        case 'get_subcategories':
            getSubcategories($conn);
            break;
        case 'get_subcategory':
            getSubcategory($conn);
            break;
        case 'add_subcategory':
            addSubcategory($conn);
            break;
        case 'update_subcategory':
            updateSubcategory($conn);
            break;
        case 'delete_subcategory':
            deleteSubcategory($conn);
            break;
            
        // Lessons
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
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}


// ==================== MAIN PHRASES FUNCTIONS ====================
function getMainPhrases($conn) {
    $sql = "SELECT * FROM phrases_main ORDER BY display_order ASC, created_at DESC";
    $result = $conn->query($sql);
    
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function getMainPhrase($conn) {
    $id = $_POST['id'] ?? 0;
    
    $sql = "SELECT * FROM phrases_main WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Main phrase not found']);
    }
}

function addMainPhrase($conn) {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 1);
        
        if (empty($title)) {
            throw new Exception('Title is required');
        }
        
        // Handle image upload
        $image_file = handleImageUpload('image_file', 'phrases/main');
        
        $sql = "INSERT INTO phrases_main (title, description, image_file, is_active, display_order) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $title, $description, $image_file, $is_active, $display_order);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Main phrase added successfully', 'id' => $conn->insert_id]);
        } else {
            throw new Exception('Error adding main phrase: ' . $conn->error);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}


function updateMainPhrase($conn) {
    try {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 1);
        
        if (empty($title)) {
            throw new Exception('Title is required');
        }
        
        // Get existing image
        $sql = "SELECT image_file FROM phrases_main WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $image_file = $existing['image_file'] ?? '';
        
        // Handle new image upload
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            // Delete old image
            if ($image_file && file_exists('../../../uploads/phrases/main/' . $image_file)) {
                unlink('../../../uploads/phrases/main/' . $image_file);
            }
            $image_file = handleImageUpload('image_file', 'phrases/main');
        }
        
        $sql = "UPDATE phrases_main SET title=?, description=?, image_file=?, is_active=?, display_order=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiii", $title, $description, $image_file, $is_active, $display_order, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Main phrase updated successfully']);
        } else {
            throw new Exception('Error updating main phrase: ' . $conn->error);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteMainPhrase($conn) {
    $id = $_POST['id'] ?? 0;
    
    // Get image file
    $sql = "SELECT image_file FROM phrases_main WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $sql = "DELETE FROM phrases_main WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Delete image file
        if ($row['image_file'] && file_exists('../../../uploads/phrases/main/' . $row['image_file'])) {
            unlink('../../../uploads/phrases/main/' . $row['image_file']);
        }
        echo json_encode(['success' => true, 'message' => 'Main phrase deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting main phrase: ' . $conn->error]);
    }
}


// ==================== SUBCATEGORIES FUNCTIONS ====================
function getSubcategories($conn) {
    $main_id = $_POST['main_id'] ?? 0;
    
    $sql = "SELECT s.*, m.title as main_title 
            FROM phrases_subcategories s 
            LEFT JOIN phrases_main m ON s.main_id = m.id 
            WHERE s.main_id = ? 
            ORDER BY s.display_order ASC, s.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $main_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function getSubcategory($conn) {
    $id = $_POST['id'] ?? 0;
    
    $sql = "SELECT * FROM phrases_subcategories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Subcategory not found']);
    }
}

function addSubcategory($conn) {
    try {
        $main_id = intval($_POST['main_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 1);
        
        if (!$main_id) {
            throw new Exception('Main phrase ID is required');
        }
        
        if (empty($title)) {
            throw new Exception('Title is required');
        }
        
        // Handle image upload
        $image_file = handleImageUpload('image_file', 'phrases/subcategories');
        
        $sql = "INSERT INTO phrases_subcategories (main_id, title, description, image_file, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssii", $main_id, $title, $description, $image_file, $is_active, $display_order);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Subcategory added successfully', 'id' => $conn->insert_id]);
        } else {
            throw new Exception('Error adding subcategory: ' . $conn->error);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}


function updateSubcategory($conn) {
    try {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 1);
        
        if (empty($title)) {
            throw new Exception('Title is required');
        }
        
        // Get existing image
        $sql = "SELECT image_file FROM phrases_subcategories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $image_file = $existing['image_file'] ?? '';
        
        // Handle new image upload
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            if ($image_file && file_exists('../../../uploads/phrases/subcategories/' . $image_file)) {
                unlink('../../../uploads/phrases/subcategories/' . $image_file);
            }
            $image_file = handleImageUpload('image_file', 'phrases/subcategories');
        }
        
        $sql = "UPDATE phrases_subcategories SET title=?, description=?, image_file=?, is_active=?, display_order=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiii", $title, $description, $image_file, $is_active, $display_order, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Subcategory updated successfully']);
        } else {
            throw new Exception('Error updating subcategory: ' . $conn->error);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteSubcategory($conn) {
    $id = $_POST['id'] ?? 0;
    
    // Get image file
    $sql = "SELECT image_file FROM phrases_subcategories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $sql = "DELETE FROM phrases_subcategories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($row['image_file'] && file_exists('../../../uploads/phrases/subcategories/' . $row['image_file'])) {
            unlink('../../../uploads/phrases/subcategories/' . $row['image_file']);
        }
        echo json_encode(['success' => true, 'message' => 'Subcategory deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting subcategory: ' . $conn->error]);
    }
}


// ==================== LESSONS FUNCTIONS ====================
function getLessons($conn) {
    $subcategory_id = $_POST['subcategory_id'] ?? 0;
    
    $sql = "SELECT l.*, s.title as subcategory_title 
            FROM phrases_lessons l 
            LEFT JOIN phrases_subcategories s ON l.subcategory_id = s.id 
            WHERE l.subcategory_id = ? 
            ORDER BY l.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subcategory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function getLesson($conn) {
    $id = $_POST['id'] ?? 0;
    
    $sql = "SELECT * FROM phrases_lessons WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lesson not found']);
    }
}

function addLesson($conn) {
    try {
        $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
        $phrase_title = trim($_POST['phrase_title'] ?? '');
        $meaning = trim($_POST['meaning'] ?? '');
        $examples = trim($_POST['examples'] ?? '');
        $is_active = intval($_POST['is_active'] ?? 1);
        
        if (!$subcategory_id) {
            throw new Exception('Subcategory ID is required');
        }
        
        if (empty($phrase_title)) {
            throw new Exception('Phrase title is required');
        }
        
        // Handle image upload
        $image_file = handleImageUpload('image_file', 'phrases/lessons');
        
        // Handle audio upload
        $audio_file = handleAudioUpload('audio_file', 'phrases/lessons');
        
        $sql = "INSERT INTO phrases_lessons (subcategory_id, phrase_title, meaning, examples, audio_file, image_file, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssi", $subcategory_id, $phrase_title, $meaning, $examples, $audio_file, $image_file, $is_active);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Lesson added successfully', 'id' => $conn->insert_id]);
        } else {
            throw new Exception('Error adding lesson: ' . $conn->error);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}


function updateLesson($conn) {
    try {
        $id = intval($_POST['id'] ?? 0);
        $phrase_title = trim($_POST['phrase_title'] ?? '');
        $meaning = trim($_POST['meaning'] ?? '');
        $examples = trim($_POST['examples'] ?? '');
        $is_active = intval($_POST['is_active'] ?? 1);
        
        if (empty($phrase_title)) {
            throw new Exception('Phrase title is required');
        }
        
        // Get existing files
        $sql = "SELECT image_file, audio_file FROM phrases_lessons WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $image_file = $existing['image_file'] ?? '';
        $audio_file = $existing['audio_file'] ?? '';
        
        // Handle new image upload
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            if ($image_file && file_exists('../../../uploads/phrases/lessons/' . $image_file)) {
                unlink('../../../uploads/phrases/lessons/' . $image_file);
            }
            $image_file = handleImageUpload('image_file', 'phrases/lessons');
        }
        
        // Handle new audio upload
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] == 0) {
            if ($audio_file && file_exists('../../../uploads/phrases/lessons/' . $audio_file)) {
                unlink('../../../uploads/phrases/lessons/' . $audio_file);
            }
            $audio_file = handleAudioUpload('audio_file', 'phrases/lessons');
        }
        
        $sql = "UPDATE phrases_lessons SET phrase_title=?, meaning=?, examples=?, audio_file=?, image_file=?, is_active=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssii", $phrase_title, $meaning, $examples, $audio_file, $image_file, $is_active, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Lesson updated successfully']);
        } else {
            throw new Exception('Error updating lesson: ' . $conn->error);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteLesson($conn) {
    $id = $_POST['id'] ?? 0;
    
    // Get files
    $sql = "SELECT image_file, audio_file FROM phrases_lessons WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $sql = "DELETE FROM phrases_lessons WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Delete files
        if ($row['image_file'] && file_exists('../../../uploads/phrases/lessons/' . $row['image_file'])) {
            unlink('../../../uploads/phrases/lessons/' . $row['image_file']);
        }
        if ($row['audio_file'] && file_exists('../../../uploads/phrases/lessons/' . $row['audio_file'])) {
            unlink('../../../uploads/phrases/lessons/' . $row['audio_file']);
        }
        echo json_encode(['success' => true, 'message' => 'Lesson deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting lesson: ' . $conn->error]);
    }
}


// ==================== HELPER FUNCTIONS ====================
function handleImageUpload($fieldName, $subFolder) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== 0) {
        return '';
    }
    
    $upload_dir = '../../../uploads/' . $subFolder . '/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $file_ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed)) {
        throw new Exception('Invalid image type. Only JPG, PNG, GIF allowed');
    }
    
    $filename = 'img_' . time() . '_' . uniqid() . '.' . $file_ext;
    
    if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $upload_dir . $filename)) {
        return $filename;
    }
    
    throw new Exception('Failed to upload image');
}

function handleAudioUpload($fieldName, $subFolder) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== 0) {
        return '';
    }
    
    $upload_dir = '../../../uploads/' . $subFolder . '/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed = ['mp3', 'wav', 'ogg', 'm4a'];
    $file_ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed)) {
        throw new Exception('Invalid audio type. Only MP3, WAV, OGG, M4A allowed');
    }
    
    $filename = 'audio_' . time() . '_' . uniqid() . '.' . $file_ext;
    
    if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $upload_dir . $filename)) {
        return $filename;
    }
    
    throw new Exception('Failed to upload audio');
}

?>

<?php include '../layout/Header.php'; ?>

<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">Phrases Management</h5>
    </div>
</div>


<!-- Main Phrases Action Buttons -->
<div class="row mb-3" id="mainPhrasesActionButtons">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <button class="btn btn-primary me-2" onclick="showAddMainPhraseModal()">
                    <i class="ri-add-line"></i> Add Main Phrase
                </button>
                <button class="btn btn-outline-success" onclick="refreshMainPhrasesTable()">
                    <i class="ri-refresh-line"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Subcategories Action Buttons -->
<div class="row mb-3" id="subcategoriesActionButtons" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <button class="btn btn-primary me-2" onclick="showAddSubcategoryModal()">
                    <i class="ri-add-line"></i> Add Subcategory
                </button>
                <button class="btn btn-outline-secondary me-2" onclick="backToMainPhrases()">
                    <i class="ri-arrow-left-line"></i> Back to Main Phrases
                </button>
                <button class="btn btn-outline-success" onclick="refreshSubcategoriesTable()">
                    <i class="ri-refresh-line"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Main Phrases View -->
<div class="row mb-3" id="mainPhrasesView">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="mainPhrasesTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Image</th>
                                <th>Display Order</th>
                                <th>Status</th>
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

<!-- Subcategories View -->
<div class="row mb-3" id="subcategoriesView" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="subcategoriesTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Image</th>
                                <th>Display Order</th>
                                <th>Status</th>
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


<!-- Add/Edit Main Phrase Modal -->
<div class="modal fade" id="mainPhraseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mainPhraseModalLabel">Add Main Phrase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="mainPhraseForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="main_phrase_id" name="id">
                    <input type="hidden" name="action" id="mainPhraseAction" value="add_main_phrase">
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" id="main_phrase_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="main_phrase_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control" name="image_file" accept="image/*" onchange="previewImage(this, 'main_phrase_image_preview')">
                                <div id="main_phrase_image_preview" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="main_phrase_display_order" name="display_order" value="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="main_phrase_is_active" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Subcategory Modal -->
<div class="modal fade" id="subcategoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subcategoryModalLabel">Add Subcategory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="subcategoryForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="subcategory_id" name="id">
                    <input type="hidden" id="subcategory_main_id" name="main_id">
                    <input type="hidden" name="action" id="subcategoryAction" value="add_subcategory">
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" id="subcategory_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="subcategory_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control" name="image_file" accept="image/*" onchange="previewImage(this, 'subcategory_image_preview')">
                                <div id="subcategory_image_preview" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="subcategory_display_order" name="display_order" value="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="subcategory_is_active" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Manage Lessons Off-canvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="manageLessonsOffcanvas" style="width: 50%; max-width: 1200px;">
    <div class="offcanvas-header bg-primary text-white">
        <h5 class="offcanvas-title">
            <i class="ri-book-line"></i> Manage Lessons - <span id="lessonSubcategoryName"></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <input type="hidden" id="current_subcategory_id">
        
        <button class="btn btn-sm btn-primary mb-3" onclick="showAddLessonModal()">
            <i class="ri-add-line"></i> Add Lesson
        </button>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Phrase Title</th>
                        <th>Meaning</th>
                        <th>Audio</th>
                        <th>Image</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="lessonsTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Lesson Modal -->
<div class="modal fade" id="lessonModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lessonModalLabel">Add Lesson</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="lessonForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="lesson_id" name="id">
                    <input type="hidden" id="lesson_subcategory_id" name="subcategory_id">
                    <input type="hidden" name="action" id="lessonAction" value="add_lesson">
                    
                    <div class="mb-3">
                        <label class="form-label">Phrase Title *</label>
                        <input type="text" class="form-control" id="lesson_phrase_title" name="phrase_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Meaning</label>
                        <input type="text" class="form-control" id="lesson_meaning" name="meaning">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Examples</label>
                        <textarea class="form-control" id="lesson_examples" name="examples" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Audio File</label>
                                <input type="file" class="form-control" name="audio_file" accept="audio/*" onchange="previewAudio(this, 'lesson_audio_preview')">
                                <div id="lesson_audio_preview" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control" name="image_file" accept="image/*" onchange="previewImage(this, 'lesson_image_preview')">
                                <div id="lesson_image_preview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="lesson_is_active" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
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
let mainPhrasesTable;
let subcategoriesTable;
let currentMainId = null;
let currentSubcategoryId = null;
let deleteItemId = null;
let deleteItemType = null;

$(document).ready(function() {
    initializeMainPhrasesTable();
    
    // Main Phrase Form
    $('#mainPhraseForm').on('submit', function(e) {
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
                    $('#mainPhraseModal').modal('hide');
                    mainPhrasesTable.ajax.reload();
                    showToast('success', result.message);
                } else {
                    showToast('error', result.message);
                }
            }
        });
    });
    
    // Subcategory Form
    $('#subcategoryForm').on('submit', function(e) {
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
                    $('#subcategoryModal').modal('hide');
                    subcategoriesTable.ajax.reload();
                    showToast('success', result.message);
                } else {
                    showToast('error', result.message);
                }
            }
        });
    });
    
    // Lesson Form
    $('#lessonForm').on('submit', function(e) {
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
                    $('#lessonModal').modal('hide');
                    loadLessons(currentSubcategoryId);
                    showToast('success', result.message);
                } else {
                    showToast('error', result.message);
                }
            }
        });
    });
    
    // Delete confirmation
    $('#confirmDelete').on('click', function() {
        if (deleteItemType === 'main_phrase') {
            deleteMainPhraseConfirm(deleteItemId);
        } else if (deleteItemType === 'subcategory') {
            deleteSubcategoryConfirm(deleteItemId);
        } else if (deleteItemType === 'lesson') {
            deleteLessonConfirm(deleteItemId);
        }
    });
});


// Initialize Main Phrases Table
function initializeMainPhrasesTable() {
    mainPhrasesTable = $('#mainPhrasesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '',
            type: 'POST',
            data: { action: 'get_main_phrases' },
            dataSrc: 'data'
        },
        columns: [
            { data: 'id', width: '5%' },
            { data: 'title', width: '25%' },
            { data: 'description', width: '30%', defaultContent: '' },
            { 
                data: 'image_file', 
                width: '10%',
                render: function(data) {
                    if (data) {
                        return `<img src="../../../uploads/phrases/main/${data}" style="max-width: 50px; max-height: 50px;" class="img-thumbnail">`;
                    }
                    return '-';
                }
            },
            { data: 'display_order', width: '10%' },
            { 
                data: 'is_active', 
                width: '10%',
                render: function(data) {
                    return data == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                }
            },
            {
                data: null,
                width: '10%',
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="viewSubcategories(${row.id}, '${row.title.replace(/'/g, "\\'")}')" title="View Subcategories">
                                <i class="ri-eye-line"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="editMainPhrase(${row.id})" title="Edit">
                                <i class="ri-edit-line"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteMainPhrase(${row.id})" title="Delete">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[4, 'asc']],
        pageLength: 25
    });
}

// View Subcategories
function viewSubcategories(mainId, mainTitle) {
    currentMainId = mainId;
    
    $('#mainPhrasesView').hide();
    $('#mainPhrasesActionButtons').hide();
    $('#subcategoriesView').show();
    $('#subcategoriesActionButtons').show();
    
    if (subcategoriesTable) {
        subcategoriesTable.destroy();
    }
    
    subcategoriesTable = $('#subcategoriesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '',
            type: 'POST',
            data: { action: 'get_subcategories', main_id: mainId },
            dataSrc: 'data'
        },
        columns: [
            { data: 'id', width: '5%' },
            { data: 'title', width: '25%' },
            { data: 'description', width: '30%', defaultContent: '' },
            { 
                data: 'image_file', 
                width: '10%',
                render: function(data) {
                    if (data) {
                        return `<img src="../../../uploads/phrases/subcategories/${data}" style="max-width: 50px; max-height: 50px;" class="img-thumbnail">`;
                    }
                    return '-';
                }
            },
            { data: 'display_order', width: '10%' },
            { 
                data: 'is_active', 
                width: '10%',
                render: function(data) {
                    return data == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                }
            },
            {
                data: null,
                width: '10%',
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="manageLessons(${row.id}, '${row.title.replace(/'/g, "\\'")}')" title="Manage Lessons">
                                <i class="ri-book-line"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="editSubcategory(${row.id})" title="Edit">
                                <i class="ri-edit-line"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteSubcategory(${row.id})" title="Delete">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[4, 'asc']],
        pageLength: 25
    });
}

// Back to Main Phrases
function backToMainPhrases() {
    currentMainId = null;
    
    $('#subcategoriesView').hide();
    $('#subcategoriesActionButtons').hide();
    $('#mainPhrasesView').show();
    $('#mainPhrasesActionButtons').show();
    
    if (subcategoriesTable) {
        subcategoriesTable.destroy();
        subcategoriesTable = null;
    }
}

// Manage Lessons
function manageLessons(subcategoryId, subcategoryTitle) {
    currentSubcategoryId = subcategoryId;
    $('#current_subcategory_id').val(subcategoryId);
    $('#lessonSubcategoryName').text(subcategoryTitle);
    
    loadLessons(subcategoryId);
    
    // Show off-canvas instead of modal
    const offcanvas = new bootstrap.Offcanvas(document.getElementById('manageLessonsOffcanvas'));
    offcanvas.show();
}

function loadLessons(subcategoryId) {
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'get_lessons', subcategory_id: subcategoryId },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                const lessons = result.data;
                let html = '';
                
                if (lessons.length === 0) {
                    html = '<tr><td colspan="7" class="text-center text-muted">No lessons found. Click "Add Lesson" to create one.</td></tr>';
                } else {
                    lessons.forEach(function(lesson) {
                        const statusBadge = lesson.is_active == 1 ? 
                            '<span class="badge bg-success">Active</span>' : 
                            '<span class="badge bg-secondary">Inactive</span>';
                        
                        const audioPreview = lesson.audio_file ? 
                            `<audio controls style="width: 150px; height: 30px;"><source src="../../../uploads/phrases/lessons/${lesson.audio_file}"></audio>` : 
                            '-';
                        
                        const imagePreview = lesson.image_file ? 
                            `<img src="../../../uploads/phrases/lessons/${lesson.image_file}" style="max-width: 50px; max-height: 50px;" class="img-thumbnail">` : 
                            '-';
                        
                        html += `
                            <tr>
                                <td>${lesson.id}</td>
                                <td>${lesson.phrase_title}</td>
                                <td>${lesson.meaning || '-'}</td>
                                <td>${audioPreview}</td>
                                <td>${imagePreview}</td>
                                <td>${statusBadge}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary" onclick="editLesson(${lesson.id})" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteLesson(${lesson.id})" title="Delete">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                }
                
                $('#lessonsTableBody').html(html);
            }
        }
    });
}

// Show Add Modals
function showAddMainPhraseModal() {
    $('#mainPhraseForm')[0].reset();
    $('#mainPhraseAction').val('add_main_phrase');
    $('#mainPhraseModalLabel').text('Add Main Phrase');
    $('#main_phrase_id').val('');
    $('#main_phrase_image_preview').html('');
    $('#mainPhraseModal').modal('show');
}

function showAddSubcategoryModal() {
    $('#subcategoryForm')[0].reset();
    $('#subcategoryAction').val('add_subcategory');
    $('#subcategoryModalLabel').text('Add Subcategory');
    $('#subcategory_id').val('');
    $('#subcategory_main_id').val(currentMainId);
    $('#subcategory_image_preview').html('');
    $('#subcategoryModal').modal('show');
}

function showAddLessonModal() {
    $('#lessonForm')[0].reset();
    $('#lessonAction').val('add_lesson');
    $('#lessonModalLabel').text('Add Lesson');
    $('#lesson_id').val('');
    $('#lesson_subcategory_id').val(currentSubcategoryId);
    $('#lesson_image_preview').html('');
    $('#lesson_audio_preview').html('');
    $('#lessonModal').modal('show');
}

// Edit Functions
function editMainPhrase(id) {
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'get_main_phrase', id: id },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                const data = result.data;
                $('#main_phrase_id').val(data.id);
                $('#main_phrase_title').val(data.title);
                $('#main_phrase_description').val(data.description || '');
                $('#main_phrase_display_order').val(data.display_order);
                $('#main_phrase_is_active').val(data.is_active);
                
                if (data.image_file) {
                    $('#main_phrase_image_preview').html(`<img src="../../../uploads/phrases/main/${data.image_file}" class="img-thumbnail" style="max-width: 150px;">`);
                }
                
                $('#mainPhraseAction').val('update_main_phrase');
                $('#mainPhraseModalLabel').text('Edit Main Phrase');
                $('#mainPhraseModal').modal('show');
            }
        }
    });
}

function editSubcategory(id) {
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'get_subcategory', id: id },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                const data = result.data;
                $('#subcategory_id').val(data.id);
                $('#subcategory_main_id').val(data.main_id);
                $('#subcategory_title').val(data.title);
                $('#subcategory_description').val(data.description || '');
                $('#subcategory_display_order').val(data.display_order);
                $('#subcategory_is_active').val(data.is_active);
                
                if (data.image_file) {
                    $('#subcategory_image_preview').html(`<img src="../../../uploads/phrases/subcategories/${data.image_file}" class="img-thumbnail" style="max-width: 150px;">`);
                }
                
                $('#subcategoryAction').val('update_subcategory');
                $('#subcategoryModalLabel').text('Edit Subcategory');
                $('#subcategoryModal').modal('show');
            }
        }
    });
}

function editLesson(id) {
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'get_lesson', id: id },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                const data = result.data;
                $('#lesson_id').val(data.id);
                $('#lesson_subcategory_id').val(data.subcategory_id);
                $('#lesson_phrase_title').val(data.phrase_title);
                $('#lesson_meaning').val(data.meaning || '');
                $('#lesson_examples').val(data.examples || '');
                $('#lesson_is_active').val(data.is_active);
                
                if (data.image_file) {
                    $('#lesson_image_preview').html(`<img src="../../../uploads/phrases/lessons/${data.image_file}" class="img-thumbnail" style="max-width: 150px;">`);
                }
                
                if (data.audio_file) {
                    $('#lesson_audio_preview').html(`<audio controls style="width: 100%;"><source src="../../../uploads/phrases/lessons/${data.audio_file}"></audio>`);
                }
                
                $('#lessonAction').val('update_lesson');
                $('#lessonModalLabel').text('Edit Lesson');
                $('#lessonModal').modal('show');
            }
        }
    });
}

// Delete Functions
function deleteMainPhrase(id) {
    deleteItemId = id;
    deleteItemType = 'main_phrase';
    $('#deleteModal').modal('show');
}

function deleteMainPhraseConfirm(id) {
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'delete_main_phrase', id: id },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                $('#deleteModal').modal('hide');
                mainPhrasesTable.ajax.reload();
                showToast('success', result.message);
            } else {
                showToast('error', result.message);
            }
        }
    });
}

function deleteSubcategory(id) {
    deleteItemId = id;
    deleteItemType = 'subcategory';
    $('#deleteModal').modal('show');
}

function deleteSubcategoryConfirm(id) {
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'delete_subcategory', id: id },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                $('#deleteModal').modal('hide');
                subcategoriesTable.ajax.reload();
                showToast('success', result.message);
            } else {
                showToast('error', result.message);
            }
        }
    });
}

function deleteLesson(id) {
    deleteItemId = id;
    deleteItemType = 'lesson';
    $('#deleteModal').modal('show');
}

function deleteLessonConfirm(id) {
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'delete_lesson', id: id },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                $('#deleteModal').modal('hide');
                loadLessons(currentSubcategoryId);
                showToast('success', result.message);
            } else {
                showToast('error', result.message);
            }
        }
    });
}

// Refresh Functions
function refreshMainPhrasesTable() {
    if (mainPhrasesTable) {
        mainPhrasesTable.ajax.reload();
    }
}

function refreshSubcategoriesTable() {
    if (subcategoriesTable) {
        subcategoriesTable.ajax.reload();
    }
}

// Preview Functions
function previewImage(input, previewId) {
    const preview = $('#' + previewId);
    preview.html('');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.html(`<img src="${e.target.result}" class="img-thumbnail" style="max-width: 150px;">`);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewAudio(input, previewId) {
    const preview = $('#' + previewId);
    preview.html('');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.html(`<audio controls style="width: 100%;"><source src="${e.target.result}"></audio>`);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Toast Notifications
function showToast(type, message) {
    const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
    const icon = type === 'success' ? 'ri-check-line' : 'ri-error-warning-line';
    
    const toastId = 'toast-' + Date.now();
    const toast = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="${icon} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    if ($('#toastContainer').length === 0) {
        $('body').append('<div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>');
    }
    
    $('#toastContainer').append(toast);
    const toastElement = new bootstrap.Toast(document.getElementById(toastId), { delay: 3000 });
    toastElement.show();
    
    $('#' + toastId).on('hidden.bs.toast', function() {
        $(this).remove();
    });
}
</script>

<?php include '../layout/Footer.php'; ?>
