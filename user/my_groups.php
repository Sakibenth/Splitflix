<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/icons.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch both joined and OWNED groups
$query = "
    (SELECT gm.joined_at, sg.group_id, sg.group_name, sg.plan_description, sg.cost_per_member, sg.validity_end, sg.status as group_status,
           u.name as owner_name, u.email as owner_email, u.phone as owner_phone, u.user_id as owner_id,
           p.platform_name, p.logo_emoji, p.brand_color,
           'member' as my_role,
           gm.payment_status,
           gm.scheduled_leave_date,
           (SELECT review_id FROM reviews WHERE group_id = sg.group_id AND reviewer_id = ? AND reviewer_role = 'member') as my_review_id
    FROM group_members gm
    JOIN subscription_group sg ON gm.group_id = sg.group_id
    JOIN users u ON sg.owner_id = u.user_id
    JOIN platforms p ON sg.platform_id = p.platform_id
    WHERE gm.user_id = ? AND gm.membership_status = 'active')
    
    UNION ALL
    
    (SELECT sg.created_at as joined_at, sg.group_id, sg.group_name, sg.plan_description, sg.cost_per_member, sg.validity_end, sg.status as group_status,
           'Me' as owner_name, 'Me' as owner_email, 'Me' as owner_phone, sg.owner_id,
           p.platform_name, p.logo_emoji, p.brand_color,
           'owner' as my_role,
           NULL as payment_status,
           NULL as scheduled_leave_date,
           NULL as my_review_id
    FROM subscription_group sg
    JOIN platforms p ON sg.platform_id = p.platform_id
    WHERE sg.owner_id = ? AND sg.status = 'active')
    
    ORDER BY joined_at DESC
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
mysqli_stmt_execute($stmt);
$groups_res = mysqli_stmt_get_result($stmt);
$my_groups = [];
while ($row = mysqli_fetch_assoc($groups_res)) {
    $my_groups[] = $row;
}
mysqli_stmt_close($stmt);

// Fetch waitlisted groups
$waitlist_query = "
    SELECT gm.*, sg.group_name, p.platform_name, p.logo_emoji, p.brand_color
    FROM group_members gm
    JOIN subscription_group sg ON gm.group_id = sg.group_id
    JOIN platforms p ON sg.platform_id = p.platform_id
    WHERE gm.user_id = ? AND gm.membership_status = 'waitlisted'
