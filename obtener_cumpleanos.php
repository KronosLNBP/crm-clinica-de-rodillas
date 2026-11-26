<?php
// Cumpleaños de hoy y de mañana (el aviso sale un día antes para preparar la felicitación).
// Un paciente cuenta para una sucursal si es su sucursal habitual o si ha tenido citas ahí.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api();
$sucursal = sucursal_permitida($pdo, $u, txt($_GET, 'sucursal', 100));

try {
    $hoy = new DateTime('today');
    $manana = (clone $hoy)->modify('+1 day');

    $buscar = function (DateTime $dia) use ($pdo, $sucursal): array {
        $anio = (int)$dia->format('Y');   // el año del cumpleaños (31 dic → 1 ene cambia)
        $mes = (int)$dia->format('n');
        $d = (int)$dia->format('j');
        // Quien nació un 29 de febrero se felicita el 28 en años no bisiestos
        $esBisiesto = (bool)$dia->format('L');
        $extra = (!$esBisiesto && $mes === 2 && $d === 28) ? ' OR (MONTH(p.fecha_nacimiento) = 2 AND DAY(p.fecha_nacimiento) = 29)' : '';

        $sql = "SELECT p.id_paciente, p.nombre, p.telefono, p.fecha_nacimiento, p.procedencia,
                       (SELECT c.sucursal FROM citas c WHERE c.id_paciente = p.id_paciente ORDER BY c.fecha DESC LIMIT 1) AS sucursal,
                       EXISTS (SELECT 1 FROM felicitaciones f WHERE f.id_paciente = p.id_paciente AND f.anio = ?) AS felicitado
                  FROM pacientes p
                 WHERE p.fecha_nacimiento IS NOT NULL
                   AND ((MONTH(p.fecha_nacimiento) = ? AND DAY(p.fecha_nacimiento) = ?)$extra)";
        $params = [$anio, $mes, $d];
        if ($sucursal !== '') {
            $sql .= ' AND (p.id_sucursal = (SELECT id_sucursal FROM sucursales WHERE nombre_sucursal = ?)
                      OR EXISTS (SELECT 1 FROM citas c2 WHERE c2.id_paciente = p.id_paciente AND c2.sucursal = ?))';
            array_push($params, $sucursal, $sucursal);
        }
        $st = $pdo->prepare($sql . ' ORDER BY p.nombre');
        $st->execute($params);
        $filas = $st->fetchAll();
        foreach ($filas as &$f) {
            $f['cumple'] = $anio - (int)substr((string)$f['fecha_nacimiento'], 0, 4);
            $f['felicitado'] = (bool)$f['felicitado'];
            $f['anio'] = $anio;
        }
        return $filas;
    };

    responder(['status' => 'success', 'hoy' => $buscar($hoy), 'manana' => $buscar($manana)]);
} catch (Throwable $e) {
    error_servidor($e);
}
