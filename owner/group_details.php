<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/icons.php';
require_once __DIR__ . '/../assets/includes/security.php';
require_once __DIR__ . '/../assets/includes/membership.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$group_id) {
    header("Location: dashboard.php");
    exit();
}

// Apply any leaves that reached their scheduled date
process_due_leaves($conn);

// Fetch Group Details
$group_query = "
    SELECT sg.*, p.platform_name, p.brand_color, u.name as owner_name, u.email as owner_email, u.phone as owner_phone
    FROM subscription_group sg
    JOIN platforms p ON sg.platform_id = p.platform_id
    JOIN users u ON sg.owner_id = u.user_id
    WHERE sg.group_id = ? AND sg.owner_id = ?
";
$stmt = mysqli_prepare($conn, $group_query);
mysqli_stmt_bind_param($stmt, "ii", $group_id, $owner_id);
mysqli_stmt_execute($stmt);
$group_res = mysqli_stmt_get_result($stmt);

if (!$group_res || mysqli_num_rows($group_res) === 0) {
    header("Location: dashboard.php");
    exit();
}
$group = mysqli_fetch_assoc($group_res);
mysqli_stmt_close($stmt);

// Fetch Members
$active_members = [];
$waitlisted_members = [];
$members_query = "
    SELECT gm.id as membership_id, gm.payment_status, gm.membership_status, gm.joined_at, u.user_id, u.name, u.email, u.phone,
           (SELECT AVG(rating) FROM reviews WHERE reviewee_id = u.user_id AND reviewer_role = 'owner') as member_rating,
           (SELECT COUNT(*) FROM reviews WHERE reviewee_id = u.user_id AND reviewer_role = 'owner') as review_count,
           (SELECT review_id FROM reviews WHERE group_id = gm.group_id AND reviewer_id = ? AND reviewee_id = u.user_id AND reviewer_role = 'owner') as owner_review_id
    FROM group_members gm
    JOIN users u ON gm.user_id = u.user_id
    WHERE gm.group_id = ?
    ORDER BY gm.joined_at ASC
";
$stmt = mysqli_prepare($conn, $members_query);
mysqli_stmt_bind_param($stmt, "ii", $owner_id, $group_id);
mysqli_stmt_execute($stmt);
$members_res = mysqli_stmt_get_result($stmt);
if ($members_res) {
    while ($row = mysqli_fetch_assoc($members_res)) {
        if ($row['membership_status'] === 'active') {
            $active_members[] = $row;
        } elseif ($row['membership_status'] === 'waitlisted') {
            $waitlisted_members[] = $row;
        }
    }
}
mysqli_stmt_close($stmt);

// Fetch Members Scheduled to Leave
$leaving_members = [];
$leaving_query = "
    SELECT gm.id as membership_id, gm.scheduled_leave_date, u.user_id, u.name, u.email, u.phone
    FROM group_members gm
    JOIN users u ON gm.user_id = u.user_id
    WHERE gm.group_id = ? AND gm.membership_status = 'active' AND gm.scheduled_leave_date IS NOT NULL
    ORDER BY gm.scheduled_leave_date ASC
