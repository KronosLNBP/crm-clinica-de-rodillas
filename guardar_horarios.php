<?php
// Guarda el horario semanal de una sucursal (solo administradores).
// POST: id_sucursal, intervalo_min, dias = JSON {"1":{"ini":"09:00","fin":"17:00"}, ...}  (día ausente = cerrado)
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(['admin']);
solo_post();

$idSuc     = (int)($_POST['id_sucursal'] ?? 0);
$intervalo = (int)($_POST['intervalo_min'] ?? 30);
$dias      = json_decode((string)($_POST['dias'] ?? ''), true);

if ($idSuc <= 0 || !is_array($dias)) error_usuario('Datos incompletos.');
if (!in_array($intervalo, [15, 20, 30, 45, 60], true)) error_usuario('Intervalo no válido.');

$filas = [];
foreach ($dias as $dia => $h) {
    $dia = (int)$dia;
    $ini = (string)($h['ini'] ?? '');
    $fin = (string)($h['fin'] ?? '');
    if ($dia < 0 || $dia > 6 || !hora_valida($ini) || !hora_valida($fin) || $fin <= $ini) {
        error_usuario('Revisa el horario del día ' . ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][$dia] . '.');
    }
    $filas[] = [$idSuc, $dia, $ini, $fin];
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE sucursales SET intervalo_min = ? WHERE id_sucursal = ?')->execute([$intervalo, $idSuc]);
    $pdo->prepare('DELETE FROM sucursal_horarios WHERE id_sucursal = ?')->execute([$idSuc]);
    $st = $pdo->prepare('INSERT INTO sucursal_horarios (id_sucursal, dia_semana, hora_inicio, hora_fin) VALUES (?,?,?,?)');
    foreach ($filas as $f) {
        $st->execute($f);
    }
    bitacora($pdo, $u['id'], 'sucursal_horarios', $idSuc, 'editar', null, ['intervalo' => $intervalo, 'dias' => $dias]);
    $pdo->commit();
    responder(['status' => 'success', 'message' => 'Horario guardado.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_servidor($e);
}
