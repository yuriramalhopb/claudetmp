-- Detalhes completos das trilhas/eventos: estado, categoria, Instagram do evento,
-- contato, vagas, o que está incluso e prazo de inscrição.
ALTER TABLE events
  ADD COLUMN state CHAR(2) NULL AFTER city,
  ADD COLUMN category VARCHAR(20) NULL AFTER is_own,
  ADD COLUMN instagram_url VARCHAR(300) NULL AFTER external_link,
  ADD COLUMN whatsapp VARCHAR(20) NULL AFTER instagram_url,
  ADD COLUMN capacity INT UNSIGNED NULL AFTER whatsapp,
  ADD COLUMN includes TEXT NULL AFTER capacity,
  ADD COLUMN registration_until DATE NULL AFTER includes;

CREATE INDEX idx_events_state ON events (state);
