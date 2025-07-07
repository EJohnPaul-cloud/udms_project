<?php
session_start();
require_once 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if document ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $document_id = intval($_GET['id']); // Ensure it's an integer

    // Prepare to fetch document details from the database
    $sql = "SELECT file_path, file_name, file_type FROM documents WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("Download error: Failed to prepare statement - " . $conn->error);
        header("Location: dashboard.php?upload_status=error&message=Database error (prepare).");
        exit();
    }

    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $document = $result->fetch_assoc();
        $file_path = $document['file_path'];
        $file_name = $document['file_name']; // Original file name for download
        $file_type = $document['file_type'];

        // Construct absolute path to the file
        $full_file_path = __DIR__ . DIRECTORY_SEPARATOR . $file_path; // __DIR__ is the current directory of this script

        // Check if the file actually exists on the server
        if (file_exists($full_file_path)) {
            // Set headers to force download
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $file_type);
            header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($full_file_path));
            flush(); // Flush system output buffer
            readfile($full_file_path); // Read the file and send it to the output buffer
            exit();
        } else {
            header("Location: dashboard.php?upload_status=error&message=File not found on server.");
            exit();
        }
    } else {
        header("Location: dashboard.php?upload_status=error&message=Document not found in database.");
        exit();
    }

    $stmt->close();
    $conn->close();

} else {
    header("Location: dashboard.php?upload_status=error&message=No document ID specified for download.");
    exit();
}
?>