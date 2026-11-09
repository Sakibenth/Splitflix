-- ============================================================
-- Splitflix - Complete Database Setup
-- ============================================================
-- Fresh install: run this file once in phpMyAdmin or:
--   mysql -u root -p < sql/full_setup.sql
--
-- Upgrading an existing Splitflix database? Run sql/migrate_v2.sql
-- instead, then compare with this file.
-- ============================================================

CREATE DATABASE IF NOT EXISTS splitflix
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE splitflix;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS group_chat;
DROP TABLE IF EXISTS movie_recommendations;
DROP TABLE IF EXISTS group_notifications;
DROP TABLE IF EXISTS group_members;
DROP TABLE IF EXISTS subscription_group;
DROP TABLE IF EXISTS plans;
DROP TABLE IF EXISTS platforms;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ========================================
-- Users
-- ========================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    id_card_image VARCHAR(255) DEFAULT NULL,
    verification_status ENUM('unverified', 'verified') NOT NULL DEFAULT 'unverified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Platforms
-- ========================================
CREATE TABLE platforms (
    platform_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_name VARCHAR(100) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL DEFAULT 'Streaming',
    brand_color VARCHAR(7) NOT NULL DEFAULT '#e50914',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Subscription groups
-- ========================================
CREATE TABLE subscription_group (
    group_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    platform_id INT NOT NULL,
    plan_description VARCHAR(255) NOT NULL,
    group_description TEXT DEFAULT NULL,
    group_name VARCHAR(150) NOT NULL,
    max_members INT NOT NULL DEFAULT 4,
    seats_remaining INT NOT NULL DEFAULT 3,
    cost_per_member DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'paused', 'closed') NOT NULL DEFAULT 'active',
    validity_start DATE NOT NULL,
    validity_end DATE DEFAULT NULL,
    payment_form_link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
    INDEX idx_group_platform_status (platform_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Group memberships (approved + waitlist)
-- ========================================
CREATE TABLE group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    membership_status ENUM('active', 'waitlisted', 'rejected') NOT NULL DEFAULT 'active',
    payment_status ENUM('cleared', 'uncleared') NOT NULL DEFAULT 'uncleared',
    scheduled_leave_date DATE DEFAULT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscription_group(group_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (group_id, user_id),
    INDEX idx_group_status (group_id, membership_status),
    INDEX idx_user_status (user_id, membership_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Reviews (member <-> owner, one per pair per group)
-- ========================================
CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewee_id INT NOT NULL,
    reviewer_role ENUM('member', 'owner') NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscription_group(group_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (group_id, reviewer_id, reviewee_id, reviewer_role),
    INDEX idx_reviewee_role (reviewee_id, reviewer_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Group notifications (owner -> members)
-- ========================================
CREATE TABLE group_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    owner_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscription_group(group_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_notification_group_created (group_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Movie recommendations (group members)
-- ========================================
CREATE TABLE movie_recommendations (
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

-- ========================================
-- Group chat (members + owner)
-- ========================================
CREATE TABLE group_chat (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscription_group(group_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_chat_group_created (group_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Seed data: platforms
-- ========================================
INSERT INTO platforms (platform_name, category, brand_color) VALUES
('Netflix', 'Video Streaming', '#E50914'),
('Amazon Prime', 'Video Streaming', '#00A8E1'),
('Hotstar', 'Video Streaming', '#1F8B24'),
('HBO Max', 'Video Streaming', '#B535F6'),
('Spotify', 'Music Streaming', '#1DB954');
