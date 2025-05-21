<?php
session_start();
require_once "config/database.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Initialize date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Adjust end date to include the entire day
$end_date = $end_date . ' 23:59:59';

// Initialize data arrays
$inventory_data = [
    'total_items' => 0,
    'total_quantity' => 0,
    'low_stock_items' => 0
];

$requisition_data = [
    'total_requisitions' => 0,
    'pending_requisitions' => 0,
    'approved_requisitions' => 0,
    'rejected_requisitions' => 0,
    'total_approved_quantity' => 0
];

// Get inventory and requisition summary in a single query
$sql_summary = "SELECT 
    (SELECT COUNT(*) FROM inventory) as total_items,
    (SELECT SUM(quantity) FROM inventory) as total_quantity,
    (SELECT COUNT(*) FROM inventory WHERE quantity < 10) as low_stock_items,
    (SELECT COUNT(*) FROM requisitions WHERE created_at BETWEEN ? AND ?) as total_requisitions,
    (SELECT COUNT(*) FROM requisitions WHERE status = 'pending' AND created_at BETWEEN ? AND ?) as pending_requisitions,
    (SELECT COUNT(*) FROM requisitions WHERE status = 'approved' AND created_at BETWEEN ? AND ?) as approved_requisitions,
    (SELECT COUNT(*) FROM requisitions WHERE status = 'rejected' AND created_at BETWEEN ? AND ?) as rejected_requisitions,
    (SELECT SUM(quantity) FROM requisitions WHERE status = 'approved' AND created_at BETWEEN ? AND ?) as total_approved_quantity";

$stmt = mysqli_prepare($conn, $sql_summary);
if($stmt) {
    mysqli_stmt_bind_param($stmt, "ssssssssss", 
        $start_date, $end_date,
        $start_date, $end_date,
        $start_date, $end_date,
        $start_date, $end_date,
        $start_date, $end_date
    );
    mysqli_stmt_execute($stmt);
    $summary_result = mysqli_stmt_get_result($stmt);
    if($summary_result) {
        $summary_data = mysqli_fetch_assoc($summary_result);
        $inventory_data = [
            'total_items' => $summary_data['total_items'] ?? 0,
            'total_quantity' => $summary_data['total_quantity'] ?? 0,
            'low_stock_items' => $summary_data['low_stock_items'] ?? 0
        ];
        $requisition_data = [
            'total_requisitions' => $summary_data['total_requisitions'] ?? 0,
            'pending_requisitions' => $summary_data['pending_requisitions'] ?? 0,
            'approved_requisitions' => $summary_data['approved_requisitions'] ?? 0,
            'rejected_requisitions' => $summary_data['rejected_requisitions'] ?? 0,
            'total_approved_quantity' => $summary_data['total_approved_quantity'] ?? 0
        ];
    }
    mysqli_stmt_close($stmt);
}

// Get category-wise inventory distribution with optimized query
$sql_categories = "SELECT 
    c.name as category_name,
    COUNT(i.id) as item_count,
    COALESCE(SUM(i.quantity), 0) as total_quantity,
    COUNT(CASE WHEN i.quantity < 10 THEN 1 END) as low_stock_count
    FROM categories c
    LEFT JOIN inventory i ON c.id = i.category_id
    GROUP BY c.id, c.name
    ORDER BY item_count DESC";
$categories_result = mysqli_query($conn, $sql_categories);
if(!$categories_result) {
    echo "Error in categories query: " . mysqli_error($conn);
}

// Get monthly trends with optimized query
$sql_trends = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as total_requisitions,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM requisitions
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
    LIMIT 12"; // Limit to last 12 months

$stmt = mysqli_prepare($conn, $sql_trends);
if($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $trends_result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
}

// Get low stock items with optimized query
$sql_low_stock = "SELECT i.*, c.name as category_name 
    FROM inventory i 
    JOIN categories c ON i.category_id = c.id 
    WHERE i.quantity < 10 
    ORDER BY i.quantity ASC
    LIMIT 50"; // Limit to 50 items
$low_stock_result = mysqli_query($conn, $sql_low_stock);
if(!$low_stock_result) {
    echo "Error in low stock query: " . mysqli_error($conn);
}

// Get recent requisitions with optimized query
$sql_recent_requisitions = "SELECT r.*, i.item_name, i.unit, u.username, c.name as category_name
    FROM requisitions r 
    JOIN inventory i ON r.item_id = i.id 
    JOIN users u ON r.requested_by = u.id 
    JOIN categories c ON i.category_id = c.id
    WHERE r.created_at BETWEEN ? AND ?
    ORDER BY r.created_at DESC
    LIMIT 20"; // Limit to 20 recent requisitions

