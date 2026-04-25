<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/icons.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch platforms from database
$platforms = [];
$query = "SELECT * FROM platforms ORDER BY platform_name ASC";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $platforms[] = $row;
    }
}

// Count available groups per platform
$group_counts = [];
$query = "SELECT p.platform_id, COUNT(sg.group_id) as group_count 
          FROM platforms p 
          LEFT JOIN subscription_group sg ON p.platform_id = sg.platform_id AND sg.status = 'active' AND sg.seats_remaining > 0
          GROUP BY p.platform_id";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $group_counts[$row['platform_id']] = $row['group_count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse subscription platforms on Splitflix">
    <title>Browse Platforms | Splitflix</title>
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
            <a href="dashboard.php" class="nav-link active">Platforms</a>
            <a href="#" class="nav-link">My Groups</a>
            <a href="#" class="nav-link">Payments</a>
        </div>
    </nav>

    <main class="dashboard-page">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <h1>Browse Platforms</h1>
                <p>Choose a platform to find available groups and start saving</p>
            </div>
        </div>

        <!-- Platform Grid -->
        <div class="platform-grid">
            <?php foreach ($platforms as $platform): 
                $count = $group_counts[$platform['platform_id']] ?? 0;
                $color = $platform['brand_color'] ?? '#e50914';
            ?>
            <a href="groups.php?platform_id=<?php echo $platform['platform_id']; ?>" 
               class="platform-card" id="platform-<?php echo $platform['platform_id']; ?>"
               style="--brand-color: <?php echo htmlspecialchars($color); ?>">
                <div class="platform-card-glow" style="background: <?php echo htmlspecialchars($color); ?>"></div>
                <div class="platform-card-content">
                    <div class="platform-logo-area">
                        <span class="platform-emoji" style="color: <?php echo htmlspecialchars($color); ?>"><?php echo getPlatformIcon($platform['platform_name']); ?></span>
                    </div>
                    <div class="platform-info">
                        <h3><?php echo htmlspecialchars($platform['platform_name']); ?></h3>
                        <p class="platform-category"><?php echo htmlspecialchars($platform['category']); ?></p>
                    </div>
                    <div class="platform-stats">
                        <div class="stat">
                            <span class="stat-number"><?php echo $count; ?></span>
                            <span class="stat-label">Available Groups</span>
                        </div>
                    </div>
                    <div class="platform-arrow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($platforms)): ?>
        <div class="empty-state">
            <span class="empty-icon">📭</span>
            <h3>No Platforms Available</h3>
            <p>Platforms will appear here once they are added to the system.</p>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
