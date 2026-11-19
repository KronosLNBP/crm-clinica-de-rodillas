<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requiere_staff_api(ROLES_OPERACION);
solo_post();

$idCita  = (int)($_POST['id_cita'] ?? 0);
$tomadas = max(0, min(100, (int)($_POST['sesiones_tomadas'] ?? 0)));
$totales = max(1, min(100, (int)($_POST['sesiones_totales'] ?? 10)));
if ($idCita <= 0) error_usuario('Falta la cita.');
$pdf = decodificar_pdf((string)($_POST['pdf_data'] ?? ''));

verificar_cita_sucursal($pdo, $u, $idCita);

try {
    $st = $pdo->prepare('SELECT paciente, telefono, id_paciente FROM citas WHERE id_cita = ?');
    $st->execute([$idCita]);
    $cita = $st->fetch();
    if (!$cita) error_usuario('La cita no existe.', 404);

    $ruta = 'carnets_guardados/carnet_' . (int)$cita['id_paciente'] . '_' . bin2hex(random_bytes(6)) . '.pdf';
    if (file_put_contents(__DIR__ . '/' . $ruta, $pdf) === false) {
        throw new RuntimeException('No se pudo escribir ' . $ruta);
    }

    $mensaje = "Hola *{$cita['paciente']}*, te compartimos tu *Carnet Virtual* de Clínica de Rodillas.\n\n"
             . "📊 *Avance de tratamiento:* $tomadas de $totales sesiones.\n\n"
             . "🪪 *Ve y descarga tu carnet aquí:*\n" . url_archivo($ruta) . "\n\n"
             . '¡Nos vemos en tu próxima cita!';

    responder(['status' => 'success', 'wa_link' => link_whatsapp($cita['telefono'], $mensaje)]);
} catch (Throwable $e) {
    error_servidor($e);
}
