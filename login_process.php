<?php
session_start();
require_once 'db_connect.php'; // Ensure your database connection file is included

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_email = trim($_POST['username_email']);
    $password = $_POST['password'];

    if (empty($username_email) || empty($password)) {
        header("Location: login.php?status=error&message=" . urlencode("Please enter both username/email and password."));
        exit();
    }

    // Prepare a statement to fetch user by username or email
    // IMPORTANT: Make sure 'profile_image' column is included in the SELECT query
    $stmt = $conn->prepare("SELECT id, username, full_name, email, password, role, profile_image FROM users WHERE username = ? OR email = ?");

    if ($stmt) {
        $stmt->bind_param("ss", $username_email, $username_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify the password
            if (password_verify($password, $user['password'])) {
                // Password is correct, set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email']; // Also good to store email in session

                // Store profile_image path in session
                $_SESSION['profile_image'] = $user['profile_image'];

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                // Incorrect password
                header("Location: login.php?status=error&message=" . urlencode("Invalid username/email or password."));
                exit();
            }
        } else {
            // User not found
            header("Location: login.php?status=error&message=" . urlencode("Invalid username/email or password."));
            exit();
        }
        $stmt->close();
    } else {
        // Error preparing the statement
        error_log("Login_process: Failed to prepare statement - " . $conn->error);
        header("Location: login.php?status=error&message=" . urlencode("A database error occurred. Please try again later."));
        exit();
    }

    $conn->close();
} else {
    // If accessed directly without POST request, redirect to login page
    header("Location: login.php");
    exit();
}
?>