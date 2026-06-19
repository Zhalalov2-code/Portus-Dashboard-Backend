-- =====================================================================
-- Urlaubsverwaltung (Vacation / Leave Management) — Schema Migration
-- Run this against the same database as tasks_module_schema.sql
-- =====================================================================

-- Yearly leave allowance per employee (defaults to 28 days, adjustable by admin)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS vacation_days_per_year INT NOT NULL DEFAULT 28;

CREATE TABLE IF NOT EXISTS vacations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NULL,
    type ENUM('vacation', 'sick', 'unpaid', 'other') NOT NULL DEFAULT 'vacation',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_count INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    reason TEXT NULL,
    approver_id INT NULL,
    approver_comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vacations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_vacations_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_vacations_approver FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_vacations_user (user_id),
    INDEX idx_vacations_department (department_id),
    INDEX idx_vacations_status (status)
) ENGINE=InnoDB;

-- Reuse the existing notifications table for vacation events.
-- task_id must become nullable, and a new vacation_id column is added
-- so vacation-related notifications don't need a fake task row.
ALTER TABLE notifications
    MODIFY COLUMN task_id INT NULL;

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS vacation_id INT NULL AFTER task_id;

ALTER TABLE notifications
    ADD CONSTRAINT fk_notifications_vacation FOREIGN KEY (vacation_id) REFERENCES vacations(id) ON DELETE CASCADE;
