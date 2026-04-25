<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Owner Dashboard - Manage your subscription groups on Splitflix">
    <title>Owner Dashboard | Splitflix</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Top Nav -->
    <nav class="top-nav">
        <div class="nav-brand">
            <a href="../index.php" class="nav-brand-link">
                <span class="nav-logo">🎬</span>
                <span class="nav-title">Splitflix</span>
            </a>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link active">My Groups</a>
            <a href="#" class="nav-link">Requests</a>
            <a href="#" class="nav-link">Revenue</a>
        </div>
        <div class="nav-user">
            <span class="nav-greeting">Hi, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="../auth/logout.php" class="nav-logout">Sign Out</a>
        </div>
    </nav>

    <main class="dashboard-page">
        <div class="page-header">
            <div class="page-header-content">
                <h1>Owner Dashboard</h1>
                <p>Create and manage your subscription groups</p>
            </div>
        </div>

        <div class="empty-state">
            <span class="empty-icon">🏗️</span>
            <h3>Coming Soon</h3>
            <p>The owner dashboard is under development. You'll be able to create groups, manage members, and track revenue here.</p>
            <a href="../index.php" class="btn-back">← Back to Role Selection</a>
        </div>
    </main>
</body>
</html>
