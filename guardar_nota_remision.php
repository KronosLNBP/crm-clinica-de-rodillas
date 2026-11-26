<?php
// Remisión con el formato impreso de la clínica (varios conceptos, observaciones, método de pago).
// Puede ir ligada a una cita o ser suelta (p. ej. venta de producto). El folio lo da el servidor y
// cuenta en el corte del día como cualquier cobro. El PDF se sube después con adjuntar_pdf_remision.php.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_OPERACION);
solo_post();

$sucursal = sucursal_permitida($pdo, $u, txt($_POST, 'sucursal', 100));
$fecha    = txt($_POST, 'fecha', 10) ?: date('Y-m-d');
$nombre   = preg_replace('/\s+/', ' ', txt($_POST, 'nombre', 150));
$telefono = solo_digitos(txt($_POST, 'telefono', 20));
$metodo   = txt($_POST, 'metodo_pago', 40);
$obs      = txt($_POST, 'observaciones', 500);
$atendio  = preg_replace('/\s+/', ' ', txt($_POST, 'atendio', 100)) ?: $u['nombre'];
$idCita   = (int)($_POST['id_cita'] ?? 0);
$conceptos = json_decode((string)($_POST['conceptos'] ?? '[]'), true);

if ($sucursal === '') error_usuario('Elige la sucursal.');
if (!fecha_valida($fecha)) error_usuario('Fecha no válida.');
if ($fecha > date('Y-m-d')) error_usuario('La fecha no puede ser futura.');
if (mb_strlen($nombre) < 3) error_usuario('Escribe el nombre del cliente.');
if ($telefono !== '' && strlen($telefono) !== 10) error_usuario('El teléfono debe tener 10 dígitos (o déjalo vacío).');
if (!isset(METODOS_PAGO[$metodo])) error_usuario('Elige el método de pago.');
if (!is_array($conceptos) || !$conceptos) error_usuario('Agrega al menos un concepto.');
if (count($conceptos) > 12) error_usuario('Máximo 12 conceptos por remisión.');

$lineas = [];
$total = 0.0;
foreach ($conceptos as $i => $c) {
    $n = $i + 1;
    $cant = filter_var($c['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 999]]);
    $desc = trim(preg_replace('/\s+/', ' ', (string)($c['descripcion'] ?? '')));
    $precio = is_numeric($c['precio'] ?? null) ? round((float)$c['precio'], 2) : -1;
    if ($cant === false) error_usuario("Concepto $n: cantidad no válida.");
    if (mb_strlen($desc) < 2 || mb_strlen($desc) > 150) error_usuario("Concepto $n: escribe la descripción.");
    if ($precio < 0 || $precio > 999999) error_usuario("Concepto $n: precio no válido.");
    $lineas[] = [$cant, $desc, $precio];
    $total += $cant * $precio;
}
$total = round($total, 2);
if ($total <= 0) error_usuario('El total debe ser mayor a cero.');

try {
    $suc = sucursal_por_nombre($pdo, $sucursal);
    if (!$suc) error_usuario('Sucursal no válida.');
    if ($idCita > 0) {
        $st = $pdo->prepare("SELECT telefono FROM citas WHERE id_cita = ? AND sucursal = ? AND estado <> 'Cancelada'");
        $st->execute([$idCita, $suc['nombre_sucursal']]);
        $telCita = $st->fetchColumn();
        if ($telCita === false) error_usuario('La cita elegida no es de esta sucursal o está cancelada.');
        if ($telefono === '') $telefono = solo_digitos((string)$telCita);
    }

    $montos = ['efectivo' => 0.0, 'credito' => 0.0, 'debito' => 0.0, 'transferencia' => 0.0];
    $montos[METODOS_PAGO[$metodo]] = $total;
    $servicio = mb_substr(implode(', ', array_map(fn($l) => ($l[0] > 1 ? $l[0] . ' x ' : '') . $l[1], $lineas)), 0, 150);
    $v8 = tiene_columna($pdo, 'remisiones_clientes', 'metodo_pago');

    $pdo->beginTransaction();
    $folio = siguiente_folio($pdo, (int)$suc['id_sucursal'], $suc['clave']);
    $cols = 'id_cita, folio, paciente, telefono, sucursal, id_sucursal, servicio, efectivo, tarjeta, credito, debito, transferencia, total, fecha, id_usuario';
    $vals = [$idCita ?: null, $folio, $nombre, $telefono, $suc['nombre_sucursal'], $suc['id_sucursal'], $servicio,
             $montos['efectivo'], $montos['credito'] + $montos['debito'], $montos['credito'], $montos['debito'], $montos['transferencia'],
             $total, $fecha, $u['id']];
    if ($v8) {
        $cols .= ', observaciones, metodo_pago, atendio';
        array_push($vals, $obs !== '' ? $obs : null, $metodo, $atendio);
    }
    $pdo->prepare("INSERT INTO remisiones_clientes ($cols) VALUES (" . implode(',', array_fill(0, count($vals), '?')) . ')')->execute($vals);
    $idRem = (int)$pdo->lastInsertId();

    $st = $pdo->prepare('INSERT INTO remision_detalle (id_remision, descripcion, cantidad, precio) VALUES (?,?,?,?)');
    foreach ($lineas as [$cant, $desc, $precio]) {
        $st->execute([$idRem, $desc, $cant, $precio]);
    }
    if ($idCita > 0) {
        $pdo->prepare("UPDATE citas SET asistencia = 'Asistió' WHERE id_cita = ?")->execute([$idCita]);
    }
    bitacora($pdo, $u['id'], 'remisiones_clientes', $idRem, 'crear', null, ['folio' => $folio, 'total' => $total, 'metodo' => $metodo]);
    $pdo->commit();

    responder(['status' => 'success', 'folio' => $folio, 'id_remision' => $idRem, 'fecha' => $fecha, 'total' => $total]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_servidor($e);
}
