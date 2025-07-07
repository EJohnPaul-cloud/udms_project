<?php
session_start();
error_reporting(E_ALL); // Display all errors
ini_set('display_errors', 1); // Display errors directly on the page

echo '<pre>';
var_dump($_FILES); // This will show what PHP receives for file uploads
echo '</pre>';

require_once 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$user_data = null;

// Initialize messages from URL parameters (for redirects from remove_image action)
$error_message = isset($_GET['status']) && $_GET['status'] == 'error' && isset($_GET['message']) ? htmlspecialchars($_GET['message']) : "";
$success_message = isset($_GET['status']) && $_GET['status'] == 'success' && isset($_GET['message']) ? htmlspecialchars($_GET['message']) : "";


// --- Function to fetch current user data ---
function fetchUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id, username, full_name, email, role, profile_image, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

// --- Handle GET request for removing profile image ---
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'remove_image') {
    $user_data_current = fetchUserData($conn, $current_user_id);
    $old_profile_image_path = $user_data_current['profile_image'];

    // Update database to remove image path
    $stmt_update = $conn->prepare("UPDATE users SET profile_image = NULL WHERE id = ?");
    if ($stmt_update) {
        $stmt_update->bind_param("i", $current_user_id);
        if ($stmt_update->execute()) {
            // Delete old physical file if it exists
            if (!empty($old_profile_image_path) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $old_profile_image_path)) {
                if (unlink(__DIR__ . DIRECTORY_SEPARAtOR . $old_profile_image_path)) {
                    $success_message = "Profile image removed successfully!";
                } else {
                    $error_message = "Profile image removed from profile, but failed to delete old file from server.";
                    error_log("Failed to unlink old profile image: " . __DIR__ . DIRECTORY_SEPARATOR . $old_profile_image_path);
                }
            } else {
                $success_message = "Profile image removed successfully (no old file found or linked).";
            }
            // Update session for immediate effect on dashboard
            $_SESSION['profile_image'] = null;

        } else {
            $error_message = "Error removing profile image: " . htmlspecialchars($stmt_update->error);
            error_log("Error removing profile image from DB: " . $stmt_update->error);
        }
        $stmt_update->close();
    } else {
        $error_message = "Database error preparing remove image statement.";
    }
    // Redirect to profile page to clear GET parameters and display message
    header("Location: profile.php?message=" . urlencode($success_message ?: $error_message) . "&status=" . urlencode(empty($error_message) ? 'success' : 'error'));
    exit();
}


