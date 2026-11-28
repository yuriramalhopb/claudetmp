CREATE TABLE IF NOT EXISTS payments (
    id                 BIGINT UNSIGNED NOT NULL PRIMARY KEY,        -- id do pagamento no Mercado Pago
    external_reference VARCHAR(40)   NOT NULL,
    amount             DECIMAL(12,2) NOT NULL,
    description        VARCHAR(255)  NOT NULL DEFAULT '',
    status             VARCHAR(30)   NOT NULL,
    status_detail      VARCHAR(80)   NULL,
    qr_code            TEXT          NULL,
    qr_code_base64     MEDIUMTEXT    NULL,
    ticket_url         VARCHAR(500)  NULL,
    expires_at         DATETIME      NULL,
    created_at         DATETIME      NOT NULL,
    paid_at            DATETIME      NULL,
    KEY idx_status (status),
    KEY idx_external_reference (external_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
