<?php
session_start();
require_once "config/database.php";

// Check if user is logged in and is a super admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "super_admin") {
    header("location: index.php");
    exit;
}

$message = "";
$error = "";

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["action"])) {
        switch ($_POST["action"]) {
            case "add":
                $username = trim($_POST["username"]);
                $password = trim($_POST["password"]);
                $email = trim($_POST["email"]);
                $full_name = trim($_POST["full_name"]);
                $role = $_POST["role"];

                // Validate input
                if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
                    $error = "All fields are required.";
                } else {
                    // Check if username or email already exists
                    $check_sql = "SELECT id FROM admin_users WHERE username = ? OR email = ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
                    mysqli_stmt_execute($check_stmt);
                    mysqli_stmt_store_result($check_stmt);

                    if (mysqli_stmt_num_rows($check_stmt) > 0) {
                        $error = "Username or email already exists.";
                    } else {
                        // Hash password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert new admin
                        $sql = "INSERT INTO admin_users (username, password, email, full_name, role, created_by) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "sssssi", $username, $hashed_password, $email, $full_name, $role, $_SESSION["id"]);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Admin user added successfully.";
                        } else {
                            $error = "Error adding admin user: " . mysqli_error($conn);
                        }
                    }
                }
                break;

            case "update":
                $id = $_POST["id"];
                $email = trim($_POST["email"]);
                $full_name = trim($_POST["full_name"]);
                $role = $_POST["role"];
                $status = $_POST["status"];

                if (empty($email) || empty($full_name)) {
                    $error = "Email and full name are required.";
                } else {
                    $sql = "UPDATE admin_users SET email = ?, full_name = ?, role = ?, status = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssssi", $email, $full_name, $role, $status, $id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Admin user updated successfully.";
                    } else {
                        $error = "Error updating admin user: " . mysqli_error($conn);
                    }
                }
                break;

            case "deactivate":
                $id = $_POST["id"];
                $sql = "UPDATE admin_users SET status = 'inactive' WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Admin user deactivated successfully.";
                } else {
                    $error = "Error deactivating admin user: " . mysqli_error($conn);
                }
                break;
        }
    }
}

// Get all admin users
$sql = "SELECT a.*, creator.username as created_by_username 
        FROM admin_users a 
        LEFT JOIN admin_users creator ON a.created_by = creator.id 
        ORDER BY a.created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Management - SIMS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .sidebar {
            height: 100vh;
            background-color: #343a40;
            padding-top: 20px;
            position: sticky;
            top: 0;
            overflow-y: auto;
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
                    <a href="admin_management.php"><i class="fas fa-users-cog"></i> Admin Management</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Admin Management</h2>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAdminModal">
                        <i class="fas fa-plus"></i> Add New Admin
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Admin Users Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($row['role'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $row['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['created_by_username'] ?? 'System'); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        data-toggle="modal" 
                                                        data-target="#editAdminModal<?php echo $row['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($row['status'] === 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('Are you sure you want to deactivate this admin?')">
                                                            <i class="fas fa-user-slash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- Edit Admin Modal -->
                                        <div class="modal fade" id="editAdminModal<?php echo $row['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Admin User</h5>
                                                        <button type="button" class="close" data-dismiss="modal">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update">
                                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                            
                                                            <div class="form-group">
                                                                <label>Username</label>
                                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['username']); ?>" readonly>
                                                            </div>
                                                            
                                                            <div class="form-group">
                                                                <label>Email</label>
                                                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($row['email']); ?>" required>
                                                            </div>
                                                            
                                                            <div class="form-group">
                                                                <label>Full Name</label>
                                                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($row['full_name']); ?>" required>
                                                            </div>
                                                            
                                                            <div class="form-group">
                                                                <label>Role</label>
                                                                <select class="form-control" name="role">
                                                                    <option value="admin" <?php echo $row['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                                    <option value="super_admin" <?php echo $row['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="form-group">
                                                                <label>Status</label>
                                                                <select class="form-control" name="status">
                                                                    <option value="active" <?php echo $row['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                                    <option value="inactive" <?php echo $row['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Admin</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Role</label>
                            <select class="form-control" name="role">
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 