// --- Handle POST request (update profile) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($conn->real_escape_string($_POST['full_name']));
    $email = trim($conn->real_escape_string($_POST['email']));
    // Username is displayed but not typically updated by user, so not retrieved from POST
    
    $update_fields = [];
    $bind_params = "";
    $bind_values = [];

    // Fetch current user data from DB to compare with POSTed data
    $user_data_before_update = fetchUserData($conn, $current_user_id); 

    // Add full_name to update if changed
    if ($full_name !== $user_data_before_update['full_name']) {
        $update_fields[] = "full_name = ?";
        $bind_params .= "s";
        $bind_values[] = &$full_name;
    }
    
    // Add email to update if changed and validate
    if ($email !== $user_data_before_update['email']) {
        if (empty($email)) {
            $error_message = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } else {
            // Check for duplicate email (excluding current user)
            $stmt_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            if ($stmt_email) {
                $stmt_email->bind_param("si", $email, $current_user_id);
                $stmt_email->execute();
                $result_email = $stmt_email->get_result();
                if ($result_email->num_rows > 0) {
                    $error_message = "This email is already in use by another account.";
                }
                $stmt_email->close();
            } else {
                $error_message = "Database error checking email uniqueness.";
            }
        }
        if (empty($error_message)) { // Only add if email is valid and unique
            $update_fields[] = "email = ?";
            $bind_params .= "s";
            $bind_values[] = &$email;
        }
    }
    
    // --- Handle Password Change ---
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($error_message) && !empty($new_password)) { // User intends to change password
        if (empty($current_password)) {
            $error_message = "Current password is required to change password.";
        } elseif ($new_password !== $confirm_new_password) {
            $error_message = "New password and confirm new password do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } else {
            // Verify current password
            $stmt_check_pass = $conn->prepare("SELECT password FROM users WHERE id = ?");
            if ($stmt_check_pass) {
                $stmt_check_pass->bind_param("i", $current_user_id);
                $stmt_check_pass->execute();
                $result_check_pass = $stmt_check_pass->get_result();
                $user_pass_hash = $result_check_pass->fetch_assoc()['password'];
                $stmt_check_pass->close();

                if (password_verify($current_password, $user_pass_hash)) {
                    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_fields[] = "password = ?";
                    $bind_params .= "s";
                    $bind_values[] = &$hashed_new_password;
                    if (empty($success_message)) $success_message = ""; // Ensure it's not null if profile image message is first
                    $success_message .= " Password updated.";
                } else {
                    $error_message = "Incorrect current password.";
                }
            } else {
                $error_message = "Database error verifying current password.";
            }
        }
    }
    
    // --- Handle Profile Image Upload ---
    $new_profile_image_path = null;
    if (empty($error_message) && isset($_FILES['profile_image_upload']) && $_FILES['profile_image_upload']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "profile_uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Ensure directory exists and is writable
        }

        $image_file = $_FILES['profile_image_upload'];
        $image_file_name = basename($image_file['name']);
        $image_file_type = strtolower(pathinfo($image_file_name, PATHINFO_EXTENSION));
        $image_file_size = $image_file['size'];
        $image_tmp_name = $image_file['tmp_name'];

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($image_file_type, $allowed_types)) {
            $error_message = "Only JPG, JPEG, PNG, and GIF files are allowed for profile images.";
        } elseif ($image_file_size > $max_size) {
            $error_message = "Profile image is too large (max 2MB).";
        } else {
            // Generate unique filename for the new image
            $new_profile_image_name = uniqid('profile_') . '.' . $image_file_type;
            $new_profile_image_path = $target_dir . $new_profile_image_name;

            if (move_uploaded_file($image_tmp_name, $new_profile_image_path)) {
                // Get old image path from DB to delete it
                $old_profile_image_path = $user_data_before_update['profile_image'];

                // Add profile_image to update fields
                $update_fields[] = "profile_image = ?";
                $bind_params .= "s";
                $bind_values[] = &$new_profile_image_path;
                if (empty($success_message)) $success_message = ""; // Ensure it's not null
                $success_message .= " Profile image uploaded.";

                // Delete the old physical file IF a new one was successfully uploaded
                if (!empty($old_profile_image_path) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $old_profile_image_path)) {
                    if (!unlink(__DIR__ . DIRECTORY_SEPARATOR . $old_profile_image_path)) {
                        error_log("Failed to delete old profile image: " . __DIR__ . DIRECTORY_SEPARATOR . $old_profile_image_path);
                        // Do not set error_message for user, but log it.
                    }
                }
                // Update session for immediate effect on dashboard
                $_SESSION['profile_image'] = $new_profile_image_path;

            } else {
                $error_message = "Failed to upload profile image. Check folder permissions.";
            }
        }
    } elseif (isset($_FILES['profile_image_upload']) && $_FILES['profile_image_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
        // An error occurred other than no file selected
        $error_message = "Profile image upload error: " . $_FILES['profile_image_upload']['error'];
    }


    // --- Perform Database Update for textual fields and potentially password/image ---
    if (empty($error_message)) {
        if (!empty($update_fields)) { // Only update if there are fields to update
            $sql_update = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $bind_params .= "i"; // Add integer for user_id
            $bind_values[] = &$current_user_id; // Add user_id to bound values

            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                // Use call_user_func_array for dynamic bind_param due to variable number of arguments
                call_user_func_array([$stmt_update, 'bind_param'], array_merge([$bind_params], $bind_values));
                
                if ($stmt_update->execute()) {
                    if (empty($success_message)) { // Only if password/image message wasn't set earlier
                        $success_message = "Profile updated successfully!";
                    } else {
                        // If password or image message was set, prepend "Profile updated"
                        $success_message = "Profile updated successfully!" . $success_message;
                    }
                    // Update session data for name and email
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                } else {
                    $error_message = "Error updating profile: " . htmlspecialchars($stmt_update->error);
                    error_log("Error updating profile: " . $stmt_update->error);
                }
                $stmt_update->close();
            } else {
                $error_message = "Database error preparing update statement.";
                error_log("Failed to prepare update profile statement: " . $conn->error);
            }
        } elseif (empty($update_fields) && empty($error_message) && $_FILES['profile_image_upload']['error'] == UPLOAD_ERR_NO_FILE) {
            // No fields changed, and no image uploaded.
            $success_message = "No changes submitted or no changes detected.";
        }
    }
}

