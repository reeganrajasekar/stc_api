<?php 
require("../layout/Session.php");
require("../../config/db.php"); 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $params  = $_REQUEST;
    $data    = [];
    $columns = [
        0 => 'id',
        1 => 'data',
        2 => 'phone',
        3 => 'created_at',
    ];

    // --- 1. Total records ---
    $sqlTotal = "SELECT COUNT(*) as cnt FROM logs_call";
    $resTotal = $conn->query($sqlTotal);
    $rowTotal = $resTotal->fetch_assoc();
    $totalRecords = $rowTotal['cnt'];

    // --- 2. Filtering ---
    $where = " WHERE 1=1 ";
    if (!empty($params['search']['value'])) {
        $search = $conn->real_escape_string($params['search']['value']);
        $where .= " AND (
            id LIKE '%$search%' 
            OR data LIKE '%$search%' 
            OR country_code LIKE '%$search%' 
            OR mobile LIKE '%$search%' 
        )";
    }

    // --- 3. Filtered count ---
    $sqlFiltered = "SELECT COUNT(*) as cnt FROM logs_call $where";
    $resFiltered = $conn->query($sqlFiltered);
    $rowFiltered = $resFiltered->fetch_assoc();
    $totalFiltered = $rowFiltered['cnt'];

    // --- 4. Ordering ---
    $orderCol = $columns[$params['order'][0]['column']] ?? 'created_at';
    $orderDir = $params['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';

    // Always force default ordering by date DESC if no column clicked
    if (!isset($params['order'])) {
        $orderCol = "created_at";
        $orderDir = "DESC";
    }

    // --- 5. Pagination ---
    $start  = intval($params['start']);
    $length = intval($params['length']);
    $limit  = $length > 0 ? "LIMIT $start, $length" : "";

    // --- 6. Data query ---
    $sqlData = "SELECT id, data, CONCAT(country_code, ' ', mobile) AS phone, created_at
                FROM logs_call
                $where
                ORDER BY $orderCol $orderDir
                $limit";

    $query = $conn->query($sqlData);
    while ($row = $query->fetch_assoc()) {
        $data[] = [
            $row['id'],
            $row['data'],
            $row['phone'],
            date("d-m-Y h:i A", strtotime($row['created_at'])),
        ];
    }

    // --- 7. JSON response ---
    $json_data = [
        "draw"            => intval($params['draw']),
        "recordsTotal"    => intval($totalRecords),
        "recordsFiltered" => intval($totalFiltered),
        "data"            => $data
    ];

    $conn->close();
    echo json_encode($json_data);
    exit;
}

?>
<?php require("../layout/Header.php"); ?>

<!-- ====== Page Title ====== -->
<div class="card mb-3 shadow-sm border">
    <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center justify-content-between">
            <h5 class="h4 text-primary m-0 fw-bolder">Call Logs</h5>
        </div>
    </div>
</div>


<?php
// ---------- Stats for cards ----------
$stats = [
    "total" => 0,
    "today" => 0,
    "week"  => 0,
    "month" => 0
];

$q1 = $conn->query("SELECT COUNT(*) AS cnt FROM logs_call");
$stats['total'] = $q1->fetch_assoc()['cnt'];

$q2 = $conn->query("SELECT COUNT(*) AS cnt FROM logs_call WHERE DATE(created_at) = CURDATE()");
$stats['today'] = $q2->fetch_assoc()['cnt'];