";
$stmt = mysqli_prepare($conn, $leaving_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$leaving_res = mysqli_stmt_get_result($stmt);
if ($leaving_res) {
    while ($row = mysqli_fetch_assoc($leaving_res)) {
        $leaving_members[] = $row;
    }
}
mysqli_stmt_close($stmt);

$total_joined = count($active_members) + 1; // Including owner
$total_allowed = $group['max_members'];
$is_accepting = $total_joined < $total_allowed;

// Billing Window Calculation
$billing_day = (int) date('j', strtotime($group['validity_start']));
$today       = new DateTime();
$today->setTime(0, 0, 0);

$today_day   = (int) $today->format('j');

$last_billing_date = clone $today;
if ($today_day < $billing_day) {
    $last_billing_date->modify('first day of last month');
    $last_billing_date->setDate((int)$last_billing_date->format('Y'), (int)$last_billing_date->format('n'), $billing_day);
} else {
    $last_billing_date->setDate((int)$today->format('Y'), (int)$today->format('n'), $billing_day);
}

$days_since_billing = $today->diff($last_billing_date)->days;
$is_accepting_window = ($days_since_billing <= 7 && $today >= $last_billing_date);

$next_billing_display = '';
if (!$is_accepting_window) {
    $next = clone $today;
    if ($today_day < $billing_day) {
        $next->setDate((int)$today->format('Y'), (int)$today->format('n'), $billing_day);
    } else {
        $next->modify('first day of next month');
        $next->setDate((int)$next->format('Y'), (int)$next->format('n'), $billing_day);
    }
    $next_billing_display = $next->format('M d');
}

$brand_color = htmlspecialchars($group['brand_color']);

// Feature 6: Fetch existing notifications for this group
$notifs = [];
$n_stmt = mysqli_prepare($conn, "
    SELECT notification_id, message, created_at
    FROM group_notifications
    WHERE group_id = ?
    ORDER BY created_at DESC
");
mysqli_stmt_bind_param($n_stmt, "i", $group_id);
mysqli_stmt_execute($n_stmt);
$n_res = mysqli_stmt_get_result($n_stmt);
while ($row = mysqli_fetch_assoc($n_res)) {
    $notifs[] = $row;
}
mysqli_stmt_close($n_stmt);

// Feature 8: Fetch the latest group chat messages
$group_chats = [];
$last_chat_id = 0;
$chat_stmt = mysqli_prepare($conn, "
    SELECT gc.message_id, gc.message, gc.created_at, gc.user_id, u.name
    FROM group_chat gc
    JOIN users u ON gc.user_id = u.user_id
    WHERE gc.group_id = ?
    ORDER BY gc.created_at ASC
    LIMIT 50
");
mysqli_stmt_bind_param($chat_stmt, "i", $group_id);
mysqli_stmt_execute($chat_stmt);
$chat_res = mysqli_stmt_get_result($chat_stmt);
while ($row = mysqli_fetch_assoc($chat_res)) {
    $group_chats[] = $row;
}
mysqli_stmt_close($chat_stmt);
if (!empty($group_chats)) {
    $last_chat = end($group_chats);
    $last_chat_id = (int)$last_chat['message_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Details - <?php echo htmlspecialchars($group['group_name']); ?> | Splitflix</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .details-container { width: 95%; max-width: 1600px; margin: 2rem auto; padding: 0 1rem; }
        .dashboard-layout { display: grid; grid-template-columns: 1.2fr 2.5fr; gap: 2.5rem; align-items: start; }
        .main-column { order: 2; }
        .side-column { order: 1; }
        @media (max-width: 1000px) { 
            .dashboard-layout { grid-template-columns: 1fr; } 
            .main-column { order: 1; }
            .side-column { order: 2; }
        }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #8888aa; text-decoration: none; font-size: 0.95rem; font-weight: 500; margin-bottom: 2rem; transition: color 0.2s; }
        .back-link:hover { color: #fff; }

        .group-header-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.03), rgba(0, 0, 0, 0.2));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px; padding: 2rem; display: flex; align-items: flex-start; gap: 2rem;
            margin-bottom: 2.5rem; position: relative; overflow: hidden;
        }
        .group-header-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: <?php echo $brand_color; ?>;
        }
        .header-logo {
            width: 80px; height: 80px; background: rgba(255,255,255,0.05); border-radius: 20px;
            display: flex; align-items: center; justify-content: center; color: <?php echo $brand_color; ?>;
            font-size: 3rem; flex-shrink: 0;
        }
        .header-info h1 { font-size: 2rem; margin-bottom: 8px; color: #f0f0f5; }
        .header-info p { color: #8888aa; margin-bottom: 12px; font-size: 1rem; }
        .plan-badge { display: inline-block; background: rgba(255, 255, 255, 0.05); padding: 8px 14px; border-radius: 8px; font-size: 0.9rem; color: #ccccee; border-left: 3px solid <?php echo $brand_color; ?>; position: relative; }

        .btn-edit-icon { background: none; border: none; color: #8888aa; cursor: pointer; margin-left: 8px; font-size: 0.9rem; transition: color 0.2s; }
        .btn-edit-icon:hover { color: #fff; }
        #plan-edit-form { display: none; margin-top: 10px; width: 100%; max-width: 500px; }
        #plan-edit-form textarea { width: 100%; min-height: 80px; padding: 10px; background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f0f0f5; font-family: inherit; margin-bottom: 8px; resize: vertical; }
        .btn-save, .btn-cancel, .btn-action { padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; }
        .btn-save, .btn-action { background: <?php echo $brand_color; ?>; color: #fff; }
        .btn-cancel { background: rgba(255, 255, 255, 0.1); color: #ccc; margin-left: 8px; transition: background 0.2s; }
        .btn-cancel:hover { background: rgba(255, 255, 255, 0.15); }
        .btn-action:hover { filter: brightness(1.1); }
        .btn-action:disabled { opacity: 0.5; cursor: not-allowed; }

        .side-panel-accordion {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05), rgba(0, 0, 0, 0.4));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        .side-panel-accordion summary {
            padding: 1.25rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: #f0f0f5;
            cursor: pointer;
            list-style: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid transparent;
            border-left: 4px solid <?php echo $brand_color; ?>;
            margin: 0;
            transition: background 0.3s;
        }
        .side-panel-accordion summary:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        .side-panel-accordion summary::-webkit-details-marker { display: none; }
        .side-panel-accordion summary::after {
            content: '▼';
            font-size: 0.8rem;
            color: #8888aa;
            transition: transform 0.3s;
        }
        .side-panel-accordion[open] summary {
            border-bottom-color: rgba(255, 255, 255, 0.05);
        }
        .side-panel-accordion[open] summary::after {
            transform: rotate(-180deg);
        }
        .side-panel-content {
            padding: 1rem 1.5rem;
        }
        .side-panel-accordion th,
        .side-panel-accordion td {
            padding: 0.9rem 1rem;
        }
        .side-panel-accordion .btn-action,
        .side-panel-accordion .cancel-leave-btn {
            white-space: nowrap;
        }

        .stats-panel { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 3rem; }
        .stat-card { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); padding: 1.5rem; border-radius: 12px; display: flex; flex-direction: column; }
        .stat-label { font-size: 0.85rem; color: #8888aa; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #fff; }
        .stat-badge-accepting { color: #4ade80; }
        .stat-badge-full { color: #f87171; }

        .members-table-container { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px; overflow: hidden; margin-bottom: 3rem; }
        .members-table-header { padding: 1.5rem 2rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05); display: flex; justify-content: space-between; align-items: center;}
        .members-table-header h2 { font-size: 1.25rem; color: #f0f0f5; }
        .members-table-header .badge { background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem 2rem; font-size: 0.85rem; color: #8888aa; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); background: rgba(0, 0, 0, 0.2); }
        td { padding: 1.25rem 2rem; border-bottom: 1px solid rgba(255, 255, 255, 0.02); font-size: 0.95rem; color: #e0e0e0; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: rgba(255, 255, 255, 0.01); }
        .member-meta { font-size: 0.85rem; color: #8888aa; margin-top: 4px; }
        
        .status-select { appearance: none; background-color: rgba(255, 255, 255, 0.05); color: #f0f0f5; border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 30px 8px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238888aa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 10px center; background-size: 1em; transition: all 0.2s; }
        .status-select:focus { outline: none; border-color: <?php echo $brand_color; ?>; }
        .status-select option { background: #12121a; }
        .status-cleared { border-color: rgba(34, 197, 94, 0.5); color: #4ade80; }
        .status-uncleared { border-color: rgba(239, 68, 68, 0.5); color: #f87171; }
        
        .cancel-leave-btn {
            background: rgba(255, 255, 255, 0.05);
            color: #ccccee;
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .cancel-leave-btn:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.4);
        }

        .empty-members { text-align: center; padding: 4rem 2rem; color: #8888aa; }

        /* ---- Feature 6: Notifications ---- */
        .notif-section { margin-top: 3rem; }
        .notif-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .notif-section-header h2 { font-size: 1.25rem; color: #f0f0f5; }
        .notif-compose { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .notif-compose label { display: block; font-size: 0.82rem; font-weight: 600; color: #8888aa; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 8px; }
        .notif-compose textarea { width: 100%; min-height: 90px; padding: 12px 14px; resize: vertical; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #f0f0f5; font-family: inherit; font-size: 0.95rem; transition: border-color 0.2s; margin-bottom: 12px; box-sizing: border-box; }
        .notif-compose textarea:focus { outline: none; border-color: <?php echo $brand_color; ?>; box-shadow: 0 0 0 3px <?php echo $brand_color; ?>15; }
        .notif-list { display: flex; flex-direction: column; gap: 12px; }
        .notif-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-left: 4px solid <?php echo $brand_color; ?>; border-radius: 10px; padding: 1rem 1.2rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
        .notif-card-body p { color: #d0d0e0; font-size: 0.95rem; line-height: 1.6; white-space: pre-wrap; margin: 0; }
        .notif-card-meta { font-size: 0.8rem; color: #8888aa; margin-top: 8px; }
        .notif-delete-btn { background: none; border: none; color: #8888aa; font-size: 0.85rem; cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: all 0.2s; flex-shrink: 0; }
        .notif-delete-btn:hover { background: rgba(239,68,68,0.1); color: #f87171; }
        .notif-empty { text-align: center; padding: 2rem; color: #8888aa; font-size: 0.9rem; }
        .notif-alert { padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; font-weight: 500; margin-bottom: 10px; display: none; }
        .notif-alert-success { background: rgba(34,197,94,0.12); color: #4ade80; border: 1px solid rgba(34,197,94,0.2); }
        .notif-alert-error { background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
        .leaving-date-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(239, 68, 68, 0.1); color: #f87171;
            padding: 5px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }

        /* ---- Group chat (owner view) ---- */
        .owner-chat {
            display: flex;
            flex-direction: column;
            height: 380px;
        }
        .owner-chat .chat-messages {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 4px 2px 12px 2px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.1) transparent;
        }
        .owner-chat .chat-messages::-webkit-scrollbar { width: 4px; }
        .owner-chat .chat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 99px; }
        .owner-chat .chat-msg { display: flex; gap: 10px; align-items: flex-start; }
        .owner-chat .chat-msg.mine { flex-direction: row-reverse; }
        .owner-chat .chat-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; font-weight: 700; color: #f0f0f5; flex-shrink: 0;
        }
        .owner-chat .chat-bubble-wrap { max-width: 75%; }
        .owner-chat .chat-name { font-size: 0.72rem; color: #8888aa; margin-bottom: 3px; font-weight: 600; }
        .owner-chat .chat-msg.mine .chat-name { text-align: right; }
        .owner-chat .chat-bubble {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.07);
            border-radius: 12px 12px 12px 4px; padding: 8px 12px;
            font-size: 0.9rem; color: #e0e0f0; line-height: 1.45; word-break: break-word;
        }
        .owner-chat .chat-msg.mine .chat-bubble {
            background: <?php echo $brand_color; ?>22;
            border-color: <?php echo $brand_color; ?>44;
            border-radius: 12px 12px 4px 12px;
        }
        .owner-chat .chat-time { font-size: 0.7rem; color: #666688; margin-top: 3px; }
        .owner-chat .chat-msg.mine .chat-time { text-align: right; }
        .owner-chat .chat-empty { text-align: center; color: #8888aa; font-size: 0.88rem; padding: 2rem 0; margin: auto; }
        .owner-chat .chat-compose {
            display: flex; gap: 8px; padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.06); margin-top: 4px;
        }
        .owner-chat .chat-input {
            flex: 1; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px; color: #f0f0f5; padding: 9px 12px;
            font-size: 0.9rem; font-family: inherit; resize: none; height: 40px;
            transition: border-color 0.2s;
        }
        .owner-chat .chat-input:focus { outline: none; border-color: <?php echo $brand_color; ?>; }
        .owner-chat .chat-send-btn {
            background: <?php echo $brand_color; ?>; color: #fff; border: none;
            border-radius: 8px; padding: 0 16px; font-size: 0.88rem;
            font-weight: 700; cursor: pointer; white-space: nowrap;
        }
        .owner-chat .chat-send-btn:hover { filter: brightness(1.1); }
        .owner-chat .chat-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .payment-link-container {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }

        .payment-link-info { flex: 1; }
        .payment-link-info h3 { font-size: 1.1rem; color: #f0f0f5; margin-bottom: 4px; }
        .payment-link-info p { font-size: 0.9rem; color: #8888aa; }

        .payment-input-wrapper {
            display: flex;
            gap: 12px;
            flex: 1.5;
        }

        .payment-input {
            flex: 1;
            padding: 12px 16px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #f0f0f5;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .payment-input:focus { outline: none; border-color: <?php echo $brand_color; ?>; box-shadow: 0 0 0 3px <?php echo $brand_color; ?>15; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: #1a1a24; padding: 2.5rem; border-radius: 20px; width: 100%; max-width: 450px; border: 1px solid rgba(255,255,255,0.1); }
        .rating-input { display: flex; gap: 10px; margin: 1.5rem 0; justify-content: center; flex-direction: row-reverse; }
        .rating-input input { display: none; }
        .rating-input label { font-size: 2rem; cursor: pointer; color: #333; transition: color 0.2s; }
        .rating-input input:checked ~ label, .rating-input label:hover, .rating-input label:hover ~ label { color: #eab308; }
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
            <a href="dashboard.php" class="nav-link active">My Groups</a>
            <a href="../auth/logout.php" class="nav-link">Logout</a>
        </div>
    </nav>

    <main class="details-container">
        <a href="dashboard.php" class="back-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Dashboard
        </a>

        <?php echo flash_render(); ?>

        <?php if (!empty($waitlisted_members)): ?>
            <div class="flash" style="background: rgba(234, 179, 8, 0.12); color: #facc15; border-color: rgba(234, 179, 8, 0.35);">
                <strong><?php echo count($waitlisted_members); ?> pending join request<?php echo count($waitlisted_members) === 1 ? '' : 's'; ?>.</strong>
                <a href="#waitlist-queue" style="color: #fff; text-decoration: underline; margin-left: 6px;">Open the waitlist queue</a> to accept or review the request.
            </div>
        <?php endif; ?>

        <div class="dashboard-layout">
            <div class="main-column">
                <!-- Header Card -->
        <div class="group-header-card">
            <div class="header-logo">
                <?php echo getPlatformIcon($group['platform_name']); ?>
            </div>
            <div class="header-info">
                <h1><?php echo htmlspecialchars($group['group_name']); ?></h1>
                <p><?php echo htmlspecialchars($group['platform_name']); ?> • Valid until <?php echo htmlspecialchars(date('M d, Y', strtotime($group['validity_end']))); ?></p>
                <div class="plan-badge" id="plan-display">
                    <strong>Plan:</strong> <span id="plan-text"><?php echo nl2br(htmlspecialchars($group['plan_description'])); ?></span>
                    <button onclick="toggleEdit()" class="btn-edit-icon" title="Edit Description">✏️</button>
                </div>
                <div id="plan-edit-form">
                    <textarea id="plan-input"><?php echo htmlspecialchars($group['plan_description']); ?></textarea>
                    <div>
                        <button onclick="saveDescription()" class="btn-save">Save</button>
                        <button onclick="toggleEdit()" class="btn-cancel">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Panel -->
        <div class="stats-panel">
            <div class="stat-card">
                <span class="stat-label">Group Status</span>
                <span class="stat-value <?php echo $is_accepting ? 'stat-badge-accepting' : 'stat-badge-full'; ?>">
                    <?php echo $is_accepting ? 'Accepting' : 'Full / Not Accepting'; ?>
                </span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Total Allowed</span>
                <span class="stat-value"><?php echo $total_allowed; ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Currently Joined</span>
                <span class="stat-value"><?php echo $total_joined; ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Price Per Member</span>
                <span class="stat-value">৳<?php echo number_format($group['cost_per_member'], 2); ?></span>
            </div>
        </div>
        
        <!-- Payment Collection Link Section -->
        <div class="payment-link-container">
            <div class="payment-link-info">
                <h3>Payment Collection Link</h3>
            </div>
            <div class="payment-input-wrapper">
                <input type="url" id="payment-link-input" class="payment-input" 
                       placeholder="https://forms.gle/..." 
                       value="<?php echo htmlspecialchars($group['payment_form_link'] ?? ''); ?>">
                <button onclick="savePaymentLink()" class="btn-action">Save Link</button>
            </div>
        </div>

        <!-- Members Breakdown Table -->
        <div class="members-table-container">
            <div class="members-table-header">
                <h2>Active Members Breakdown</h2>
                <span class="badge"><?php echo $total_joined; ?> Active</span>
            </div>
            <?php if (empty($active_members)): ?>
                <div class="empty-members">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                    <h3>No active members yet</h3>
                    <p>When users join your group, they will appear here.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Contact</th>
                            <th>Joined Date</th>
                            <th>Payment Status (This Month)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Owner Row -->
                        <tr style="background: rgba(255, 255, 255, 0.03);">
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($group['owner_name']); ?></div>
                                <div class="member-meta" style="color: <?php echo $brand_color; ?>; font-weight: 700;">OWNER</div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($group['owner_email']); ?></div>
                                <?php if (!empty($group['owner_phone'])): ?>
                                    <div class="member-meta">📞 <?php echo htmlspecialchars($group['owner_phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($group['created_at'])); ?>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(255,255,255,0.1); color: #fff;">N/A (Group Owner)</span>
                            </td>
                        </tr>

                        <?php foreach ($active_members as $member): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($member['name']); ?></div>
                                    <div class="member-meta">
                                        <?php if ($member['member_rating']): ?>
                                            <span style="color: #eab308;">⭐ <?php echo round($member['member_rating'], 1); ?></span>
                                        <?php endif; ?>
                                        <?php if ($member['owner_review_id']): ?>
                                            <span style="color: #22c55e; margin-left: 8px;">✓ Reviewed</span>
                                        <?php else: ?>
                                            <button type="button"
                                                    data-member-name="<?php echo htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    onclick="openOwnerReviewModal(<?php echo (int)$group_id; ?>, <?php echo (int)$member['user_id']; ?>, this)"
                                                    style="background: none; border: none; color: #eab308; cursor: pointer; padding: 0; font-size: 0.8rem; text-decoration: underline; margin-left: 8px;">Review Member</button>
                                        <?php endif; ?>
                                        <button type="button"
                                                data-member-name="<?php echo htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="removeMember(<?php echo (int)$member['membership_id']; ?>, this)"
                                                style="background: none; border: none; color: #ff4444; cursor: pointer; padding: 0; font-size: 0.8rem; text-decoration: underline; margin-left: 12px;">Remove</button>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($member['email']); ?></div>
                                    <?php if (!empty($member['phone'])): ?>
                                        <div class="member-meta">📞 <?php echo htmlspecialchars($member['phone']); ?></div>
                                    <?php else: ?>
                                        <div class="member-meta">No phone provided</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($member['joined_at'])); ?>
                                </td>
                                <td>
                                    <select class="status-select <?php echo $member['payment_status'] === 'cleared' ? 'status-cleared' : 'status-uncleared'; ?>" 
                                            onchange="updatePaymentStatus(<?php echo $member['membership_id']; ?>, this)">
                                        <option value="uncleared" <?php echo $member['payment_status'] === 'uncleared' ? 'selected' : ''; ?>>Uncleared</option>
                                        <option value="cleared" <?php echo $member['payment_status'] === 'cleared' ? 'selected' : ''; ?>>Cleared</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

            </div> <!-- End Main Column -->

            <div class="side-column">
                <!-- Waitlist Queue Accordion -->
                <details class="side-panel-accordion" id="waitlist-queue" <?php echo !empty($waitlisted_members) ? 'open' : ''; ?>>
                    <summary>Waitlist Queue <span class="badge" style="background: rgba(255,255,255,0.1); color: #fff; padding: 2px 8px; border-radius: 12px; margin-left: 10px;"><?php echo count($waitlisted_members); ?> Waiting</span></summary>
                    <div class="side-panel-content" style="padding: 0;">
                        <div class="members-table-container" style="border: none; margin: 0; border-radius: 0;">
                            <?php if (empty($waitlisted_members)): ?>
                                <div class="empty-members" style="padding: 2rem;">
                                    <p style="margin: 0;">The waitlist is currently empty.</p>
                                </div>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Requested By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($waitlisted_members as $index => $member):
                                            $m_rating = $member['member_rating'] ? round($member['member_rating'], 1) : null;
                                        ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight: 600;">
                                                        <span style="color: #8888aa;">#<?php echo $index + 1; ?></span>
                                                        <?php echo htmlspecialchars($member['name']); ?>
                                                    </div>
                                                    <div class="member-meta">
                                                        <?php if ($m_rating): ?>
                                                            <span style="color: #eab308;">⭐ <?php echo $m_rating; ?></span>
                                                            <span style="font-size: 0.75rem;">(<?php echo (int)$member['review_count']; ?> reviews)</span>
                                                        <?php else: ?>
                                                            <span style="color: #666; font-size: 0.75rem;">New member (no reviews)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="member-meta"><?php echo htmlspecialchars($member['email']); ?></div>
                                                    <div class="member-meta">🕐 <?php echo date('M d, g:i A', strtotime($member['joined_at'])); ?></div>
                                                </td>
                                                <td>
                                                    <?php if (!$is_accepting_window): ?>
                                                        <button class="btn-action" style="background: rgba(255,255,255,0.1); color: #888; cursor: not-allowed;" disabled title="Can only accept within 7 days of the billing date">
                                                            Wait until <?php echo $next_billing_display; ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-action"
                                                                onclick="acceptMember(<?php echo (int)$member['membership_id']; ?>, this)"
                                                                <?php echo !$is_accepting ? 'disabled title="Cannot accept, group is full"' : ''; ?>>
                                                            Accept
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>

        <!-- Leaving Queue -->
                <details class="side-panel-accordion" <?php echo !empty($leaving_members) ? 'open' : ''; ?>>
                    <summary>🚪 Leaving Queue <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171; padding: 2px 8px; border-radius: 12px; margin-left: 10px;"><?php echo count($leaving_members); ?> Scheduled</span></summary>
                    <div class="side-panel-content" style="padding: 0;">
                        <div class="members-table-container" style="border: none; margin: 0; border-radius: 0;">
                            <?php if (empty($leaving_members)): ?>
                                <div class="empty-members" style="padding: 2rem;">
                                    <p style="margin: 0;">No members have scheduled to leave.</p>
                                </div>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Member</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leaving_members as $lm): ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($lm['name']); ?></div>
                                                    <div class="member-meta"><?php echo htmlspecialchars($lm['email']); ?></div>
                                                    <?php if (!empty($lm['phone'])): ?>
                                                        <div class="member-meta">📞 <?php echo htmlspecialchars($lm['phone']); ?></div>
                                                    <?php endif; ?>
                                                    <span class="leaving-date-badge" style="margin-top: 6px;">
                                                        📅 <?php echo date('M d, Y', strtotime($lm['scheduled_leave_date'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="cancel-leave-btn"
                                                            data-member-name="<?php echo htmlspecialchars($lm['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            onclick="cancelLeave(<?php echo (int)$lm['membership_id']; ?>, this)">
                                                        ✕ Cancel Leave
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>

        <!-- tanvir muhtady feature 6 notification -->
                <details class="side-panel-accordion">
                    <summary>📢 Group Notifications <span class="badge" style="background: rgba(255,255,255,0.1); color: #fff; padding: 2px 8px; border-radius: 12px; margin-left: 10px;"><?php echo count($notifs); ?></span></summary>
                    <div class="side-panel-content">
                        <div class="notif-compose">
                <label>Broadcast a message to your group members</label>
                <div id="notif-alert" class="notif-alert"></div>
                <textarea id="notif-message" placeholder="e.g. time moton taka na dile rog kata hobe"></textarea>
                <button class="btn-action" onclick="postNotification(this)">📣 Post Notification</button>
            </div>
            <div class="notif-list" id="notif-list">
                <?php if (empty($notifs)): ?>
                    <div class="notif-empty" id="notif-empty-state">No notifications posted yet. Members will see your messages here.</div>
                <?php else: ?>
                    <?php foreach ($notifs as $n): ?>
                    <div class="notif-card" id="notif-<?php echo $n['notification_id']; ?>">
                        <div class="notif-card-body">
                            <p><?php echo nl2br(htmlspecialchars($n['message'])); ?></p>
                            <div class="notif-card-meta">🕐 <?php echo date('M d, Y g:i A', strtotime($n['created_at'])); ?></div>
                        </div>
                        <button class="notif-delete-btn" onclick="deleteNotification(<?php echo $n['notification_id']; ?>)">🗑 Delete</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
                        </div>
                </details>

                <!-- Group chat (owner view) -->
                <details class="side-panel-accordion" id="group-chat"
                         ontoggle="if (this.open) startChat(<?php echo (int)$group_id; ?>)">
                    <summary>💬 Group Chat <span class="badge" style="background: rgba(255,255,255,0.1); color: #fff; padding: 2px 8px; border-radius: 12px; margin-left: 10px;"><?php echo count($group_chats); ?> messages</span></summary>
                    <div class="side-panel-content" style="padding: 1rem 1.25rem;">
                        <div class="owner-chat">
                            <div class="chat-messages" id="chat-msgs-<?php echo (int)$group_id; ?>">
                                <?php if (empty($group_chats)): ?>
                                    <div class="chat-empty" id="chat-empty-<?php echo (int)$group_id; ?>">No messages yet. Say hello to your group! 👋</div>
                                <?php else: ?>
                                    <?php foreach ($group_chats as $cm):
                                        $initials = strtoupper(substr($cm['name'], 0, 1));
                                        $is_mine = ((int)$cm['user_id'] === (int)$owner_id);
                                    ?>
                                        <div class="chat-msg <?php echo $is_mine ? 'mine' : ''; ?>" id="cmsg-<?php echo (int)$cm['message_id']; ?>">
                                            <div class="chat-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                            <div class="chat-bubble-wrap">
                                                <div class="chat-name"><?php echo $is_mine ? 'You (owner)' : htmlspecialchars($cm['name']); ?></div>
                                                <div class="chat-bubble"><?php echo nl2br(htmlspecialchars($cm['message'])); ?></div>
                                                <div class="chat-time"><?php echo date('M d, g:i A', strtotime($cm['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="chat-compose">
                                <textarea class="chat-input" id="chat-input-<?php echo (int)$group_id; ?>" placeholder="Message your group..." rows="1"
                                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChat(<?php echo (int)$group_id; ?>);}"></textarea>
                                <button type="button" class="chat-send-btn" onclick="sendChat(<?php echo (int)$group_id; ?>)">Send</button>
                            </div>
                        </div>
                    </div>
                </details>
            </div> <!-- End Side Column -->
        </div> <!-- End Dashboard Layout -->
    </main>

    <!-- Owner Review Modal -->
    <div id="ownerReviewModal" class="modal">
        <div class="modal-content">
            <h2 id="ownerModalTitle">Review Member</h2>
            <p style="color: #8888aa; font-size: 0.9rem; margin-bottom: 1rem;">Review this member's payment discipline and cooperation.</p>
            
            <form action="../user/submit_review.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" id="ownerModalGroupId" name="group_id">
                <input type="hidden" id="ownerModalRevieweeId" name="reviewee_id">
                <input type="hidden" name="role" value="owner">

                <div class="rating-input">
                    <input type="radio" name="rating" value="5" id="ostar5" required><label for="ostar5">★</label>
                    <input type="radio" name="rating" value="4" id="ostar4"><label for="ostar4">★</label>
                    <input type="radio" name="rating" value="3" id="ostar3"><label for="ostar3">★</label>
                    <input type="radio" name="rating" value="2" id="ostar2"><label for="ostar2">★</label>
                    <input type="radio" name="rating" value="1" id="ostar1"><label for="ostar1">★</label>
                </div>

                <textarea name="comment" style="width: 100%; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 1rem; color: #fff; margin-bottom: 1.5rem; resize: none; min-height: 100px;" placeholder="Feedback on member..."></textarea>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="closeOwnerReviewModal()" style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); background: transparent; color: #fff; cursor: pointer;">Cancel</button>
                    <button type="submit" style="flex: 2; padding: 12px; background: #eab308; color: #000; border: none; border-radius: 10px; font-weight: 700; cursor: pointer;">Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
        const chatPollers = {};
        const lastMsgIds = {};
        lastMsgIds[<?php echo (int)$group_id; ?>] = <?php echo (int)$last_chat_id; ?>;

        function openOwnerReviewModal(groupId, memberId, btn) {
            const memberName = btn.dataset.memberName || 'Member';
            document.getElementById('ownerModalGroupId').value = groupId;
            document.getElementById('ownerModalRevieweeId').value = memberId;
            document.getElementById('ownerModalTitle').textContent = 'Review ' + memberName;
            document.getElementById('ownerReviewModal').style.display = 'flex';
        }

        function closeOwnerReviewModal() {
            document.getElementById('ownerReviewModal').style.display = 'none';
        }

        function updatePaymentStatus(membershipId, selectElement) {
            const newStatus = selectElement.value;
            selectElement.className = 'status-select ' + (newStatus === 'cleared' ? 'status-cleared' : 'status-uncleared');
            fetch('update_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}&membership_id=${membershipId}&status=${newStatus}`
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert('Failed to update: ' + (data.error || 'Unknown'));
                    selectElement.value = newStatus === 'cleared' ? 'uncleared' : 'cleared';
                    selectElement.className = 'status-select ' + (selectElement.value === 'cleared' ? 'status-cleared' : 'status-uncleared');
                }
            }).catch(e => alert('Network error'));
        }

        function toggleEdit() {
            const d = document.getElementById('plan-display'), f = document.getElementById('plan-edit-form');
            if (f.style.display === 'none' || f.style.display === '') {
                d.style.display = 'none'; f.style.display = 'block';
            } else {
                d.style.display = 'inline-block'; f.style.display = 'none';
            }
        }

        function saveDescription() {
            const newDesc = document.getElementById('plan-input').value;
            const btn = document.querySelector('.btn-save');
            btn.textContent = 'Saving...'; btn.disabled = true;
            fetch('update_description.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}&group_id=<?php echo (int)$group_id; ?>&description=${encodeURIComponent(newDesc)}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('plan-text').innerHTML = escHtml(newDesc).replace(/\n/g, '<br>');
                    toggleEdit();
                } else alert('Failed: ' + data.error);
            }).finally(() => { btn.textContent = 'Save'; btn.disabled = false; });
        }

        function savePaymentLink() {
            const link = document.getElementById('payment-link-input').value;
            const btn = document.querySelector('.payment-link-container .btn-action');
            const originalText = btn.textContent;
            
            btn.textContent = 'Saving...';
            btn.disabled = true;

            fetch('update_payment_link.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}&group_id=<?php echo (int)$group_id; ?>&payment_link=${encodeURIComponent(link)}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    btn.textContent = 'Saved!';
                    btn.style.background = '#22c55e';
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.style.background = '<?php echo $brand_color; ?>';
                        btn.disabled = false;
                    }, 2000);
                } else {
                    alert('Failed to save link: ' + (data.error || 'Unknown error'));
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(e => {
                alert('Network error');
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }

        function removeMember(membershipId, btn) {
            const memberName = btn.dataset.memberName || 'this member';
            if(!confirm(`Are you sure you want to remove ${memberName} from the group? This will free up their seat.`)) return;
            
            fetch('remove_member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}&membership_id=${membershipId}&group_id=<?php echo (int)$group_id; ?>`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to remove member: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(e => {
                console.error(e);
                alert('Network error');
            });
        }

        function acceptMember(membershipId, btn) {
            btn.textContent = 'Accepting...';
            btn.disabled = true;
            
            fetch('accept_member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}&membership_id=${membershipId}&group_id=<?php echo (int)$group_id; ?>`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.reload(); // Reload to show updated tables and stats
                } else {
                    alert('Failed to accept member: ' + (data.error || 'Unknown error'));
                    btn.textContent = 'Accept';
                    btn.disabled = false;
                }
            })
            .catch(e => {
                console.error(e);
                alert('Network error');
                btn.textContent = 'Accept';
                btn.disabled = false;
            });
        }

        function cancelLeave(membershipId, btn) {
            const memberName = btn.dataset.memberName || 'this member';
            if (!confirm(`Cancel ${memberName}'s scheduled leave? They will remain in the group.`)) return;
            fetch('cancel_leave.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}&membership_id=${membershipId}&group_id=<?php echo (int)$group_id; ?>`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) window.location.reload();
                else alert('Failed: ' + (data.error || 'Unknown error'));
            }).catch(() => alert('Network error'));
        }

        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target == document.getElementById('ownerReviewModal')) closeOwnerReviewModal();
        }

        // tanvir muhtady Feature 6: Notifications 
        function showNotifAlert(msg, type) {
            const el = document.getElementById('notif-alert');
            el.className = 'notif-alert notif-alert-' + type;
            el.textContent = msg;
            el.style.display = 'block';
            setTimeout(() => { el.style.display = 'none'; }, 4000);
        }

        function postNotification(btn) {
            const msg = document.getElementById('notif-message').value.trim();
            if (!msg) { showNotifAlert('Please write a message before posting.', 'error'); return; }
            btn.textContent = 'Posting...';
            btn.disabled = true;
            fetch('post_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}&group_id=<?php echo (int)$group_id; ?>&message=${encodeURIComponent(msg)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotifAlert('✅ Notification posted! Members can now see it.', 'success');
                    document.getElementById('notif-message').value = '';
                    const empty = document.getElementById('notif-empty-state');
                    if (empty) empty.remove();
                    const list = document.getElementById('notif-list');
                    const card = document.createElement('div');
                    card.className = 'notif-card';
                    card.id = 'notif-' + data.notification_id;
                    card.innerHTML = `
                        <div class="notif-card-body">
                            <p>${escHtml(msg).replace(/\n/g,'<br>')}</p>
                            <div class="notif-card-meta">🕐 ${data.created_at}</div>
                        </div>
                        <button class="notif-delete-btn" onclick="deleteNotification(${data.notification_id})">🗑 Delete</button>`;
                    list.prepend(card);
                } else {
                    showNotifAlert(data.error || 'Failed to post notification.', 'error');
                }
            })
            .catch(() => showNotifAlert('Network error.', 'error'))
            .finally(() => { btn.textContent = '📣 Post Notification'; btn.disabled = false; });
        }

        function deleteNotification(id) {
            if (!confirm('Delete this notification? Members will no longer see it.')) return;
            fetch('delete_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}&notification_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('notif-' + id).remove();
                    const list = document.getElementById('notif-list');
                    if (list.children.length === 0) {
                        list.innerHTML = '<div class="notif-empty" id="notif-empty-state">No notifications posted yet.</div>';
                    }
                } else {
                    alert('Failed to delete: ' + (data.error || 'Unknown'));
                }
            });
        }

        // ---- Group chat (owner view) ----
        function startChat(gid) {
            setTimeout(() => scrollChatBottom(gid), 50);
            if (chatPollers[gid]) return;
            pollChat(gid);
            chatPollers[gid] = setInterval(() => pollChat(gid), 4000);
        }

        function scrollChatBottom(gid) {
            const box = document.getElementById('chat-msgs-' + gid);
            if (box) box.scrollTop = box.scrollHeight;
        }

        function pollChat(gid) {
            const lastId = lastMsgIds[gid] || 0;
            fetch(`../user/chat_messages.php?group_id=${gid}&after=${lastId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.messages.length) return;
                    const box = document.getElementById('chat-msgs-' + gid);
                    const empty = document.getElementById('chat-empty-' + gid);
                    if (empty) empty.remove();
                    const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;
                    data.messages.forEach(m => {
                        if (document.getElementById('cmsg-' + m.message_id)) return;
                        const div = document.createElement('div');
                        div.className = 'chat-msg' + (m.is_mine ? ' mine' : '');
                        div.id = 'cmsg-' + m.message_id;
                        div.innerHTML = `
                            <div class="chat-avatar">${escHtml(m.initials)}</div>
                            <div class="chat-bubble-wrap">
                                <div class="chat-name">${m.is_mine ? 'You (owner)' : escHtml(m.name)}</div>
                                <div class="chat-bubble">${escHtml(m.message).replace(/\n/g, '<br>')}</div>
                                <div class="chat-time">${escHtml(m.created_at)}</div>
                            </div>`;
                        box.appendChild(div);
                        lastMsgIds[gid] = m.message_id;
                    });
                    if (atBottom) scrollChatBottom(gid);
                })
                .catch(() => {});
        }

        function sendChat(gid) {
            const input = document.getElementById('chat-input-' + gid);
            const msg = input.value.trim();
            if (!msg) return;
            const btn = input.nextElementSibling;
            btn.disabled = true;
            input.disabled = true;
            fetch('../user/send_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}&group_id=${gid}&message=${encodeURIComponent(msg)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    pollChat(gid);
                } else {
                    alert(data.error || 'Failed to send message.');
                }
            })
            .catch(() => alert('Network error.'))
            .finally(() => { btn.disabled = false; input.disabled = false; input.focus(); });
        }

        function escHtml(s) {
            return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
    </script>
</body>
</html>