";
$stmt = mysqli_prepare($conn, $waitlist_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$waitlist_res = mysqli_stmt_get_result($stmt);
$my_waitlist = [];
while ($row = mysqli_fetch_assoc($waitlist_res)) {
    $my_waitlist[] = $row;
}
mysqli_stmt_close($stmt);

// Feature 6 & 7: Pre-fetch notifications and recommendations for each active member group
$group_notifs = [];
$group_recs   = [];
$group_chats  = [];
foreach ($my_groups as $g) {
    if ($g['my_role'] !== 'member') continue;
    $gid = (int)$g['group_id'];

    // Notifications
    $ns = mysqli_prepare($conn, "SELECT notification_id, message, created_at FROM group_notifications WHERE group_id = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($ns, "i", $gid);
    mysqli_stmt_execute($ns);
    $nr = mysqli_stmt_get_result($ns);
    $group_notifs[$gid] = [];
    while ($row = mysqli_fetch_assoc($nr)) $group_notifs[$gid][] = $row;
    mysqli_stmt_close($ns);

    // Recommendations
    $rs = mysqli_prepare($conn, "SELECT mr.recommendation_id, mr.title, mr.genre, mr.description, mr.platform_hint, mr.created_at, mr.recommended_by, u.name as recommender_name FROM movie_recommendations mr JOIN users u ON mr.recommended_by = u.user_id WHERE mr.group_id = ? ORDER BY mr.created_at DESC");
    mysqli_stmt_bind_param($rs, "i", $gid);
    mysqli_stmt_execute($rs);
    $rr = mysqli_stmt_get_result($rs);
    $group_recs[$gid] = [];
    while ($row = mysqli_fetch_assoc($rr)) $group_recs[$gid][] = $row;
    mysqli_stmt_close($rs);

    // Chat messages (last 50)
    $cs = mysqli_prepare($conn, "SELECT gc.message_id, gc.message, gc.created_at, gc.user_id, u.name FROM group_chat gc JOIN users u ON gc.user_id = u.user_id WHERE gc.group_id = ? ORDER BY gc.created_at ASC LIMIT 50");
    mysqli_stmt_bind_param($cs, "i", $gid);
    mysqli_stmt_execute($cs);
    $cr = mysqli_stmt_get_result($cs);
    $group_chats[$gid] = [];
    while ($row = mysqli_fetch_assoc($cr)) $group_chats[$gid][] = $row;
    mysqli_stmt_close($cs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subscriptions | Splitflix</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 2rem; }
        .section-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; color: #fff; display: flex; align-items: center; gap: 10px; }
        
        .subscription-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .card-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .platform-info { display: flex; align-items: center; gap: 1rem; }
        .platform-logo { width: 50px; height: 50px; background: rgba(255,255,255,0.05); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
        .platform-details h3 { font-size: 1.1rem; color: #fff; margin-bottom: 4px; }
        .platform-details p { font-size: 0.85rem; color: #8888aa; }

        .owner-contact { background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); }
        .owner-contact h4 { font-size: 0.9rem; margin-bottom: 8px; color: #ccccee; }
        .contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.85rem; }
        .contact-item { display: flex; align-items: center; gap: 6px; color: #8888aa; }
        .contact-item b { color: #fff; }

        .payment-section { display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.15); padding: 1rem 1.25rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); }
        .payment-section .label { font-size: 0.85rem; color: #8888aa; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .btn-pay {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #000; font-weight: 700; font-size: 0.9rem;
            padding: 10px 22px; border-radius: 10px; text-decoration: none;
            border: none; cursor: pointer;
            transition: filter 0.2s, transform 0.15s;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        .btn-pay:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .badge-paid {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(34, 197, 94, 0.12);
            color: #22c55e; font-weight: 700; font-size: 0.9rem;
            padding: 8px 18px; border-radius: 10px;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .review-section { border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.25rem; }
        .btn-review { 
            background: rgba(234, 179, 8, 0.1); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.3);
            padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-review:hover { background: #eab308; color: #000; }
        
        .waitlist-item {
            background: rgba(255, 255, 255, 0.02);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #8888aa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        /* Review Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: #1a1a24; padding: 2.5rem; border-radius: 20px; width: 100%; max-width: 450px; border: 1px solid rgba(255,255,255,0.1); }
        .modal-content h2 { margin-bottom: 1rem; }
        .rating-input { display: flex; gap: 10px; margin: 1.5rem 0; justify-content: center; }
        .star-radio { display: none; }
        .star-label { font-size: 2rem; cursor: pointer; color: #333; transition: color 0.2s; }
        .star-radio:checked ~ .star-label, .star-label:hover, .star-label:hover ~ .star-label { color: #eab308; }
        /* Reverse order for CSS-only star hover trick */
        .rating-input { flex-direction: row-reverse; }

        .btn-leave {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(239, 68, 68, 0.08); color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.25); font-size: 0.82rem;
            font-weight: 600; padding: 7px 14px; border-radius: 8px;
            text-decoration: none; transition: all 0.2s; cursor: pointer;
        }
        .btn-leave:hover { background: rgba(239, 68, 68, 0.18); }
        .badge-leave-scheduled {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(239, 68, 68, 0.08); color: #f87171;
            font-size: 0.82rem; font-weight: 600;
            padding: 6px 12px; border-radius: 8px;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .btn-submit { width: 100%; padding: 12px; background: #eab308; color: #000; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; }

        /* ---- Feature 6 & 7: Tabs inside subscription card ---- */
        .card-tabs { border-top: 1px solid rgba(255,255,255,0.06); padding-top: 1.2rem; }
        .tab-row { display: flex; gap: 6px; margin-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0; }
        .tab-btn { padding: 8px 18px; font-size: 0.85rem; font-weight: 600; color: #8888aa; background: none; border: none; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: all 0.2s; }
        .tab-btn:hover { color: #ccccee; }
        .tab-btn.active { color: #f0f0f5; border-bottom-color: #e50914; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* Notification cards (member read-only) */
        .notif-item { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-left: 3px solid var(--brand); border-radius: 8px; padding: 0.9rem 1.1rem; margin-bottom: 10px; }
        .notif-item p { color: #d0d0e0; font-size: 0.9rem; line-height: 1.5; white-space: pre-wrap; margin: 0; }
        .notif-item-meta { font-size: 0.78rem; color: #8888aa; margin-top: 6px; }
        .notif-empty-msg { color: #8888aa; font-size: 0.88rem; padding: 1rem 0; text-align: center; }

        /* Recommendation form + cards */
        .rec-form { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; padding: 1.2rem; margin-bottom: 1.2rem; }
        .rec-form h4 { font-size: 0.9rem; font-weight: 600; color: #f0f0f5; margin-bottom: 1rem; }
        .rec-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
        .rec-field { display: flex; flex-direction: column; gap: 5px; }
        .rec-field label { font-size: 0.78rem; font-weight: 600; color: #8888aa; text-transform: uppercase; letter-spacing: 0.4px; }
        .rec-field input, .rec-field select, .rec-field textarea { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 7px; color: #f0f0f5; padding: 9px 12px; font-size: 0.88rem; font-family: inherit; transition: border-color 0.2s; }
        .rec-field input:focus, .rec-field select:focus, .rec-field textarea:focus { outline: none; border-color: #e50914; }
        .rec-field textarea { resize: vertical; min-height: 60px; }
        .rec-field select option { background: #12121a; }
        .btn-rec-submit { background: #e50914; color: #fff; border: none; padding: 9px 20px; border-radius: 7px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: filter 0.2s; }
        .btn-rec-submit:hover { filter: brightness(1.1); }
        .btn-rec-submit:disabled { opacity: 0.5; cursor: not-allowed; }
        .rec-alert { padding: 8px 12px; border-radius: 7px; font-size: 0.85rem; font-weight: 500; margin-bottom: 10px; display: none; }
        .rec-alert-success { background: rgba(34,197,94,0.12); color: #4ade80; border: 1px solid rgba(34,197,94,0.2); }
        .rec-alert-error { background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }

        .rec-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 1rem 1.1rem; margin-bottom: 10px; display: flex; gap: 0.9rem; align-items: flex-start; }
        .rec-card.mine { border-left: 3px solid #e50914; }
        .rec-card-icon { font-size: 1.6rem; flex-shrink: 0; }
        .rec-card-body .rec-title { font-size: 0.95rem; font-weight: 700; color: #f0f0f5; margin-bottom: 4px; }
        .rec-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 6px; }
        .badge-pill { font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
        .badge-genre { background: rgba(255,255,255,0.08); color: #ccccee; }
        .badge-platform { background: rgba(229,9,20,0.1); color: #ff6b6b; }
        .rec-card-body .rec-desc { font-size: 0.85rem; color: #9999bb; line-height: 1.4; }
        .rec-card-body .rec-meta { font-size: 0.76rem; color: #8888aa; margin-top: 6px; }

        /* ---- Feature 8: Group Chat ---- */
        .chat-pane { display: flex; flex-direction: column; height: 420px; }
        .chat-messages { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding: 4px 2px 12px 2px; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent; }
        .chat-messages::-webkit-scrollbar { width: 4px; }
        .chat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 99px; }
        .chat-msg { display: flex; gap: 10px; align-items: flex-start; }
        .chat-msg.mine { flex-direction: row-reverse; }
        .chat-avatar { width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; color: #f0f0f5; flex-shrink: 0; }
        .chat-bubble-wrap { max-width: 70%; }
        .chat-name { font-size: 0.72rem; color: #8888aa; margin-bottom: 3px; font-weight: 600; }
        .chat-msg.mine .chat-name { text-align: right; }
        .chat-bubble { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.07); border-radius: 12px 12px 12px 4px; padding: 8px 12px; font-size: 0.9rem; color: #e0e0f0; line-height: 1.45; word-break: break-word; }
        .chat-msg.mine .chat-bubble { background: rgba(229,9,20,0.15); border-color: rgba(229,9,20,0.25); border-radius: 12px 12px 4px 12px; color: #f0e0e0; }
        .chat-time { font-size: 0.7rem; color: #666688; margin-top: 3px; }
        .chat-msg.mine .chat-time { text-align: right; }
        .chat-empty { text-align: center; color: #8888aa; font-size: 0.88rem; padding: 2rem 0; margin: auto; }
        .chat-compose { display: flex; gap: 8px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.06); margin-top: 4px; }
        .chat-input { flex: 1; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #f0f0f5; padding: 9px 12px; font-size: 0.9rem; font-family: inherit; transition: border-color 0.2s; resize: none; height: 40px; }
        .chat-input:focus { outline: none; border-color: #e50914; }
        .chat-send-btn { background: #e50914; color: #fff; border: none; border-radius: 8px; padding: 0 16px; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: filter 0.2s; white-space: nowrap; }
        .chat-send-btn:hover { filter: brightness(1.1); }
        .chat-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }
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
            <a href="my_groups.php" class="nav-link active">My Groups</a>
        </div>
    </nav>

    <div class="container">
        <h2 class="section-title"><span>💳</span> My Active Subscriptions</h2>
        
        <?php if (empty($my_groups)): ?>
            <div class="empty-state" style="padding: 4rem 2rem; background: rgba(255,255,255,0.02); border-radius: 16px; text-align: center;">
                <span style="font-size: 3rem;">🛰️</span>
                <h3>No active subscriptions</h3>
                <p>Browse platforms and join a group to see them here.</p>
                <a href="dashboard.php" class="btn-primary" style="display: inline-block; margin-top: 1rem; text-decoration: none;">Browse Platforms</a>
            </div>
        <?php else: ?>
            <?php foreach ($my_groups as $group): ?>
                <div class="subscription-card">
                    <div class="card-top">
                        <div class="platform-info">
                            <div class="platform-logo" style="color: <?php echo htmlspecialchars($group['brand_color']); ?>">
                                <?php echo getPlatformIcon($group['platform_name']); ?>
                            </div>
                            <div class="platform-details">
                                <h3><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                <p><?php echo htmlspecialchars($group['plan_description']); ?> • ৳<?php echo number_format($group['cost_per_member'], 0); ?>/mo</p>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.75rem; color: #8888aa; margin-bottom: 4px;">RENEWS ON</div>
                            <div style="font-weight: 700; color: #fff;"><?php echo date('M d, Y', strtotime($group['validity_end'])); ?></div>
                        </div>
                    </div>

                    <?php if ($group['my_role'] === 'member'): ?>
                    <div class="owner-contact">
                        <h4>Contact Group Owner</h4>
                        <div class="contact-grid">
                            <div class="contact-item">Owner: <b><?php echo htmlspecialchars($group['owner_name']); ?></b></div>
                            <div class="contact-item">Email: <b><?php echo htmlspecialchars($group['owner_email']); ?></b></div>
                            <div class="contact-item">Phone: <b><?php echo htmlspecialchars($group['owner_phone'] ?: 'N/A'); ?></b></div>
                        </div>
                    </div>
                    <?php endif; ?>



                    <?php if ($group['my_role'] === 'member'): ?>
                    <div style="display: flex; justify-content: flex-end;">
                        <?php if ($group['scheduled_leave_date']): ?>
                            <span class="badge-leave-scheduled">
                                🚪 Leaving <?php echo date('M d, Y', strtotime($group['scheduled_leave_date'])); ?>
                            </span>
                        <?php else: ?>
                            <a href="leave_group.php?group_id=<?php echo $group['group_id']; ?>"
                               class="btn-leave"
                               onclick="return confirm('Schedule your leave from this group? You will stay active until the next billing date.')">🚪 Leave Group</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="review-section">
                        <?php if ($group['my_role'] === 'owner'): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="background: <?php echo htmlspecialchars($group['brand_color']); ?>20; color: <?php echo htmlspecialchars($group['brand_color']); ?>; padding: 4px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border: 1px solid <?php echo htmlspecialchars($group['brand_color']); ?>40;">👑 My Group (Owner)</span>
                                <a href="../owner/group_details.php?group_id=<?php echo $group['group_id']; ?>" style="font-size: 0.85rem; color: #8888aa; text-decoration: underline;">Manage Group</a>
                            </div>
                        <?php elseif ($group['my_review_id']): ?>
                            <span style="color: #22c55e; font-size: 0.85rem; font-weight: 600;">✅ Review Submitted</span>
                        <?php else: ?>
                            <button class="btn-review" onclick="openReviewModal(<?php echo $group['group_id']; ?>, <?php echo $group['owner_id']; ?>, '<?php echo addslashes($group['owner_name']); ?>')">
                                ⭐ Review Owner
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($group['my_role'] === 'member'):
                        $gid   = (int)$group['group_id'];
                        $brand = htmlspecialchars($group['brand_color']);
                        $notifs_for_group = $group_notifs[$gid] ?? [];
                        $recs_for_group   = $group_recs[$gid] ?? [];
                        $chats_for_group  = $group_chats[$gid] ?? [];
                    ?>
                    <!-- Feature 6, 7 & 8: Tabs -->
                    <div class="card-tabs" style="--brand: <?php echo $brand; ?>">
                        <div class="tab-row">
                            <button class="tab-btn active" onclick="switchCardTab(<?php echo $gid; ?>, 'notif', this)">📢 Notifications</button>
                            <button class="tab-btn" onclick="switchCardTab(<?php echo $gid; ?>, 'rec', this)">🎬 Recommendations</button>
                            <button class="tab-btn" onclick="switchCardTab(<?php echo $gid; ?>, 'chat', this); startChat(<?php echo $gid; ?>)">💬 Group Chat</button>
                        </div>

                        <!-- Notifications pane -->
                        <div class="tab-pane active" id="ctab-notif-<?php echo $gid; ?>">
                            <?php if (empty($notifs_for_group)): ?>
                                <div class="notif-empty-msg">No notifications from your group owner yet.</div>
                            <?php else: ?>
                                <?php foreach ($notifs_for_group as $n): ?>
                                <div class="notif-item" style="--brand: <?php echo $brand; ?>">
                                    <p><?php echo nl2br(htmlspecialchars($n['message'])); ?></p>
                                    <div class="notif-item-meta">🕐 <?php echo date('M d, Y g:i A', strtotime($n['created_at'])); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Recommendations pane -->
                        <div class="tab-pane" id="ctab-rec-<?php echo $gid; ?>">
                            <!-- Post form -->
                            <div class="rec-form">
                                <h4>➕ Recommend a Movie / Show</h4>
                                <div id="rec-alert-<?php echo $gid; ?>" class="rec-alert"></div>
                                <div class="rec-grid">
                                    <div class="rec-field">
                                        <label>Title *</label>
                                        <input type="text" id="rt-<?php echo $gid; ?>" placeholder="movie na khuje porte bosh">
                                    </div>
                                    <div class="rec-field">
                                        <label>Genre</label>
                                        <select id="rg-<?php echo $gid; ?>">
                                            <option value="">— Select —</option>
                                            <option>Action</option><option>Comedy</option><option>Drama</option>
                                            <option>Horror</option><option>Sci-Fi</option><option>Thriller</option>
                                            <option>Romance</option><option>Documentary</option><option>Animation</option><option>Other</option>
                                        </select>
                                    </div>
                                    <div class="rec-field">
                                        <label>Available On</label>
                                        <input type="text" id="rp-<?php echo $gid; ?>" placeholder="torrentbd">
                                    </div>
                                    <div class="rec-field">
                                        <label>Why watch it?</label>
                                        <textarea id="rd-<?php echo $gid; ?>" placeholder="netflix niye bhab barse?"></textarea>
                                    </div>
                                </div>
                                <button class="btn-rec-submit" onclick="postRec(<?php echo $gid; ?>)">Post Recommendation</button>
                            </div>

                            <!-- Recommendations list -->
                            <div id="rec-list-<?php echo $gid; ?>">
                                <?php if (empty($recs_for_group)): ?>
                                    <div class="notif-empty-msg" id="rec-empty-<?php echo $gid; ?>">No recommendations yet. Be the first!</div>
                                <?php else: ?>
                                    <?php foreach ($recs_for_group as $r): ?>
                                    <div class="rec-card <?php echo $r['recommended_by'] == $user_id ? 'mine' : ''; ?>">
                                        <span class="rec-card-icon">🎬</span>
                                        <div class="rec-card-body">
                                            <div class="rec-title"><?php echo htmlspecialchars($r['title']); ?></div>
                                            <div class="rec-badges">
                                                <?php if ($r['genre']): ?><span class="badge-pill badge-genre"><?php echo htmlspecialchars($r['genre']); ?></span><?php endif; ?>
                                                <?php if ($r['platform_hint']): ?><span class="badge-pill badge-platform">📺 <?php echo htmlspecialchars($r['platform_hint']); ?></span><?php endif; ?>
                                            </div>
                                            <?php if ($r['description']): ?><div class="rec-desc"><?php echo nl2br(htmlspecialchars($r['description'])); ?></div><?php endif; ?>
                                            <div class="rec-meta">By <strong><?php echo htmlspecialchars($r['recommender_name']); ?></strong> · <?php echo date('M d, Y', strtotime($r['created_at'])); ?><?php echo $r['recommended_by'] == $user_id ? ' <em>(you)</em>' : ''; ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Chat pane -->
                        <div class="tab-pane chat-pane" id="ctab-chat-<?php echo $gid; ?>">
                            <div class="chat-messages" id="chat-msgs-<?php echo $gid; ?>">
                                <?php if (empty($chats_for_group)): ?>
                                    <div class="chat-empty" id="chat-empty-<?php echo $gid; ?>">No messages yet. Say hello! 👋</div>
                                <?php else: ?>
                                    <?php foreach ($chats_for_group as $cm):
                                        $initials = strtoupper(substr($cm['name'], 0, 1));
                                        $is_mine  = $cm['user_id'] == $user_id;
                                    ?>
                                    <div class="chat-msg <?php echo $is_mine ? 'mine' : ''; ?>" id="cmsg-<?php echo $cm['message_id']; ?>">
                                        <div class="chat-avatar"><?php echo $initials; ?></div>
                                        <div class="chat-bubble-wrap">
                                            <div class="chat-name"><?php echo $is_mine ? 'You' : htmlspecialchars($cm['name']); ?></div>
                                            <div class="chat-bubble"><?php echo nl2br(htmlspecialchars($cm['message'])); ?></div>
                                            <div class="chat-time"><?php echo date('M d, g:i A', strtotime($cm['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="chat-compose">
                                <textarea class="chat-input" id="chat-input-<?php echo $gid; ?>" placeholder="Type a message..." rows="1"
                                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChat(<?php echo $gid; ?>);}"></textarea>
                                <button class="chat-send-btn" onclick="sendChat(<?php echo $gid; ?>)">Send</button>
                            </div>
                        </div>

                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($my_waitlist)): ?>
            <h2 class="section-title" style="margin-top: 3rem;"><span>⏳</span> Pending Requests</h2>
            <?php foreach ($my_waitlist as $item): ?>
                <div class="waitlist-item">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span style="font-size: 1.5rem;"><?php echo getPlatformIcon($item['platform_name']); ?></span>
                        <div>
                            <div style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($item['group_name']); ?></div>
                            <div style="font-size: 0.8rem; color: #8888aa;"><?php echo htmlspecialchars($item['platform_name']); ?></div>
                        </div>
                    </div>
                    <span style="font-size: 0.85rem; color: #eab308; font-weight: 600;">WAITING FOR APPROVAL</span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Review Owner</h2>
            <p style="color: #8888aa; font-size: 0.9rem;">How was your experience sharing with this owner?</p>
            
            <form action="submit_review.php" method="POST">
                <input type="hidden" id="modalGroupId" name="group_id">
                <input type="hidden" id="modalRevieweeId" name="reviewee_id">
                <input type="hidden" name="role" value="member">

                <div class="rating-input">
                    <input type="radio" name="rating" value="5" id="star5" class="star-radio" required><label for="star5" class="star-label">★</label>
                    <input type="radio" name="rating" value="4" id="star4" class="star-radio"><label for="star4" class="star-label">★</label>
                    <input type="radio" name="rating" value="3" id="star3" class="star-radio"><label for="star3" class="star-label">★</label>
                    <input type="radio" name="rating" value="2" id="star2" class="star-radio"><label for="star2" class="star-label">★</label>
                    <input type="radio" name="rating" value="1" id="star1" class="star-radio"><label for="star1" class="star-label">★</label>
                </div>

                <textarea name="comment" class="review-text" placeholder="Share your feedback (payment discipline, communication, etc.)"></textarea>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-cancel" onclick="closeReviewModal()" style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); background: transparent; color: #fff; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-submit" style="flex: 2;">Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReviewModal(groupId, ownerId, ownerName) {
            document.getElementById('modalGroupId').value = groupId;
            document.getElementById('modalRevieweeId').value = ownerId;
            document.getElementById('modalTitle').textContent = 'Review ' + ownerName;
            document.getElementById('reviewModal').style.display = 'flex';
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
        }

        // Close on outside click
        window.onclick = function(event) {
            if (event.target == document.getElementById('reviewModal')) closeReviewModal();
        }

        // ---- Feature 6 & 7: Tab switching ----
        function switchCardTab(gid, tab, btn) {
            const card = btn.closest('.card-tabs');
            card.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            card.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('ctab-' + tab + '-' + gid).classList.add('active');
        }

        function showRecAlert(gid, msg, type) {
            const el = document.getElementById('rec-alert-' + gid);
            el.className = 'rec-alert rec-alert-' + type;
            el.textContent = msg;
            el.style.display = 'block';
            setTimeout(() => { el.style.display = 'none'; }, 4000);
        }

        function postRec(gid) {
            const title    = document.getElementById('rt-' + gid).value.trim();
            const genre    = document.getElementById('rg-' + gid).value;
            const platform = document.getElementById('rp-' + gid).value.trim();
            const desc     = document.getElementById('rd-' + gid).value.trim();
            if (!title) { showRecAlert(gid, 'Please enter a title.', 'error'); return; }
            const btn = event.target;
            btn.textContent = 'Posting...';
            btn.disabled = true;
            fetch('add_recommendation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `group_id=${gid}&title=${encodeURIComponent(title)}&genre=${encodeURIComponent(genre)}&platform_hint=${encodeURIComponent(platform)}&description=${encodeURIComponent(desc)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showRecAlert(gid, '✅ Recommendation posted!', 'success');
                    const list = document.getElementById('rec-list-' + gid);
                    const empty = document.getElementById('rec-empty-' + gid);
                    if (empty) empty.remove();
                    const card = document.createElement('div');
                    card.className = 'rec-card mine';
                    card.innerHTML = `
                        <span class="rec-card-icon">🎬</span>
                        <div class="rec-card-body">
                            <div class="rec-title">${escHtml(title)}</div>
                            <div class="rec-badges">
                                ${genre ? `<span class="badge-pill badge-genre">${escHtml(genre)}</span>` : ''}
                                ${platform ? `<span class="badge-pill badge-platform">📺 ${escHtml(platform)}</span>` : ''}
                            </div>
                            ${desc ? `<div class="rec-desc">${escHtml(desc)}</div>` : ''}
                            <div class="rec-meta">By <strong>You</strong> · Just now</div>
                        </div>`;
                    list.prepend(card);
                    document.getElementById('rt-' + gid).value = '';
                    document.getElementById('rg-' + gid).value = '';
                    document.getElementById('rp-' + gid).value = '';
                    document.getElementById('rd-' + gid).value = '';
                } else {
                    showRecAlert(gid, data.error || 'Failed to post.', 'error');
                }
            })
            .catch(() => showRecAlert(gid, 'Network error.', 'error'))
            .finally(() => { btn.textContent = 'Post Recommendation'; btn.disabled = false; });
        }

        function escHtml(s) {
            return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // ---- Feature 8: Group Chat ----
        const chatPollers = {}; // tracks setInterval per group
        const lastMsgIds  = {}; // tracks last seen message_id per group

        function startChat(gid) {
            // Scroll to bottom on open
            setTimeout(() => scrollChatBottom(gid), 50);
            // Start polling if not already
            if (chatPollers[gid]) return;
            pollChat(gid); // immediate first poll
            chatPollers[gid] = setInterval(() => pollChat(gid), 4000);
        }

        function scrollChatBottom(gid) {
            const box = document.getElementById('chat-msgs-' + gid);
            if (box) box.scrollTop = box.scrollHeight;
        }

        function pollChat(gid) {
            const lastId = lastMsgIds[gid] || 0;
            fetch(`chat_messages.php?group_id=${gid}&after=${lastId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.messages.length) return;
                    const box = document.getElementById('chat-msgs-' + gid);
                    const empty = document.getElementById('chat-empty-' + gid);
                    if (empty) empty.remove();
                    const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;
                    data.messages.forEach(m => {
                        if (document.getElementById('cmsg-' + m.message_id)) return; // skip if already rendered
                        const mine = m.is_mine;
                        const div = document.createElement('div');
                        div.className = 'chat-msg' + (mine ? ' mine' : '');
                        div.id = 'cmsg-' + m.message_id;
                        div.innerHTML = `
                            <div class="chat-avatar">${escHtml(m.initials)}</div>
                            <div class="chat-bubble-wrap">
                                <div class="chat-name">${mine ? 'You' : escHtml(m.name)}</div>
                                <div class="chat-bubble">${escHtml(m.message).replace(/\n/g,'<br>')}</div>
                                <div class="chat-time">${escHtml(m.created_at)}</div>
                            </div>`;
                        box.appendChild(div);
                        lastMsgIds[gid] = m.message_id;
                    });
                    if (atBottom) scrollChatBottom(gid);
                })
                .catch(() => {}); // silent fail on poll errors
        }

        function sendChat(gid) {
            const input = document.getElementById('chat-input-' + gid);
            const msg   = input.value.trim();
            if (!msg) return;
            const btn = input.nextElementSibling;
            btn.disabled = true;
            input.disabled = true;
            fetch('send_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `group_id=${gid}&message=${encodeURIComponent(msg)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    pollChat(gid); // fetch immediately after sending
                } else {
                    alert(data.error || 'Failed to send message.');
                }
            })
            .catch(() => alert('Network error.'))
            .finally(() => { btn.disabled = false; input.disabled = false; input.focus(); });
        }
    </script>
</body>
</html>
