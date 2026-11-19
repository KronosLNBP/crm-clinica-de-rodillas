-- =====================================================================
--  CRM Clínica de Rodillas — Migración v2 (MariaDB 10.11)
--  Objetivo: normalizar sin romper el PHP actual.
--  * Las columnas de texto viejas (paciente, telefono, sucursal, etc.)
--    se CONSERVAN; se agregan columnas id_* nuevas al lado.
--  * Cuando el PHP ya use las columnas nuevas, se pueden borrar las viejas
--    (sección 9, comentada).
--  HAZ UN RESPALDO ANTES DE CORRERLO.  Pruébalo primero en una copia.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '-06:00';          -- Hora de CDMX (ver nota en README)
START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. SUCURSALES: nombres iguales a los que usa `citas` + falta Tepeyac
-- ---------------------------------------------------------------------
UPDATE sucursales SET nombre_sucursal = TRIM(REPLACE(nombre_sucursal, 'Sucursal ', ''));

INSERT INTO sucursales (nombre_sucursal, ciudad)
SELECT 'Tepeyac', 'CDMX'          -- verifica la ciudad
WHERE NOT EXISTS (SELECT 1 FROM sucursales WHERE nombre_sucursal = 'Tepeyac');
INSERT INTO sucursales (nombre_sucursal, ciudad)
SELECT 'Balbuena', 'CDMX'         -- aparece en el menú de index.php
WHERE NOT EXISTS (SELECT 1 FROM sucursales WHERE nombre_sucursal = 'Balbuena');

ALTER TABLE sucursales
  ADD COLUMN clave  VARCHAR(5) NULL,                 -- prefijo de folio: DV-000001
  ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1,
  ADD UNIQUE KEY uq_sucursal_nombre (nombre_sucursal);

UPDATE sucursales SET clave = CASE nombre_sucursal
  WHEN 'Del Valle' THEN 'DV' WHEN 'Pachuca' THEN 'PAC' WHEN 'Puebla' THEN 'PUE'
  WHEN 'Tepeyac' THEN 'TEP' WHEN 'Balbuena' THEN 'BAL'
  ELSE UPPER(LEFT(nombre_sucursal, 3)) END;
ALTER TABLE sucursales MODIFY clave VARCHAR(5) NOT NULL, ADD UNIQUE KEY uq_sucursal_clave (clave);

