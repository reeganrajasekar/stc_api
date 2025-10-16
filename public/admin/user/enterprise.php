<?php 
require("./layout/Session.php");
require("./../config/db.php"); 

// Handle AJAX requests for DataTable
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'get_users') {
        // Check if users table exists
        if ($conn->query("SHOW TABLES LIKE 'users'")->num_rows == 0) {
            echo json_encode([
                "draw"            => intval($_REQUEST['draw']),
                "recordsTotal"    => 0,
                "recordsFiltered" => 0,
                "data"            => []
            ]);
            exit;
        }
        
    $params  = $_REQUEST;
    $data    = [];
    $columns = [
            0 => 'id',
            1 => 'full_name',
        2 => 'email',
            3 => 'mobile_number',
            4 => 'is_active',
            5 => 'is_verified',
            6 => 'is_paid',
            7 => 'created_at'
        ];

        // --- 1. Base filtering ---
        $where = " WHERE user_role = 'enterprise' "; // Only show enterprise users
        
        // Apply enterprise filter if provided
        if (isset($params['enterprise_filter']) && !empty($params['enterprise_filter'])) {
            $enterprise_filter = $conn->real_escape_string($params['enterprise_filter']);
            $where .= " AND enterprise_id = '$enterprise_filter' ";
        }
        
        // Apply filter based on type
        $filter = isset($params['filter']) ? $params['filter'] : 'all';
        switch($filter) {
            case 'verified':
                $where .= " AND is_verified = 1 AND is_active = 1 ";
                break;
            case 'unverified':
                $where .= " AND is_verified = 0 AND is_active = 1 ";
                break;
            case 'active':
                $where .= " AND is_active = 1 ";
                break;
            case 'recently_active':
                $where .= " AND is_active = 1 AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
                break;
            case 'inactive':
                $where .= " AND is_active = 1 AND (last_login < DATE_SUB(NOW(), INTERVAL 7 DAY) OR last_login IS NULL) ";
                break;
            case 'paid':
                $where .= " AND is_active = 1 AND id IN (SELECT DISTINCT id FROM user_subscriptions WHERE is_active = 1 AND payment_status = 'completed') ";
                break;
            case 'unpaid':
                $where .= " AND is_active = 1 AND id NOT IN (SELECT DISTINCT id FROM user_subscriptions WHERE is_active = 1 AND payment_status = 'completed') ";
                break;
            case 'all':
            default:
                // Show all users (both active and inactive)
                break;
        }

        // --- 2. Total records (with filter applied) ---
        $sqlTotal = "SELECT COUNT(*) as cnt FROM users $where";
    $resTotal = $conn->query($sqlTotal);
    $totalRecords = $resTotal->fetch_assoc()['cnt'];

    if (!empty($params['search']['value'])) {
        $search = $conn->real_escape_string($params['search']['value']);
        $where .= " AND (
                full_name LIKE '%$search%' 
            OR email LIKE '%$search%' 
                OR mobile_number LIKE '%$search%' 
                OR country LIKE '%$search%'
        )";
    }

    // --- 3. Filtered count ---
    $sqlFiltered = "SELECT COUNT(*) as cnt FROM users $where";
    $resFiltered = $conn->query($sqlFiltered);
    $totalFiltered = $resFiltered->fetch_assoc()['cnt'];

    // --- 4. Ordering ---
    $orderCol = $columns[$params['order'][0]['column']] ?? 'created_at';
    $orderDir = $params['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';

    // --- 5. Pagination ---
    $start  = intval($params['start']);
    $length = intval($params['length']);
    $limit  = $length > 0 ? "LIMIT $start, $length" : "";

    // --- 6. Data query ---
    $sqlData = "
        SELECT 
                u.id, 
                u.full_name, 
                u.email, 
                u.mobile_number,
                u.is_active,
                u.is_verified,
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM user_subscriptions us 
                        WHERE us.user_id = u.id 
                        AND us.is_active = 1 
                        AND us.payment_status = 'completed'
                    ) THEN 1 
                    ELSE 0 
                END as is_paid,
                DATE_FORMAT(u.created_at, '%d-%m-%Y %h:%i %p') AS created_at,
                u.last_login
            FROM users u
        $where
        ORDER BY $orderCol $orderDir
        $limit
    ";

    $query = $conn->query($sqlData);
    while ($row = $query->fetch_assoc()) {
        $data[] = array_values($row);
    }

    // --- 7. JSON response ---
    echo json_encode([
        "draw"            => intval($params['draw']),
        "recordsTotal"    => intval($totalRecords),
        "recordsFiltered" => intval($totalFiltered),
        "data"            => $data
    ]);
    exit;
    }
    
    // Handle user actions
    if ($action == 'toggle_status') {
        $user_id = intval($_POST['user_id']);
        $status = intval($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
        }
        exit;
    }
    
    if ($action == 'delete_user') {
        $user_id = intval($_POST['user_id']);
        
        // Permanently delete the user from the database
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User deleted permanently']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
        }
        exit;
    }
    
    if ($action == 'get_user_details') {
        $user_id = intval($_POST['user_id']);
        
        $stmt = $conn->prepare("
            SELECT 
                u.id, u.full_name, u.email, u.mobile_number, 
                u.is_active, u.is_verified, u.gender, u.total_points, 
                u.created_at, u.last_login,
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM user_subscriptions us 
                        WHERE us.user_id = u.id 
                        AND us.is_active = 1 
                        AND us.payment_status = 'completed'
                    ) THEN 1 
                    ELSE 0 
                END as is_paid
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user['created_at'] = date('d-m-Y H:i A', strtotime($user['created_at']));
            $user['last_login'] = $user['last_login'] ? date('d-m-Y H:i A', strtotime($user['last_login'])) : null;
            echo json_encode(['success' => true, 'data' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        exit;
    }
    
    if ($action == 'update_user') {
        $user_id = intval($_POST['user_id']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $mobile = trim($_POST['mobile']);
        $gender = $_POST['gender'];
        $points = intval($_POST['points']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_verified = isset($_POST['is_verified']) ? 1 : 0;
        
        $stmt = $conn->prepare("
            UPDATE users SET 
                full_name = ?, email = ?, mobile_number = ?, 
                gender = ?, total_points = ?, 
                is_active = ?, is_verified = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssiiii", $full_name, $email, $mobile, $gender, $points, $is_active, $is_verified, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user']);
        }
        exit;
    }
    
    if ($action == 'get_all_enterprises') {
        $stmt = $conn->prepare("
            SELECT id, enterprise_id, enterprise_name, enterprise_logo, 
                   enterprise_description, is_active, created_at
            FROM enterprises
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $enterprises = [];
        while ($row = $result->fetch_assoc()) {
            $enterprises[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $enterprises]);
        exit;
    }
    
    if ($action == 'get_enterprise_names') {
        $stmt = $conn->prepare("
            SELECT DISTINCT enterprise_id, enterprise_name 
            FROM enterprises 
            WHERE is_active = 1
            ORDER BY enterprise_name ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $enterprises = [];
        while ($row = $result->fetch_assoc()) {
            $enterprises[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $enterprises]);
        exit;
    }
    
    if ($action == 'get_enterprises') {
        $user_id = intval($_POST['user_id']);
        
        // First get the user's enterprise_id
        $user_stmt = $conn->prepare("SELECT enterprise_id FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            $user_enterprise_id = $user_data['enterprise_id'];
            
            if (!empty($user_enterprise_id)) {
                // Get enterprise details by enterprise_id
                $stmt = $conn->prepare("
                    SELECT id, enterprise_id, enterprise_name, enterprise_logo, 
                           enterprise_description, is_active, created_at
                    FROM enterprises
                    WHERE enterprise_id = ?
                    ORDER BY created_at DESC
                ");
                $stmt->bind_param("s", $user_enterprise_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $enterprises = [];
                while ($row = $result->fetch_assoc()) {
                    $enterprises[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $enterprises]);
            } else {
                echo json_encode(['success' => true, 'data' => []]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        exit;
    }
    

    if ($action == 'create_enterprise') {
        $enterprise_name = trim($_POST['enterprise_name']);
        $enterprise_description = isset($_POST['enterprise_description']) ? trim($_POST['enterprise_description']) : '';
        
        // Generate unique enterprise ID
        $enterprise_id = 'ENT-' . strtoupper(uniqid());
        
        // Handle logo upload
        $enterprise_logo = null;
        if (isset($_FILES['enterprise_logo']) && $_FILES['enterprise_logo']['error'] == 0) {
            $upload_dir = __DIR__ . '/../../uploads/enterprises/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['enterprise_logo']['name'], PATHINFO_EXTENSION);
            $file_name = $enterprise_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['enterprise_logo']['tmp_name'], $file_path)) {
                $enterprise_logo = '/uploads/enterprises/' . $file_name;
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO enterprises (enterprise_id, enterprise_name, enterprise_logo, enterprise_description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssss", $enterprise_id, $enterprise_name, $enterprise_logo, $enterprise_description);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Enterprise created successfully', 'enterprise_id' => $enterprise_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create enterprise']);
        }
        exit;
    }
    
    if ($action == 'toggle_enterprise_status') {
        $enterprise_id = intval($_POST['enterprise_id']);
        $status = intval($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE enterprises SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $enterprise_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Enterprise status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update enterprise status']);
        }
        exit;
    }
    
    if ($action == 'get_enterprise_details') {
        $enterprise_id = intval($_POST['enterprise_id']);
        
        $stmt = $conn->prepare("
            SELECT id, enterprise_id, enterprise_name, enterprise_logo, 
                   enterprise_description, is_active, created_at
            FROM enterprises
            WHERE id = ?
        ");
        $stmt->bind_param("i", $enterprise_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $enterprise = $result->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $enterprise]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Enterprise not found']);
        }
        exit;
    }
    
    if ($action == 'update_enterprise') {
        $enterprise_id = intval($_POST['enterprise_id']);
        $enterprise_name = trim($_POST['enterprise_name']);
        $enterprise_description = isset($_POST['enterprise_description']) ? trim($_POST['enterprise_description']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Handle logo upload if new file provided
        $enterprise_logo = null;
        if (isset($_FILES['enterprise_logo']) && $_FILES['enterprise_logo']['error'] == 0) {
            $upload_dir = __DIR__ . '/../../uploads/enterprises/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['enterprise_logo']['name'], PATHINFO_EXTENSION);
            $file_name = 'ENT_' . $enterprise_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['enterprise_logo']['tmp_name'], $file_path)) {
                $enterprise_logo = '/uploads/enterprises/' . $file_name;
                
                // Update with new logo
                $stmt = $conn->prepare("
                    UPDATE enterprises SET 
                        enterprise_name = ?, enterprise_logo = ?, 
                        enterprise_description = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("sssii", $enterprise_name, $enterprise_logo, $enterprise_description, $is_active, $enterprise_id);
            }
        } else {
            // Update without changing logo
            $stmt = $conn->prepare("
                UPDATE enterprises SET 
                    enterprise_name = ?, enterprise_description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssii", $enterprise_name, $enterprise_description, $is_active, $enterprise_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Enterprise updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update enterprise']);
        }
        exit;
    }
    
    if ($action == 'get_degrees_by_enterprise') {
        $enterprise_id = $conn->real_escape_string($_POST['enterprise_id']);
        
        $stmt = $conn->prepare("
            SELECT DISTINCT degree 
            FROM users 
            WHERE enterprise_id = ? AND degree IS NOT NULL AND degree != ''
            ORDER BY degree ASC
        ");
        $stmt->bind_param("s", $enterprise_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $degrees = [];
        while ($row = $result->fetch_assoc()) {
            $degrees[] = $row['degree'];
        }
        
        echo json_encode(['success' => true, 'data' => $degrees]);
        exit;
    }
    
    if ($action == 'get_departments_by_degree') {
        $enterprise_id = $conn->real_escape_string($_POST['enterprise_id']);
        $degree = $conn->real_escape_string($_POST['degree']);
        
        $stmt = $conn->prepare("
            SELECT DISTINCT department 
            FROM users 
            WHERE enterprise_id = ? AND degree = ? AND department IS NOT NULL AND department != ''
            ORDER BY department ASC
        ");
        $stmt->bind_param("ss", $enterprise_id, $degree);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row['department'];
        }
        
        echo json_encode(['success' => true, 'data' => $departments]);
        exit;
    }
    
    if ($action == 'get_graduation_years') {
        $enterprise_id = $conn->real_escape_string($_POST['enterprise_id']);
        $degree = $conn->real_escape_string($_POST['degree']);
        $department = $conn->real_escape_string($_POST['department']);
        
        $stmt = $conn->prepare("
            SELECT DISTINCT graduation_year 
            FROM users 
            WHERE enterprise_id = ? AND degree = ? AND department = ? 
            AND graduation_year IS NOT NULL AND graduation_year != ''
            ORDER BY graduation_year ASC
        ");
        $stmt->bind_param("sss", $enterprise_id, $degree, $department);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $years = [];
        while ($row = $result->fetch_assoc()) {
            $years[] = $row['graduation_year'];
        }
        
        echo json_encode(['success' => true, 'data' => $years]);
        exit;
    }
    
    if ($action == 'get_categories') {
        $enterprise_id = $conn->real_escape_string($_POST['enterprise_id']);
        $degree = $conn->real_escape_string($_POST['degree']);
        $department = $conn->real_escape_string($_POST['department']);
        $graduation_year = $conn->real_escape_string($_POST['graduation_year']);
        
        $stmt = $conn->prepare("
            SELECT DISTINCT program_category 
            FROM users 
            WHERE enterprise_id = ? AND degree = ? AND department = ? AND graduation_year = ?
            AND program_category IS NOT NULL AND program_category != ''
            ORDER BY program_category ASC
        ");
        $stmt->bind_param("ssss", $enterprise_id, $degree, $department, $graduation_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['program_category'];
        }
        
        echo json_encode(['success' => true, 'data' => $categories]);
        exit;
    }
    
    if ($action == 'get_current_permissions') {
        $enterprise_id = $conn->real_escape_string($_POST['enterprise_id']);
        $degree = $conn->real_escape_string($_POST['degree']);
        $department = $conn->real_escape_string($_POST['department']);
        $graduation_year = $conn->real_escape_string($_POST['graduation_year']);
        $category = $conn->real_escape_string($_POST['category']);
        
        // Get count of affected users
        $count_stmt = $conn->prepare("
            SELECT COUNT(*) as user_count
            FROM users 
            WHERE enterprise_id = ? AND degree = ? AND department = ? 
            AND graduation_year = ? AND program_category = ?
        ");
        $count_stmt->bind_param("sssss", $enterprise_id, $degree, $department, $graduation_year, $category);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $user_count = $count_result->fetch_assoc()['user_count'];
        
        // Get current permissions
        $stmt = $conn->prepare("
            SELECT is_course, is_books, is_listening, is_phrases, is_speaking, is_reading, is_videos
            FROM users 
            WHERE enterprise_id = ? AND degree = ? AND department = ? 
            AND graduation_year = ? AND program_category = ?
            LIMIT 1
        ");
        $stmt->bind_param("sssss", $enterprise_id, $degree, $department, $graduation_year, $category);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $permissions = $result->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $permissions, 'user_count' => $user_count]);
        } else {
            // Return default permissions (all unchecked)
            echo json_encode(['success' => true, 'data' => [
                'is_course' => 0,
                'is_books' => 0,
                'is_listening' => 0,
                'is_phrases' => 0,
                'is_speaking' => 0,
                'is_reading' => 0,
                'is_videos' => 0
            ], 'user_count' => $user_count]);
        }
        exit;
    }
    
    if ($action == 'update_permissions') {
        $enterprise_id = $conn->real_escape_string($_POST['enterprise_id']);
        $degree = $conn->real_escape_string($_POST['degree']);
        $department = $conn->real_escape_string($_POST['department']);
        $graduation_year = $conn->real_escape_string($_POST['graduation_year']);
        $category = $conn->real_escape_string($_POST['category']);
        
        $is_course = isset($_POST['is_course']) ? 1 : 0;
        $is_books = isset($_POST['is_books']) ? 1 : 0;
        $is_listening = isset($_POST['is_listening']) ? 1 : 0;
        $is_phrases = isset($_POST['is_phrases']) ? 1 : 0;
        $is_speaking = isset($_POST['is_speaking']) ? 1 : 0;
        $is_reading = isset($_POST['is_reading']) ? 1 : 0;
        $is_videos = isset($_POST['is_videos']) ? 1 : 0;
        
        $stmt = $conn->prepare("
            UPDATE users SET 
                is_course = ?, is_books = ?, is_listening = ?, 
                is_phrases = ?, is_speaking = ?, is_reading = ?, is_videos = ?
            WHERE enterprise_id = ? AND degree = ? AND department = ? 
            AND graduation_year = ? AND program_category = ?
        ");
        $stmt->bind_param(
            "iiiiiiisssss", 
            $is_course, $is_books, $is_listening, $is_phrases, 
            $is_speaking, $is_reading, $is_videos,
            $enterprise_id, $degree, $department, $graduation_year, $category
        );
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            echo json_encode([
                'success' => true, 
                'message' => "Permissions updated successfully for $affected_rows user(s)",
                'affected_rows' => $affected_rows
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update permissions']);
        }
        exit;
    }
    
    if ($action == 'import_users') {
        require_once __DIR__ . '/../../../vendor/autoload.php';
        
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
            echo json_encode(['success' => false, 'message' => 'Please upload a valid Excel file']);
            exit;
        }
        
        $file = $_FILES['excel_file']['tmp_name'];
        $file_extension = pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION);
        
        if (!in_array(strtolower($file_extension), ['xls', 'xlsx'])) {
            echo json_encode(['success' => false, 'message' => 'Only Excel files (.xls, .xlsx) are allowed']);
            exit;
        }
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            $imported = 0;
            $skipped = 0;
            $errors = [];
            
            // Skip header row
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Skip completely empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                // Map columns based on your Excel structure (0-indexed)
                $first_name = isset($row[1]) ? trim($row[1]) : '';
                $last_name = isset($row[2]) ? trim($row[2]) : '';
                $full_name = trim($first_name . ' ' . $last_name);
                $gender = isset($row[3]) ? strtolower(trim($row[3])) : '';
                $mobile_number = isset($row[4]) ? trim($row[4]) : '';
                $current_state = isset($row[5]) ? trim($row[5]) : '';
                $current_city = isset($row[6]) ? trim($row[6]) : '';
                $dob = isset($row[7]) ? $row[7] : null;
                $email = isset($row[8]) ? trim($row[8]) : ''; // College Mail ID
                $personal_email = isset($row[9]) ? trim($row[9]) : ''; // Personal Email ID
                $department = isset($row[10]) ? trim($row[10]) : ''; // Department
                $degree = isset($row[11]) ? trim($row[11]) : ''; // Degree
                $graduation_year = isset($row[12]) ? trim($row[12]) : ''; // Graduation Year
                $program_category = isset($row[13]) ? trim($row[13]) : ''; // Program Category
                
                // Try to find enterprise_id in the last columns (search for ENT- pattern)
                $enterprise_id = '';
                for ($col = 10; $col < count($row); $col++) {
                    if (isset($row[$col]) && strpos($row[$col], 'ENT-') === 0) {
                        $enterprise_id = trim($row[$col]);
                        break;
                    }
                }
                
                $missing_fields = [];
                if (empty($full_name)) $missing_fields[] = 'name';
                if (empty($mobile_number)) $missing_fields[] = 'mobile';
                if (empty($enterprise_id)) $missing_fields[] = 'enterprise_id';
                
                if (!empty($missing_fields)) {
                    $skipped++;
                    $errors[] = "Row " . ($i + 1) . ": Missing " . implode(', ', $missing_fields);
                    continue;
                }
                
                // Use college email if available, otherwise personal email
                $user_email = !empty($email) ? $email : $personal_email;
                
                if (empty($user_email)) {
                    $skipped++;
                    $errors[] = "Row " . ($i + 1) . ": No email provided";
                    continue;
                }
                
                // Check if user already exists
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR mobile_number = ?");
                $check_stmt->bind_param("ss", $user_email, $mobile_number);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $skipped++;
                    $errors[] = "Row " . ($i + 1) . ": User already exists";
                    continue;
                }
                
                // Format date of birth
                $formatted_dob = null;
                if (!empty($dob)) {
                    try {
                        if (is_numeric($dob)) {
                            // Excel date format
                            $formatted_dob = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dob)->format('Y-m-d');
                        } else {
                            $formatted_dob = date('Y-m-d', strtotime($dob));
                        }
                    } catch (Exception $e) {
                        $formatted_dob = null;
                    }
                }
                
                // Generate default password (you can change this logic)
                $default_password = 'Enterprise@123';
                $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
                 
                $insert_stmt = $conn->prepare("
                    INSERT INTO users (
                        full_name, email, personal_email, mobile_number, password_hash, 
                        date_of_birth, gender, current_state, current_city, 
                        department, degree, graduation_year, program_category, 
                        user_role, enterprise_id, is_active, is_verified, 
                        auth_provider, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'enterprise', ?, 1, 0, 'email', NOW())
                ");
                
                $insert_stmt->bind_param(
                    "ssssssssssssss",
                    $full_name,
                    $user_email,
                    $personal_email,
                    $mobile_number,
                    $password_hash,
                    $formatted_dob,
                    $gender,
                    $current_state,
                    $current_city,
                    $department,
                    $degree,
                    $graduation_year,
                    $program_category,
                    $enterprise_id
                );
                
                if ($insert_stmt->execute()) {
                    $imported++;
                } else {
                    $skipped++;
                    $errors[] = "Row " . ($i + 1) . ": " . $conn->error;
                }
            }
            
            $message = "Import completed: $imported users imported, $skipped skipped";
            if (count($errors) > 0 && count($errors) <= 5) {
                $message .= "\nErrors: " . implode(", ", array_slice($errors, 0, 5));
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 10)
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error processing file: ' . $e->getMessage()]);
        }
        exit;
    }
}
?>

<?php require("./layout/Header.php"); ?>


<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">Enterprise</h5>
    </div>
</div>



<!-- Action Buttons -->
<div class="row mb-3">
    <div class="col-md-8">
        <button class="btn btn-primary" onclick="refreshTable()">
            <i class="ti ti-refresh"></i> Refresh
        </button>
        <button class="btn btn-success" onclick="exportUsers()">
             <i class="ti ti-upload"></i> Export
        </button>
        <button class="btn btn-warning" onclick="openImportModal()">
           <i class="ti ti-download"></i> Import
        </button>
        <button class="btn btn-info" onclick="viewAllEnterprises()">
            <i class="ti ti-building"></i> Enterprises
        </button>
        <button class="btn btn-secondary" onclick="openPermissionsModal()">
            <i class="ti ti-lock"></i> Permissions
        </button>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group" role="group">
            <!-- <button type="button" class="btn btn-outline-primary filter-btn" data-filter="all" onclick="filterUsers('all')">All</button> -->
            <!-- <button type="button" class="btn btn-outline-success filter-btn" data-filter="verified" onclick="filterUsers('verified')">Verified</button>
            <button type="button" class="btn btn-outline-warning filter-btn" data-filter="unverified" onclick="filterUsers('unverified')">Unverified</button> -->
            <!-- <button type="button" class="btn btn-outline-info filter-btn" data-filter="active" onclick="filterUsers('active')">Active</button> -->
            <!-- <button type="button" class="btn btn-outline-success filter-btn" data-filter="paid" onclick="filterUsers('paid')">Paid</button>
            <button type="button" class="btn btn-outline-secondary filter-btn" data-filter="unpaid" onclick="filterUsers('unpaid')">Unpaid</button> -->
        </div>
    </div>
</div>

<!-- Enterprise Filter -->
<div class="row mb-3">
    <div class="col-md-4">
        <label for="enterpriseFilter" class="form-label fw-bold">
            <i class="ti ti-building me-1"></i>Filter by Enterprise
        </label>
        <select id="enterpriseFilter" class="form-select" onchange="filterByEnterprise()">
            <option value="">All Enterprises</option>
        </select>
    </div>
</div>

<!-- Users Table -->
<div class="card mb-3 shadow-sm border p-2">
    <div class="card-body p-0">
    <div class="table-responsive">
            <table id="dataTable" class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="20%">User</th>
                        <th width="20%">Contact</th>
                        <th width="10%">Status</th>
                        <th width="10%">Verified</th>
                        <th width="10%">Paid</th>
                        <th width="12%">Joined</th>
                        <th width="13%">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
</div>

<style>
.filter-btn.active {
    background-color: var(--bs-primary);
    color: white !important;
    border-color: var(--bs-primary);
}

#confirmStatusChange:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Button animation styles */
.btn {
    transition: all 0.3s ease;
}

.btn-success {
    animation: successPulse 0.6s ease-in-out;
}

@keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.ti-check {
    animation: checkBounce 0.8s ease-in-out;
}

@keyframes checkBounce {
    0% { transform: scale(0) rotate(0deg); }
    50% { transform: scale(1.2) rotate(180deg); }
    100% { transform: scale(1) rotate(360deg); }
}

/* Permissions modal dropdown styles */
.form-select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background-color: #f8f9fa;
}

.form-select:not(:disabled) {
    opacity: 1;
    transition: opacity 0.3s ease;
}

/* Smooth transition for dropdown states */
#perm_enterprise,
#perm_degree,
#perm_department,
#perm_graduation_year,
#perm_category {
    transition: opacity 0.3s ease, background-color 0.3s ease;
}
</style>

<script>
$(document).ready(function() {
    // Set initial active filter
    $('.filter-btn[data-filter="all"]').addClass('active');
    
    // Load enterprise filter dropdown
    loadEnterpriseFilter();
    
    // Reset edit modal button when modal is hidden
    $('#editUserModal').on('hidden.bs.modal', function() {
        // Only reset if not in success state
        const submitBtn = $('#editUserModal .btn-primary');
        if (!submitBtn.hasClass('btn-success')) {
            submitBtn.html('Update User');
            submitBtn.removeClass('btn-success').addClass('btn-primary');
        }
    });
    
    // Reset status change modal button when modal is shown
    // $('#statusChangeModal').on('show.bs.modal', function() {
    //     // Ensure button is in correct state when modal opens
    //     $('#confirmStatusChange').prop('disabled', false)
    //                             .removeClass('btn-success')
    //                             .addClass('btn-primary');
    // });
    
    // // Reset status change modal button when modal is hidden
    // $('#statusChangeModal').on('hidden.bs.modal', function() {
    //     // Only reset if not in success state
    //     if (!$('#confirmStatusChange').hasClass('btn-success')) {
    //         $('#confirmStatusChange').text('Confirm');
    //         $('#confirmStatusChange').removeClass('btn-success').addClass('btn-primary');
    //     }
    // });
    
    $('#dataTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [[7, 'desc']],
        ajax: { 
            url: "", 
            type: "POST",
            data: function(d) {
                d.action = 'get_users';
                d.filter = currentFilter;
                d.enterprise_filter = currentEnterpriseFilter;
                return d;
            }
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rt<"d-flex justify-content-between mt-3"lp>',
        buttons: [
            { 
                extend: 'csvHtml5', 
                className: 'btn btn-sm btn-outline-info',
                title: 'Enterprise_Users_Export',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6],
                    format: {
                        body: function(data, row, column, node) {
                            // Clean HTML tags and get plain text
                            if (column === 1) {
                                // Extract name from HTML
                                const temp = document.createElement('div');
                                temp.innerHTML = data;
                                return temp.querySelector('h6') ? temp.querySelector('h6').textContent : data;
                            }
                            if (column === 2) {
                                // Extract email from HTML
                                const temp = document.createElement('div');
                                temp.innerHTML = data;
                                return temp.querySelector('.fw-medium') ? temp.querySelector('.fw-medium').textContent : data;
                            }
                            if (column === 3 || column === 4 || column === 5) {
                                // Extract badge text
                                const temp = document.createElement('div');
                                temp.innerHTML = data;
                                return temp.querySelector('.badge') ? temp.querySelector('.badge').textContent : data;
                            }
                            return data;
                        }
                    }
                },
                customize: function(csv) {
                    // Add proper headers
                    const rows = csv.split('\n');
                    rows[0] = '#,Full Name,Email,Mobile,Status,Verified,Paid,Joined Date';
                    return rows.join('\n');
                }
            },
            { 
                extend: 'excelHtml5', 
                className: 'btn btn-sm btn-outline-success',
                title: 'Enterprise_Users_Export',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6],
                    format: {
                        body: function(data, row, column, node) {
                            if (column === 1) {
                                const temp = document.createElement('div');
                                temp.innerHTML = data;
                                return temp.querySelector('h6') ? temp.querySelector('h6').textContent : data;
                            }
                            if (column === 2) {
                                const temp = document.createElement('div');
                                temp.innerHTML = data;
                                return temp.querySelector('.fw-medium') ? temp.querySelector('.fw-medium').textContent : data;
                            }
                            if (column === 3 || column === 4 || column === 5) {
                                const temp = document.createElement('div');
                                temp.innerHTML = data;
                                return temp.querySelector('.badge') ? temp.querySelector('.badge').textContent : data;
                            }
                            return data;
                        }
                    }
                },
                customize: function(xlsx) {
                    const sheet = xlsx.xl.worksheets['sheet1.xml'];
                    // Set column headers
                    $('row:first c', sheet).each(function(index) {
                        const headers = ['#', 'Full Name', 'Email', 'Mobile', 'Status', 'Verified', 'Paid', 'Joined Date'];
                        if (headers[index]) {
                            $(this).find('v').text(headers[index]);
                        }
                    });
                }
            }
        ],
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        pageLength: 10,
        columnDefs: [
            {
                targets: 0,
                orderable: false,
                render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
            },
            {
                targets: 1,
                render: function(data, type, row) {
                    return `
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                <span class="text-white fw-bold">${row[1].charAt(0).toUpperCase()}</span>
                            </div>
                            <div>
                                <h6 class="mb-0">${row[1]}</h6>
                                <small class="text-muted">ID: ${row[0]}</small>
                            </div>
                        </div>
                    `;
                }
            },
            {
                targets: 2,
                render: function(data, type, row) {
                    return `
                        <div>
                            <div class="fw-medium">${row[2]}</div>
                            <small class="text-muted">${row[3]}</small>
                        </div>
                    `;
                }
            },
            {
                targets: 3,
                render: function(data, type, row) {
                    const status = row[4] == 1 ? 'Active' : 'Inactive';
                    const badgeClass = row[4] == 1 ? 'bg-success' : 'bg-danger';
                    return `<span class="badge ${badgeClass}">${status}</span>`;
                }
            },
            {
                targets: 4,
                render: function(data, type, row) {
                    const verified = row[5] == 1 ? 'Yes' : 'No';
                    const badgeClass = row[5] == 1 ? 'bg-success' : 'bg-warning';
                    return `<span class="badge ${badgeClass}">${verified}</span>`;
                }
            },
            {
                targets: 5,
                render: function(data, type, row) {
                    const paid = row[6] == 1 ? 'Yes' : 'No';
                    const badgeClass = row[6] == 1 ? 'bg-success' : 'bg-secondary';
                    return `<span class="badge ${badgeClass}">${paid}</span>`;
                }
            },
            {
                targets: 6,
                render: function(data, type, row) {
                    return row[7] || 'N/A';
                }
            },
            {
                targets: 7,
                orderable: false,
                render: function(data, type, row) {
                    return `
                       
                            <button class="btn btn-sm btn-info me-1" onclick="viewEnterprises(${row[0]})" title="Enterprises">
                                <i class="ti ti-building"></i>
                            </button>
                            <button class="btn btn-sm btn-primary me-1" onclick="viewUser(${row[0]})" title="View">
                                <i class="ti ti-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-warning me-1" onclick="editUser(${row[0]})" title="Edit">
                                <i class="ti ti-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-info-${row[4] == 1 ? 'danger' : 'success'}" 
                                    onclick="toggleUserStatus(${row[0]}, ${row[4]})" 
                                    title="${row[4] == 1 ? 'Deactivate' : 'Activate'}">
                                <i class="ti ti-${row[4] == 1 ? 'user-x' : 'user-check'}"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(${row[0]})" title="Delete">
                                <i class="ti ti-trash"></i>
                            </button>
                         
                    `;
                }
            }
        ]
    });
});

// User Management Functions
function refreshTable() {
    $('#dataTable').DataTable().ajax.reload(null, false);
}

// Enhanced refresh function with error handling
function refreshDataTable() {
    try {
        const table = $('#dataTable').DataTable();
        if (table) {
            // Show loading indicator
            $('#dataTable_processing').show();
            
            table.ajax.reload(null, false);
            console.log('DataTable refreshed successfully');
            
            // Hide loading indicator after a short delay
            setTimeout(function() {
                $('#dataTable_processing').hide();
            }, 1000);
        } else {
            console.error('DataTable not found');
        }
    } catch (error) {
        console.error('Error refreshing DataTable:', error);
        // Fallback: reload the page
        location.reload();
    }
}

// Global variables for filtering
let currentFilter = 'all';
let currentEnterpriseFilter = '';

function filterUsers(type) {
    currentFilter = type;
    
    // Update button states
    $('.filter-btn').removeClass('active');
    $(`.filter-btn[data-filter="${type}"]`).addClass('active');
    
    refreshDataTable();
}

function filterByEnterprise() {
    currentEnterpriseFilter = $('#enterpriseFilter').val();
    refreshDataTable();
}

function loadEnterpriseFilter() {
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_enterprise_names'
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                let options = '<option value="">All Enterprises</option>';
                result.data.forEach(function(enterprise) {
                    options += `<option value="${enterprise.enterprise_id}">${enterprise.enterprise_name}</option>`;
                });
                $('#enterpriseFilter').html(options);
            }
        },
        error: function() {
            console.error('Failed to load enterprise filter');
        }
    });
}

function viewUser(userId) {
    // Get user data via AJAX
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_user_details',
            user_id: userId
        },
        success: function(response) {
            const user = JSON.parse(response);
            if (user.success) {
                $('#viewUserModal .modal-body').html(`
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="avatar-lg bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                <span class="text-white fw-bold fs-2">${user.data.full_name.charAt(0).toUpperCase()}</span>
                            </div>
                            <h5 class="mb-1">${user.data.full_name}</h5>
                            <p class="text-muted mb-0">ID: ${user.data.id}</p>
                        </div>
                        <div class="col-md-8">
                            <table class="table table-borderless">
                                <tr><td><strong>Email:</strong></td><td>${user.data.email}</td></tr>
                                <tr><td><strong>Mobile:</strong></td><td>${user.data.mobile_number}</td></tr>
                                <tr><td><strong>Status:</strong></td><td><span class="badge ${user.data.is_active == 1 ? 'bg-success' : 'bg-danger'}">${user.data.is_active == 1 ? 'Active' : 'Inactive'}</span></td></tr>
                                <tr><td><strong>Verified:</strong></td><td><span class="badge ${user.data.is_verified == 1 ? 'bg-success' : 'bg-warning'}">${user.data.is_verified == 1 ? 'Yes' : 'No'}</span></td></tr>
                                <tr><td><strong>Paid User:</strong></td><td><span class="badge ${user.data.is_paid == 1 ? 'bg-success' : 'bg-secondary'}">${user.data.is_paid == 1 ? 'Yes' : 'No'}</span></td></tr>
                                <tr><td><strong>Gender:</strong></td><td>${user.data.gender || 'Not specified'}</td></tr>
                                <tr><td><strong>Points:</strong></td><td>${user.data.total_points || 0}</td></tr>
                                <tr><td><strong>Joined:</strong></td><td>${user.data.created_at}</td></tr>
                                <tr><td><strong>Last Login:</strong></td><td>${user.data.last_login || 'Never'}</td></tr>
                            </table>
                        </div>
                    </div>
                `);
                $('#viewUserModal').modal('show');
            } else {
                // Show error message (using toastr if available)
            if (typeof toastr !== 'undefined') {
                toastr.error('Failed to load user details');
            } else {
                console.log('Error: Failed to load user details');
            }
            }
        },
        error: function() {
            // Show error message (using toastr if available)
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while loading user details');
            } else {
                console.log('Error: An error occurred while loading user details');
            }
        }
    });
}

