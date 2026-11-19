<?php
// Datos del portal: SIEMPRE del paciente que inició sesión (no acepta nombre por URL).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$pac = paciente_actual();
if (!$pac) {
    responder(['status' => 'error', 'message' => 'Sesión expirada.', 'login' => true], 401);
}

try {
    $perfil = perfil_paciente($pdo, (int)$pac['id_paciente']);
    if (!$perfil) error_usuario('Expediente no encontrado.', 404);
    responder(['status' => 'success', 'data' => $perfil]);
} catch (Throwable $e) {
    error_servidor($e);
}
