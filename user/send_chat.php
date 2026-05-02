<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id  = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$message  = trim($_POST['message'] ?? '');

if (!$group_id || $message === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

if (mb_strlen($message) > 1000) {
    echo json_encode(['success' => false, 'error' => 'Message too long (max 1000 characters)']);
    exit();
}


$check = mysqli_prepare($conn, "
    SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? AND membership_status = 'active'
    UNION
    SELECT 1 FROM subscription_group WHERE group_id = ? AND owner_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($check, "iiii", $group_id, $user_id, $group_id, $user_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) === 0) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}
mysqli_stmt_close($check);

$stmt = mysqli_prepare($conn, "INSERT INTO group_chat (group_id, user_id, message) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iis", $group_id, $user_id, $message);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message_id' => mysqli_insert_id($conn)]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
mysqli_stmt_close($stmt);
?>
