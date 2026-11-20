<?php
// Login de la app de Android. Devuelve un token (válido 30 días) y los catálogos que la app necesita.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
solo_post();

$usuario     = txt($_POST, 'usuario', 60);
$pass        = (string)($_POST['password'] ?? '');
$dispositivo = txt($_POST, 'dispositivo', 120);

try {
    if (demasiados_intentos($pdo, 'app')) {
        error_usuario('Demasiados intentos. Espera 15 minutos.', 429);
    }
    $st = $pdo->prepare('SELECT id_usuario, nombre, password_hash, rol, id_sucursal FROM usuarios WHERE usuario = ? AND activo = 1');
    $st->execute([$usuario]);
    $u = $st->fetch();
    if (!$u || !password_verify($pass, $u['password_hash'])) {
        registrar_intento($pdo, 'app');
        error_usuario('Usuario o contraseña incorrectos.', 401);
    }
    limpiar_intentos($pdo, 'app');

    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO api_tokens (id_usuario, token_hash, dispositivo, expira_en) VALUES (?, ?, ?, NOW() + INTERVAL ' . TOKEN_DIAS . ' DAY)')
        ->execute([$u['id_usuario'], hash('sha256', $token), $dispositivo ?: null]);
    $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?')->execute([$u['id_usuario']]);
    bitacora($pdo, (int)$u['id_usuario'], 'usuarios', (int)$u['id_usuario'], 'login', null, ['app' => $dispositivo]);

    $staff = ['id' => (int)$u['id_usuario'], 'id_sucursal' => $u['id_sucursal'] !== null ? (int)$u['id_sucursal'] : null];
    $fija = sucursal_del_usuario($pdo, $staff);
    $sucursales = $fija
        ? [$fija['nombre_sucursal']]
        : $pdo->query('SELECT nombre_sucursal FROM sucursales WHERE activo = 1 ORDER BY id_sucursal')->fetchAll(PDO::FETCH_COLUMN);
    $servicios = $pdo->query('SELECT nombre FROM servicios WHERE activo = 1 ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN);

    responder([
        'status'   => 'success',
        'token'    => $token,
        'usuario'  => ['nombre' => $u['nombre'], 'rol' => $u['rol'], 'sucursal_fija' => $fija['nombre_sucursal'] ?? null],
        'sucursales' => $sucursales,
        'servicios'  => $servicios,
        'puede_operar' => in_array($u['rol'], ROLES_OPERACION, true),
    ]);
} catch (Throwable $e) {
    error_servidor($e);
}
