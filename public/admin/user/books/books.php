<?php
require("../layout/Session.php");
require("../../config/db.php");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'get_books':
            getBooks($conn);
            break;
        case 'get_book':
            getBook($conn);
            break;
        case 'add_book':
            addBook($conn);
            break;
        case 'update_book':
            updateBook($conn);
            break;
        case 'delete_book':
            deleteBook($conn);
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
        case 'get_books_by_category':
            getBooksByCategory($conn);
            break;
        case 'duplicate_book':
            duplicateBook($conn);
            break;
        case 'get_categories_with_count':
            getCategoriesWithCount($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function getBooks($conn) {
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
            0 => 'b.book_id',
            1 => 'b.book_title', 
            2 => 'b.book_author',
            3 => 'bc.category_name',
            4 => 'b.is_popular',
            5 => 'b.is_recommended',
            6 => 'b.is_active',
            7 => 'b.created_at'
        ];
        
        $orderBy = ($columns[$orderColumn] ?? 'b.created_at') . ' ' . $orderDir;
        
        // Base filtering
        $where = " WHERE 1=1 ";
        
        // Apply category filter if provided
        $categoryFilter = $params['category_filter'] ?? '';
        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $where .= " AND b.category_id = " . intval($categoryFilter) . " ";
        }
        
        // Total records count (with category filter applied)
        $totalSql = "SELECT COUNT(*) as total FROM books b LEFT JOIN book_categories bc ON b.category_id = bc.category_id $where";
        $totalResult = $conn->query($totalSql);
        $totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
        
        // Apply search filter
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $where .= " AND (b.book_title LIKE '%$search%' OR b.book_author LIKE '%$search%' OR bc.category_name LIKE '%$search%') ";
        }
        
        // Filtered count
        $filteredSql = "SELECT COUNT(*) as total FROM books b LEFT JOIN book_categories bc ON b.category_id = bc.category_id $where";
        $filteredResult = $conn->query($filteredSql);
        $totalFiltered = $filteredResult ? $filteredResult->fetch_assoc()['total'] : 0;
        
        // Pagination
        $limit = $length > 0 ? "LIMIT $start, $length" : "";
        
        // Main data query
        $sql = "SELECT b.book_id, b.book_title, b.book_author, b.category_id, b.thumbnail, b.pdf_file,
                       b.is_popular, b.is_recommended, b.is_active, b.created_at, bc.category_name
                FROM books b 
                LEFT JOIN book_categories bc ON b.category_id = bc.category_id 
                $where 
                ORDER BY $orderBy 
                $limit";
        
        $result = $conn->query($sql);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'book_id' => $row['book_id'],
                    'book_title' => $row['book_title'],
                    'book_author' => $row['book_author'],
                    'category_name' => $row['category_name'] ?? 'No Category',
                    'category_id' => $row['category_id'],
                    'thumbnail' => $row['thumbnail'] ?? '',
                    'pdf_file' => $row['pdf_file'] ?? '',
                    'is_popular' => $row['is_popular'],
                    'is_recommended' => $row['is_recommended'],
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

function getBook($conn) {
    $book_id = $_POST['book_id'] ?? 0;
    
    $sql = "SELECT b.book_id, b.category_id, b.book_title, b.book_author, b.thumbnail, b.pdf_file,
                   b.is_popular, b.is_recommended, b.is_active, b.created_at, b.updated_at, bc.category_name 
            FROM books b 
            LEFT JOIN book_categories bc ON b.category_id = bc.category_id 
            WHERE b.book_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Book not found']);
    }
}