function editUser(userId) {
    // Get user data for editing
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_user_details',
            user_id: userId
        },
        success: function(response) {
            const user = JSON.parse(response);
            if (user.success) {
                $('#editUserModal #edit_user_id').val(user.data.id);
                $('#editUserModal #edit_full_name').val(user.data.full_name);
                $('#editUserModal #edit_email').val(user.data.email);
                $('#editUserModal #edit_mobile').val(user.data.mobile_number);
                $('#editUserModal #edit_gender').val(user.data.gender || '');
                $('#editUserModal #edit_points').val(user.data.total_points || 0);
                $('#editUserModal #edit_is_active').prop('checked', user.data.is_active == 1);
                $('#editUserModal #edit_is_verified').prop('checked', user.data.is_verified == 1);
                
                // Update paid status display
                const paidStatus = user.data.is_paid == 1 ? 'Yes' : 'No';
                const paidBadgeClass = user.data.is_paid == 1 ? 'bg-success' : 'bg-secondary';
                $('#edit_paid_status').removeClass().addClass(`badge ${paidBadgeClass}`).text(paidStatus);
                
                // Reset button state when opening modal
                const submitBtn = $('#editUserModal .btn-primary');
                submitBtn.html('Update User');
                submitBtn.removeClass('btn-success').addClass('btn-primary');
                
                $('#editUserModal').modal('show');
            } else {
                // Show error message (using toastr if available)
            if (typeof toastr !== 'undefined') {
                toastr.error('Failed to load user details');
            } else {
                console.log('Error: Failed to load user details');
            }
            }
        },
        error: function() {
            // Show error message (using toastr if available)
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while loading user details');
            } else {
                console.log('Error: An error occurred while loading user details');
            }
        }
    });
}

