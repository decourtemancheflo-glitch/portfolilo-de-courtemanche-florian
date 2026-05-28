-- =============================================
-- Portfolio Florian DC — Base de données
-- Exécuter une seule fois pour initialiser
-- =============================================

CREATE DATABASE IF NOT EXISTS portfolio_fdc
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE portfolio_fdc;

-- Table des messages de contact
CREATE TABLE IF NOT EXISTS messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom         VARCHAR(100)  NOT NULL,
  email       VARCHAR(255)  NOT NULL,
  sujet       VARCHAR(200)  DEFAULT '',
  message     TEXT          NOT NULL,
  ip          VARCHAR(45)   DEFAULT NULL,          -- IPv4 ou IPv6
  lu          TINYINT(1)    NOT NULL DEFAULT 0,    -- 0 = non lu, 1 = lu
  cree_le     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_lu      (lu),
  INDEX idx_cree_le (cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
