<?php
session_start();
require_once 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['document_file'])) {
    $user_id = $_SESSION['user_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);

    // --- File Upload Handling ---
    $target_dir = "uploads/"; // Directory where files will be stored
    // Create 'uploads' directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true); // 0777 grants full permissions, consider more restrictive in production
    }

    $original_file_name = basename($_FILES["document_file"]["name"]);
    $file_type = $_FILES["document_file"]["type"];
    $file_size = $_FILES["document_file"]["size"];
    $tmp_name = $_FILES["document_file"]["tmp_name"];
    $error_code = $_FILES["document_file"]["error"];

    // Generate a unique file name to prevent overwrites and security issues
    $new_file_name = uniqid() . "-" . time() . "." . pathinfo($original_file_name, PATHINFO_EXTENSION);
    $target_file_path = $target_dir . $new_file_name;

    // Basic validation
    if ($error_code !== UPLOAD_ERR_OK) {
        header("Location: dashboard.php?upload_status=error&message=Upload error code: " . $error_code);
        exit();
    }
    if ($file_size > 50000000) { // Max 50MB (adjust as needed)
        header("Location: dashboard.php?upload_status=error&message=File is too large.");
        exit();
    }
    // You might want to add more file type checks here (e.g., only PDF, DOCX etc.)
    // For example: $allowed_types = ['application/pdf', 'application/msword'];
    // if (!in_array($file_type, $allowed_types)) { ... }


    if (move_uploaded_file($tmp_name, $target_file_path)) {
        // File successfully moved, now save metadata to database
        $sql = "INSERT INTO documents (user_id, title, description, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            // Log error for debugging, don't expose to user
            error_log("Failed to prepare document insert statement: " . $conn->error);
            header("Location: dashboard.php?upload_status=error&message=Database error during upload.");
            exit();
        }

        // 'isssssi' -> i=integer, s=string, s=string, s=string, s=string, s=string, i=integer
        $stmt->bind_param("isssssi", $user_id, $title, $description, $original_file_name, $target_file_path, $file_type, $file_size);

        if ($stmt->execute()) {
            header("Location: dashboard.php?upload_status=success");
            exit();
        } else {
            // If DB insert fails, consider deleting the uploaded file
            unlink($target_file_path);
            error_log("Failed to insert document metadata: " . $stmt->error);
            header("Location: dashboard.php?upload_status=error&message=Failed to save document info to database.");
            exit();
        }

        $stmt->close();
    } else {
        header("Location: dashboard.php?upload_status=error&message=Failed to move uploaded file.");
        exit();
    }

    $conn->close();

} else {
    header("Location: dashboard.php");
    exit();
}
?>