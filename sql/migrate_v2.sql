-- ============================================================
-- Splitflix - Migration v2 (for databases created before this update)
-- ============================================================
-- Run this ONCE against an existing "splitflix" database.
-- Fresh installs should import sql/full_setup.sql instead.
--
-- This migration:
--   1. creates the feature 6/7/8 tables if they are missing
--   2. changes the review uniqueness key so an owner can review
--      every member of a group
--   3. removes the unused users.role column
--   4. repairs seats_remaining from the real member counts
-- ============================================================

USE splitflix;

-- 1. Feature tables -------------------------------------------------
CREATE TABLE IF NOT EXISTS group_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    owner_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscription_group(group_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_notification_group_created (group_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movie_recommendations (
    recommendation_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    recommended_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    genre VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    platform_hint VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscription_group(group_id) ON DELETE CASCADE,
    FOREIGN KEY (recommended_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_recommendation_group_created (group_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_chat (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscription_group(group_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_chat_group_created (group_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Review key: allow one owner review per member (not per group) ---
ALTER TABLE reviews
    DROP INDEX unique_review,
    ADD UNIQUE KEY unique_review (group_id, reviewer_id, reviewee_id, reviewer_role);

-- 3. Remove the unused account role column ---------------------------
ALTER TABLE users DROP COLUMN role;

-- 4. Recompute every group's free seats ------------------------------
UPDATE subscription_group sg
SET sg.seats_remaining = GREATEST(
    sg.max_members - 1 - (
        SELECT COUNT(*)
        FROM group_members gm
        WHERE gm.group_id = sg.group_id
          AND gm.membership_status = 'active'
    ),
    0
);

-- NOTE: Columns that the application no longer uses (platforms.logo_emoji,
-- subscription_group.contact_info, and the old plans table) may remain in an
-- upgraded database. They are harmless; a fresh full_setup.sql install does
-- not create them. Drop them manually only after checking your own queries.
