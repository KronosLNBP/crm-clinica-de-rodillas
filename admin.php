<?php
// Administración: usuarios del personal y horarios de cada sucursal. Solo administradores.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$usuario = requiere_staff_pagina();
if ($usuario['rol'] !== 'admin') {
    http_response_code(403);
    exit('Solo administradores.');
}

$usuarios   = $pdo->query('SELECT u.id_usuario, u.nombre, u.usuario, u.rol, u.id_sucursal, u.activo, u.ultimo_acceso, s.nombre_sucursal
                             FROM usuarios u LEFT JOIN sucursales s ON s.id_sucursal = u.id_sucursal ORDER BY u.activo DESC, u.nombre')->fetchAll();
$sucursales = $pdo->query('SELECT id_sucursal, nombre_sucursal, intervalo_min FROM sucursales WHERE activo = 1 ORDER BY id_sucursal')->fetchAll();
$horarios   = [];
foreach ($pdo->query('SELECT id_sucursal, dia_semana, TIME_FORMAT(hora_inicio,"%H:%i") ini, TIME_FORMAT(hora_fin,"%H:%i") fin FROM sucursal_horarios') as $h) {
    $horarios[$h['id_sucursal']][$h['dia_semana']] = $h;
}
$dias  = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];
$roles = ['recepcion' => 'Recepción', 'medico' => 'Médico', 'terapeuta' => 'Terapeuta', 'contabilidad' => 'Contabilidad (solo consulta)', 'admin' => 'Administrador'];
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $e(csrf_token()) ?>">
    <title>Administración - Clínica de Rodillas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
<nav class="bg-[#0E7C66] text-white py-4 px-8 shadow-md">
    <div class="max-w-6xl mx-auto flex justify-between items-center">
        <h1 class="text-xl font-bold">⚙️ Administración</h1>
        <div class="text-sm"><a href="index.php" class="underline">← Volver a la agenda</a> · <a href="logout_staff.php" class="underline">Salir</a></div>
    </div>
</nav>

<div class="max-w-6xl mx-auto mt-6 px-4 pb-12 space-y-8">

    <!-- USUARIOS -->
    <section class="bg-white rounded-xl shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-black text-gray-800">👥 Usuarios del personal</h2>
            <button onclick="editarUsuario(null)" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded shadow text-sm">+ Nuevo usuario</button>
        </div>
        <p class="text-xs text-gray-500 mb-3">Si le asignas una sucursal, esa persona solo verá y trabajará con esa sucursal. Déjalo en "Todas" para administración y médicos que rotan.</p>
        <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-xs uppercase text-gray-500 bg-gray-50"><tr><th class="px-3 py-2">Nombre</th><th class="px-3 py-2">Usuario</th><th class="px-3 py-2">Rol</th><th class="px-3 py-2">Sucursal</th><th class="px-3 py-2">Último acceso</th><th class="px-3 py-2">Estado</th><th></th></tr></thead>
            <tbody class="divide-y">
            <?php foreach ($usuarios as $us): ?>
                <tr class="<?= $us['activo'] ? '' : 'opacity-50' ?>">
                    <td class="px-3 py-2 font-bold"><?= $e($us['nombre']) ?></td>
                    <td class="px-3 py-2 font-mono"><?= $e($us['usuario']) ?></td>
                    <td class="px-3 py-2"><?= $e($roles[$us['rol']] ?? $us['rol']) ?></td>
                    <td class="px-3 py-2"><?= $e($us['nombre_sucursal'] ?? 'Todas') ?></td>
                    <td class="px-3 py-2 text-gray-500"><?= $e($us['ultimo_acceso'] ?? '—') ?></td>
                    <td class="px-3 py-2"><?= $us['activo'] ? '✅ Activo' : '⛔ Inactivo' ?></td>
                    <td class="px-3 py-2 text-right"><button onclick='editarUsuario(<?= json_encode($us, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)' class="text-indigo-600 font-bold hover:underline">Editar</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <!-- HORARIOS -->
    <section class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-black text-gray-800 mb-1">🕘 Horario de cada sucursal</h2>
        <p class="text-xs text-gray-500 mb-4">La agenda solo muestra estas horas y avisa si alguien intenta agendar fuera de horario. Desmarca un día si la sucursal no abre.</p>
        <div class="grid md:grid-cols-2 gap-6">
        <?php foreach ($sucursales as $s): $hs = $horarios[$s['id_sucursal']] ?? []; ?>
            <form class="border rounded-lg p-4 form-horario" data-id="<?= (int)$s['id_sucursal'] ?>" onsubmit="guardarHorario(event, this)">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold text-blue-800"><?= $e($s['nombre_sucursal']) ?></h3>
                    <label class="text-xs text-gray-600">Citas cada
                        <select name="intervalo" class="border rounded p-1">
                            <?php foreach ([15, 20, 30, 45, 60] as $m): ?><option value="<?= $m ?>" <?= (int)$s['intervalo_min'] === $m ? 'selected' : '' ?>><?= $m ?> min</option><?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <table class="w-full text-sm">
                <?php foreach ($dias as $n => $nombreDia): $h = $hs[$n] ?? null; ?>
                    <tr class="fila-dia" data-dia="<?= $n ?>">
                        <td class="py-1"><label class="inline-flex items-center gap-2"><input type="checkbox" class="abre" <?= $h ? 'checked' : '' ?>> <?= $nombreDia ?></label></td>
                        <td class="py-1"><input type="time" class="ini border rounded p-1" value="<?= $e($h['ini'] ?? '09:00') ?>"></td>
                        <td class="py-1">a</td>
                        <td class="py-1"><input type="time" class="fin border rounded p-1" value="<?= $e($h['fin'] ?? '17:00') ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </table>
                <button class="mt-3 bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded text-sm">Guardar horario</button>
            </form>
        <?php endforeach; ?>
        </div>
    </section>