$stmt = mysqli_prepare($conn, $sql_recent_requisitions);
if($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $recent_requisitions = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
}

// Prepare data for charts
$months = [];
$approved = [];
$rejected = [];
if(isset($trends_result)) {
    while($row = mysqli_fetch_assoc($trends_result)) {
        $months[] = date('M Y', strtotime($row['month'] . '-01'));
        $approved[] = $row['approved_count'];
        $rejected[] = $row['rejected_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - SIMS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            height: 100vh;
            background-color: #343a40;
            padding-top: 20px;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .content {
            padding: 20px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Print styles */
        @media print {
            .sidebar, .no-print {
                display: none !important;
            }
            .content {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .col-md-10 {
                width: 100% !important;
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
            .card {
                break-inside: avoid;
                page-break-inside: avoid;
                border: 1px solid #ddd !important;
            }
            .chart-container {
                height: 200px !important;
            }
            .table {
                font-size: 12px !important;
            }
            .card-title {
                font-size: 16px !important;
            }
            .card-text {
                font-size: 14px !important;
            }
            .container-fluid {
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .row {
                margin: 0 !important;
            }
            .col-md-3, .col-md-6, .col-md-12 {
                padding: 0 10px !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <h3 class="text-white text-center mb-4">SIMS</h3>
                <nav>
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="categories.php"><i class="fas fa-tags"></i> Categories</a>
                    <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
                    <a href="requisition.php"><i class="fas fa-clipboard-list"></i> Requisitions</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Reports</h2>
                    <div class="d-flex align-items-center">
                        <form class="form-inline mr-3">
                            <div class="form-group mx-sm-3">
                                <label for="start_date" class="mr-2">From:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="form-group mx-sm-3">
                                <label for="end_date" class="mr-2">To:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary mr-2">Filter</button>
                        </form>
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Items</h5>
                                <h2 class="card-text"><?php echo $inventory_data['total_items']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Quantity</h5>
                                <h2 class="card-text"><?php echo $inventory_data['total_quantity']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock Items</h5>
                                <h2 class="card-text"><?php echo $inventory_data['low_stock_items']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Requisitions</h5>
                                <h2 class="card-text"><?php echo $requisition_data['total_requisitions']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Requisition Status Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="requisitionStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Monthly Requisition Trends</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Distribution -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Category-wise Inventory Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Total Items</th>
                                                <th>Total Quantity</th>
                                                <th>Low Stock Items</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if($categories_result) {
                                                while($row = mysqli_fetch_assoc($categories_result)) {
                                                    echo "<tr>";
                                                    echo "<td>" . htmlspecialchars($row['category_name']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row['item_count']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row['total_quantity'] ?? 0) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row['low_stock_count']) . "</td>";
                                                    echo "</tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Items and Recent Requisitions -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Low Stock Items</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Item Name</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Unit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if($low_stock_result) {
                                                while($row = mysqli_fetch_assoc($low_stock_result)) {
                                                    echo "<tr>";
                                                    echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row['category_name']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row['quantity']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row['unit']) . "</td>";
                                                    echo "</tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Requisitions</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if(isset($recent_requisitions)) {
                                                while($row = mysqli_fetch_assoc($recent_requisitions)) {
                                                    $status_class = '';
                                                    switch($row['status']) {
                                                        case 'pending':
                                                            $status_class = 'warning';
                                                            break;
                                                        case 'approved':
                                                            $status_class = 'success';
                                                            break;
                                                        case 'rejected':
                                                            $status_class = 'danger';
                                                            break;
                                                    }
                                                    
                                                    echo "<tr>";
                                                    echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row['category_name']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row['quantity']) . " " . htmlspecialchars($row['unit']) . "</td>";
                                                    echo "<td><span class='badge badge-" . $status_class . "'>" . ucfirst(htmlspecialchars($row['status'])) . "</span></td>";
                                                    echo "<td>" . date('M d, Y', strtotime($row['created_at'])) . "</td>";
                                                    echo "</tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Requisition Status Chart
        const statusCtx = document.getElementById('requisitionStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: ['Pending', 'Approved', 'Rejected'],
                datasets: [{
                    data: [
                        <?php echo $requisition_data['pending_requisitions']; ?>,
                        <?php echo $requisition_data['approved_requisitions']; ?>,
                        <?php echo $requisition_data['rejected_requisitions']; ?>
                    ],
                    backgroundColor: ['#ffc107', '#28a745', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Monthly Trends Chart
        const trendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Approved',
                    data: <?php echo json_encode($approved); ?>,
                    borderColor: '#28a745',
                    fill: false
                }, {
                    label: 'Rejected',
                    data: <?php echo json_encode($rejected); ?>,
                    borderColor: '#dc3545',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html> 