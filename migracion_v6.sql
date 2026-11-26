-- =====================================================================
--  CRM Clínica de Rodillas — Migración v6  (correr DESPUÉS de v2 a v5)
--  Solo dos roles: admin y asistente.
--  * asistente: citas, asistencia, cobros, pacientes, expediente y notas, cumpleaños y ausencias.
--  * admin: todo lo anterior + corte de caja, historial de cortes, cancelar recibos,
--    usuarios y horarios.
--  Los usuarios que tenían otro rol (recepción, médico, terapeuta, contabilidad) pasan a asistente.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE usuarios
  MODIFY rol ENUM('admin','recepcion','medico','terapeuta','contabilidad','asistente') NOT NULL DEFAULT 'recepcion';

UPDATE usuarios SET rol = 'asistente' WHERE rol <> 'admin';

ALTER TABLE usuarios
  MODIFY rol ENUM('admin','asistente') NOT NULL DEFAULT 'asistente';

-- Revisa quién quedó como administrador:
-- SELECT nombre, usuario, rol, activo FROM usuarios ORDER BY rol, nombre;