// Global variables for status change
let statusChangeUserId = null;
let statusChangeNewStatus = null;

// Global variables for delete confirmation
let deleteUserId = null;

function deleteUser(userId) {
    deleteUserId = userId;
    
    $('#deleteUserMessage').html('<strong class="text-danger">Warning:</strong> Are you sure you want to permanently delete this user? This action cannot be undone and will remove all user data from the database.');
    $('#deleteUserModal').modal('show');
}

function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const action = currentStatus == 1 ? 'deactivate' : 'activate';
    const actionText = action.charAt(0).toUpperCase() + action.slice(1);
    
    // Store the values for the modal
    statusChangeUserId = userId;
    statusChangeNewStatus = newStatus;
    
    // Update modal content
    $('#statusChangeMessage').text(`Are you sure you want to ${action} this user?`);
    
    // Reset and set button properly with HTML to ensure it shows
    $('#confirmStatusChange').removeClass('btn-success')
                            .addClass('btn-primary')
                            .html(actionText);
    
    // Show the modal
    $('#statusChangeModal').modal('show');
}

// Handle delete confirmation
$(document).ready(function() {
    $('#confirmDeleteUser').click(function() {
        if (deleteUserId) {
            $('#confirmDeleteUser').html('<i class="spinner-border spinner-border-sm me-2"></i>Deleting...');
            
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'delete_user',
                    user_id: deleteUserId
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        if (typeof toastr !== 'undefined') {
                            toastr.success(result.message);
                        }
                        
                        $('#confirmDeleteUser').html('<i class="ti ti-check me-2"></i>Deleted!');
                        $('#confirmDeleteUser').removeClass('btn-danger').addClass('btn-success');
                        
                        refreshDataTable();
                        
                        setTimeout(function() {
                            $('#deleteUserModal').modal('hide');
                            $('#confirmDeleteUser').html('Delete');
                            $('#confirmDeleteUser').removeClass('btn-success').addClass('btn-danger');
                        }, 2000);
                    } else {
                        if (typeof toastr !== 'undefined') {
                            toastr.error(result.message);
                        }
                        $('#confirmDeleteUser').html('Delete');
                    }
                },
                error: function() {
                    $('#confirmDeleteUser').html('Delete');
                    if (typeof toastr !== 'undefined') {
                        toastr.error('An error occurred while deleting user');
                    }
                },
                complete: function() {
                    deleteUserId = null;
                }
            });
        }
    });
});

