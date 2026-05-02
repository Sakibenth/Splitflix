<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id       = $_SESSION['user_id'];
$group_id      = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$title         = trim($_POST['title'] ?? '');
$genre         = trim($_POST['genre'] ?? '');
$platform_hint = trim($_POST['platform_hint'] ?? '');
$description   = trim($_POST['description'] ?? '');

if (!$group_id || $title === '') {
    echo json_encode(['success' => false, 'error' => 'Title and group are required']);
    exit();
}

// Verify the user is an active member of this group
$check = mysqli_prepare($conn, "SELECT id FROM group_members WHERE group_id = ? AND user_id = ? AND membership_status = 'active'");
mysqli_stmt_bind_param($check, "ii", $group_id, $user_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) === 0) {
    echo json_encode(['success' => false, 'error' => 'You are not an active member of this group']);
    exit();
}
mysqli_stmt_close($check);

$stmt = mysqli_prepare($conn, "INSERT INTO movie_recommendations (group_id, recommended_by, title, genre, description, platform_hint) VALUES (?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iissss", $group_id, $user_id, $title, $genre, $description, $platform_hint);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'recommendation_id' => mysqli_insert_id($conn)]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
mysqli_stmt_close($stmt);
?>
