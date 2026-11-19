<?php
// Cambia SOLO la asistencia (lo usa "Ver Asistencias" → Guardar cambios).
// Antes se reenviaba la cita completa y los campos vacíos llegaban como el texto "null".
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api();
solo_post();

$idCita = (int)($_POST['id_cita'] ?? 0);
$asist  = txt($_POST, 'asistencia', 20);
if ($idCita <= 0 || !in_array($asist, ASISTENCIAS, true)) error_usuario('Datos no válidos.');

try {
    $st = $pdo->prepare("UPDATE citas SET asistencia = ? WHERE id_cita = ? AND estado <> 'Cancelada'");
    $st->execute([$asist, $idCita]);
    if ($st->rowCount() > 0) {
        bitacora($pdo, $u['id'], 'citas', $idCita, 'editar', null, ['asistencia' => $asist]);
    }
    responder(['status' => 'success']);
} catch (Throwable $e) {
    error_servidor($e);
}
