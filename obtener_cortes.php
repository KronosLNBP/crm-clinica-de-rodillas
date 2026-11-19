<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api();

$sucursal = sucursal_permitida($pdo, $u, txt($_GET, 'sucursal', 100));
$inicio   = txt($_GET, 'fecha_inicio', 10);
$fin      = txt($_GET, 'fecha_fin', 10);

try {
    $sql = 'SELECT id_corte, sucursal, fecha, efectivo, credito, debito, transferencia, total_general,
                   total_pacientes, folio_inicio, folio_fin, efectivo_contado, diferencia
              FROM cortes_diarios WHERE 1 = 1';
    $p = [];
    if ($sucursal !== '') { $sql .= ' AND sucursal = ?'; $p[] = $sucursal; }
    if (fecha_valida($inicio) && fecha_valida($fin)) { $sql .= ' AND fecha BETWEEN ? AND ?'; array_push($p, $inicio, $fin); }
    $sql .= ' ORDER BY fecha DESC, sucursal';

    $st = $pdo->prepare($sql);
    $st->execute($p);
    $cortes = $st->fetchAll();

    $r = ['total' => 0.0, 'efectivo' => 0.0, 'tarjetas' => 0.0, 'transferencia' => 0.0, 'pacientes' => 0];
    foreach ($cortes as $c) {
        $r['total']         += (float)$c['total_general'];
        $r['efectivo']      += (float)$c['efectivo'];
        $r['tarjetas']      += (float)$c['credito'] + (float)$c['debito'];
        $r['transferencia'] += (float)$c['transferencia'];
        $r['pacientes']     += (int)$c['total_pacientes'];
    }
    responder(['status' => 'success', 'data' => $cortes, 'resumen' => $r]);
} catch (Throwable $e) {
    error_servidor($e);
}
