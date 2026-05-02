<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id  = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$after_id = isset($_GET['after'])    ? (int)$_GET['after']    : 0;

if (!$group_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid group']);
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

$stmt = mysqli_prepare($conn, "
    SELECT gc.message_id, gc.message, gc.created_at, gc.user_id, u.name
    FROM group_chat gc
    JOIN users u ON gc.user_id = u.user_id
    WHERE gc.group_id = ? AND gc.message_id > ?
    ORDER BY gc.created_at ASC
    LIMIT 50
");
mysqli_stmt_bind_param($stmt, "ii", $group_id, $after_id);
mysqli_stmt_execute($stmt);
$res      = mysqli_stmt_get_result($stmt);
$messages = [];
while ($row = mysqli_fetch_assoc($res)) {
    $messages[] = [
        'message_id' => (int)$row['message_id'],
        'message'    => $row['message'],
        'created_at' => date('M d, g:i A', strtotime($row['created_at'])),
        'user_id'    => (int)$row['user_id'],
        'name'       => $row['name'],
        'initials'   => strtoupper(substr($row['name'], 0, 1)),
        'is_mine'    => ($row['user_id'] == $user_id),
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'messages' => $messages]);
?>
