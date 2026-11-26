-- =====================================================================
--  CRM Clínica de Rodillas — Migración v7  (correr DESPUÉS de v2 a v6)
--  Cámara Hiperbárica (camarahiperbarica.mx, Grupo Empresarial Díaz):
--  modalidad (valoración / sesión / paquete), número de sesiones del paquete,
--  motivo de consulta y médico que refiere.
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO servicios (nombre)
SELECT 'Cámara Hiperbárica' WHERE NOT EXISTS (SELECT 1 FROM servicios WHERE nombre = 'Cámara Hiperbárica');

ALTER TABLE citas
  ADD COLUMN modalidad ENUM('Valoración médica','Sesión individual','Paquete de sesiones') NULL AFTER id_servicio,
  ADD COLUMN sesiones_paquete TINYINT UNSIGNED NULL AFTER modalidad,
  ADD COLUMN motivo_consulta VARCHAR(80) NULL AFTER sesiones_paquete,
  ADD COLUMN referido_por VARCHAR(120) NULL AFTER motivo_consulta;
