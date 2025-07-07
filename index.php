<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Set timezone for Lagos, Nigeria
date_default_timezone_set('Africa/Lagos');
$current_datetime = date("F j, Y, g:i a");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMS - Welcome</title>
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
            --light-bg: #f0f2f5; /* A slightly softer light background */
            --white-bg: #ffffff;
            --border-color: #e0e0e0;
            --shadow-color: rgba(0, 0, 0, 0.08);
            --dark-blue: #2c3e50; /* A dark blue for contrast, like for hero text */
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Make it full height */
            text-align: center; /* Center content horizontally */
        }
        .welcome-container {
            background-color: var(--white-bg);
            padding: 50px 40px; /* More padding for a premium feel */
            border-radius: 15px; /* Slightly more rounded corners */
            box-shadow: 0 10px 30px var(--shadow-color); /* More pronounced shadow */
            max-width: 700px; /* Wider container */
            width: 100%;
            border: 1px solid var(--border-color);
        }
        h1 {
            color: var(--dark-blue); /* A darker, professional blue for the main heading */
            margin-bottom: 25px;
            font-weight: 700; /* Bolder heading */
            font-size: 2.5em; /* Larger font size */
        }
        p {
            color: var(--light-text-color);
            margin-bottom: 20px;
            font-size: 1.1em;
            max-width: 550px; /* Constrain paragraph width for readability */
            margin-left: auto;
            margin-right: auto;
        }
        .current-time {
            font-size: 0.95em;
            color: #777;
            margin-top: 30px;
            margin-bottom: 40px; /* Space before buttons */
        }
        .current-time strong {
            color: var(--text-color);
        }
        .button-group {
            display: flex;
            justify-content: center;
            gap: 25px; /* Space between buttons */
            margin-top: 30px;
        }
        .btn {
            padding: 15px 35px; /* Larger padding for prominent buttons */
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em; /* Larger font size */
            cursor: pointer;
            text-decoration: none; /* Remove underline for links */
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15); /* More prominent button shadow */
        }
        .btn:hover {
            transform: translateY(-3px); /* Lift effect on hover */
            box-shadow: 0 8px 20px rgba(0,0,0,0.25);
        }
        .btn-primary {
            background-color: var(--primary-color); /* Green for primary action (Register) */
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        .btn-secondary {
            background-color: var(--secondary-color); /* Blue for secondary action (Login) */
        }
        .btn-secondary:hover {
            background-color: var(--secondary-hover);
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2em;
            }
            .welcome-container {
                padding: 30px 20px;
            }
            .button-group {
                flex-direction: column; /* Stack buttons vertically on small screens */
                gap: 15px;
            }
            .btn {
                width: 100%; /* Full width when stacked */
            }
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <h1>Welcome to the University Document Management System!</h1>
        <p>
            Your centralized hub for managing academic and administrative documents efficiently. 
            Streamline your workflow, securely store important files, and access what you need, when you need it.
        </p>
        <p>Whether you're a student, faculty member, or administrator, UDMS is designed to simplify your document interactions within the university environment.</p>

        <div class="current-time">
            Current date and time in Lagos, Nigeria: <strong><?php echo $current_datetime; ?></strong>
        </div>

        <div class="button-group">
            <a href="login.php" class="btn btn-secondary">Login to Your Account</a>
            <a href="register.php" class="btn btn-primary">Register New Account</a>
        </div>
    </div>
</body>
</html>
