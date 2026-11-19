<?php
// Login del personal de la clínica (recepción, médicos, admin).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
iniciar_sesion();

if (usuario_actual()) {
    header('Location: index.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
            $error = 'La página expiró, intenta de nuevo.';
        } elseif (demasiados_intentos($pdo, 'staff')) {
            $error = 'Demasiados intentos. Espera 15 minutos.';
        } else {
            $usuario = txt($_POST, 'usuario', 60);
            $pass    = (string)($_POST['password'] ?? '');
            $st = $pdo->prepare('SELECT id_usuario, nombre, password_hash, rol, id_sucursal FROM usuarios WHERE usuario = ? AND activo = 1');
            $st->execute([$usuario]);
            $u = $st->fetch();

            if ($u && password_verify($pass, $u['password_hash'])) {
                limpiar_intentos($pdo, 'staff');
                session_regenerate_id(true);
                $_SESSION['staff'] = [
                    'id'          => (int)$u['id_usuario'],
                    'nombre'      => $u['nombre'],
                    'rol'         => $u['rol'],
                    'id_sucursal' => $u['id_sucursal'] !== null ? (int)$u['id_sucursal'] : null,
                ];
                $_SESSION['staff_ultimo'] = time();
                $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?')->execute([$u['id_usuario']]);
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?')
                        ->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id_usuario']]);
                }
                bitacora($pdo, (int)$u['id_usuario'], 'usuarios', (int)$u['id_usuario'], 'login');
                header('Location: index.php');
                exit;
            }
            registrar_intento($pdo, 'staff');
            $error = 'Usuario o contraseña incorrectos.';
        }
    } catch (Throwable $e) {
        error_log('[CRM] login_staff: ' . $e->getMessage());
        $error = 'Error del servidor. Intenta más tarde.';
    }
}
header('X-Frame-Options: DENY');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso personal - Clínica de Rodillas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <form method="post" class="w-full max-w-sm bg-white p-8 rounded-2xl shadow-xl space-y-5" autocomplete="on">
        <div class="text-center">
            <h1 class="text-2xl font-black text-gray-800">Clínica de Rodillas</h1>
            <p class="text-sm text-gray-500">Acceso del personal</p>
        </div>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1" for="usuario">Usuario</label>
            <input id="usuario" name="usuario" required autocomplete="username" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1" for="password">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <?php if ($error !== ''): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm text-center border border-red-200"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <button class="w-full py-3 rounded-lg font-bold text-white bg-[#4361ee] hover:bg-blue-700">Entrar</button>
    </form>
</body>
</html>
