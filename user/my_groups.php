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

        textarea.review-text { width: 100%; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 1rem; color: #fff; margin-bottom: 1.5rem; resize: none; min-height: 100px; }
        .btn-submit { width: 100%; padding: 12px; background: #eab308; color: #000; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; }
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

                    <div class="owner-contact">
                        <h4>Contact Group Owner</h4>
                        <div class="contact-grid">
                            <div class="contact-item">Owner: <b><?php echo htmlspecialchars($group['owner_name']); ?></b></div>
                            <div class="contact-item">Email: <b><?php echo htmlspecialchars($group['owner_email']); ?></b></div>
                            <div class="contact-item">Phone: <b><?php echo htmlspecialchars($group['owner_phone'] ?: 'N/A'); ?></b></div>
                        </div>
                    </div>

                    <?php if ($group['my_role'] === 'member'): ?>
                    <div class="payment-section">
                        <div>
                            <div class="label">💳 Monthly Payment</div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #fff; margin-top: 4px;">৳<?php echo number_format($group['cost_per_member'], 0); ?><span style="font-size: 0.8rem; color: #8888aa; font-weight: 400;">/mo</span></div>
                        </div>
                        <?php if ($group['payment_status'] === 'cleared'): ?>
                            <span class="badge-paid">✅ Paid</span>
                        <?php else: ?>
                            <a href="../payments/checkout.php?group_id=<?php echo $group['group_id']; ?>" class="btn-pay">
                                💳 Pay Now
                            </a>
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
    </script>
</body>
</html>
