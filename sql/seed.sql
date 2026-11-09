-- ============================================================
-- Splitflix - Platform seed data (optional)
-- ============================================================
-- full_setup.sql already inserts these platforms. This file only
-- matters if you created the tables manually and want the sample
-- platforms. It is safe to run more than once (INSERT IGNORE).
-- ============================================================

USE splitflix;

INSERT IGNORE INTO platforms (platform_name, category, brand_color) VALUES
('Netflix', 'Video Streaming', '#E50914'),
('Amazon Prime', 'Video Streaming', '#00A8E1'),
('Hotstar', 'Video Streaming', '#1F8B24'),
('HBO Max', 'Video Streaming', '#B535F6'),
('Spotify', 'Music Streaming', '#1DB954');
