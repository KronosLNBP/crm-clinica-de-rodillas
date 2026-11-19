<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/citas_lib.php';

$u = requiere_staff_api();
solo_post();

try {
    $d = datos_cita_desde_post($pdo);
    $d[':creado_por'] = $u['id'];

    $st = $pdo->prepare(
        'INSERT INTO citas (fecha, hora, paciente, telefono, edad, estado, asistencia, ya_es_paciente,
                            tipo_tratamiento, asistente, sucursal, id_paciente, id_sucursal, id_servicio,
                            es_express, id_creado_por)
         VALUES (:fecha, :hora, :paciente, :telefono, :edad, :estado, :asistencia, :ya_es_paciente,
                 :tipo, :asistente, :sucursal, :id_paciente, :id_sucursal, :id_servicio,
                 :es_express, :creado_por)'
    );
    $st->execute($d);
    $id = (int)$pdo->lastInsertId();
    bitacora($pdo, $u['id'], 'citas', $id, 'crear', null, $d);

    responder(['status' => 'success', 'message' => 'Cita guardada correctamente.', 'id_cita' => $id]);
} catch (Throwable $e) {
    error_servidor($e);
}
