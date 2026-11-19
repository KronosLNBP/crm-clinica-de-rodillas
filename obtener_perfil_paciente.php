<?php
// Expediente del paciente para el CRM. Se busca por id_cita → id_paciente
// (antes era por nombre: dos "Karla Martinez" se mezclaban).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

requiere_staff_api();

$idCita = (int)($_GET['id_cita'] ?? 0);
if ($idCita <= 0) error_usuario('Cita no especificada.');

try {
    $st = $pdo->prepare('SELECT id_paciente FROM citas WHERE id_cita = ?');
    $st->execute([$idCita]);
    $idPaciente = (int)$st->fetchColumn();
    $perfil = $idPaciente ? perfil_paciente($pdo, $idPaciente) : null;
    if (!$perfil) error_usuario('No se encontró el paciente de esta cita.', 404);

    responder(['status' => 'success', 'data' => $perfil]);
} catch (Throwable $e) {
    error_servidor($e);
}
