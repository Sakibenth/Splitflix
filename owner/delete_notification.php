<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    csrf_abort_json();
}

$owner_id        = $_SESSION['user_id'];
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if (!$notification_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

// Only delete if this owner posted it
$stmt = mysqli_prepare($conn, "DELETE FROM group_notifications WHERE notification_id = ? AND owner_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $notification_id, $owner_id);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Not found or access denied']);
}
mysqli_stmt_close($stmt);
?>
