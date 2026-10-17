CREATE TABLE IF NOT EXISTS jobs (
    id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    event        VARCHAR(255)     NOT NULL,
    listener     VARCHAR(255)     NOT NULL,
    payload      JSON             NOT NULL,
    status       ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    on_failure   ENUM('retry','stop') NOT NULL DEFAULT 'retry',
    error        TEXT             NULL,
    run_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_jobs_worker ON jobs (status, run_at);
