<?php
// Validación común para guardar_cita.php y actualizar_cita.php.
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

/** Valida el formulario de cita y devuelve los valores listos para la BD (o responde con error). */
function datos_cita_desde_post(PDO $pdo, array $u, int $idCitaActual = 0): array
{
    $fecha    = txt($_POST, 'fecha', 10);
    $hora     = txt($_POST, 'hora', 8);
    $paciente = preg_replace('/\s+/', ' ', txt($_POST, 'paciente', 150));
    $telefono = solo_digitos(txt($_POST, 'telefono', 20));
    $edadTxt  = txt($_POST, 'edad', 3);
    $estado   = txt($_POST, 'estado', 20) ?: 'Agendado';
    $asist    = txt($_POST, 'asistencia', 20) ?: 'Pendiente';
    $yaEs     = txt($_POST, 'ya_es_paciente', 2) === 'Si' ? 'Si' : 'No';
    $tipo     = txt($_POST, 'tipo', 100);
    $asistente = txt($_POST, 'asistente', 100);
    $sucNombre = sucursal_permitida($pdo, $u, txt($_POST, 'sucursal', 100));

    if (!fecha_valida($fecha)) error_usuario('La fecha no es válida.');
    if (!hora_valida($hora))   error_usuario('La hora no es válida.');
    if (mb_strlen($paciente) < 3) error_usuario('Escribe el nombre completo del paciente.');
    if (strlen($telefono) !== 10) error_usuario('El teléfono debe tener 10 dígitos.');
    if (!in_array($estado, ESTADOS_CITA, true)) error_usuario('Estado de cita no válido.');
    if (!in_array($asist, ASISTENCIAS, true))   error_usuario('Valor de asistencia no válido.');

    $edad = null;
    if ($edadTxt !== '') {
        if (!ctype_digit($edadTxt) || (int)$edadTxt < 1 || (int)$edadTxt > 110) {
            error_usuario('La edad debe estar entre 1 y 110.');
        }
        $edad = (int)$edadTxt;
    }

    $suc = sucursal_por_nombre($pdo, $sucNombre);
    if (!$suc) error_usuario('Sucursal no válida.');
    $srv = servicio_por_nombre($pdo, $tipo);
    if (!$srv) error_usuario('Tipo de tratamiento no válido.');

    $idPaciente = obtener_o_crear_paciente($pdo, $paciente, $telefono, (int)$suc['id_sucursal']);

    // Si ya asistió antes, es paciente: no depende de que recepción lo marque
    $st = $pdo->prepare("SELECT 1 FROM citas WHERE id_paciente = ? AND asistencia = 'Asistió' AND id_cita <> ? LIMIT 1");
    $st->execute([$idPaciente, $idCitaActual]);
    if ($st->fetchColumn()) {
        $yaEs = 'Si';
    }

    return [
        ':fecha' => $fecha, ':hora' => $hora,
        ':paciente' => $paciente, ':telefono' => $telefono, ':edad' => $edad,
        ':estado' => $estado, ':asistencia' => $asist, ':ya_es_paciente' => $yaEs,
        ':tipo' => $srv['nombre'], ':asistente' => $asistente, ':sucursal' => $suc['nombre_sucursal'],
        ':id_paciente' => $idPaciente, ':id_sucursal' => (int)$suc['id_sucursal'],
        ':id_servicio' => (int)$srv['id_servicio'],
        ':es_express' => $estado === 'Express' ? 1 : 0,
    ];
}
