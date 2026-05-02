<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$owner_id = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$message  = trim($_POST['message'] ?? '');

if (!$group_id || $message === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

// Verify this user actually owns the group
$check = mysqli_prepare($conn, "SELECT group_id FROM subscription_group WHERE group_id = ? AND owner_id = ?");
mysqli_stmt_bind_param($check, "ii", $group_id, $owner_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) === 0) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}
mysqli_stmt_close($check);

$stmt = mysqli_prepare($conn, "INSERT INTO group_notifications (group_id, owner_id, message) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iis", $group_id, $owner_id, $message);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success'         => true,
        'notification_id' => mysqli_insert_id($conn),
        'created_at'      => date('M d, Y g:i A')
    ]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
mysqli_stmt_close($stmt);
?>
