<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_groups.php");
    exit();
}

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    flash_set('error', 'Your session expired. Please try again.');
    header("Location: my_groups.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

if (!$group_id) {
    flash_set('error', 'Invalid group.');
    header("Location: my_groups.php");
    exit();
}

// Fetch group + verify user is an active member (not owner)
$q = "SELECT sg.group_id, sg.validity_start, sg.owner_id
      FROM subscription_group sg
      JOIN group_members gm ON sg.group_id = gm.group_id
      WHERE sg.group_id = ? AND gm.user_id = ? AND gm.membership_status = 'active'";
$stmt = mysqli_prepare($conn, $q);
mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);
mysqli_stmt_execute($stmt);
$group = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$group) {
    flash_set('error', 'Group not found or you are not an active member.');
    header("Location: my_groups.php");
    exit();
}

if ((int)$group['owner_id'] === (int)$user_id) {
    flash_set('error', 'Owners cannot leave their own group.');
    header("Location: my_groups.php");
    exit();
}

// --- Calculate next billing date ---
// Billing day = day-of-month of validity_start (e.g. 26 for Apr 26)
$billing_day = (int) date('j', strtotime($group['validity_start']));
$today       = new DateTime();
$today_day   = (int) $today->format('j');

// Find the next occurrence of $billing_day
$next = clone $today;
if ($today_day < $billing_day) {
    // It's still this month
    $next->setDate((int)$today->format('Y'), (int)$today->format('n'), $billing_day);
} else {
    // Already passed this month — move to next month
    $next->modify('first day of next month');
    $next->setDate((int)$next->format('Y'), (int)$next->format('n'), $billing_day);
}
$leave_date = $next->format('Y-m-d');
$leave_date_display = $next->format('M d, Y');

// Schedule the leave
$update = "UPDATE group_members SET scheduled_leave_date = ? WHERE group_id = ? AND user_id = ? AND membership_status = 'active'";
$stmt   = mysqli_prepare($conn, $update);
mysqli_stmt_bind_param($stmt, "sii", $leave_date, $group_id, $user_id);

if (mysqli_stmt_execute($stmt)) {
    flash_set('success', "Leave scheduled for $leave_date_display. You remain active until then.");
} else {
    flash_set('error', 'Failed to schedule your leave. Please try again.');
}
mysqli_stmt_close($stmt);
header("Location: my_groups.php");
?>
