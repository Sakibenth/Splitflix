-- Splitflix Full Database Setup (consolidated)
-- Run this to recreate the entire database from scratch

CREATE DATABASE IF NOT EXISTS splitflix;
USE splitflix;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS group_members;
DROP TABLE IF EXISTS subscription_group;
DROP TABLE IF EXISTS plans;
DROP TABLE IF EXISTS platforms;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ========================================
-- Users table
-- ========================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('member', 'owner') NOT NULL DEFAULT 'member',
    profile_image VARCHAR(255) DEFAULT NULL,
    id_card_image VARCHAR(255) DEFAULT NULL,
    verification_status ENUM('unverified', 'verified') NOT NULL DEFAULT 'unverified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Platforms table
-- ========================================
CREATE TABLE platforms (
    platform_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'Streaming',
    logo_emoji VARCHAR(10) NOT NULL DEFAULT '🎬',
    brand_color VARCHAR(7) NOT NULL DEFAULT '#e50914',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Plans table
-- ========================================
CREATE TABLE plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    max_seats INT NOT NULL DEFAULT 1,
    device_limit INT NOT NULL DEFAULT 1,
    monthly_cost DECIMAL(10,2) NOT NULL,
    billing_type ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Subscription Groups table
-- ========================================
CREATE TABLE subscription_group (
    group_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    platform_id INT NOT NULL,
    plan_description VARCHAR(255) NOT NULL,
    group_name VARCHAR(150) NOT NULL,
    max_members INT NOT NULL DEFAULT 4,
    seats_remaining INT NOT NULL DEFAULT 3,
    cost_per_member DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'paused', 'closed') NOT NULL DEFAULT 'active',
    validity_start DATE NOT NULL,
    validity_end DATE DEFAULT NULL,
    contact_info VARCHAR(255) DEFAULT NULL,
    payment_form_link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Group Members table
-- ========================================
CREATE TABLE group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    membership_status ENUM('active', 'waitlisted', 'rejected') DEFAULT 'active',
    payment_status ENUM('cleared', 'uncleared') NOT NULL DEFAULT 'uncleared',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscription_group(group_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (group_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Seed Data: Platforms
-- ========================================
INSERT INTO platforms (platform_name, category, logo_emoji, brand_color) VALUES
('Netflix', 'Video Streaming', '🎬', '#E50914'),
('Amazon Prime', 'Video Streaming', '📦', '#00A8E1'),
('Hotstar', 'Video Streaming', '⭐', '#1F8B24'),
('HBO Max', 'Video Streaming', '🎭', '#B535F6'),
('Spotify', 'Music Streaming', '🎵', '#1DB954');

-- ========================================
-- Seed Data: Plans
-- ========================================
INSERT INTO plans (platform_id, plan_name, max_seats, device_limit, monthly_cost, billing_type) VALUES
(1, 'Standard', 2, 2, 15.49, 'monthly'),
(1, 'Premium', 4, 4, 22.99, 'monthly'),
(2, 'Prime Monthly', 3, 3, 14.99, 'monthly'),
(2, 'Prime Yearly', 3, 3, 139.00, 'yearly'),
(3, 'Super', 2, 2, 8.99, 'monthly'),
(3, 'Premium', 4, 4, 14.99, 'monthly'),
(4, 'Ad-Free', 3, 3, 15.99, 'monthly'),
(4, 'Ultimate', 4, 4, 19.99, 'monthly'),
(5, 'Duo', 2, 2, 14.99, 'monthly'),
(5, 'Family', 6, 6, 16.99, 'monthly');