-- Bloqueo de fuerza bruta en los logins
CREATE TABLE intentos_login (
  id     BIGINT AUTO_INCREMENT PRIMARY KEY,
  ip     VARCHAR(45) NOT NULL,
  tipo   VARCHAR(10) NOT NULL,                       -- 'staff' | 'paciente'
  fecha  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_int (ip, tipo, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. USUARIOS (login real con password_hash + roles)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id_usuario     INT AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(120) NOT NULL,
  usuario        VARCHAR(60)  NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,              -- password_hash() de PHP
  rol            ENUM('admin','recepcion','medico','terapeuta') NOT NULL DEFAULT 'recepcion',
  id_sucursal    INT NULL,                           -- NULL = todas
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso  DATETIME NULL,
  creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuario_sucursal FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. SERVICIOS (catálogo con precio; evita servicio = '200' o 'ssss')
-- ---------------------------------------------------------------------
CREATE TABLE servicios (
  id_servicio  INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(120) NOT NULL UNIQUE,
  precio       DECIMAL(10,2) NOT NULL DEFAULT 0,
  sesiones     INT NOT NULL DEFAULT 1,               -- para paquetes
  activo       TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO servicios (nombre)
SELECT DISTINCT TRIM(tipo_tratamiento) FROM citas
WHERE tipo_tratamiento IS NOT NULL AND TRIM(tipo_tratamiento) <> '';

-- ---------------------------------------------------------------------
-- 4. PACIENTES (hoy el paciente se repite en cada cita)
-- ---------------------------------------------------------------------
CREATE TABLE pacientes (
  id_paciente       INT AUTO_INCREMENT PRIMARY KEY,
  nombre            VARCHAR(150) NOT NULL,
  telefono          VARCHAR(20)  NOT NULL,
  email             VARCHAR(150) NULL,
  fecha_nacimiento  DATE NULL,                       -- mejor que guardar edad
  sexo              ENUM('F','M','Otro') NULL,
  origen            VARCHAR(60) NULL,                -- Facebook, Google, Referido...
  id_sucursal       INT NULL,                        -- sucursal habitual
  notas             TEXT NULL,
  acepto_aviso_privacidad TINYINT(1) NOT NULL DEFAULT 0,
  creado_en         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pac_tel (telefono),
  KEY idx_pac_nombre (nombre),
  UNIQUE KEY uq_pac_nombre_tel (nombre, telefono),
  CONSTRAINT fk_pac_sucursal FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un paciente por combinación nombre+teléfono (revisa duplicados después, sección 10)
INSERT IGNORE INTO pacientes (nombre, telefono)      -- IGNORE: "KAREN" y "Karen" con el mismo tel. son la misma persona
SELECT DISTINCT TRIM(paciente), TRIM(telefono) FROM citas;

-- ---------------------------------------------------------------------
-- 5. CITAS: columnas relacionales + estados controlados
-- ---------------------------------------------------------------------
ALTER TABLE citas
  ADD COLUMN id_paciente  INT NULL AFTER id_cita,
  ADD COLUMN id_sucursal  INT NULL AFTER sucursal,
  ADD COLUMN id_servicio  INT NULL AFTER tipo_tratamiento,
  ADD COLUMN id_usuario   INT NULL AFTER asistente,          -- quién atiende
  ADD COLUMN id_creado_por INT NULL,                          -- quién agendó
  ADD COLUMN es_express   TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN notas        TEXT NULL,
  ADD COLUMN motivo_cancelacion VARCHAR(255) NULL,
  ADD COLUMN actualizado_en TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;

UPDATE citas c JOIN pacientes p ON p.nombre = TRIM(c.paciente) AND p.telefono = TRIM(c.telefono)
   SET c.id_paciente = p.id_paciente;
UPDATE citas c JOIN sucursales s ON s.nombre_sucursal = TRIM(c.sucursal)
   SET c.id_sucursal = s.id_sucursal;
UPDATE citas c JOIN servicios sv ON sv.nombre = TRIM(c.tipo_tratamiento)
   SET c.id_servicio = sv.id_servicio;

-- "Express" se conserva como estado porque index.php lo usa (colores y filtros);
-- además se marca es_express para que a futuro sea un tipo y no un estado.
UPDATE citas SET es_express = 1 WHERE estado = 'Express';
UPDATE citas SET estado = 'Agendado' WHERE estado IS NULL OR estado = '';
UPDATE citas SET asistencia = 'Pendiente' WHERE asistencia NOT IN ('Pendiente','Asistió','No Asistió');
UPDATE citas SET edad = NULL WHERE edad IS NOT NULL AND (edad < 5 OR edad > 110);

ALTER TABLE citas
  MODIFY estado ENUM('Agendado','Confirmada','Express','Reagendada','Cancelada','Completada')
         NOT NULL DEFAULT 'Agendado',
  MODIFY asistencia ENUM('Pendiente','Asistió','No Asistió') NOT NULL DEFAULT 'Pendiente',
  ADD KEY idx_cita_fecha (fecha, hora),
  ADD KEY idx_cita_suc_fecha (id_sucursal, fecha),
  ADD KEY idx_cita_tel (telefono),
  ADD CONSTRAINT fk_cita_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente),
  ADD CONSTRAINT fk_cita_sucursal FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal),
  ADD CONSTRAINT fk_cita_servicio FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio),
  ADD CONSTRAINT fk_cita_usuario  FOREIGN KEY (id_usuario)  REFERENCES usuarios(id_usuario),
  ADD CONSTRAINT fk_cita_creador  FOREIGN KEY (id_creado_por) REFERENCES usuarios(id_usuario),
  ADD CONSTRAINT chk_cita_edad CHECK (edad IS NULL OR edad BETWEEN 0 AND 120);

-- ---------------------------------------------------------------------
-- 6. REMISIONES: método de pago igual al corte, estado, quién la hizo
-- ---------------------------------------------------------------------
ALTER TABLE remisiones_clientes
  ADD COLUMN id_sucursal INT NULL AFTER sucursal,
  ADD COLUMN credito     DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER tarjeta,
  ADD COLUMN debito      DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN estado      ENUM('Vigente','Cancelada') NOT NULL DEFAULT 'Vigente',
  ADD COLUMN id_usuario  INT NULL,
  ADD COLUMN id_corte    INT NULL,                           -- en qué corte entró
  ADD COLUMN creado_en   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Rellenar sucursal faltante desde la cita
UPDATE remisiones_clientes r JOIN citas c ON c.id_cita = r.id_cita
   SET r.sucursal = COALESCE(r.sucursal, c.sucursal);
UPDATE remisiones_clientes r JOIN sucursales s ON s.nombre_sucursal = r.sucursal
   SET r.id_sucursal = s.id_sucursal;
-- "tarjeta" histórica se pasa a crédito (ajusta si era débito)
UPDATE remisiones_clientes SET credito = COALESCE(tarjeta,0);
-- Remisiones en $0 o con servicio que es un número = basura de pruebas
UPDATE remisiones_clientes SET estado = 'Cancelada'
 WHERE COALESCE(total,0) = 0 OR servicio REGEXP '^[0-9.]+$';
-- Remisiones emitidas dos veces (misma cita, servicio y monto): se deja la más reciente
UPDATE remisiones_clientes r
  JOIN remisiones_clientes r2
    ON r2.id_cita = r.id_cita AND r2.servicio = r.servicio AND r2.total = r.total
   AND r2.id_remision > r.id_remision
   SET r.estado = 'Cancelada';

ALTER TABLE remisiones_clientes
  MODIFY efectivo      DECIMAL(10,2) NOT NULL DEFAULT 0,
  MODIFY transferencia DECIMAL(10,2) NOT NULL DEFAULT 0,
  MODIFY total         DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD KEY idx_rem_folio (id_sucursal, folio),
  ADD KEY idx_rem_fecha (fecha),
  ADD CONSTRAINT fk_rem_cita     FOREIGN KEY (id_cita)     REFERENCES citas(id_cita),
  ADD CONSTRAINT fk_rem_sucursal FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal),
  ADD CONSTRAINT fk_rem_usuario  FOREIGN KEY (id_usuario)  REFERENCES usuarios(id_usuario);
