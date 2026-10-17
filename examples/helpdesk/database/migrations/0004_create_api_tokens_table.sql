CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT NOT NULL,
    `name`         VARCHAR(100) NOT NULL,
    `token_hash`   CHAR(64) NOT NULL UNIQUE,
    `created_at`   DATETIME NULL,
    `last_used_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_api_tokens_user ON api_tokens (user_id);
