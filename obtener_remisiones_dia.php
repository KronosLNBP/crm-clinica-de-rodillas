<?php
// Pacientes del día en una sucursal + su remisión vigente (si ya se cobró).
// Se consulta aparte del calendario para que los filtros de tratamiento/paciente NO recorten el corte.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api();

$sucursal = sucursal_permitida($pdo, $u, txt($_GET, 'sucursal', 100));
$fecha    = txt($_GET, 'fecha', 10);
if ($sucursal === '' || !fecha_valida($fecha)) error_usuario('Sucursal o fecha no válidas.');

try {
    $st = $pdo->prepare(
        "SELECT c.id_cita, c.hora, c.paciente, c.telefono, c.sucursal, c.tipo_tratamiento, c.asistencia,
                r.id_remision, r.folio, r.servicio, r.efectivo, r.credito, r.debito, r.transferencia, r.total, r.ruta_archivo
           FROM citas c
           LEFT JOIN (SELECT id_cita, MAX(id_remision) AS id_rem
                        FROM remisiones_clientes WHERE estado = 'Vigente' GROUP BY id_cita) ult
                  ON ult.id_cita = c.id_cita
           LEFT JOIN remisiones_clientes r ON r.id_remision = ult.id_rem
          WHERE c.sucursal = ? AND c.fecha = ? AND c.estado <> 'Cancelada' AND c.asistencia <> 'No Asistió'
          ORDER BY c.hora, c.id_cita"
    );
    $st->execute([$sucursal, $fecha]);

    $st2 = $pdo->prepare('SELECT id_corte, fecha_registro FROM cortes_diarios WHERE sucursal = ? AND fecha = ?');
    $st2->execute([$sucursal, $fecha]);

    responder(['status' => 'success', 'data' => $st->fetchAll(), 'corte' => $st2->fetch() ?: null]);
} catch (Throwable $e) {
    error_servidor($e);
}
