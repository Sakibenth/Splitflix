<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/icons.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$platform_id = isset($_GET['platform_id']) ? (int)$_GET['platform_id'] : 0;

if (!$platform_id) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch platform info
$platform_query = "SELECT * FROM platforms WHERE platform_id = ?";
$stmt = mysqli_prepare($conn, $platform_query);
mysqli_stmt_bind_param($stmt, "i", $platform_id);
mysqli_stmt_execute($stmt);
$platform = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$platform) {
    header("Location: dashboard.php");
    exit();
}

// Fetch Groups with Owner Reputation and current user's status
$groups_query = "
    SELECT sg.*, u.name as owner_name, u.verification_status,
           (SELECT AVG(rating) FROM reviews WHERE reviewee_id = sg.owner_id AND reviewer_role = 'member') as owner_rating,
           (SELECT COUNT(*) FROM reviews WHERE reviewee_id = sg.owner_id AND reviewer_role = 'member') as review_count,
           gm.membership_status as my_status
    FROM subscription_group sg
    JOIN users u ON sg.owner_id = u.user_id
    LEFT JOIN group_members gm ON sg.group_id = gm.group_id AND gm.user_id = ?
    WHERE sg.platform_id = ? AND sg.status = 'active'
    ORDER BY sg.seats_remaining DESC, sg.created_at DESC
";
$stmt = mysqli_prepare($conn, $groups_query);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $platform_id);
mysqli_stmt_execute($stmt);
$groups_res = mysqli_stmt_get_result($stmt);
$groups = [];
while ($row = mysqli_fetch_assoc($groups_res)) {
    $groups[] = $row;
}
mysqli_stmt_close($stmt);

$brand_color = htmlspecialchars($platform['brand_color'] ?? '#e50914');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($platform['platform_name']); ?> Groups | Splitflix</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .groups-container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .group-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 2rem; }
        
        .group-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            transition: transform 0.2s, border-color 0.2s;
            position: relative;
            overflow: hidden;
        }
        .group-card:hover { transform: translateY(-4px); border-color: <?php echo $brand_color; ?>50; }
        
        .group-status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-open { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .status-full { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        .owner-info { display: flex; align-items: center; gap: 0.75rem; }
        .owner-avatar { width: 40px; height: 40px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .owner-details h4 { font-size: 0.95rem; display: flex; align-items: center; gap: 4px; }
        .verified-badge { color: #3b82f6; font-size: 0.8rem; }
        
        .rating-stars { color: #eab308; font-size: 0.85rem; display: flex; align-items: center; gap: 2px; }
        .rating-count { color: #8888aa; font-size: 0.75rem; margin-left: 4px; }

        .group-plan-info { background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); }
        .plan-name { font-weight: 700; color: #fff; margin-bottom: 4px; display: block; }
        .plan-desc { font-size: 0.85rem; color: #8888aa; }

        .group-stats { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
        .price-tag { font-size: 1.25rem; font-weight: 800; color: #fff; }
        .price-tag span { font-size: 0.85rem; color: #8888aa; font-weight: 400; }
        
        .seats-info { font-size: 0.9rem; color: #ccccee; }
        .seats-count { font-weight: 700; color: <?php echo $brand_color; ?>; }

        .btn-join {
            width: 100%;
            padding: 12px;
            background: <?php echo $brand_color; ?>;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: filter 0.2s;
            text-align: center;
            text-decoration: none;
        }
        .btn-join:hover { filter: brightness(1.1); }
        .btn-join.disabled { background: rgba(255,255,255,0.05); color: #666; cursor: not-allowed; }

        .empty-state { text-align: center; padding: 4rem 2rem; color: #8888aa; }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="nav-brand">
            <a href="dashboard.php" class="nav-brand-link">
                <span class="nav-logo">🎬</span>
                <span class="nav-title">Splitflix</span>
            </a>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Platforms</a>
            <a href="my_groups.php" class="nav-link">My Groups</a>
        </div>
    </nav>

    <main class="groups-container">
        <div class="page-header" style="margin-bottom: 3rem;">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="font-size: 4rem;"><?php echo getPlatformIcon($platform['platform_name']); ?></div>
                <div>
                    <h1 style="font-size: 2.5rem;"><?php echo htmlspecialchars($platform['platform_name']); ?> Groups</h1>
                    <p style="color: #8888aa;">Available shared subscriptions for <?php echo htmlspecialchars($platform['platform_name']); ?></p>
                </div>
            </div>
        </div>

        <?php if (empty($groups)): ?>
            <div class="empty-state">
                <span style="font-size: 4rem;">🎭</span>
                <h3>No Active Groups</h3>
                <p>There are currently no active groups for this platform. Why not check back later or start your own?</p>
            </div>
        <?php else: ?>
            <div class="group-grid">
                <?php foreach ($groups as $group): 
                    $is_full = $group['seats_remaining'] <= 0;
                    $rating = $group['owner_rating'] ? round($group['owner_rating'], 1) : null;
                ?>
                <div class="group-card">
                    <span class="group-status-badge <?php echo $is_full ? 'status-full' : 'status-open'; ?>">
                        <?php echo $is_full ? 'Full' : 'Partially Filled'; ?>
                    </span>

                    <div class="owner-info">
                        <div class="owner-avatar">👤</div>
                        <div class="owner-details">
                            <h4>
                                <?php echo htmlspecialchars($group['owner_name']); ?>
                                <?php if ($group['verification_status'] === 'verified'): ?>
                                    <span class="verified-badge" title="Verified Owner">✓</span>
                                <?php endif; ?>
                            </h4>
                            <div class="rating-stars">
                                <?php if ($rating): ?>
                                    ⭐ <?php echo $rating; ?>
                                    <span class="rating-count">(<?php echo $group['review_count']; ?> reviews)</span>
                                <?php else: ?>
                                    <span style="color: #666; font-size: 0.75rem;">No reviews yet</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="group-plan-info">
                        <span class="plan-name"><?php echo htmlspecialchars($group['group_name']); ?></span>
                        <p class="plan-desc"><?php echo nl2br(htmlspecialchars($group['plan_description'])); ?></p>
                        <?php if (!empty($group['group_description'])): ?>
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.05);">
                                <p style="font-size: 0.8rem; color: #8888aa; margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Owner's Note:</p>
                                <p style="font-size: 0.85rem; color: #ccccee; line-height: 1.4;"><?php echo nl2br(htmlspecialchars($group['group_description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="group-stats">
                        <div class="price-tag">
                            ৳<?php echo number_format($group['cost_per_member'], 0); ?><span>/mo</span>
                        </div>
                        <div class="seats-info">
                            <span class="seats-count"><?php echo $group['seats_remaining']; ?></span> seats left
                        </div>
                    </div>

                    <?php if ($group['owner_id'] == $user_id): ?>
                        <button class="btn-join" style="background: rgba(255,255,255,0.05); color: #888; cursor: default;" disabled>My Group</button>
                    <?php elseif ($group['my_status'] === 'active'): ?>
                        <button class="btn-join" style="background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); cursor: default;" disabled>Joined</button>
                    <?php elseif ($group['my_status'] === 'waitlisted'): ?>
                        <button class="btn-join" style="background: rgba(234, 179, 8, 0.15); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.3); cursor: default;" disabled>Waiting for Approval</button>
                    <?php elseif ($is_full): ?>
                        <button class="btn-join disabled" disabled>Group Full</button>
                    <?php else: ?>
                        <a href="join_group.php?group_id=<?php echo $group['group_id']; ?>" class="btn-join">Request to Join</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
