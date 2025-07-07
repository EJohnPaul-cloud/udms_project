<?php
// Include the database connection file
require_once 'db_connect.php';

// Set timezone (good practice)
date_default_timezone_set('Africa/Lagos'); // Adjusted based on current time context

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize it (prevent SQL injection and XSS)
    // mysqli_real_escape_string is crucial for security
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password']; // Get raw password for hashing
    $role = $conn->real_escape_string($_POST['role']);

    // --- Basic Validation ---
    if (empty($full_name) || empty($username) || empty($password) || empty($role)) {
        header("Location: register.php?status=error&message=All required fields must be filled.");
        exit();
    }

    // --- Check if username or email already exists ---
    $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $stmt_check = $conn->prepare($check_sql);
    if ($stmt_check === false) {
        die("Error preparing statement for existence check: " . $conn->error);
    }
    $stmt_check->bind_param("ss", $username, $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        header("Location: register.php?status=exists");
        $stmt_check->close();
        $conn->close();
        exit();
    }
    $stmt_check->close();


    // Hash the password securely (VERY IMPORTANT!)
    // PASSWORD_DEFAULT is the best current algorithm (bcrypt)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Prepare an SQL INSERT statement using prepared statements for security
    // IMPORTANT: Removed 'created_at' from the field list and VALUES part, as per your table structure
    $sql = "INSERT INTO users (full_name, username, password, email, role) VALUES (?, ?, ?, ?, ?)";

    // Prepare the statement
    $stmt = $conn->prepare($sql);

    // Check if the statement preparation was successful
    if ($stmt === false) {
        die("Error preparing statement for insertion: " . $conn->error);
    }

    // Bind parameters to the statement
    // 'sssss' means: s=string for full_name, username, hashed_password, email, role (5 parameters)
    $stmt->bind_param("sssss", $full_name, $username, $hashed_password, $email, $role);

    // Execute the statement
    if ($stmt->execute()) {
        // Registration successful
        header("Location: register.php?status=success");
        exit();
    } else {
        // Registration failed
        error_log("User registration failed: " . $stmt->error); // Log the error for debugging
        header("Location: register.php?status=error");
        exit();
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();

} else {
    // If someone tries to access this script directly without POST method
    header("Location: register.php");
    exit();
}
?>