<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/security.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_groups.php");
    exit();
}

$reviewer_id = (int)$_SESSION['user_id'];
$group_id    = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$reviewee_id = isset($_POST['reviewee_id']) ? (int)$_POST['reviewee_id'] : 0;
$role        = isset($_POST['role']) ? $_POST['role'] : '';
$rating      = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment     = trim($_POST['comment'] ?? '');

$redirect = ($role === 'owner')
    ? "../owner/group_details.php?group_id=" . $group_id
    : "my_groups.php";

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    flash_set('error', 'Your session expired. Please try again.');
    header("Location: $redirect");
    exit();
}

if (!in_array($role, ['member', 'owner'], true)) {
    flash_set('error', 'Invalid review type.');
    header("Location: $redirect");
    exit();
}

if ($rating < 1 || $rating > 5) {
    flash_set('error', 'Please choose a rating between 1 and 5 stars.');
    header("Location: $redirect");
    exit();
}

if (!$group_id || !$reviewee_id) {
    flash_set('error', 'Invalid review request.');
    header("Location: $redirect");
    exit();
}

// Load the group so we can verify who the owner is
$group_stmt = mysqli_prepare($conn, "SELECT owner_id FROM subscription_group WHERE group_id = ?");
mysqli_stmt_bind_param($group_stmt, "i", $group_id);
mysqli_stmt_execute($group_stmt);
$group = mysqli_fetch_assoc(mysqli_stmt_get_result($group_stmt));
mysqli_stmt_close($group_stmt);

if (!$group) {
    flash_set('error', 'Group not found.');
    header("Location: $redirect");
    exit();
}

$owner_id = (int)$group['owner_id'];

if ($role === 'member') {
    // A member may only review the owner of a group they actively belong to
    if ($reviewee_id !== $owner_id) {
        flash_set('error', 'You can only review the owner of this group.');
        header("Location: $redirect");
        exit();
    }

    $check_stmt = mysqli_prepare($conn, "
        SELECT id FROM group_members
        WHERE group_id = ? AND user_id = ? AND membership_status = 'active'
    ");
    mysqli_stmt_bind_param($check_stmt, "ii", $group_id, $reviewer_id);
} else {
    // Only the owner may review a member, and only an active one
    if ($reviewer_id !== $owner_id) {
        flash_set('error', 'Only the group owner can review members.');
        header("Location: $redirect");
        exit();
    }

    $check_stmt = mysqli_prepare($conn, "
        SELECT id FROM group_members
        WHERE group_id = ? AND user_id = ? AND membership_status = 'active'
    ");
    mysqli_stmt_bind_param($check_stmt, "ii", $group_id, $reviewee_id);
}

mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);
if (mysqli_stmt_num_rows($check_stmt) === 0) {
    mysqli_stmt_close($check_stmt);
    flash_set('error', 'You are not allowed to review this person.');
    header("Location: $redirect");
    exit();
}
mysqli_stmt_close($check_stmt);

// One review per reviewer, per reviewee, per group
$dup_stmt = mysqli_prepare($conn, "
    SELECT review_id FROM reviews
    WHERE group_id = ? AND reviewer_id = ? AND reviewee_id = ? AND reviewer_role = ?
");
mysqli_stmt_bind_param($dup_stmt, "iiis", $group_id, $reviewer_id, $reviewee_id, $role);
mysqli_stmt_execute($dup_stmt);
mysqli_stmt_store_result($dup_stmt);
if (mysqli_stmt_num_rows($dup_stmt) > 0) {
    mysqli_stmt_close($dup_stmt);
    flash_set('error', 'You have already reviewed this person for this group.');
    header("Location: $redirect");
    exit();
}
mysqli_stmt_close($dup_stmt);

$insert_query = "INSERT INTO reviews (group_id, reviewer_id, reviewee_id, reviewer_role, rating, comment) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($stmt, "iiisis", $group_id, $reviewer_id, $reviewee_id, $role, $rating, $comment);

if (mysqli_stmt_execute($stmt)) {
    flash_set('success', 'Review submitted!');
} else {
    flash_set('error', 'Failed to submit your review. Please try again.');
}
mysqli_stmt_close($stmt);

header("Location: $redirect");
?>
