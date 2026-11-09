<?php
// ============================================================
// Splitflix - Membership helpers
// ============================================================
// Keeps seats_remaining in sync with the real active membership
// rows and applies leaves that were scheduled for today or earlier.
// ============================================================

/**
 * Recompute one group's free seats from its active members.
 *
 * The owner occupies one seat, so free seats are:
 *     max_members - 1 - active_members   (never below zero)
 */
function recalculate_seats(mysqli $conn, int $group_id): void
{
    $sql = "
        UPDATE subscription_group sg
        SET sg.seats_remaining = GREATEST(
            sg.max_members - 1 - (
                SELECT COUNT(*)
                FROM group_members gm
                WHERE gm.group_id = sg.group_id
                  AND gm.membership_status = 'active'
            ),
            0
        )
        WHERE sg.group_id = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $group_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Remove members whose scheduled leave date has arrived and refresh
 * the affected groups' seat counts. Safe to call on every page load.
 */
function process_due_leaves(mysqli $conn): void
{
    $sql = "
        SELECT DISTINCT group_id
        FROM group_members
        WHERE membership_status = 'active'
          AND scheduled_leave_date IS NOT NULL
          AND scheduled_leave_date <= CURDATE()
    ";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return;
    }

    $group_ids = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $group_ids[] = (int) $row['group_id'];
    }
    mysqli_free_result($result);

    if (empty($group_ids)) {
        return;
    }

    $delete = "
        DELETE FROM group_members
        WHERE membership_status = 'active'
          AND scheduled_leave_date IS NOT NULL
          AND scheduled_leave_date <= CURDATE()
    ";
    if (!mysqli_query($conn, $delete)) {
        return;
    }

    foreach ($group_ids as $group_id) {
        recalculate_seats($conn, $group_id);
    }
}
