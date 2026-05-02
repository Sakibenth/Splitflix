<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/icons.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];

// Verify user exists
$user_check_stmt = mysqli_prepare($conn, "SELECT user_id, verification_status FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($user_check_stmt, "i", $owner_id);
mysqli_stmt_execute($user_check_stmt);
$user_check_res = mysqli_stmt_get_result($user_check_stmt);
if (!$user_check_res || !$user_row = mysqli_fetch_assoc($user_check_res)) {
    mysqli_stmt_close($user_check_stmt);
    header("Location: ../auth/logout.php");
    exit();
}
$verification_status = $user_row['verification_status'];
mysqli_stmt_close($user_check_stmt);

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
    SELECT sg.*, p.platform_name, p.logo_emoji, p.brand_color 
    FROM subscription_group sg
    JOIN platforms p ON sg.platform_id = p.platform_id
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
            align-items: flex-start;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            position: relative;
            cursor: pointer;
            transition: transform 0.2s, border-color 0.2s;
        }
        
        .group-row:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.2);
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

        .group-desc {
            margin-top: 8px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.02);
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            font-size: 0.85rem;
            color: #bbbbcc;
            line-height: 1.4;
        }
        
        .group-status-container {
            position: absolute;
            top: 1.25rem;
            right: 1.5rem;
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

        .verification-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(234, 179, 8, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .verification-card.verified {
            border-color: rgba(34, 197, 94, 0.3);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #22c55e;
            font-weight: 600;
        }
        .verification-card h3 {
            color: #eab308;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .verification-card p {
            color: #bbbbcc;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .verification-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            align-items: flex-end;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-group label {
            font-size: 0.85rem;
            color: #f0f0f5;
        }
        .form-group input {
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
            color: white;
            font-family: inherit;
        }
        .btn-verify {
            background: #eab308;
            color: #000;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            height: 42px;
        }
        .btn-verify:hover {
            background: #facc15;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
            font-size: 0.9rem;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid rgba(34, 197, 94, 0.2);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Top Nav -->
    <nav class="top-nav">
        <div class="nav-brand">
            <a href="../auth/logout.php" class="nav-brand-link">
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

        <?php if (isset($_GET['verified']) && $_GET['verified'] == 1): ?>
            <div class="alert-success">Profile verified successfully!</div>
        <?php endif; ?>

        <?php if ($verification_status !== 'verified'): ?>
            <div class="verification-card">
                <h3>⚠️ Verify Your Identity</h3>
                <p>To build trust with members and show a verified badge on your groups, please upload a photo of your ID card and provide your phone number.</p>
                
                <?php if (isset($_GET['verify_error'])): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($_GET['verify_error']); ?></div>
                <?php endif; ?>

                <form action="verify_profile.php" method="POST" enctype="multipart/form-data" class="verification-form">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" placeholder="+880..." required>
                    </div>
                    <div class="form-group">
                        <label for="id_card">ID Card Photo (Max 5MB)</label>
                        <input type="file" id="id_card" name="id_card" accept=".jpg,.jpeg,.png,.webp" required>
                    </div>
                    <button type="submit" class="btn-verify">Submit Verification</button>
                </form>
            </div>
        <?php else: ?>
            <div class="verification-card verified">
                <span>✅</span>
                <span>Your profile is verified. Users will see a trusted badge on your groups.</span>
            </div>
        <?php endif; ?>

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
                <a href="group_details.php?group_id=<?php echo $group['group_id']; ?>" class="group-row" style="text-decoration: none; color: inherit;">
                    <div class="group-info">
                        <div class="group-logo" style="color: <?php echo htmlspecialchars($group['brand_color']); ?>">
                            <?php echo getPlatformIcon($group['platform_name']); ?>
                        </div>
                        <div class="group-details">
                            <h4><?php echo htmlspecialchars($group['group_name']); ?></h4>
                            <p><?php echo htmlspecialchars($group['platform_name']); ?> • <?php echo htmlspecialchars($group['seats_remaining']); ?> seats left</p>
                            <div class="group-desc">
                                <strong>Plan:</strong> <?php echo htmlspecialchars($group['plan_description']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="group-status-container">
                        <span class="group-status status-<?php echo htmlspecialchars($group['status']); ?>">
                            <?php echo htmlspecialchars($group['status']); ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
