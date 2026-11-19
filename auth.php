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

/** Para endpoints JSON del CRM. Exige sesión y, en POST, token CSRF. */
function requiere_staff_api(array $roles = []): array
{
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
