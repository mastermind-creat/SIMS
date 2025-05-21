<?php
session_start();
require_once "config/database.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Get total items count
$total_items_sql = "SELECT COUNT(*) as total FROM inventory";
$total_items_result = mysqli_query($conn, $total_items_sql);
$total_items = mysqli_fetch_assoc($total_items_result)['total'];

// Get pending requisitions count
$pending_req_sql = "SELECT COUNT(*) as total FROM requisitions WHERE status = 'pending'";
$pending_req_result = mysqli_query($conn, $pending_req_sql);
$pending_requisitions = mysqli_fetch_assoc($pending_req_result)['total'];

// Get low stock items (items with quantity less than 10)
$low_stock_sql = "SELECT COUNT(*) as total FROM inventory WHERE quantity < 10";
$low_stock_result = mysqli_query($conn, $low_stock_sql);
$low_stock_items = mysqli_fetch_assoc($low_stock_result)['total'];

// Get recent activities (last 5 requisitions)
$recent_activities_sql = "SELECT r.*, i.item_name, u.username 
                         FROM requisitions r 
                         JOIN inventory i ON r.item_id = i.id 
                         JOIN users u ON r.requested_by = u.id 
                         ORDER BY r.created_at DESC 
                         LIMIT 5";
$recent_activities_result = mysqli_query($conn, $recent_activities_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Store Inventory Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-approved {
            background-color: #28a745;
            color: #fff;
        }
        .status-rejected {
            background-color: #dc3545;
            color: #fff;
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
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?></h2>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Items</h5>
                                <h2 class="card-text"><?php echo $total_items; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Pending Requisitions</h5>
                                <h2 class="card-text"><?php echo $pending_requisitions; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock Items</h5>
                                <h2 class="card-text"><?php echo $low_stock_items; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <?php if(mysqli_num_rows($recent_activities_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Quantity</th>
                                                    <th>Requested By</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($activity = mysqli_fetch_assoc($recent_activities_result)): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($activity['item_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($activity['quantity']); ?></td>
                                                        <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                                        <td>
                                                            <span class="badge status-badge status-<?php echo strtolower($activity['status']); ?>">
                                                                <?php echo ucfirst($activity['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No recent activities to display.</p>
                                <?php endif; ?>
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
</body>
</html> 