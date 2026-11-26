<?php
// Marca que ya se mandó la felicitación de cumpleaños de este año (para no repetirla).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_OPERACION);
solo_post();
$id = (int)($_POST['id_paciente'] ?? 0);
if ($id <= 0) error_usuario('Falta el paciente.');
$anio = (int)($_POST['anio'] ?? date('Y'));
if (abs($anio - (int)date('Y')) > 1) error_usuario('Año no válido.');

try {
    $pdo->prepare('INSERT IGNORE INTO felicitaciones (id_paciente, anio, id_usuario) VALUES (?, ?, ?)')->execute([$id, $anio, $u['id']]);
    responder(['status' => 'success']);
} catch (Throwable $e) {
    error_servidor($e);
}
