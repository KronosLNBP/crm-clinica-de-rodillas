<?php
// Notas clínicas del médico/terapeuta para el paciente de una cita (admin y asistentes).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_CLINICOS);
$idCita = (int)($_GET['id_cita'] ?? 0);
if ($idCita <= 0) error_usuario('Falta la cita.');
verificar_cita_sucursal($pdo, $u, $idCita);

try {
    $st = $pdo->prepare(
        'SELECT v.id_valoracion, v.rodilla, v.diagnostico, v.dolor_eva, v.notas, v.creado_en, us.nombre AS autor
           FROM valoraciones v
           JOIN citas c ON c.id_paciente = v.id_paciente
           LEFT JOIN usuarios us ON us.id_usuario = v.id_usuario
          WHERE c.id_cita = ?
          ORDER BY v.creado_en DESC'
    );
    $st->execute([$idCita]);
    responder(['status' => 'success', 'data' => $st->fetchAll()]);
} catch (Throwable $e) {
    error_servidor($e);
}
