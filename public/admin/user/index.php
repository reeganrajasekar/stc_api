<?php 
require("./layout/Session.php");
require("../config/db.php"); 
require("./layout/Header.php");

// ---------- User Analytics Stats ----------
$totalUsers = 0;
$activeUsers = 0;
$thisMonthUsers = 0;
$lastMonthUsers = 0;
$paidUsers = 0;
$freeUsers = 0;
$completedUsers = 0;
$inProgressUsers = 0;

// Check if users table exists and get comprehensive user stats
if ($conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0) {
    // Total users
    $totalUsers = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
    
    // Active users
    $activeUsers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE is_active=1")->fetch_assoc()['c'];
    
    // This month registrations
    $thisMonthUsers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetch_assoc()['c'];
    
    // Last month registrations
    $lastMonthUsers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)")->fetch_assoc()['c'];
}

// ---------- Enterprise User Stats ----------
$totalEnterpriseUsers = 0;
$activeEnterpriseUsers = 0;
$verifiedEnterpriseUsers = 0;
$unverifiedEnterpriseUsers = 0;
$enterpriseUsersWithCourseAccess = 0;
$enterpriseUsersWithReadingAccess = 0;
$enterpriseUsersWithListeningAccess = 0;
$enterpriseUsersWithSpeakingAccess = 0;

if ($conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0) {
    // Total enterprise users
    $totalEnterpriseUsers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise'")->fetch_assoc()['c'];
    
    // Active enterprise users
    $activeEnterpriseUsers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise' AND is_active=1")->fetch_assoc()['c'];
    
    // Verified enterprise users
    $verifiedEnterpriseUsers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise' AND is_verified=1")->fetch_assoc()['c'];
    
    // Unverified enterprise users
    $unverifiedEnterpriseUsers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise' AND is_verified=0")->fetch_assoc()['c'];
    
    // Check if permission columns exist
    $columnsExist = $conn->query("SHOW COLUMNS FROM users LIKE 'is_course'")->num_rows > 0;
    
    if ($columnsExist) {
        // Enterprise users with course access
        $enterpriseUsersWithCourseAccess = $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise' AND is_course=1")->fetch_assoc()['c'];
        
        // Enterprise users with reading access
        $enterpriseUsersWithReadingAccess = $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise' AND is_reading=1")->fetch_assoc()['c'];
        
        // Enterprise users with listening access
        $enterpriseUsersWithListeningAccess = $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise' AND is_listening=1")->fetch_assoc()['c'];
        
        // Enterprise users with speaking access
        $enterpriseUsersWithSpeakingAccess = $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise' AND is_speaking=1")->fetch_assoc()['c'];
    }
}

// Check subscription data for paid/free users
if ($conn->query("SHOW TABLES LIKE 'user_subscriptions'")->num_rows > 0) {
    $paidUsers = $conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM user_subscriptions WHERE is_active=1 AND payment_status='completed'")->fetch_assoc()['c'];
    $freeUsers = $totalUsers - $paidUsers;
} else {
    $freeUsers = $totalUsers; // If no subscription table, all users are free
}

// Course completion data - not needed for free resources
$completedUsers = 0;
$inProgressUsers = 0;

// ---------- User Registration Trends (Last 12 Months) ----------
$registrationMonths = [];
$registrationCounts = [];
if ($conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0) {
    $userRegistrations = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) as total
        FROM users 
        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    while ($row = $userRegistrations->fetch_assoc()) {
        $registrationMonths[] = date('M Y', strtotime($row['month'] . '-01'));
        $registrationCounts[] = $row['total'];
    }
}

