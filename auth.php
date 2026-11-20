<?php
// Sesiones del personal (CRM) y de pacientes (portal), CSRF y control de acceso.
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

const SESION_MAX_HORAS = 10;

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('crm_rodillas');
    session_start();
}

function csrf_token(): string
{
    iniciar_sesion();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function usuario_actual(): ?array
{
    iniciar_sesion();
    $u = $_SESSION['staff'] ?? null;
    if (!$u) {
        return null;
    }
    if (time() - (int)($_SESSION['staff_ultimo'] ?? 0) > SESION_MAX_HORAS * 3600) {
        unset($_SESSION['staff'], $_SESSION['staff_ultimo']);
        return null;
    }
    $_SESSION['staff_ultimo'] = time();
    return $u;
}

const TOKEN_DIAS = 30;

/** Usuario de la app de Android: encabezado X-Api-Token (no lo bloquean los servidores Apache como a Authorization). */
function usuario_por_token(): ?array
{
    global $pdo;
    $tok = (string)($_SERVER['HTTP_X_API_TOKEN'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $tok)) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT u.id_usuario, u.nombre, u.rol, u.id_sucursal, t.id_token
           FROM api_tokens t JOIN usuarios u ON u.id_usuario = t.id_usuario
          WHERE t.token_hash = ? AND t.expira_en > NOW() AND u.activo = 1'
    );
    $st->execute([hash('sha256', $tok)]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    $pdo->prepare('UPDATE api_tokens SET ultimo_uso = NOW() WHERE id_token = ?')->execute([$r['id_token']]);
    return [
        'id'          => (int)$r['id_usuario'],
        'nombre'      => $r['nombre'],
        'rol'         => $r['rol'],
        'id_sucursal' => $r['id_sucursal'] !== null ? (int)$r['id_sucursal'] : null,
        'id_token'    => (int)$r['id_token'],
    ];
}

/** Para endpoints JSON del CRM. Acepta la sesión del navegador (con CSRF) o el token de la app. */
function requiere_staff_api(array $roles = []): array
{
    if (isset($_SERVER['HTTP_X_API_TOKEN'])) {
        $u = usuario_por_token();
        if (!$u) {
            responder(['status' => 'error', 'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.', 'login' => true], 401);
        }
        if ($roles && !in_array($u['rol'], $roles, true)) {
            error_usuario('No tienes permiso para esta acción.', 403);
        }
        return $u;   // sin CSRF: el token no viaja solo como una cookie, así que otra página no puede usarlo
    }
    $u = usuario_actual();
    if (!$u) {
        responder(['status' => 'error', 'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.', 'login' => true], 401);
    }
    if ($roles && !in_array($u['rol'], $roles, true)) {
        error_usuario('No tienes permiso para esta acción.', 403);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && !hash_equals(csrf_token(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        error_usuario('Token de seguridad inválido. Recarga la página.', 419);
    }
    return $u;
}

/** Para páginas HTML del CRM. Si no hay sesión, manda al login. */
function requiere_staff_pagina(): array
{
    $u = usuario_actual();
    if (!$u) {
        header('Location: login_staff.php');
        exit;
    }
    header('X-Frame-Options: DENY');
    return $u;
}

function paciente_actual(): ?array
{
    iniciar_sesion();
    return $_SESSION['paciente'] ?? null;
}
