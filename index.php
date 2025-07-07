<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMS - Welcome</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        p { color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to Your UDMS Project!</h1>
        <p>This is your first PHP page running on your local server.</p>
        <p>Current date and time in Lekki, Lagos: <strong>
            <?php
            // Set timezone for Lagos
            date_default_timezone_set('Africa/Lagos');
            echo date("F j, Y, g:i a");
            ?>
        </strong></p>
        <p>Now, let's start building the rest!</p>
    </div>
</body>
</html>