// Always fetch latest user data for display, even after an update attempt
$user_data = fetchUserData($conn, $current_user_id);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMS - My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50; /* Green */
            --primary-hover: #45a049;
            --secondary-color: #007bff; /* Blue */
            --secondary-hover: #0056b3;
            --danger-color: #dc3545; /* Red */
            --danger-hover: #c82333;
            --info-color: #ffc107; /* Orange */
            --info-hover: #e0a800;
            --text-color: #333;
            --light-text-color: #555;
            --accent-text-color: #1a73e8; /* Vibrant blue for emphasis */
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
        .profile-container {
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
            margin-bottom: 30px;
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
        .info { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--light-text-color); font-weight: 400; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="file"] { /* Added file input styling */
            width: calc(100% - 24px);
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group input[type="password"]:focus,
        .form-group input[type="file"]:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            outline: none;
        }
        
        .read-only-field {
            background-color: var(--light-bg);
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1em;
            color: var(--light-text-color);
            margin-bottom: 20px;
        }
        .read-only-field strong {
            color: var(--text-color);
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
        .btn-danger { background-color: var(--danger-color); } /* Added for remove image button */
        .btn-danger:hover { background-color: var(--danger-hover); }


        /* Password change section specific styling */
        .password-change-section {
            border-top: 1px solid var(--border-color);
            margin-top: 30px;
            padding-top: 25px;
        }
        .password-change-section h3 {
            text-align: left;
            margin-bottom: 20px;
            font-size: 1.2em;
        }

        @media (max-width: 768px) {
            .profile-container {
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
    <div class="profile-container">
        <h2>My Profile</h2>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($user_data): ?>
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_data['id']); ?>">
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <div class="read-only-field"><strong><?php echo htmlspecialchars($user_data['username']); ?></strong></div>
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name:</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Current Profile Image:</label>
                    <?php if (!empty($user_data['profile_image']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $user_data['profile_image'])): ?>
                        <div style="margin-bottom: 15px;">
                            <img src="<?php echo htmlspecialchars($user_data['profile_image']); ?>" alt="Profile Image" style="width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 2px solid var(--border-color);">
                            <br>
                            <a href="profile.php?action=remove_image" onclick="return confirm('Are you sure you want to remove your profile image?');" class="btn btn-danger" style="margin-top: 10px; padding: 8px 15px; font-size: 0.85em;">Remove Image</a>
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom: 15px; color: #777; font-style: italic;">No profile image uploaded.</div>
                    <?php endif; ?>

                    <label for="profile_image_upload">Upload New Profile Image (Optional):</label>
                    <input type="file" id="profile_image_upload" name="profile_image_upload" accept="image/jpeg, image/png, image/gif">
                    <small style="color: #777;">Accepted formats: JPG, PNG, GIF. Max size: 2MB.</small>
                </div>

                <div class="form-group">
                    <label for="role">Your Role:</label>
                    <div class="read-only-field"><strong><?php echo htmlspecialchars(ucfirst($user_data['role'])); ?></strong></div>
                </div>

                <p style="font-size: 0.9em; color: #777; text-align: right; margin-top: -10px;">
                    Account created: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($user_data['created_at']))); ?>
                </p>

                <div class="password-change-section">
                    <h3>Change Password</h3>
                    <div class="form-group">
                        <label for="current_password">Current Password:</label>
                        <input type="password" id="current_password" name="current_password">
                        <small style="color: #777;">Required if you want to change your password.</small>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password">
                        <small style="color: #777;">Leave blank if not changing password.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_new_password">Confirm New Password:</label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password">
                    </div>
                </div>

                <div class="btn-group">
                    <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
                    <button type="submit" class="btn btn-save">Save Changes</button>
                </div>
            </form>
        <?php else: ?>
            <div class="message error">User profile data could not be loaded. Please try again or contact support.</div>
            <div class="btn-group" style="justify-content: center;">
                <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>