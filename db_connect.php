<?php
// Database connection parameters
$servername = "localhost"; // Usually 'localhost' when using XAMPP
$username = "root";        // Default XAMPP MySQL username
$password = "";            // Default XAMPP MySQL password (empty, or whatever you set if you changed it)
$dbname = "udms_db";       // The name of the database you created

// Create database connection using MySQLi (Improved MySQL extension)
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // If connection fails, stop script execution and show error
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set character set to UTF-8 for proper handling of special characters
// This aligns with your utf8mb4_unicode_ci collation
$conn->set_charset("utf8mb4");

// This file will now provide the $conn variable for database interactions
// You typically don't close the connection here; it's closed automatically at the end of the script,
// or explicitly in register_process.php after its operations.
?>