// ---------- User Gender Distribution ----------
$genderData = [];
if ($conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0) {
    $genderStats = $conn->query("
        SELECT 
            COALESCE(gender, 'Not Specified') as gender,
            COUNT(*) as total
        FROM users 
        WHERE is_active=1
        GROUP BY COALESCE(gender, 'Not Specified')
        ORDER BY total DESC
    ");
    while ($row = $genderStats->fetch_assoc()) {
        $genderData[$row['gender']] = (int)$row['total'];
    }
}

// ---------- Enterprise Users by Enterprise ----------
$enterpriseDistribution = [];
if ($conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0 && $conn->query("SHOW TABLES LIKE 'enterprises'")->num_rows > 0) {
    $enterpriseStats = $conn->query("
        SELECT 
            e.enterprise_name,
            COUNT(u.id) as total
        FROM enterprises e
        LEFT JOIN users u ON e.enterprise_id COLLATE utf8mb4_unicode_ci = u.enterprise_id COLLATE utf8mb4_unicode_ci 
            AND u.user_role='enterprise'
        WHERE e.is_active=1
        GROUP BY e.enterprise_id, e.enterprise_name
        ORDER BY total DESC
        LIMIT 10
    ");
    if ($enterpriseStats) {
        while ($row = $enterpriseStats->fetch_assoc()) {
            $enterpriseDistribution[$row['enterprise_name']] = (int)$row['total'];
        }
    }
}

// ---------- Enterprise Permission Distribution ----------
$permissionData = [];
if ($conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0) {
    $columnsExist = $conn->query("SHOW COLUMNS FROM users LIKE 'is_course'")->num_rows > 0;
    if ($columnsExist) {
        $permissionData = [
            'Courses' => $enterpriseUsersWithCourseAccess,
            'Reading' => $enterpriseUsersWithReadingAccess,
            'Listening' => $enterpriseUsersWithListeningAccess,
            'Speaking' => $enterpriseUsersWithSpeakingAccess,
            'Books' => $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise' AND is_books=1")->fetch_assoc()['c'],
            'Phrases' => $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise' AND is_phrases=1")->fetch_assoc()['c'],
            'Videos' => $conn->query("SELECT COUNT(*) AS c FROM users WHERE user_role='enterprise' AND is_videos=1")->fetch_assoc()['c']
        ];
    }
}

?>

<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="h4 text-primary fw-bolder m-0">User Analytics Dashboard</h5>
        <div class="d-flex gap-2">
            <span class="badge bg-success">Total Users: <?= $totalUsers ?></span>
            <span class="badge bg-primary">Active: <?= $activeUsers ?></span>
        </div>
    </div>
</div>

<?php 
// Check if database tables exist
$tablesExist = [
    'users' => $conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0,
    'user_subscriptions' => $conn->query("SHOW TABLES LIKE 'user_subscriptions'")->num_rows > 0
];

$missingTables = array_keys(array_filter($tablesExist, function($exists) { return !$exists; }));

if (!empty($missingTables)): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <strong>Database Setup Required:</strong> The following tables are missing: <?= implode(', ', $missingTables) ?>. 
    Please run the database schema to create these tables for full user analytics functionality.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ====== 8 Key Metrics Cards ====== -->
<div class="row g-3 mb-4">
    <!-- Card 1: Total Users -->
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-2 fw-medium">Total Users</p>
                        <h2 class="mb-0 fw-bold"><?= number_format($totalUsers) ?></h2>
                        <small class="text-success"><i class="ti ti-arrow-up"></i> All registered</small>
                    </div>
                    <div class="avatar-lg rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center p-2">
                        <i class="ti ti-users text-primary" style="font-size: 25px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Active Users -->
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-2 fw-medium">Active Users</p>
                        <h2 class="mb-0 fw-bold"><?= number_format($activeUsers) ?></h2>
                        <small class="text-success"><i class="ti ti-check"></i> Currently active</small>
                    </div>
                    <div class="avatar-lg rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center p-2">
                        <i class="ti ti-user-check text-success" style="font-size: 25px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: This Month -->
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-2 fw-medium">This Month</p>
                        <h2 class="mb-0 fw-bold"><?= number_format($thisMonthUsers) ?></h2>
                        <small class="text-info"><i class="ti ti-calendar"></i> New registrations</small>
                    </div>
                    <div class="avatar-lg rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center p-2">
                        <i class="ti ti-user-plus text-info" style="font-size: 25px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Last Month -->
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-2 fw-medium">Last Month</p>
                        <h2 class="mb-0 fw-bold"><?= number_format($lastMonthUsers) ?></h2>
                        <small class="text-warning"><i class="ti ti-calendar-minus"></i> Previous month</small>
                    </div>
                    <div class="avatar-lg rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center p-2">
                        <i class="ti ti-calendar text-warning" style="font-size: 25px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 5: Paid Users -->
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-2 fw-medium">Paid Users</p>
                        <h2 class="mb-0 fw-bold"><?= number_format($paidUsers) ?></h2>
                        <small class="text-success"><i class="ti ti-credit-card"></i> Premium subscribers</small>
                    </div>
                    <div class="avatar-lg rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center p-2">
                        <i class="ti ti-credit-card text-success" style="font-size: 25px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 6: Free Users -->
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-2 fw-medium">Free Users</p>
                        <h2 class="mb-0 fw-bold"><?= number_format($freeUsers) ?></h2>
                        <small class="text-info"><i class="ti ti-gift"></i> Using free features</small>
                    </div>
                    <div class="avatar-lg rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center p-2">
                        <i class="ti ti-gift text-info" style="font-size: 25px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 7: Enterprise Users -->
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-2 fw-medium">Enterprise</p>
                        <h2 class="mb-0 fw-bold"><?= number_format($totalEnterpriseUsers) ?></h2>
                        <small class="text-primary"><i class="ti ti-building"></i> Total enterprise users</small>
                    </div>
                    <div class="avatar-lg rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center p-2">
                        <i class="ti ti-building text-primary" style="font-size: 25px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 8: Inactive Users -->
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-2 fw-medium">Inactive</p>
                        <h2 class="mb-0 fw-bold"><?= number_format($totalUsers - $activeUsers) ?></h2>
                        <small class="text-danger"><i class="ti ti-user-x"></i> Not currently active</small>
                    </div>
                    <div class="avatar-lg rounded-circle bg-danger bg-opacity-10 d-flex align-items-center justify-content-center p-2">
                        <i class="ti ti-user-x text-danger" style="font-size: 25px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====== User Analytics Charts ====== -->
<div class="row mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm border mb-0">
            <div class="card-body">
                <h5 class="fw-bold text-primary mb-3">User Registration Trends (Last 12 Months)</h5>
                <div id="userRegistrationChart"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border mb-0">
            <div class="card-body">
                <h5 class="fw-bold text-primary mb-3">User Gender Distribution</h5>
                <div id="userGenderChart"></div>
            </div>
        </div>
    </div>
</div>

<!-- ====== Enterprise Analytics Charts ====== -->
<!-- <div class="row mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm border mb-0">
            <div class="card-body">
                <h5 class="fw-bold text-success mb-3">
                    <i class="ti ti-building me-2"></i>Enterprise User Distribution
                </h5>
                <div id="enterpriseDistributionChart"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border mb-0">
            <div class="card-body">
                <h5 class="fw-bold text-success mb-3">
                    <i class="ti ti-lock me-2"></i>Permission Access Distribution
                </h5>
                <div id="permissionDistributionChart"></div>
            </div>
        </div>
    </div>
</div> -->

<!-- ====== ApexCharts ====== -->
<script>
// User Registration Trends (Line Chart)
var userRegistrationOptions = {
    chart: { 
        type: 'line', 
        height: 350, 
        toolbar: { show: true },
        zoom: { enabled: true }
    },
    series: [{ 
        name: 'New Users', 
        data: <?= json_encode($registrationCounts) ?> 
    }],
    xaxis: { 
        categories: <?= json_encode($registrationMonths) ?>,
        title: { text: 'Month' }
    },
    yaxis: {
        title: { text: 'Number of Users' }
    },
    stroke: { curve: 'smooth', width: 3 },
    dataLabels: { enabled: false },
    colors: ['#008FFB'],
    grid: { borderColor: '#f1f1f1' }
};
new ApexCharts(document.querySelector("#userRegistrationChart"), userRegistrationOptions).render();

// User Gender Distribution (Donut Chart)
var genderLabels = <?= json_encode(array_keys($genderData)) ?>;
var genderValues = <?= json_encode(array_values($genderData)) ?>;

// Debug: Log gender data
console.log('Gender Labels:', genderLabels);
console.log('Gender Values:', genderValues);

var userGenderOptions = {
    chart: { 
        type: 'donut', 
        height: 350 
    },
    series: genderValues.length > 0 ? genderValues : [1],
    labels: genderLabels.length > 0 ? genderLabels : ['No Data'],
    colors: ['#008FFB','#FF69B4'], // Rose for female, Blue for male
    dataLabels: { 
        enabled: true, 
        formatter: function (val) { return Math.round(val) + "%" } // Round to whole number
    },
    legend: { position: 'bottom' },
    plotOptions: {
        pie: {
            donut: {
                size: '70%'
            }
        }
    },
    noData: {
        text: 'No gender data available',
        align: 'center',
        verticalAlign: 'middle',
        style: {
            color: '#999',
            fontSize: '14px'
        }
    }
};

// Only render if we have data
if (genderValues.length > 0 && genderValues.some(val => val > 0)) {
    new ApexCharts(document.querySelector("#userGenderChart"), userGenderOptions).render();
} else {
    document.querySelector("#userGenderChart").innerHTML = '<div class="text-center text-muted p-4">No gender data available</div>';
}

// ====== Enterprise Distribution Chart (Bar Chart) ======
var enterpriseLabels = <?= json_encode(array_keys($enterpriseDistribution)) ?>;
var enterpriseValues = <?= json_encode(array_values($enterpriseDistribution)) ?>;

var enterpriseDistributionOptions = {
    chart: { 
        type: 'bar', 
        height: 350,
        toolbar: { show: true }
    },
    series: [{ 
        name: 'Users', 
        data: enterpriseValues.length > 0 ? enterpriseValues : [0]
    }],
    xaxis: { 
        categories: enterpriseLabels.length > 0 ? enterpriseLabels : ['No Data'],
        title: { text: 'Enterprise' },
        labels: {
            rotate: -45,
            rotateAlways: true,
            style: {
                fontSize: '11px'
            }
        }
    },
    yaxis: {
        title: { text: 'Number of Users' }
    },
    colors: ['#28a745'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            horizontal: false,
            columnWidth: '60%',
            dataLabels: {
                position: 'top'
            }
        }
    },
    dataLabels: {
        enabled: true,
        offsetY: -20,
        style: {
            fontSize: '12px',
            colors: ["#304758"]
        }
    },
    grid: { borderColor: '#f1f1f1' },
    noData: {
        text: 'No enterprise data available',
        align: 'center',
        verticalAlign: 'middle'
    }
};

if (enterpriseValues.length > 0 && enterpriseValues.some(val => val > 0)) {
    new ApexCharts(document.querySelector("#enterpriseDistributionChart"), enterpriseDistributionOptions).render();
} else {
    document.querySelector("#enterpriseDistributionChart").innerHTML = '<div class="text-center text-muted p-4">No enterprise data available</div>';
}

// ====== Permission Distribution Chart (Radar Chart) ======
var permissionLabels = <?= json_encode(array_keys($permissionData)) ?>;
var permissionValues = <?= json_encode(array_values($permissionData)) ?>;

var permissionDistributionOptions = {
    chart: { 
        type: 'radar', 
        height: 350,
        toolbar: { show: false }
    },
    series: [{ 
        name: 'Users with Access', 
        data: permissionValues.length > 0 ? permissionValues : [0]
    }],
    xaxis: { 
        categories: permissionLabels.length > 0 ? permissionLabels : ['No Data']
    },
    yaxis: {
        show: true,
        tickAmount: 4
    },
    colors: ['#28a745'],
    fill: {
        opacity: 0.2
    },
    stroke: {
        show: true,
        width: 2,
        colors: ['#28a745']
    },
    markers: {
        size: 4,
        colors: ['#28a745'],
        strokeColors: '#fff',
        strokeWidth: 2
    },
    dataLabels: {
        enabled: true,
        background: {
            enabled: true,
            borderRadius: 2
        }
    },
    plotOptions: {
        radar: {
            polygons: {
                strokeColors: '#e8e8e8',
                fill: {
                    colors: ['#f8f8f8', '#fff']
                }
            }
        }
    },
    noData: {
        text: 'No permission data available',
        align: 'center',
        verticalAlign: 'middle'
    }
};

if (permissionValues.length > 0 && permissionValues.some(val => val > 0)) {
    new ApexCharts(document.querySelector("#permissionDistributionChart"), permissionDistributionOptions).render();
} else {
    document.querySelector("#permissionDistributionChart").innerHTML = '<div class="text-center text-muted p-4">No permission data available</div>';
}

</script>

<?php require("./layout/Footer.php") ?>
