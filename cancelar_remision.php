<?php
// Cancela una remisión (no se borra: queda en bitácora). Solo administradores.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(['admin']);
solo_post();

$idRem  = (int)($_POST['id_remision'] ?? 0);
$motivo = txt($_POST, 'motivo', 255);
if ($idRem <= 0 || $motivo === '') error_usuario('Indica la remisión y el motivo.');

try {
    $st = $pdo->prepare("UPDATE remisiones_clientes SET estado = 'Cancelada' WHERE id_remision = ? AND estado = 'Vigente'");
    $st->execute([$idRem]);
    if ($st->rowCount() === 0) error_usuario('La remisión no existe o ya estaba cancelada.');
    bitacora($pdo, $u['id'], 'remisiones_clientes', $idRem, 'cancelar', null, ['motivo' => $motivo]);
    responder(['status' => 'success', 'message' => 'Remisión cancelada. Vuelve a guardar el corte del día.']);
} catch (Throwable $e) {
    error_servidor($e);
}
