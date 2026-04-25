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
    <meta name="description" content="Splitflix - Choose how you want to use the platform">
    <title>Choose View | Splitflix</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Top Nav -->
    <nav class="top-nav">
        <div class="nav-brand">
            <span class="nav-logo">🎬</span>
            <span class="nav-title">Splitflix</span>
        </div>
    </nav>

    <!-- Role Selection -->
    <main class="role-select-page">
        <div class="role-header">
            <h1>How would you like to continue?</h1>
            <p>Choose your view to get started</p>
        </div>

        <div class="role-cards">
            <!-- User Card -->
            <a href="user/dashboard.php" class="role-card role-user" id="viewAsUser">
                <div class="role-icon-wrapper">
                    <div class="role-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                </div>
                <h2>View as User</h2>
                <p>Browse available groups across platforms, request to join, track your subscriptions and payments.</p>
                <div class="role-features">
                    <span>🔍 Browse Groups</span>
                    <span>📋 Join Requests</span>
                    <span>💳 Payments</span>
                </div>
                <div class="role-cta">
                    <span>Continue as User</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </div>
            </a>

            <!-- Owner Card -->
            <a href="owner/dashboard.php" class="role-card role-owner" id="viewAsOwner">
                <div class="role-icon-wrapper">
                    <div class="role-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                            <path d="M2 17l10 5 10-5"></path>
                            <path d="M2 12l10 5 10-5"></path>
                        </svg>
                    </div>
                </div>
                <h2>View as Owner</h2>
                <p>Create and manage subscription groups, approve members, track payments and broadcast notifications.</p>
                <div class="role-features">
                    <span>➕ Create Groups</span>
                    <span>✅ Approvals</span>
                    <span>📊 Revenue</span>
                </div>
                <div class="role-cta">
                    <span>Continue as Owner</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </div>
            </a>
        </div>
    </main>
</body>
</html>
