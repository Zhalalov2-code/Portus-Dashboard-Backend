-- Таблица для rate limiting регистрации водителей (макс 5 попыток с IP за 1 час)
CREATE TABLE IF NOT EXISTS fahrer_reg_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_created (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cron: очистка старых записей (запускать ежечасно)
-- DELETE FROM fahrer_reg_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR);

-- Cron: очистка просроченных токенов (запускать ежедневно)
-- DELETE FROM auth_tokens WHERE expires_at < NOW();
