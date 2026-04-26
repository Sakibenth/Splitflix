-- Add owner verification columns to users table
ALTER TABLE users 
ADD COLUMN phone VARCHAR(20) DEFAULT NULL,
ADD COLUMN id_card_image VARCHAR(255) DEFAULT NULL,
ADD COLUMN verification_status ENUM('unverified', 'verified') NOT NULL DEFAULT 'unverified';
