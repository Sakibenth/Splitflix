<?php
// ============================================================
// Splitflix - Database Configuration Template
// ============================================================
// SETUP INSTRUCTIONS:
// 1. Copy this file: cp config/database.example.php config/database.php
// 2. Fill in your local database credentials below
// 3. Never commit database.php to Git (it's in .gitignore)
// ============================================================

// Database Configuration
define('DB_HOST', 'localhost');   // Usually 'localhost' for XAMPP
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password (empty for XAMPP default)
define('DB_NAME', 'splitflix');   // The database name

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4
mysqli_set_charset($conn, "utf8mb4");
?>
