<?php
require_once "config/database.php";

// Create admin_users table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS admin_users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id)
)";

if (mysqli_query($conn, $sql)) {
    echo "Admin users table created successfully<br>";
} else {
    echo "Error creating table: " . mysqli_error($conn) . "<br>";
}

// Create super admin user
$username = 'superadmin';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$email = 'admin@example.com';
$full_name = 'Super Admin';
$role = 'super_admin';
$status = 'active';

// Check if super admin already exists
$check_sql = "SELECT id FROM admin_users WHERE username = ? OR email = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) == 0) {
    // Insert super admin
    $sql = "INSERT INTO admin_users (username, password, email, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssss", $username, $password, $email, $full_name, $role, $status);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "Super admin user created successfully<br>";
        echo "Username: superadmin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Error creating super admin: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "Super admin user already exists<br>";
}

mysqli_close($conn);
?> 