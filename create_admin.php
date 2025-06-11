<?php
require_once "config/database.php";

// Check if admin user already exists
$check_sql = "SELECT id FROM users WHERE username = 'admin'";
$result = mysqli_query($conn, $check_sql);

if(mysqli_num_rows($result) == 0) {
    // Create admin user
    $username = "store";
    $password = "store";
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
    
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "ss", $username, $hashed_password);
        
        if(mysqli_stmt_execute($stmt)){
            echo "Admin user created successfully!<br>";
            echo "Username: store<br>";
            echo "Password: store<br>";
            echo "<a href='index.php'>Go to login page</a>";
        } else{
            echo "Error creating admin user: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    }
} else {
    echo "Admin user already exists.<br>";
    echo "<a href='index.php'>Go to login page</a>";
}

mysqli_close($conn);
?>