<?php
// Cierra la sesión del paciente (portal).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
iniciar_sesion();
unset($_SESSION['paciente']);
session_regenerate_id(true);
responder(['status' => 'success']);