-- Cuando el PHP llene credito/debito en lugar de tarjeta, activa:
-- ALTER TABLE remisiones_clientes ADD CONSTRAINT chk_rem_total
--   CHECK (total = efectivo + credito + debito + transferencia);

-- Conceptos de la remisión (una remisión, varios servicios)
CREATE TABLE remision_detalle (
  id_detalle   INT AUTO_INCREMENT PRIMARY KEY,
  id_remision  INT NOT NULL,
  id_servicio  INT NULL,
  descripcion  VARCHAR(150) NOT NULL,
  cantidad     INT NOT NULL DEFAULT 1,
  precio       DECIMAL(10,2) NOT NULL,
  descuento    DECIMAL(10,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_det_rem FOREIGN KEY (id_remision) REFERENCES remisiones_clientes(id_remision) ON DELETE CASCADE,
  CONSTRAINT fk_det_srv FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Folios consecutivos sin choques (uno por sucursal)
CREATE TABLE folios (
  id_sucursal  INT PRIMARY KEY,
  ultimo       INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_folio_suc FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal)
) ENGINE=InnoDB;
INSERT INTO folios (id_sucursal) SELECT id_sucursal FROM sucursales;

-- ---------------------------------------------------------------------
-- 7. CORTES: utf8mb4 (hoy es latin1), uno por sucursal y día
-- ---------------------------------------------------------------------
ALTER TABLE cortes_diarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE cortes_diarios
  ADD COLUMN id_sucursal INT NULL AFTER sucursal,
  ADD COLUMN id_usuario  INT NULL,
  ADD COLUMN efectivo_contado DECIMAL(10,2) NULL,             -- arqueo físico
  ADD COLUMN diferencia DECIMAL(10,2) AS (efectivo_contado - efectivo) VIRTUAL,
  ADD COLUMN observaciones TEXT NULL;

UPDATE cortes_diarios cd JOIN sucursales s ON s.nombre_sucursal = cd.sucursal
   SET cd.id_sucursal = s.id_sucursal;

ALTER TABLE cortes_diarios
  ADD UNIQUE KEY uq_corte_dia (id_sucursal, fecha),
  ADD CONSTRAINT fk_corte_suc FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal),
  ADD CONSTRAINT fk_corte_usr FOREIGN KEY (id_usuario)  REFERENCES usuarios(id_usuario);

