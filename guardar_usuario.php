<?php
// Alta / edición de usuarios del personal (solo administradores).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(['admin']);
solo_post();

const ROLES_VALIDOS = ['admin', 'recepcion', 'medico', 'terapeuta', 'contabilidad'];

$id      = (int)($_POST['id_usuario'] ?? 0);
$nombre  = txt($_POST, 'nombre', 120);
$usuario = strtolower(txt($_POST, 'usuario', 60));
$rol     = txt($_POST, 'rol', 20);
$idSuc   = (int)($_POST['id_sucursal'] ?? 0) ?: null;
$activo  = ($_POST['activo'] ?? '1') === '1' ? 1 : 0;
$pass    = (string)($_POST['password'] ?? '');

if (mb_strlen($nombre) < 3) error_usuario('Escribe el nombre.');
if (!preg_match('/^[a-z0-9._-]{3,60}$/', $usuario)) error_usuario('Usuario: solo letras, números, punto o guion (mín. 3).');
if (!in_array($rol, ROLES_VALIDOS, true)) error_usuario('Rol no válido.');
if ($id === 0 && strlen($pass) < 8) error_usuario('La contraseña debe tener al menos 8 caracteres.');
if ($pass !== '' && strlen($pass) < 8) error_usuario('La contraseña debe tener al menos 8 caracteres.');
if ($id === $u['id'] && (!$activo || $rol !== 'admin')) error_usuario('No puedes quitarte tu propio acceso de administrador.');

try {
    $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE usuario = ? AND id_usuario <> ?');
    $st->execute([$usuario, $id]);
    if ($st->fetchColumn()) error_usuario('Ese nombre de usuario ya existe.');

    if ($id === 0) {
        $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password_hash, rol, id_sucursal, activo) VALUES (?,?,?,?,?,?)')
            ->execute([$nombre, $usuario, password_hash($pass, PASSWORD_DEFAULT), $rol, $idSuc, $activo]);
        $id = (int)$pdo->lastInsertId();
        bitacora($pdo, $u['id'], 'usuarios', $id, 'crear', null, compact('nombre', 'usuario', 'rol', 'idSuc'));
    } else {
        $pdo->prepare('UPDATE usuarios SET nombre = ?, usuario = ?, rol = ?, id_sucursal = ?, activo = ? WHERE id_usuario = ?')
            ->execute([$nombre, $usuario, $rol, $idSuc, $activo, $id]);
        if ($pass !== '') {
            $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
        }
        bitacora($pdo, $u['id'], 'usuarios', $id, 'editar', null, compact('nombre', 'usuario', 'rol', 'idSuc', 'activo'));
    }
    responder(['status' => 'success', 'message' => 'Usuario guardado.']);
} catch (Throwable $e) {
    error_servidor($e);
}
