CREATE TABLE IF NOT EXISTS `tickets` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `assignee_id` INT NULL,
    `subject`     VARCHAR(200) NOT NULL,
    `body`        TEXT NOT NULL,
    `status`      ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
    `priority`    ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    `created_at`  DATETIME NULL,
    `updated_at`  DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_tickets_user ON tickets (user_id);
CREATE INDEX idx_tickets_status ON tickets (status);

CREATE TABLE IF NOT EXISTS `replies` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id`   INT NOT NULL,
    `user_id`     INT NOT NULL,
    `body`        TEXT NOT NULL,
    `is_internal` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  DATETIME NULL,
    `updated_at`  DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_replies_ticket ON replies (ticket_id);

CREATE TABLE IF NOT EXISTS `attachments` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id`     INT NOT NULL,
    `user_id`       INT NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name`   VARCHAR(100) NOT NULL,
    `mime`          VARCHAR(100) NOT NULL,
    `size`          INT NOT NULL,
    `created_at`    DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_attachments_ticket ON attachments (ticket_id);

CREATE TABLE IF NOT EXISTS `activities` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id`  INT NOT NULL,
    `user_id`    INT NULL,
    `action`     VARCHAR(50) NOT NULL,
    `details`    VARCHAR(255) NULL,
    `created_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_activities_ticket ON activities (ticket_id);

-- event_key makes the async notification listener idempotent: the queue
-- delivers at least once, a retried job must not notify twice.
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `ticket_id`   INT NOT NULL,
    `message_key` VARCHAR(100) NOT NULL,
    `params`      JSON NULL,
    `event_key`   VARCHAR(150) NOT NULL UNIQUE,
    `read_at`     DATETIME NULL,
    `created_at`  DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_notifications_user ON notifications (user_id);
