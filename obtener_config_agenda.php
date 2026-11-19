<?php
// Horarios por sucursal + ausencias en un rango. Lo usa el calendario para dibujar
// solo las horas en que abre cada sucursal y marcar los días/horas bloqueados.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api();
$desde = txt($_GET, 'desde', 10);
$hasta = txt($_GET, 'hasta', 10);

try {
    $suc = sucursal_del_usuario($pdo, $u);
    $sql = 'SELECT id_sucursal, nombre_sucursal, clave, intervalo_min FROM sucursales WHERE activo = 1';
    $p = [];
    if ($suc) { $sql .= ' AND id_sucursal = ?'; $p[] = $suc['id_sucursal']; }
    $st = $pdo->prepare($sql . ' ORDER BY id_sucursal');
    $st->execute($p);
    $sucursales = [];
    foreach ($st->fetchAll() as $s) {
        $s['horarios'] = (object)[];
        $sucursales[(int)$s['id_sucursal']] = $s;
    }

    $hor = $pdo->query('SELECT id_sucursal, dia_semana, TIME_FORMAT(hora_inicio, "%H:%i") ini, TIME_FORMAT(hora_fin, "%H:%i") fin FROM sucursal_horarios');
    foreach ($hor->fetchAll() as $h) {
        $id = (int)$h['id_sucursal'];
        if (isset($sucursales[$id])) {
            $sucursales[$id]['horarios']->{$h['dia_semana']} = ['ini' => $h['ini'], 'fin' => $h['fin']];
        }
    }

    $bloqueos = [];
    if (fecha_valida($desde) && fecha_valida($hasta)) {
        $sql = 'SELECT b.id_bloqueo, s.nombre_sucursal AS sucursal, b.medico, b.fecha_inicio, b.fecha_fin,
                       TIME_FORMAT(b.hora_inicio, "%H:%i") hora_inicio, TIME_FORMAT(b.hora_fin, "%H:%i") hora_fin, b.motivo
                  FROM bloqueos_agenda b JOIN sucursales s ON s.id_sucursal = b.id_sucursal
                 WHERE b.fecha_fin >= ? AND b.fecha_inicio <= ?';
        $p = [$desde, $hasta];
        if ($suc) { $sql .= ' AND b.id_sucursal = ?'; $p[] = $suc['id_sucursal']; }
        $st = $pdo->prepare($sql . ' ORDER BY b.fecha_inicio');
        $st->execute($p);
        $bloqueos = $st->fetchAll();
    }

    responder(['status' => 'success', 'sucursales' => array_values($sucursales), 'bloqueos' => $bloqueos]);
} catch (Throwable $e) {
    error_servidor($e);
}