// Handle status change confirmation
$(document).ready(function() {
    $('#confirmStatusChange').click(function() {
        if (statusChangeUserId && statusChangeNewStatus !== null) {
            // Disable button and show loading state
         
        
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'toggle_status',
                    user_id: statusChangeUserId,
                    status: statusChangeNewStatus
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        // Show success animation
                            // Hide modal immediately
          
                        // Show success message (using toastr if available)
                        if (typeof toastr !== 'undefined') {
                            toastr.success(result.message);
                        } else {
                            console.log('Success: ' + result.message);
                        }
                    
                        $('#confirmStatusChange').html('<i class="spinner-border spinner-border-sm me-2"></i>Processing...');
                // Refresh table immediately
                refreshDataTable();
                        // Reset button after 2 seconds
                        setTimeout(function() {
                               
                        $('#confirmStatusChange').html('<i class="ti ti-check me-2"></i>Done!');
                        $('#confirmStatusChange').removeClass('btn-primary').addClass('btn-success');
                        
                        $('#statusChangeModal').modal('hide');
                        }, 2000);
                        
            
                            // $('#confirmStatusChange').text('Confirm');
                            // $('#confirmStatusChange').removeClass('btn-success').addClass('btn-primary');
                    } else {
                        // Show error message (using toastr if available)
                if (typeof toastr !== 'undefined') {
                    toastr.error(result.message);
                } else {
                    console.log('Error: ' + result.message);
                }
                    }
                },
                error: function() {
                    // Reset button on error
                    $('#confirmStatusChange').text('Confirm');
                    $('#confirmStatusChange').removeClass('btn-success').addClass('btn-primary');
                    // Show error message (using toastr if available)
                    if (typeof toastr !== 'undefined') {
                        toastr.error('An error occurred while updating user status');
                    } else {
                        console.log('Error: An error occurred while updating user status');
                    }
                },
                complete: function() {
                    // Reset variables
                    statusChangeUserId = null;
                    statusChangeNewStatus = null;
                }
            });
        }
    });
});

