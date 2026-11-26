<?php
// "Firma" ligera de la agenda visible: si cambia, el navegador vuelve a pedir las citas.
// Se consulta cada 20 s, así que solo hace conteos y máximos (no trae datos de pacientes).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api();
$sucursal = sucursal_permitida($pdo, $u, txt($_GET, 'sucursal', 100));
$desde = txt($_GET, 'desde', 10);
$hasta = txt($_GET, 'hasta', 10);
if (!fecha_valida($desde) || !fecha_valida($hasta)) error_usuario('Rango no válido.');

try {
    $filtro = $sucursal !== '' ? ' AND sucursal = ?' : '';
    $p = $sucursal !== '' ? [$desde, $hasta, $sucursal] : [$desde, $hasta];

    $st = $pdo->prepare("SELECT COUNT(*) AS n, SUM(estado <> 'Cancelada') AS activas,
                                MAX(GREATEST(COALESCE(actualizado_en, fecha_registro), fecha_registro)) AS ult
                           FROM citas WHERE fecha BETWEEN ? AND ?$filtro");
    $st->execute($p);
    $citas = $st->fetch();

    $st = $pdo->prepare('SELECT COUNT(*) AS n, MAX(b.creado_en) AS ult FROM bloqueos_agenda b JOIN sucursales s ON s.id_sucursal = b.id_sucursal
                          WHERE b.fecha_fin >= ? AND b.fecha_inicio <= ?' . ($sucursal !== '' ? ' AND s.nombre_sucursal = ?' : ''));
    $st->execute($p);
    $bloq = $st->fetch();

    try {   // la tabla existe desde migracion_v5
        $fel = (int)$pdo->query('SELECT COUNT(*) FROM felicitaciones WHERE anio = YEAR(CURDATE())')->fetchColumn();
    } catch (PDOException $e) {
        $fel = 0;
    }

    responder(['status' => 'success', 'firma' => md5(json_encode([$citas, $bloq, $fel]))]);
} catch (Throwable $e) {
    error_servidor($e);
}
