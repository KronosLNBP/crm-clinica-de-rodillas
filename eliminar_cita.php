<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_OPERACION);
solo_post();

$idCita = (int)($_POST['id_cita'] ?? 0);
$motivo = txt($_POST, 'motivo', 255);
if ($idCita <= 0) error_usuario('ID de cita no proporcionado.');

verificar_cita_sucursal($pdo, $u, $idCita);

try {
    $st = $pdo->prepare('SELECT * FROM citas WHERE id_cita = ?');
    $st->execute([$idCita]);
    $cita = $st->fetch();
    if (!$cita) error_usuario('La cita no existe.', 404);
    if ($cita['asistencia'] === 'Asistió') error_usuario('No se puede cancelar una cita a la que el paciente ya asistió.');

    $st = $pdo->prepare('SELECT COUNT(*) FROM remisiones_clientes WHERE id_cita = ? AND estado = \'Vigente\'');
    $st->execute([$idCita]);
    if ((int)$st->fetchColumn() > 0) error_usuario('La cita tiene un cobro registrado; cancela primero la remisión.');

    $pdo->prepare("UPDATE citas SET estado = 'Cancelada', motivo_cancelacion = ? WHERE id_cita = ?")
        ->execute([$motivo !== '' ? $motivo : null, $idCita]);
    bitacora($pdo, $u['id'], 'citas', $idCita, 'cancelar', $cita, ['motivo' => $motivo]);

    responder(['status' => 'success', 'message' => 'Cita cancelada.']);
} catch (Throwable $e) {
    error_servidor($e);
}
