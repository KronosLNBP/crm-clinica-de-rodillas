-- =====================================================================
--  CRM Clínica de Rodillas — Migración v8  (correr DESPUÉS de v7)
--  Remisiones con el formato impreso de la clínica: varios conceptos,
--  observaciones, método de pago y quién atendió. Pueden ir sin cita
--  (por ejemplo, venta de un producto). Siguen el mismo folio por sucursal
--  y entran al corte de caja del día.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE remisiones_clientes
  ADD COLUMN observaciones VARCHAR(500) NULL,
  ADD COLUMN metodo_pago   VARCHAR(40)  NULL,
  ADD COLUMN atendio       VARCHAR(100) NULL;
