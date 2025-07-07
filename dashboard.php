<?php
session_start();

// Check if the user is NOT logged in. If not, redirect them to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// If the user is logged in, retrieve their session data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Include the database connection for listing documents
require_once 'db_connect.php';

// Initialize variables for document listing and search
$documents = [];
$search_query = "";

// --- Handle Search/Filter ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $conn->real_escape_string($_GET['search']);
    $sql = "SELECT d.*, u.username as uploader_username 
            FROM documents d 
            JOIN users u ON d.user_id = u.id 
            WHERE d.title LIKE '%$search_query%' 
            OR d.description LIKE '%$search_query%' 
            OR d.file_name LIKE '%$search_query%' 
            OR u.username LIKE '%$search_query%' -- Allow searching by uploader username
            ORDER BY d.uploaded_at DESC";
} else {
    // Default query to list all documents
    $sql = "SELECT d.*, u.username as uploader_username 
            FROM documents d 
            JOIN users u ON d.user_id = u.id 
            ORDER BY d.uploaded_at DESC";
}

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
}

$conn->close(); // Close connection after fetching documents
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMS - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50; /* A fresh green */
            --primary-hover: #45a049;
            --secondary-color: #007bff; /* Blue for actions */
            --secondary-hover: #0056b3;
            --danger-color: #dc3545; /* Red for danger */
            --danger-hover: #c82333;
            --info-color: #ffc107; /* Orange for warnings/info */
            --info-hover: #e0a800;
            --text-color: #333;
            --light-text-color: #555;
            --accent-text-color: #1a73e8; /* A vibrant blue for emphasis */
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
        }

        .dashboard-container {
            background-color: var(--white-bg);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px var(--shadow-color);
            max-width: 1000px;
            margin: 30px auto;
            border: 1px solid var(--border-color);
        }

        h2, h3 {
            color: var(--text-color);
            margin-bottom: 25px;
            font-weight: 600;
            text-align: center;
        }

        .welcome-message {
            font-size: 1.3em;
            color: var(--light-text-color); /* Adjusted to match regular text */
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        /* Style for the emphasized text within welcome/user info */
        .welcome-message strong, .user-info strong {
            color: var(--accent-text-color); /* Use the new vibrant blue */
            font-weight: 700; /* Extra bold */
            font-size: 1.05em; /* Slightly larger */
        }

        .user-info {
            text-align: center;
            margin-bottom: 30px;
            font-size: 0.95em;
            color: var(--light-text-color);
        }

        .logout-btn {
            display: block;
            width: fit-content;
            margin: 0 auto 40px auto;
            padding: 12px 25px;
            background-color: var(--danger-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 1em;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .logout-btn:hover {
            background-color: var(--danger-hover);
            transform: translateY(-2px);
        }

        hr {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 40px 0;
        }

        .section {
            margin-bottom: 40px;
            padding: 30px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background-color: var(--white-bg);
            box-shadow: 0 5px 15px var(--shadow-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--light-text-color);
            font-weight: 400;
        }

        .form-group input[type="text"],
        .form-group input[type="file"],
        .form-group textarea,
        .form-group select {
            width: calc(100% - 24px);
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="file"]:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            outline: none;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        .btn-upload { background-color: var(--primary-color); }
        .btn-upload:hover { background-color: var(--primary-hover); }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .search-form input[type="text"] {
            flex-grow: 1;
        }
        .btn-search { background-color: var(--secondary-color); }
        .btn-search:hover { background-color: var(--secondary-hover); }

        .btn-clear-search {
            background-color: var(--info-color);
            color: var(--text-color);
            text-decoration: none;
        }
        .btn-clear-search:hover { background-color: var(--info-hover); }


        .document-list table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 25px;
            background-color: var(--white-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px var(--shadow-color);
        }
        .document-list th, .document-list td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .document-list th {
            background-color: var(--light-bg);
            color: var(--light-text-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 0.5px;
        }
        .document-list tbody tr:last-child td {
            border-bottom: none;
        }
        .document-list tbody tr:nth-child(even) {
            background-color: #fcfcfc;
        }
        .document-list tbody tr:hover {
            background-color: #f0f0f0;
            cursor: pointer;
        }
        .no-documents {
            text-align: center;
            color: #777;
            padding: 30px;
            background-color: var(--light-bg);
            border-radius: 8px;
            margin-top: 25px;
            font-style: italic;
        }
        .document-actions a {
            display: inline-block; /* For proper spacing */
            margin-right: 8px; /* Reduced spacing */
            margin-bottom: 5px; /* Add some vertical space */
            text-decoration: none;
            color: var(--secondary-color);
            font-weight: 500;
            transition: color 0.3s ease;
            white-space: nowrap; /* Prevent breaking */
        }
        .document-actions a:hover {
            color: var(--secondary-hover);
            text-decoration: underline;
        }
        .document-actions a.delete-link {
            color: var(--danger-color);
        }
        .document-actions a.delete-link:hover {
            color: var(--danger-hover);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 20px;
                margin: 20px auto;
            }
            .document-list table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .form-group input, .form-group textarea, .form-group select {
                width: calc(100% - 20px);
            }
            .search-form {
                flex-direction: column;
                gap: 10px;
            }
            .search-form input, .search-form button, .search-form a {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Welcome to Your UDMS Dashboard!</h2>
        <div style="text-align: center; margin-bottom: 20px;">
            <?php
            // Use a default avatar if no profile image is set or if the file doesn't exist
            $profile_image_path = 'profile_uploads/default_avatar.png'; 
            // Check if session has a profile_image and if that file actually exists on the server
            if (!empty($_SESSION['profile_image']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $_SESSION['profile_image'])) {
                $profile_image_path = $_SESSION['profile_image'];
            }
            ?>
            <img src="<?php echo htmlspecialchars($profile_image_path); ?>" alt="Profile Picture" 
                 style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color); margin-bottom: 10px;">
            <p class="welcome-message">Hello, <strong><?php echo htmlspecialchars($full_name); ?></strong>!</p>
            <p class="user-info">Your User ID: <strong><?php echo htmlspecialchars($user_id); ?></strong> | Your Role: <strong><?php echo htmlspecialchars($role); ?></strong></p>
        </div>
        <a href="logout.php" class="logout-btn">Logout</a>

        <hr>

        <div class="section upload-section">
            <h3>Upload New Document</h3>
            <?php
            if (isset($_GET['upload_status'])) {
                if ($_GET['upload_status'] == 'success') {
                    echo '<p style="color: var(--primary-color); font-weight: 600;">Document uploaded successfully!</p>';
                } elseif ($_GET['upload_status'] == 'error') {
                    echo '<p style="color: var(--danger-color); font-weight: 600;">Error uploading document: ' . htmlspecialchars($_GET['message'] ?? 'Please try again.') . '</p>';
                }
            }
            ?>
            <form action="upload_document.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Document Title:</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="document_file">Select Document File:</label>
                    <input type="file" id="document_file" name="document_file" required>
                </div>
                <button type="submit" class="btn btn-upload">Upload Document</button>
            </form>
        </div>

        <hr>

        <div class="section search-section">
            <h3>Search Documents</h3>
            <form action="dashboard.php" method="GET" class="search-form">
                <input type="text" id="search" name="search" placeholder="Search by title, description, file name, or uploader..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit" class="btn btn-search">Search</button>
                <?php if (!empty($search_query)): ?>
                    <a href="dashboard.php" class="btn btn-clear-search">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>

        <hr>

        <div class="section document-list">
            <h3>All Documents</h3>
            <?php if (empty($documents)): ?>
                <p class="no-documents">No documents found. Upload one now!</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>File Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Uploaded By</th>
                            <th>Uploaded At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                <td><?php echo htmlspecialchars(substr($doc['description'], 0, 70)) . (strlen($doc['description']) > 70 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                <td><?php echo htmlspecialchars($doc['file_type']); ?></td>
                                <td><?php echo round($doc['file_size'] / 1024, 2); ?> KB</td>
                                <td><?php echo htmlspecialchars($doc['uploader_username']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($doc['uploaded_at']))); ?></td>
                                <td class="document-actions">
                                    <a href="download_document.php?id=<?php echo $doc['id']; ?>">Download</a>
                                    <a href="view_document.php?id=<?php echo $doc['id']; ?>" target="_blank">View</a>
                                    <a href="edit_document.php?id=<?php echo $doc['id']; ?>">Edit</a>
                                    <a href="delete_document.php?id=<?php echo $doc['id']; ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this document?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>