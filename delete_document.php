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
    $document_id = intval($_GET['id']);
    $current_user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    // First, retrieve file path to delete the physical file
    // Also, check if the current user is authorized to delete (either uploader or admin)
    $sql_select = "SELECT file_path, user_id FROM documents WHERE id = ?";
    $stmt_select = $conn->prepare($sql_select);

    if ($stmt_select === false) {
        error_log("Delete error: Failed to prepare select statement - " . $conn->error);
        header("Location: dashboard.php?upload_status=error&message=Database error (select for delete).");
        exit();
    }

    $stmt_select->bind_param("i", $document_id);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();

    if ($result_select->num_rows === 1) {
        $document = $result_select->fetch_assoc();
        $file_path_on_server = $document['file_path'];
        $document_uploader_id = $document['user_id'];

        // Authorization check: Only uploader or admin can delete
        if ($current_user_id == $document_uploader_id || $user_role == 'admin') {
            // Delete the record from the database first
            $sql_delete_db = "DELETE FROM documents WHERE id = ?";
            $stmt_delete_db = $conn->prepare($sql_delete_db);

            if ($stmt_delete_db === false) {
                error_log("Delete error: Failed to prepare delete statement - " . $conn->error);
                header("Location: dashboard.php?upload_status=error&message=Database error (delete prepare).");
                exit();
            }

            $stmt_delete_db->bind_param("i", $document_id);

            if ($stmt_delete_db->execute()) {
                // Database record deleted successfully, now delete the physical file
                $full_file_path = __DIR__ . DIRECTORY_SEPARATOR . $file_path_on_server;
                if (file_exists($full_file_path)) {
                    if (unlink($full_file_path)) {
                        header("Location: dashboard.php?upload_status=success&message=Document deleted successfully.");
                        exit();
                    } else {
                        // Database record deleted, but file delete failed (log this!)
                        error_log("Failed to delete physical file: " . $full_file_path);
                        header("Location: dashboard.php?upload_status=error&message=Document record deleted, but file remains on server.");
                        exit();
                    }
                } else {
                    // Database record deleted, but file wasn't found (perhaps already deleted or path wrong)
                    header("Location: dashboard.php?upload_status=success&message=Document record deleted. Physical file not found/already gone.");
                    exit();
                }
            } else {
                error_log("Failed to delete document from database: " . $stmt_delete_db->error);
                header("Location: dashboard.php?upload_status=error&message=Failed to delete document from database.");
                exit();
            }

            $stmt_delete_db->close();
        } else {
            header("Location: dashboard.php?upload_status=error&message=You are not authorized to delete this document.");
            exit();
        }
    } else {
        header("Location: dashboard.php?upload_status=error&message=Document not found for deletion.");
        exit();
    }

    $stmt_select->close();
    $conn->close();

} else {
    header("Location: dashboard.php?upload_status=error&message=No document ID specified for deletion.");
    exit();
}
?>