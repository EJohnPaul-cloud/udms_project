<?php
session_start(); // Start the session at the very beginning

// Initialize message variables
$error_message = '';
$success_message = '';

// Check for messages passed via GET parameters (e.g., from a successful registration)
if (isset($_GET['message']) && isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
        $success_message = htmlspecialchars($_GET['message']);
    } elseif ($_GET['status'] == 'error') {
        $error_message = htmlspecialchars($_GET['message']);
    }
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMS - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50; /* Green */
            --primary-hover: #45a049;
            --secondary-color: #007bff; /* Blue - often used for login/action buttons */
            --secondary-hover: #0056b3;
            --danger-color: #dc3545; /* Red */
            --danger-hover: #c82333;
            --info-color: #ffc107; /* Orange */
            --info-hover: #e0a800;
            --text-color: #333;
            --light-text-color: #555;
            --accent-text-color: #1a73e8;
            --light-bg: #f8f9fa;
            --white-bg: #ffffff;
            --border-color: #e0e0e0;
            --shadow-color: rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            color: var(--text-color);
        }
        .login-container {
            background-color: var(--white-bg);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px var(--shadow-color);
            width: 100%;
            max-width: 450px; /* Increased slightly for better look */
            border: 1px solid var(--border-color);
        }
        h2 {
            text-align: center;
            color: var(--text-color);
            margin-bottom: 30px;
            font-weight: 600;
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
        .form-group input[type="password"] {
            width: calc(100% - 24px); /* Account for padding */
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus {
            border-color: var(--secondary-color); /* Blue for focus */
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2); /* Blue shadow */
            outline: none;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background-color: var(--secondary-color); /* Blue for login button */
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            font-weight: 600;
        }
        .btn:hover {
            background-color: var(--secondary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
        .message {
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; } /* Added success style */
        .register-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.95em;
            color: var(--light-text-color);
        }
        .register-link a {
            color: var(--accent-text-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .register-link a:hover {
            color: var(--primary-hover); /* Green hover for register link */
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 0 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login to UDMS</h2>

        <?php
        // Display messages if any
        if (!empty($error_message)) {
            echo '<div class="message error">' . $error_message . '</div>';
        }
        if (!empty($success_message)) {
            echo '<div class="message success">' . $success_message . '</div>';
        }
        ?>

        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label for="username_email">Username or Email:</label>
                <input type="text" id="username_email" name="username_email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>