function exportUsers() {
    // Show export options
    const exportType = confirm('Click OK for Excel export, Cancel for CSV export');
    if (exportType) {
        $('#dataTable').DataTable().button('.buttons-excel').trigger();
    } else {
        $('#dataTable').DataTable().button('.buttons-csv').trigger();
    }
}

function openImportModal() {
    $('#importUsersForm')[0].reset();
    $('#importResults').hide();
    $('#importUsersModal').modal('show');
}

function importUsers() {
    const fileInput = document.getElementById('excel_file');
    
    if (!fileInput.files || fileInput.files.length === 0) {
        if (typeof toastr !== 'undefined') {
            toastr.error('Please select an Excel file to upload');
        }
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'import_users');
    formData.append('excel_file', fileInput.files[0]);
    
    const submitBtn = $('#importUsersBtn');
    const originalText = submitBtn.html();
    
    submitBtn.html('<i class="spinner-border spinner-border-sm me-2"></i>Importing...');
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
                submitBtn.html('<i class="ti ti-check me-2"></i>Import Complete!');
                submitBtn.removeClass('btn-primary').addClass('btn-success');
                
                // Show detailed results
                let resultsHtml = `
                    <div class="alert alert-success">
                        <h6><i class="ti ti-check-circle me-2"></i>Import Summary</h6>
                        <p class="mb-1"><strong>Imported:</strong> ${result.imported} users</p>
                        <p class="mb-0"><strong>Skipped:</strong> ${result.skipped} users</p>
                    </div>
                `;
                
                if (result.errors && result.errors.length > 0) {
                    resultsHtml += `
                        <div class="alert alert-warning">
                            <h6><i class="ti ti-alert-triangle me-2"></i>Errors/Warnings</h6>
                            <ul class="mb-0 small">
                                ${result.errors.map(err => `<li>${err}</li>`).join('')}
                            </ul>
                        </div>
                    `;
                }
                
                $('#importResults').html(resultsHtml).show();
                
                if (typeof toastr !== 'undefined') {
                    toastr.success(result.message);
                }
                
                // Refresh table after 3 seconds
                setTimeout(function() {
                    refreshDataTable();
                    $('#importUsersModal').modal('hide');
                    submitBtn.html(originalText);
                    submitBtn.removeClass('btn-success').addClass('btn-primary');
                }, 3000);
            } else {
                submitBtn.html(originalText);
                
                $('#importResults').html(`
                    <div class="alert alert-danger">
                        <i class="ti ti-x-circle me-2"></i>${result.message}
                    </div>
                `).show();
                
                if (typeof toastr !== 'undefined') {
                    toastr.error(result.message);
                }
            }
        },
        error: function() {
            submitBtn.html(originalText);
            
            $('#importResults').html(`
                <div class="alert alert-danger">
                    <i class="ti ti-x-circle me-2"></i>An error occurred while importing users
                </div>
            `).show();
            
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while importing users');
            }
        }
    });
}

// Enterprise Management Functions
let currentEnterpriseUserId = null;

// Copy enterprise ID to clipboard with visual feedback
function copyEnterpriseId(enterpriseId, event) {
    const button = event ? event.currentTarget : null;
    const icon = button ? button.querySelector('i') : null;
    
    navigator.clipboard.writeText(enterpriseId).then(function() {
        // Change icon to check mark
        if (icon) {
            icon.classList.remove('ti-copy');
            icon.classList.add('ti-check');
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
        }
        
        if (typeof toastr !== 'undefined') {
            toastr.success('Enterprise ID copied to clipboard!');
        }
        
        // Reset icon after 2 seconds
        setTimeout(function() {
            if (icon) {
                icon.classList.remove('ti-check');
                icon.classList.add('ti-copy');
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }
        }, 2000);
    }).catch(function(err) {
        console.error('Failed to copy: ', err);
        // Fallback method
        const textArea = document.createElement('textarea');
        textArea.value = enterpriseId;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            
            // Change icon to check mark
            if (icon) {
                icon.classList.remove('ti-copy');
                icon.classList.add('ti-check');
                button.classList.remove('btn-outline-secondary');
                button.classList.add('btn-success');
            }
            
            if (typeof toastr !== 'undefined') {
                toastr.success('Enterprise ID copied to clipboard!');
            }
            
            // Reset icon after 2 seconds
            setTimeout(function() {
                if (icon) {
                    icon.classList.remove('ti-check');
                    icon.classList.add('ti-copy');
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-secondary');
                }
            }, 2000);
        } catch (err) {
            console.error('Fallback copy failed: ', err);
            if (typeof toastr !== 'undefined') {
                toastr.error('Failed to copy Enterprise ID');
            }
        }
        document.body.removeChild(textArea);
    });
}

