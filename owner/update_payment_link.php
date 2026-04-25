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
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$payment_link = isset($_POST['payment_link']) ? trim($_POST['payment_link']) : '';

if (!$group_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

// Verify that this owner actually owns the group
$verify_query = "SELECT owner_id FROM subscription_group WHERE group_id = ?";
$stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['success' => false, 'error' => 'Group not found']);
    exit();
}

$row = mysqli_fetch_assoc($res);
if ($row['owner_id'] !== $owner_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized to edit this group']);
    exit();
}
mysqli_stmt_close($stmt);

// Update the payment link
$update_query = "UPDATE subscription_group SET payment_form_link = ? WHERE group_id = ?";
$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "si", $payment_link, $group_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
mysqli_stmt_close($stmt);
?>
