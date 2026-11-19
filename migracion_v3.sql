-- =====================================================================
--  CRM Clínica de Rodillas — Migración v3  (correr DESPUÉS de migracion_v2.sql)
--  * Horario propio por sucursal y día (Del Valle no abre igual que Tepeyac)
--  * Ausencias / bloqueos de agenda (vacaciones, permisos, sale temprano)
--  * Rol "contabilidad": solo consulta cortes, sin imprimir ni enviar hojas
-- =====================================================================
SET NAMES utf8mb4;

-- Intervalo de las citas por sucursal (minutos)
ALTER TABLE sucursales ADD COLUMN intervalo_min TINYINT UNSIGNED NOT NULL DEFAULT 30;

-- Horario por día. dia_semana: 0=Domingo, 1=Lunes ... 6=Sábado. Sin fila = cerrado ese día.
CREATE TABLE sucursal_horarios (
  id_sucursal  INT NOT NULL,
  dia_semana   TINYINT NOT NULL CHECK (dia_semana BETWEEN 0 AND 6),
  hora_inicio  TIME NOT NULL,
  hora_fin     TIME NOT NULL,
  PRIMARY KEY (id_sucursal, dia_semana),
  CONSTRAINT chk_horario CHECK (hora_fin > hora_inicio),
  CONSTRAINT fk_hor_suc FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horario inicial (el mismo que tenía el calendario: L-S 9:00 a 17:30).
-- Ajústalo por sucursal en  admin.php → Horarios.
INSERT INTO sucursal_horarios (id_sucursal, dia_semana, hora_inicio, hora_fin)
SELECT s.id_sucursal, d.dia, '09:00:00', '17:30:00'
  FROM sucursales s
  JOIN (SELECT 1 AS dia UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6) d;

-- Ausencias y bloqueos de agenda
CREATE TABLE bloqueos_agenda (
  id_bloqueo   INT AUTO_INCREMENT PRIMARY KEY,
  id_sucursal  INT NOT NULL,
  medico       VARCHAR(120) NULL,                  -- "Dr. Palma" (opcional)
  fecha_inicio DATE NOT NULL,
  fecha_fin    DATE NOT NULL,
  hora_inicio  TIME NULL,                          -- NULL = todo el día
  hora_fin     TIME NULL,
  motivo       VARCHAR(150) NOT NULL,              -- Vacaciones, permiso, emergencia, sale temprano...
  id_usuario   INT NULL,
  creado_en    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bloq (id_sucursal, fecha_inicio, fecha_fin),
  CONSTRAINT chk_bloq_fechas CHECK (fecha_fin >= fecha_inicio),
  CONSTRAINT fk_bloq_suc FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal),
  CONSTRAINT fk_bloq_usr FOREIGN KEY (id_usuario)  REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rol de solo consulta para quien revisa los cortes
ALTER TABLE usuarios
  MODIFY rol ENUM('admin','recepcion','medico','terapeuta','contabilidad') NOT NULL DEFAULT 'recepcion';
