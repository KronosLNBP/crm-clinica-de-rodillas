<?php
// Registra una ausencia / bloqueo de agenda (vacaciones, permiso, emergencia, sale temprano).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_OPERACION);
solo_post();

$sucNombre = sucursal_permitida($pdo, $u, txt($_POST, 'sucursal', 100));
$medico    = txt($_POST, 'medico', 120);
$fIni      = txt($_POST, 'fecha_inicio', 10);
$fFin      = txt($_POST, 'fecha_fin', 10) ?: $fIni;
$hIni      = txt($_POST, 'hora_inicio', 5);
$hFin      = txt($_POST, 'hora_fin', 5);
$motivo    = txt($_POST, 'motivo', 150);

if (!fecha_valida($fIni) || !fecha_valida($fFin) || $fFin < $fIni) error_usuario('Revisa las fechas.');
if ($motivo === '') error_usuario('Escribe el motivo (vacaciones, permiso, etc.).');
if (($hIni === '') !== ($hFin === '')) error_usuario('Pon hora de inicio y de fin, o deja ambas vacías para todo el día.');
if ($hIni !== '' && (!hora_valida($hIni) || !hora_valida($hFin) || $hFin <= $hIni)) error_usuario('Revisa las horas.');

try {
    $suc = sucursal_por_nombre($pdo, $sucNombre);
    if (!$suc) error_usuario('Elige una sucursal.');

    $st = $pdo->prepare('INSERT INTO bloqueos_agenda (id_sucursal, medico, fecha_inicio, fecha_fin, hora_inicio, hora_fin, motivo, id_usuario)
                         VALUES (?,?,?,?,?,?,?,?)');
    $st->execute([$suc['id_sucursal'], $medico ?: null, $fIni, $fFin, $hIni ?: null, $hFin ?: null, $motivo, $u['id']]);
    $id = (int)$pdo->lastInsertId();
    bitacora($pdo, $u['id'], 'bloqueos_agenda', $id, 'crear', null, $_POST);

    // Avisar si ya había citas en ese periodo, para reprogramarlas
    $sql = "SELECT COUNT(*) FROM citas WHERE sucursal = ? AND estado <> 'Cancelada' AND fecha BETWEEN ? AND ?";
    $p = [$suc['nombre_sucursal'], $fIni, $fFin];
    if ($hIni !== '') { $sql .= ' AND hora >= ? AND hora < ?'; array_push($p, $hIni, $hFin); }
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $afectadas = (int)$st->fetchColumn();

    responder(['status' => 'success', 'afectadas' => $afectadas,
               'message' => 'Ausencia registrada.' . ($afectadas ? " Ojo: hay $afectadas cita(s) en ese periodo que conviene reprogramar." : '')]);
} catch (Throwable $e) {
    error_servidor($e);
}
