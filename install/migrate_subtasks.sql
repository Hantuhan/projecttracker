-- Run in phpMyAdmin if you installed before these features existed

CREATE TABLE IF NOT EXISTS subtasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  position INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_subtasks_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  INDEX idx_subtasks_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_comments_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User invite / approval (skip any ALTER that already exists)
-- If a column already exists, phpMyAdmin will error on that line — run the others.

ALTER TABLE users
  ADD COLUMN status ENUM('pending', 'active', 'rejected') NOT NULL DEFAULT 'active' AFTER role;

ALTER TABLE users
  ADD COLUMN invited_by INT UNSIGNED NULL AFTER status;

ALTER TABLE users
  ADD COLUMN approved_at TIMESTAMP NULL AFTER invited_by;

ALTER TABLE users ADD INDEX idx_users_status (status);

UPDATE users SET status = 'active' WHERE status IS NULL OR status = '';
