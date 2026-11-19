<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/citas_lib.php';

$u = requiere_staff_api();
solo_post();

$idCita = (int)($_POST['id_cita'] ?? 0);
if ($idCita <= 0) error_usuario('Falta el ID de la cita.');

try {
    $st = $pdo->prepare('SELECT * FROM citas WHERE id_cita = ?');
    $st->execute([$idCita]);
    $antes = $st->fetch();
    if (!$antes) error_usuario('La cita no existe.', 404);
    if ($antes['estado'] === 'Cancelada') error_usuario('La cita está cancelada; agenda una nueva.');

    $d = datos_cita_desde_post($pdo, $idCita);
    $d[':id_cita'] = $idCita;

    $st = $pdo->prepare(
        'UPDATE citas SET fecha = :fecha, hora = :hora, paciente = :paciente, telefono = :telefono,
                edad = :edad, estado = :estado, asistencia = :asistencia, ya_es_paciente = :ya_es_paciente,
                tipo_tratamiento = :tipo, asistente = :asistente, sucursal = :sucursal,
                id_paciente = :id_paciente, id_sucursal = :id_sucursal, id_servicio = :id_servicio,
                es_express = :es_express
          WHERE id_cita = :id_cita'
    );
    $st->execute($d);
    bitacora($pdo, $u['id'], 'citas', $idCita, 'editar', $antes, $d);

    responder(['status' => 'success', 'message' => 'Cita actualizada correctamente.']);
} catch (Throwable $e) {
    error_servidor($e);
}
