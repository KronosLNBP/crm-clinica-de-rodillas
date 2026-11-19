<?php
// Funciones compartidas: respuestas JSON, validación, catálogos, pacientes, bitácora.
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';

// Cualquier error no atrapado: se registra en el log y se muestra un mensaje claro (no un 500 en blanco)
set_exception_handler(function (Throwable $e): void {
    error_log('[CRM] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $sqlState = $e instanceof PDOException ? (string)$e->getCode() : '';
    $msg = in_array($sqlState, ['42S02', '42S22'], true)
        ? 'La base de datos no está actualizada: falta correr migracion_v2.sql en phpMyAdmin.'
        : 'Ocurrió un error en el servidor. Intenta de nuevo.';
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html')) {
        if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
        echo '<div style="font-family:sans-serif;max-width:520px;margin:80px auto;padding:20px;border:1px solid #fca5a5;background:#fef2f2;border-radius:10px;color:#991b1b">'
           . '<strong>Error</strong><br>' . htmlspecialchars($msg) . '</div>';
    } else {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    }
});

const ESTADOS_CITA =['Agendado', 'Confirmada', 'Express', 'Reagendada', 'Completada']; // 'Cancelada' solo vía eliminar_cita.php
const ASISTENCIAS  = ['Pendiente', 'Asistió', 'No Asistió'];
const MAX_PDF_BYTES = 5 * 1024 * 1024;
const ROLES_OPERACION = ['admin', 'recepcion', 'medico', 'terapeuta'];   // pueden modificar
const ROLES_CLINICOS  = ['admin', 'medico', 'terapeuta'];                // ven/escriben notas clínicas
// 'contabilidad' = solo consulta (cortes, remisiones, agenda)

function cfg(string $clave): string
{
    global $cfg;
    return (string)($cfg[$clave] ?? '');
}

function responder(array $data, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function error_usuario(string $mensaje, int $codigo = 422): never
{
    responder(['status' => 'error', 'message' => $mensaje], $codigo);
}

function error_servidor(Throwable $e): never
{
    error_log('[CRM] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $falta = $e instanceof PDOException && in_array((string)$e->getCode(), ['42S02', '42S22'], true);
    responder(['status' => 'error', 'message' => $falta
        ? 'La base de datos no está actualizada: falta correr migracion_v2.sql.'
        : 'Ocurrió un error en el servidor. Intenta de nuevo.'], 500);
}

function solo_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        error_usuario('Método no permitido.', 405);
    }
}

/** Texto de $_POST/$_GET recortado. Trata "null"/"undefined" (que manda FormData) como vacío. */
function txt(array $origen, string $clave, int $max = 255): string
{
    $v = trim((string)($origen[$clave] ?? ''));
    if ($v === 'null' || $v === 'undefined') {
        $v = '';
    }
    return mb_substr($v, 0, $max);
}

function dinero(array $origen, string $clave): float
{
    $v = $origen[$clave] ?? 0;
    return is_numeric($v) ? round(max(0.0, (float)$v), 2) : 0.0;
}

function fecha_valida(string $f): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $f);
    return $d !== false && $d->format('Y-m-d') === $f;
}

function hora_valida(string $h): bool
{
    return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $h);
}

function solo_digitos(string $t): string
{
    return preg_replace('/\D+/', '', $t) ?? '';
}