$q3 = $conn->query("SELECT COUNT(*) AS cnt 
                    FROM logs_call 
                    WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
$stats['week'] = $q3->fetch_assoc()['cnt'];

$q4 = $conn->query("SELECT COUNT(*) AS cnt 
                    FROM logs_call 
                    WHERE YEAR(created_at)=YEAR(CURDATE()) 
                      AND MONTH(created_at)=MONTH(CURDATE())");
$stats['month'] = $q4->fetch_assoc()['cnt'];

?>
<!-- ====== Stats Cards ====== -->
 <div class="row mb-3">
    <div class="col-lg-3 col-md-6">
        <div class="card text-center shadow-sm border mb-0">
            <div class="card-body">
                <h4 class="text-primary h1"><?= $stats['total']; ?></h4>
                <p class="mb-0 fs-4">Total Calls</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center shadow-sm border mb-0">
            <div class="card-body">
                <h4 class="text-success h1"><?= $stats['today']; ?></h4>
                <p class="mb-0 fs-4">Today</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center shadow-sm border mb-0">
            <div class="card-body">
                <h4 class="text-secondary h1"><?= $stats['week']; ?></h4>
                <p class="mb-0 fs-4">This Week</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center shadow-sm border mb-0">
            <div class="card-body">
                <h4 class="text-warning h1"><?= $stats['month']; ?></h4>
                <p class="mb-0 fs-4">This Month</p>
            </div>
        </div>
    </div>
</div>

<?php
// ---------- 7-Day Category Stats ----------
$chartData = [];
$sqlChart = "
    SELECT 
        DATE(created_at) AS log_date,
        data AS category,
        COUNT(*) AS total_count
    FROM logs_call
    WHERE DATE(created_at) >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(created_at), data
    ORDER BY DATE(created_at) DESC, data ASC
";
$resChart = $conn->query($sqlChart);

while ($row = $resChart->fetch_assoc()) {
    $chartData[] = [
        "date"     => date("d-m-Y", strtotime($row['log_date'])),
        "category" => $row['category'],
        "count"    => intval($row['total_count'])
    ];
} 

// ---------- All-Time Category Stats ----------
$allTimeData = [];
$sqlAllTime = "
    SELECT 
        data AS category,
        COUNT(*) AS total_count
    FROM logs_call
    GROUP BY data
    ORDER BY total_count DESC
";
$resAllTime = $conn->query($sqlAllTime);

while ($row = $resAllTime->fetch_assoc()) {
    $allTimeData[] = [
        "category" => $row['category'],
        "count"    => intval($row['total_count'])
    ];
}

?>
<!-- ====== Charts Section ====== -->
<div class="row mb-3">
  <div class="col-lg-7">
    <div class="card shadow-sm border mb-0">
      <div class="card-body">
        <h5 class="fw-bold text-primary mb-3">Last 7 Days - Category Summary</h5>
        <div id="chart7days"></div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card shadow-sm border mb-0">
      <div class="card-body">
        <h5 class="fw-bold text-primary mb-3">All Time - Category Summary</h5>
        <div id="chartAllTime"></div>
      </div>
    </div>
  </div>
</div>

<script>
const chart7daysData = <?= json_encode($chartData); ?>;
const chartAllTimeData = <?= json_encode($allTimeData); ?>;

// --- 7-Day Chart (Stacked Bar) ---
(function() {
  const grouped = {};
  chart7daysData.forEach(item => {
    if (!grouped[item.category]) grouped[item.category] = {};
    grouped[item.category][item.date] = item.count;
  });
  const dates = [...new Set(chart7daysData.map(i => i.date))].sort();
  const series = Object.keys(grouped).map(cat => ({
    name: cat,
    data: dates.map(d => grouped[cat][d] || 0)
  }));

  new ApexCharts(document.querySelector("#chart7days"), {
    chart: { type: 'bar', height: 300, stacked: true },
    series: series,
    xaxis: { categories: dates },
    legend: { position: 'bottom' },
    dataLabels: { enabled: false },
    title: { text: '' }
  }).render();
})();

// --- All-Time Category Chart (Donut) ---
(function() {
  const categories = chartAllTimeData.map(i => i.category);
  const counts = chartAllTimeData.map(i => i.count);

  new ApexCharts(document.querySelector("#chartAllTime"), {
    chart: { type: 'donut', height: 310 },
    labels: categories,
    series: counts,
    legend: { position: 'bottom' },
    title: { text: '' },
    dataLabels: { enabled: true }
  }).render();
})();
</script>

<!-- ====== DataTable ====== -->
<div class="card mb-3 shadow-sm border p-4">
    <div class="table-responsive">
        <table id="dataTable" class="table table-hover table-borderless align-middle mb-0 border-0 rounded-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Category</th>
                    <th>Recipient</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- ====== DataTable Script ====== -->
<script type="text/javascript">
$(document).ready(function() {
    var table = $('#dataTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [[3, "desc"]], // default order: Created At DESC
        dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rt<"d-flex justify-content-between mt-3"lp>',
        lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
        ],
        pageLength: 10,
        buttons: [
            { extend: 'csvHtml5', className: 'btn btn-sm btn-outline-info' },
            { extend: 'pdfHtml5', orientation: 'landscape', pageSize: 'A4', className: 'btn btn-sm btn-outline-info' },
        ],
        columnDefs: [
            {
                targets: 0,
                orderable: false,
                render: function (data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            }
        ],
        ajax: {
            url: "",
            type: "post",
            beforeSend: function() {
                let skeleton = '';
                for (let i = 0; i < 10; i++) {
                    skeleton += `
                    <tr class="skeleton-row">
                        <td><div class="skeleton"></div></td>
                        <td><div class="skeleton"></div></td>
                        <td><div class="skeleton"></div></td>
                        <td><div class="skeleton"></div></td>
                    </tr>`;
                }
                $("#dataTable tbody").html(skeleton);
            },
            error: function() {
                $("#dataTable").hide();
            }
        }
    });
});
</script>

<?php require("../layout/Footer.php"); ?>
