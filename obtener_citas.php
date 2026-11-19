<?php
// Citas para el calendario / directorio. Acepta rango de fechas para no cargar TODO el historial.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

requiere_staff_api();

$sucursal = txt($_GET, 'sucursal', 100);
$tipo     = txt($_GET, 'tipo', 100);
$yaEs     = txt($_GET, 'ya_es_paciente', 2);
$desde    = txt($_GET, 'desde', 10);
$hasta    = txt($_GET, 'hasta', 10);

try {
    $sql = "SELECT id_cita, id_paciente, fecha, hora, paciente, telefono, edad, estado, ya_es_paciente,
                   tipo_tratamiento, asistente, sucursal, asistencia
              FROM citas WHERE estado <> 'Cancelada'";
    $p = [];
    if ($sucursal !== '') { $sql .= ' AND sucursal = ?';         $p[] = $sucursal; }
    if ($tipo !== '')     { $sql .= ' AND tipo_tratamiento = ?'; $p[] = $tipo; }
    if ($yaEs === 'Si' || $yaEs === 'No') { $sql .= ' AND ya_es_paciente = ?'; $p[] = $yaEs; }
    if (fecha_valida($desde)) { $sql .= ' AND fecha >= ?'; $p[] = $desde; }
    if (fecha_valida($hasta)) { $sql .= ' AND fecha <= ?'; $p[] = $hasta; }
    // Sin rango (directorio) se traen las más recientes primero, para que el límite no corte las nuevas
    $sql .= (fecha_valida($desde) || fecha_valida($hasta))
        ? ' ORDER BY fecha ASC, hora ASC LIMIT 5000'
        : ' ORDER BY fecha DESC, hora DESC LIMIT 5000';

    $st = $pdo->prepare($sql);
    $st->execute($p);
    responder(['status' => 'success', 'data' => $st->fetchAll()]);
} catch (Throwable $e) {
    error_servidor($e);
}
