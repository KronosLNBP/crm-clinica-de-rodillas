<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/citas_lib.php';

$u = requiere_staff_api(ROLES_OPERACION);
solo_post();

try {
    $d = datos_cita_desde_post($pdo, $u);
    $hiper = datos_hiperbarica($pdo, $d[':tipo']);
    $avisos = array_filter([
        aviso_agenda($pdo, $d[':id_sucursal'], $d[':fecha'], $d[':hora'], $d[':es_express'] === 1),
        aviso_valoracion($pdo, $d[':id_paciente'], $hiper),
    ]);
    if ($avisos && ($_POST['forzar'] ?? '') !== '1') {
        responder(['status' => 'advertencia', 'message' => implode(' ', $avisos)]);
    }
    $d[':creado_por'] = $u['id'];
    $colsExtra = $hiper ? ', modalidad, sesiones_paquete, motivo_consulta, referido_por' : '';
    $valsExtra = $hiper ? ', :modalidad, :sesiones_paquete, :motivo_consulta, :referido_por' : '';
    $d += $hiper;

    $st = $pdo->prepare(
        "INSERT INTO citas (fecha, hora, paciente, telefono, edad, estado, asistencia, ya_es_paciente,
                            tipo_tratamiento, asistente, sucursal, id_paciente, id_sucursal, id_servicio,
                            es_express, id_creado_por$colsExtra)
         VALUES (:fecha, :hora, :paciente, :telefono, :edad, :estado, :asistencia, :ya_es_paciente,
                 :tipo, :asistente, :sucursal, :id_paciente, :id_sucursal, :id_servicio,
                 :es_express, :creado_por$valsExtra)"
    );
    $st->execute($d);
    $id = (int)$pdo->lastInsertId();
    bitacora($pdo, $u['id'], 'citas', $id, 'crear', null, $d);

    responder(['status' => 'success', 'message' => 'Cita guardada correctamente.', 'id_cita' => $id]);
} catch (Throwable $e) {
    error_servidor($e);
}
