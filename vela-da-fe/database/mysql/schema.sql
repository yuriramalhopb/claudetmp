-- Vela da Fé — schema MySQL 5.7+/8 / MariaDB 10.3+ (utf8mb4).
-- Criado automaticamente na primeira execução. Valores em dinheiro são guardados em CENTAVOS (INT).

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin',          -- admin | moderator
    status VARCHAR(20) NOT NULL DEFAULT 'active',       -- active | blocked
    session_version INT UNSIGNED NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    skey VARCHAR(80) NOT NULL PRIMARY KEY,
    svalue MEDIUMTEXT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candle_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#f5f0e6',
    description VARCHAR(255) NULL,
    price INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candle_plans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(60) NOT NULL,
    hours INT UNSIGNED NOT NULL,
    price INT UNSIGNED NOT NULL DEFAULT 0,
    highlight TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code CHAR(12) NOT NULL,                              -- identificador público (não sequencial)
    token_hash CHAR(64) NOT NULL,                        -- sha256 do token secreto de gerenciamento
    type_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'outros',
    name VARCHAR(80) NOT NULL,
    show_name TINYINT(1) NOT NULL DEFAULT 1,
    for_whom VARCHAR(100) NULL,
    intention TEXT NULL,
    show_intention TINYINT(1) NOT NULL DEFAULT 1,
    email VARCHAR(160) NULL,
    notify_email TINYINT(1) NOT NULL DEFAULT 0,
    mass_intention TINYINT(1) NOT NULL DEFAULT 0,
    mass_done_at DATETIME NULL,
    status VARCHAR(20) NOT NULL,                         -- pending_payment | active | cancelled | removed
    mod_status VARCHAR(20) NOT NULL DEFAULT 'approved',  -- approved | pending | rejected
    amount INT UNSIGNED NOT NULL DEFAULT 0,
    hours INT UNSIGNED NOT NULL,
    payment_method VARCHAR(20) NULL,                     -- free | pix
    paid_claimed_at DATETIME NULL,                       -- a pessoa informou que já pagou
    paid_at DATETIME NULL,
    lit_at DATETIME NULL,
    expires_at DATETIME NULL,
    prayer_count INT UNSIGNED NOT NULL DEFAULT 0,
    reminder_sent_at DATETIME NULL,
    expired_notice_at DATETIME NULL,
    ip_hash CHAR(64) NULL,
    relit_from BIGINT UNSIGNED NULL,
    admin_note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_candles_code (code),
    KEY idx_candles_wall (status, expires_at),
    KEY idx_candles_type (type_id),
    KEY idx_candles_mass (mass_intention, status),
    KEY idx_candles_email (email),
    KEY idx_candles_ip (ip_hash, created_at),
    CONSTRAINT fk_candles_type FOREIGN KEY (type_id) REFERENCES candle_types (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candle_prayers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    candle_id BIGINT UNSIGNED NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    day DATE NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_prayer_once (candle_id, visitor_hash, day),
    CONSTRAINT fk_prayers_candle FOREIGN KEY (candle_id) REFERENCES candles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prayers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    body TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_prayers_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE daily_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    text VARCHAR(600) NOT NULL,
    reference VARCHAR(80) NULL,
    show_on DATE NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mass_times (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    weekday TINYINT UNSIGNED NOT NULL,                   -- 0=domingo ... 6=sábado
    time CHAR(5) NOT NULL,
    label VARCHAR(100) NOT NULL,
    location VARCHAR(120) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE testimonials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    text TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',       -- pending | approved | rejected
    ip_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_testimonials_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(160) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',       -- pending | sent | failed
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    KEY idx_mail_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    rl_key CHAR(64) NOT NULL PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    reset_at BIGINT UNSIGNED NOT NULL,
    KEY idx_rl_reset (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    details VARCHAR(500) NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
