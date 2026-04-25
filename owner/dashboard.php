<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/icons.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];

// Fetch platforms for the "Create Group" panels
$platforms = [];
$query = "SELECT * FROM platforms ORDER BY platform_name ASC";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $platforms[] = $row;
    }
}

// Fetch owner's existing groups
$my_groups = [];
$groups_query = "
    SELECT sg.*, p.platform_name, p.logo_emoji, p.brand_color, pl.plan_name 
    FROM subscription_group sg
    JOIN platforms p ON sg.platform_id = p.platform_id
    JOIN plans pl ON sg.plan_id = pl.plan_id
    WHERE sg.owner_id = ?
    ORDER BY sg.created_at DESC
";
$stmt = mysqli_prepare($conn, $groups_query);
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$groups_result = mysqli_stmt_get_result($stmt);
if ($groups_result) {
    while ($row = mysqli_fetch_assoc($groups_result)) {
        $my_groups[] = $row;
    }
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
    <style>
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 2rem 0 1.5rem;
            color: #f0f0f5;
        }
        
        .my-groups-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .group-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
        }
        
        .group-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .group-logo {
            font-size: 2rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
        }
        
        .group-details h4 {
            font-size: 1.1rem;
            margin-bottom: 4px;
        }
        
        .group-details p {
            font-size: 0.85rem;
            color: #8888aa;
        }
        
        .group-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .status-paused { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .status-closed { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    </style>
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
    </nav>

    <main class="dashboard-page">
        <div class="page-header">
            <div class="page-header-content">
                <h1>Owner Dashboard</h1>
                <p>Create and manage your subscription groups</p>
            </div>
        </div>

        <h2 class="section-title">Create a New Group</h2>
        <!-- Platform Grid -->
        <div class="platform-grid">
            <?php foreach ($platforms as $platform): 
                $color = $platform['brand_color'] ?? '#e50914';
            ?>
            <a href="create_group.php?platform_id=<?php echo $platform['platform_id']; ?>" 
               class="platform-card" 
               style="--brand-color: <?php echo htmlspecialchars($color); ?>">
                <div class="platform-card-glow" style="background: <?php echo htmlspecialchars($color); ?>"></div>
                <div class="platform-card-content">
                    <div class="platform-logo-area">
                        <span class="platform-emoji"><?php echo getPlatformIcon($platform['platform_name']); ?></span>
                    </div>
                    <div class="platform-info">
                        <h3><?php echo htmlspecialchars($platform['platform_name']); ?></h3>
                        <p class="platform-category">Create Group</p>
                    </div>
                    <div class="platform-arrow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <h2 class="section-title">My Active Groups</h2>
        <div class="my-groups-list">
            <?php if (empty($my_groups)): ?>
                <div class="empty-state" style="padding: 2rem 1rem;">
                    <span class="empty-icon">📭</span>
                    <h3>No Groups Yet</h3>
                    <p>Click on any platform above to start sharing your subscription!</p>
                </div>
            <?php else: ?>
                <?php foreach ($my_groups as $group): ?>
                <div class="group-row">
                    <div class="group-info">
                        <div class="group-logo" style="color: <?php echo htmlspecialchars($group['brand_color']); ?>">
                            <?php echo getPlatformIcon($group['platform_name']); ?>
                        </div>
                        <div class="group-details">
                            <h4><?php echo htmlspecialchars($group['group_name']); ?></h4>
                            <p><?php echo htmlspecialchars($group['platform_name']); ?> • <?php echo htmlspecialchars($group['plan_name']); ?> • <?php echo htmlspecialchars($group['seats_remaining']); ?> seats left</p>
                        </div>
                    </div>
                    <div>
                        <span class="group-status status-<?php echo htmlspecialchars($group['status']); ?>">
                            <?php echo htmlspecialchars($group['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
