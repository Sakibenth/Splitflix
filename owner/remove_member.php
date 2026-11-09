<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/security.php';
require_once __DIR__ . '/../assets/includes/membership.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    csrf_abort_json();
}

$owner_id = (int)$_SESSION['user_id'];
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
$group = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$group || (int)$group['owner_id'] !== $owner_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// 2. Start transaction
mysqli_begin_transaction($conn);

try {
    // Lock and verify the membership row before deleting it
    $lock = mysqli_prepare($conn, "SELECT membership_status FROM group_members WHERE id = ? AND group_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($lock, "ii", $membership_id, $group_id);
    mysqli_stmt_execute($lock);
    $lock_res = mysqli_stmt_get_result($lock);
    $membership = $lock_res ? mysqli_fetch_assoc($lock_res) : null;
    mysqli_stmt_close($lock);

    if (!$membership) {
        throw new Exception('Membership not found');
    }

    $remove_member = "DELETE FROM group_members WHERE id = ? AND group_id = ?";
    $stmt1 = mysqli_prepare($conn, $remove_member);
    mysqli_stmt_bind_param($stmt1, "ii", $membership_id, $group_id);
    mysqli_stmt_execute($stmt1);
    if (mysqli_stmt_affected_rows($stmt1) === 0) {
        mysqli_stmt_close($stmt1);
        throw new Exception('Membership could not be removed');
    }
    mysqli_stmt_close($stmt1);

    // 3. Recompute free seats from the remaining active members
    recalculate_seats($conn, $group_id);

    mysqli_commit($conn);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