function viewAllEnterprises() {
    currentEnterpriseUserId = null; // No specific user
    
    // Show the "Add New Enterprise" button for all enterprises view
    $('#addEnterpriseBtn').show();
    $('#enterprisesModalLabel').html('<i class="ti ti-building me-2"></i>All Enterprises');
    
    // Hide card view, show table view
    $('#enterprisesListContainer').hide();
    $('#enterprisesTableContainer').show();
    
    // Show modal
    $('#enterprisesModal').modal('show');
    
    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#enterprisesDataTable')) {
        $('#enterprisesDataTable').DataTable().destroy();
    }
    
    // Fetch data and initialize DataTable
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_all_enterprises'
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                // Initialize DataTable with data
                $('#enterprisesDataTable').DataTable({
                    data: result.data,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    order: [[6, 'desc']], // Sort by created date
                    columns: [
                        {
                            data: null,
                            orderable: false,
                            render: function(data, type, row, meta) {
                                return meta.row + 1;
                            }
                        },
                        {
                            data: 'enterprise_logo',
                            orderable: false,
                            render: function(data, type, row) {
                                if (data) {
                                    return `<img src="${data}" alt="Logo" class="rounded" style="max-width: 50px; max-height: 50px; object-fit: cover;">`;
                                } else {
                                    return '<div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;"><i class="ti ti-building text-white"></i></div>';
                                }
                            }
                        },
                        {
                            data: 'enterprise_name',
                            render: function(data, type, row) {
                                return `<strong>${data}</strong>`;
                            }
                        },
                        {
                            data: 'enterprise_id',
                            render: function(data) {
                                return `
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="small text-truncate" style="max-width: 150px;" title="${data}">${data}</code>
                                        <button class="btn btn-sm btn-outline-secondary copy-enterprise-btn" 
                                                onclick="copyEnterpriseId('${data}', event)" 
                                                title="Copy Enterprise ID">
                                            <i class="ti ti-copy"></i>
                                        </button>
                                    </div>
                                `;
                            }
                        },
                        {
                            data: 'enterprise_description',
                            render: function(data) {
                                if (data && data.length > 50) {
                                    return data.substring(0, 50) + '...';
                                }
                                return data || '<span class="text-muted">No description</span>';
                            }
                        },
                        {
                            data: 'is_active',
                            render: function(data) {
                                if (data == 1) {
                                    return '<span class="badge bg-success">Active</span>';
                                } else {
                                    return '<span class="badge bg-danger">Inactive</span>';
                                }
                            }
                        },
                        {
                            data: 'created_at',
                            render: function(data) {
                                return new Date(data).toLocaleDateString('en-GB', { 
                                    day: '2-digit', 
                                    month: 'short', 
                                    year: 'numeric' 
                                });
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            render: function(data, type, row) {
                                return `
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-warning" onclick="editEnterprise(${row.id})" title="Edit">
                                            <i class="ti ti-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-${row.is_active == 1 ? 'danger' : 'success'}" 
                                                onclick="toggleEnterpriseStatus(${row.id}, ${row.is_active})" 
                                                title="${row.is_active == 1 ? 'Deactivate' : 'Activate'}">
                                            <i class="ti ti-${row.is_active == 1 ? 'x' : 'check'}"></i>
                                        </button>
                                    </div>
                                `;
                            }
                        }
                    ]
                });
            } else {
                if (typeof toastr !== 'undefined') {
                    toastr.error('Failed to load enterprises');
                }
            }
        },
        error: function() {
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while loading enterprises');
            }
        }
    });
}

