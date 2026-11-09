-- Splitflix Database Setup
-- CSE370 Lab Project
--
-- LEGACY / HISTORICAL SCRIPT - do not use for a new install.
-- Use sql/full_setup.sql instead. This file reflects an earlier schema
-- (plan_id, users.role, platforms.logo_emoji) and is kept for history.

CREATE DATABASE IF NOT EXISTS splitflix;
USE splitflix;

-- Users table (both Owners and Members)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('member', 'owner') NOT NULL DEFAULT 'member',
    profile_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Platforms table
CREATE TABLE IF NOT EXISTS platforms (
    platform_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'Streaming',
    logo_emoji VARCHAR(10) NOT NULL DEFAULT '🎬',
    brand_color VARCHAR(7) NOT NULL DEFAULT '#e50914',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Plans table (each platform has multiple plans)
CREATE TABLE IF NOT EXISTS plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    max_seats INT NOT NULL DEFAULT 1,
    device_limit INT NOT NULL DEFAULT 1,
    monthly_cost DECIMAL(10,2) NOT NULL,
    billing_type ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subscription Groups table
CREATE TABLE IF NOT EXISTS subscription_group (
    group_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    platform_id INT NOT NULL,
    plan_id INT NOT NULL,
    group_name VARCHAR(150) NOT NULL,
    max_members INT NOT NULL DEFAULT 4,
    seats_remaining INT NOT NULL DEFAULT 3,
    cost_per_member DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'paused', 'closed') NOT NULL DEFAULT 'active',
    validity_start DATE NOT NULL,
    validity_end DATE DEFAULT NULL,
    contact_info VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(plan_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
-- Netflix Plans
(1, 'Standard', 2, 2, 15.49, 'monthly'),
(1, 'Premium', 4, 4, 22.99, 'monthly'),
-- Amazon Prime Plans
(2, 'Prime Monthly', 3, 3, 14.99, 'monthly'),
(2, 'Prime Yearly', 3, 3, 139.00, 'yearly'),
-- Hotstar Plans
(3, 'Super', 2, 2, 8.99, 'monthly'),
(3, 'Premium', 4, 4, 14.99, 'monthly'),
-- HBO Max Plans
(4, 'Ad-Free', 3, 3, 15.99, 'monthly'),
(4, 'Ultimate', 4, 4, 19.99, 'monthly'),
-- Spotify Plans
(5, 'Duo', 2, 2, 14.99, 'monthly'),
(5, 'Family', 6, 6, 16.99, 'monthly');
