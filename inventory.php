<?php
session_start();
require_once "config/database.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Initialize variables
$item_name = $quantity = $unit = $category_id = "";
$item_name_err = $quantity_err = $unit_err = $category_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(isset($_POST["action"])) {
        if($_POST["action"] == "add") {
            // Validate item name
            if(empty(trim($_POST["item_name"]))){
                $item_name_err = "Please enter item name.";
            } else{
                $item_name = trim($_POST["item_name"]);
            }
            
            // Validate quantity
            if(empty(trim($_POST["quantity"]))){
                $quantity_err = "Please enter quantity.";
            } else{
                $quantity = trim($_POST["quantity"]);
            }
            
            // Validate unit
            if(empty(trim($_POST["unit"]))){
                $unit_err = "Please enter unit.";
            } else{
                $unit = trim($_POST["unit"]);
            }
            
            // Validate category
            if(empty(trim($_POST["category_id"]))){
                $category_err = "Please select a category.";
            } else{
                $category_id = trim($_POST["category_id"]);
            }
            
            // Check input errors before inserting in database
            if(empty($item_name_err) && empty($quantity_err) && empty($unit_err) && empty($category_err)){
                $sql = "INSERT INTO inventory (item_name, quantity, unit, category_id) VALUES (?, ?, ?, ?)";
                
                if($stmt = mysqli_prepare($conn, $sql)){
                    mysqli_stmt_bind_param($stmt, "sisi", $param_item_name, $param_quantity, $param_unit, $param_category_id);
                    
                    $param_item_name = $item_name;
                    $param_quantity = $quantity;
                    $param_unit = $unit;
                    $param_category_id = $category_id;
                    
                    if(mysqli_stmt_execute($stmt)){
                        header("location: inventory.php");
                    } else{
                        echo "Something went wrong. Please try again later.";
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// Fetch all categories for the dropdown
$categories_sql = "SELECT * FROM categories ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_sql);

// Fetch all inventory items with category names
$sql = "SELECT i.*, c.name as category_name 
        FROM inventory i 
        LEFT JOIN categories c ON i.category_id = c.id 
        ORDER BY i.item_name ASC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management - SIMS</title>
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
                    <h2>Inventory Management</h2>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addItemModal">
                        <i class="fas fa-plus"></i> Add New Item
                    </button>
                </div>

                <!-- Inventory Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Category</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if(mysqli_num_rows($result) > 0){
                                        while($row = mysqli_fetch_assoc($result)){
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['quantity']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['unit']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['category_name']) . "</td>";
                                            echo "<td>
                                                    <button class='btn btn-sm btn-info' onclick='editItem(" . $row['id'] . ")'><i class='fas fa-edit'></i></button>
                                                    <button class='btn btn-sm btn-danger' onclick='deleteItem(" . $row['id'] . ")'><i class='fas fa-trash'></i></button>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' class='text-center'>No items found</td></tr>";
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

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addItemModalLabel">Add New Item</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Item Name</label>
                            <input type="text" name="item_name" class="form-control <?php echo (!empty($item_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $item_name; ?>">
                            <span class="invalid-feedback"><?php echo $item_name_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="quantity" class="form-control <?php echo (!empty($quantity_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $quantity; ?>">
                            <span class="invalid-feedback"><?php echo $quantity_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Unit</label>
                            <input type="text" name="unit" class="form-control <?php echo (!empty($unit_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $unit; ?>">
                            <span class="invalid-feedback"><?php echo $unit_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" class="form-control <?php echo (!empty($category_err)) ? 'is-invalid' : ''; ?>">
                                <option value="">Select a category</option>
                                <?php
                                if(mysqli_num_rows($categories_result) > 0){
                                    while($category = mysqli_fetch_assoc($categories_result)){
                                        $selected = ($category['id'] == $category_id) ? 'selected' : '';
                                        echo "<option value='" . $category['id'] . "' " . $selected . ">" . htmlspecialchars($category['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                            <span class="invalid-feedback"><?php echo $category_err; ?></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function editItem(id) {
            // Implement edit functionality
            alert('Edit item with ID: ' + id);
        }

        function deleteItem(id) {
            if(confirm('Are you sure you want to delete this item?')) {
                // Implement delete functionality
                alert('Delete item with ID: ' + id);
            }
        }
    </script>
</body>
</html> 