function ip_cliente(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

// ---------- Catálogos ----------
function sucursal_por_nombre(PDO $pdo, string $nombre): ?array
{
    $st = $pdo->prepare('SELECT id_sucursal, nombre_sucursal, clave FROM sucursales WHERE nombre_sucursal = ? AND activo = 1');
    $st->execute([$nombre]);
    return $st->fetch() ?: null;
}

function servicio_por_nombre(PDO $pdo, string $nombre): ?array
{
    $st = $pdo->prepare('SELECT id_servicio, nombre FROM servicios WHERE nombre = ? AND activo = 1');
    $st->execute([$nombre]);
    return $st->fetch() ?: null;
}

// ---------- Sucursal asignada al usuario ----------
/** Sucursal fija del usuario, o null si puede ver todas. */
function sucursal_del_usuario(PDO $pdo, array $u): ?array
{
    if (empty($u['id_sucursal'])) {
        return null;
    }
    $st = $pdo->prepare('SELECT id_sucursal, nombre_sucursal, clave FROM sucursales WHERE id_sucursal = ?');
    $st->execute([$u['id_sucursal']]);
    return $st->fetch() ?: null;
}

/** Nombre de sucursal que el usuario puede usar. Si tiene una asignada, se fuerza la suya. */
function sucursal_permitida(PDO $pdo, array $u, string $pedida): string
{
    $s = sucursal_del_usuario($pdo, $u);
    if (!$s) {
        return $pedida;
    }
    if ($pedida !== '' && $pedida !== $s['nombre_sucursal']) {
        error_usuario('Solo puedes trabajar con la sucursal ' . $s['nombre_sucursal'] . '.', 403);
    }
    return $s['nombre_sucursal'];
}

function verificar_cita_sucursal(PDO $pdo, array $u, int $idCita): void
{
    $s = sucursal_del_usuario($pdo, $u);
    if (!$s) {
        return;
    }
    $st = $pdo->prepare('SELECT sucursal FROM citas WHERE id_cita = ?');
    $st->execute([$idCita]);
    $suc = $st->fetchColumn();
    if ($suc !== false && $suc !== $s['nombre_sucursal']) {
        error_usuario('Esa cita es de otra sucursal.', 403);
    }
}

// ---------- Horarios y ausencias ----------
/** Devuelve un aviso si la fecha/hora cae fuera de horario o en una ausencia; null si todo bien. */
function aviso_agenda(PDO $pdo, int $idSucursal, string $fecha, string $hora, bool $express): ?string
{
    $hora = substr($hora, 0, 5) . ':00';
    if (!$express) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM sucursal_horarios WHERE id_sucursal = ?');
        $st->execute([$idSucursal]);
        if ((int)$st->fetchColumn() > 0) {                  // solo si la sucursal ya tiene horario configurado
            $st = $pdo->prepare('SELECT hora_inicio, hora_fin FROM sucursal_horarios WHERE id_sucursal = ? AND dia_semana = ?');
            $st->execute([$idSucursal, (int)date('w', strtotime($fecha))]);
            $h = $st->fetch();
            if (!$h) {
                return 'La sucursal no abre ese día.';
            }
            if ($hora < $h['hora_inicio'] || $hora >= $h['hora_fin']) {
                return 'Esa hora está fuera del horario de la sucursal (' . substr($h['hora_inicio'], 0, 5) . ' a ' . substr($h['hora_fin'], 0, 5) . ').';
            }
        }
    }
    $st = $pdo->prepare(
        'SELECT medico, motivo FROM bloqueos_agenda
          WHERE id_sucursal = ? AND ? BETWEEN fecha_inicio AND fecha_fin
            AND (hora_inicio IS NULL OR (? >= hora_inicio AND ? < hora_fin)) LIMIT 1'
    );
    $st->execute([$idSucursal, $fecha, $hora, $hora]);
    if ($b = $st->fetch()) {
        return 'Agenda bloqueada: ' . trim(($b['medico'] ? $b['medico'] . ' – ' : '') . $b['motivo']) . '.';
    }
    return null;
}

// ---------- Pacientes ----------
/** Devuelve el id del paciente (nombre + teléfono); lo crea si no existe. Seguro ante dos guardados simultáneos. */
function obtener_o_crear_paciente(PDO $pdo, string $nombre, string $telefono, ?int $idSucursal): int
{
    $st = $pdo->prepare(
        'INSERT INTO pacientes (nombre, telefono, id_sucursal) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE id_paciente = LAST_INSERT_ID(id_paciente)'
    );
    $st->execute([$nombre, $telefono, $idSucursal]);
    return (int)$pdo->lastInsertId();
}

function folio_expediente(int $idPaciente): string
{
    return 'CR-' . str_pad((string)$idPaciente, 5, '0', STR_PAD_LEFT);
}

