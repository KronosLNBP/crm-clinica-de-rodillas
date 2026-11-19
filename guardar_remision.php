<?php
// Registra un cobro (remisión). El FOLIO lo genera el servidor, consecutivo por sucursal (DV-000001),
// dentro de una transacción: no se repite aunque dos personas cobren al mismo tiempo.
// El PDF se sube después con adjuntar_pdf_remision.php (ya con el folio real impreso).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_OPERACION);
solo_post();

$idCita   = (int)($_POST['id_cita'] ?? 0);
$servicio = txt($_POST, 'servicio', 150);
$efectivo = dinero($_POST, 'efectivo');
$credito  = dinero($_POST, 'credito');
$debito   = dinero($_POST, 'debito');
$transf   = dinero($_POST, 'transferencia');
$fecha    = txt($_POST, 'fecha', 10) ?: date('Y-m-d');
$forzar   = ($_POST['forzar'] ?? '') === '1';
$total    = round($efectivo + $credito + $debito + $transf, 2);

if ($idCita <= 0) error_usuario('Falta la cita.');
if (mb_strlen($servicio) < 3 || preg_match('/^[\d.,$\s]+$/', $servicio)) error_usuario('Describe el servicio (no el precio).');
if ($total <= 0) error_usuario('Captura el monto cobrado.');
if (!fecha_valida($fecha)) error_usuario('Fecha no válida.');

verificar_cita_sucursal($pdo, $u, $idCita);

try {
    // Los datos del paciente salen de la BD, no del navegador
    $st = $pdo->prepare(
        "SELECT c.id_cita, c.paciente, c.telefono, c.sucursal, s.id_sucursal, s.clave
           FROM citas c JOIN sucursales s ON s.nombre_sucursal = c.sucursal
          WHERE c.id_cita = ? AND c.estado <> 'Cancelada'"
    );
    $st->execute([$idCita]);
    $cita = $st->fetch();
    if (!$cita) error_usuario('La cita no existe o está cancelada.', 404);

    $st = $pdo->prepare("SELECT folio FROM remisiones_clientes WHERE id_cita = ? AND estado = 'Vigente' ORDER BY id_remision DESC LIMIT 1");
    $st->execute([$idCita]);
    $previo = $st->fetchColumn();
    if ($previo && !$forzar) {
        responder(['status' => 'duplicada', 'folio' => $previo, 'message' => "Esta cita ya tiene la remisión $previo."]);
    }

    $pdo->beginTransaction();
    $pdo->prepare('INSERT IGNORE INTO folios (id_sucursal, ultimo) VALUES (?, 0)')->execute([$cita['id_sucursal']]);
    $pdo->prepare('UPDATE folios SET ultimo = LAST_INSERT_ID(ultimo + 1) WHERE id_sucursal = ?')->execute([$cita['id_sucursal']]);
    $consecutivo = (int)$pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    $folio = $cita['clave'] . '-' . str_pad((string)$consecutivo, 6, '0', STR_PAD_LEFT);

    $st = $pdo->prepare(
        'INSERT INTO remisiones_clientes (id_cita, folio, paciente, telefono, sucursal, id_sucursal, servicio,
                 efectivo, tarjeta, credito, debito, transferencia, total, fecha, id_usuario)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        $idCita, $folio, $cita['paciente'], $cita['telefono'], $cita['sucursal'], $cita['id_sucursal'], $servicio,
        $efectivo, $credito + $debito, $credito, $debito, $transf, $total, $fecha, $u['id'],
    ]);
    $idRem = (int)$pdo->lastInsertId();
    // Si pagó, asistió: no hay que marcarlo a mano
    $pdo->prepare("UPDATE citas SET asistencia = 'Asistió' WHERE id_cita = ?")->execute([$idCita]);
    bitacora($pdo, $u['id'], 'remisiones_clientes', $idRem, 'crear', null, ['folio' => $folio, 'total' => $total]);
    $pdo->commit();

    responder(['status' => 'success', 'folio' => $folio, 'id_remision' => $idRem]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_servidor($e);
}
