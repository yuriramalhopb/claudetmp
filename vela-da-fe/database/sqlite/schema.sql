-- Vela da Fé — schema SQLite 3 (zero configuração: um único arquivo em storage/).
-- Valores em dinheiro são guardados em CENTAVOS (INTEGER).

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    session_version INTEGER NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
);

CREATE TABLE settings (
    skey VARCHAR(80) NOT NULL PRIMARY KEY,
    svalue TEXT NULL,
    updated_at DATETIME NULL
);

CREATE TABLE candle_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(60) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#f5f0e6',
    description VARCHAR(255) NULL,
    price INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

CREATE TABLE candle_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label VARCHAR(60) NOT NULL,
    hours INTEGER NOT NULL,
    price INTEGER NOT NULL DEFAULT 0,
    highlight INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

CREATE TABLE candles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code CHAR(12) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    type_id INTEGER NOT NULL REFERENCES candle_types (id),
    plan_id INTEGER NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'outros',
    name VARCHAR(80) NOT NULL,
    show_name INTEGER NOT NULL DEFAULT 1,
    for_whom VARCHAR(100) NULL,
    intention TEXT NULL,
    show_intention INTEGER NOT NULL DEFAULT 1,
    email VARCHAR(160) NULL,
    notify_email INTEGER NOT NULL DEFAULT 0,
    mass_intention INTEGER NOT NULL DEFAULT 0,
    mass_done_at DATETIME NULL,
    status VARCHAR(20) NOT NULL,
    mod_status VARCHAR(20) NOT NULL DEFAULT 'approved',
    amount INTEGER NOT NULL DEFAULT 0,
    hours INTEGER NOT NULL,
    payment_method VARCHAR(20) NULL,
    paid_claimed_at DATETIME NULL,
    paid_at DATETIME NULL,
    lit_at DATETIME NULL,
    expires_at DATETIME NULL,
    prayer_count INTEGER NOT NULL DEFAULT 0,
    reminder_sent_at DATETIME NULL,
    expired_notice_at DATETIME NULL,
    ip_hash CHAR(64) NULL,
    relit_from INTEGER NULL,
    admin_note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
);
CREATE INDEX idx_candles_wall ON candles (status, expires_at);
CREATE INDEX idx_candles_type ON candles (type_id);
CREATE INDEX idx_candles_mass ON candles (mass_intention, status);
CREATE INDEX idx_candles_email ON candles (email);
CREATE INDEX idx_candles_ip ON candles (ip_hash, created_at);

CREATE TABLE candle_prayers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    candle_id INTEGER NOT NULL REFERENCES candles (id) ON DELETE CASCADE,
    visitor_hash CHAR(64) NOT NULL,
    day DATE NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE (candle_id, visitor_hash, day)
);

CREATE TABLE prayers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    body TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

CREATE TABLE daily_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    text VARCHAR(600) NOT NULL,
    reference VARCHAR(80) NULL,
    show_on DATE NULL,
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

CREATE TABLE mass_times (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    weekday INTEGER NOT NULL,
    time CHAR(5) NOT NULL,
    label VARCHAR(100) NOT NULL,
    location VARCHAR(120) NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

CREATE TABLE testimonials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(80) NOT NULL,
    text TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    ip_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL
);
CREATE INDEX idx_testimonials_status ON testimonials (status, created_at);

CREATE TABLE mail_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    to_email VARCHAR(160) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    sent_at DATETIME NULL
);
CREATE INDEX idx_mail_status ON mail_queue (status, created_at);

CREATE TABLE rate_limits (
    rl_key CHAR(64) NOT NULL PRIMARY KEY,
    hits INTEGER NOT NULL DEFAULT 0,
    reset_at INTEGER NOT NULL
);
CREATE INDEX idx_rl_reset ON rate_limits (reset_at);

CREATE TABLE audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    action VARCHAR(60) NOT NULL,
    details VARCHAR(500) NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL
);
CREATE INDEX idx_audit_created ON audit_log (created_at);
