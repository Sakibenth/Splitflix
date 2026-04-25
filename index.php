<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Splitflix Dashboard - Manage your shared subscriptions">
    <title>Dashboard | Splitflix</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0f0f1a;
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dashboard-placeholder {
            text-align: center;
            padding: 3rem;
        }
        .dashboard-placeholder h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #e50914, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        .dashboard-placeholder p {
            color: #8888aa;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .dashboard-placeholder .user-info {
            margin: 2rem 0;
            padding: 1.5rem 2rem;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            display: inline-block;
        }
        .dashboard-placeholder .user-info span {
            color: #ff6b6b;
            font-weight: 600;
        }
        .btn-logout {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 12px 32px;
            background: linear-gradient(135deg, #e50914, #b20710);
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(229,9,20,0.3);
        }
    </style>
</head>
<body>
    <div class="dashboard-placeholder">
        <h1>🎬 Splitflix</h1>
        <p>Welcome to your dashboard!</p>
        <div class="user-info">
            <p>Logged in as: <span><?php echo htmlspecialchars($_SESSION['name']); ?></span></p>
            <p>Email: <span><?php echo htmlspecialchars($_SESSION['email']); ?></span></p>
            <p>Role: <span><?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?></span></p>
        </div>
        <br>
        <a href="auth/logout.php" class="btn-logout">Sign Out</a>
    </div>
</body>
</html>
