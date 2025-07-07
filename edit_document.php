<?php
session_start();
require_once 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$document_id = null;
$document = null; // Will hold document data fetched from DB
$error_message = "";
$success_message = "";
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// --- Handle form submission (POST request for updating) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['document_id'])) {
    $document_id = intval($_POST['document_id']);
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);

    // Fetch original document details for authorization check AND old file path if new file is uploaded
    $sql_check = "SELECT user_id, file_path FROM documents WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $document_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $error_message = "Document not found for editing.";
    } else {
        $original_doc = $result_check->fetch_assoc();
        // Authorization check: Only uploader or admin can edit
        if ($current_user_id == $original_doc['user_id'] || $user_role == 'admin') {
            $old_file_path = $original_doc['file_path']; // Store old file path for potential deletion
            
            $update_sql = "UPDATE documents SET title = ?, description = ? WHERE id = ?";
            $bind_params = "ssi";
            $bind_values = [&$title, &$description, &$document_id];

            // --- Handle NEW File Upload (if any) ---
            if (isset($_FILES['new_document_file']) && $_FILES['new_document_file']['error'] === UPLOAD_ERR_OK) {
                $target_dir = "uploads/";
                // Ensure the uploads directory exists
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $new_original_file_name = basename($_FILES["new_document_file"]["name"]);
                $new_file_type = $_FILES["new_document_file"]["type"];
                $new_file_size = $_FILES["new_document_file"]["size"];
                $new_tmp_name = $_FILES["new_document_file"]["tmp_name"];

                // Generate a unique file name for the new file
                $new_stored_file_name = uniqid() . "-" . time() . "." . pathinfo($new_original_file_name, PATHINFO_EXTENSION);
                $new_target_file_path = $target_dir . $new_stored_file_name;

                // Basic validation for the new file
                if ($new_file_size > 50000000) { // Max 50MB
                    $error_message = "New file is too large (max 50MB).";
                }
                // Add more file type checks if necessary, e.g., if (!in_array($new_file_type, $allowed_types)) {...}

                if (empty($error_message) && move_uploaded_file($new_tmp_name, $new_target_file_path)) {
                    // New file uploaded successfully, update database query to include file details
                    $update_sql = "UPDATE documents SET title = ?, description = ?, file_name = ?, file_path = ?, file_type = ?, file_size = ? WHERE id = ?";
                    $bind_params = "sssssii";
                    // Need to re-order bind_values for the new SQL
                    $bind_values = [&$title, &$description, &$new_original_file_name, &$new_target_file_path, &$new_file_type, &$new_file_size, &$document_id];

                    // Attempt to delete the old physical file IF a new one was successfully uploaded
                    $full_old_file_path = __DIR__ . DIRECTORY_SEPARATOR . $old_file_path;
                    if (file_exists($full_old_file_path) && is_file($full_old_file_path)) {
                        if (!unlink($full_old_file_path)) {
                            // Log error but don't stop the process if the new file is uploaded
                            error_log("Failed to delete old file: " . $full_old_file_path);
                        }
                    }
                } else if (empty($error_message)) { // Only if no size error was set
                    $error_message = "Failed to upload new file. Check file size/permissions.";
                }
            } elseif (isset($_FILES['new_document_file']) && $_FILES['new_document_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                // An error occurred with file upload, but it wasn't UPLOAD_ERR_NO_FILE (meaning a file was selected but had issues)
                $error_message = "File upload error: " . $_FILES['new_document_file']['error'];
            }

            // Proceed with database update if no file upload error
            if (empty($error_message)) {
                $stmt_update = $conn->prepare($update_sql);

                if ($stmt_update === false) {
                    error_log("Edit error: Failed to prepare update statement - " . $conn->error);
                    $error_message = "Database error during update (prepare).";
                } else {
                    // Use call_user_func_array for dynamic bind_param due to variable number of arguments
                    call_user_func_array([$stmt_update, 'bind_param'], array_merge([$bind_params], $bind_values));
                    
                    if ($stmt_update->execute()) {
                        $success_message = "Document updated successfully!";
                        // If a new file was uploaded, append specific message
                        if (isset($new_target_file_path)) {
                            $success_message .= " File replaced successfully.";
                        }
                    } else {
                        $error_message = "Failed to update document: " . $stmt_update->error;
                        error_log("Failed to update document: " . $stmt_update->error);
                    }
                    $stmt_update->close();
                }
            }
        } else {
            $error_message = "You are not authorized to edit this document.";
        }
    }
    $stmt_check->close();

    // After attempting update, re-fetch the document details to display the latest data
    $sql_fetch_latest = "SELECT * FROM documents WHERE id = ?";
    $stmt_fetch_latest = $conn->prepare($sql_fetch_latest);
    $stmt_fetch_latest->bind_param("i", $document_id);
    $stmt_fetch_latest->execute();
    $document = $stmt_fetch_latest->get_result()->fetch_assoc();
    $stmt_fetch_latest->close();

} else if (isset($_GET['id']) && !empty($_GET['id'])) {
    // --- Handle GET request (for displaying the form initially) ---
    $document_id = intval($_GET['id']);

    $sql = "SELECT * FROM documents WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("Edit error: Failed to prepare select statement - " . $conn->error);
        $error_message = "Database error (select for edit).";
    } else {
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $document = $result->fetch_assoc();
            // Authorization check: Only uploader or admin can view/edit this page
            if ($current_user_id != $document['user_id'] && $user_role != 'admin') {
                header("Location: dashboard.php?upload_status=error&message=You are not authorized to view/edit this document.");
                exit();
            }
        } else {
            $error_message = "Document not found.";
        }
        $stmt->close();
    }
} else {
    $error_message = "No document ID specified for editing.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMS - Edit Document</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-hover: #45a049;
            --secondary-color: #007bff;
            --secondary-hover: #0056b3;
            --danger-color: #dc3545;
            --danger-hover: #c82333;
            --text-color: #333;
            --light-bg: #f8f9fa;
            --white-bg: #ffffff;
            --border-color: #e0e0e0;
            --shadow-color: rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            margin: 0;
            padding: 20px;
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .edit-container {
            background-color: var(--white-bg);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px var(--shadow-color);
            max-width: 600px;
            width: 100%;
            border: 1px solid var(--border-color);
        }
        h2 {
            color: var(--text-color);
            margin-bottom: 25px;
            font-weight: 600;
            text-align: center;
        }
        .message {
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 400; }
        .form-group input[type="text"],
        .form-group input[type="file"], /* Added style for file input */
        .form-group textarea {
            width: calc(100% - 24px);
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="file"]:focus, /* Added style for file input */
        .form-group textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            outline: none;
        }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .current-file-info {
            font-size: 0.9em;
            color: #777;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        .btn {
            padding: 12px 25px;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            text-decoration: none;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
        .btn-save { background-color: var(--primary-color); }
        .btn-save:hover { background-color: var(--primary-hover); }
        .btn-back { background-color: #6c757d; }
        .btn-back:hover { background-color: #5a6268; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .edit-container {
                padding: 20px;
                margin: 20px auto;
            }
            .btn-group {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <h2>Edit Document</h2>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($document): ?>
            <form action="edit_document.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="document_id" value="<?php echo htmlspecialchars($document['id']); ?>">
                
                <div class="form-group">
                    <label for="title">Document Title:</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($document['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($document['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="new_document_file">Replace Document File (Optional):</label>
                    <input type="file" id="new_document_file" name="new_document_file">
                    <?php if (!empty($document['file_name'])): ?>
                        <p class="current-file-info">
                            Current file: <strong><?php echo htmlspecialchars($document['file_name']); ?></strong> 
                            (Type: <?php echo htmlspecialchars($document['file_type']); ?>, 
                            Size: <?php echo round($document['file_size'] / 1024, 2); ?> KB)
                        </p>
                    <?php endif; ?>
                    <small>Leave blank if you don't want to replace the file. Max 50MB.</small>
                </div>

                <div class="btn-group">
                    <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
                    <button type="submit" class="btn btn-save">Save Changes</button>
                </div>
            </form>
        <?php elseif (empty($error_message)): ?>
            <div class="message error">No document selected for editing or document not found.</div>
            <div class="btn-group" style="justify-content: center;">
                <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>