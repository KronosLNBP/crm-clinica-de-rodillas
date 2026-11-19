<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
iniciar_sesion();
$_SESSION = [];
session_destroy();
header('Location: login_staff.php');
