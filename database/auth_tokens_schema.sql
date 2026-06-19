-- Таблица токенов авторизации (сотрудники из users и водители из fahrer).
-- Применить один раз: mysql -u root1 -p portusapp1 < auth_tokens_schema.sql

CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_type ENUM('user', 'fahrer') NOT NULL,
    subject_id INT NOT NULL,
    token CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uniq_token (token),
    INDEX idx_subject (subject_type, subject_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
