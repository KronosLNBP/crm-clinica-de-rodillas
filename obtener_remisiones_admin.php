<?php
// Reporte de remisiones para administradores: todas las sucursales, por rango de fechas,
// método de pago, estado y búsqueda. ?formato=csv descarga el mismo filtro para Excel.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

requiere_staff_api(ROLES_ADMIN);

$sucursal = txt($_GET, 'sucursal', 100);
$desde    = txt($_GET, 'desde', 10);
$hasta    = txt($_GET, 'hasta', 10);
$estado   = txt($_GET, 'estado', 10);
$metodo   = txt($_GET, 'metodo', 20);
$buscar   = txt($_GET, 'q', 60);
$pagina   = max(1, (int)($_GET['pagina'] ?? 1));
$csv      = ($_GET['formato'] ?? '') === 'csv';
$porPag   = 100;

if (!fecha_valida($desde) || !fecha_valida($hasta)) error_usuario('Fechas no válidas.');
if ($desde > $hasta) error_usuario('La fecha inicial es mayor que la final.');
if ((new DateTime($desde))->diff(new DateTime($hasta))->days > 731) error_usuario('El rango máximo es de 2 años.');

$where = ['r.fecha BETWEEN ? AND ?'];
$params = [$desde, $hasta];
if ($sucursal !== '') { $where[] = 's.nombre_sucursal = ?'; $params[] = $sucursal; }
if (in_array($estado, ['Vigente', 'Cancelada'], true)) { $where[] = 'r.estado = ?'; $params[] = $estado; }
$cols = ['efectivo' => 'r.efectivo', 'credito' => 'r.credito', 'debito' => 'r.debito', 'transferencia' => 'r.transferencia'];
if (isset($cols[$metodo])) { $where[] = $cols[$metodo] . ' > 0'; }
if ($buscar !== '') {
    $where[] = '(r.paciente LIKE ? OR r.folio LIKE ? OR r.servicio LIKE ?)';
    $like = '%' . addcslashes($buscar, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
$w = implode(' AND ', $where);
$from = "FROM remisiones_clientes r JOIN sucursales s ON s.id_sucursal = r.id_sucursal LEFT JOIN usuarios u ON u.id_usuario = r.id_usuario WHERE $w";

try {
    $v8 = tiene_columna($pdo, 'remisiones_clientes', 'metodo_pago');
    $extra = $v8 ? ', r.metodo_pago, r.atendio, r.observaciones' : ', NULL AS metodo_pago, NULL AS atendio, NULL AS observaciones';
    $select = "SELECT r.id_remision, r.folio, r.fecha, s.nombre_sucursal AS sucursal, r.paciente, r.telefono, r.servicio,
                      r.efectivo, r.credito, r.debito, r.transferencia, r.total, r.estado, r.ruta_archivo, r.id_cita,
                      u.nombre AS usuario, r.creado_en$extra $from ORDER BY r.fecha DESC, r.id_remision DESC";

    if ($csv) {
        $st = $pdo->prepare($select . ' LIMIT 20000');
        $st->execute($params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="remisiones_' . $desde . '_a_' . $hasta . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // para que Excel respete los acentos
        fputcsv($out, ['Folio', 'Fecha', 'Sucursal', 'Cliente', 'Teléfono', 'Conceptos', 'Método de pago', 'Efectivo', 'T. crédito', 'T. débito', 'Transferencia', 'Total', 'Estado', 'Atendió', 'Registró', 'Observaciones']);
        foreach ($st as $r) {
            // Evita que Excel interprete como fórmula un texto que empiece con = + - @
            $t = fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : (string)$v;
            fputcsv($out, [$r['folio'], $r['fecha'], $r['sucursal'], $t($r['paciente']), $r['telefono'], $t($r['servicio']), $r['metodo_pago'],
                           $r['efectivo'], $r['credito'], $r['debito'], $r['transferencia'], $r['total'], $r['estado'], $t($r['atendio']), $t($r['usuario']), $t($r['observaciones'])]);
        }
        exit;
    }

    $st = $pdo->prepare("SELECT COUNT(*) n,
                COALESCE(SUM(CASE WHEN r.estado = 'Vigente' THEN r.total END), 0) total,
                COALESCE(SUM(CASE WHEN r.estado = 'Vigente' THEN r.efectivo END), 0) efectivo,
                COALESCE(SUM(CASE WHEN r.estado = 'Vigente' THEN r.credito END), 0) credito,
                COALESCE(SUM(CASE WHEN r.estado = 'Vigente' THEN r.debito END), 0) debito,
                COALESCE(SUM(CASE WHEN r.estado = 'Vigente' THEN r.transferencia END), 0) transferencia,
                SUM(r.estado = 'Cancelada') canceladas $from");
    $st->execute($params);
    $resumen = $st->fetch();

    $st = $pdo->prepare("SELECT s.nombre_sucursal sucursal, COUNT(*) n, COALESCE(SUM(r.total), 0) total $from AND r.estado = 'Vigente' GROUP BY s.nombre_sucursal ORDER BY total DESC");
    $st->execute($params);
    $porSucursal = $st->fetchAll();

    $st = $pdo->prepare($select . ' LIMIT ' . $porPag . ' OFFSET ' . (($pagina - 1) * $porPag));
    $st->execute($params);
    $rows = $st->fetchAll();

    if ($rows) {
        $ids = array_column($rows, 'id_remision');
        $st = $pdo->prepare('SELECT id_remision, cantidad, descripcion, precio FROM remision_detalle WHERE id_remision IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id_detalle');
        $st->execute($ids);
        $det = [];
        foreach ($st->fetchAll() as $d) $det[$d['id_remision']][] = $d;
        foreach ($rows as &$r) $r['conceptos'] = $det[$r['id_remision']] ?? [];
        unset($r);
    }

    responder(['status' => 'success', 'data' => $rows, 'resumen' => $resumen, 'por_sucursal' => $porSucursal,
               'pagina' => $pagina, 'paginas' => max(1, (int)ceil($resumen['n'] / $porPag))]);
} catch (Throwable $e) {
    error_servidor($e);
}
