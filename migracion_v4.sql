-- =====================================================================
--  CRM Clínica de Rodillas — Migración v4  (correr DESPUÉS de v2 y v3)
--  Tokens de acceso para la app de Android (la app no usa cookies).
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE api_tokens (
  id_token     INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario   INT NOT NULL,
  token_hash   CHAR(64) NOT NULL UNIQUE,          -- sha256 del token; el token en claro solo lo tiene el teléfono
  dispositivo  VARCHAR(120) NULL,
  creado_en    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_uso   DATETIME NULL,
  expira_en    DATETIME NOT NULL,
  KEY idx_tok_usr (id_usuario),
  CONSTRAINT fk_tok_usr FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
