-- ============================================================
-- Splitflix: Monthly Per-Group Payment Reset Event
-- Resets payment_status to 'uncleared' for all active members
-- of groups whose billing day (DAY of validity_start) matches
-- today's day-of-month. Runs every day at midnight.
-- ============================================================

-- Step 1: Enable the MySQL Event Scheduler (must be ON)
SET GLOBAL event_scheduler = ON;

-- Step 2: Drop old event if it exists (for re-running safely)
DROP EVENT IF EXISTS reset_group_payments_monthly;

-- Step 3: Create the daily billing reset event
CREATE EVENT reset_group_payments_monthly
ON SCHEDULE EVERY 1 DAY
STARTS CONCAT(CURDATE(), ' 00:00:00')
DO
    UPDATE group_members gm
    JOIN subscription_group sg ON gm.group_id = sg.group_id
    SET gm.payment_status = 'uncleared'
    WHERE
        gm.membership_status = 'active'
        AND sg.status = 'active'
        AND DAY(sg.validity_start) = DAY(CURDATE());
