-- Due-date reminders (run in phpMyAdmin if already installed)

CREATE TABLE IF NOT EXISTS task_reminder_log (
  task_id INT UNSIGNED NOT NULL,
  reminder_type ENUM('before', 'due', 'overdue') NOT NULL,
  reference_date DATE NOT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (task_id, reminder_type, reference_date),
  CONSTRAINT fk_reminder_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('notify_reminders', '1'),
  ('reminder_days_before', '1'),
  ('reminder_on_due', '1'),
  ('reminder_overdue', '1'),
  ('reminder_cron_key', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
