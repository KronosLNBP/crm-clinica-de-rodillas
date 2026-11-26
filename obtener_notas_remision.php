<?php
// Historial de remisiones de una sucursal (por fecha o buscando por nombre/folio), con sus conceptos
// para poder reimprimirlas con el formato de la clínica.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_OPERACION);

$sucursal = sucursal_permitida($pdo, $u, txt($_GET, 'sucursal', 100));
$fecha    = txt($_GET, 'fecha', 10);
$buscar   = txt($_GET, 'q', 60);
if ($sucursal === '') error_usuario('Elige la sucursal.');
if ($buscar === '' && !fecha_valida($fecha)) error_usuario('Fecha no válida.');

try {
    $extra = tiene_columna($pdo, 'remisiones_clientes', 'metodo_pago') ? ', r.observaciones, r.metodo_pago, r.atendio' : '';
    $sql = "SELECT r.id_remision, r.id_cita, r.folio, r.paciente, r.telefono, r.servicio, r.efectivo, r.credito, r.debito,
                   r.transferencia, r.total, r.fecha, r.estado, r.ruta_archivo, u.nombre AS usuario$extra
              FROM remisiones_clientes r
              JOIN sucursales s ON s.id_sucursal = r.id_sucursal
              LEFT JOIN usuarios u ON u.id_usuario = r.id_usuario
             WHERE s.nombre_sucursal = ?";
    $params = [$sucursal];
    if ($buscar !== '') {
        $sql .= ' AND (r.paciente LIKE ? OR r.folio LIKE ?)';
        $like = '%' . addcslashes($buscar, '%_\\') . '%';
        array_push($params, $like, $like);
    } else {
        $sql .= ' AND r.fecha = ?';
        $params[] = $fecha;
    }
    $st = $pdo->prepare($sql . ' ORDER BY r.id_remision DESC LIMIT 100');
    $st->execute($params);
    $rems = $st->fetchAll();

    if ($rems) {
        $ids = array_column($rems, 'id_remision');
        $st = $pdo->prepare('SELECT id_remision, cantidad, descripcion, precio FROM remision_detalle WHERE id_remision IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id_detalle');
        $st->execute($ids);
        $det = [];
        foreach ($st->fetchAll() as $d) {
            $det[$d['id_remision']][] = $d;
        }
        foreach ($rems as &$r) {
            $r['conceptos'] = $det[$r['id_remision']] ?? [];
        }
        unset($r);
    }
    responder(['status' => 'success', 'data' => $rems]);
} catch (Throwable $e) {
    error_servidor($e);
}