</div>

<!-- Modal usuario -->
<div id="modalUsuario" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center p-4 z-50">
    <form id="formUsuario" class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 space-y-3" onsubmit="guardarUsuario(event)">
        <h3 id="tituloUsuario" class="text-lg font-black">Nuevo usuario</h3>
        <input type="hidden" name="id_usuario">
        <label class="block text-sm">Nombre<input name="nombre" required class="w-full border rounded p-2 mt-1"></label>
        <label class="block text-sm">Usuario (para entrar)<input name="usuario" required class="w-full border rounded p-2 mt-1 font-mono" autocomplete="off"></label>
        <label class="block text-sm">Contraseña <span id="ayudaPass" class="text-gray-400 text-xs">(mín. 8)</span><input name="password" type="password" class="w-full border rounded p-2 mt-1" autocomplete="new-password"></label>
        <div class="grid grid-cols-2 gap-3">
            <label class="block text-sm">Rol<select name="rol" class="w-full border rounded p-2 mt-1"><?php foreach ($roles as $k => $v): ?><option value="<?= $k ?>"><?= $e($v) ?></option><?php endforeach; ?></select></label>
            <label class="block text-sm">Sucursal<select name="id_sucursal" class="w-full border rounded p-2 mt-1"><option value="">Todas</option><?php foreach ($sucursales as $s): ?><option value="<?= (int)$s['id_sucursal'] ?>"><?= $e($s['nombre_sucursal']) ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="activo" checked> Activo</label>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modalUsuario').classList.add('hidden')" class="px-4 py-2 rounded bg-gray-200 font-bold text-sm">Cancelar</button>
            <button class="px-4 py-2 rounded bg-emerald-600 text-white font-bold text-sm">Guardar</button>
        </div>
    </form>
</div>

<script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const post = (url, fd) => fetch(url, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': CSRF }, credentials: 'same-origin' }).then(r => r.json());

    function editarUsuario(u) {
        const f = document.getElementById('formUsuario'); f.reset();
        document.getElementById('tituloUsuario').innerText = u ? 'Editar usuario' : 'Nuevo usuario';
        document.getElementById('ayudaPass').innerText = u ? '(déjala vacía para no cambiarla)' : '(mín. 8)';
        f.id_usuario.value = u ? u.id_usuario : '';
        f.nombre.value = u ? u.nombre : ''; f.usuario.value = u ? u.usuario : '';
        f.rol.value = u ? u.rol : 'recepcion'; f.id_sucursal.value = u && u.id_sucursal ? u.id_sucursal : '';
        f.activo.checked = u ? u.activo == 1 : true;
        document.getElementById('modalUsuario').classList.remove('hidden');
    }
    function guardarUsuario(ev) {
        ev.preventDefault(); const f = ev.target; const fd = new FormData(f);
        fd.set('activo', f.activo.checked ? '1' : '0');
        post('guardar_usuario.php', fd).then(d => { alert(d.message); if (d.status === 'success') location.reload(); })
            .catch(() => alert('Error de red.'));
    }
    function guardarHorario(ev, form) {
        ev.preventDefault(); const dias = {};
        form.querySelectorAll('.fila-dia').forEach(tr => {
            if (tr.querySelector('.abre').checked) dias[tr.dataset.dia] = { ini: tr.querySelector('.ini').value, fin: tr.querySelector('.fin').value };
        });
        const fd = new FormData(); fd.append('id_sucursal', form.dataset.id); fd.append('intervalo_min', form.intervalo.value); fd.append('dias', JSON.stringify(dias));
        post('guardar_horarios.php', fd).then(d => alert(d.message)).catch(() => alert('Error de red.'));
    }
</script>
</body>
</html>
