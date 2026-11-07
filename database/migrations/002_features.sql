-- Recursos: 2FA, notificações (e-mail/push), termo de responsabilidade, presença,
-- Pix, GPX, interação nas fotos e fila de e-mails.

ALTER TABLE users
  ADD COLUMN totp_secret VARCHAR(255) NULL AFTER session_version,
  ADD COLUMN totp_enabled_at DATETIME NULL AFTER totp_secret,
  ADD COLUMN totp_recovery TEXT NULL AFTER totp_enabled_at,
  ADD COLUMN totp_last_step BIGINT NULL AFTER totp_recovery,
  ADD COLUMN notify_email TINYINT(1) NOT NULL DEFAULT 1 AFTER show_phone,
  ADD COLUMN notify_push TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_email;

ALTER TABLE events
  ADD COLUMN requires_waiver TINYINT(1) NOT NULL DEFAULT 1 AFTER registration_until,
  ADD COLUMN pix_amount DECIMAL(10,2) NULL AFTER requires_waiver,
  ADD COLUMN gpx_file VARCHAR(64) NULL AFTER cover,
  ADD COLUMN track_json MEDIUMTEXT NULL AFTER gpx_file,
  ADD COLUMN track_km DECIMAL(8,2) NULL AFTER track_json,
  ADD COLUMN track_elevation INT NULL AFTER track_km,
  ADD COLUMN announced_at DATETIME NULL AFTER status,
  ADD COLUMN reminder_sent_at DATETIME NULL AFTER announced_at;

-- eventos que já existiam não devem disparar aviso de "trilha nova"
UPDATE events SET announced_at = NOW() WHERE announced_at IS NULL;

ALTER TABLE event_rsvps
  ADD COLUMN waiver_accepted_at DATETIME NULL,
  ADD COLUMN waiver_hash CHAR(64) NULL,
  ADD COLUMN waiver_ip VARCHAR(45) NULL,
  ADD COLUMN attended TINYINT(1) NULL,
  ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'none',
  ADD COLUMN payment_receipt VARCHAR(64) NULL,
  ADD COLUMN payment_informed_at DATETIME NULL,
  ADD COLUMN paid_at DATETIME NULL,
  ADD COLUMN paid_by INT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS mail_queue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(160) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NULL,
  send_after DATETIME NOT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_mail_pending (sent_at, send_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  endpoint VARCHAR(700) NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  p256dh VARCHAR(120) NOT NULL,
  auth VARCHAR(40) NOT NULL,
  user_agent VARCHAR(255) NULL,
  failures TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_success_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_push_endpoint (endpoint_hash),
  CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  body VARCHAR(300) NOT NULL,
  url VARCHAR(255) NOT NULL,
  only_user_ids TEXT NULL,
  pushed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_notif_pushed (pushed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_likes (
  photo_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (photo_id, user_id),
  CONSTRAINT fk_like_photo FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
  CONSTRAINT fk_like_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  photo_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_comment_photo (photo_id, id),
  CONSTRAINT fk_comment_photo FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
  CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_tags (
  photo_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  tagged_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (photo_id, user_id),
  CONSTRAINT fk_tag_photo FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
  CONSTRAINT fk_tag_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tag_by FOREIGN KEY (tagged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
