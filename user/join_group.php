<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    flash_set('error', 'Your session expired. Please try again.');
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

if (!$group_id) {
    flash_set('error', 'Invalid group.');
    header("Location: dashboard.php");
    exit();
}

// 1. Check if group exists and is active
$group_query = "SELECT * FROM subscription_group WHERE group_id = ? AND status = 'active'";
$stmt = mysqli_prepare($conn, $group_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$group = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$group) {
    flash_set('error', 'That group is no longer available.');
    header("Location: dashboard.php");
    exit();
}

$platform_id = (int)$group['platform_id'];

// Owners cannot join their own group
if ((int)$group['owner_id'] === (int)$user_id) {
    flash_set('error', 'You already own this group.');
    header("Location: groups.php?platform_id=" . $platform_id);
    exit();
}

// 2. Check if already a member or waitlisted
$check_query = "SELECT membership_status FROM group_members WHERE group_id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);
mysqli_stmt_execute($stmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($existing) {
    flash_set('error', $existing['membership_status'] === 'active'
        ? 'You are already a member of this group.'
        : 'You already have a pending request for this group.');
    header("Location: my_groups.php");
    exit();
}

// 3. Add to waitlist
$insert_query = "INSERT INTO group_members (group_id, user_id, membership_status) VALUES (?, ?, 'waitlisted')";
$stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);

if (mysqli_stmt_execute($stmt)) {
    flash_set('success', 'Request submitted! The owner will review it and approve you when a seat opens.');
    header("Location: my_groups.php");
} else {
    flash_set('error', 'Failed to submit your request. Please try again.');
    header("Location: groups.php?platform_id=" . $platform_id);
}
mysqli_stmt_close($stmt);
?>
