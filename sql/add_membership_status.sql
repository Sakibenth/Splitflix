USE splitflix;

ALTER TABLE group_members ADD COLUMN membership_status ENUM('active', 'waitlisted', 'rejected') DEFAULT 'active' AFTER user_id;
