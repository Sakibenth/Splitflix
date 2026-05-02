<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$owner_id      = $_SESSION['user_id'];
$membership_id = (int)$_POST['membership_id'];
$group_id      = (int)$_POST['group_id'];

// Verify this owner owns the group
$check = "SELECT group_id FROM subscription_group WHERE group_id = ? AND owner_id = ?";
$stmt  = mysqli_prepare($conn, $check);
mysqli_stmt_bind_param($stmt, "ii", $group_id, $owner_id);
mysqli_stmt_execute($stmt);
if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
mysqli_stmt_close($stmt);

// Clear the scheduled_leave_date
$update = "UPDATE group_members SET scheduled_leave_date = NULL WHERE id = ? AND group_id = ?";
$stmt   = mysqli_prepare($conn, $update);
mysqli_stmt_bind_param($stmt, "ii", $membership_id, $group_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
mysqli_stmt_close($stmt);
?>
