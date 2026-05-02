<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$group_id) {
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
    header("Location: dashboard.php");
    exit();
}

// Check Join Window Lock
$billing_day = (int) date('j', strtotime($group['validity_start']));
$today_day   = (int) date('j');
if ($billing_day !== $today_day) {
    header("Location: dashboard.php?error=You can only request to join this group on its billing date (day $billing_day of the month).");
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
    header("Location: dashboard.php?error=Already requested or joined");
    exit();
}

// 3. Add to waitlist
$insert_query = "INSERT INTO group_members (group_id, user_id, membership_status) VALUES (?, ?, 'waitlisted')";
$stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);

if (mysqli_stmt_execute($stmt)) {
    header("Location: dashboard.php?success=Request submitted! The owner will review your reputation and approve.");
} else {
    header("Location: dashboard.php?error=Failed to submit request");
}
mysqli_stmt_close($stmt);
?>
