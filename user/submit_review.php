<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$reviewer_id = $_SESSION['user_id'];
$group_id = (int)$_POST['group_id'];
$reviewee_id = (int)$_POST['reviewee_id'];
$role = $_POST['role']; // 'member' or 'owner'
$rating = (int)$_POST['rating'];
$comment = trim($_POST['comment']);

if ($rating < 1 || $rating > 5) {
    header("Location: my_groups.php?error=Invalid rating");
    exit();
}

// Ensure reviewer is part of the group
$user_to_check = ($role === 'member') ? $reviewer_id : $reviewee_id;
$check_query = "SELECT id FROM group_members WHERE group_id = ? AND user_id = ? AND membership_status = 'active'";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_to_check);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if (mysqli_num_rows($res) === 0 && $role === 'member') {
     // For owners reviewing members, check if reviewee is in group
     // For members reviewing owners, check if reviewer is in group
     header("Location: my_groups.php?error=Unauthorized review");
     exit();
}
mysqli_stmt_close($stmt);

// Check if already reviewed
$dup_check = "SELECT review_id FROM reviews WHERE group_id = ? AND reviewer_id = ? AND reviewer_role = ?";
$stmt = mysqli_prepare($conn, $dup_check);
mysqli_stmt_bind_param($stmt, "iis", $group_id, $reviewer_id, $role);
mysqli_stmt_execute($stmt);
if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
    header("Location: my_groups.php?error=Already reviewed");
    exit();
}
mysqli_stmt_close($stmt);

$insert_query = "INSERT INTO reviews (group_id, reviewer_id, reviewee_id, reviewer_role, rating, comment) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($stmt, "iiisis", $group_id, $reviewer_id, $reviewee_id, $role, $rating, $comment);

if (mysqli_stmt_execute($stmt)) {
    $redirect = ($role === 'member') ? 'my_groups.php?success=Review submitted!' : "../owner/group_details.php?group_id=$group_id&success=Review submitted!";
    header("Location: $redirect");
} else {
    header("Location: my_groups.php?error=Failed to submit review");
}
mysqli_stmt_close($stmt);
?>
