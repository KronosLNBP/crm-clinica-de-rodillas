<?php
// ALTA DEL PRIMER ADMINISTRADOR.
// Solo funciona mientras la tabla `usuarios` esté vacía. Úsalo una vez y BÓRRALO del servidor.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
iniciar_sesion();

$hayUsuarios = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
$msg = '';

if (!$hayUsuarios && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
    $nombre  = txt($_POST, 'nombre', 120);
    $usuario = txt($_POST, 'usuario', 60);
    $pass    = (string)($_POST['password'] ?? '');
    if ($nombre === '' || $usuario === '' || strlen($pass) < 10) {
        $msg = 'Llena todo. La contraseña debe tener al menos 10 caracteres.';
    } else {
        $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES (?, ?, ?, 'admin')")
            ->execute([$nombre, $usuario, password_hash($pass, PASSWORD_DEFAULT)]);
        $hayUsuarios = true;
        $msg = 'Administrador creado. Ahora BORRA crear_admin.php del servidor y entra en login_staff.php.';
    }
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Crear administrador</title></head>
<body style="font-family:sans-serif;max-width:420px;margin:60px auto">
<h2>Crear primer administrador</h2>
<?php if ($msg): ?><p><strong><?= htmlspecialchars($msg) ?></strong></p><?php endif; ?>
<?php if (!$hayUsuarios): ?>
<form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <p><label>Nombre<br><input name="nombre" required></label></p>
    <p><label>Usuario<br><input name="usuario" required></label></p>
    <p><label>Contraseña (mín. 10)<br><input name="password" type="password" minlength="10" required></label></p>
    <button>Crear</button>
</form>
<?php else: ?>
<p>Ya existe al menos un usuario. Este archivo ya no hace nada: bórralo.</p>
<?php endif; ?>
</body></html>
