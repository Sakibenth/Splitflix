

CREATE TABLE IF NOT EXISTS group_chat (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id   INT NOT NULL,
    user_id    INT NOT NULL,
    message    TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscription_group(group_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_group_created (group_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
