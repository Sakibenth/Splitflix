<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$owner_id = $_SESSION['user_id'];
$membership_id = isset($_POST['membership_id']) ? (int)$_POST['membership_id'] : 0;
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

if (!$membership_id || !$group_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

// 1. Verify ownership
$verify_query = "SELECT owner_id FROM subscription_group WHERE group_id = ?";
$stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$group = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$group || $group['owner_id'] !== $owner_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// 2. Start Transaction
mysqli_begin_transaction($conn);

try {
    // 3. Delete membership (or mark as inactive/removed)
    // We'll delete for simplicity, but the review stays linked to user/group IDs
    $remove_member = "DELETE FROM group_members WHERE id = ? AND group_id = ?";
    $stmt1 = mysqli_prepare($conn, $remove_member);
    mysqli_stmt_bind_param($stmt1, "ii", $membership_id, $group_id);
    mysqli_stmt_execute($stmt1);
    mysqli_stmt_close($stmt1);

    // 4. Update seats remaining
    $update_group = "UPDATE subscription_group SET seats_remaining = seats_remaining + 1 WHERE group_id = ?";
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
