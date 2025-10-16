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
            7 => 'created_at',
            8 => 'enterprise_id'
        ];

        // --- 1. Base filtering ---
        $where = " WHERE user_role = 'user' "; // Only show regular users (not enterprise users)
        
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
                u.last_login,
                u.enterprise_id,
                u.user_role
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
                u.created_at, u.last_login, u.enterprise_id, u.user_role,
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
        $enterprise_id = isset($_POST['enterprise_id']) ? trim($_POST['enterprise_id']) : null;
        
        $stmt = $conn->prepare("
            UPDATE users SET 
                full_name = ?, email = ?, mobile_number = ?, 
                gender = ?, total_points = ?, 
                is_active = ?, is_verified = ?, enterprise_id = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssiiisi", $full_name, $email, $mobile, $gender, $points, $is_active, $is_verified, $enterprise_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user']);
        }
        exit;
    }
    

}
?>

<?php require("./layout/Header.php"); ?>


<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">Users</h5>
    </div>
</div>



<!-- Action Buttons -->
<div class="row mb-3">
    <div class="col-md-6">
        <button class="btn btn-primary" onclick="refreshTable()">
            <i class="ti ti-refresh"></i> Refresh
        </button>
        <button class="btn btn-success" onclick="exportUsers()">
            <i class="ti ti-download"></i> Export
        </button>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="all" onclick="filterUsers('all')">All</button>
            <button type="button" class="btn btn-outline-success filter-btn" data-filter="verified" onclick="filterUsers('verified')">Verified</button>
            <button type="button" class="btn btn-outline-warning filter-btn" data-filter="unverified" onclick="filterUsers('unverified')">Unverified</button>
            <button type="button" class="btn btn-outline-info filter-btn" data-filter="active" onclick="filterUsers('active')">Active</button>
            <button type="button" class="btn btn-outline-success filter-btn" data-filter="paid" onclick="filterUsers('paid')">Paid</button>
            <button type="button" class="btn btn-outline-secondary filter-btn" data-filter="unpaid" onclick="filterUsers('unpaid')">Unpaid</button>
        </div>
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
</style>

<script>
$(document).ready(function() {
    // Set initial active filter
    $('.filter-btn[data-filter="all"]').addClass('active');
    
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
    //         $('#confirmStatusChange').prop('disabled', false).text('Confirm');
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
                return d;
            }
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rt<"d-flex justify-content-between mt-3"lp>',
        buttons: [
            { 
                extend: 'csvHtml5', 
                className: 'btn btn-sm btn-outline-info',
                title: 'Users_Export',
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
                title: 'Users_Export',
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
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewUser(${row[0]})" title="View">
                                <i class="ti ti-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="editUser(${row[0]})" title="Edit">
                                <i class="ti ti-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-${row[4] == 1 ? 'danger' : 'success'}" 
                                    onclick="toggleUserStatus(${row[0]}, ${row[4]})" 
                                    title="${row[4] == 1 ? 'Deactivate' : 'Activate'}">
                                <i class="ti ti-${row[4] == 1 ? 'user-x' : 'user-check'}"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${row[0]})" title="Delete">
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>
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

function filterUsers(type) {
    currentFilter = type;
    
    // Update button states
    $('.filter-btn').removeClass('active');
    $(`.filter-btn[data-filter="${type}"]`).addClass('active');
    
    refreshDataTable();
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

// Global variables for delete confirmation
let deleteUserId = null;

function deleteUser(userId) {
    deleteUserId = userId;
    
    $('#deleteUserMessage').html('<strong class="text-danger">Warning:</strong> Are you sure you want to permanently delete this user? This action cannot be undone and will remove all user data from the database.');
    $('#deleteUserModal').modal('show');
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
                                
                        $('#confirmStatusChange').removeClass('btn-primary').addClass('btn-success');
                        
                        $('#statusChangeModal').modal('hide');
                        }, 2000);
                        
            
                            // $('#confirmStatusChange').prop('disabled', false).text('Confirm');
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
            submitBtn.prop('disabled', false).html(originalText);
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

<?php require("./layout/Footer.php"); ?>
