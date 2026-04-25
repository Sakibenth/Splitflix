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
$status = isset($_POST['status']) ? $_POST['status'] : '';

if (!$membership_id || !in_array($status, ['cleared', 'uncleared'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

// First, verify that this owner actually owns the group this membership belongs to
$verify_query = "
    SELECT sg.owner_id 
    FROM group_members gm
    JOIN subscription_group sg ON gm.group_id = sg.group_id
    WHERE gm.id = ?
";
$stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($stmt, "i", $membership_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['success' => false, 'error' => 'Membership not found']);
    exit();
}

$row = mysqli_fetch_assoc($res);
if ($row['owner_id'] !== $owner_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized to edit this group']);
    exit();
}
mysqli_stmt_close($stmt);

// Update the status
$update_query = "UPDATE group_members SET payment_status = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "si", $status, $membership_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
mysqli_stmt_close($stmt);
?>
