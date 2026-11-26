<?php
// Corte del día: los totales se CALCULAN en el servidor a partir de las remisiones emitidas.
// Antes se mandaban desde la pantalla (lo tecleado), y el corte podía no cuadrar con los recibos.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_ADMIN);
solo_post();

$sucursal = sucursal_permitida($pdo, $u, txt($_POST, 'sucursal', 100));
$fecha    = txt($_POST, 'fecha', 10);
$contado  = isset($_POST['efectivo_contado']) && is_numeric($_POST['efectivo_contado']) ? dinero($_POST, 'efectivo_contado') : null;
$obs      = txt($_POST, 'observaciones', 1000);

if (!fecha_valida($fecha)) error_usuario('Fecha no válida.');
if ($fecha > date('Y-m-d')) error_usuario('No se puede hacer el corte de un día futuro.');

try {
    $suc = sucursal_por_nombre($pdo, $sucursal);
    if (!$suc) error_usuario('Sucursal no válida.');

    $pdo->beginTransaction();
    $st = $pdo->prepare(
        "SELECT id_remision, id_cita, folio, paciente, servicio, efectivo, credito, debito, transferencia, total
           FROM remisiones_clientes
          WHERE id_sucursal = ? AND fecha = ? AND estado = 'Vigente'
          ORDER BY id_remision
          FOR UPDATE"
    );
    $st->execute([$suc['id_sucursal'], $fecha]);
    $rems = $st->fetchAll();

    $t = ['efectivo' => 0.0, 'credito' => 0.0, 'debito' => 0.0, 'transferencia' => 0.0, 'total' => 0.0];
    $citas = [];
    foreach ($rems as $r) {
        foreach ($t as $k => $_) {
            $t[$k] += (float)$r[$k];
        }
        $citas[$r['id_cita'] ?: 'r' . $r['id_remision']] = true;
    }
    $folioIni = $rems ? $rems[0]['folio'] : null;
    $folioFin = $rems ? $rems[count($rems) - 1]['folio'] : null;

    $st = $pdo->prepare(
        'INSERT INTO cortes_diarios (sucursal, id_sucursal, fecha, efectivo, credito, debito, transferencia,
                 total_general, total_pacientes, folio_inicio, folio_fin, detalles_json, id_usuario,
                 efectivo_contado, observaciones)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE efectivo = VALUES(efectivo), credito = VALUES(credito), debito = VALUES(debito),
                 transferencia = VALUES(transferencia), total_general = VALUES(total_general),
                 total_pacientes = VALUES(total_pacientes), folio_inicio = VALUES(folio_inicio),
                 folio_fin = VALUES(folio_fin), detalles_json = VALUES(detalles_json),
                 id_usuario = VALUES(id_usuario),
                 efectivo_contado = COALESCE(VALUES(efectivo_contado), efectivo_contado),
                 observaciones = VALUES(observaciones),
                 id_corte = LAST_INSERT_ID(id_corte)'
    );
    $st->execute([
        $suc['nombre_sucursal'], $suc['id_sucursal'], $fecha,
        round($t['efectivo'], 2), round($t['credito'], 2), round($t['debito'], 2), round($t['transferencia'], 2),
        round($t['total'], 2), count($citas), $folioIni, $folioFin,
        json_encode($rems, JSON_UNESCAPED_UNICODE), $u['id'], $contado, $obs !== '' ? $obs : null,
    ]);
    $idCorte = (int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE remisiones_clientes SET id_corte = ? WHERE id_sucursal = ? AND fecha = ? AND estado = 'Vigente'")
        ->execute([$idCorte, $suc['id_sucursal'], $fecha]);
    bitacora($pdo, $u['id'], 'cortes_diarios', $idCorte, 'editar', null, $t);
    $pdo->commit();

    responder([
        'status'  => 'success',
        'message' => sprintf('Corte guardado: %d remisiones, total $%s.', count($rems), number_format($t['total'], 2)),
        'totales' => $t, 'total_pacientes' => count($citas), 'folio_inicio' => $folioIni, 'folio_fin' => $folioFin,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_servidor($e);
}