function addBook($conn) {
    try {
        // Validate and sanitize input data
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $book_title = trim($_POST['book_title'] ?? '');
        $book_author = trim($_POST['book_author'] ?? '');
        $is_popular = filter_var($_POST['is_popular'] ?? 0, FILTER_VALIDATE_INT);
        $is_recommended = filter_var($_POST['is_recommended'] ?? 0, FILTER_VALIDATE_INT);
        $is_active = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
        
        // Input validation
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($book_title) || strlen($book_title) < 3) {
            throw new Exception('Book title must be at least 3 characters long');
        }
        
        if (empty($book_author) || strlen($book_author) < 2) {
            throw new Exception('Book author must be at least 2 characters long');
        }
        
        // Handle file uploads
        $thumbnail = '';
        $pdf_file = '';
        $upload_dir = '../../../uploads/books/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Handle thumbnail upload
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
            $allowed_image = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_image)) {
                $thumbnail = 'thumb_' . time() . '_' . uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_dir . $thumbnail);
            }
        }
        
        // Handle PDF upload
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
            $file_ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
            
            if ($file_ext === 'pdf') {
                $pdf_file = 'book_' . time() . '_' . uniqid() . '.pdf';
                move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_dir . $pdf_file);
            }
        }
        
        // Begin database transaction
        $conn->begin_transaction();
        
        try {
            $sql = "INSERT INTO books (category_id, book_title, book_author, thumbnail, pdf_file, is_popular, is_recommended, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            
            $stmt->bind_param("issssiii", 
                $category_id, $book_title, $book_author, $thumbnail, $pdf_file,
                $is_popular, $is_recommended, $is_active
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Database insert failed: ' . $stmt->error);
            }
            
            $insertId = $conn->insert_id;
            $conn->commit();
            
            error_log("Book added successfully: ID $insertId, Category: $category_id");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Book added successfully', 
                'id' => $insertId
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add Book Error: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateBook($conn) {
    try {
        $book_id = $_POST['book_id'] ?? 0;
        $category_id = $_POST['category_id'] ?? 0;
        $book_title = trim($_POST['book_title'] ?? '');
        $book_author = trim($_POST['book_author'] ?? '');
        $is_popular = $_POST['is_popular'] ?? 0;
        $is_recommended = $_POST['is_recommended'] ?? 0;
        $is_active = $_POST['is_active'] ?? 1;
        
        // Input validation
        if (!$category_id || $category_id <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        if (empty($book_title) || strlen($book_title) < 3) {
            throw new Exception('Book title must be at least 3 characters long');
        }
        
        if (empty($book_author) || strlen($book_author) < 2) {
            throw new Exception('Book author must be at least 2 characters long');
        }
        
        // Get existing book data
        $sql = "SELECT thumbnail, pdf_file FROM books WHERE book_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        
        $thumbnail = $existing['thumbnail'] ?? '';
        $pdf_file = $existing['pdf_file'] ?? '';
        $upload_dir = '../../../uploads/books/';
        
        // Handle thumbnail upload
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
            $allowed_image = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_image)) {
                // Delete old thumbnail
                if ($thumbnail && file_exists($upload_dir . $thumbnail)) {
                    unlink($upload_dir . $thumbnail);
                }
                $thumbnail = 'thumb_' . time() . '_' . uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_dir . $thumbnail);
            }
        }
        
        // Handle PDF upload
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
            $file_ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
            
            if ($file_ext === 'pdf') {
                // Delete old PDF
                if ($pdf_file && file_exists($upload_dir . $pdf_file)) {
                    unlink($upload_dir . $pdf_file);
                }
                $pdf_file = 'book_' . time() . '_' . uniqid() . '.pdf';
                move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_dir . $pdf_file);
            }
        }
        
        $sql = "UPDATE books SET category_id=?, book_title=?, book_author=?, thumbnail=?, pdf_file=?, is_popular=?, is_recommended=?, is_active=?, updated_at=NOW() WHERE book_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("issssiiii", $category_id, $book_title, $book_author, $thumbnail, $pdf_file, $is_popular, $is_recommended, $is_active, $book_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Book updated successfully']);
        } else {
            throw new Exception('Error updating book: ' . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Update Book Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteBook($conn) {
    $book_id = $_POST['book_id'] ?? 0;
    
    // Get file paths before deleting
    $sql = "SELECT thumbnail, pdf_file FROM books WHERE book_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    
    $sql = "DELETE FROM books WHERE book_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    
    if ($stmt->execute()) {
        // Delete files
        $upload_dir = '../../../uploads/books/';
        if ($book['thumbnail'] && file_exists($upload_dir . $book['thumbnail'])) {
            unlink($upload_dir . $book['thumbnail']);
        }
        if ($book['pdf_file'] && file_exists($upload_dir . $book['pdf_file'])) {
            unlink($upload_dir . $book['pdf_file']);
        }
        echo json_encode(['success' => true, 'message' => 'Book deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting book: ' . $conn->error]);
    }
}

function getCategories($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'book_categories'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    $sql = "SELECT * FROM book_categories ORDER BY category_order ASC, category_name ASC";
    $result = $conn->query($sql);
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $categories]);
}



