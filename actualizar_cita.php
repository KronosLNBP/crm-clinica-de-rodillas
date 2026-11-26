<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/citas_lib.php';

$u = requiere_staff_api(ROLES_OPERACION);
solo_post();

$idCita = (int)($_POST['id_cita'] ?? 0);
if ($idCita <= 0) error_usuario('Falta el ID de la cita.');

try {
    $st = $pdo->prepare('SELECT * FROM citas WHERE id_cita = ?');
    $st->execute([$idCita]);
    $antes = $st->fetch();
    if (!$antes) error_usuario('La cita no existe.', 404);
    if ($antes['estado'] === 'Cancelada') error_usuario('La cita está cancelada; agenda una nueva.');

    verificar_cita_sucursal($pdo, $u, $idCita);
    $d = datos_cita_desde_post($pdo, $u, $idCita);
    // Solo se avisa si cambió la fecha, la hora o la sucursal (editar una cita vieja no debe estorbar)
    $movida = $d[':fecha'] !== $antes['fecha'] || substr($d[':hora'], 0, 5) !== substr($antes['hora'], 0, 5)
           || $d[':sucursal'] !== $antes['sucursal'];
    if ($movida) {
        $aviso = aviso_agenda($pdo, $d[':id_sucursal'], $d[':fecha'], $d[':hora'], $d[':es_express'] === 1);
        if ($aviso && ($_POST['forzar'] ?? '') !== '1') {
            responder(['status' => 'advertencia', 'message' => $aviso]);
        }
    }
    $hiper = datos_hiperbarica($pdo, $d[':tipo']);
    $cambioModalidad = $hiper && (($antes['tipo_tratamiento'] ?? '') !== SERVICIO_HIPERBARICA || ($antes['modalidad'] ?? null) !== $hiper[':modalidad']);
    if ($cambioModalidad && ($_POST['forzar'] ?? '') !== '1' && ($av = aviso_valoracion($pdo, $d[':id_paciente'], $hiper, $idCita))) {
        responder(['status' => 'advertencia', 'message' => $av]);
    }
    $setExtra = $hiper ? ', modalidad = :modalidad, sesiones_paquete = :sesiones_paquete, motivo_consulta = :motivo_consulta, referido_por = :referido_por' : '';
    $d += $hiper;
    $d[':id_cita'] = $idCita;

    $st = $pdo->prepare(
        "UPDATE citas SET fecha = :fecha, hora = :hora, paciente = :paciente, telefono = :telefono,
                edad = :edad, estado = :estado, asistencia = :asistencia, ya_es_paciente = :ya_es_paciente,
                tipo_tratamiento = :tipo, asistente = :asistente, sucursal = :sucursal,
                id_paciente = :id_paciente, id_sucursal = :id_sucursal, id_servicio = :id_servicio,
                es_express = :es_express$setExtra
          WHERE id_cita = :id_cita"
    );
    $st->execute($d);
    bitacora($pdo, $u['id'], 'citas', $idCita, 'editar', $antes, $d);

    responder(['status' => 'success', 'message' => 'Cita actualizada correctamente.']);
} catch (Throwable $e) {
    error_servidor($e);
}