function viewEnterprises(userId) {
    currentEnterpriseUserId = userId;
    
    // Hide the "Add New Enterprise" button for user-specific view
    $('#addEnterpriseBtn').hide();
    $('#enterprisesModalLabel').html('<i class="ti ti-building me-2"></i>Enterprise Details');
    
    // Show card view, hide table view
    $('#enterprisesTableContainer').hide();
    $('#enterprisesListContainer').show();
    
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_enterprises',
            user_id: userId
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                let enterprisesHtml = '';
                
                if (result.data.length > 0) {
                    result.data.forEach(function(enterprise) {
                        const statusBadge = enterprise.is_active == 1 ? 
                            '<span class="badge bg-success">Active</span>' : 
                            '<span class="badge bg-danger">Inactive</span>';
                        
                        const logoHtml = enterprise.enterprise_logo ? 
                            `<img src="${enterprise.enterprise_logo}" alt="Logo" class="rounded" style="max-width: 100px; max-height: 100px; object-fit: cover;">` : 
                            '<div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;"><i class="ti ti-building text-white fs-2"></i></div>';
                        
                        enterprisesHtml += `
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-2 text-center">
                                            ${logoHtml}
                                        </div>
                                        <div class="col-md-8">
                                            <h5 class="mb-2 fw-bold text-primary">${enterprise.enterprise_name}</h5>
                                            <p class="text-muted mb-2"><i class="ti ti-id me-1"></i><strong>Enterprise ID:</strong> ${enterprise.enterprise_id}</p>
                                            <p class="mb-2"><strong>Description:</strong> ${enterprise.enterprise_description || 'No description available'}</p>
                                            <p class="mb-0 small text-muted"><i class="ti ti-calendar me-1"></i><strong>Created:</strong> ${new Date(enterprise.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}</p>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            ${statusBadge}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    enterprisesHtml = '<div class="alert alert-warning"><i class="ti ti-alert-triangle me-2"></i>No enterprise assigned to this user.</div>';
                }
                
                $('#enterprisesListContainer').html(enterprisesHtml);
                $('#enterprisesModal').modal('show');
            } else {
                if (typeof toastr !== 'undefined') {
                    toastr.error(result.message || 'Failed to load enterprises');
                }
            }
        },
        error: function() {
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while loading enterprises');
            }
        }
    });
}

function openNewEnterpriseModal() {
    $('#newEnterpriseForm')[0].reset();
    $('#newEnterpriseModal').modal('show');
}

function createEnterprise() {
    const formData = new FormData(document.getElementById('newEnterpriseForm'));
    formData.append('action', 'create_enterprise');
    
    const submitBtn = $('#createEnterpriseBtn');
    const originalText = submitBtn.html();
    
    submitBtn.html('<i class="spinner-border spinner-border-sm me-2"></i>Creating...');
    
    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                submitBtn.html('<i class="ti ti-check me-2"></i>Created!');
                submitBtn.removeClass('btn-primary').addClass('btn-success');
                
                if (typeof toastr !== 'undefined') {
                    toastr.success(result.message);
                }
                
                setTimeout(function() {
                    $('#newEnterpriseModal').modal('hide');
                    submitBtn.html(originalText);
                    submitBtn.removeClass('btn-success').addClass('btn-primary');
                    
                    // Refresh enterprises list
                    if (currentEnterpriseUserId) {
                        viewEnterprises(currentEnterpriseUserId);
                    } else {
                        viewAllEnterprises();
                    }
                    
                    // Reload enterprise filter dropdown
                    loadEnterpriseFilter();
                }, 2000);
            } else {
                submitBtn.html(originalText);
                if (typeof toastr !== 'undefined') {
                    toastr.error(result.message);
                }
            }
        },
        error: function() {
            submitBtn.html(originalText);
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while creating enterprise');
            }
        }
    });
}

function toggleEnterpriseStatus(enterpriseId, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'toggle_enterprise_status',
            enterprise_id: enterpriseId,
            status: newStatus
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                if (typeof toastr !== 'undefined') {
                    toastr.success(result.message);
                }
                
                // Refresh appropriate view
                if (currentEnterpriseUserId) {
                    viewEnterprises(currentEnterpriseUserId);
                } else {
                    viewAllEnterprises();
                }
            } else {
                if (typeof toastr !== 'undefined') {
                    toastr.error(result.message);
                }
            }
        },
        error: function() {
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while updating enterprise status');
            }
        }
    });
}

function editEnterprise(enterpriseId) {
    // Get enterprise data for editing
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_enterprise_details',
            enterprise_id: enterpriseId
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                const enterprise = result.data;
                
                $('#edit_enterprise_id').val(enterprise.id);
                $('#edit_enterprise_name').val(enterprise.enterprise_name);
                $('#edit_enterprise_description').val(enterprise.enterprise_description);
                $('#edit_is_active').prop('checked', enterprise.is_active == 1);
                
                if (enterprise.enterprise_logo) {
                    $('#current_logo_preview').html(`
                        <img src="${enterprise.enterprise_logo}" alt="Current Logo" class="img-thumbnail" style="max-width: 150px;">
                        <p class="small text-muted mt-2">Current logo</p>
                    `);
                } else {
                    $('#current_logo_preview').html('<p class="text-muted">No logo uploaded</p>');
                }
                
                $('#editEnterpriseModal').modal('show');
            } else {
                if (typeof toastr !== 'undefined') {
                    toastr.error('Failed to load enterprise details');
                }
            }
        },
        error: function() {
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while loading enterprise details');
            }
        }
    });
}

function updateEnterprise() {
    const formData = new FormData(document.getElementById('editEnterpriseForm'));
    formData.append('action', 'update_enterprise');
    
    const submitBtn = $('#updateEnterpriseBtn');
    const originalText = submitBtn.html();
    
    submitBtn.html('<i class="spinner-border spinner-border-sm me-2"></i>Updating...');
    
    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                submitBtn.html('<i class="ti ti-check me-2"></i>Updated!');
                submitBtn.removeClass('btn-primary').addClass('btn-success');
                
                if (typeof toastr !== 'undefined') {
                    toastr.success(result.message);
                }
                
                setTimeout(function() {
                    $('#editEnterpriseModal').modal('hide');
                    submitBtn.html(originalText);
                    submitBtn.removeClass('btn-success').addClass('btn-primary');
                    
                    // Refresh enterprises list
                    if (currentEnterpriseUserId) {
                        viewEnterprises(currentEnterpriseUserId);
                    } else {
                        viewAllEnterprises();
                    }
                    
                    // Reload enterprise filter dropdown
                    loadEnterpriseFilter();
                }, 2000);
            } else {
                submitBtn.html(originalText);
                if (typeof toastr !== 'undefined') {
                    toastr.error(result.message);
                }
            }
        },
        error: function() {
            submitBtn.html(originalText);
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while updating enterprise');
            }
        }
    });
}

// Permissions Management Functions
function openPermissionsModal() {
    // Reset form
    $('#permissionsForm')[0].reset();
    
    // Reset and hide all dependent dropdowns
    $('#perm_degree').html('<option value="">Select Degree</option>').prop('disabled', true);
    $('#perm_department').html('<option value="">Select Department</option>').prop('disabled', true);
    $('#perm_graduation_year').html('<option value="">Select Graduation Year</option>').prop('disabled', true);
    $('#perm_category').html('<option value="">Select Category</option>').prop('disabled', true);
    
    // Hide permissions checkboxes
    $('#permissionsCheckboxes').hide();
    
    // Load enterprises
    loadEnterpriseDropdown();
    
    $('#permissionsModal').modal('show');
}

function loadEnterpriseDropdown() {
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_enterprise_names'
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                let options = '<option value="">Select Enterprise</option>';
                result.data.forEach(function(enterprise) {
                    options += `<option value="${enterprise.enterprise_id}">${enterprise.enterprise_name}</option>`;
                });
                $('#perm_enterprise').html(options);
            }
        }
    });
}

function onEnterpriseChange() {
    const enterpriseId = $('#perm_enterprise').val();
    
    // Reset dependent dropdowns with disabled state
    $('#perm_degree').html('<option value="">Select Degree</option>').prop('disabled', true).css('opacity', '0.6');
    $('#perm_department').html('<option value="">Select Department</option>').prop('disabled', true).css('opacity', '0.6');
    $('#perm_graduation_year').html('<option value="">Select Graduation Year</option>').prop('disabled', true).css('opacity', '0.6');
    $('#perm_category').html('<option value="">Select Category</option>').prop('disabled', true).css('opacity', '0.6');
    $('#permissionsCheckboxes').hide();
    
    if (!enterpriseId) return;
    
    // Load degrees
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_degrees_by_enterprise',
            enterprise_id: enterpriseId
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success && result.data.length > 0) {
                let options = '<option value="">Select Degree</option>';
                result.data.forEach(function(degree) {
                    options += `<option value="${degree}">${degree}</option>`;
                });
                $('#perm_degree').html(options).prop('disabled', false).css('opacity', '1');
            } else {
                if (typeof toastr !== 'undefined') {
                    toastr.warning('No degrees found for this enterprise');
                }
            }
        }
    });
}

function onDegreeChange() {
    const enterpriseId = $('#perm_enterprise').val();
    const degree = $('#perm_degree').val();
    
    // Reset dependent dropdowns
    $('#perm_department').html('<option value="">Select Department</option>').prop('disabled', true);
    $('#perm_graduation_year').html('<option value="">Select Graduation Year</option>').prop('disabled', true);
    $('#perm_category').html('<option value="">Select Category</option>').prop('disabled', true);
    $('#permissionsCheckboxes').hide();
    
    if (!degree) return;
    
    // Load departments
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_departments_by_degree',
            enterprise_id: enterpriseId,
            degree: degree
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success && result.data.length > 0) {
                let options = '<option value="">Select Department</option>';
                result.data.forEach(function(dept) {
                    options += `<option value="${dept}">${dept}</option>`;
                });
                $('#perm_department').html(options).prop('disabled', false);
            } else {
                if (typeof toastr !== 'undefined') {
                    toastr.warning('No departments found for this degree');
                }
            }
        }
    });
}

function onDepartmentChange() {
    const enterpriseId = $('#perm_enterprise').val();
    const degree = $('#perm_degree').val();
    const department = $('#perm_department').val();
    
    // Reset dependent dropdowns
    $('#perm_graduation_year').html('<option value="">Select Graduation Year</option>').prop('disabled', true);
    $('#perm_category').html('<option value="">Select Category</option>').prop('disabled', true);
    $('#permissionsCheckboxes').hide();
    
    if (!department) return;
    
    // Load graduation years
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_graduation_years',
            enterprise_id: enterpriseId,
            degree: degree,
            department: department
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success && result.data.length > 0) {
                let options = '<option value="">Select Graduation Year</option>';
                result.data.forEach(function(year) {
                    options += `<option value="${year}">${year}</option>`;
                });
                $('#perm_graduation_year').html(options).prop('disabled', false);
            } else {
                if (typeof toastr !== 'undefined') {
                    toastr.warning('No graduation years found');
                }
            }
        }
    });
}

function onGraduationYearChange() {
    const enterpriseId = $('#perm_enterprise').val();
    const degree = $('#perm_degree').val();
    const department = $('#perm_department').val();
    const graduationYear = $('#perm_graduation_year').val();
    
    // Reset dependent dropdowns
    $('#perm_category').html('<option value="">Select Category</option>').prop('disabled', true);
    $('#permissionsCheckboxes').hide();
    
    if (!graduationYear) return;
    
    // Load categories
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_categories',
            enterprise_id: enterpriseId,
            degree: degree,
            department: department,
            graduation_year: graduationYear
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success && result.data.length > 0) {
                let options = '<option value="">Select Category</option>';
                result.data.forEach(function(category) {
                    options += `<option value="${category}">${category}</option>`;
                });
                $('#perm_category').html(options).prop('disabled', false);
            } else {
                if (typeof toastr !== 'undefined') {
                    toastr.warning('No categories found');
                }
            }
        }
    });
}

function onCategoryChange() {
    const category = $('#perm_category').val();
    
    if (!category) {
        $('#permissionsCheckboxes').hide();
        return;
    }
    
    // Load current permissions
    const enterpriseId = $('#perm_enterprise').val();
    const degree = $('#perm_degree').val();
    const department = $('#perm_department').val();
    const graduationYear = $('#perm_graduation_year').val();
    
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'get_current_permissions',
            enterprise_id: enterpriseId,
            degree: degree,
            department: department,
            graduation_year: graduationYear,
            category: category
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                const perms = result.data;
                
                // Set checkbox values
                $('#perm_is_course').prop('checked', perms.is_course == 1);
                $('#perm_is_books').prop('checked', perms.is_books == 1);
                $('#perm_is_listening').prop('checked', perms.is_listening == 1);
                $('#perm_is_phrases').prop('checked', perms.is_phrases == 1);
                $('#perm_is_speaking').prop('checked', perms.is_speaking == 1);
                $('#perm_is_reading').prop('checked', perms.is_reading == 1);
                $('#perm_is_videos').prop('checked', perms.is_videos == 1);
                
                // Update user count display
                const userCount = result.user_count || 0;
                $('#affectedUsersCount').html(`
                    <div class="alert alert-warning">
                        <i class="ti ti-users me-2"></i>
                        <strong>${userCount}</strong> user(s) will be affected by this permission change.
                    </div>
                `);
                
                // Show permissions checkboxes
                $('#permissionsCheckboxes').show();
            }
        }
    });
}

function updatePermissions() {
    const formData = new FormData(document.getElementById('permissionsForm'));
    formData.append('action', 'update_permissions');
    
    const submitBtn = $('#updatePermissionsBtn');
    const originalText = submitBtn.html();
    
    submitBtn.html('<i class="spinner-border spinner-border-sm me-2"></i>Updating...');
 
    
    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                submitBtn.html('<i class="ti ti-check me-2"></i>Updated!');
                submitBtn.removeClass('btn-primary').addClass('btn-success');
                
                if (typeof toastr !== 'undefined') {
                    toastr.success(result.message);
                }
                
                setTimeout(function() {
                    $('#permissionsModal').modal('hide');
                    submitBtn.html(originalText);
                    submitBtn.removeClass('btn-success').addClass('btn-primary');
                    submitBtn.prop('disabled', false);
                    
                    refreshDataTable();
                }, 2000);
            } else {
                submitBtn.html(originalText);
                submitBtn.prop('disabled', false);
                
                if (typeof toastr !== 'undefined') {
                    toastr.error(result.message);
                }
            }
        },
        error: function() {
            submitBtn.html(originalText);
            submitBtn.prop('disabled', false);
            
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while updating permissions');
            }
        }
    });
}

// Update user function
function updateUser() {
    const formData = new FormData(document.getElementById('editUserForm'));
    formData.append('action', 'update_user');
    
    // Get the submit button and show loading state
    const submitBtn = $('#editUserModal .btn-primary');
    const originalText = submitBtn.html();
    
    // Show loading state
    submitBtn.html('<i class="spinner-border spinner-border-sm me-2"></i>Updating...');
    
    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                // Show success animation
                submitBtn.html('<i class="ti ti-check me-2"></i>Updated!');
                submitBtn.removeClass('btn-primary').addClass('btn-success');
                
                // Show success message (using toastr if available)
                if (typeof toastr !== 'undefined') {
                    toastr.success(result.message);
                } else {
                    console.log('Success: ' + result.message);
                }
                
                // Hide modal after 2 seconds to show tick mark
                setTimeout(function() {
                    // Force close modal
                    $('#editUserModal').modal('hide');
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    $('body').css('padding-right', '');
                    
                    // Reset button immediately
                    submitBtn.html('Update User');
                    submitBtn.removeClass('btn-success').addClass('btn-primary');
                    
                    // Refresh DataTable with current filter
                    refreshDataTable();
                }, 2000);
            } else {
                // Reset button on error
                submitBtn.html(originalText);
                submitBtn.removeClass('btn-success').addClass('btn-primary');
                // Show error message (using toastr if available)
                if (typeof toastr !== 'undefined') {
                    toastr.error(result.message);
                } else {
                    console.log('Error: ' + result.message);
                }
            }
        },
        error: function() {
            // Reset button on error
            submitBtn.html(originalText);
            submitBtn.removeClass('btn-success').addClass('btn-primary');
            // Show error message (using toastr if available)
            if (typeof toastr !== 'undefined') {
                toastr.error('An error occurred while updating user');
            } else {
                console.log('Error: An error occurred while updating user');
            }
        }
    });
}
</script>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- User details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_mobile" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="edit_mobile" name="mobile" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_gender" class="form-label">Gender</label>
                            <select class="form-select" id="edit_gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_points" class="form-label">Total Points</label>
                            <input type="number" class="form-control" id="edit_points" name="points" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Active User
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_verified" name="is_verified">
                                <label class="form-check-label" for="edit_is_verified">
                                    Email Verified
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Paid Status</label>
                            <div class="form-control-plaintext">
                                <span id="edit_paid_status" class="badge bg-secondary">Loading...</span>
                                <small class="text-muted d-block">Subscription status (read-only)</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateUser()">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Change Confirmation Modal -->
<div class="modal fade" id="statusChangeModal" tabindex="-1" aria-labelledby="statusChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusChangeModalLabel">Confirm Status Change</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="statusChangeMessage">Are you sure you want to change this user's status?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStatusChange">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Confirmation Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteUserModalLabel">
                    <i class="ti ti-alert-triangle me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="deleteUserMessage">Are you sure you want to delete this user?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteUser">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Enterprises Modal -->
<div class="modal fade" id="enterprisesModal" tabindex="-1" aria-labelledby="enterprisesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="enterprisesModalLabel">
                    <i class="ti ti-building me-2"></i>User Enterprises
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3" id="addEnterpriseBtn">
                    <button class="btn btn-primary" onclick="openNewEnterpriseModal()">
                        <i class="ti ti-plus me-2"></i>Add New Enterprise
                    </button>
                </div>
                <div id="enterprisesListContainer">
                    <!-- Enterprises will be loaded here -->
                </div>
                <div id="enterprisesTableContainer" style="display: none;">
                    <div class="table-responsive">
                        <table id="enterprisesDataTable" class="table table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th style="width: 80px;">Logo</th>
                                    <th style="width: 150px;">Name</th>
                                    <th style="width: 200px;">Enterprise ID</th>
                                    <th>Description</th>
                                    <th style="width: 80px;">Status</th>
                                    <th style="width: 100px;">Created</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- New Enterprise Modal -->
<div class="modal fade" id="newEnterpriseModal" tabindex="-1" aria-labelledby="newEnterpriseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newEnterpriseModalLabel">
                    <i class="ti ti-building-plus me-2"></i>Create New Enterprise
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newEnterpriseForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="enterprise_name" class="form-label">Enterprise Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="enterprise_name" name="enterprise_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="enterprise_logo" class="form-label">Enterprise Logo</label>
                        <input type="file" class="form-control" id="enterprise_logo" name="enterprise_logo" accept="image/*">
                        <small class="text-muted">Accepted formats: JPG, PNG, GIF (Max 2MB)</small>
                    </div>
                    <div class="mb-3">
                        <label for="enterprise_description" class="form-label">Description</label>
                        <textarea class="form-control" id="enterprise_description" name="enterprise_description" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="createEnterpriseBtn" onclick="createEnterprise()">
                        <i class="ti ti-plus me-2"></i>Create Enterprise
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Users Modal -->
<div class="modal fade" id="importUsersModal" tabindex="-1" aria-labelledby="importUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importUsersModalLabel">
                    <i class="ti ti-upload me-2"></i>Import Enterprise Users
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="importUsersForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="ti ti-info-circle me-2"></i>Excel File Format</h6>
                        <p class="mb-2">Your Excel file should have the following columns in order:</p>
                        <ul class="small mb-0">
                            <li><strong>Column A:</strong> S.No</li>
                            <li><strong>Column B:</strong> First Name <span class="text-danger">*</span></li>
                            <li><strong>Column C:</strong> Last Name <span class="text-danger">*</span></li>
                            <li><strong>Column D:</strong> Gender</li>
                            <li><strong>Column E:</strong> Mobile No. <span class="text-danger">*</span></li>
                            <li><strong>Column F:</strong> Current State</li>
                            <li><strong>Column G:</strong> Current City</li>
                            <li><strong>Column H:</strong> DOB (Date of Birth)</li>
                            <li><strong>Column I:</strong> College Mail ID</li>
                            <li><strong>Column J:</strong> Personal Email ID</li>
                            <li><strong>Column K:</strong> Department</li>
                            <li><strong>Column L:</strong> Degree</li>
                            <li><strong>Column M:</strong> Graduation Year</li>
                            <li><strong>Column N:</strong> Program Category</li>
                            <li><strong>Column O:</strong> enterprise_id <span class="text-danger">*</span></li>
                        </ul>
                        <p class="mt-2 mb-0 small text-muted">
                            <strong>Note:</strong> First row should be headers. Fields marked with <span class="text-danger">*</span> are required. Users will be created with default password: <code>Enterprise@123</code>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="excel_file" class="form-label">Select Excel File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xls,.xlsx" required>
                        <small class="text-muted">Accepted formats: .xls, .xlsx</small>
                    </div>
                    
                    <div id="importResults" style="display: none;">
                        <!-- Import results will be shown here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="importUsersBtn" onclick="importUsers()">
                        <i class="ti ti-upload me-2"></i>Import Users
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Permissions Modal -->
<div class="modal fade" id="permissionsModal" tabindex="-1" aria-labelledby="permissionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="permissionsModalLabel">
                    <i class="ti ti-lock me-2"></i>Manage User Permissions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="permissionsForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="ti ti-info-circle me-2"></i>
                        <strong>Note:</strong> Select filters to apply permissions to specific user groups. Permissions will be updated for <strong>ALL USERS</strong> matching the selected criteria.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="perm_enterprise" class="form-label">
                                <i class="ti ti-building me-1"></i>Enterprise <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="perm_enterprise" name="enterprise_id" onchange="onEnterpriseChange()" required>
                                <option value="">Select Enterprise</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="perm_degree" class="form-label">
                                <i class="ti ti-school me-1"></i>Degree <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="perm_degree" name="degree" onchange="onDegreeChange()" disabled required>
                                <option value="">Select Degree</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="perm_department" class="form-label">
                                <i class="ti ti-briefcase me-1"></i>Department <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="perm_department" name="department" onchange="onDepartmentChange()" disabled required>
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="perm_graduation_year" class="form-label">
                                <i class="ti ti-calendar me-1"></i>Graduation Year <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="perm_graduation_year" name="graduation_year" onchange="onGraduationYearChange()" disabled required>
                                <option value="">Select Graduation Year</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="perm_category" class="form-label">
                                <i class="ti ti-category me-1"></i>Program Category <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="perm_category" name="category" onchange="onCategoryChange()" disabled required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="permissionsCheckboxes" style="display: none;">
                        <hr>
                        
                        <div id="affectedUsersCount" class="mb-3">
                            <!-- User count will be displayed here -->
                        </div>
                        
                        <h6 class="mb-3 text-primary">
                            <i class="ti ti-shield-check me-2"></i>Feature Permissions
                        </h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="perm_is_course" name="is_course">
                                    <label class="form-check-label" for="perm_is_course">
                                        <i class="ti ti-book me-1"></i>Courses
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="perm_is_books" name="is_books">
                                    <label class="form-check-label" for="perm_is_books">
                                        <i class="ti ti-books me-1"></i>Books
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="perm_is_listening" name="is_listening">
                                    <label class="form-check-label" for="perm_is_listening">
                                        <i class="ti ti-headphones me-1"></i>Listening
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="perm_is_phrases" name="is_phrases">
                                    <label class="form-check-label" for="perm_is_phrases">
                                        <i class="ti ti-message me-1"></i>Phrases
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="perm_is_speaking" name="is_speaking">
                                    <label class="form-check-label" for="perm_is_speaking">
                                        <i class="ti ti-microphone me-1"></i>Speaking
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="perm_is_reading" name="is_reading">
                                    <label class="form-check-label" for="perm_is_reading">
                                        <i class="ti ti-book-2 me-1"></i>Reading
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="perm_is_videos" name="is_videos">
                                    <label class="form-check-label" for="perm_is_videos">
                                        <i class="ti ti-video me-1"></i>Videos
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updatePermissionsBtn" onclick="updatePermissions()">
                        <i class="ti ti-check me-2"></i>Update Permissions
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Enterprise Modal -->
<div class="modal fade" id="editEnterpriseModal" tabindex="-1" aria-labelledby="editEnterpriseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEnterpriseModalLabel">
                    <i class="ti ti-edit me-2"></i>Edit Enterprise
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editEnterpriseForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit_enterprise_id" name="enterprise_id">
                    
                    <div class="mb-3">
                        <label for="edit_enterprise_name" class="form-label">Enterprise Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_enterprise_name" name="enterprise_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Logo</label>
                        <div id="current_logo_preview" class="mb-2">
                            <!-- Logo preview will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_enterprise_logo" class="form-label">Update Logo (Optional)</label>
                        <input type="file" class="form-control" id="edit_enterprise_logo" name="enterprise_logo" accept="image/*">
                        <small class="text-muted">Leave empty to keep current logo. Accepted formats: JPG, PNG, GIF (Max 2MB)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_enterprise_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_enterprise_description" name="enterprise_description" rows="4"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">
                                Active Enterprise
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateEnterpriseBtn" onclick="updateEnterprise()">
                        <i class="ti ti-check me-2"></i>Update Enterprise
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require("./layout/Footer.php"); ?>