/** Historial, asistencias e inversión de un paciente (lo usan el CRM y el portal). */
function perfil_paciente(PDO $pdo, int $idPaciente): ?array
{
    $st = $pdo->prepare('SELECT id_paciente, nombre, telefono FROM pacientes WHERE id_paciente = ?');
    $st->execute([$idPaciente]);
    $pac = $st->fetch();
    if (!$pac) {
        return null;
    }

    $st = $pdo->prepare(
        "SELECT id_cita, fecha, hora, sucursal, tipo_tratamiento, asistencia, estado
           FROM citas WHERE id_paciente = ? AND estado <> 'Cancelada'
          ORDER BY fecha DESC, hora DESC"
    );
    $st->execute([$idPaciente]);
    $historial = $st->fetchAll();

    $asis = $faltas = $pend = 0;
    foreach ($historial as $c) {
        if ($c['asistencia'] === 'Asistió') {
            $asis++;
        } elseif ($c['asistencia'] === 'No Asistió') {
            $faltas++;
        } else {
            $pend++;
        }
    }

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(r.total), 0)
           FROM remisiones_clientes r JOIN citas c ON c.id_cita = r.id_cita
          WHERE c.id_paciente = ? AND r.estado = 'Vigente'"
    );
    $st->execute([$idPaciente]);

    return [
        'id_paciente' => (int)$pac['id_paciente'],
        'paciente'    => $pac['nombre'],
        'telefono'    => $pac['telefono'],
        'folio'       => folio_expediente((int)$pac['id_paciente']),
        'total_citas' => count($historial),
        'asistencias' => $asis,
        'faltas'      => $faltas,
        'pendientes'  => $pend,
        'inversion'   => (float)$st->fetchColumn(),
        'historial'   => $historial,
    ];
}

// ---------- Bitácora ----------
function bitacora(PDO $pdo, ?int $idUsuario, string $tabla, int $idRegistro, string $accion, ?array $antes = null, ?array $despues = null): void
{
    $st = $pdo->prepare('INSERT INTO bitacora (id_usuario, tabla, id_registro, accion, antes, despues, ip) VALUES (?,?,?,?,?,?,?)');
    $st->execute([
        $idUsuario, $tabla, $idRegistro, $accion,
        $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
        $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
        ip_cliente(),
    ]);
}

// ---------- Fuerza bruta ----------
function demasiados_intentos(PDO $pdo, string $tipo, int $max = 5): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM intentos_login WHERE ip = ? AND tipo = ? AND fecha > NOW() - INTERVAL 15 MINUTE');
    $st->execute([ip_cliente(), $tipo]);
    return (int)$st->fetchColumn() >= $max;
}

function registrar_intento(PDO $pdo, string $tipo): void
{
    $pdo->prepare('INSERT INTO intentos_login (ip, tipo) VALUES (?, ?)')->execute([ip_cliente(), $tipo]);
}

function limpiar_intentos(PDO $pdo, string $tipo): void
{
    $pdo->prepare('DELETE FROM intentos_login WHERE ip = ? AND tipo = ?')->execute([ip_cliente(), $tipo]);
}

// ---------- Archivos PDF ----------
/** Decodifica el data-URI que genera html2pdf y valida que de verdad sea un PDF. */
function decodificar_pdf(string $base64): string
{
    $partes = explode(',', $base64, 2);
    $datos = base64_decode(end($partes), true);
    if ($datos === false || strncmp($datos, '%PDF-', 5) !== 0) {
        error_usuario('El PDF generado no es válido.');
    }
    if (strlen($datos) > MAX_PDF_BYTES) {
        error_usuario('El PDF es demasiado grande.');
    }
    return $datos;
}

/** Link firmado para que el paciente abra su PDF sin iniciar sesión (no se puede adivinar). */
function url_archivo(string $ruta): string
{
    $firma = hash_hmac('sha256', $ruta, cfg('app_secret'));
    return cfg('base_url') . '/ver_archivo.php?f=' . rawurlencode($ruta) . '&t=' . $firma;
}

function link_whatsapp(string $telefono, string $mensaje): string
{
    $tel = solo_digitos($telefono);
    if (strlen($tel) === 10) {
        $tel = '52' . $tel;
    }
    return 'https://wa.me/' . $tel . '?text=' . rawurlencode($mensaje);
}
