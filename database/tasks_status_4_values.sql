-- Задачи: оставляем только 4 статуса:
--   new            — ожидание подтверждения (новая задача)
--   in_progress    — в процессе
--   clarification  — обратный запрос / уточнение
--   done           — выполнено (erledigt)
--
-- Сначала переводим старые closed/cancelled в done, затем сужаем ENUM.
-- Порядок важен: ALTER упадёт, если останутся строки со старыми значениями.

UPDATE tasks SET status = 'done' WHERE status IN ('closed', 'cancelled');

ALTER TABLE tasks
    MODIFY status ENUM('new', 'in_progress', 'clarification', 'done') NOT NULL DEFAULT 'new';
