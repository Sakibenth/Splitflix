<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/icons.php';

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

// Fetch Group Details
$group_query = "
    SELECT sg.*, p.platform_name, p.logo_emoji, p.brand_color 
    FROM subscription_group sg
    JOIN platforms p ON sg.platform_id = p.platform_id
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
    SELECT gm.id as membership_id, gm.payment_status, gm.membership_status, gm.joined_at, u.user_id, u.name, u.email, u.phone 
    FROM group_members gm
    JOIN users u ON gm.user_id = u.user_id
    WHERE gm.group_id = ?
    ORDER BY gm.joined_at ASC
";
$stmt = mysqli_prepare($conn, $members_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
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

$total_joined = count($active_members);
$total_allowed = $group['max_members'];
$is_accepting = $total_joined < $total_allowed;
$brand_color = htmlspecialchars($group['brand_color']);
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
        .details-container { max-width: 1100px; margin: 2rem auto; padding: 0 2rem; }
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
        .empty-members { text-align: center; padding: 4rem 2rem; color: #8888aa; }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="nav-brand">
            <a href="../auth/logout.php" class="nav-brand-link">
                <span class="nav-logo">🎬</span>
                <span class="nav-title">Splitflix</span>
            </a>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link active">My Groups</a>
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
                        <?php foreach ($active_members as $member): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($member['name']); ?></div>
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

        <!-- Waitlist Queue Table -->
        <div class="members-table-container">
            <div class="members-table-header">
                <h2>Waitlist Queue</h2>
                <span class="badge"><?php echo count($waitlisted_members); ?> Waiting</span>
            </div>
            <?php if (empty($waitlisted_members)): ?>
                <div class="empty-members" style="padding: 2rem;">
                    <p style="margin: 0;">The waitlist is currently empty.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Requested By</th>
                            <th>Contact</th>
                            <th>Request Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($waitlisted_members as $index => $member): ?>
                            <tr>
                                <td>
                                    <strong style="color: #8888aa;"><?php echo $index + 1; ?></strong>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($member['name']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($member['email']); ?></div>
                                </td>
                                <td>
                                    <?php echo date('M d, g:i A', strtotime($member['joined_at'])); ?>
                                </td>
                                <td>
                                    <button class="btn-action" 
                                            onclick="acceptMember(<?php echo $member['membership_id']; ?>, this)"
                                            <?php echo !$is_accepting ? 'disabled title="Cannot accept, group is full"' : ''; ?>>
                                        Accept
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function updatePaymentStatus(membershipId, selectElement) {
            const newStatus = selectElement.value;
            selectElement.className = 'status-select ' + (newStatus === 'cleared' ? 'status-cleared' : 'status-uncleared');
            fetch('update_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `membership_id=${membershipId}&status=${newStatus}`
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
                body: `group_id=<?php echo $group_id; ?>&description=${encodeURIComponent(newDesc)}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('plan-text').innerHTML = newDesc.replace(/\n/g, '<br>');
                    toggleEdit();
                } else alert('Failed: ' + data.error);
            }).finally(() => { btn.textContent = 'Save'; btn.disabled = false; });
        }

        function acceptMember(membershipId, btn) {
            if(!confirm("Are you sure you want to accept this member into the group?")) return;
            
            btn.textContent = 'Accepting...';
            btn.disabled = true;
            
            fetch('accept_member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `membership_id=${membershipId}&group_id=<?php echo $group_id; ?>`
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
    </script>
</body>
</html>
