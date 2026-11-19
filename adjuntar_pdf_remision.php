<?php
// Guarda el PDF de una remisión ya registrada y arma el link de WhatsApp para el paciente.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

requiere_staff_api();
solo_post();

$idRem = (int)($_POST['id_remision'] ?? 0);
if ($idRem <= 0) error_usuario('Falta la remisión.');
$pdf = decodificar_pdf((string)($_POST['pdf_data'] ?? ''));

try {
    $st = $pdo->prepare("SELECT folio, paciente, telefono, total FROM remisiones_clientes WHERE id_remision = ? AND estado = 'Vigente'");
    $st->execute([$idRem]);
    $r = $st->fetch();
    if (!$r) error_usuario('La remisión no existe o está cancelada.', 404);

    $ruta = 'remisiones_guardadas/remision_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $r['folio']) . '_' . bin2hex(random_bytes(6)) . '.pdf';
    if (file_put_contents(__DIR__ . '/' . $ruta, $pdf) === false) {
        throw new RuntimeException('No se pudo escribir ' . $ruta);
    }
    $pdo->prepare('UPDATE remisiones_clientes SET ruta_archivo = ? WHERE id_remision = ?')->execute([$ruta, $idRem]);

    $mensaje = "Hola *{$r['paciente']}*, te enviamos el comprobante de tu servicio en *Clínica de Rodillas*.\n\n"
             . "🧾 *Folio:* {$r['folio']}\n"
             . '💰 *Total pagado:* $' . number_format((float)$r['total'], 2) . "\n\n"
             . "📄 *Descarga tu recibo en PDF aquí:*\n" . url_archivo($ruta) . "\n\n"
             . '¡Gracias por tu preferencia!';

    responder(['status' => 'success', 'wa_link' => link_whatsapp($r['telefono'], $mensaje)]);
} catch (Throwable $e) {
    error_servidor($e);
}
