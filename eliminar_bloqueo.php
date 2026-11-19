<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_OPERACION);
solo_post();
$id = (int)($_POST['id_bloqueo'] ?? 0);
if ($id <= 0) error_usuario('Falta la ausencia.');

try {
    $st = $pdo->prepare('SELECT b.*, s.nombre_sucursal FROM bloqueos_agenda b JOIN sucursales s ON s.id_sucursal = b.id_sucursal WHERE id_bloqueo = ?');
    $st->execute([$id]);
    $b = $st->fetch();
    if (!$b) error_usuario('No existe.', 404);
    sucursal_permitida($pdo, $u, $b['nombre_sucursal']);

    $pdo->prepare('DELETE FROM bloqueos_agenda WHERE id_bloqueo = ?')->execute([$id]);
    bitacora($pdo, $u['id'], 'bloqueos_agenda', $id, 'eliminar', $b);
    responder(['status' => 'success']);
} catch (Throwable $e) {
    error_servidor($e);
}
