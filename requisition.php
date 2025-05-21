<?php
session_start();
require_once "config/database.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Initialize variables
$item_id = $quantity = $purpose = "";
$item_id_err = $quantity_err = $purpose_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(isset($_POST["action"])) {
        if($_POST["action"] == "add") {
            // Validate item
            if(empty(trim($_POST["item_id"]))){
                $item_id_err = "Please select an item.";
            } else{
                $item_id = trim($_POST["item_id"]);
            }
            
            // Validate quantity
            if(empty(trim($_POST["quantity"]))){
                $quantity_err = "Please enter quantity.";
            } else{
                $quantity = trim($_POST["quantity"]);
            }
            
            // Validate purpose
            if(empty(trim($_POST["purpose"]))){
                $purpose_err = "Please enter purpose.";
            } else{
                $purpose = trim($_POST["purpose"]);
            }
            
            // Check input errors before inserting in database
            if(empty($item_id_err) && empty($quantity_err) && empty($purpose_err)){
                try {
                    // First check if the item has enough quantity
                    $check_sql = "SELECT quantity FROM inventory WHERE id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "i", $item_id);
                    mysqli_stmt_execute($check_stmt);
                    mysqli_stmt_bind_result($check_stmt, $available_quantity);
                    mysqli_stmt_fetch($check_stmt);
                    mysqli_stmt_close($check_stmt);

                    if($quantity > $available_quantity){
                        $quantity_err = "Requested quantity exceeds available stock.";
                    } else {
                        $sql = "INSERT INTO requisitions (item_id, quantity, purpose, status, requested_by) VALUES (?, ?, ?, 'pending', ?)";
                        
                        if($stmt = mysqli_prepare($conn, $sql)){
                            mysqli_stmt_bind_param($stmt, "iisi", $param_item_id, $param_quantity, $param_purpose, $param_user_id);
                            
                            $param_item_id = $item_id;
                            $param_quantity = $quantity;
                            $param_purpose = $purpose;
                            $param_user_id = $_SESSION["id"];
                            
                            if(mysqli_stmt_execute($stmt)){
                                header("location: requisition.php");
                                exit();
                            } else{
                                throw new Exception("Error executing statement: " . mysqli_error($conn));
                            }

                            mysqli_stmt_close($stmt);
                        } else {
                            throw new Exception("Error preparing statement: " . mysqli_error($conn));
                        }
                    }
                } catch (Exception $e) {
                    echo "Error: " . $e->getMessage();
                }
            }
        } elseif($_POST["action"] == "update_status") {
            $requisition_id = $_POST["requisition_id"];
            $new_status = $_POST["status"];
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Update requisition status
                $update_sql = "UPDATE requisitions SET status = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $new_status, $requisition_id);
                mysqli_stmt_execute($update_stmt);
                
                // If approved, update inventory quantity
                if($new_status == "approved") {
                    // Get requisition details
                    $req_sql = "SELECT item_id, quantity FROM requisitions WHERE id = ?";
                    $req_stmt = mysqli_prepare($conn, $req_sql);
                    mysqli_stmt_bind_param($req_stmt, "i", $requisition_id);
                    mysqli_stmt_execute($req_stmt);
                    mysqli_stmt_bind_result($req_stmt, $req_item_id, $req_quantity);
                    mysqli_stmt_fetch($req_stmt);
                    mysqli_stmt_close($req_stmt);
                    
                    // Update inventory
                    $inv_sql = "UPDATE inventory SET quantity = quantity - ? WHERE id = ?";
                    $inv_stmt = mysqli_prepare($conn, $inv_sql);
                    mysqli_stmt_bind_param($inv_stmt, "ii", $req_quantity, $req_item_id);
                    mysqli_stmt_execute($inv_stmt);
                }
                
                mysqli_commit($conn);
                header("location: requisition.php");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo "Error updating requisition: " . $e->getMessage();
            }
        }
    }
}

// Fetch all inventory items for dropdown
$sql_items = "SELECT id, item_name, quantity, unit FROM inventory ORDER BY item_name ASC";
$items_result = mysqli_query($conn, $sql_items);

// Fetch all requisitions
$sql_requisitions = "SELECT r.*, i.item_name, i.unit, u.username 
                    FROM requisitions r 
                    JOIN inventory i ON r.item_id = i.id 
                    JOIN users u ON r.requested_by = u.id 
                    ORDER BY r.created_at DESC";
$requisitions_result = mysqli_query($conn, $sql_requisitions);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Requisitions - SIMS</title>
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
                    <h2>Requisitions</h2>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRequisitionModal">
                        <i class="fas fa-plus"></i> New Requisition
                    </button>
                </div>

                <!-- Requisitions Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Purpose</th>
                                        <th>Requested By</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if(mysqli_num_rows($requisitions_result) > 0){
                                        while($row = mysqli_fetch_assoc($requisitions_result)){
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
                                            echo "<td>" . htmlspecialchars($row['quantity']) . " " . htmlspecialchars($row['unit']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['purpose']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                            echo "<td><span class='badge badge-" . $status_class . "'>" . ucfirst(htmlspecialchars($row['status'])) . "</span></td>";
                                            echo "<td>" . date('M d, Y', strtotime($row['created_at'])) . "</td>";
                                            echo "<td>";
                                            if($row['status'] == 'pending') {
                                                echo "<button class='btn btn-sm btn-success' onclick='updateStatus(" . $row['id'] . ", \"approved\")'><i class='fas fa-check'></i></button> ";
                                                echo "<button class='btn btn-sm btn-danger' onclick='updateStatus(" . $row['id'] . ", \"rejected\")'><i class='fas fa-times'></i></button>";
                                            }
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='text-center'>No requisitions found</td></tr>";
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

    <!-- Add Requisition Modal -->
    <div class="modal fade" id="addRequisitionModal" tabindex="-1" role="dialog" aria-labelledby="addRequisitionModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRequisitionModalLabel">New Requisition</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Item</label>
                            <select name="item_id" class="form-control <?php echo (!empty($item_id_err)) ? 'is-invalid' : ''; ?>">
                                <option value="">Select Item</option>
                                <?php
                                while($item = mysqli_fetch_assoc($items_result)) {
                                    echo "<option value='" . $item['id'] . "'>" . htmlspecialchars($item['item_name']) . " (Available: " . $item['quantity'] . " " . $item['unit'] . ")</option>";
                                }
                                ?>
                            </select>
                            <span class="invalid-feedback"><?php echo $item_id_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="quantity" class="form-control <?php echo (!empty($quantity_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $quantity; ?>">
                            <span class="invalid-feedback"><?php echo $quantity_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Purpose</label>
                            <textarea name="purpose" class="form-control <?php echo (!empty($purpose_err)) ? 'is-invalid' : ''; ?>" rows="3"><?php echo $purpose; ?></textarea>
                            <span class="invalid-feedback"><?php echo $purpose_err; ?></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Submit Requisition</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function updateStatus(id, status) {
            if(confirm('Are you sure you want to ' + status + ' this requisition?')) {
                // Create a form and submit it
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>';
                
                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'update_status';
                form.appendChild(actionInput);
                
                var idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'requisition_id';
                idInput.value = id;
                form.appendChild(idInput);
                
                var statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = status;
                form.appendChild(statusInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 