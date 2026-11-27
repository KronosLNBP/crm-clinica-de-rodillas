<?php
// Administración: reporte de remisiones, usuarios del personal y horarios de cada sucursal. Solo administradores.
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
$roles = ['asistente' => 'Asistente (citas, cobros, pacientes y cumpleaños)', 'admin' => 'Administrador (todo, incluye cortes y usuarios)'];
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="manifest.webmanifest">
    <meta name="theme-color" content="#0E7C66">
    <link rel="icon" href="icons/icon-192.png">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
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

    <div class="flex flex-wrap gap-2 text-sm font-semibold">
        <a href="#remisiones" class="bg-white rounded-full shadow px-4 py-1.5 text-[#0E7C66]">🧾 Remisiones</a>
        <a href="#usuarios" class="bg-white rounded-full shadow px-4 py-1.5 text-[#0E7C66]">👥 Usuarios</a>
        <a href="#horarios" class="bg-white rounded-full shadow px-4 py-1.5 text-[#0E7C66]">🕘 Horarios</a>
    </div>

    <!-- REMISIONES -->
    <section id="remisiones" class="bg-white rounded-xl shadow p-6 scroll-mt-4">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <div><h2 class="text-lg font-black text-gray-800">🧾 Remisiones generadas</h2><p class="text-sm text-gray-500">Todas las sucursales. Los totales cuentan solo remisiones vigentes.</p></div>
            <button onclick="descargarRemCsv()" class="bg-white border border-emerald-600 text-emerald-700 hover:bg-emerald-50 font-bold px-4 py-2 rounded text-sm">⬇️ Descargar Excel (CSV)</button>
        </div>
        <form id="filtrosRem" onsubmit="event.preventDefault(); cargarRem(1)" onchange="cargarRem(1)" class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end text-sm mb-3">
            <label class="block">Sucursal<select name="sucursal" class="w-full border rounded p-2 mt-1 bg-white"><option value="">Todas</option><?php foreach ($sucursales as $s): ?><option><?= $e($s['nombre_sucursal']) ?></option><?php endforeach; ?></select></label>
            <label class="block">Desde<input type="date" name="desde" class="w-full border rounded p-2 mt-1"></label>
            <label class="block">Hasta<input type="date" name="hasta" class="w-full border rounded p-2 mt-1"></label>
            <label class="block">Método de pago<select name="metodo" class="w-full border rounded p-2 mt-1 bg-white"><option value="">Todos</option><option value="efectivo">Efectivo</option><option value="credito">Tarjeta de crédito</option><option value="debito">Tarjeta de débito</option><option value="transferencia">Transferencia</option></select></label>
            <label class="block">Estado<select name="estado" class="w-full border rounded p-2 mt-1 bg-white"><option value="">Todas</option><option value="Vigente">Vigentes</option><option value="Cancelada">Canceladas</option></select></label>
            <label class="block col-span-2 md:col-span-1">Buscar<input type="search" name="q" placeholder="Cliente, folio o concepto" oninput="clearTimeout(window._tRem); window._tRem = setTimeout(() => cargarRem(1), 400)" class="w-full border rounded p-2 mt-1"></label>
        </form>
        <div class="flex flex-wrap gap-2 mb-4 text-xs font-semibold">
            <button type="button" onclick="rangoRem('hoy')" class="bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1">Hoy</button>
            <button type="button" onclick="rangoRem('semana')" class="bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1">Esta semana</button>
            <button type="button" onclick="rangoRem('mes')" class="bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1">Este mes</button>
            <button type="button" onclick="rangoRem('mesPasado')" class="bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1">Mes pasado</button>
            <button type="button" onclick="rangoRem('anio')" class="bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1">Este año</button>
        </div>
        <div id="resumenRem" class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-3"></div>
        <div id="sucRem" class="flex flex-wrap gap-2 mb-4 text-xs"></div>
        <div class="overflow-x-auto border rounded-lg">
            <table class="w-full text-sm text-left min-w-[860px]">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="p-2">Folio</th><th class="p-2">Fecha</th><th class="p-2">Sucursal</th><th class="p-2">Cliente y conceptos</th><th class="p-2">Método</th><th class="p-2 text-right">Total</th><th class="p-2">Registró</th><th class="p-2"></th></tr></thead>
                <tbody id="tablaRem"><tr><td colspan="8" class="p-6 text-center text-gray-400">Cargando…</td></tr></tbody>
            </table>
        </div>
        <div id="pagRem" class="flex justify-between items-center mt-3 text-sm"></div>
    </section>

    <!-- USUARIOS -->
    <section id="usuarios" class="bg-white rounded-xl shadow p-6 scroll-mt-4">
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
    <section id="horarios" class="bg-white rounded-xl shadow p-6 scroll-mt-4">
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
        f.rol.value = u ? u.rol : 'asistente'; f.id_sucursal.value = u && u.id_sucursal ? u.id_sucursal : '';
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

    // ===== Reporte de remisiones =====
    const escH = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const mxn = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    let remData = [];
    function rangoRem(tipo) {
        const h = new Date(), f = document.getElementById('filtrosRem');
        let ini = new Date(h), fin = new Date(h);
        if (tipo === 'semana') { ini.setDate(h.getDate() - ((h.getDay() + 6) % 7)); }
        if (tipo === 'mes') { ini = new Date(h.getFullYear(), h.getMonth(), 1); }
        if (tipo === 'mesPasado') { ini = new Date(h.getFullYear(), h.getMonth() - 1, 1); fin = new Date(h.getFullYear(), h.getMonth(), 0); }
        if (tipo === 'anio') { ini = new Date(h.getFullYear(), 0, 1); }
        f.desde.value = iso(ini); f.hasta.value = iso(fin); cargarRem(1);
    }
    function paramsRem(extra = {}) { return new URLSearchParams({ ...Object.fromEntries(new FormData(document.getElementById('filtrosRem'))), ...extra }); }
    function metodoRem(r) {
        if (r.metodo_pago) return r.metodo_pago;
        return [['Efectivo', r.efectivo], ['T. crédito', r.credito], ['T. débito', r.debito], ['Transferencia', r.transferencia]].filter(m => parseFloat(m[1]) > 0).map(m => m[0]).join(' + ') || '—';
    }
    function cargarRem(pagina) {
        const tb = document.getElementById('tablaRem');
        tb.innerHTML = '<tr><td colspan="8" class="p-6 text-center text-gray-400">Cargando…</td></tr>';
        fetch('obtener_remisiones_admin.php?' + paramsRem({ pagina }), { credentials: 'same-origin' }).then(r => r.json()).then(d => {
            if (d.status !== 'success') { tb.innerHTML = `<tr><td colspan="8" class="p-6 text-center text-red-600">${escH(d.message)}</td></tr>`; return; }
            remData = d.data; const s = d.resumen;
            const tarjeta = (t, v, sub = '') => `<div class="rounded-lg border p-3"><p class="text-xs text-gray-500">${t}</p><p class="text-lg font-black">${v}</p>${sub ? `<p class="text-xs text-gray-400">${sub}</p>` : ''}</div>`;
            document.getElementById('resumenRem').innerHTML =
                tarjeta('Remisiones', s.n, s.canceladas > 0 ? `${s.canceladas} cancelada${s.canceladas == 1 ? '' : 's'}` : '')
                + tarjeta('Total vigente', mxn.format(s.total)) + tarjeta('Efectivo', mxn.format(s.efectivo))
                + tarjeta('Tarjetas', mxn.format(+s.credito + +s.debito), `Crédito ${mxn.format(s.credito)} · Débito ${mxn.format(s.debito)}`) + tarjeta('Transferencia', mxn.format(s.transferencia));
            document.getElementById('sucRem').innerHTML = d.por_sucursal.map(x => `<span class="bg-emerald-50 text-emerald-800 rounded-full px-3 py-1 font-semibold">${escH(x.sucursal)}: ${mxn.format(x.total)} · ${x.n}</span>`).join('');
            tb.innerHTML = remData.length ? remData.map((r, i) => {
                const canc = r.estado === 'Cancelada';
                const conceptos = r.conceptos.length ? r.conceptos.map(c => `${c.cantidad > 1 ? c.cantidad + ' × ' : ''}${escH(c.descripcion)}`).join(', ') : escH(r.servicio);
                return `<tr class="border-t ${canc ? 'bg-red-50/50 text-gray-400' : ''}">
                    <td class="p-2 font-mono font-bold ${canc ? '' : 'text-emerald-700'}">${escH(r.folio)}${canc ? '<span class="block text-[10px] font-sans font-bold text-red-600">CANCELADA</span>' : ''}</td>
                    <td class="p-2 whitespace-nowrap">${escH(r.fecha)}</td><td class="p-2">${escH(r.sucursal)}</td>
                    <td class="p-2"><p class="font-semibold ${canc ? 'line-through' : 'text-gray-800'}">${escH(r.paciente)}${r.id_cita ? '' : ' <span class="text-[10px] text-sky-700 font-normal">(sin cita)</span>'}</p><p class="text-xs text-gray-500">${conceptos}</p>${r.observaciones ? `<p class="text-xs text-gray-400 italic">${escH(r.observaciones)}</p>` : ''}</td>
                    <td class="p-2 text-xs">${escH(metodoRem(r))}</td>
                    <td class="p-2 text-right font-black whitespace-nowrap">${mxn.format(r.total)}</td>
                    <td class="p-2 text-xs">${escH(r.atendio || r.usuario || '')}</td>
                    <td class="p-2 text-right whitespace-nowrap">${r.ruta_archivo ? `<a href="ver_archivo.php?f=${encodeURIComponent(r.ruta_archivo)}" target="_blank" class="text-emerald-700 font-semibold hover:underline">PDF</a>` : ''}${canc ? '' : ` <button onclick="cancelarRem(${i})" class="ml-2 text-red-600 font-semibold hover:underline">Cancelar</button>`}</td></tr>`;
            }).join('') : '<tr><td colspan="8" class="p-6 text-center text-gray-400">No hay remisiones con estos filtros.</td></tr>';
            document.getElementById('pagRem').innerHTML = d.paginas > 1
                ? `<button ${d.pagina <= 1 ? 'disabled' : ''} onclick="cargarRem(${d.pagina - 1})" class="px-3 py-1 rounded bg-gray-100 disabled:opacity-40">← Anterior</button><span class="text-gray-500">Página ${d.pagina} de ${d.paginas}</span><button ${d.pagina >= d.paginas ? 'disabled' : ''} onclick="cargarRem(${d.pagina + 1})" class="px-3 py-1 rounded bg-gray-100 disabled:opacity-40">Siguiente →</button>` : '';
        }).catch(() => { tb.innerHTML = '<tr><td colspan="8" class="p-6 text-center text-red-600">Error de conexión.</td></tr>'; });
    }
    function descargarRemCsv() { location.href = 'obtener_remisiones_admin.php?' + paramsRem({ formato: 'csv' }); }
    function cancelarRem(i) {
        const r = remData[i]; if (!r) return;
        const motivo = prompt(`Cancelar la remisión ${r.folio} de ${r.paciente} por ${mxn.format(r.total)}.\n\n¿Motivo de la cancelación?`);
        if (!motivo || !motivo.trim()) return;
        const fd = new FormData(); fd.append('id_remision', r.id_remision); fd.append('motivo', motivo.trim());
        post('cancelar_remision.php', fd).then(d => { alert(d.message); if (d.status === 'success') cargarRem(1); }).catch(() => alert('Error de red.'));
    }
    rangoRem('mes');
</script>
</body>
</html>
