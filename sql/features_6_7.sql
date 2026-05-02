
CREATE TABLE IF NOT EXISTS group_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    owner_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscription_group(group_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE
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
    FOREIGN KEY (recommended_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
