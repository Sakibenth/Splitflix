<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$owner_id = $_SESSION['user_id'];
$membership_id = isset($_POST['membership_id']) ? (int)$_POST['membership_id'] : 0;
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

if (!$membership_id || !$group_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

// 1. Verify ownership and check if group has space
$verify_query = "
    SELECT sg.owner_id, sg.max_members, sg.seats_remaining, 
           (SELECT COUNT(*) FROM group_members WHERE group_id = sg.group_id AND membership_status = 'active') as active_count
    FROM subscription_group sg
    WHERE sg.group_id = ?
";
$stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['success' => false, 'error' => 'Group not found']);
    exit();
}

$group = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if ($group['owner_id'] !== $owner_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($group['active_count'] >= $group['max_members']) {
    echo json_encode(['success' => false, 'error' => 'Group is already full']);
    exit();
}

// 2. Start Transaction
mysqli_begin_transaction($conn);

try {
    // 3. Update membership status
    $update_member = "UPDATE group_members SET membership_status = 'active' WHERE id = ? AND group_id = ? AND membership_status = 'waitlisted'";
    $stmt1 = mysqli_prepare($conn, $update_member);
    mysqli_stmt_bind_param($stmt1, "ii", $membership_id, $group_id);
    mysqli_stmt_execute($stmt1);
    
    if (mysqli_stmt_affected_rows($stmt1) === 0) {
        throw new Exception("Membership not found or already active");
    }
    mysqli_stmt_close($stmt1);

    // 4. Update seats remaining
    $update_group = "UPDATE subscription_group SET seats_remaining = seats_remaining - 1 WHERE group_id = ?";
    $stmt2 = mysqli_prepare($conn, $update_group);
    mysqli_stmt_bind_param($stmt2, "i", $group_id);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);

    mysqli_commit($conn);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
