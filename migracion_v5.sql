-- =====================================================================
--  CRM Clínica de Rodillas — Migración v5  (correr DESPUÉS de v2, v3 y v4)
--  * Lugar de procedencia del paciente (la fecha de nacimiento ya existe desde la v2)
--  * Registro de felicitaciones de cumpleaños enviadas (para no repetirlas)
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE pacientes
  ADD COLUMN procedencia VARCHAR(120) NULL AFTER fecha_nacimiento;

CREATE TABLE felicitaciones (
  id_paciente  INT NOT NULL,
  anio         SMALLINT NOT NULL,
  id_usuario   INT NULL,
  enviada_en   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_paciente, anio),
  CONSTRAINT fk_fel_pac FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente) ON DELETE CASCADE,
  CONSTRAINT fk_fel_usr FOREIGN KEY (id_usuario)  REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
