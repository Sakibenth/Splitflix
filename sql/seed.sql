USE splitflix;

INSERT INTO platforms (platform_name, category, logo_emoji, brand_color) VALUES
('Netflix', 'Video Streaming', '🎬', '#E50914'),
('Amazon Prime', 'Video Streaming', '📦', '#00A8E1'),
('Hotstar', 'Video Streaming', '⭐', '#1F8B24'),
('HBO Max', 'Video Streaming', '🎭', '#B535F6'),
('Spotify', 'Music Streaming', '🎵', '#1DB954');

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