function addCategory($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'book_categories'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    $category_name = $_POST['category_name'] ?? '';
    $category_order = intval($_POST['category_order'] ?? 0);
    
    if (empty($category_name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        return;
    }
    
    try {
        $conn->begin_transaction();
        
        // Adjust existing display orders if inserting at specific position
        if ($category_order > 0) {
            // Increment all positions >= target position by 1
            $adjustSql = "UPDATE book_categories SET category_order = category_order + 1 WHERE category_order >= ?";
            $adjustStmt = $conn->prepare($adjustSql);
            $adjustStmt->bind_param("i", $category_order);
            $adjustStmt->execute();
            $adjustStmt->close();
        }
        
        // Insert the new category
        $sql = "INSERT INTO book_categories (category_name, category_order) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $category_name, $category_order);
        
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
    $new_category_order = intval($_POST['category_order'] ?? 0);
    
    try {
        $conn->begin_transaction();
        
        // Get current display order
        $getCurrentSql = "SELECT category_order FROM book_categories WHERE category_id = ?";
        $getCurrentStmt = $conn->prepare($getCurrentSql);
        $getCurrentStmt->bind_param("i", $category_id);
        $getCurrentStmt->execute();
        $result = $getCurrentStmt->get_result();
        $current = $result->fetch_assoc();
        $old_category_order = intval($current['category_order'] ?? 0);
        $getCurrentStmt->close();
        
        // Only adjust if display order actually changed
        if ($new_category_order != $old_category_order && $new_category_order > 0 && $old_category_order > 0) {
            if ($new_category_order < $old_category_order) {
                // Moving UP (e.g., from 5 to 2)
                // Increment positions 2, 3, 4 by 1 (target position to old position - 1)
                $adjustSql = "UPDATE book_categories SET category_order = category_order + 1 
                             WHERE category_order >= ? AND category_order < ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $new_category_order, $old_category_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            } else {
                // Moving DOWN (e.g., from 2 to 5)
                // Decrement positions 3, 4, 5 by 1 (old position + 1 to target position)
                $adjustSql = "UPDATE book_categories SET category_order = category_order - 1 
                             WHERE category_order > ? AND category_order <= ? AND category_id != ?";
                $adjustStmt = $conn->prepare($adjustSql);
                $adjustStmt->bind_param("iii", $old_category_order, $new_category_order, $category_id);
                $adjustStmt->execute();
                $adjustStmt->close();
            }
        }
        
        // Update the category with new position
        $sql = "UPDATE book_categories SET category_name=?, category_order=? WHERE category_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $category_name, $new_category_order, $category_id);
        
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
    
    $sql = "DELETE FROM book_categories WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $conn->error]);
    }
}

function getBooksByCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT b.*, bc.category_name 
            FROM books b 
            LEFT JOIN book_categories bc ON b.category_id = bc.category_id 
            WHERE b.category_id = ? 
            ORDER BY b.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $books]);
}

function duplicateBook($conn) {
    $book_id = $_POST['book_id'] ?? 0;
    
    // Get original book
    $sql = "SELECT * FROM books WHERE book_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Insert duplicate with modified title
        $new_book_title = $row['book_title'] . ' (Copy)';
        
        $sql = "INSERT INTO books (category_id, book_title, book_author, thumbnail, pdf_file, is_popular, is_recommended, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssiii", 
            $row['category_id'], 
            $new_book_title, 
            $row['book_author'], 
            $row['thumbnail'], 
            $row['pdf_file'], 
            $row['is_popular'], 
            $row['is_recommended'], 
            $row['is_active']
        );
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Book duplicated successfully', 'new_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error duplicating book: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Original book not found']);
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
            $sql = "INSERT INTO books (category_id, book_title, book_author, thumbnail, pdf_file, is_popular, is_recommended, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssiii", 
                $item['category_id'] ?? 1,
                $item['book_title'] ?? '',
                $item['book_author'] ?? '',
                $item['thumbnail'] ?? '',
                $item['pdf_file'] ?? '',
                $item['is_popular'] ?? 0,
                $item['is_recommended'] ?? 0,
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

