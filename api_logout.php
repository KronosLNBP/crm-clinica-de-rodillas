<?php
// Cierra la sesión de la app: borra el token de ese teléfono.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
solo_post();

try {
    $u = usuario_por_token();
    if ($u) {
        $pdo->prepare('DELETE FROM api_tokens WHERE id_token = ?')->execute([$u['id_token']]);
    }
    responder(['status' => 'success']);
} catch (Throwable $e) {
    error_servidor($e);
}
