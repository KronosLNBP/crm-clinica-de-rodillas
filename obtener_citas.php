<?php
// Citas para el calendario / directorio. Acepta rango de fechas para no cargar TODO el historial.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api();

$sucursal = sucursal_permitida($pdo, $u, txt($_GET, 'sucursal', 100));
$tipo     = txt($_GET, 'tipo', 100);
$yaEs     = txt($_GET, 'ya_es_paciente', 2);
$desde    = txt($_GET, 'desde', 10);
$hasta    = txt($_GET, 'hasta', 10);

try {
    $sql = "SELECT c.id_cita, c.id_paciente, c.fecha, c.hora, c.paciente, c.telefono, c.edad, c.estado, c.ya_es_paciente,
                   c.tipo_tratamiento, c.asistente, c.sucursal, c.asistencia, p.fecha_nacimiento, p.procedencia
              FROM citas c LEFT JOIN pacientes p ON p.id_paciente = c.id_paciente
             WHERE c.estado <> 'Cancelada'";
    $p = [];
    if ($sucursal !== '') { $sql .= ' AND c.sucursal = ?';         $p[] = $sucursal; }
    if ($tipo !== '')     { $sql .= ' AND c.tipo_tratamiento = ?'; $p[] = $tipo; }
    if ($yaEs === 'Si' || $yaEs === 'No') { $sql .= ' AND c.ya_es_paciente = ?'; $p[] = $yaEs; }
    if (fecha_valida($desde)) { $sql .= ' AND c.fecha >= ?'; $p[] = $desde; }
    if (fecha_valida($hasta)) { $sql .= ' AND c.fecha <= ?'; $p[] = $hasta; }
    // Sin rango (directorio) se traen las más recientes primero, para que el límite no corte las nuevas
    $sql .= (fecha_valida($desde) || fecha_valida($hasta))
        ? ' ORDER BY c.fecha ASC, c.hora ASC LIMIT 5000'
        : ' ORDER BY c.fecha DESC, c.hora DESC LIMIT 5000';

    $st = $pdo->prepare($sql);
    $st->execute($p);
    responder(['status' => 'success', 'data' => $st->fetchAll()]);
} catch (Throwable $e) {
    error_servidor($e);
}