function getCategory($conn) {
    $category_id = $_POST['category_id'] ?? 0;
    
    $sql = "SELECT * FROM book_categories WHERE category_id = ?";
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

function getCategoriesWithCount($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'book_categories'");
    if ($tableCheck->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Categories table does not exist. Please run the database schema first.']);
        return;
    }
    
    $sql = "SELECT bc.category_id, bc.category_name, bc.category_order, bc.created_at, 
                   COUNT(b.book_id) as book_count
            FROM book_categories bc 
            LEFT JOIN books b ON bc.category_id = b.category_id 
            GROUP BY bc.category_id, bc.category_name, bc.category_order, bc.created_at
            ORDER BY bc.category_order ASC, bc.category_name ASC";
    
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
        <h5 class="h4 text-primary fw-bolder m-0">Books Management</h5>
      
    </div>
</div>

<!-- Categories View Action Panel -->
<div class="row mb-3" id="categoriesActionPanel">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <button class="btn btn-outline-secondary me-2" onclick="showAddCategoryForm()">
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

<!-- Books View Action Panel -->
<div class="row mb-3" id="booksActionPanel" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <button class="btn btn-primary me-2" onclick="showQuickAddPanel()">
                            <i class="ri-add-line"></i> Add Book
                        </button>
                        
                        <button class="btn btn-outline-secondary me-2" onclick="showCategoriesView()">
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
                <h6 class="mb-0"><i class="ri-lightning-line"></i> Quick Add Book</h6>
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
                                <select class="form-select" id="quick_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Book Author *</label>
                                <input type="text" class="form-control" id="quick_book_author" name="book_author" required placeholder="Enter author name...">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Book Title *</label>
                        <input type="text" class="form-control" id="quick_book_title" name="book_title" required placeholder="Enter book title...">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Thumbnail Image</label>
                                <input type="file" class="form-control" id="quick_thumbnail" name="thumbnail" accept="image/*" onchange="previewQuickThumbnail(this)">
                                <small class="text-muted">JPG, PNG, GIF</small>
                                <div id="quick_thumbnail_preview" style="display: none; margin-top: 10px;">
                                    <div class="thumbnail-preview-container">
                                        <img id="quick_thumbnail_img" src="" alt="Thumbnail Preview" style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;">
                                        <button type="button" class="btn btn-sm btn-danger ms-2" onclick="removeQuickThumbnail()" title="Remove">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">PDF File</label>
                                <input type="file" class="form-control" id="quick_pdf_file" name="pdf_file" accept=".pdf">
                                <small class="text-muted">PDF format only</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="quick_is_popular" name="is_popular" value="1">
                                    <label class="form-check-label" for="quick_is_popular">
                                        Popular
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="quick_is_recommended" name="is_recommended" value="1">
                                    <label class="form-check-label" for="quick_is_recommended">
                                        Recommended
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
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

<!-- Categories Table -->
<div class="row" id="categoriesTableContainer">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Book Categories</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="categoriesTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Book Count</th>
                                <th>Display Order</th>
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

<!-- Books Table -->
<div class="row" id="booksTableContainer" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0" id="booksTableTitle">Books in Category</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="booksTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Thumbnail</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Popular</th>
                                <th>Recommended</th>
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

    <!-- Edit Book Modal -->
    <div class="modal fade" id="editBookModal" tabindex="-1" aria-labelledby="editBookModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBookModalLabel">Edit Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editBookForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="edit_book_id" name="book_id">
                        <input type="hidden" name="action" value="update_book">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_category_id" class="form-label">Category *</label>
                                    <select class="form-select" id="edit_category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_book_author" class="form-label">Author *</label>
                                    <input type="text" class="form-control" id="edit_book_author" name="book_author" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_book_title" class="form-label">Book Title *</label>
                            <input type="text" class="form-control" id="edit_book_title" name="book_title" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_thumbnail" class="form-label">Thumbnail Image</label>
                                    <input type="file" class="form-control" id="edit_thumbnail" name="thumbnail" accept="image/*" onchange="previewEditThumbnail(this)">
                                    <small class="text-muted">Leave empty to keep current image</small>
                                    
                                    <!-- Current thumbnail display -->
                                    <div id="current_thumbnail_display" style="display: none; margin-top: 10px;">
                                        <small class="text-muted">Current Thumbnail:</small>
                                        <div class="mt-2">
                                            <img id="current_thumbnail_img" src="" alt="Current Thumbnail" style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                    </div>
                                    
                                    <!-- New thumbnail preview -->
                                    <div id="edit_thumbnail_preview" style="display: none; margin-top: 10px;">
                                        <small class="text-success">New Thumbnail Preview:</small>
                                        <div class="thumbnail-preview-container mt-2">
                                            <img id="edit_thumbnail_img" src="" alt="New Thumbnail Preview" style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;">
                                            <button type="button" class="btn btn-sm btn-danger ms-2" onclick="removeEditThumbnail()" title="Remove">
                                                <i class="ri-close-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_pdf_file" class="form-label">PDF File</label>
                                    <input type="file" class="form-control" id="edit_pdf_file" name="pdf_file" accept=".pdf">
                                    <small class="text-muted">Leave empty to keep current PDF</small>
                                    <div id="current_pdf"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_is_popular" name="is_popular" value="1">
                                        <label class="form-check-label" for="edit_is_popular">
                                            Popular
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_is_recommended" name="is_recommended" value="1">
                                        <label class="form-check-label" for="edit_is_recommended">
                                            Recommended
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
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
                        <button type="submit" class="btn btn-primary" id="updateBookBtn">
                            <span class="btn-text">Update Book</span>
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
    
    <!-- PDF Viewer Modal -->
    <div class="modal fade" id="pdfViewerModal" tabindex="-1" aria-labelledby="pdfViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfViewerModalLabel">PDF Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="height: 80vh;">
                    <iframe id="pdfFrame" style="width: 100%; height: 100%; border: none;"></iframe>
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
                            <input type="text" class="form-control" id="category_name" name="category_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="category_order" name="category_order" value="0">
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
                                <li>Each passage should have: passage_title, passage_text</li>
                                <li>Optional fields: category_id, pronunciation_tips, points, is_active</li>
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
    "passage_title": "The Fox and the Dog",
    "passage_text": "The quick brown fox jumps over the lazy dog. This sentence contains every letter of the alphabet.",
    "pronunciation_tips": "Focus on clear enunciation of each word",
    "points": 15,
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        let booksTable;
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
                    { data: 'category_id' },
                    { data: 'category_name' },
                    { 
                        data: 'book_count',
                        render: function(data, type, row) {
                            return data || 0;
                        }
                    },
                    { 
                        data: 'category_order',
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
                               
                                    <button class="btn btn-primary me-2" onclick="viewCategoryBooks(${row.category_id}, '${safeCategoryName}')" title="View Books">
                                        <i class="ri-book-line"></i> View Books
                                    </button>
                                    <button class="btn btn-warning me-2" onclick="editCategory(${row.category_id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-danger me-2" onclick="deleteCategory(${row.category_id})" title="Delete">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                               
                            `;
                        }
                    }
                ],
                order: [[3, 'asc'], [1, 'asc']],
                pageLength: 10,
                responsive: true
            });

            // Initialize Books DataTable (but don't load data yet)
            booksTable = $('#booksTable').DataTable({
                processing: true,
                serverSide: true,
                deferLoading: 0, // Don't load data initially
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_books';
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
                    { data: 'book_id' },
                    { 
                        data: 'thumbnail',
                        render: function(data, type, row) {
                            if (data && data.trim() !== '') {
                                return `<img src="../../../uploads/books/${data}" alt="Thumbnail" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHZpZXdCb3g9IjAgMCA1MCA1MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjUwIiBoZWlnaHQ9IjUwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0yNSAyMEMyNi4zODA3IDIwIDI3LjUgMTguODgwNyAyNy41IDE3LjVDMjcuNSAxNi4xMTkzIDI2LjM4MDcgMTUgMjUgMTVDMjMuNjE5MyAxNSAyMi41IDE2LjExOTMgMjIuNSAxNy41QzIyLjUgMTguODgwNyAyMy42MTkzIDIwIDI1IDIwWiIgZmlsbD0iIzlDQTNBRiIvPgo8cGF0aCBkPSJNMzUgMzVIMTVWMzBMMjAgMjVMMjUgMzBMMzAgMjVMMzUgMzBWMzVaIiBmaWxsPSIjOUNBM0FGIi8+Cjwvc3ZnPgo=';">`;
                            } else {
                                return '<span class="text-muted">No Image</span>';
                            }
                        }
                    },
                    { 
                        data: 'book_title',
                        render: function(data, type, row) {
                            return data.length > 30 ? data.substring(0, 30) + '...' : data;
                        }
                    },
                    { 
                        data: 'book_author',
                        render: function(data, type, row) {
                            return data.length > 25 ? data.substring(0, 25) + '...' : data;
                        }
                    },
                    { 
                        data: 'is_popular',
                        render: function(data, type, row) {
                            return data == 1 ? '<span class="badge bg-warning">Popular</span>' : '-';
                        }
                    },
                    { 
                        data: 'is_recommended',
                        render: function(data, type, row) {
                            return data == 1 ? '<span class="badge bg-info">Recommended</span>' : '-';
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
                            let viewPdfBtn = '';
                            if (row.pdf_file) {
                                viewPdfBtn = `<button class="btn btn-info me-2" onclick="viewPDF('${row.pdf_file}')" title="View PDF">
                                    <i class="ri-file-pdf-line"></i>
                                </button>`;
                            }
                            return `
                               
                                    ${viewPdfBtn}
                                    <button class="btn btn-primary me-2" onclick="editBook(${row.book_id})" title="Edit">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button class="btn btn-warning me-2" onclick="duplicateBook(${row.book_id})" title="Duplicate">
                                        <i class="ri-file-copy-line"></i>
                                    </button>
                                    <button class="btn btn-danger me-2" onclick="deleteBook(${row.book_id})" title="Delete">
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

            // Edit book form submission
            $('#editBookForm').on('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('editBookForm');
                
                // Client-side validation
                let hasError = false;
                
                if (!$('#edit_category_id').val()) {
                    showInlineError('edit_category_id', 'Please select a category');
                    hasError = true;
                }
                
                if (!$('#edit_book_title').val() || $('#edit_book_title').val().length < 3) {
                    showInlineError('edit_book_title', 'Book title must be at least 3 characters');
                    hasError = true;
                }
                
                if (!$('#edit_book_author').val() || $('#edit_book_author').val().length < 2) {
                    showInlineError('edit_book_author', 'Book author must be at least 2 characters');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = $('#updateBookBtn');
                submitBtn.prop('disabled', true);
                submitBtn.find('.btn-text').hide();
                submitBtn.find('.btn-loading').show();
                
                const formData = new FormData(this);
                
                // Handle checkboxes
                formData.set('is_popular', $('#edit_is_popular').is(':checked') ? 1 : 0);
                formData.set('is_recommended', $('#edit_is_recommended').is(':checked') ? 1 : 0);
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
                            $('#editBookModal').modal('hide');
                            refreshDataTable();
                            showSuccessToast('Book updated successfully!');
                        } else {
                            showErrorToast(result.message);
                            // Try to identify which field has the error
                            if (result.message.includes('category')) {
                                showInlineError('edit_category_id', result.message);
                            } else if (result.message.includes('title')) {
                                showInlineError('edit_book_title', result.message);
                            } else if (result.message.includes('author')) {
                                showInlineError('edit_book_author', result.message);
                            }
                        }
                    },
                    error: function() {
                        showErrorToast('An error occurred while updating the book.');
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
                if (deleteItemType === 'book') {
                    deleteBookConfirm(deleteItemId);
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
                
                if (!$('#quick_book_title').val() || $('#quick_book_title').val().length < 3) {
                    showInlineError('quick_book_title', 'Book title must be at least 3 characters');
                    hasError = true;
                }
                
                if (!$('#quick_book_author').val() || $('#quick_book_author').val().length < 2) {
                    showInlineError('quick_book_author', 'Book author must be at least 2 characters');
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
                formData.append('action', 'add_book');
                
                // Restore disabled state if it was disabled
                if (wasDisabled) {
                    categoryField.prop('disabled', true);
                }
                
                // Handle checkboxes
                formData.set('is_popular', $('#quick_is_popular').is(':checked') ? 1 : 0);
                formData.set('is_recommended', $('#quick_is_recommended').is(':checked') ? 1 : 0);
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
                                showSuccessToast('Book added successfully!');
                                
                                // Add success animation to form
                                $('#quickAddForm').addClass('form-success');
                                setTimeout(() => {
                                    $('#quickAddForm').removeClass('form-success');
                                }, 600);
                                
                                // Reset form but keep category selected
                                resetQuickFormExceptCategory();
                                
                                // Reload datatable with proper callback
                                refreshDataTable();
                                
                                // Focus on book title for next entry
                                setTimeout(() => {
                                    $('#quick_book_title').focus();
                                }, 200);
                            } else {
                                showErrorToast(result.message);
                                // Try to identify which field has the error
                                if (result.message.includes('category')) {
                                    showInlineError('quick_category_id', result.message);
                                } else if (result.message.includes('title')) {
                                    showInlineError('quick_book_title', result.message);
                                } else if (result.message.includes('author')) {
                                    showInlineError('quick_book_author', result.message);
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            showErrorToast('Error processing server response');
                        }
                    },
                    error: function(xhr) {
                        showErrorToast('An error occurred while saving the book.');
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
                                                <small class="text-muted">Order: ${category.category_order || 0}</small>
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

        function editBook(bookId) {
            // Load book data and categories simultaneously
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_book', book_id: bookId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        
                        // Load categories first, then populate form
                        $.ajax({
                            url: '',
                            type: 'POST',
                            data: { action: 'get_categories' },
                            success: function(catResponse) {
                                const catResult = JSON.parse(catResponse);
                                if (catResult.success) {
                                    // Populate edit category dropdown
                                    const editCategorySelect = $('#edit_category_id');
                                    editCategorySelect.empty().append('<option value="">Select Category</option>');
                                    
                                    catResult.data.forEach(function(category) {
                                        editCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                                    });
                                    
                                    // Now populate the form with book data
                                    $('#edit_book_id').val(data.book_id);
                                    $('#edit_category_id').val(data.category_id);
                                    $('#edit_book_title').val(data.book_title);
                                    $('#edit_book_author').val(data.book_author);
                                    $('#edit_is_popular').prop('checked', data.is_popular == 1);
                                    $('#edit_is_recommended').prop('checked', data.is_recommended == 1);
                                    $('#edit_is_active').prop('checked', data.is_active == 1);
                                    
                                    // Show current thumbnail
                                    if (data.thumbnail && data.thumbnail.trim() !== '') {
                                        $('#current_thumbnail_img').attr('src', '../../../uploads/books/' + data.thumbnail);
                                        $('#current_thumbnail_display').show();
                                    } else {
                                        $('#current_thumbnail_display').hide();
                                    }
                                    
                                    // Show current PDF
                                    if (data.pdf_file) {
                                        $('#current_pdf').html('<small class="text-success">Current: ' + data.pdf_file + '</small>');
                                    } else {
                                        $('#current_pdf').html('');
                                    }
                                    
                                    // Hide new thumbnail preview
                                    $('#edit_thumbnail_preview').hide();
                                    $('#edit_thumbnail').val('');
                                    
                                    // Show the modal
                                    $('#editBookModal').modal('show');
                                }
                            }
                        });
                    } else {
                        showErrorToast(result.message);
                    }
                },
                error: function() {
                    showErrorToast('Error loading book data');
                }
            });
        }
        
        function viewPDF(pdfFile) {
            if (!pdfFile) {
                showErrorToast('No PDF file available');
                return;
            }
            
            const pdfPath = '../../../uploads/books/' + pdfFile;
            $('#pdfFrame').attr('src', pdfPath);
            $('#pdfViewerModal').modal('show');
        }

        function deleteBook(bookId) {
            deleteItemId = bookId;
            deleteItemType = 'book';
            $('#deleteModal').modal('show');
        }

        function deleteBookConfirm(bookId) {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'delete_book', book_id: bookId },
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

        // Function to clean up modal backdrops
        function cleanupModalBackdrop() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            $('body').css('padding-right', '');
        }

        function showAddCategoryForm() {
            // Clean up any existing backdrops first
            cleanupModalBackdrop();
            
            // Hide the manage categories modal first
            $('#manageCategoriesModal').modal('hide');
            
            // Wait for the modal to fully hide before showing the new one
            setTimeout(function() {
                // Clean up again after hiding
                cleanupModalBackdrop();
                
                $('#category_id_edit').val('');
                $('#category_name').val('');
                $('#category_order').val('0');
                $('#categoryAction').val('add_category');
                $('#categoryModalLabel').text('Add Category');
                
                // Show the category modal
                $('#categoryModal').modal('show');
            }, 300);
        }

        function editCategory(categoryId) {
            // Clean up any existing backdrops first
            cleanupModalBackdrop();
            
            // Hide the manage categories modal first
            $('#manageCategoriesModal').modal('hide');
            
            // Wait for the modal to fully hide before showing the new one
            setTimeout(function() {
                // Clean up again after hiding
                cleanupModalBackdrop();
                
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
                            $('#category_order').val(data.category_order);
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

        function showAddCategoryForm() {
            $('#category_id_edit').val('');
            $('#category_name').val('');
            $('#category_order').val('0');
            $('#categoryAction').val('add_category');
            $('#categoryModalLabel').text('Add Category');
            
            $('#categoryModal').modal('show');
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
        $('#editBookModal').on('hidden.bs.modal', function() {
            $('#editBookForm')[0].reset();
            // Reset button state
            const submitBtn = $('#updateBookBtn');
            submitBtn.prop('disabled', false);
            submitBtn.find('.btn-loading').hide();
            submitBtn.find('.btn-text').show();
            // Clear thumbnail displays
            $('#current_thumbnail_display').hide();
            $('#edit_thumbnail_preview').hide();
            $('#current_thumbnail_img').attr('src', '');
            $('#edit_thumbnail_img').attr('src', '');
            // Clear file info
            $('#current_pdf').html('');
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
        
        // Handle label clicks to focus inputs
        $(document).on('click', '.modal .form-label', function() {
            const targetId = $(this).attr('for');
            if (targetId) {
                $('#' + targetId).focus();
            }
        });

        // New workflow functions
        function showQuickAddPanel() {
            $('#quickAddPanel').slideDown(400, function() {
                // Focus on book title after panel is fully shown
                $('#quick_book_title').focus();
                
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
            $('#quickAddForm')[0].reset();
            $('#quick_is_active').prop('checked', true);
            $('#quick_thumbnail_preview').hide();
            $('#quick_thumbnail_img').attr('src', '');
            $('#quick_book_title').focus();
        }

        function resetQuickFormExceptCategory() {
            // Store the selected category and its state
            const selectedCategory = $('#quick_category_id').val();
            const isDisabled = $('#quick_category_id').prop('disabled');
            const hasLockClass = $('#quick_category_id').hasClass('bg-light');
            
            // Reset form fields
            $('#quick_book_title').val('');
            $('#quick_book_author').val('');
            $('#quick_thumbnail').val('');
            $('#quick_pdf_file').val('');
            $('#quick_is_popular').prop('checked', false);
            $('#quick_is_recommended').prop('checked', false);
            $('#quick_is_active').prop('checked', true);
            
            // Reset thumbnail preview
            $('#quick_thumbnail_preview').hide();
            $('#quick_thumbnail_img').attr('src', '');
            
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
                            categoryFilter.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                            quickCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                            editCategorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
                        });
                    }
                }
            });
        }

        // New functions for category/book view switching
        function viewCategoryBooks(categoryId, categoryName) {
            console.log('Switching to books view for category:', categoryId, categoryName);
            currentViewCategory = categoryId;
            
            // Update UI
            $('#categoriesTableContainer').hide();
            $('#categoriesActionPanel').hide();
            $('#booksTableContainer').show();
            $('#booksActionPanel').show();
            $('#breadcrumb').show();
            $('#booksTableTitle').text('Books in "' + categoryName + '"');
            
            // Set and lock category in quick add form
            $('#quick_category_id').val(categoryId);
            $('#quick_category_id').prop('disabled', true);
            $('#quick_category_id').addClass('bg-light');
            
            // Reload books table with category filter
            if (booksTable) {
                booksTable.ajax.reload(null, false);
            } else {
                console.error('Books table not initialized');
            }
        }
        
        function showCategoriesView() {
            console.log('Switching back to categories view');
            currentViewCategory = null;
            
            // Update UI
            $('#booksTableContainer').hide();
            $('#booksActionPanel').hide();
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
                if (booksTable) {
                    booksTable.ajax.reload(function() {
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

        function duplicateBook(bookId) {
            if (confirm('Are you sure you want to duplicate this book?')) {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: { action: 'duplicate_book', book_id: bookId },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            refreshDataTable();
                            showSuccessToast('Book duplicated successfully!');
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

        // Thumbnail preview functions
        function previewQuickThumbnail(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file type
                if (!file.type.match('image.*')) {
                    showErrorToast('Please select a valid image file');
                    input.value = '';
                    return;
                }
                
                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showErrorToast('Image file size must be less than 5MB');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#quick_thumbnail_img').attr('src', e.target.result);
                    $('#quick_thumbnail_preview').show();
                };
                reader.readAsDataURL(file);
            }
        }

        function removeQuickThumbnail() {
            $('#quick_thumbnail').val('');
            $('#quick_thumbnail_preview').hide();
            $('#quick_thumbnail_img').attr('src', '');
        }

        function previewEditThumbnail(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file type
                if (!file.type.match('image.*')) {
                    showErrorToast('Please select a valid image file');
                    input.value = '';
                    return;
                }
                
                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showErrorToast('Image file size must be less than 5MB');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#edit_thumbnail_img').attr('src', e.target.result);
                    $('#edit_thumbnail_preview').show();
                };
                reader.readAsDataURL(file);
            }
        }

        function removeEditThumbnail() {
            $('#edit_thumbnail').val('');
            $('#edit_thumbnail_preview').hide();
            $('#edit_thumbnail_img').attr('src', '');
        }

    </script>

<?php require("../layout/Footer.php"); ?>
