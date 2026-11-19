<?php
// Sirve PDFs de remisiones/carnets. Las carpetas están bloqueadas por .htaccess;
// solo se abren con link firmado (el que se manda por WhatsApp) o con sesión del personal.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$ruta  = (string)($_GET['f'] ?? '');
$firma = (string)($_GET['t'] ?? '');

if (!preg_match('#^(remisiones_guardadas|carnets_guardados)/[A-Za-z0-9_\-]+\.(pdf|html)$#', $ruta)) {
    http_response_code(404);
    exit('No encontrado');
}
$firmaOk = $firma !== '' && hash_equals(hash_hmac('sha256', $ruta, cfg('app_secret')), $firma);
if (!$firmaOk && !usuario_actual()) {
    http_response_code(403);
    exit('Acceso denegado');
}

$archivo = __DIR__ . '/' . $ruta;
if (!is_file($archivo)) {
    http_response_code(404);
    exit('No encontrado');
}

$esPdf = str_ends_with($ruta, '.pdf');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Type: ' . ($esPdf ? 'application/pdf' : 'application/octet-stream'));
header('Content-Disposition: ' . ($esPdf ? 'inline' : 'attachment') . '; filename="' . basename($ruta) . '"');
header('Content-Length: ' . filesize($archivo));
readfile($archivo);
