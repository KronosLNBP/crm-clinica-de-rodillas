<?php
// Detalle de un corte: cada remisión con su PDF. Para que quien revisa las hojas lo consulte
// directo en el sistema, sin imprimir ni mandar el corte por WhatsApp.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_ADMIN);
$id = (int)($_GET['id_corte'] ?? 0);
if ($id <= 0) error_usuario('Falta el corte.');

try {
    $st = $pdo->prepare('SELECT c.*, us.nombre AS hecho_por FROM cortes_diarios c LEFT JOIN usuarios us ON us.id_usuario = c.id_usuario WHERE c.id_corte = ?');
    $st->execute([$id]);
    $corte = $st->fetch();
    if (!$corte) error_usuario('No existe.', 404);
    sucursal_permitida($pdo, $u, (string)$corte['sucursal']);
    unset($corte['detalles_json']);

    $st = $pdo->prepare(
        "SELECT r.id_remision, r.folio, r.paciente, r.servicio, r.efectivo, r.credito, r.debito, r.transferencia,
                r.total, r.ruta_archivo, us.nombre AS cobro
           FROM remisiones_clientes r LEFT JOIN usuarios us ON us.id_usuario = r.id_usuario
          WHERE r.id_sucursal = ? AND r.fecha = ? AND r.estado = 'Vigente'
          ORDER BY r.id_remision"
    );
    $st->execute([$corte['id_sucursal'], $corte['fecha']]);
    $rems = $st->fetchAll();
    foreach ($rems as &$r) {
        $r['pdf'] = $r['ruta_archivo'] ? 'ver_archivo.php?f=' . rawurlencode($r['ruta_archivo']) : null;
        unset($r['ruta_archivo']);
    }
    responder(['status' => 'success', 'corte' => $corte, 'remisiones' => $rems]);
} catch (Throwable $e) {
    error_servidor($e);
}