ALTER TABLE remisiones_clientes
  ADD CONSTRAINT fk_rem_corte FOREIGN KEY (id_corte) REFERENCES cortes_diarios(id_corte);

-- ---------------------------------------------------------------------
-- 8. BITÁCORA (quién cambió qué; útil para auditoría y dinero)
-- ---------------------------------------------------------------------
CREATE TABLE bitacora (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_usuario  INT NULL,
  tabla       VARCHAR(50) NOT NULL,
  id_registro INT NOT NULL,
  accion      ENUM('crear','editar','cancelar','eliminar','login') NOT NULL,
  antes       LONGTEXT NULL CHECK (antes IS NULL OR JSON_VALID(antes)),
  despues     LONGTEXT NULL CHECK (despues IS NULL OR JSON_VALID(despues)),
  ip          VARCHAR(45) NULL,
  fecha       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bit_reg (tabla, id_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expediente clínico básico de rodilla (opcional pero muy útil)
CREATE TABLE valoraciones (
  id_valoracion INT AUTO_INCREMENT PRIMARY KEY,
  id_paciente   INT NOT NULL,
  id_cita       INT NULL,
  rodilla       ENUM('Derecha','Izquierda','Ambas') NULL,
  diagnostico   VARCHAR(255) NULL,
  dolor_eva     TINYINT NULL CHECK (dolor_eva BETWEEN 0 AND 10),
  notas         TEXT NULL,
  id_usuario    INT NULL,
  creado_en     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_val_pac  FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente),
  CONSTRAINT fk_val_cita FOREIGN KEY (id_cita)     REFERENCES citas(id_cita),
  CONSTRAINT fk_val_usr  FOREIGN KEY (id_usuario)  REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

-- ---------------------------------------------------------------------
-- 9. (DESPUÉS) cuando el PHP ya use id_paciente / id_sucursal / id_servicio
-- ---------------------------------------------------------------------
-- ALTER TABLE citas DROP COLUMN paciente, DROP COLUMN telefono, DROP COLUMN edad,
--   DROP COLUMN sucursal, DROP COLUMN tipo_tratamiento, DROP COLUMN asistente,
--   DROP COLUMN ya_es_paciente;   -- se calcula: ¿tiene citas previas con asistencia?
-- ALTER TABLE citas MODIFY id_paciente INT NOT NULL, MODIFY id_sucursal INT NOT NULL;
-- ALTER TABLE remisiones_clientes DROP COLUMN tarjeta;
-- ALTER TABLE cortes_diarios DROP COLUMN detalles_json;  -- se arma con remisiones.id_corte

-- ---------------------------------------------------------------------
-- 10. CONSULTAS DE REVISIÓN (córrelas y limpia a mano)
-- ---------------------------------------------------------------------
-- a) Citas contradictorias: canceladas pero "asistió"
-- SELECT id_cita, fecha, paciente, estado, asistencia FROM citas
--  WHERE estado = 'Cancelada' AND asistencia = 'Asistio';
-- b) Mismo teléfono con distintos nombres / mismo nombre con distintos teléfonos
-- SELECT telefono, GROUP_CONCAT(DISTINCT nombre) FROM pacientes GROUP BY telefono HAVING COUNT(*) > 1;
-- SELECT nombre, GROUP_CONCAT(DISTINCT telefono) FROM pacientes GROUP BY nombre HAVING COUNT(*) > 1;
-- c) Folios repetidos
-- SELECT id_sucursal, folio, COUNT(*) FROM remisiones_clientes GROUP BY id_sucursal, folio HAVING COUNT(*) > 1;
-- d) Empalmes de horario en la misma sucursal
-- SELECT id_sucursal, fecha, hora, COUNT(*) FROM citas WHERE estado <> 'Cancelada'
--  GROUP BY id_sucursal, fecha, hora HAVING COUNT(*) > 1;
-- e) Corte que menciona citas sin remisión
-- SELECT * FROM cortes_diarios WHERE detalles_json LIKE '%"id_cita":"24"%';
