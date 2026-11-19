<?php
// Conexión PDO. Las credenciales viven en config.php (fuera de GitHub).
date_default_timezone_set('America/Mexico_City');

if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(500);
    exit('Falta config.php (copia config.example.php).');
}
$cfg = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4",
        $cfg['db_user'],
        $cfg['db_pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    // Misma hora que PHP (CDMX ya no tiene horario de verano)
    $pdo->exec("SET time_zone = '-06:00'");
} catch (PDOException $e) {
    error_log('[CRM] Conexión fallida: ' . $e->getMessage());   // al log, nunca al navegador
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['status' => 'error', 'message' => 'No hay conexión con la base de datos.']));
}
