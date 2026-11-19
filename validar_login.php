<?php
// Login del PORTAL DEL PACIENTE: folio de expediente (CR-00012) + teléfono.
// Acepta también el folio viejo CR-AAAA-NNNN (número de cita) para no romper carnets ya entregados.
// Con bloqueo tras 5 intentos fallidos (antes se podía adivinar probando números de cita).
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
iniciar_sesion();
solo_post();

$folio    = strtoupper(txt($_POST, 'folio', 20));
$telefono = solo_digitos(txt($_POST, 'telefono', 20));

try {
    if (demasiados_intentos($pdo, 'paciente')) {
        error_usuario('Demasiados intentos. Espera 15 minutos o pide ayuda en recepción.', 429);
    }

    $idPaciente = 0;
    if (preg_match('/^CR-(\d{1,6})$/', $folio, $m)) {
        $idPaciente = (int)$m[1];
    } elseif (preg_match('/^CR-\d{4}-(\d{1,6})$/', $folio, $m)) {
        $st = $pdo->prepare('SELECT id_paciente FROM citas WHERE id_cita = ?');
        $st->execute([(int)$m[1]]);
        $idPaciente = (int)$st->fetchColumn();
    }

    $pac = null;
    if ($idPaciente > 0) {
        $st = $pdo->prepare('SELECT id_paciente, nombre, telefono FROM pacientes WHERE id_paciente = ?');
        $st->execute([$idPaciente]);
        $pac = $st->fetch();
    }

    if (!$pac || strlen($telefono) < 10 || !hash_equals(solo_digitos($pac['telefono']), $telefono)) {
        registrar_intento($pdo, 'paciente');
        error_usuario('Folio o teléfono incorrectos. Verifica tus datos.', 401);
    }

    limpiar_intentos($pdo, 'paciente');
    session_regenerate_id(true);
    $_SESSION['paciente'] = [
        'id_paciente' => (int)$pac['id_paciente'],
        'nombre'      => $pac['nombre'],
        'folio'       => folio_expediente((int)$pac['id_paciente']),
    ];
    responder(['status' => 'success']);
} catch (Throwable $e) {
    error_servidor($e);
}
