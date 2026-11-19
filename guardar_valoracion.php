<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_CLINICOS);
solo_post();

$idCita = (int)($_POST['id_cita'] ?? 0);
$rodilla = txt($_POST, 'rodilla', 10);
$diag    = txt($_POST, 'diagnostico', 255);
$dolorT  = txt($_POST, 'dolor_eva', 2);
$notas   = txt($_POST, 'notas', 5000);

if ($idCita <= 0) error_usuario('Falta la cita.');
if (!in_array($rodilla, ['', 'Derecha', 'Izquierda', 'Ambas'], true)) error_usuario('Rodilla no válida.');
if ($dolorT !== '' && (!ctype_digit($dolorT) || (int)$dolorT > 10)) error_usuario('El dolor va de 0 a 10.');
if ($diag === '' && $notas === '') error_usuario('Escribe un diagnóstico o una nota.');
verificar_cita_sucursal($pdo, $u, $idCita);

try {
    $st = $pdo->prepare('SELECT id_paciente FROM citas WHERE id_cita = ?');
    $st->execute([$idCita]);
    $idPac = (int)$st->fetchColumn();
    if (!$idPac) error_usuario('La cita no tiene paciente.', 404);

    $pdo->prepare('INSERT INTO valoraciones (id_paciente, id_cita, rodilla, diagnostico, dolor_eva, notas, id_usuario) VALUES (?,?,?,?,?,?,?)')
        ->execute([$idPac, $idCita, $rodilla ?: null, $diag ?: null, $dolorT === '' ? null : (int)$dolorT, $notas ?: null, $u['id']]);
    bitacora($pdo, $u['id'], 'valoraciones', (int)$pdo->lastInsertId(), 'crear');
    responder(['status' => 'success', 'message' => 'Nota guardada.']);
} catch (Throwable $e) {
    error_servidor($e);
}
