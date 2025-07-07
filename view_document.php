<?php
session_start();
require_once 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $document_id = intval($_GET['id']);

    $sql = "SELECT file_path, file_name, file_type FROM documents WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("View error: Failed to prepare statement - " . $conn->error);
        header("Location: dashboard.php?upload_status=error&message=Database error (view).");
        exit();
    }

    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $document = $result->fetch_assoc();
        $file_path = $document['file_path'];
        $file_name = $document['file_name'];
        $file_type = $document['file_type'];

        $full_file_path = __DIR__ . DIRECTORY_SEPARATOR . $file_path;

        if (file_exists($full_file_path)) {
            // Set headers to display file in browser, or download if browser can't display
            header('Content-Type: ' . $file_type);
            // Use 'inline' to try and display in browser, 'attachment' to force download
            header('Content-Disposition: inline; filename="' . basename($file_name) . '"');
            header('Content-Length: ' . filesize($full_file_path));
            header('Accept-Ranges: bytes');
            @readfile($full_file_path); // Use @ to suppress errors if connection is lost
            exit();
        } else {
            header("Location: dashboard.php?upload_status=error&message=File not found on server for viewing.");
            exit();
        }
    } else {
        header("Location: dashboard.php?upload_status=error&message=Document not found in database for viewing.");
        exit();
    }

    $stmt->close();
    $conn->close();

} else {
    header("Location: dashboard.php?upload_status=error&message=No document ID specified for viewing.");
    exit();
}
?>