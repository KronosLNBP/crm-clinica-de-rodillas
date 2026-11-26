<?php
require_once __DIR__ . '/auth.php';
$usuario = requiere_staff_pagina();   // sin sesión → login_staff.php
$rol = $usuario['rol'];
$sucUsuario = sucursal_del_usuario($pdo, $usuario);   // null = puede ver todas
$listaSucursales = $sucUsuario
    ? [$sucUsuario['nombre_sucursal']]
    : $pdo->query('SELECT nombre_sucursal FROM sucursales WHERE activo = 1 ORDER BY id_sucursal')->fetchAll(PDO::FETCH_COLUMN);
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$opcionesSucursal = implode('', array_map(fn($x) => '<option value="' . $h($x) . '">' . $h($x) . '</option>', $listaSucursales));
$esClinico = in_array($rol, ROLES_CLINICOS, true);
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
    <title>CRM Clínica de Rodillas - Agenda</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Great+Vibes&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { sans: ['Figtree', 'system-ui', 'sans-serif'] },
            colors: { tinta: '#1E2B33', niebla: '#F3F6F5', jade: { DEFAULT: '#0E7C66', claro: '#E3F2EE', oscuro: '#0A5E4D' }, ambar: '#E8A33D', rosa: '#C8445A' }
        } } };
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root { --tinta: #1E2B33; --niebla: #F3F6F5; --jade: #0E7C66; --jade-claro: #E3F2EE; --linea: #DDE5E2; --suave: #6B7B83; }
        body { font-family: 'Figtree', system-ui, sans-serif; color: var(--tinta); }
        .tabular { font-variant-numeric: tabular-nums; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: #C9D4D0; border-radius: 10px; }
        :focus-visible { outline: 3px solid #0E7C66; outline-offset: 2px; }

        /* Barra lateral */
        .nav-item { display: flex; align-items: center; gap: .75rem; width: 100%; padding: .6rem .75rem; border-radius: .75rem; font-weight: 600; color: #3C4A52; text-align: left; transition: background .15s; }
        .nav-item:hover { background: #E9EFED; }
        .nav-item svg { width: 20px; height: 20px; flex-shrink: 0; color: #6B7B83; }
        .menu-item { cursor: pointer; }
        .menu-item.menu-activo { background: #fff; box-shadow: inset 0 0 0 2px var(--jade); color: var(--tinta); }
        .punto { width: .7rem; height: .7rem; border-radius: 999px; flex-shrink: 0; }

        /* Filtros tipo "chip" */
        .filtro-btn, .filtro-pac-btn { border: 1.5px solid var(--linea) !important; background: #fff !important; color: #3C4A52 !important; border-radius: 999px !important; box-shadow: none !important; font-weight: 600 !important; }
        .filtro-btn:hover, .filtro-pac-btn:hover { border-color: #9DB3AB !important; }
        .filtro-activo { background: var(--tinta) !important; color: #fff !important; border-color: var(--tinta) !important; }

        /* Selector de vista */
        .vista-btn { padding: .45rem 1rem; border-radius: .6rem; font-weight: 600; color: #4B5A62; }
        .vista-btn[aria-pressed="true"] { background: #fff; color: var(--tinta); box-shadow: 0 1px 2px rgba(30,43,51,.15); }

        /* Calendario: encabezados sobrios en lugar de azul brillante */
        #cuadricula-calendario .bg-\[\#007bff\] { background: #fff !important; color: var(--tinta) !important; border-bottom: 1px solid var(--linea); }
        #cuadricula-calendario .bg-\[\#007bff\] .text-blue-100 { color: var(--suave) !important; }
        #cuadricula-calendario .bg-red-500 { background: #FBE9EC !important; color: #A3263B !important; }
        #cuadricula-calendario .bg-red-500 .text-blue-100 { color: #A3263B !important; }
        .es-hoy { background-color: #F1FAF7 !important; }
        .es-hoy-mes { box-shadow: inset 0 3px 0 var(--jade); }

        /* Ventanas emergentes más limpias */
        .fixed.inset-0.bg-black { background-color: rgba(30, 43, 51, .45) !important; backdrop-filter: blur(3px); }
        .fixed.inset-0.bg-black > div { border-radius: 1.25rem !important; }
        .bg-\[\#4361ee\] { background-color: var(--jade) !important; }
        .bg-\[\#1e3a8a\] { background-color: var(--tinta) !important; }
        .text-\[\#1e3a8a\] { color: var(--tinta) !important; }
        /* Formulario de cita y ventanas: mismo lenguaje visual */
        [id^="modal"] input:not([type=checkbox]):not([type=radio]), [id^="modal"] select, [id^="modal"] textarea { border-radius: .65rem !important; border-color: var(--linea) !important; }
        #tituloModal { color: var(--tinta) !important; font-weight: 800; }
        #botonesEdicion button { background: #fff !important; color: var(--tinta) !important; border: 1.5px solid var(--linea); border-radius: .7rem !important; padding-top: .6rem !important; padding-bottom: .6rem !important; }
        #botonesEdicion button:hover { border-color: #9DB3AB; }
        #botonesEdicion #btnEliminar { color: #A3263B !important; border-color: #F2C9D0; }
        #btnGuardar { background: var(--jade) !important; border-radius: .8rem !important; font-size: 1.05rem; }
        #btnGuardar:hover { background: #0A5E4D !important; }
        .rol-asistente .solo-admin { display: none !important; } /* asistente: sin corte ni administración */
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
        
        /* REGLAS DE IMPRESIÓN CORREGIDAS */
        @media print {
            body * { visibility: hidden; }
            
            /* Imprimir Remisiones si está abierto */
            #modalRemisiones:not(.hidden), #modalRemisiones:not(.hidden) * { visibility: visible; }
            #modalRemisiones:not(.hidden) { position: absolute; left: 0; top: 0; width: 100%; height: auto; background: white !important; overflow: visible !important; }
            
            /* Imprimir Historial de Cortes si está abierto */
            #modalHistorialCortes:not(.hidden), #modalHistorialCortes:not(.hidden) * { visibility: visible; }
            #modalHistorialCortes:not(.hidden) { position: absolute; left: 0; top: 0; width: 100%; height: auto; background: white !important; overflow: visible !important; }
            
            .no-print, .no-print * { display: none !important; }
        }
    </style>
</head>
<body class="bg-niebla min-h-screen rol-<?= $h($rol) ?>">
<?php $coloresSuc = ['Del Valle' => '#0E7C66', 'Tepeyac' => '#3B6FB6', 'Balbuena' => '#B4559B', 'Pachuca' => '#7A5AC8', 'Puebla' => '#D9822B']; ?>
<div class="md:flex min-h-screen">

    <!-- BARRA LATERAL -->
    <aside class="md:w-64 md:min-h-screen md:sticky md:top-0 md:h-screen md:overflow-y-auto bg-niebla md:border-r border-[#DDE5E2] flex flex-col p-4 gap-5 no-print">
        <div class="flex items-center gap-3 px-1">
            <div class="w-10 h-10 rounded-xl bg-jade text-white grid place-items-center font-extrabold text-lg">CR</div>
            <div><p class="font-extrabold leading-tight">Clínica de Rodillas</p><p class="text-xs text-[#6B7B83]">Hola, <?= $h(explode(' ', $usuario['nombre'])[0]) ?></p></div>
            <a href="logout_staff.php" class="md:hidden ml-auto text-sm font-semibold underline">Salir</a>
        </div>

        <button onclick="nuevaCitaRapida()" class="solo-operacion w-full bg-jade hover:bg-jade-oscuro text-white font-bold py-3 rounded-xl shadow-sm text-base">+ Nueva cita</button>
        <button onclick="abrirCitaExpress()" class="solo-operacion -mt-3 w-full flex items-center justify-center gap-2 border-2 border-ambar text-[#8A5A12] hover:bg-[#FDF3E3] font-bold py-2 rounded-xl text-sm"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg> Llegó sin cita</button>

        <nav aria-label="Sucursales">
            <p class="text-xs font-semibold text-[#6B7B83] px-2 mb-1">Sucursal</p>
            <ul class="flex md:flex-col gap-1 overflow-x-auto">
                <?php foreach ($listaSucursales as $i => $suc): ?><li id="menu-suc-<?= $i ?>" data-sucursal="<?= $h($suc) ?>" onclick="cargarCitas(<?= $h(json_encode($suc, JSON_UNESCAPED_UNICODE)) ?>, 'menu-suc-<?= $i ?>')" class="menu-item nav-item whitespace-nowrap"><span class="punto" style="background: <?= $h($coloresSuc[$suc] ?? '#6B7B83') ?>"></span><?= $h($suc) ?></li>
                <?php endforeach; ?>
                <?php if (!$sucUsuario): ?><li id="menu-todas" data-sucursal="" onclick="cargarCitas('', 'menu-todas')" class="menu-item nav-item whitespace-nowrap menu-activo"><span class="punto" style="background: conic-gradient(#0E7C66 0 25%, #3B6FB6 0 50%, #7A5AC8 0 75%, #D9822B 0)"></span>Todas</li><?php endif; ?>
            </ul>
        </nav>

        <nav aria-label="Herramientas" class="flex md:flex-col gap-1 overflow-x-auto">
            <p class="hidden md:block text-xs font-semibold text-[#6B7B83] px-2 mb-1">Herramientas</p>
            <button onclick="abrirRemisiones()" class="solo-operacion nav-item whitespace-nowrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg> Cobros del día</button>
            <button onclick="abrirNotas()" class="solo-operacion nav-item whitespace-nowrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg> Remisiones</button>
            <button onclick="abrirModalAsistencias()" class="solo-operacion nav-item whitespace-nowrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/></svg> Pasar asistencia</button>
            <button onclick="abrirDirectorio()" class="nav-item whitespace-nowrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg> Pacientes y citas</button>
            <button onclick="abrirHistorialCortes()" class="solo-admin nav-item whitespace-nowrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg> Cortes de caja</button>
            <button onclick="abrirCumples()" class="nav-item whitespace-nowrap"><span aria-hidden="true" style="width:20px;text-align:center">🎂</span> Cumpleaños <span id="badgeCumples" class="hidden ml-auto bg-rosa text-white text-xs font-bold rounded-full px-2 py-0.5"></span></button>
            <button onclick="abrirAusencias()" class="solo-operacion nav-item whitespace-nowrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 15l6 4M15 15l-6 4"/></svg> Ausencias</button>
            <?php if ($rol === 'admin'): ?><a href="admin.php" class="nav-item whitespace-nowrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Administración</a><?php endif; ?>
        </nav>

        <div class="mt-auto hidden md:block"><a href="logout_staff.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Cerrar sesión</a></div>
    </aside>

    <!-- ÁREA PRINCIPAL -->
    <main class="flex-1 min-w-0 p-4 md:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4 mb-5">
            <div>
                <p id="titulo-calendario" class="text-sm font-semibold text-jade">Agenda</p>
                <h1 id="texto-rango" class="text-3xl md:text-4xl font-extrabold tracking-tight tabular">Cargando…</h1>
                <p id="estadoVivo" class="text-xs text-[#6B7B83] mt-1" title="La agenda se actualiza sola cada 20 segundos">● Se actualiza sola</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center bg-white border border-[#DDE5E2] rounded-xl">
                    <button onclick="cambiarRango(-1)" aria-label="Anterior" class="px-3 py-2 hover:bg-niebla rounded-l-xl text-lg font-bold">‹</button>
                    <button onclick="irHoy()" class="px-4 py-2 font-semibold border-x border-[#DDE5E2] hover:bg-niebla">Hoy</button>
                    <button onclick="cambiarRango(1)" aria-label="Siguiente" class="px-3 py-2 hover:bg-niebla rounded-r-xl text-lg font-bold">›</button>
                </div>
                <div class="flex bg-[#E6ECEA] p-1 rounded-xl" role="group" aria-label="Vista">
                    <button class="vista-btn" data-vista="dia" onclick="setVista('dia')">Día</button>
                    <button class="vista-btn" data-vista="semana" onclick="setVista('semana')">Semana</button>
                    <button class="vista-btn" data-vista="mes" onclick="setVista('mes')">Mes</button>
                </div>
            </div>
        </header>

        <div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
            <button id="btn-filtro-todos" onclick="filtrarPorTipo('', 'btn-filtro-todos')" class="filtro-btn px-3 py-1.5 filtro-activo">Todos los tratamientos</button>
            <button id="btn-filtro-sports" onclick="filtrarPorTipo('Sports medicine', 'btn-filtro-sports')" class="filtro-btn px-3 py-1.5">Sports medicine</button>
            <button id="btn-filtro-clinica" onclick="filtrarPorTipo('Clínica de rodillas', 'btn-filtro-clinica')" class="filtro-btn px-3 py-1.5">Clínica de rodillas</button>
            <button id="btn-filtro-camara" onclick="filtrarPorTipo('Cámara Hiperbárica', 'btn-filtro-camara')" class="filtro-btn px-3 py-1.5">Cámara hiperbárica</button>
            <span class="w-px h-6 bg-[#DDE5E2] mx-1 hidden sm:block"></span>
            <button id="btn-pac-todos" onclick="filtrarPorPaciente('', 'btn-pac-todos')" class="filtro-pac-btn px-3 py-1.5 filtro-activo">Todos los pacientes</button>
            <button id="btn-pac-si" onclick="filtrarPorPaciente('Si', 'btn-pac-si')" class="filtro-pac-btn px-3 py-1.5">Ya son pacientes</button>
            <button id="btn-pac-no" onclick="filtrarPorPaciente('No', 'btn-pac-no')" class="filtro-pac-btn px-3 py-1.5">Primera vez</button>
        </div>

        <div id="cuadricula-calendario" class="bg-white border border-[#DDE5E2] rounded-2xl overflow-hidden"></div>
    </main>
</div>

    <!-- NUEVO MODAL: HISTORIAL DE CORTES -->
    <div id="modalHistorialCortes" class="fixed inset-0 bg-black bg-opacity-60 hidden flex justify-center items-center z-[70] p-4">
        <!-- El div container se ajusta en impresión para no estar recortado -->
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl max-h-[95vh] flex flex-col overflow-hidden print:max-h-none print:overflow-visible print:shadow-none print:rounded-none">
            
            <div class="flex justify-between items-center p-4 border-b bg-teal-50 no-print">
                <h2 class="text-xl font-black text-teal-800 flex items-center gap-2">📈 Historial Financiero de Cortes de Caja</h2>
                <button onclick="cerrarHistorialCortes()" class="text-gray-500 hover:text-red-500 text-3xl font-bold leading-none">&times;</button>
            </div>
            
            <div class="p-6 flex flex-col h-full bg-gray-50 overflow-hidden print:overflow-visible print:bg-white print:p-0">
                
                <!-- Encabezado exclusivo de impresión -->
                <div class="text-center mb-8 hidden print:block">
                    <h2 class="text-2xl font-bold text-teal-900 uppercase">REPORTE FINANCIERO DE CORTES DE CAJA</h2>
                    <h3 id="print-hc-rango" class="text-lg font-bold text-gray-700 uppercase mt-1"></h3>
                </div>

                <!-- Filtros del Historial (No se imprimen) -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6 bg-white p-4 rounded-lg border border-gray-200 shadow-sm items-end no-print">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Sucursal</label>
                        <select id="filtroHC_Sucursal" onchange="cargarHistorialCortes()" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-teal-500 outline-none font-medium"><?php if (!$sucUsuario): ?><option value="">Todas las Sucursales</option><?php endif; ?><?= $opcionesSucursal ?></select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Período Rápido</label>
                        <select id="filtroHC_Periodo" onchange="aplicarPeriodoRapido()" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-teal-500 outline-none font-medium bg-teal-50 text-teal-800">
                            <option value="semana">Esta Semana</option>
                            <option value="mes" selected>Este Mes</option>
                            <option value="trimestre">Último Trimestre</option>
                            <option value="todo">Todo el Historial</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Desde</label>
                        <input type="date" id="filtroHC_Inicio" onchange="cargarHistorialCortes()" class="w-full border border-gray-300 rounded p-2 text-sm outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Hasta</label>
                        <input type="date" id="filtroHC_Fin" onchange="cargarHistorialCortes()" class="w-full border border-gray-300 rounded p-2 text-sm outline-none">
                    </div>
                    <div>
                        <button onclick="window.print()" class="w-full bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded shadow transition-colors flex items-center justify-center gap-2">
                            🖨️ Imprimir Reporte
                        </button>
                    </div>
                </div>

                <!-- Tarjetas de Resumen -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6 text-center">
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">Total Pacientes</p>
                        <p id="hc-tot-pacientes" class="text-2xl font-black text-blue-600 mt-1">0</p>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-green-200">
                        <p class="text-xs text-green-600 font-bold uppercase tracking-wider">Efectivo Total</p>
                        <p id="hc-tot-efectivo" class="text-xl font-black text-green-700 mt-1">$0.00</p>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-indigo-200">
                        <p class="text-xs text-indigo-600 font-bold uppercase tracking-wider">Tarjetas Total</p>
                        <p id="hc-tot-tarjetas" class="text-xl font-black text-indigo-700 mt-1">$0.00</p>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-purple-200">
                        <p class="text-xs text-purple-600 font-bold uppercase tracking-wider">Transf. Total</p>
                        <p id="hc-tot-transferencia" class="text-xl font-black text-purple-700 mt-1">$0.00</p>
                    </div>
                    <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg shadow-sm border border-yellow-300 shadow-md">
                        <p class="text-xs text-yellow-700 font-bold uppercase tracking-wider">Ingreso Global</p>
                        <p id="hc-tot-general" class="text-2xl font-black text-yellow-800 mt-1">$0.00</p>
                    </div>
                </div>

                <p class="text-xs text-gray-500 mb-2 no-print">👆 Haz clic en un corte para ver cada cobro y su recibo. Ya no hace falta imprimir ni mandar el corte: quien revisa puede consultarlo aquí.</p>
                <!-- Tabla de Resultados de Cortes -->
                <div class="flex-1 overflow-auto border border-gray-200 rounded-lg shadow-sm bg-white print:overflow-visible print:border-none print:shadow-none">
                    <table class="w-full text-sm text-left text-gray-700">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-100 border-b border-gray-200 sticky top-0 shadow-sm print:bg-gray-200 print:text-black">
                            <tr>
                                <th class="px-4 py-3">ID Corte</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Sucursal</th>
                                <th class="px-4 py-3 text-center">Pacientes</th>
                                <th class="px-4 py-3 text-right">Efectivo</th>
                                <th class="px-4 py-3 text-right">Tarjetas</th>
                                <th class="px-4 py-3 text-right">Transf.</th>
                                <th class="px-4 py-3 text-right font-bold text-blue-800 print:text-black">Total Día</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-historial-cortes" class="divide-y divide-gray-100">
                            <!-- Inyectado por JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DIRECTORIO Y REPORTES -->
    <div id="modalDirectorio" class="fixed inset-0 bg-black bg-opacity-60 hidden flex justify-center items-center z-[70] p-4 no-print">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b bg-blue-50">
                <h2 class="text-xl font-black text-blue-800 flex items-center gap-2">📋 Directorio y Reporte de Citas</h2>
                <button onclick="cerrarDirectorio()" class="text-gray-500 hover:text-red-500 text-3xl font-bold leading-none">&times;</button>
            </div>
            <div class="p-6 flex flex-col h-full bg-gray-50 overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Tratamiento</label>
                        <select id="filtroDirTratamiento" onchange="renderizarTablaDirectorio()" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-blue-500 outline-none font-medium">
                            <option value="">Todos los Tratamientos</option><option value="Sports medicine">Sports medicine</option><option value="Clínica de rodillas">Clínica de rodillas</option><option value="Cámara Hiperbárica">Cámara Hiperbárica</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Asistencia</label>
                        <select id="filtroDirAsistencia" onchange="renderizarTablaDirectorio()" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-blue-500 outline-none font-medium">
                            <option value="">Cualquier Estatus</option><option value="Asistió">✅ Asistió</option><option value="No Asistió">❌ No Asistió / Faltó</option><option value="Pendiente">⏳ Pendiente</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Estado de la Cita</label>
                        <select id="filtroDirEstado" onchange="renderizarTablaDirectorio()" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-blue-500 outline-none font-medium">
                            <option value="">Todos los Estados</option><option value="Agendado">📅 Agendado</option><option value="Confirmada">👍 Confirmada</option><option value="Express">⚡ Cita en el Momento (Express)</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="renderizarTablaDirectorio(true)" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded shadow transition-colors flex items-center justify-center gap-2">🔄 Limpiar Filtros</button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto border border-gray-200 rounded-lg shadow-sm bg-white">
                    <table class="w-full text-sm text-left text-gray-700">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-100 border-b border-gray-200 sticky top-0 shadow-sm">
                            <tr><th class="px-4 py-3">Fecha y Hora</th><th class="px-4 py-3">Paciente</th><th class="px-4 py-3">Teléfono</th><th class="px-4 py-3">Sucursal</th><th class="px-4 py-3">Tratamiento</th><th class="px-4 py-3 text-center">Estado</th><th class="px-4 py-3 text-center">Asistencia</th><th class="px-4 py-3 text-center">Acción</th></tr>
                        </thead>
                        <tbody id="tabla-directorio-body" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE REMISIONES / CONTROL DIARIO -->
    <div id="modalNotas" class="fixed inset-0 bg-black/50 hidden z-50 p-3 md:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="tituloNotas">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl mx-auto">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b">
                <div><h2 id="tituloNotas" class="text-xl font-extrabold">Remisiones</h2><p id="subNotas" class="text-sm text-[#6B7B83]"></p></div>
                <div class="flex items-center gap-2">
                    <div class="inline-flex rounded-lg bg-[#EEF3F1] p-1 text-sm font-semibold" role="tablist">
                        <button id="tabNotaNueva" onclick="tabNotas('nueva')" class="px-3 py-1.5 rounded-md bg-white shadow-sm">Nueva</button>
                        <button id="tabNotaHist" onclick="tabNotas('hist')" class="px-3 py-1.5 rounded-md text-[#4B5A62]">Historial</button>
                    </div>
                    <button onclick="cerrarNotas()" class="text-[#6B7B83] hover:text-red-600 text-3xl leading-none px-2" aria-label="Cerrar">&times;</button>
                </div>
            </div>
            <div id="panelNotaNueva" class="grid lg:grid-cols-[1fr_auto] gap-6 p-5">
                <form id="formNota" onsubmit="guardarNota(event)" oninput="previaNota()" class="space-y-4 min-w-0">
                    <div class="grid sm:grid-cols-[1fr_150px] gap-3">
                        <div><label class="block text-sm font-semibold mb-1" for="notaCitaSel">Paciente con cita</label><select id="notaCitaSel" onchange="elegirCitaNota()" class="w-full border border-gray-300 rounded-lg p-2 bg-white"><option value="">Cargando citas…</option></select></div>
                        <div><label class="block text-sm font-semibold mb-1" for="notaFecha">Fecha</label><input id="notaFecha" type="date" required onchange="cargarCitasNota()" class="w-full border border-gray-300 rounded-lg p-2"></div>
                    </div>
                    <div><label class="block text-sm font-semibold mb-1" for="notaNombre">Nombre del cliente</label><input id="notaNombre" required maxlength="150" autocomplete="off" oninput="ligarCitaNota()" class="w-full border border-gray-300 rounded-lg p-2" placeholder="Se llena al elegir la cita, o escríbelo si no tiene cita"><p id="notaLigada" class="text-xs text-[#0A5E4D] mt-1 hidden"></p></div>
                    <div><label class="block text-sm font-semibold mb-1" for="notaTel">Teléfono <span class="font-normal text-[#6B7B83]">(opcional, para mandarla por WhatsApp)</span></label><input id="notaTel" inputmode="numeric" maxlength="10" class="w-full sm:w-60 border border-gray-300 rounded-lg p-2" placeholder="10 dígitos"></div>
                    <div>
                        <p class="text-sm font-semibold mb-1">Conceptos</p>
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="hidden sm:grid grid-cols-[64px_1fr_110px_100px_32px] gap-2 bg-[#F3F6F5] px-3 py-2 text-xs font-semibold text-[#4B5A62]"><span>Cant.</span><span>Descripción</span><span>Precio unit.</span><span class="text-right">Importe</span><span></span></div>
                            <div id="notaConceptos" class="divide-y"></div>
                        </div>
                        <datalist id="notaServicios"><option>Consulta de valoración</option><option>Sesión de terapia de rodilla</option><option>Sesión de Cámara Hiperbárica</option><option>Paquete de sesiones</option><option>Infiltración</option><option>Rodillera</option></datalist>
                        <div class="flex items-center justify-between mt-2"><button type="button" onclick="agregarConceptoNota()" class="text-sm font-semibold text-[#0E7C66] hover:underline">+ Agregar concepto</button><p class="text-lg font-extrabold">Total: <span id="notaTotal">$0.00</span></p></div>
                    </div>
                    <div><label class="block text-sm font-semibold mb-1" for="notaObs">Observaciones</label><textarea id="notaObs" rows="2" maxlength="250" class="w-full border border-gray-300 rounded-lg p-2"></textarea></div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div><label class="block text-sm font-semibold mb-1" for="notaMetodo">Método de pago</label><select id="notaMetodo" required class="w-full border border-gray-300 rounded-lg p-2 bg-white"><option value="">Elige…</option><option>Efectivo</option><option>Tarjeta de crédito</option><option>Tarjeta de débito</option><option>Transferencia</option></select></div>
                        <div><label class="block text-sm font-semibold mb-1" for="notaAtendio">Nombre y firma (quién atiende)</label><input id="notaAtendio" maxlength="100" class="w-full border border-gray-300 rounded-lg p-2"></div>
                    </div>
                    <p class="text-xs text-[#6B7B83]">Al guardar se asigna el folio de la sucursal, el cobro entra al corte del día y el PDF queda guardado como respaldo.</p>
                    <div id="notaResultado" class="hidden rounded-xl border border-[#BFE3CD] bg-[#F1FAF7] p-3"></div>
                    <button id="btnGuardarNota" type="submit" class="w-full bg-[#0E7C66] hover:bg-[#0A5E4D] text-white font-bold rounded-xl py-3">Guardar y generar PDF</button>
                </form>
                <div class="hidden lg:block">
                    <p class="text-xs font-semibold text-[#6B7B83] mb-2">Vista previa</p>
                    <div style="width:396px;height:612px;overflow:hidden;border-radius:10px;box-shadow:0 10px 30px -12px rgba(30,43,51,.35);border:1px solid #E2E8E6"><div id="notaPrevia" style="transform:scale(.75);transform-origin:0 0;width:528px;height:816px"></div></div>
                </div>
            </div>
            <div id="panelNotaHist" class="hidden p-5">
                <div class="flex flex-wrap items-end gap-3 mb-4">
                    <div><label class="block text-sm font-semibold mb-1" for="histNotaFecha">Fecha</label><input id="histNotaFecha" type="date" onchange="cargarHistNotas()" class="border border-gray-300 rounded-lg p-2"></div>
                    <div class="flex-1 min-w-[200px]"><label class="block text-sm font-semibold mb-1" for="histNotaBuscar">Buscar por nombre o folio</label><input id="histNotaBuscar" type="search" oninput="clearTimeout(window._tBuscaNota); window._tBuscaNota = setTimeout(cargarHistNotas, 350)" class="w-full border border-gray-300 rounded-lg p-2" placeholder="Ej. Rodríguez o DV-000043"></div>
                </div>
                <div id="pendHistNotas" class="mb-4"></div>
                <div id="listaHistNotas" class="space-y-2"></div>
            </div>
        </div>
    </div>

    <div id="modalRemisiones" class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-6xl max-h-[95vh] flex flex-col">
            <div class="flex justify-between items-center p-4 border-b bg-emerald-50 rounded-t-lg no-print">
                <h2 id="tituloModalRemisiones" class="text-xl font-bold text-emerald-800">🧾 Control de Pacientes - Remisiones</h2>
                <button onclick="cerrarModalRemisiones()" class="text-gray-500 hover:text-red-500 text-3xl font-bold leading-none">&times;</button>
            </div>
            
            <div class="p-6 flex-1 overflow-y-auto print:overflow-visible">
                <div class="text-center mb-6 hidden print:block">
                    <h2 class="text-2xl font-bold text-blue-900 uppercase">CONTROL DE PACIENTES REHABILITACION</h2>
                    <h3 id="print-sucursal-titulo" class="text-xl font-bold text-gray-700 uppercase"></h3>
                </div>

                <div class="flex justify-between items-end mb-6 no-print">
                    <div>
                        <label class="block font-bold text-gray-700 text-sm mb-1 uppercase">Fecha:</label>
                        <input type="date" id="inputFechaRemision" onchange="cargarDatosRemision()" class="border border-gray-400 rounded-md p-2 shadow-sm font-bold text-gray-700 focus:ring-emerald-500">
                    </div>
                    <div class="solo-admin flex gap-2">
                        <button id="btn-guardar-corte" onclick="guardarCorteDiario()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow flex items-center gap-2 transition-colors">💾 Guardar Corte</button>
                        <button onclick="enviarRemisionWhatsApp()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded shadow flex items-center gap-2 transition-colors">📲 WhatsApp</button>
                        <button onclick="enviarRemisionCorreo()" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded shadow flex items-center gap-2 transition-colors">✉️ Correo</button>
                        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow flex items-center gap-2 transition-colors">🖨️ Imprimir Resumen</button>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-400 rounded-md mb-6 print:overflow-visible print:border-none">
                    <table class="w-full text-sm text-left text-gray-700">
                        <thead class="text-xs text-white uppercase bg-[#4361ee] print:text-black print:bg-gray-200 border-b border-gray-400">
                            <tr><th class="px-2 py-3 border-r border-gray-300 text-center w-10">#</th><th class="px-2 py-3 border-r border-gray-300 w-24">Folio</th><th class="px-3 py-3 border-r border-gray-300">Paciente</th><th class="px-2 py-3 border-r border-gray-300 w-48">Servicio</th><th class="px-2 py-3 border-r border-gray-300 text-center w-20 text-yellow-300 print:text-black">Efectivo</th><th class="px-2 py-3 border-r border-gray-300 text-center w-20 text-yellow-300 print:text-black">Crédito</th><th class="px-2 py-3 border-r border-gray-300 text-center w-20 text-yellow-300 print:text-black">Débito</th><th class="px-2 py-3 border-r border-gray-300 text-center w-20 text-yellow-300 print:text-black">Transf.</th><th class="px-2 py-3 text-center w-24 no-print">Acción</th></tr>
                        </thead>
                        <tbody id="tabla-remisiones-body" class="divide-y divide-gray-300"></tbody>
                    </table>
                </div>

                <div class="solo-admin flex justify-between items-end gap-6 mt-4">
                    <div class="w-1/2">
                        <div class="flex gap-4 items-center mb-4"><label class="font-bold text-gray-700">Folios del:</label><input type="text" id="folio-inicio" readonly class="border-b border-gray-400 w-24 px-1 focus:outline-none bg-transparent font-mono text-gray-600 cursor-not-allowed"><label class="font-bold text-gray-700">al:</label><input type="text" id="folio-fin" readonly class="border-b border-gray-400 w-24 px-1 focus:outline-none bg-transparent font-mono text-gray-600 cursor-not-allowed"></div>
                        <div class="mt-8 pt-4 w-64 text-center border-t border-black"><span class="text-sm font-bold text-gray-700">Firma del Médico / Recepción</span></div>
                    </div>
                    <div class="w-1/2 bg-gray-50 p-4 rounded border border-gray-300">
                        <h4 class="font-bold border-b border-gray-300 pb-2 mb-3 text-center text-gray-600">RESUMEN DE VENTAS</h4>
                        <div class="flex justify-between items-center mb-1.5"><span class="font-bold text-sm uppercase">Efectivo:</span><span id="tot-efectivo" class="font-black text-lg text-gray-800 bg-yellow-100 px-3 py-1 rounded">$0.00</span></div>
                        <div class="flex justify-between items-center mb-1.5"><span class="font-bold text-sm uppercase">T. Crédito:</span><span id="tot-credito" class="font-black text-lg text-gray-800 bg-yellow-100 px-3 py-1 rounded">$0.00</span></div>
                        <div class="flex justify-between items-center mb-1.5"><span class="font-bold text-sm uppercase">T. Débito:</span><span id="tot-debito" class="font-black text-lg text-gray-800 bg-yellow-100 px-3 py-1 rounded">$0.00</span></div>
                        <div class="flex justify-between items-center mb-1.5"><span class="font-bold text-sm uppercase">Transferencia:</span><span id="tot-transferencia" class="font-black text-lg text-gray-800 bg-yellow-100 px-3 py-1 rounded">$0.00</span></div>
                        <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-300"><span class="font-black text-lg uppercase text-blue-900">Total General:</span><span id="tot-general" class="font-black text-2xl text-blue-800">$0.00</span></div>
                        <div class="flex justify-between items-center mt-2 pt-2 border-t border-gray-200"><span class="font-bold text-sm text-gray-600 uppercase">Total Pacientes:</span><span id="tot-pacientes" class="font-bold text-gray-800">0</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALES VARIOS OMITIDOS VISUALMENTE AQUÍ (Asistencias, Agenda Día, Perfil, Carnet, Cita) -->
    <!-- (El JavaScript se encarga de inyectar y manejar la funcionalidad completa, el código se mantiene debajo intacto) -->

    <!-- MODAL DE ASISTENCIAS MENSUALES LOTE -->
    <div id="modalAsistencias" class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-center z-50 p-4 no-print">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-4 border-b bg-indigo-50 rounded-t-lg"><h2 class="text-xl font-bold text-indigo-800">📊 Control de Asistencias (Mensual)</h2><button onclick="cerrarModalAsistencias()" class="text-gray-500 hover:text-red-500 text-3xl font-bold leading-none">&times;</button></div>
            <div class="p-6 flex-1 overflow-y-auto">
                <div class="grid grid-cols-4 gap-4 mb-6 text-center">
                    <div class="bg-blue-50 p-3 rounded-lg border border-blue-200"><div class="text-xs text-blue-600 font-bold uppercase tracking-wider">Total Citas</div><div id="res-total" class="text-3xl font-black text-blue-800">0</div></div>
                    <div class="bg-green-50 p-3 rounded-lg border border-green-200"><div class="text-xs text-green-600 font-bold uppercase tracking-wider">✅ Asistieron</div><div id="res-asistio" class="text-3xl font-black text-green-800">0</div></div>
                    <div class="bg-red-50 p-3 rounded-lg border border-red-200"><div class="text-xs text-red-600 font-bold uppercase tracking-wider">❌ Faltaron</div><div id="res-falto" class="text-3xl font-black text-red-800">0</div></div>
                    <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200"><div class="text-xs text-yellow-600 font-bold uppercase tracking-wider">⏳ Pendientes</div><div id="res-pendientes" class="text-3xl font-black text-yellow-800">0</div></div>
                </div>
                <h3 class="font-bold text-gray-700 mb-3 border-b pb-1">Desglose del Mes Visible</h3><div id="lista-asistencias" class="space-y-3 mb-6"></div>
                <div class="flex justify-end pt-4 border-t border-gray-200"><button id="btn-guardar-lote" onclick="guardarAsistenciasLote()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-md shadow-md transition-colors flex items-center gap-2">💾 Guardar Cambios</button></div>
            </div>
        </div>
    </div>

    <!-- MODAL AGENDA DÍA -->
    <div id="modalAgendaDia" class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-center z-50 p-4 no-print">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-4 border-b bg-blue-50 rounded-t-lg"><h2 id="tituloModalDia" class="text-xl font-bold text-blue-800">📅 Agenda del Día</h2><button onclick="cerrarModalAgendaDia()" class="text-gray-500 hover:text-red-500 text-3xl font-bold leading-none">&times;</button></div>
            <div class="p-6 flex-1 overflow-y-auto"><p class="text-sm text-gray-500 mb-4">Haz clic en cualquier cita para editarla.</p><div id="lista-agenda-dia" class="space-y-3"></div></div>
        </div>
    </div>

    <!-- MODAL PERFIL DEL PACIENTE -->
    <div id="modalPerfil" class="fixed inset-0 bg-black bg-opacity-60 hidden flex justify-center items-center z-[70] p-4 no-print">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl flex flex-col overflow-hidden max-h-[90vh]">
            <div class="flex justify-between items-center p-4 border-b bg-[#1e3a8a]"><h2 class="text-xl font-black text-white flex items-center gap-2">🗂️ Expediente Clínico</h2><button onclick="cerrarPerfil()" class="text-gray-300 hover:text-red-400 text-3xl font-bold leading-none">&times;</button></div>
            <div class="p-6 overflow-y-auto bg-gray-50">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-6 flex justify-between items-center">
                    <div><p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">Paciente</p><h3 id="perfil-nombre" class="text-2xl font-black text-gray-800">Cargando...</h3><p id="perfil-telefono" class="text-gray-600 font-medium mt-1">📞 ---</p></div>
                    <div class="text-right"><p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">Folio / Expediente</p><div id="perfil-folio" class="bg-blue-100 text-blue-800 font-mono font-bold px-4 py-2 rounded-lg text-lg border border-blue-200">CR-XXXX</div></div>
                </div>
                <div class="grid grid-cols-4 gap-4 mb-6 text-center">
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200"><p class="text-xs text-gray-500 font-bold uppercase tracking-wider">Total Citas</p><p id="perfil-total-citas" class="text-3xl font-black text-blue-600 mt-1">0</p></div>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-green-200"><p class="text-xs text-green-600 font-bold uppercase tracking-wider">Asistencias</p><p id="perfil-asistencias" class="text-3xl font-black text-green-600 mt-1">0</p></div>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-red-200"><p class="text-xs text-red-600 font-bold uppercase tracking-wider">Faltas</p><p id="perfil-faltas" class="text-3xl font-black text-red-600 mt-1">0</p></div>
                    <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg shadow-sm border border-yellow-300"><p class="text-xs text-yellow-700 font-bold uppercase tracking-wider">Inversión Total</p><p id="perfil-inversion" class="text-2xl font-black text-yellow-800 mt-1">$0.00</p></div>
                </div>
                <h3 class="font-bold text-gray-700 border-b pb-2 mb-4 text-sm uppercase tracking-wider">Historial de Tratamiento</h3>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm"><table class="w-full text-sm text-left text-gray-700"><thead class="text-xs text-gray-500 uppercase bg-gray-100 border-b border-gray-200"><tr><th class="px-4 py-3">Fecha y Hora</th><th class="px-4 py-3">Tratamiento</th><th class="px-4 py-3">Sucursal</th><th class="px-4 py-3 text-center">Asistencia</th></tr></thead><tbody id="perfil-historial-body" class="divide-y divide-gray-100"></tbody></table></div>
                <?php if ($esClinico): ?>
                <h3 class="font-bold text-gray-700 border-b pb-2 mb-4 mt-6 text-sm uppercase tracking-wider">🩺 Notas del médico <span class="normal-case font-normal text-gray-400">(admin y asistentes)</span></h3>
                <form id="formNota" onsubmit="guardarNota(event)" class="bg-white border border-gray-200 rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                    <select name="rodilla" class="border rounded p-2 text-sm"><option value="">Rodilla…</option><option>Derecha</option><option>Izquierda</option><option>Ambas</option></select>
                    <input name="diagnostico" maxlength="255" placeholder="Diagnóstico" class="md:col-span-2 border rounded p-2 text-sm">
                    <input name="dolor_eva" type="number" min="0" max="10" placeholder="Dolor 0-10" class="border rounded p-2 text-sm">
                    <textarea name="notas" rows="2" maxlength="5000" placeholder="Evolución, indicaciones, plan de tratamiento…" class="md:col-span-4 border rounded p-2 text-sm"></textarea>
                    <button class="md:col-span-4 bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 rounded text-sm">Guardar nota</button>
                </form>
                <div id="lista-notas" class="space-y-2"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="toast" role="status" aria-live="polite" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 bg-[#1E2B33] text-white text-sm font-semibold px-4 py-3 rounded-xl shadow-lg z-[100] no-print"></div>

    <!-- MODAL CUMPLEAÑOS -->
    <div id="modalCumples" class="fixed inset-0 bg-black bg-opacity-60 hidden flex justify-center items-center z-[70] p-4 no-print">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b"><h2 class="text-xl font-black">🎂 Cumpleaños</h2><button onclick="document.getElementById('modalCumples').classList.add('hidden')" class="text-gray-500 hover:text-red-500 text-3xl font-bold leading-none">&times;</button></div>
            <div class="p-6 overflow-y-auto bg-gray-50">
                <p class="text-sm text-gray-600 mb-4">El aviso aparece un día antes para que la felicitación salga a tiempo. Al tocar “Felicitar por WhatsApp” se abre el mensaje listo y el paciente queda marcado como felicitado este año.</p>
                <h3 class="font-bold mb-2">Mañana</h3><div id="lista-cumples-manana" class="space-y-2 mb-6"></div>
                <h3 class="font-bold mb-2">Hoy</h3><div id="lista-cumples-hoy" class="space-y-2"></div>
            </div>
        </div>
    </div>

    <!-- MODAL AUSENCIAS / BLOQUEOS DE AGENDA -->
    <div id="modalAusencias" class="fixed inset-0 bg-black bg-opacity-60 hidden flex justify-center items-center z-[70] p-4 no-print">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b bg-red-50"><h2 class="text-xl font-black text-red-800">🚫 Ausencias y bloqueos de agenda</h2><button onclick="document.getElementById('modalAusencias').classList.add('hidden')" class="text-gray-500 hover:text-red-500 text-3xl font-bold leading-none">&times;</button></div>
            <div class="p-6 overflow-y-auto bg-gray-50">
                <p class="text-sm text-gray-600 mb-4">Registra con anticipación cuando un médico no estará (vacaciones, permiso, congreso) o saldrá temprano. Esos días/horas se ven en rojo en la agenda y el sistema avisa antes de agendar ahí. Deja las horas vacías para bloquear el día completo.</p>
                <form id="formAusencia" onsubmit="guardarAusencia(event)" class="bg-white border border-gray-200 rounded-lg p-4 grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
                    <label class="col-span-2 text-xs font-bold text-gray-500 uppercase">Sucursal<select name="sucursal" required class="w-full border rounded p-2 text-sm font-normal normal-case"><?= $opcionesSucursal ?></select></label>
                    <label class="col-span-2 text-xs font-bold text-gray-500 uppercase">Médico (opcional)<input name="medico" maxlength="120" placeholder="Ej. Dr. Palma" class="w-full border rounded p-2 text-sm font-normal normal-case"></label>
                    <label class="col-span-2 text-xs font-bold text-gray-500 uppercase">Motivo<input name="motivo" list="motivosAusencia" required maxlength="150" class="w-full border rounded p-2 text-sm font-normal normal-case"></label>
                    <datalist id="motivosAusencia"><option>Vacaciones</option><option>Permiso</option><option>Emergencia</option><option>Sale temprano</option><option>Llega tarde</option><option>Congreso / curso</option><option>Día festivo</option></datalist>
                    <label class="text-xs font-bold text-gray-500 uppercase">Desde<input name="fecha_inicio" type="date" required class="w-full border rounded p-2 text-sm"></label>
                    <label class="text-xs font-bold text-gray-500 uppercase">Hasta<input name="fecha_fin" type="date" class="w-full border rounded p-2 text-sm"></label>
                    <label class="text-xs font-bold text-gray-500 uppercase">Hora inicio<input name="hora_inicio" type="time" class="w-full border rounded p-2 text-sm"></label>
                    <label class="text-xs font-bold text-gray-500 uppercase">Hora fin<input name="hora_fin" type="time" class="w-full border rounded p-2 text-sm"></label>
                    <div class="col-span-2 flex items-end"><button class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded text-sm">Registrar ausencia</button></div>
                </form>
                <h3 class="font-bold text-gray-700 mb-2 text-sm uppercase">Próximas ausencias</h3>
                <div id="lista-ausencias" class="space-y-2"></div>
            </div>
        </div>
    </div>

    <!-- MODAL DETALLE DE CORTE -->
    <div id="modalDetalleCorte" class="fixed inset-0 bg-black bg-opacity-60 hidden flex justify-center items-center z-[80] p-4 no-print">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b bg-teal-50"><h2 id="tituloDetalleCorte" class="text-xl font-black text-teal-800">Detalle del corte</h2><button onclick="document.getElementById('modalDetalleCorte').classList.add('hidden')" class="text-gray-500 hover:text-red-500 text-3xl font-bold leading-none">&times;</button></div>
            <div class="p-6 overflow-y-auto">
                <div id="detalle-corte-info" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-sm"></div>
                <table class="w-full text-sm text-left"><thead class="text-xs uppercase text-gray-500 bg-gray-100"><tr><th class="px-3 py-2">Folio</th><th class="px-3 py-2">Paciente</th><th class="px-3 py-2">Servicio</th><th class="px-3 py-2 text-right">Efectivo</th><th class="px-3 py-2 text-right">Tarjeta</th><th class="px-3 py-2 text-right">Transf.</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2">Cobró</th><th class="px-3 py-2">Recibo</th></tr></thead><tbody id="detalle-corte-body" class="divide-y"></tbody></table>
            </div>
        </div>
    </div>

    <!-- MODAL CARNET VIRTUAL -->
    <div id="modalCarnet" class="fixed inset-0 bg-black bg-opacity-60 hidden flex justify-center items-center z-[60] p-4 no-print">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl flex flex-col overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b bg-purple-50"><h2 class="text-xl font-black text-purple-800 flex items-center gap-2">🪪 Perfil Clínico y Carnet</h2><button onclick="cerrarCarnet()" class="text-gray-500 hover:text-red-500 text-3xl font-bold leading-none">&times;</button></div>
            <div class="p-6 flex flex-col md:flex-row gap-6 bg-gray-50 max-h-[80vh] overflow-y-auto">
                <div class="w-full md:w-1/3 bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-700 border-b pb-2 mb-4 text-sm uppercase tracking-wider">Datos del Paciente</h3>
                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Núm. Expediente</label><input type="text" id="inputExpedienteCarnet" placeholder="Ej. CR-2026-012" onkeyup="dibujarCarnetVisual()" class="w-full border border-gray-300 rounded-md p-2 mb-3 text-sm focus:ring-purple-500 outline-none uppercase font-mono">
                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Diagnóstico Clínico</label><textarea id="inputDiagCarnet" rows="2" placeholder="Ej. Osteoartritis Rodilla Derecha" onkeyup="dibujarCarnetVisual()" class="w-full border border-gray-300 rounded-md p-2 mb-3 text-sm focus:ring-purple-500 outline-none"></textarea>
                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Médico Tratante</label><input type="text" id="inputMedicoCarnet" placeholder="Ej. Dr. Roberto Sánchez" onkeyup="dibujarCarnetVisual()" class="w-full border border-gray-300 rounded-md p-2 mb-4 text-sm focus:ring-purple-500 outline-none">
                    <h3 class="font-bold text-gray-700 border-b pb-2 mb-4 text-sm uppercase tracking-wider">Progreso</h3>
                    <div class="flex gap-4 mb-6"><div class="w-1/2"><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Total</label><input type="number" id="inputTotalesCarnet" value="10" min="1" onchange="dibujarCarnetVisual()" class="w-full border border-gray-300 rounded-md p-2 focus:ring-purple-500 outline-none text-center font-bold"></div><div class="w-1/2"><label class="block text-xs font-bold text-purple-700 mb-1 uppercase">Tomadas</label><input type="number" id="inputTomadasCarnet" value="0" min="0" onchange="dibujarCarnetVisual()" class="w-full border border-purple-300 rounded-md p-2 focus:ring-purple-500 outline-none bg-purple-50 text-center font-bold text-purple-700"></div></div>
                    <button id="btnGenerarCarnet" onclick="generarEnviarCarnet()" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg shadow-md transition-colors flex items-center justify-center gap-2">📲 Generar y Enviar</button>
                    <button type="button" onclick="imprimirCarnet()" class="w-full mt-2 bg-gray-700 hover:bg-gray-800 text-white font-bold py-3 px-4 rounded-lg shadow-md transition-colors flex items-center justify-center gap-2">🖨️ Imprimir carnet</button>
                </div>
                <div class="w-full md:w-2/3 flex justify-center items-start">
                    <div id="carnet-render" style="width: 100%; background: white; border: 1px solid #ccc; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        <div style="background: linear-gradient(90deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center;"><div><h2 style="margin: 0; font-size: 20px; font-weight: 900; letter-spacing: 1px;">CLÍNICA DE RODILLAS</h2><p style="margin: 2px 0 0 0; font-size: 11px; opacity: 0.9; letter-spacing: 1px;">CARNET DE REHABILITACIÓN FÍSICA</p></div><div style="text-align: right;"><p style="margin: 0; font-size: 10px; opacity: 0.8; text-transform: uppercase;">Expediente</p><p style="margin: 0; font-size: 16px; font-weight: bold; font-family: monospace;" id="txt-carnet-exp">CR-XXXX</p></div></div>
                        <div style="padding: 20px; background-color: #f8fafc; border-bottom: 2px dashed #cbd5e1;"><table style="width: 100%; border-collapse: collapse; font-size: 13px;"><tr><td style="padding: 4px 0; color: #64748b; width: 100px; font-weight: bold;">Paciente:</td><td style="padding: 4px 0; font-weight: bold; color: #0f172a; font-size: 15px;" id="txt-carnet-nombre"></td></tr><tr><td style="padding: 4px 0; color: #64748b; font-weight: bold;">Tratamiento:</td><td style="padding: 4px 0; color: #334155;" id="txt-carnet-tratamiento"></td></tr><tr><td style="padding: 4px 0; color: #64748b; font-weight: bold;">Diagnóstico:</td><td style="padding: 4px 0; color: #334155; font-style: italic;" id="txt-carnet-diag">No especificado</td></tr><tr><td style="padding: 4px 0; color: #64748b; font-weight: bold;">Médico:</td><td style="padding: 4px 0; color: #334155;" id="txt-carnet-medico">No especificado</td></tr></table></div>
                        <div style="padding: 25px 20px;"><div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 15px;"><h3 style="margin: 0; color: #1e3a8a; font-size: 14px; font-weight: bold; text-transform: uppercase;">Control de Sesiones</h3><p style="margin: 0; font-size: 12px; font-weight: bold; color: #64748b; background: #e2e8f0; padding: 3px 8px; border-radius: 10px;">Avance: <span id="txt-carnet-avance" style="color: #1e3a8a;"></span></p></div><div id="carnet-grid" style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: flex-start;"></div></div>
                        <div style="background-color: #1e293b; color: #94a3b8; padding: 10px 20px; font-size: 10px; text-align: center;">Elaborado por Clínica de Rodillas | Presente este carnet en cada sesión.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL FORMULARIO CITA INDIVIDUAL -->
    <div id="modalCita" class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-center z-40 p-4 no-print">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-4 border-b bg-gray-50 rounded-t-lg"><h2 id="tituloModal" class="text-xl font-bold text-blue-700">Agendar Nueva Cita</h2><button onclick="cerrarModal()" class="text-gray-500 hover:text-red-500 text-3xl font-bold leading-none">&times;</button></div>
            <form id="formCita" class="p-4 space-y-4">
                <input type="hidden" name="id_cita" id="inputIdCita" value="">
                <div id="mensaje" class="text-sm font-medium hidden rounded p-2 text-center"></div>
                <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-medium text-gray-700">Fecha</label><input type="date" name="fecha" id="inputFecha" required class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div><div><label class="block text-sm font-medium text-gray-700">Hora</label><input type="time" name="hora" id="inputHora" step="1800" required class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div></div>
                <div class="bg-gray-50 border border-gray-200 p-3 rounded-md"><label class="block text-sm font-bold text-gray-700 mb-2">Control de Asistencia</label><div class="flex items-center gap-6"><label class="inline-flex items-center cursor-pointer hover:bg-green-50 p-1 rounded transition"><input type="checkbox" id="checkSiAsistio" class="w-6 h-6 text-green-600 border-gray-400 rounded focus:ring-green-500 cursor-pointer shadow-sm"><span class="ml-2 text-sm font-bold text-green-700">✅ Sí Asistió</span></label><label class="inline-flex items-center cursor-pointer hover:bg-red-50 p-1 rounded transition"><input type="checkbox" id="checkNoAsistio" class="w-6 h-6 text-red-600 border-gray-400 rounded focus:ring-red-500 cursor-pointer shadow-sm"><span class="ml-2 text-sm font-bold text-red-700">❌ No Asistió</span></label></div></div>
                <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-medium text-gray-700">Estado Base</label><select name="estado" id="inputEstado" class="mt-1 block w-full border border-gray-300 rounded-md p-2 bg-white"><option value="Agendado">Agendado</option><option value="Confirmada">Confirmada</option><option value="Express">Cita en el Momento ⚡</option></select></div><div><label class="block text-sm font-medium text-gray-700">Edad</label><input type="number" name="edad" id="inputEdad" class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div></div>
                <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-medium text-gray-700" for="inputFechaNac">Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" id="inputFechaNac" max="<?= date('Y-m-d') ?>" oninput="calcularEdad()" class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div><div><label class="block text-sm font-medium text-gray-700" for="inputProcedencia">Lugar de procedencia</label><input type="text" name="procedencia" id="inputProcedencia" list="listaProcedencias" maxlength="120" placeholder="Ej. CDMX, Hidalgo…" class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div></div>
                <datalist id="listaProcedencias"><option>Ciudad de México</option><option>Estado de México</option><option>Hidalgo</option><option>Puebla</option><option>Tlaxcala</option><option>Morelos</option><option>Querétaro</option><option>Veracruz</option><option>Guerrero</option><option>Otro estado</option><option>Extranjero</option></datalist>
                <div><label class="block text-sm font-medium text-gray-700">Paciente</label><input type="text" name="paciente" id="inputPaciente" required class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div>
                <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-medium text-gray-700">Teléfono</label><input type="tel" name="telefono" id="inputTelefono" required class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div><div><label class="block text-sm font-medium text-gray-700">¿Ya es paciente?</label><select name="ya_es_paciente" id="inputYaPaciente" class="mt-1 block w-full border border-gray-300 rounded-md p-2"><option value="No">No</option><option value="Si">Sí</option></select></div></div>
                <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-medium text-gray-700">Tipo (Tratamiento)</label><select name="tipo" id="inputTipo" onchange="mostrarBloqueHiper()" class="mt-1 block w-full border border-gray-300 rounded-md p-2"><option value="Sports medicine">Sports medicine</option><option value="Clínica de rodillas">Clínica de rodillas</option><option value="Cámara Hiperbárica">Cámara Hiperbárica</option></select></div><div><label class="block text-sm font-medium text-gray-700">Sucursal</label><select name="sucursal" id="inputSucursal" class="mt-1 block w-full border border-gray-300 rounded-md p-2"><?= $opcionesSucursal ?></select></div></div>
                <div id="bloqueHiper" class="hidden rounded-xl border border-[#BFE3CD] bg-[#F1FAF7] p-3 space-y-3">
                    <p class="text-sm font-bold text-[#0A5E4D]">🫁 Cámara Hiperbárica <span class="font-normal text-[#4B5A62]">· la terapia requiere valoración médica previa</span></p>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700" for="inputModalidad">Modalidad</label><select name="modalidad" id="inputModalidad" onchange="mostrarBloqueHiper()" class="mt-1 block w-full border border-gray-300 rounded-md p-2 bg-white"><option value="Valoración médica">Valoración médica</option><option value="Sesión individual">Sesión individual</option><option value="Paquete de sesiones">Paquete de sesiones</option></select></div>
                        <div id="campoSesiones"><label class="block text-sm font-medium text-gray-700" for="inputSesiones">Sesiones del paquete</label><input type="number" name="sesiones_paquete" id="inputSesiones" min="2" max="60" placeholder="Ej. 10" class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700" for="inputMotivo">Motivo de consulta</label><select name="motivo_consulta" id="inputMotivo" class="mt-1 block w-full border border-gray-300 rounded-md p-2 bg-white"><option value="">Sin especificar</option><option>Pie diabético y heridas</option><option>Post-operatorio y estética</option><option>Recuperación deportiva</option><option>Bienestar y adultos mayores</option><option>Otro</option></select></div>
                        <div><label class="block text-sm font-medium text-gray-700" for="inputReferido">Lo envía (médico, opcional)</label><input type="text" name="referido_por" id="inputReferido" maxlength="120" placeholder="Ej. Dr. Pérez" class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div>
                    </div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700">Asistente</label><input type="text" name="asistente" id="inputAsistente" class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div>
                
                <div id="botonesEdicion" class="grid grid-cols-2 gap-2 mt-4 w-full">
                    <button type="button" id="btnPerfil" onclick="abrirPerfilPaciente()" class="hidden bg-blue-600 text-white font-bold py-2 px-1 rounded hover:bg-blue-700 transition duration-200 text-sm flex items-center justify-center gap-1">👤 Perfil</button>
                    <button type="button" id="btnCarnet" onclick="abrirCarnet()" class="hidden bg-purple-500 text-white font-bold py-2 px-1 rounded hover:bg-purple-600 transition duration-200 text-sm flex items-center justify-center gap-1">🪪 Carnet</button>
                    <button type="button" id="btnSubsecuente" onclick="prepararSubsecuente()" class="hidden bg-teal-500 text-white font-bold py-2 px-1 rounded hover:bg-teal-600 transition duration-200 text-sm flex items-center justify-center gap-1">🔁 Subsecuente</button>
                    <button type="button" id="btnEliminar" onclick="eliminarCita()" class="hidden bg-red-500 text-white font-bold py-2 px-1 rounded hover:bg-red-600 transition duration-200 text-sm flex items-center justify-center gap-1">🗑️ Eliminar</button>
                    <button type="button" id="btnCopiarCita" onclick="confirmacionDesdeFormulario()" class="col-span-2 bg-white font-bold py-2 px-1 rounded text-sm flex items-center justify-center gap-1">📋 Mensaje de confirmación para el paciente</button>
                </div>
                <button type="submit" id="btnGuardar" class="w-full mt-3 bg-[#10b981] text-white font-bold py-3 px-4 rounded hover:bg-green-600 transition duration-200">Guardar Cita</button>
            </form>
        </div>
    </div>

    <!-- SCRIPT PRINCIPAL UNIFICADO -->
    <script>
        // ===== Seguridad: token CSRF en cada petición, sesión expirada y escape de HTML =====
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        const _fetch = window.fetch.bind(window);
        window.fetch = (url, opts = {}) => {
            opts.headers = Object.assign({}, opts.headers, { 'X-CSRF-Token': CSRF });
            opts.credentials = 'same-origin';
            return _fetch(url, opts).then(res => {
                if (res.status === 401) { alert('Tu sesión expiró. Inicia sesión de nuevo.'); location.href = 'login_staff.php'; }
                return res;
            });
        };
        // Todo texto que venga de la BD pasa por esc() antes de ir a innerHTML (evita XSS y nombres con apóstrofo)
        function esc(v) { return String(v ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch])); }
        const fmtMXN = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

        const SUCURSAL_FIJA = <?= json_encode($sucUsuario['nombre_sucursal'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
        const PUEDE_NOTAS = <?= $esClinico ? 'true' : 'false' ?>;
        let sucursalActual = ''; let tipoTratamientoActual = ''; let yaPacienteActual = ''; let citasGlobales = []; let vistaActual = (() => { try { return localStorage.getItem('crm_vista') || 'dia'; } catch (e) { return 'dia'; } })(); let diaActual = new Date();
        const nombresMesesCorto = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const nombresMesesCompletos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        let lunesActual = new Date(); const diaSemana = lunesActual.getDay(); const diff = lunesActual.getDate() - diaSemana + (diaSemana === 0 ? -6 : 1);
        lunesActual.setDate(diff); let mesActualDate = new Date(); 

        document.getElementById('checkSiAsistio').addEventListener('change', function() { if(this.checked) document.getElementById('checkNoAsistio').checked = false; });
        document.getElementById('checkNoAsistio').addEventListener('change', function() { if(this.checked) document.getElementById('checkSiAsistio').checked = false; });

        function formatearFechaISO(fechaObj) {
            const anio = fechaObj.getFullYear(); const mes = (fechaObj.getMonth() + 1).toString().padStart(2, '0'); const dia = fechaObj.getDate().toString().padStart(2, '0'); return `${anio}-${mes}-${dia}`;
        }

        // ============================
        // NUEVA FUNCIÓN: HISTORIAL DE CORTES
        // ============================
        function abrirHistorialCortes() {
            document.getElementById('filtroHC_Sucursal').value = sucursalActual; 
            aplicarPeriodoRapido(); 
            document.getElementById('modalHistorialCortes').classList.remove('hidden');
        }

        function cerrarHistorialCortes() {
            document.getElementById('modalHistorialCortes').classList.add('hidden');
        }

        function aplicarPeriodoRapido() {
            const periodo = document.getElementById('filtroHC_Periodo').value;
            const inputInicio = document.getElementById('filtroHC_Inicio');
            const inputFin = document.getElementById('filtroHC_Fin');
            const hoy = new Date();
            
            if (periodo === 'todo') { inputInicio.value = ''; inputFin.value = ''; } 
            else if (periodo === 'semana') {
                let inicioSemana = new Date(hoy); const dia = inicioSemana.getDay(); const diff = inicioSemana.getDate() - dia + (dia === 0 ? -6 : 1);
                inicioSemana.setDate(diff); inputInicio.value = formatearFechaISO(inicioSemana);
                let finSemana = new Date(inicioSemana); finSemana.setDate(finSemana.getDate() + 6); inputFin.value = formatearFechaISO(finSemana);
            } else if (periodo === 'mes') {
                const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1); const finMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
                inputInicio.value = formatearFechaISO(inicioMes); inputFin.value = formatearFechaISO(finMes);
            } else if (periodo === 'trimestre') {
                const inicioTrimestre = new Date(hoy.getFullYear(), hoy.getMonth() - 2, 1); const finMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
                inputInicio.value = formatearFechaISO(inicioTrimestre); inputFin.value = formatearFechaISO(finMes);
            }
            cargarHistorialCortes();
        }

        function cargarHistorialCortes() {
            const sucursal = document.getElementById('filtroHC_Sucursal').value;
            const inicio = document.getElementById('filtroHC_Inicio').value;
            const fin = document.getElementById('filtroHC_Fin').value;
            
            // Texto dinámico para impresión
            const textoSucursal = sucursal || 'TODAS LAS SUCURSALES';
            document.getElementById('print-hc-rango').innerText = `SUCURSAL: ${textoSucursal} | PERÍODO: ${inicio || 'HISTÓRICO'} AL ${fin || 'ACTUAL'}`;

            fetch(`obtener_cortes.php?sucursal=${encodeURIComponent(sucursal)}&fecha_inicio=${encodeURIComponent(inicio)}&fecha_fin=${encodeURIComponent(fin)}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const r = data.resumen; const fmt = fmtMXN;
                    document.getElementById('hc-tot-pacientes').innerText = r.pacientes; document.getElementById('hc-tot-efectivo').innerText = fmt.format(r.efectivo); document.getElementById('hc-tot-tarjetas').innerText = fmt.format(r.tarjetas); document.getElementById('hc-tot-transferencia').innerText = fmt.format(r.transferencia); document.getElementById('hc-tot-general').innerText = fmt.format(r.total);
                    const tbody = document.getElementById('tabla-historial-cortes'); let html = '';
                    if (data.data.length === 0) { html = `<tr><td colspan="8" class="text-center py-8 text-gray-500 font-bold bg-white">No hay cortes de caja guardados en este período.</td></tr>`; } 
                    else {
                        data.data.forEach(c => {
                            html += `<tr onclick="abrirDetalleCorte(${parseInt(c.id_corte)})" title="Ver detalle" class="hover:bg-teal-50 cursor-pointer transition border-b border-gray-100"><td class="px-4 py-3 font-mono text-xs text-teal-700 underline">#${c.id_corte}</td><td class="px-4 py-3 font-bold text-gray-800">${esc(c.fecha)}</td><td class="px-4 py-3 text-gray-600 font-medium">${esc(c.sucursal)}</td><td class="px-4 py-3 text-center font-bold text-blue-600">${c.total_pacientes}</td><td class="px-4 py-3 text-right text-gray-600">${fmt.format(c.efectivo)}</td><td class="px-4 py-3 text-right text-gray-600">${fmt.format(parseFloat(c.credito) + parseFloat(c.debito))}</td><td class="px-4 py-3 text-right text-gray-600">${fmt.format(c.transferencia)}</td><td class="px-4 py-3 text-right font-black text-blue-800 print:text-black">${fmt.format(c.total_general)}</td></tr>`;
                        });
                    }
                    tbody.innerHTML = html;
                } else { alert("Error al cargar historial: " + data.message); }
            }).catch(err => { console.error("Error de red.", err); });
        }

        // ============================
        // GUARDAR CORTE
        // ============================
        function guardarCorteDiario() {
            // Los totales los calcula el servidor con las remisiones EMITIDAS (no con lo tecleado en pantalla)
            const pendientes = [...document.querySelectorAll('.fila-paciente-remision')].filter(f => {
                const c = remisionesDia.find(x => `fila-remision-${x.id_cita}` === f.id);
                const editable = !(c && c.id_remision) || f.dataset.adicional;
                return editable && [...f.querySelectorAll('input[type=number]')].some(i => parseFloat(i.value) > 0);
            }).length;
            if (pendientes && !confirm(`Hay ${pendientes} paciente(s) con monto capturado SIN remisión emitida; no entrarán en el corte.\n¿Guardar de todos modos?`)) return;
            const contado = prompt('Efectivo contado en caja (opcional, para el arqueo):', '');
            if (contado === null) return;

            const btn = document.getElementById('btn-guardar-corte'); const originalText = btn.innerHTML;
            btn.innerHTML = "⏳ Guardando..."; btn.disabled = true;
            const formData = new FormData();
            formData.append('sucursal', sucursalActual);
            formData.append('fecha', document.getElementById('inputFechaRemision').value);
            const limpio = contado.replace(/[,$\s]/g, '');
            if (limpio !== '') formData.append('efectivo_contado', limpio);

            fetch('guardar_corte.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { if (data.status === 'success') { alert('✅ ' + data.message); cargarDatosRemision(); } else { alert('Error: ' + data.message); } })
            .catch(() => alert('Error de red al guardar el corte.'))
            .finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
        }

        // ============================
        // DIRECTORIO / REPORTES
        // ============================
        // El directorio pide su propia lista (todo el historial), independiente del rango del calendario
        let citasDirectorio = [];
        function abrirDirectorio() {
            document.getElementById('modalDirectorio').classList.remove('hidden');
            document.getElementById('tabla-directorio-body').innerHTML = '<tr><td colspan="8" class="text-center py-8 text-gray-400 bg-white">Cargando...</td></tr>';
            fetch(`obtener_citas.php?${new URLSearchParams({ sucursal: sucursalActual })}`).then(r => r.json()).then(d => {
                citasDirectorio = d.status === 'success' ? d.data : [];
                renderizarTablaDirectorio(true);
            }).catch(() => alert('Error de red al cargar el directorio.'));
        }
        function cerrarDirectorio() { document.getElementById('modalDirectorio').classList.add('hidden'); }

        function renderizarTablaDirectorio(reset = false) {
            if(reset) { document.getElementById('filtroDirTratamiento').value = ''; document.getElementById('filtroDirAsistencia').value = ''; document.getElementById('filtroDirEstado').value = ''; }
            const fTratamiento = document.getElementById('filtroDirTratamiento').value; const fAsistencia = document.getElementById('filtroDirAsistencia').value; const fEstado = document.getElementById('filtroDirEstado').value;

            let citasFiltradas = citasDirectorio.filter(cita => {
                let pasa = true;
                if(fTratamiento && cita.tipo_tratamiento !== fTratamiento) pasa = false;
                if(fAsistencia) { let asisReal = cita.asistencia; if(asisReal !== 'Asistió' && asisReal !== 'No Asistió') asisReal = 'Pendiente'; if(asisReal !== fAsistencia) pasa = false; }
                if(fEstado && cita.estado !== fEstado) pasa = false;
                return pasa;
            });

            citasFiltradas.sort((a, b) => b.fecha.localeCompare(a.fecha) || b.hora.localeCompare(a.hora));
            const tbody = document.getElementById('tabla-directorio-body'); let html = '';

            if(citasFiltradas.length === 0) { html = `<tr><td colspan="8" class="text-center py-8 text-gray-500 font-bold bg-white">No se encontraron citas con estos filtros.</td></tr>`; } 
            else {
                citasFiltradas.forEach(cita => {
                    let badgeAsistencia = cita.asistencia === 'Asistió' ? '<span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-bold border border-green-200">✅ Asistió</span>' : (cita.asistencia === 'No Asistió' ? '<span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-bold border border-red-200">❌ Faltó</span>' : '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs font-bold border border-yellow-200">⏳ Pendiente</span>');
                    let badgeEstado = cita.estado === 'Express' ? '<span class="text-orange-600 font-bold">⚡ Express</span>' : (cita.estado === 'Confirmada' ? '<span class="text-blue-600 font-bold">👍 Confirmada</span>' : '<span class="text-gray-500 font-medium">📅 Agendado</span>');
                    html += `<tr class="hover:bg-gray-50 transition border-b border-gray-100"><td class="px-4 py-3 font-bold text-gray-700">${cita.fecha} <span class="text-gray-400 font-normal ml-1 text-xs">${cita.hora.substring(0,5)}</span></td><td class="px-4 py-3 font-bold text-gray-800">${esc(cita.paciente)}</td><td class="px-4 py-3 text-gray-600">${esc(cita.telefono)}</td><td class="px-4 py-3 text-gray-600">${esc(cita.sucursal)}</td><td class="px-4 py-3 text-gray-600 font-medium">${esc(cita.tipo_tratamiento)}</td><td class="px-4 py-3 text-center">${badgeEstado}</td><td class="px-4 py-3 text-center">${badgeAsistencia}</td><td class="px-4 py-3 text-center"><button onclick="abrirDirectorioEditar(${cita.id_cita})" class="bg-indigo-100 text-indigo-700 hover:bg-indigo-600 hover:text-white px-3 py-1.5 rounded shadow-sm text-xs font-bold transition-colors w-full">Abrir</button></td></tr>`;
                });
            }
            tbody.innerHTML = html;
        }

        function abrirDirectorioEditar(idCita) { cerrarDirectorio(); const cita = citasDirectorio.find(c => c.id_cita == idCita); if(cita) abrirModalEditar(cita); }

        function abrirCitaExpress() {
            const hoy = new Date(); const fechaStr = formatearFechaISO(hoy); let minutos = hoy.getMinutes(); let horas = hoy.getHours();
            if (minutos > 30) { minutos = "00"; horas = horas + 1; } else { minutos = "30"; } const horaStr = `${horas.toString().padStart(2, '0')}:${minutos}`;
            abrirModalNueva(fechaStr, horaStr);
            document.getElementById('tituloModal').innerText = "⚡ Registrar Cita en el Momento"; document.getElementById('checkSiAsistio').checked = true; document.getElementById('inputEstado').value = "Express"; document.getElementById('inputYaPaciente').value = "Si";
            const msg = document.getElementById('mensaje'); msg.textContent = "ℹ️ Cita Express: Fecha de hoy, hora actual y asistencia autocompletadas."; msg.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700', 'bg-blue-100', 'text-blue-800'); msg.classList.add('bg-orange-100', 'text-orange-800');
        }

        function prepararSubsecuente() {
            document.getElementById('inputIdCita').value = ""; document.getElementById('tituloModal').innerText = "🔁 Agendar Cita Subsecuente"; document.getElementById('inputFecha').value = ""; document.getElementById('inputHora').value = ""; document.getElementById('inputEstado').value = "Express"; document.getElementById('inputYaPaciente').value = "Si"; document.getElementById('checkSiAsistio').checked = false; document.getElementById('checkNoAsistio').checked = false; document.getElementById('botonesEdicion').classList.add('hidden'); document.getElementById('btnGuardar').innerText = "Guardar Cita Subsecuente";
            const msg = document.getElementById('mensaje'); msg.textContent = "ℹ️ Datos copiados. Selecciona la nueva fecha y hora."; msg.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700', 'bg-orange-100', 'text-orange-800'); msg.classList.add('bg-blue-100', 'text-blue-800');
        }

        function abrirPerfilPaciente() {
            const paciente = document.getElementById('inputPaciente').value; const telefono = document.getElementById('inputTelefono').value; const idCita = document.getElementById('inputIdCita').value; if(!paciente || !idCita) return;
            document.getElementById('perfil-nombre').innerText = "Cargando..."; document.getElementById('modalPerfil').classList.remove('hidden'); if (PUEDE_NOTAS) cargarNotas();
            fetch(`obtener_perfil_paciente.php?id_cita=${encodeURIComponent(idCita)}`).then(res => res.json()).then(data => {
                if(data.status === 'success') {
                    const info = data.data; document.getElementById('perfil-nombre').innerText = info.paciente; document.getElementById('perfil-telefono').innerText = "📞 " + (info.telefono || telefono || 'Sin registrar') + (info.fecha_nacimiento ? '   🎂 ' + info.fecha_nacimiento.split('-').reverse().join('/') : '') + (info.procedencia ? '   📍 ' + info.procedencia : ''); document.getElementById('perfil-folio').innerText = info.folio; document.getElementById('perfil-total-citas').innerText = info.total_citas; document.getElementById('perfil-asistencias').innerText = info.asistencias; document.getElementById('perfil-faltas').innerText = info.faltas;
                    const usdFormatter = fmtMXN; document.getElementById('perfil-inversion').innerText = usdFormatter.format(info.inversion);
                    let htmlTabla = '';
                    if(info.historial.length === 0) { htmlTabla = '<tr><td colspan="4" class="text-center py-4 text-gray-500">No hay historial clínico previo.</td></tr>'; } 
                    else {
                        info.historial.forEach(c => {
                            let badge = c.asistencia === 'Asistió' ? '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold">✅ Asistió</span>' : (c.asistencia === 'No Asistió' ? '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">❌ Faltó</span>' : '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold">⏳ Pendiente</span>');
                            htmlTabla += `<tr class="hover:bg-gray-50 transition border-b border-gray-100"><td class="px-4 py-3 font-bold text-gray-700">${c.fecha} <span class="text-gray-400 font-normal ml-1">${c.hora.substring(0,5)}</span></td><td class="px-4 py-3 text-gray-600">${esc(c.tipo_tratamiento)}</td><td class="px-4 py-3 text-gray-500 font-medium">${esc(c.sucursal)}</td><td class="px-4 py-3 text-center">${badge}</td></tr>`;
                        });
                    }
                    document.getElementById('perfil-historial-body').innerHTML = htmlTabla;
                } else { alert("Error al cargar perfil: " + data.message); cerrarPerfil(); }
            }).catch(err => { alert("Error de conexión al obtener el perfil."); cerrarPerfil(); });
        }
        function cerrarPerfil() { document.getElementById('modalPerfil').classList.add('hidden'); }

        function abrirCarnet() {
            const paciente = document.getElementById('inputPaciente').value; const tratamiento = document.getElementById('inputTipo').value; const idCita = document.getElementById('inputIdCita').value; if(!paciente) return;
            document.getElementById('txt-carnet-nombre').innerText = paciente; document.getElementById('txt-carnet-tratamiento').innerText = tratamiento; document.getElementById('inputExpedienteCarnet').value = `CR-${new Date().getFullYear()}-${idCita.padStart(4, '0')}`; document.getElementById('inputDiagCarnet').value = ''; document.getElementById('inputMedicoCarnet').value = '';
            fetch(`obtener_perfil_paciente.php?id_cita=${encodeURIComponent(idCita)}`).then(res => res.json()).then(data => {
                let asistencias = 0; if(data.status === 'success') { asistencias = data.data.asistencias; document.getElementById('inputExpedienteCarnet').value = data.data.folio; } document.getElementById('inputTomadasCarnet').value = asistencias; document.getElementById('inputTotalesCarnet').value = (([...citasGlobales, ...(typeof citasDirectorio !== 'undefined' ? citasDirectorio : [])].find(x => x.id_cita == idCita) || {}).sesiones_paquete) || 10; dibujarCarnetVisual(); document.getElementById('modalCarnet').classList.remove('hidden');
            });
        }
        function cerrarCarnet() { document.getElementById('modalCarnet').classList.add('hidden'); }

        function dibujarCarnetVisual() {
            const totales = parseInt(document.getElementById('inputTotalesCarnet').value) || 10; const tomadas = parseInt(document.getElementById('inputTomadasCarnet').value) || 0; const exp = document.getElementById('inputExpedienteCarnet').value; const diag = document.getElementById('inputDiagCarnet').value; const med = document.getElementById('inputMedicoCarnet').value;
            document.getElementById('txt-carnet-exp').innerText = exp || 'CR-XXXX'; document.getElementById('txt-carnet-diag').innerText = diag || 'No especificado'; document.getElementById('txt-carnet-medico').innerText = med || 'No especificado'; document.getElementById('txt-carnet-avance').innerText = `${tomadas} / ${totales}`;
            const grid = document.getElementById('carnet-grid'); let html = '';
            for(let i=1; i<=totales; i++) {
                if(i <= tomadas) { html += `<div style="width: 45px; height: 45px; border-radius: 50%; background-color: #10b981; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; border: 3px solid #d1fae5; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg></div>`; } 
                else { html += `<div style="width: 45px; height: 45px; border-radius: 50%; background-color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #94a3b8; border: 2px dashed #cbd5e1; font-size: 16px;">${i}</div>`; }
            }
            grid.innerHTML = html;
        }

        function generarEnviarCarnet() {
            const paciente = document.getElementById('inputPaciente').value; const telefono = document.getElementById('inputTelefono').value; const totales = document.getElementById('inputTotalesCarnet').value; const tomadas = document.getElementById('inputTomadasCarnet').value; const btn = document.getElementById('btnGenerarCarnet'); const txtOrig = btn.innerHTML;
            btn.innerHTML = "⏳ Generando PDF..."; btn.disabled = true; const target = document.getElementById('carnet-render');
            const opt = { margin: 0, filename: `Carnet_Clinico_${paciente.replace(/\s+/g, '_')}.pdf`, image: { type: 'jpeg', quality: 1 }, html2canvas: { scale: 3, useCORS: true }, jsPDF: { unit: 'pt', format: [target.offsetWidth, target.offsetHeight], orientation: 'landscape' } };
            html2pdf().set(opt).from(target).output('datauristring').then(function(pdfBase64) {
                const formData = new FormData(); formData.append('id_cita', document.getElementById('inputIdCita').value); formData.append('sesiones_totales', totales); formData.append('sesiones_tomadas', tomadas); formData.append('pdf_data', pdfBase64);
                fetch('guardar_carnet.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => { btn.innerHTML = txtOrig; btn.disabled = false; if(data.status === 'success') { cerrarCarnet(); window.open(data.wa_link, '_blank'); } else { alert("Error al guardar carnet: " + (data.message || "")); } }).catch(err => { btn.innerHTML = txtOrig; btn.disabled = false; alert("Error de red."); });
            });
        }

        function abrirRemisiones() {
            if (sucursalActual === '') { alert("⚠️ Selecciona una sucursal específica."); return; }
            document.getElementById('tituloModalRemisiones').innerText = `🧾 Control Diario - Sucursal ${sucursalActual}`; document.getElementById('print-sucursal-titulo').innerText = `SUCURSAL ${sucursalActual}`; document.getElementById('inputFechaRemision').value = formatearFechaISO(new Date()); cargarDatosRemision(); document.getElementById('modalRemisiones').classList.remove('hidden');
        }
        function cerrarModalRemisiones() { document.getElementById('modalRemisiones').classList.add('hidden'); }

        // La lista del día se pide al servidor SIN los filtros del calendario (antes, con un filtro activo,
        // el corte salía incompleto) y trae las remisiones ya emitidas con su folio real.
        let remisionesDia = [];
        function cargarDatosRemision() {
            const fecha = document.getElementById('inputFechaRemision').value; const tbody = document.getElementById('tabla-remisiones-body');
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-6 text-gray-400 bg-white">Cargando...</td></tr>`;
            fetch(`obtener_remisiones_dia.php?${new URLSearchParams({ sucursal: sucursalActual, fecha })}`).then(r => r.json()).then(data => {
                if (data.status !== 'success') { tbody.innerHTML = `<tr><td colspan="9" class="text-center py-6 text-red-500 bg-white">${esc(data.message)}</td></tr>`; return; }
                remisionesDia = data.data;
                document.getElementById('btn-guardar-corte').title = data.corte ? `Último corte guardado: ${data.corte.fecha_registro}` : 'Aún no hay corte guardado para este día';
                if (remisionesDia.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="9" class="text-center py-6 text-gray-500 font-bold bg-white">No hay pacientes programados.</td></tr>`;
                } else {
                    tbody.innerHTML = remisionesDia.map((c, i) => {
                        const emitida = !!c.id_remision;
                        const ro = emitida ? 'readonly tabindex="-1"' : '';
                        const val = (k) => emitida && parseFloat(c[k]) > 0 ? `value="${esc(c[k])}"` : '';
                        const celdaNum = (cls, k) => `<td class="px-2 py-1 border-r border-gray-300"><input type="number" min="0" step="0.01" ${val(k)} ${ro} oninput="calcularTotalesRemision()" class="${cls} border-0 w-full text-sm text-right font-bold bg-transparent outline-none focus:ring-1 focus:ring-emerald-300" placeholder="0"></td>`;
                        return `<tr id="fila-remision-${parseInt(c.id_cita)}" class="border-b border-gray-300 hover:bg-gray-50 transition-colors fila-paciente-remision ${emitida ? 'bg-emerald-50' : 'bg-white'}">`
                            + `<td class="px-2 py-1 text-center font-bold text-gray-600 border-r border-gray-300">${i + 1}</td>`
                            + `<td class="px-2 py-1 border-r border-gray-300 font-mono text-xs input-folio ${emitida ? 'text-emerald-700 font-bold' : 'text-gray-400'}">${emitida ? esc(c.folio) : 'Auto'}</td>`
                            + `<td class="px-3 py-1 border-r border-gray-300 font-bold text-gray-900 truncate nombre-paciente">${esc(c.paciente)}</td>`
                            + `<td class="px-2 py-1 border-r border-gray-300"><input type="text" maxlength="150" ${emitida ? `value="${esc(c.servicio)}"` : ''} ${ro} class="input-servicio border-0 w-full text-sm text-gray-600 bg-transparent outline-none focus:ring-1 focus:ring-emerald-300" placeholder="Escribe el servicio..."></td>`
                            + celdaNum('input-efectivo', 'efectivo') + celdaNum('input-credito', 'credito') + celdaNum('input-debito', 'debito') + celdaNum('input-transferencia', 'transferencia')
                            + `<td class="px-2 py-1 text-center no-print"><button onclick="emitirReciboCliente(${parseInt(c.id_cita)})" class="${emitida ? 'bg-gray-400 hover:bg-gray-500' : 'bg-emerald-500 hover:bg-emerald-600'} text-white px-3 py-1.5 rounded shadow text-xs font-bold transition-colors w-full">${emitida ? '➕ Otro cobro' : '📤 Emitir PDF'}</button>${emitida && c.ruta_archivo ? `<a href="ver_archivo.php?f=${encodeURIComponent(c.ruta_archivo)}" target="_blank" class="block mt-1 text-xs text-blue-700 underline">🖨️ Ver / imprimir</a>` : ''}</td></tr>`;
                    }).join('');
                }
                (data.sueltas || []).forEach(r => {
                    const v = (k) => parseFloat(r[k]) > 0 ? `value="${esc(r[k])}"` : '';
                    const celda = (cls, k) => `<td class="px-2 py-1 border-r border-gray-300"><input type="number" ${v(k)} readonly tabindex="-1" class="${cls} border-0 w-full text-sm text-right font-bold bg-transparent outline-none"></td>`;
                    tbody.insertAdjacentHTML('beforeend', `<tr class="border-b border-gray-300 bg-sky-50"><td class="px-2 py-1 text-center text-gray-500 border-r border-gray-300">—</td><td class="px-2 py-1 border-r border-gray-300 font-mono text-xs text-emerald-700 font-bold">${esc(r.folio)}</td><td class="px-3 py-1 border-r border-gray-300 font-bold text-gray-900 truncate">${esc(r.paciente)} <span class="text-xs font-normal text-sky-700">(sin cita)</span></td><td class="px-2 py-1 border-r border-gray-300 text-sm text-gray-600 truncate">${esc(r.servicio)}</td>${celda('input-efectivo', 'efectivo')}${celda('input-credito', 'credito')}${celda('input-debito', 'debito')}${celda('input-transferencia', 'transferencia')}<td class="px-2 py-1 text-center no-print">${r.ruta_archivo ? `<a href="ver_archivo.php?f=${encodeURIComponent(r.ruta_archivo)}" target="_blank" class="text-xs text-blue-700 underline">🖨️ Ver / imprimir</a>` : ''}</td></tr>`);
                });
                document.getElementById('tot-pacientes').innerText = remisionesDia.length + (data.sueltas || []).length;
                const folios = [...remisionesDia, ...(data.sueltas || [])].filter(c => c.folio).map(c => c.folio).sort();
                document.getElementById('folio-inicio').value = folios[0] || '';
                document.getElementById('folio-fin').value = folios[folios.length - 1] || '';
                calcularTotalesRemision();
            }).catch(() => { tbody.innerHTML = `<tr><td colspan="9" class="text-center py-6 text-red-500 bg-white">Error de red.</td></tr>`; });
        }

        function calcularTotalesRemision() {
            let totEfectivo = 0, totCredito = 0, totDebito = 0, totTransf = 0; document.querySelectorAll('.input-efectivo').forEach(input => { const val = parseFloat(input.value); if(!isNaN(val)) totEfectivo += val; }); document.querySelectorAll('.input-credito').forEach(input => { const val = parseFloat(input.value); if(!isNaN(val)) totCredito += val; }); document.querySelectorAll('.input-debito').forEach(input => { const val = parseFloat(input.value); if(!isNaN(val)) totDebito += val; }); document.querySelectorAll('.input-transferencia').forEach(input => { const val = parseFloat(input.value); if(!isNaN(val)) totTransf += val; });
            document.getElementById('tot-efectivo').innerText = `$${totEfectivo.toFixed(2)}`; document.getElementById('tot-credito').innerText = `$${totCredito.toFixed(2)}`; document.getElementById('tot-debito').innerText = `$${totDebito.toFixed(2)}`; document.getElementById('tot-transferencia').innerText = `$${totTransf.toFixed(2)}`; document.getElementById('tot-general').innerText = `$${(totEfectivo + totCredito + totDebito + totTransf).toFixed(2)}`;
        }

        function generarPdfRemision(c, folio, servicio, efectivo, credito, debito, transf, total, fecha) {
            const metodo = [['Efectivo', efectivo], ['Tarjeta de crédito', credito], ['Tarjeta de débito', debito], ['Transferencia', transf]].filter(m => m[1] > 0).map(m => m[0]).join(' + ');
            return pdfNotaRemision({ folio, fecha, nombre: c.paciente, conceptos: [{ cantidad: 1, descripcion: servicio, precio: total }], total, observaciones: '', metodo, atendio: USUARIO_NOMBRE });
        }

        // 1) Registra el cobro (el servidor da el folio)  2) genera el PDF con ese folio  3) sube el PDF
        function emitirReciboCliente(idCita) {
            const c = remisionesDia.find(x => x.id_cita == idCita); if (!c) return;
            const fila = document.getElementById(`fila-remision-${idCita}`);
            const btn = fila.querySelector('button');
            const inputs = fila.querySelectorAll('input');
            if (c.id_remision && !fila.dataset.adicional) {
                // Ya tiene remisión: el primer clic desbloquea la fila para un cobro adicional
                if (!confirm(`Esta cita ya tiene la remisión ${c.folio}. ¿Capturar un cobro adicional?`)) return;
                fila.dataset.adicional = '1';
                inputs.forEach(i => { i.readOnly = false; i.removeAttribute('tabindex'); i.value = ''; });
                btn.innerHTML = '📤 Emitir PDF'; inputs[0].focus(); calcularTotalesRemision(); return;
            }
            const servicio = fila.querySelector('.input-servicio').value.trim();
            const num = (cls) => Math.max(0, parseFloat(fila.querySelector(cls).value) || 0);
            const efectivo = num('.input-efectivo'), credito = num('.input-credito'), debito = num('.input-debito'), transf = num('.input-transferencia');
            const total = efectivo + credito + debito + transf;
            const fecha = document.getElementById('inputFechaRemision').value;
            if (servicio.length < 3) { alert("⚠️ Escribe el detalle del servicio."); fila.querySelector('.input-servicio').focus(); return; }
            if (total <= 0) { alert("⚠️ Captura el monto cobrado."); return; }
            if (!confirm(`¿Registrar cobro de ${fmtMXN.format(total)} a ${c.paciente}?`)) return;

            const textoOriginal = btn.innerHTML; btn.innerHTML = "⏳ Guardando..."; btn.disabled = true;
            const fd = new FormData();
            fd.append('id_cita', idCita); fd.append('servicio', servicio); fd.append('efectivo', efectivo); fd.append('credito', credito);
            fd.append('debito', debito); fd.append('transferencia', transf); fd.append('fecha', fecha);
            if (fila.dataset.adicional) fd.append('forzar', '1');

            fetch('guardar_remision.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                if (data.status === 'duplicada') { alert(data.message); cargarDatosRemision(); return null; }
                if (data.status !== 'success') throw new Error(data.message);
                btn.innerHTML = "⏳ Generando PDF...";
                return generarPdfRemision(c, data.folio, servicio, efectivo, credito, debito, transf, total, fecha).then(pdf => {
                    const fd2 = new FormData(); fd2.append('id_remision', data.id_remision); fd2.append('pdf_data', pdf);
                    return fetch('adjuntar_pdf_remision.php', { method: 'POST', body: fd2 }).then(r => r.json());
                }).then(d2 => {
                    if (d2.status !== 'success') { alert(`El cobro quedó registrado con folio ${data.folio}, pero el PDF no se guardó: ${d2.message}`); }
                    else if (confirm(`✅ Recibo guardado (Folio: ${data.folio}). La asistencia quedó marcada automáticamente.\n\nAceptar = enviarlo por WhatsApp\nCancelar = abrirlo para imprimir`)) { window.open(d2.wa_link, '_blank'); }
                    else { window.open(d2.pdf, '_blank'); }
                    cargarCitas(sucursalActual, '');
                    cargarDatosRemision();
                });
            }).catch(err => {
                alert('Error: ' + (err && err.message ? err.message : 'de red'));
                btn.innerHTML = textoOriginal; btn.disabled = false;
            });
        }

        function compilarResumenRemision() {
            let texto = `*🧾 CORTE DIARIO - SUCURSAL ${sucursalActual.toUpperCase()}*\n*📅 Fecha:* ${document.getElementById('inputFechaRemision').value}\n-----------------------------------\n`;
            const filas = document.querySelectorAll('.fila-paciente-remision'); let desglose = '';
            if (filas.length > 0 && document.getElementById('tot-pacientes').innerText !== '0') {
                filas.forEach(fila => {
                    const folio = fila.querySelector('.input-folio').innerText; const paciente = fila.querySelector('.nombre-paciente').innerText;
                    const efectivo = parseFloat(fila.querySelector('.input-efectivo').value) || 0; const credito = parseFloat(fila.querySelector('.input-credito').value) || 0; const debito = parseFloat(fila.querySelector('.input-debito').value) || 0; const transf = parseFloat(fila.querySelector('.input-transferencia').value) || 0;
                    const totalPaciente = efectivo + credito + debito + transf;
                    if(totalPaciente > 0) { desglose += `• [${folio}] ${paciente} - *$${totalPaciente.toFixed(2)}*\n`; } else { desglose += `• [${folio}] ${paciente} - *Sin Cobro*\n`; }
                });
            } else { desglose += `No hubo pacientes registrados.\n`; }
            texto += `*📋 DESGLOSE DE PACIENTES:*\n${desglose}\n-----------------------------------\n*💰 RESUMEN DE VENTAS:*\n💵 Efectivo: ${document.getElementById('tot-efectivo').innerText}\n💳 T. Crédito: ${document.getElementById('tot-credito').innerText}\n💳 T. Débito: ${document.getElementById('tot-debito').innerText}\n📲 Transferencia: ${document.getElementById('tot-transferencia').innerText}\n-----------------------------------\n*🏆 TOTAL GENERAL: ${document.getElementById('tot-general').innerText}*\n👥 Total Pacientes: ${document.getElementById('tot-pacientes').innerText}`;
            return texto;
        }

        function enviarRemisionWhatsApp() { window.open(`https://wa.me/?text=${encodeURIComponent(compilarResumenRemision())}`, '_blank'); }
        function enviarRemisionCorreo() { window.open(`mailto:?subject=${encodeURIComponent(`Corte Diario - Sucursal ${sucursalActual}`)}&body=${encodeURIComponent(compilarResumenRemision())}`, '_blank'); }

        // ==========================================
        // VISTAS CALENDARIO Y AGENDA DIA
        // ==========================================
        function toggleVistaAnterior() { vistaActual = vistaActual === 'semana' ? 'mes' : 'semana'; document.getElementById('btn-toggle-vista').innerHTML = vistaActual === 'semana' ? '🗓️ Cambiar a Mensual' : '📅 Cambiar a Semanal'; actualizarCuadricula(); cargarCitas(sucursalActual, ''); }
        function cambiarRango(direccion) {
            if (vistaActual === 'dia') { diaActual.setDate(diaActual.getDate() + direccion); }
            else if (vistaActual === 'semana') { lunesActual.setDate(lunesActual.getDate() + (direccion * 7)); diaActual = new Date(lunesActual); }
            else { mesActualDate.setMonth(mesActualDate.getMonth() + direccion); diaActual = new Date(mesActualDate.getFullYear(), mesActualDate.getMonth(), 1); }
            actualizarCuadricula(); cargarCitas(sucursalActual, '');
        }
        function lunesDe(d) { const x = new Date(d); const w = x.getDay(); x.setDate(x.getDate() - w + (w === 0 ? -6 : 1)); return x; }
        function setVista(v) {
            vistaActual = v; try { localStorage.setItem('crm_vista', v); } catch (e) {}
            lunesActual = lunesDe(diaActual); mesActualDate = new Date(diaActual.getFullYear(), diaActual.getMonth(), 1);
            document.querySelectorAll('.vista-btn').forEach(b => b.setAttribute('aria-pressed', b.dataset.vista === v ? 'true' : 'false'));
            actualizarCuadricula(); cargarCitas(sucursalActual, '');
        }
        function irHoy() { diaActual = new Date(); setVista(vistaActual); }
        function nuevaCitaRapida() {
            const f = vistaActual === 'dia' ? formatearFechaISO(diaActual) : formatearFechaISO(new Date());
            const hd = horarioDia(new Date(f + 'T12:00').getDay());
            abrirModalNueva(f, hd ? aHora(hd.ini) : '09:00');
        }
        function actualizarCuadricula() {
            if (vistaActual === 'dia') { generarVistaDia(); return; }
            if (vistaActual === 'semana') generarCuadriculaSemanal(); else generarCuadriculaMensual();
            const hoy = formatearFechaISO(new Date());   // resaltar hoy
            document.querySelectorAll(`[id^="celda-sem-${hoy}-"], #celda-mes-${hoy}`).forEach(c => vistaActual === 'mes' ? c.parentElement.classList.add('es-hoy', 'es-hoy-mes') : c.classList.add('es-hoy'));
        }

        // ===== Horarios por sucursal y ausencias =====
        let configAgenda = { sucursales: [], bloqueos: [] };
        let gridSemana = { ini: 540, fin: 1050, paso: 30 };
        const DIAS_LARGO = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const aMin = (hh) => { const [a, b] = hh.split(':').map(Number); return a * 60 + b; };
        const aHora = (m) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
        function sucursalesVisibles() { return configAgenda.sucursales.filter(x => !sucursalActual || x.nombre_sucursal === sucursalActual); }
        // Horario del día (en minutos) uniendo las sucursales visibles; null si ninguna abre
        function horarioDia(dow) {
            const sucs = sucursalesVisibles();
            if (!sucs.some(x => Object.keys(x.horarios || {}).length)) return dow === 0 ? null : { ini: 540, fin: 1050 }; // sin configurar: L-S 9:00-17:30
            let ini = null, fin = null;
            sucs.forEach(x => { const hh = (x.horarios || {})[dow]; if (hh) { ini = ini === null ? aMin(hh.ini) : Math.min(ini, aMin(hh.ini)); fin = fin === null ? aMin(hh.fin) : Math.max(fin, aMin(hh.fin)); } });
            return ini === null ? null : { ini, fin };
        }
        function intervaloActual() { const v = sucursalesVisibles().map(x => parseInt(x.intervalo_min) || 30); return v.length ? Math.min(...v) : 30; }
        function bloqueosDe(fechaStr, horaStr = null) {
            return configAgenda.bloqueos.filter(b => (!sucursalActual || b.sucursal === sucursalActual) && fechaStr >= b.fecha_inicio && fechaStr <= b.fecha_fin
                && (horaStr === null || !b.hora_inicio || (horaStr >= b.hora_inicio && horaStr < b.hora_fin)));
        }
        const textoBloqueo = (b) => `${b.medico ? b.medico + ': ' : ''}${b.motivo}${b.hora_inicio ? ` (${b.hora_inicio}–${b.hora_fin})` : ''}${sucursalActual ? '' : ` [${b.sucursal}]`}`;

        function generarCuadriculaMensual() {
            const mes = mesActualDate.getMonth(); const anio = mesActualDate.getFullYear(); document.getElementById('texto-rango').innerText = `${nombresMesesCompletos[mes]} ${anio}`;
            const primerDiaSemana = new Date(anio, mes, 1).getDay(); const diasEnMes = new Date(anio, mes + 1, 0).getDate();
            let html = '<div class="grid grid-cols-7 gap-2 border-none bg-transparent shadow-none p-2">';
            ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'].forEach(d => html += `<div class="bg-[#007bff] text-white text-center py-2 rounded font-semibold text-sm">${d}</div>`);
            for (let i = 0; i < primerDiaSemana; i++) { html += `<div class="border rounded h-32 bg-gray-50 opacity-50"></div>`; }
            for (let i = 1; i <= diasEnMes; i++) {
                const fechaStr = `${anio}-${(mes + 1).toString().padStart(2, '0')}-${i.toString().padStart(2, '0')}`;
                const hd = horarioDia(new Date(anio, mes, i).getDay()); const bl = bloqueosDe(fechaStr);
                const avisos = bl.map(b => `<div class="text-[9px] leading-tight bg-red-100 text-red-700 font-bold rounded px-1 truncate" title="${esc(textoBloqueo(b))}">🚫 ${esc(textoBloqueo(b))}</div>`).join('');
                const boton = hd ? `<button onclick="abrirModalNueva('${fechaStr}', '${aHora(hd.ini)}')" class="solo-operacion bg-blue-100 text-blue-700 hover:bg-blue-600 hover:text-white rounded w-6 h-6 flex items-center justify-center font-bold text-lg leading-none transition-colors" title="Agendar Cita">+</button>` : '<span class="text-[10px] text-gray-400">Cerrado</span>';
                html += `<div class="border border-gray-300 rounded h-32 flex flex-col ${hd ? 'bg-white hover:border-blue-400' : 'bg-gray-100'} transition-colors overflow-hidden shadow-sm"><div class="${bl.length ? 'bg-red-50' : 'bg-gray-50'} border-b border-gray-200 px-2 py-1 flex justify-between items-center"><span class="font-bold text-gray-700 text-sm">${i}</span>${boton}</div><div id="celda-mes-${fechaStr}" class="flex-1 overflow-y-auto p-1 space-y-1">${avisos}</div></div>`;
            }
            html += '</div>'; document.getElementById('cuadricula-calendario').innerHTML = html;
        }

        function generarCuadriculaSemanal() {
            const dias = []; for (let i = 0; i < 7; i++) { const d = new Date(lunesActual); d.setDate(lunesActual.getDate() + i); dias.push(d); }
            document.getElementById('texto-rango').innerText = `Semana: ${dias[0].getDate()} ${nombresMesesCorto[dias[0].getMonth()]} al ${dias[6].getDate()} ${nombresMesesCorto[dias[6].getMonth()]} ${dias[0].getFullYear()}`;
            const visibles = dias.filter(d => horarioDia(d.getDay()));       // solo los días que abre (domingo si alguna sucursal abre)
            const cont = document.getElementById('cuadricula-calendario');
            if (!visibles.length) { cont.innerHTML = '<p class="p-8 text-center text-gray-500">No hay horario configurado para esta semana.</p>'; return; }
            let ini = Infinity, fin = 0; visibles.forEach(d => { const hd = horarioDia(d.getDay()); ini = Math.min(ini, hd.ini); fin = Math.max(fin, hd.fin); });
            const paso = intervaloActual(); gridSemana = { ini, fin, paso };
            const cols = `grid-template-columns: 70px repeat(${visibles.length}, minmax(0, 1fr));`;
            let html = `<div class="grid divide-x border-b border-gray-300" style="${cols}"><div class="bg-[#007bff] text-white font-bold p-2 flex items-center justify-center text-sm">Hora</div>`;
            visibles.forEach(d => {
                const f = formatearFechaISO(d); const bl = bloqueosDe(f).filter(b => !b.hora_inicio);
                html += `<div class="${bl.length ? 'bg-red-500' : 'bg-[#007bff]'} text-white p-2 text-center flex flex-col justify-center" title="${esc(bl.map(textoBloqueo).join(' | '))}"><span class="font-bold text-sm">${DIAS_LARGO[d.getDay()]}</span><span class="text-xs text-blue-100">${d.getDate()} ${nombresMesesCorto[d.getMonth()]}${bl.length ? ' · 🚫 ' + esc(bl[0].medico || bl[0].motivo) : ''}</span></div>`;
            });
            html += '</div>';
            for (let m = ini; m < fin; m += paso) {
                const hora = aHora(m);
                html += `<div class="grid divide-x border-b border-gray-200" style="${cols}"><div class="bg-gray-100 text-gray-700 font-bold p-1 flex items-center justify-center text-xs">${hora}</div>`;
                visibles.forEach(d => {
                    const f = formatearFechaISO(d); const hd = horarioDia(d.getDay()); const bl = bloqueosDe(f, hora);
                    if (m < hd.ini || m >= hd.fin) {
                        html += `<div id="celda-sem-${f}-${hora}" class="bg-gray-200 min-h-[4rem] p-1 flex flex-col gap-1" title="Cerrado"></div>`;
                    } else if (bl.length) {
                        html += `<div id="celda-sem-${f}-${hora}" class="bg-red-50 min-h-[4rem] p-1 flex flex-col gap-1" title="${esc(bl.map(textoBloqueo).join(' | '))}"><span class="text-[9px] text-red-600 font-bold truncate">🚫 ${esc(textoBloqueo(bl[0]))}</span></div>`;
                    } else {
                        html += `<div id="celda-sem-${f}-${hora}" class="bg-white min-h-[4rem] p-1 cursor-pointer hover:bg-blue-50 transition-colors flex flex-col gap-1 relative group" onclick="abrirModalNueva('${f}', '${hora}')"><div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 bg-blue-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-[10px] font-bold z-10 transition-opacity" title="Agregar">+</div></div>`;
                    }
                });
                html += '</div>';
            }
            cont.innerHTML = html;
        }

        function abrirModalAgendaDia(fecha) {
            const citasDelDia = citasGlobales.filter(c => c.fecha === fecha).sort((a, b) => a.hora.localeCompare(b.hora));
            const partes = fecha.split('-'); document.getElementById('tituloModalDia').innerText = `📅 Agenda: ${partes[2]} de ${nombresMesesCompletos[parseInt(partes[1])-1]} del ${partes[0]}`; let htmlLista = '';
            if (citasDelDia.length === 0) { htmlLista = '<p class="text-center text-gray-500 py-4">No hay citas.</p>'; } 
            else {
                citasDelDia.forEach(cita => {
                    let badgeAsistencia = cita.asistencia === 'Asistió' ? '<span class="text-green-600">✅ Asistió</span>' : (cita.asistencia === 'No Asistió' ? '<span class="text-red-600">❌ Faltó</span>' : '<span class="text-yellow-600">⏳ Pendiente</span>');
                    htmlLista += `<div onclick="abrirEditarDesdeAgendaDia(${cita.id_cita})" class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-200 hover:bg-blue-50 transition cursor-pointer"><div><p class="font-bold text-sm text-gray-800">${esc(cita.paciente)}</p><p class="text-xs text-gray-500 mt-1"><strong class="text-blue-700 text-sm mr-2">${esc(cita.hora.substring(0,5))}</strong> | 🩺 ${esc(cita.tipo_tratamiento)} | 🏢 ${esc(cita.sucursal)}</p></div><div class="text-xs font-bold text-right">${badgeAsistencia}</div></div>`;
                });
            }
            document.getElementById('lista-agenda-dia').innerHTML = htmlLista; document.getElementById('modalAgendaDia').classList.remove('hidden');
        }

        function cerrarModalAgendaDia() { document.getElementById('modalAgendaDia').classList.add('hidden'); }
        function abrirEditarDesdeAgendaDia(idCita) { const cita = citasGlobales.find(c => c.id_cita == idCita); if(cita) { cerrarModalAgendaDia(); abrirModalEditar(cita); } }

        function guardarAsistenciasLote() {
            // Solo se manda la asistencia (antes se reenviaba la cita completa y "null" llegaba como texto a la BD)
            const cambios = [];
            document.querySelectorAll('.select-asistencia-bulk').forEach(select => {
                const idCita = select.getAttribute('data-id'); const nuevo = select.value;
                const original = citasGlobales.find(c => c.id_cita == idCita);
                if (original && original.asistencia !== nuevo) {
                    const fd = new FormData(); fd.append('id_cita', idCita); fd.append('asistencia', nuevo);
                    cambios.push(fetch('actualizar_asistencia.php', { method: 'POST', body: fd }).then(r => r.json()));
                }
            });
            if (cambios.length === 0) { cerrarModalAsistencias(); return; }
            const btn = document.getElementById('btn-guardar-lote'); btn.innerHTML = "⏳ Guardando..."; btn.disabled = true;
            Promise.all(cambios).then(res => {
                const errores = res.filter(r => r.status !== 'success').length;
                if (errores) alert(`${errores} cambio(s) no se guardaron.`);
                cerrarModalAsistencias(); cargarCitas(sucursalActual, '');
            }).catch(() => alert('Error de red al guardar asistencias.'))
              .finally(() => { btn.innerHTML = "💾 Guardar Cambios"; btn.disabled = false; });
        }

        function abrirModalAsistencias() {
            const fechasVisiblesStr = []; let fechaReferencia = vistaActual === 'semana' ? lunesActual : (vistaActual === 'dia' ? diaActual : mesActualDate);
            const mes = fechaReferencia.getMonth(); const anio = fechaReferencia.getFullYear(); const diasEnMes = new Date(anio, mes + 1, 0).getDate();
            for (let i = 1; i <= diasEnMes; i++) { fechasVisiblesStr.push(`${anio}-${(mes + 1).toString().padStart(2, '0')}-${i.toString().padStart(2, '0')}`); }
            const citasRango = citasGlobales.filter(cita => fechasVisiblesStr.includes(cita.fecha)).sort((a, b) => a.fecha.localeCompare(b.fecha) || a.hora.localeCompare(b.hora));
            let asis = 0, faltas = 0, pend = 0; let htmlLista = '';
            citasRango.forEach(cita => {
                if (cita.asistencia === 'Asistió') asis++; else if (cita.asistencia === 'No Asistió') faltas++; else pend++;
                const colorClaseSelect = cita.asistencia === 'Asistió' ? 'text-green-700 bg-green-50 border-green-400' : (cita.asistencia === 'No Asistió' ? 'text-red-700 bg-red-50 border-red-400' : 'text-yellow-700 bg-yellow-50 border-yellow-400');
                htmlLista += `<div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-200 hover:bg-gray-100 transition shadow-sm"><div><p class="font-bold text-sm text-gray-800">${esc(cita.paciente)}</p><p class="text-xs text-gray-500 mt-1"><strong>📅 ${esc(cita.fecha)}</strong> | ⌚ ${esc(cita.hora.substring(0,5))} | 🩺 ${esc(cita.tipo_tratamiento)} | 🏢 ${esc(cita.sucursal)}</p></div><div class="ml-4"><select data-id="${cita.id_cita}" onchange="this.className = 'select-asistencia-bulk cursor-pointer border rounded-md p-2 text-sm font-bold shadow-sm outline-none transition-colors ' + (this.value === 'Asistió' ? 'text-green-700 bg-green-50 border-green-400' : (this.value === 'No Asistió' ? 'text-red-700 bg-red-50 border-red-400' : 'text-yellow-700 bg-yellow-50 border-yellow-400'))" class="select-asistencia-bulk cursor-pointer border rounded-md p-2 text-sm font-bold shadow-sm outline-none transition-colors ${colorClaseSelect}"><option value="Pendiente" ${cita.asistencia !== 'Asistió' && cita.asistencia !== 'No Asistió' ? 'selected' : ''}>⏳ Pendiente</option><option value="Asistió" ${cita.asistencia === 'Asistió' ? 'selected' : ''}>✅ Asistió</option><option value="No Asistió" ${cita.asistencia === 'No Asistió' ? 'selected' : ''}>❌ Faltó</option></select></div></div>`;
            });
            document.getElementById('res-total').innerText = citasRango.length; document.getElementById('res-asistio').innerText = asis; document.getElementById('res-falto').innerText = faltas; document.getElementById('res-pendientes').innerText = pend; document.getElementById('lista-asistencias').innerHTML = htmlLista || '<p class="text-sm text-gray-500 italic p-4 text-center">No hay citas registradas en este mes.</p>'; document.getElementById('modalAsistencias').classList.remove('hidden');
        }
        function cerrarModalAsistencias() { document.getElementById('modalAsistencias').classList.add('hidden'); }

        function abrirModalNueva(fecha, hora) {
            document.getElementById('modalCita').classList.remove('hidden'); document.getElementById('tituloModal').innerText = "Agendar Nueva Cita"; document.getElementById('inputIdCita').value = ""; document.getElementById('formCita').reset(); document.getElementById('checkSiAsistio').checked = false; document.getElementById('checkNoAsistio').checked = false; document.getElementById('inputFecha').value = fecha; document.getElementById('inputHora').value = hora;
            if(sucursalActual !== '') document.getElementById('inputSucursal').value = sucursalActual; if(tipoTratamientoActual !== '') document.getElementById('inputTipo').value = tipoTratamientoActual; mostrarBloqueHiper();
            document.getElementById('botonesEdicion').classList.add('hidden'); document.getElementById('btnGuardar').classList.remove('w-full'); document.getElementById('btnGuardar').classList.add('w-full'); document.getElementById('btnGuardar').innerText = "Guardar Cita"; document.getElementById('mensaje').classList.add('hidden');
        }

        function abrirModalEditar(cita) {
            document.getElementById('modalCita').classList.remove('hidden'); document.getElementById('tituloModal').innerText = "Editar Cita"; document.getElementById('checkSiAsistio').checked = false; document.getElementById('checkNoAsistio').checked = false;
            if (cita.asistencia === 'Asistió') document.getElementById('checkSiAsistio').checked = true; else if (cita.asistencia === 'No Asistió') document.getElementById('checkNoAsistio').checked = true;
            document.getElementById('inputIdCita').value = cita.id_cita; document.getElementById('inputFecha').value = cita.fecha; document.getElementById('inputHora').value = cita.hora.substring(0, 5); document.getElementById('inputEstado').value = cita.estado; document.getElementById('inputPaciente').value = cita.paciente; document.getElementById('inputTelefono').value = cita.telefono; document.getElementById('inputEdad').value = cita.edad ?? ''; document.getElementById('inputFechaNac').value = cita.fecha_nacimiento || ''; document.getElementById('inputProcedencia').value = cita.procedencia || ''; document.getElementById('inputYaPaciente').value = cita.ya_es_paciente; document.getElementById('inputTipo').value = cita.tipo_tratamiento; document.getElementById('inputAsistente').value = cita.asistente || ''; document.getElementById('inputSucursal').value = cita.sucursal; document.getElementById('inputModalidad').value = cita.modalidad || 'Valoración médica'; document.getElementById('inputSesiones').value = cita.sesiones_paquete || ''; document.getElementById('inputMotivo').value = cita.motivo_consulta || ''; document.getElementById('inputReferido').value = cita.referido_por || ''; mostrarBloqueHiper();
            document.getElementById('botonesEdicion').classList.remove('hidden'); document.getElementById('btnPerfil').classList.remove('hidden'); document.getElementById('btnCarnet').classList.remove('hidden'); document.getElementById('btnSubsecuente').classList.remove('hidden');
            const btnEliminar = document.getElementById('btnEliminar'); btnEliminar.classList.remove('hidden');
            if (cita.asistencia === 'Asistió') { btnEliminar.disabled = true; btnEliminar.classList.add('opacity-50', 'cursor-not-allowed'); } else { btnEliminar.disabled = false; btnEliminar.classList.remove('opacity-50', 'cursor-not-allowed'); }
            document.getElementById('btnGuardar').innerText = "Actualizar Cita"; document.getElementById('mensaje').classList.add('hidden');
        }

        function cerrarModal() { document.getElementById('modalCita').classList.add('hidden'); document.getElementById('formCita').reset(); document.getElementById('mensaje').classList.add('hidden'); }

        function eliminarCita() {
            const idCita = document.getElementById('inputIdCita').value; if (!idCita) return;
            const motivo = prompt('¿Por qué se cancela la cita? (desaparecerá del calendario)');
            if (motivo === null) return;
            const formData = new FormData(); formData.append('id_cita', idCita); formData.append('motivo', motivo);
            fetch('eliminar_cita.php', { method: 'POST', body: formData }).then(r => r.json()).then(data => {
                if (data.status === 'success') { cerrarModal(); cargarCitas(sucursalActual, ''); } else { alert('Error: ' + data.message); }
            }).catch(() => alert('Error de red.'));
        }

        function rangoVisible() {
            // Semana: se pide el/los mes(es) que toca la semana, para que "Ver Asistencias" tenga el mes completo.
            let ini, fin;
            if (vistaActual === 'dia') {
                ini = new Date(diaActual.getFullYear(), diaActual.getMonth(), 1);
                fin = new Date(diaActual.getFullYear(), diaActual.getMonth() + 1, 0);
            } else if (vistaActual === 'semana') {
                const sab = new Date(lunesActual); sab.setDate(sab.getDate() + 6);
                ini = new Date(lunesActual.getFullYear(), lunesActual.getMonth(), 1);
                fin = new Date(sab.getFullYear(), sab.getMonth() + 1, 0);
            } else {
                ini = new Date(mesActualDate.getFullYear(), mesActualDate.getMonth(), 1);
                fin = new Date(mesActualDate.getFullYear(), mesActualDate.getMonth() + 1, 0);
            }
            return { desde: formatearFechaISO(ini), hasta: formatearFechaISO(fin) };
        }

        function cargarCitas(sucursal, botonMenuId = '') {
            sucursalActual = sucursal;
            if(botonMenuId !== '') { document.querySelectorAll('.menu-item').forEach(li => li.classList.remove('menu-activo')); document.getElementById(botonMenuId).classList.add('menu-activo'); }
            document.getElementById('titulo-calendario').innerText = sucursal === '' ? 'Agenda de todas las sucursales' : `Agenda de ${sucursal}`;
            cargarCumples();
            const r = rangoVisible();
            const qs = new URLSearchParams({ sucursal, tipo: tipoTratamientoActual, ya_es_paciente: yaPacienteActual, desde: r.desde, hasta: r.hasta });
            Promise.all([
                fetch(`obtener_citas.php?${qs}`).then(res => res.json()),
                fetch(`obtener_config_agenda.php?${new URLSearchParams(r)}`).then(res => res.json()),
            ]).then(([data, cfg]) => {
                if (cfg.status === 'success') configAgenda = cfg;
                actualizarCuadricula();
                if (data.status === 'success') { citasGlobales = data.data; renderizarCitasEnCalendario(citasGlobales); firmaAgenda = null; revisarCambios(); }
                else { alert(data.message || 'No se pudieron cargar las citas.'); }
            }).catch(() => alert('Error de red al cargar las citas.'));
        }

        // Los botones de filtro existían en el HTML pero estas dos funciones faltaban (daban error al hacer clic)
        function filtrarPorTipo(tipo, botonId) {
            tipoTratamientoActual = tipo;
            document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('filtro-activo'));
            document.getElementById(botonId).classList.add('filtro-activo');
            cargarCitas(sucursalActual, '');
        }
        function filtrarPorPaciente(valor, botonId) {
            yaPacienteActual = valor;
            document.querySelectorAll('.filtro-pac-btn').forEach(b => b.classList.remove('filtro-activo'));
            document.getElementById(botonId).classList.add('filtro-activo');
            cargarCitas(sucursalActual, '');
        }

        function renderizarCitasEnCalendario(citas) {
            if (vistaActual === 'dia') { generarVistaDia(); return; }
            document.querySelectorAll('.cita-indicador').forEach(el => el.remove()); document.querySelectorAll('.btn-ver-mas').forEach(el => el.remove());
            const citasPorCelda = {};
            citas.forEach(cita => {
                const horaCorta = cita.hora.substring(0, 5); let idCelda = '';
                if (vistaActual === 'semana') {
                    // Se acomoda en el renglón que le toca según el intervalo (las de fuera de horario, en el primero/último)
                    const g = gridSemana; let m = aMin(horaCorta);
                    m = Math.min(Math.max(m, g.ini), g.fin - 1); m = g.ini + Math.floor((m - g.ini) / g.paso) * g.paso;
                    idCelda = `celda-sem-${cita.fecha}-${aHora(m)}`;
                } else { idCelda = `celda-mes-${cita.fecha}`; }
                if (!citasPorCelda[idCelda]) citasPorCelda[idCelda] = []; citasPorCelda[idCelda].push(cita);
            });

            for (const [idCelda, listaCitas] of Object.entries(citasPorCelda)) {
                const celda = document.getElementById(idCelda); if (!celda) continue;
                const maxVisible = vistaActual === 'mes' ? 2 : 1;
                listaCitas.slice(0, maxVisible).forEach(cita => {
                    const horaCorta = cita.hora.substring(0, 5); const etiquetaCita = document.createElement('div'); let colorClase = 'bg-gray-100 text-gray-800 border-gray-300'; let tagSucursal = '';
                    if (sucursalActual === '') {
                        if(cita.sucursal === 'Del Valle') { colorClase = 'bg-blue-100 text-blue-800 border-blue-300'; tagSucursal = '[DV] '; } else if(cita.sucursal === 'Tepeyac') { colorClase = 'bg-green-100 text-green-800 border-green-300'; tagSucursal = '[TEP] '; } else if(cita.sucursal === 'Balbuena') { colorClase = 'bg-pink-100 text-pink-800 border-pink-300'; tagSucursal = '[BAL] '; } else if(cita.sucursal === 'Pachuca') { colorClase = 'bg-purple-100 text-purple-800 border-purple-300'; tagSucursal = '[PAC] '; } else if(cita.sucursal === 'Puebla') { colorClase = 'bg-orange-100 text-orange-800 border-orange-300'; tagSucursal = '[PUE] '; }
                    } else {
                        if(cita.tipo_tratamiento === 'Sports medicine') colorClase = 'bg-blue-100 text-blue-800 border-blue-300'; else if(cita.tipo_tratamiento === 'Clínica de rodillas') colorClase = 'bg-green-100 text-green-800 border-green-300'; else if(cita.tipo_tratamiento === 'Cámara Hiperbárica') colorClase = 'bg-purple-100 text-purple-800 border-purple-300';
                    }
                    if (cita.estado === 'Express') { colorClase = 'bg-orange-500 text-white border-orange-600 font-bold'; }
                    let iconoAsistencia = ''; let estiloAsistencia = '';
                    if (cita.asistencia === 'Asistió') { iconoAsistencia = '✅ '; estiloAsistencia = 'border-l-4 border-l-green-600'; } else if (cita.asistencia === 'No Asistió') { iconoAsistencia = '❌ '; estiloAsistencia = 'opacity-50 grayscale'; } else if (cita.estado === 'Confirmada') { iconoAsistencia = '👍 '; }
                    etiquetaCita.className = `cita-indicador w-full text-[10px] leading-tight rounded p-1 border cursor-pointer hover:opacity-80 z-20 shadow-sm ${colorClase} ${estiloAsistencia}`;
                    etiquetaCita.innerHTML = `<div class="flex justify-between items-start mb-0.5"><span class="font-bold">${iconoAsistencia}${horaCorta}</span><span class="text-[9px] font-semibold opacity-75">${cita.ya_es_paciente === 'Si' ? '👤 Pac.' : '🆕 Nvo.'}</span></div><span class="truncate block w-full"><strong>${tagSucursal}</strong>${esc(cita.paciente)}</span>`;
                    etiquetaCita.onclick = function(e) { e.stopPropagation(); abrirModalEditar(cita); }; celda.appendChild(etiquetaCita);
                });
                if (listaCitas.length > maxVisible) {
                    const btnVerMas = document.createElement('div'); btnVerMas.className = 'btn-ver-mas w-full text-center mt-1 bg-gray-200 hover:bg-gray-300 text-gray-700 text-[10px] font-bold rounded py-0.5 cursor-pointer shadow-sm'; btnVerMas.innerHTML = `+${listaCitas.length - maxVisible} ver más`;
                    const fechaReferencia = listaCitas[0].fecha; btnVerMas.onclick = function(e) { e.stopPropagation(); abrirModalAgendaDia(fechaReferencia); }; celda.appendChild(btnVerMas);
                }
            }
        }

        function enviarCita(formData, idCita) {
            fetch(idCita ? 'actualizar_cita.php' : 'guardar_cita.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                const msg = document.getElementById('mensaje'); msg.className = 'text-sm font-medium rounded p-2 text-center';
                if (data.status === 'advertencia') {
                    if (confirm(`⚠️ ${data.message}\n\n¿Agendar de todos modos?`)) { formData.set('forzar', '1'); enviarCita(formData, idCita); }
                    else { msg.classList.add('hidden'); }
                    return;
                }
                if (data.status === 'success') { cargarCitas(sucursalActual, ''); mostrarConfirmacionCita(datosDeFormulario(formData), data.message); }
                else { msg.textContent = data.message; msg.classList.add('bg-red-100', 'text-red-700'); }
            }).catch(() => alert('Error de red al guardar la cita.'));
        }
        document.getElementById('formCita').addEventListener('submit', function(e) {
            e.preventDefault(); const formData = new FormData(this); let asistenciaFinal = 'Pendiente';
            if (document.getElementById('checkSiAsistio').checked) asistenciaFinal = 'Asistió'; else if (document.getElementById('checkNoAsistio').checked) asistenciaFinal = 'No Asistió';
            formData.append('asistencia', asistenciaFinal);
            enviarCita(formData, document.getElementById('inputIdCita').value);
        });

        // ===== Vista Día: lista de citas con acciones de un clic =====
        const COLOR_SUC = { 'Del Valle': '#0E7C66', 'Tepeyac': '#3B6FB6', 'Balbuena': '#B4559B', 'Pachuca': '#7A5AC8', 'Puebla': '#D9822B' };
        function generarVistaDia() {
            const f = formatearFechaISO(diaActual);
            const nombre = diaActual.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
            document.getElementById('texto-rango').innerText = nombre.charAt(0).toUpperCase() + nombre.slice(1);
            const citas = citasGlobales.filter(c => c.fecha === f).sort((a, b) => a.hora.localeCompare(b.hora));
            const hd = horarioDia(diaActual.getDay()); const bl = bloqueosDe(f);
            const asis = citas.filter(c => c.asistencia === 'Asistió').length, faltas = citas.filter(c => c.asistencia === 'No Asistió').length;
            let html = `<div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-[#DDE5E2]">
                <p class="text-[#4B5A62]">${hd ? `Abierto de <strong class="tabular">${aHora(hd.ini)}</strong> a <strong class="tabular">${aHora(hd.fin)}</strong>` : '<strong>Cerrado</strong> este día'}</p>
                <p class="text-[#4B5A62] tabular"><strong class="text-tinta">${citas.length}</strong> citas, <span class="text-[#2F7D4F] font-semibold">${asis} asistieron</span>, <span class="text-rosa font-semibold">${faltas} faltaron</span></p></div>`;
            html += bl.map(b => `<div class="mx-5 mt-4 rounded-xl bg-[#FBE9EC] text-[#A3263B] px-4 py-3 font-semibold">🚫 ${esc(textoBloqueo(b))}</div>`).join('');
            if (f === formatearFechaISO(new Date())) {
                const pend = [...cumplesData.manana.map(x => ({ ...x, c: 'mañana' })), ...cumplesData.hoy.map(x => ({ ...x, c: 'hoy' }))].filter(x => !x.felicitado);
                if (pend.length) html += `<button onclick="abrirCumples()" class="mx-5 mt-4 w-[calc(100%-2.5rem)] text-left rounded-xl bg-[#FDF3E3] text-[#8A5A12] px-4 py-3 font-semibold">🎂 ${pend.length === 1 ? esc(pend[0].nombre) + ' cumple años ' + pend[0].c : pend.length + ' pacientes cumplen años hoy o mañana'}. Toca para felicitar.</button>`;
            }
            if (!citas.length) {
                html += `<div class="px-5 py-16 text-center"><p class="text-xl font-bold">No hay citas este día</p><p class="text-[#6B7B83] mt-1">Agenda una con el botón “Nueva cita”.</p></div>`;
            } else {
                html += '<ul class="divide-y divide-[#EDF2F0]">' + citas.map(c => {
                    const estado = c.asistencia === 'Asistió' ? '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#E4F4EA] text-[#2F7D4F]">Asistió</span>'
                                 : c.asistencia === 'No Asistió' ? '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#FBE9EC] text-[#A3263B]">Faltó</span>'
                                 : c.estado === 'Confirmada' ? '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-jade-claro text-jade-oscuro">Confirmada</span>'
                                 : '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#FDF3E3] text-[#8A5A12]">Por llegar</span>';
                    const nuevo = c.ya_es_paciente === 'Si' ? '' : '<span class="text-xs font-semibold text-[#8A5A12]">Primera vez</span>';
                    const pendiente = c.asistencia !== 'Asistió' && c.asistencia !== 'No Asistió';
                    return `<li class="flex flex-wrap md:flex-nowrap items-center gap-4 px-5 py-4 hover:bg-[#F8FAF9]">
                        <div class="w-16 text-2xl font-extrabold tabular">${esc(c.hora.substring(0, 5))}</div>
                        <span class="w-1.5 self-stretch rounded-full" style="background:${COLOR_SUC[c.sucursal] || '#6B7B83'}"></span>
                        <button onclick="abrirModalEditar(citasGlobales.find(x => x.id_cita == ${parseInt(c.id_cita)}))" class="flex-1 min-w-[12rem] text-left">
                            <p class="text-lg font-bold leading-tight">${esc(c.paciente)} ${c.estado === 'Express' ? '<span class="text-xs font-bold text-[#8A5A12]">⚡ sin cita</span>' : ''}</p>
                            <p class="text-sm text-[#6B7B83]">${esc(c.tipo_tratamiento)}${esc(detalleHiper(c))}${sucursalActual ? '' : ', ' + esc(c.sucursal)} ${nuevo}</p>
                        </button>
                        ${estado}
                        <div class="solo-operacion flex gap-2">
                            ${pendiente ? `<button onclick="marcarAsistencia(${parseInt(c.id_cita)}, 'Asistió')" class="px-3 py-2 rounded-lg border-2 border-[#BFE3CD] text-[#2F7D4F] font-bold hover:bg-[#E4F4EA]">✓ Llegó</button>
                            <button onclick="marcarAsistencia(${parseInt(c.id_cita)}, 'No Asistió')" class="px-3 py-2 rounded-lg border-2 border-[#F2C9D0] text-[#A3263B] font-bold hover:bg-[#FBE9EC]">Faltó</button>` : ''}
                            ${c.asistencia !== 'No Asistió' ? `<button onclick="cobrarCita(${parseInt(c.id_cita)})" class="px-3 py-2 rounded-lg bg-jade text-white font-bold hover:bg-jade-oscuro">Cobrar</button>` : ''}
                        </div></li>`;
                }).join('') + '</ul>';
            }
            document.getElementById('cuadricula-calendario').innerHTML = html;
        }
        function marcarAsistencia(id, valor) {
            const fd = new FormData(); fd.append('id_cita', id); fd.append('asistencia', valor);
            fetch('actualizar_asistencia.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                if (d.status === 'success') cargarCitas(sucursalActual, ''); else alert('Error: ' + d.message);
            }).catch(() => alert('Error de red.'));
        }
        function cobrarCita(id) {
            const c = citasGlobales.find(x => x.id_cita == id); if (!c) return;
            if (sucursalActual !== c.sucursal) {
                const li = [...document.querySelectorAll('.menu-item')].find(x => x.dataset.sucursal === c.sucursal);
                cargarCitas(c.sucursal, li ? li.id : '');
            }
            abrirRemisiones();
            document.getElementById('inputFechaRemision').value = c.fecha; cargarDatosRemision();
        }

        // ===== Remisión con el formato impreso de la clínica (media carta, 528 × 816 px) =====
        const LOGO_REMISION = 'img/logo_remision.png';
        function htmlNotaRemision(d) {
            const AZUL = '#1F3B6E', CIELO = '#6EC3E8', PALIDO = '#BFE5F5';
            const e = (v) => esc(v ?? '');
            const [anio, mes, dia] = String(d.fecha || '').split('-');
            const filas = (d.conceptos || []).map(c => {
                const imp = (parseFloat(c.cantidad) || 0) * (parseFloat(c.precio) || 0);
                return `<tr><td style="padding:5px 6px;text-align:center;vertical-align:top">${e(c.cantidad)}</td><td style="padding:5px 8px;vertical-align:top;word-break:break-word">${e(c.descripcion)}${parseFloat(c.cantidad) > 1 ? `<span style="color:#6B7B8C;font-size:9px"> · ${fmtMXN.format(parseFloat(c.precio) || 0)} c/u</span>` : ''}</td><td style="padding:5px 8px;text-align:right;vertical-align:top;white-space:nowrap">${fmtMXN.format(imp)}</td></tr>`;
            }).join('');
            const pill = (txt, x, y, w) => `<div style="position:absolute;left:${x}px;top:${y}px;width:${w}px;height:24px;border:1.5px solid ${AZUL};border-radius:4px;box-shadow:3px 3px 0 ${CIELO};background:#fff;font-size:8.5px;font-weight:600;color:${AZUL};display:flex;align-items:center;justify-content:center;letter-spacing:.3px">${txt}</div>`;
            const lineaObs = (y, txt) => `<div style="position:absolute;left:40px;top:${y}px;width:451px;height:16px;border-bottom:1px solid #C9D3DD;font-size:10px;color:#1E2B33;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${e(txt)}</div>`;
            const obs = String(d.observaciones || '');
            const obsLineas = obs.match(/.{1,86}(\s|$)/g) || [];
            return `<div style="position:relative;width:528px;height:816px;overflow:hidden;background:#fff;font-family:'Montserrat',Arial,sans-serif;color:#1E2B33">
<svg width="528" height="240" viewBox="0 0 528 240" style="position:absolute;left:0;top:0">
  <path d="M0,0 H120 C80,22 40,52 0,92 Z" fill="${AZUL}"/>
  <path d="M120,0 H190 C120,18 60,60 0,128 V92 C40,52 80,22 120,0 Z" fill="${CIELO}"/>
  <path d="M0,150 C110,214 250,214 360,176 C430,152 480,120 528,82 V110 C470,150 410,186 340,206 C230,238 100,228 0,180 Z" fill="${PALIDO}"/>
  <path d="M0,128 C110,196 250,200 360,160 C430,134 480,104 528,70 V96 C470,138 410,170 340,192 C230,222 100,214 0,160 Z" fill="${CIELO}"/>
  <path d="M60,170 C170,212 290,198 380,160 C440,134 490,104 528,86 V112 C480,140 430,172 360,192 C270,218 160,214 60,170 Z" fill="${AZUL}"/>
</svg>
<div style="position:absolute;left:24px;top:36px;width:136px;height:136px;border-radius:50%;background:#fff;border:6px solid ${AZUL};overflow:hidden;box-shadow:0 2px 0 ${CIELO}">
  <div style="position:absolute;left:0;top:12px;width:124px;height:96px;overflow:hidden"><img src="${LOGO_REMISION}" alt="" style="position:absolute;width:172px;max-width:none;height:auto;left:-33px;top:-27px"></div>
</div>
<div style="position:absolute;left:176px;top:30px;width:330px;text-align:right;font-weight:800;font-size:25px;letter-spacing:.4px;color:${AZUL}">CLÍNICA DE RODILLAS</div>
<div style="position:absolute;left:176px;top:60px;width:318px;text-align:right;font-family:'Great Vibes',cursive;font-size:31px;color:${AZUL}">Dr. Díaz Campuzano</div>
<div style="position:absolute;left:374px;top:196px;width:117px;height:82px;border:1.5px solid ${AZUL};border-radius:8px;overflow:hidden;background:#fff">
  <div style="background:${AZUL};color:#fff;font-size:12px;font-weight:600;text-align:center;padding:5px 0;letter-spacing:.8px">REMISIÓN</div>
  <div style="text-align:center;font-weight:700;color:#C0262D;font-size:12px;padding-top:4px">${e(d.folio || '—')}</div>
  <div style="display:flex;justify-content:space-around;padding:3px 4px 0;font-size:11px;font-weight:600;text-align:center">
    <div>${e(dia)}<div style="font-size:6.5px;color:${AZUL};font-weight:500;margin-top:1px">DÍA</div></div>
    <div>${e(mes)}<div style="font-size:6.5px;color:${AZUL};font-weight:500;margin-top:1px">MES</div></div>
    <div>${e(anio)}<div style="font-size:6.5px;color:${AZUL};font-weight:500;margin-top:1px">AÑO</div></div>
  </div>
</div>
<div style="position:absolute;left:40px;top:262px;font-size:7.5px;font-weight:600;color:${AZUL};border:1px solid ${AZUL};padding:1px 4px;background:#fff">NOMBRE:</div>
<div style="position:absolute;left:84px;top:256px;width:268px;height:20px;border-bottom:1.5px solid ${AZUL};font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${e(d.nombre)}</div>
<div style="position:absolute;left:374px;top:284px;width:117px;text-align:center;font-size:8.5px;color:${AZUL}">R.F.C DICM8203021M4</div>
<div style="position:absolute;left:39px;top:304px;width:452px;height:252px;border:1.5px solid ${AZUL};border-radius:8px 8px 0 0;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:10.5px">
    <colgroup><col style="width:72px"><col><col style="width:92px"></colgroup>
    <thead><tr style="background:${AZUL};color:#fff;font-size:11px;font-weight:500;letter-spacing:.4px"><th style="padding:6px 0;font-weight:500">CANTIDAD</th><th style="padding:6px 0;font-weight:500">DESCRIPCIÓN</th><th style="padding:6px 0;font-weight:500">IMPORTE</th></tr></thead>
    <tbody>${filas}</tbody>
  </table>
  <div style="position:absolute;left:72px;top:27px;bottom:0;border-left:1.5px dashed #8EA3BD"></div>
  <div style="position:absolute;right:92px;top:27px;bottom:0;border-left:1.5px dashed #8EA3BD"></div>
</div>
<div style="position:absolute;left:39px;top:556px;width:347px;height:26px;background:${AZUL};border-radius:0 0 0 8px;color:#fff;font-size:12px;font-weight:600;text-align:right;padding:6px 10px 0 0;letter-spacing:.5px">TOTAL:</div>
<div style="position:absolute;left:389px;top:556px;width:100px;height:26px;border:1.5px solid ${AZUL};border-radius:0 0 8px 0;font-size:12px;font-weight:700;padding:5px 8px 0;display:flex;justify-content:space-between"><span>$</span><span>${(parseFloat(d.total) || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>
<div style="position:absolute;left:40px;top:592px;font-size:7.5px;color:${AZUL}">OBSERVACIONES:</div>
${lineaObs(602, obsLineas[0])}${lineaObs(618, obsLineas[1])}${lineaObs(634, obsLineas.slice(2).join(' '))}
${pill('MÉTODO DE PAGO:', 75, 660, 112)}${pill('NOMBRE Y FIRMA:', 334, 660, 112)}
<div style="position:absolute;left:40px;top:694px;width:202px;height:20px;border-bottom:1.5px solid ${AZUL};text-align:center;font-size:11px;font-weight:600">${e(d.metodo)}</div>
<div style="position:absolute;left:288px;top:694px;width:203px;height:20px;border-bottom:1.5px solid ${AZUL};text-align:center;font-size:11px;font-weight:600">${e(d.atendio)}</div>
<div style="position:absolute;left:96px;top:726px;width:340px;font-size:7.6px;font-weight:700;color:${AZUL};line-height:1.45">NOTA:&nbsp; PUEDE SOLICITAR SU FACTURA MÁXIMO EL ÚLTIMO DÍA DEL MES CORRESPONDIENTE.<br>ESTA NOTA FORMA PARTE DE LA FACTURA MENSUAL GLOBAL.</div>
<svg width="528" height="80" viewBox="0 0 528 80" style="position:absolute;left:0;bottom:0">
  <path d="M0,4 C60,30 140,52 240,58 C330,63 420,50 528,16 V80 H0 Z" fill="${PALIDO}"/>
  <path d="M0,30 C80,56 170,70 270,70 C370,70 450,56 528,34 V80 H0 Z" fill="${CIELO}"/>
  <path d="M0,52 C90,72 200,80 300,78 C400,76 470,64 528,52 V80 H0 Z" fill="${AZUL}"/>
</svg>
</div>`;
        }
        /** Genera el PDF (media carta) de una remisión con el formato de la clínica. Devuelve data-URI. */
        async function pdfNotaRemision(d) {
            if (document.fonts) { try { await Promise.all([document.fonts.load("800 25px Montserrat"), document.fonts.load("31px 'Great Vibes'"), document.fonts.load("600 12px Montserrat")]); } catch (_) {} }
            const cont = document.createElement('div');
            cont.style.cssText = 'position:fixed;left:-10000px;top:0';
            cont.innerHTML = htmlNotaRemision(d);
            document.body.appendChild(cont);
            await new Promise(r => { const img = cont.querySelector('img'); if (!img || img.complete) r(); else { img.onload = img.onerror = r; } });
            try {
                return await html2pdf().set({ margin: 0, filename: `Remision_${d.folio}.pdf`, image: { type: 'jpeg', quality: 0.96 }, html2canvas: { scale: 3, useCORS: true, backgroundColor: '#ffffff' }, jsPDF: { unit: 'in', format: [5.5, 8.5], orientation: 'portrait' } }).from(cont.firstElementChild).output('datauristring');
            } finally { cont.remove(); }
        }

        // ===== Sección Remisiones =====
        const USUARIO_NOMBRE = <?= json_encode($usuario['nombre'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let notaCitas = [], notaIdCita = 0;
        function abrirNotas() {
            if (sucursalActual === '') { avisar('Elige primero una sucursal en el menú.'); return; }
            document.getElementById('subNotas').textContent = `Sucursal ${sucursalActual}`;
            nuevaNota(); tabNotas('nueva');
            document.getElementById('modalNotas').classList.remove('hidden');
        }
        /** Citas de la fecha elegida (se vuelve a pedir al abrir, al cambiar la fecha y cuando la agenda cambia). */
        function cargarCitasNota() {
            const fecha = document.getElementById('notaFecha').value; if (!fecha || sucursalActual === '') return;
            return fetch(`obtener_remisiones_dia.php?${new URLSearchParams({ sucursal: sucursalActual, fecha })}`).then(r => r.json()).then(d => {
                notaCitas = d.status === 'success' ? d.data : [];
                const sel = document.getElementById('notaCitaSel');
                const pend = notaCitas.filter(c => !c.id_remision).length;
                sel.innerHTML = `<option value="">${notaCitas.length ? `Sin cita / otro cliente  (${pend} cita${pend === 1 ? '' : 's'} sin remisión)` : 'No hay citas ese día · cliente sin cita'}</option>`
                    + notaCitas.map(c => `<option value="${parseInt(c.id_cita)}">${esc(String(c.hora).slice(0, 5))} · ${esc(c.paciente)} · ${esc(c.tipo_tratamiento)}${c.id_remision ? ' ✓ ' + esc(c.folio) : ''}</option>`).join('');
                sel.value = notaIdCita && notaCitas.some(c => c.id_cita == notaIdCita) ? String(notaIdCita) : '';
                if (sel.value === '' && notaIdCita) { notaIdCita = 0; ligarCitaNota(); }
            }).catch(() => {});
        }
        function elegirCitaNota() {
            const c = notaCitas.find(x => x.id_cita == document.getElementById('notaCitaSel').value);
            if (c && c.id_remision && !confirm(`${c.paciente} ya tiene la remisión ${c.folio}. ¿Hacer otra?`)) { document.getElementById('notaCitaSel').value = ''; return; }
            document.getElementById('notaNombre').value = c ? c.paciente : '';
            document.getElementById('notaTel').value = c && c.telefono ? c.telefono : '';
            ligarCitaNota(); previaNota();
        }
        function cerrarNotas() { document.getElementById('modalNotas').classList.add('hidden'); }
        function tabNotas(t) {
            const nueva = t === 'nueva';
            document.getElementById('panelNotaNueva').classList.toggle('hidden', !nueva);
            document.getElementById('panelNotaHist').classList.toggle('hidden', nueva);
            document.getElementById('tabNotaNueva').className = 'px-3 py-1.5 rounded-md ' + (nueva ? 'bg-white shadow-sm' : 'text-[#4B5A62]');
            document.getElementById('tabNotaHist').className = 'px-3 py-1.5 rounded-md ' + (!nueva ? 'bg-white shadow-sm' : 'text-[#4B5A62]');
            if (!nueva) { const f = document.getElementById('histNotaFecha'); if (!f.value) f.value = formatearFechaISO(new Date()); cargarHistNotas(); }
        }
        function nuevaNota() {
            document.getElementById('formNota').reset();
            document.getElementById('notaFecha').value = formatearFechaISO(new Date());
            document.getElementById('notaAtendio').value = USUARIO_NOMBRE;
            document.getElementById('notaConceptos').innerHTML = '';
            document.getElementById('notaResultado').classList.add('hidden');
            document.getElementById('btnGuardarNota').disabled = false;
            notaIdCita = 0; ligarCitaNota(); agregarConceptoNota(); cargarCitasNota();
        }
        function agregarConceptoNota() {
            const cont = document.getElementById('notaConceptos');
            if (cont.children.length >= 12) { avisar('Máximo 12 conceptos por remisión.'); return; }
            const fila = document.createElement('div');
            fila.className = 'grid grid-cols-[60px_1fr_1fr_28px] sm:grid-cols-[64px_1fr_110px_100px_32px] gap-2 items-center px-3 py-2 concepto-nota';
            fila.innerHTML = `<input type="number" min="1" max="999" value="1" required class="c-cant w-full border border-gray-300 rounded-md p-1.5 text-center" aria-label="Cantidad">`
                + `<input list="notaServicios" maxlength="150" required class="c-desc col-span-4 sm:col-span-1 order-first sm:order-none w-full border border-gray-300 rounded-md p-1.5" placeholder="Descripción del servicio o producto" aria-label="Descripción">`
                + `<input type="number" min="0" step="0.01" required class="c-precio w-full border border-gray-300 rounded-md p-1.5 text-right" placeholder="Precio" aria-label="Precio unitario">`
                + `<span class="c-imp text-right font-semibold">$0.00</span>`
                + `<button type="button" onclick="this.parentElement.remove(); previaNota()" class="text-[#6B7B83] hover:text-red-600 text-xl leading-none" aria-label="Quitar concepto">&times;</button>`;
            cont.appendChild(fila); previaNota();
            if (cont.children.length > 1) fila.querySelector('.c-desc').focus();
        }
        function ligarCitaNota() {
            const nombre = document.getElementById('notaNombre').value.trim().toLowerCase();
            const elegida = notaCitas.find(x => x.id_cita == document.getElementById('notaCitaSel').value);
            const c = elegida && String(elegida.paciente).trim().toLowerCase() === nombre ? elegida : notaCitas.find(x => String(x.paciente).trim().toLowerCase() === nombre);
            notaIdCita = c ? parseInt(c.id_cita) : 0;
            document.getElementById('notaCitaSel').value = c ? String(c.id_cita) : '';
            const p = document.getElementById('notaLigada');
            p.classList.toggle('hidden', !c);
            if (c) { p.textContent = `Ligada a su cita de las ${String(c.hora).slice(0, 5)}. Se marcará como asistió.`; if (!document.getElementById('notaTel').value && c.telefono) document.getElementById('notaTel').value = c.telefono; }
        }
        function datosNota(folio) {
            const conceptos = [...document.querySelectorAll('.concepto-nota')].map(f => ({ cantidad: parseInt(f.querySelector('.c-cant').value) || 0, descripcion: f.querySelector('.c-desc').value.trim(), precio: parseFloat(f.querySelector('.c-precio').value) || 0 }));
            return { folio, fecha: document.getElementById('notaFecha').value, nombre: document.getElementById('notaNombre').value.trim(), telefono: document.getElementById('notaTel').value.trim(),
                     conceptos, total: conceptos.reduce((s, c) => s + c.cantidad * c.precio, 0), observaciones: document.getElementById('notaObs').value.trim(),
                     metodo: document.getElementById('notaMetodo').value, atendio: document.getElementById('notaAtendio').value.trim() };
        }
        function previaNota() {
            document.querySelectorAll('.concepto-nota').forEach(f => { f.querySelector('.c-imp').textContent = fmtMXN.format((parseInt(f.querySelector('.c-cant').value) || 0) * (parseFloat(f.querySelector('.c-precio').value) || 0)); });
            const d = datosNota('Folio al guardar');
            document.getElementById('notaTotal').textContent = fmtMXN.format(d.total);
            document.getElementById('notaPrevia').innerHTML = htmlNotaRemision(d);
        }
        async function guardarNota(ev) {
            ev.preventDefault();
            const d = datosNota('');
            if (d.conceptos.length === 0) { avisar('Agrega al menos un concepto.'); return; }
            if (d.total <= 0) { avisar('El total debe ser mayor a cero.'); return; }
            if (!confirm(`¿Guardar remisión de ${fmtMXN.format(d.total)} a ${d.nombre}?`)) return;
            const btn = document.getElementById('btnGuardarNota'); btn.disabled = true; btn.textContent = 'Guardando…';
            try {
                const fd = new FormData();
                fd.append('sucursal', sucursalActual); fd.append('fecha', d.fecha); fd.append('nombre', d.nombre); fd.append('telefono', d.telefono);
                fd.append('conceptos', JSON.stringify(d.conceptos)); fd.append('observaciones', d.observaciones); fd.append('metodo_pago', d.metodo);
                fd.append('atendio', d.atendio); if (notaIdCita) fd.append('id_cita', notaIdCita);
                const r = await (await fetch('guardar_nota_remision.php', { method: 'POST', body: fd })).json();
                if (r.status !== 'success') throw new Error(r.message || 'No se pudo guardar.');
                btn.textContent = 'Generando PDF…';
                d.folio = r.folio;
                const fd2 = new FormData(); fd2.append('id_remision', r.id_remision); fd2.append('pdf_data', await pdfNotaRemision(d));
                const r2 = await (await fetch('adjuntar_pdf_remision.php', { method: 'POST', body: fd2 })).json();
                const res = document.getElementById('notaResultado');
                res.innerHTML = r2.status === 'success'
                    ? `<p class="font-bold text-[#0A5E4D] mb-2">Remisión ${esc(r.folio)} guardada.</p><div class="flex flex-wrap gap-2"><a href="${esc(r2.pdf)}" target="_blank" class="bg-[#0E7C66] text-white font-semibold rounded-lg px-3 py-2 text-sm">Ver / imprimir PDF</a>${d.telefono ? `<a href="${esc(r2.wa_link)}" target="_blank" class="bg-[#25D366] text-white font-semibold rounded-lg px-3 py-2 text-sm">Enviar por WhatsApp</a>` : ''}<button type="button" onclick="nuevaNota()" class="border border-gray-300 font-semibold rounded-lg px-3 py-2 text-sm">Nueva remisión</button></div>`
                    : `<p class="font-bold text-red-700">El cobro quedó con folio ${esc(r.folio)}, pero el PDF no se guardó: ${esc(r2.message)}. Puedes generarlo desde Historial.</p>`;
                res.classList.remove('hidden');
                btn.textContent = 'Guardada'; previaNota();
                document.getElementById('notaPrevia').innerHTML = htmlNotaRemision(d);
                cargarCitas(sucursalActual, '');
            } catch (e) {
                avisar(e.message || 'Error de conexión.'); btn.disabled = false; btn.textContent = 'Guardar y generar PDF';
            }
        }
        let histNotas = [];
        function cargarHistNotas() {
            const q = document.getElementById('histNotaBuscar').value.trim();
            const cont = document.getElementById('listaHistNotas');
            cont.innerHTML = '<p class="text-[#6B7B83] text-sm">Cargando…</p>';
            const params = { sucursal: sucursalActual }; if (q) params.q = q; else params.fecha = document.getElementById('histNotaFecha').value;
            const pendCont = document.getElementById('pendHistNotas'); pendCont.innerHTML = '';
            if (!q) fetch(`obtener_remisiones_dia.php?${new URLSearchParams({ sucursal: sucursalActual, fecha: params.fecha })}`).then(r => r.json()).then(d => {
                const pend = d.status === 'success' ? d.data.filter(c => !c.id_remision) : [];
                pendCont.innerHTML = pend.length ? `<p class="text-sm font-bold mb-2">Citas sin remisión (${pend.length})</p><div class="space-y-2">` + pend.map(c => `<div class="flex flex-wrap items-center gap-3 border border-dashed border-[#BFE3CD] bg-[#F1FAF7] rounded-xl px-4 py-2"><span class="font-mono text-sm text-[#6B7B83] w-24">${esc(String(c.hora).slice(0, 5))}</span><div class="flex-1 min-w-[160px]"><p class="font-semibold">${esc(c.paciente)}</p><p class="text-xs text-[#6B7B83]">${esc(c.tipo_tratamiento)}</p></div><button onclick="remisionDesdeCita(${parseInt(c.id_cita)}, '${esc(params.fecha)}')" class="bg-[#0E7C66] text-white text-sm font-semibold rounded-lg px-3 py-1.5">Hacer remisión</button></div>`).join('') + '</div><p class="text-sm font-bold mt-4">Remisiones emitidas</p>' : '';
            }).catch(() => {});
            fetch(`obtener_notas_remision.php?${new URLSearchParams(params)}`).then(r => r.json()).then(d => {
                if (d.status !== 'success') { cont.innerHTML = `<p class="text-red-600 text-sm">${esc(d.message)}</p>`; return; }
                histNotas = d.data;
                cont.innerHTML = histNotas.length ? histNotas.map((n, i) => {
                    const canc = n.estado === 'Cancelada';
                    return `<div class="flex flex-wrap items-center gap-3 border border-gray-200 rounded-xl px-4 py-3 ${canc ? 'opacity-60' : ''}">
                        <span class="font-mono text-sm font-bold text-[#0A5E4D] w-24">${esc(n.folio)}</span>
                        <div class="flex-1 min-w-[180px]"><p class="font-semibold">${esc(n.paciente)}${canc ? ' <span class="text-xs text-red-600 font-bold">CANCELADA</span>' : ''}</p><p class="text-xs text-[#6B7B83] truncate">${esc(n.fecha)} · ${esc(n.servicio)}${n.metodo_pago ? ' · ' + esc(n.metodo_pago) : ''}</p></div>
                        <span class="font-extrabold">${fmtMXN.format(parseFloat(n.total) || 0)}</span>
                        ${n.ruta_archivo ? `<a href="ver_archivo.php?f=${encodeURIComponent(n.ruta_archivo)}" target="_blank" class="text-sm font-semibold text-[#0E7C66] hover:underline">Ver / imprimir</a>` : ''}
                        ${!canc ? `<button onclick="regenerarNota(${i}, this)" class="text-sm font-semibold text-[#4B5A62] hover:underline">${n.ruta_archivo ? 'Rehacer PDF' : 'Generar PDF'}</button>` : ''}
                    </div>`;
                }).join('') : '<p class="text-[#6B7B83] text-sm">No hay remisiones con ese filtro.</p>';
            }).catch(() => { cont.innerHTML = '<p class="text-red-600 text-sm">Error de conexión.</p>'; });
        }
        async function remisionDesdeCita(idCita, fecha) {
            nuevaNota(); tabNotas('nueva');
            document.getElementById('notaFecha').value = fecha;
            await cargarCitasNota();
            document.getElementById('notaCitaSel').value = String(idCita); elegirCitaNota();
            document.querySelector('.concepto-nota .c-desc')?.focus();
        }
        /** Vuelve a generar el PDF con el formato de la clínica (útil para cobros anteriores o si falló la subida). */
        async function regenerarNota(i, btn) {
            const n = histNotas[i]; if (!n) return;
            const metodo = n.metodo_pago || (['efectivo', 'credito', 'debito', 'transferencia'].filter(k => parseFloat(n[k]) > 0).map(k => ({ efectivo: 'Efectivo', credito: 'Tarjeta de crédito', debito: 'Tarjeta de débito', transferencia: 'Transferencia' })[k]).join(' + '));
            const conceptos = n.conceptos.length ? n.conceptos : [{ cantidad: 1, descripcion: n.servicio, precio: n.total }];
            btn.disabled = true; btn.textContent = 'Generando…';
            try {
                const pdf = await pdfNotaRemision({ folio: n.folio, fecha: n.fecha, nombre: n.paciente, conceptos, total: n.total, observaciones: n.observaciones || '', metodo, atendio: n.atendio || n.usuario || '' });
                const fd = new FormData(); fd.append('id_remision', n.id_remision); fd.append('pdf_data', pdf);
                const r = await (await fetch('adjuntar_pdf_remision.php', { method: 'POST', body: fd })).json();
                if (r.status !== 'success') throw new Error(r.message);
                window.open(r.pdf, '_blank'); cargarHistNotas();
            } catch (e) { avisar(e.message || 'No se pudo generar el PDF.'); btn.disabled = false; btn.textContent = 'Generar PDF'; }
        }

        // ===== Cámara Hiperbárica =====
        function mostrarBloqueHiper() {
            const esHiper = document.getElementById('inputTipo').value === 'Cámara Hiperbárica';
            document.getElementById('bloqueHiper').classList.toggle('hidden', !esHiper);
            document.getElementById('campoSesiones').classList.toggle('invisible', document.getElementById('inputModalidad').value !== 'Paquete de sesiones');
        }
        function detalleHiper(c) {
            if (c.tipo_tratamiento !== 'Cámara Hiperbárica' || !c.modalidad) return '';
            const m = c.modalidad === 'Paquete de sesiones' ? `Paquete de ${c.sesiones_paquete || '?'} sesiones` : c.modalidad;
            return ` · ${m}${c.motivo_consulta ? ' · ' + c.motivo_consulta : ''}`;
        }

        // ===== Mensaje de confirmación para el paciente (copiar / WhatsApp) =====
        function datosDeFormulario(fd) {
            const g = (k) => (fd instanceof FormData ? fd.get(k) : document.querySelector(`#formCita [name="${k}"]`)?.value) || '';
            return { paciente: g('paciente'), telefono: g('telefono'), fecha: g('fecha'), hora: g('hora'), sucursal: g('sucursal'), tipo: g('tipo'), modalidad: g('modalidad'), sesiones: g('sesiones_paquete') };
        }
        function textoConfirmacion(d) {
            const f = new Date(d.fecha + 'T12:00').toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
            const [hh, mm] = (d.hora || '00:00').split(':').map(Number);
            const hora = `${((hh + 11) % 12) + 1}:${String(mm).padStart(2, '0')} ${hh < 12 ? 'a. m.' : 'p. m.'}`;
            return `Hola ${d.paciente.split(' ')[0]} 👋, tu cita en Clínica de Rodillas quedó agendada:\n\n`
                 + `📅 ${f.charAt(0).toUpperCase() + f.slice(1)}\n⏰ ${hora}\n📍 Sucursal ${d.sucursal}\n🩺 ${d.tipo}${d.tipo === 'Cámara Hiperbárica' && d.modalidad ? ' — ' + d.modalidad + (d.modalidad === 'Paquete de sesiones' && d.sesiones ? ' (' + d.sesiones + ' sesiones)' : '') : ''}\n\n`
                 + `Te pedimos llegar 10 minutos antes. Si necesitas cambiarla, responde a este mensaje. ¡Te esperamos!`;
        }
        // silencioso = copia automática al guardar: si el navegador no lo permite, no molesta (queda el botón Copiar)
        function copiarTexto(t, silencioso = false) {
            const ok = () => avisar('📋 Mensaje copiado. Pégalo en WhatsApp o donde lo necesites.');
            if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(t).then(ok).catch(() => copiarRespaldo(t, ok, silencioso));
            copiarRespaldo(t, ok, silencioso);
        }
        function copiarRespaldo(t, ok, silencioso) {
            const a = document.createElement('textarea'); a.value = t; a.style.position = 'fixed'; a.style.opacity = '0';
            document.body.appendChild(a); a.select();
            let copiado = false; try { copiado = document.execCommand('copy'); } catch (e) {}
            a.remove();
            if (copiado) ok(); else if (!silencioso) prompt('Copia el mensaje:', t);
        }
        function linkWhatsApp(tel, texto) { const n = String(tel || '').replace(/\D/g, ''); return `https://wa.me/${n.length === 10 ? '52' + n : n}?text=${encodeURIComponent(texto)}`; }
        let ultimaConfirmacion = '';
        function mostrarConfirmacionCita(d, titulo) {
            ultimaConfirmacion = textoConfirmacion(d);
            const msg = document.getElementById('mensaje');
            msg.className = 'text-sm rounded-xl p-4 text-left bg-[#E3F2EE] text-[#1E2B33]';
            msg.innerHTML = `<p class="font-bold text-[#0A5E4D] mb-2">✅ ${esc(titulo || 'Cita guardada')}</p>`
                + `<pre class="whitespace-pre-wrap font-sans bg-white rounded-lg p-3 border border-[#DDE5E2] mb-3">${esc(ultimaConfirmacion)}</pre>`
                + `<div class="grid grid-cols-3 gap-2"><button type="button" onclick="copiarTexto(ultimaConfirmacion)" class="bg-[#0E7C66] text-white font-bold py-2 rounded-lg">📋 Copiar</button>`
                + `<a href="${esc(linkWhatsApp(d.telefono, ultimaConfirmacion))}" target="_blank" rel="noopener" class="bg-[#25D366] text-white font-bold py-2 rounded-lg text-center">WhatsApp</a>`
                + `<button type="button" onclick="cerrarModal()" class="bg-white border border-[#DDE5E2] font-bold py-2 rounded-lg">Cerrar</button></div>`;
            copiarTexto(ultimaConfirmacion, true);   // se copia en automático al guardar
            msg.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        function confirmacionDesdeFormulario() { mostrarConfirmacionCita(datosDeFormulario(null), 'Mensaje listo para el paciente'); }
        let toastTimer = null;
        function avisar(t) {
            const el = document.getElementById('toast'); el.textContent = t; el.classList.remove('hidden');
            clearTimeout(toastTimer); toastTimer = setTimeout(() => el.classList.add('hidden'), 3000);
        }

        // ===== Actualización automática (sin recargar la página) =====
        let firmaAgenda = null, revisando = false;
        function revisarCambios() {
            if (revisando || document.visibilityState !== 'visible') return;
            revisando = true;
            fetch(`obtener_cambios.php?${new URLSearchParams({ sucursal: sucursalActual, ...rangoVisible() })}`).then(r => r.json()).then(d => {
                if (d.status !== 'success') return;
                const estado = document.getElementById('estadoVivo');
                estado.textContent = '● Al día · ' + new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
                if (firmaAgenda !== null && d.firma !== firmaAgenda) {
                    cargarCitas(sucursalActual, ''); avisar('🔄 La agenda se actualizó con cambios nuevos');
                    if (!document.getElementById('modalNotas').classList.contains('hidden')) { cargarCitasNota(); if (!document.getElementById('panelNotaHist').classList.contains('hidden')) cargarHistNotas(); }
                }
                firmaAgenda = d.firma;
            }).catch(() => { document.getElementById('estadoVivo').textContent = '○ Sin conexión, reintentando…'; })
              .finally(() => { revisando = false; });
        }
        setInterval(revisarCambios, 20000);
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') revisarCambios(); });

        // ===== Edad automática desde la fecha de nacimiento =====
        function calcularEdad() {
            const v = document.getElementById('inputFechaNac').value; if (!v) return;
            const n = new Date(v + 'T12:00'), h = new Date();
            let e = h.getFullYear() - n.getFullYear(); const m = h.getMonth() - n.getMonth();
            if (m < 0 || (m === 0 && h.getDate() < n.getDate())) e--;
            if (e >= 0 && e < 120) document.getElementById('inputEdad').value = e;
        }

        // ===== Cumpleaños (aviso un día antes) =====
        let cumplesData = { hoy: [], manana: [] };
        function cargarCumples() {
            fetch(`obtener_cumpleanos.php?${new URLSearchParams({ sucursal: sucursalActual })}`).then(r => r.json()).then(d => {
                if (d.status !== 'success') return;
                cumplesData = d;
                const pendientes = [...d.hoy, ...d.manana].filter(x => !x.felicitado).length;
                const b = document.getElementById('badgeCumples'); b.textContent = pendientes; b.classList.toggle('hidden', pendientes === 0);
                if (vistaActual === 'dia') generarVistaDia();
                if (!document.getElementById('modalCumples').classList.contains('hidden')) pintarCumples();
            }).catch(() => {});
        }
        function filaCumple(x, cuando) {
            const msj = `¡Feliz cumpleaños, ${x.nombre.split(' ')[0]}! 🎂 De parte de todo el equipo de Clínica de Rodillas te deseamos un excelente día. ¡Gracias por confiar en nosotros!`;
            const tel = String(x.telefono || '').replace(/\D/g, ''); const wa = `https://wa.me/${tel.length === 10 ? '52' + tel : tel}?text=${encodeURIComponent(msj)}`;
            return `<div class="flex flex-wrap items-center justify-between gap-3 bg-white border rounded-lg p-3"><div><p class="font-bold">${esc(x.nombre)} <span class="font-normal text-gray-500">cumple ${parseInt(x.cumple)} ${cuando}</span></p><p class="text-xs text-gray-500">📞 ${esc(x.telefono)}${x.sucursal ? ' · ' + esc(x.sucursal) : ''}${x.procedencia ? ' · 📍 ' + esc(x.procedencia) : ''}</p></div>`
                + (x.felicitado ? '<span class="text-sm font-bold text-[#2F7D4F]">✓ Felicitado</span>'
                    : `<a href="${esc(wa)}" target="_blank" rel="noopener" onclick="marcarFelicitado(${parseInt(x.id_paciente)}, ${parseInt(x.anio)})" class="solo-operacion px-3 py-2 rounded-lg bg-[#25D366] text-white font-bold text-sm">Felicitar por WhatsApp</a>`) + '</div>';
        }
        function pintarCumples() {
            const vacio = '<p class="text-sm text-gray-500 italic">Nadie.</p>';
            document.getElementById('lista-cumples-manana').innerHTML = cumplesData.manana.map(x => filaCumple(x, 'mañana')).join('') || vacio;
            document.getElementById('lista-cumples-hoy').innerHTML = cumplesData.hoy.map(x => filaCumple(x, 'hoy')).join('') || vacio;
        }
        function abrirCumples() { pintarCumples(); document.getElementById('modalCumples').classList.remove('hidden'); cargarCumples(); }
        function marcarFelicitado(id, anio) {
            const fd = new FormData(); fd.append('id_paciente', id); fd.append('anio', anio);
            fetch('marcar_felicitado.php', { method: 'POST', body: fd }).then(() => setTimeout(cargarCumples, 400));
        }

        // ===== Ausencias =====
        function abrirAusencias() {
            const f = document.getElementById('formAusencia'); f.reset();
            if (sucursalActual) f.sucursal.value = sucursalActual;
            f.fecha_inicio.value = formatearFechaISO(new Date());
            document.getElementById('modalAusencias').classList.remove('hidden'); cargarAusencias();
        }
        function cargarAusencias() {
            const hoy = new Date(); const fin = new Date(); fin.setDate(fin.getDate() + 365);
            const cont = document.getElementById('lista-ausencias'); cont.innerHTML = '<p class="text-gray-400 text-sm">Cargando…</p>';
            fetch(`obtener_config_agenda.php?${new URLSearchParams({ desde: formatearFechaISO(hoy), hasta: formatearFechaISO(fin) })}`).then(r => r.json()).then(d => {
                const lista = (d.bloqueos || []);
                cont.innerHTML = lista.length ? lista.map(b => `<div class="flex justify-between items-center bg-white border border-red-200 rounded-lg p-3 text-sm"><div><strong class="text-red-700">${esc(b.medico || 'Toda la sucursal')}</strong> · ${esc(b.motivo)} <span class="text-gray-500">— ${esc(b.sucursal)}</span><br><span class="text-xs text-gray-600">📅 ${esc(b.fecha_inicio)}${b.fecha_fin !== b.fecha_inicio ? ' al ' + esc(b.fecha_fin) : ''} ${b.hora_inicio ? `⌚ ${esc(b.hora_inicio)}–${esc(b.hora_fin)}` : '(todo el día)'}</span></div><button onclick="eliminarAusencia(${parseInt(b.id_bloqueo)})" class="text-red-600 hover:underline text-xs font-bold">Quitar</button></div>`).join('')
                    : '<p class="text-gray-500 text-sm italic">No hay ausencias registradas.</p>';
            });
        }
        function guardarAusencia(ev) {
            ev.preventDefault();
            fetch('guardar_bloqueo.php', { method: 'POST', body: new FormData(ev.target) }).then(r => r.json()).then(d => {
                alert((d.status === 'success' ? '✅ ' : 'Error: ') + d.message);
                if (d.status === 'success') { ev.target.reset(); cargarAusencias(); cargarCitas(sucursalActual, ''); }
            }).catch(() => alert('Error de red.'));
        }
        function eliminarAusencia(id) {
            if (!confirm('¿Quitar esta ausencia? La agenda volverá a estar disponible.')) return;
            const fd = new FormData(); fd.append('id_bloqueo', id);
            fetch('eliminar_bloqueo.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                if (d.status !== 'success') alert('Error: ' + d.message); cargarAusencias(); cargarCitas(sucursalActual, '');
            });
        }

        // ===== Detalle de corte (consultarlo en el sistema en vez de imprimir) =====
        function abrirDetalleCorte(id) {
            const body = document.getElementById('detalle-corte-body'); body.innerHTML = '<tr><td colspan="9" class="text-center py-6 text-gray-400">Cargando…</td></tr>';
            document.getElementById('modalDetalleCorte').classList.remove('hidden');
            fetch(`obtener_corte_detalle.php?id_corte=${id}`).then(r => r.json()).then(d => {
                if (d.status !== 'success') { body.innerHTML = `<tr><td colspan="9" class="text-center py-6 text-red-500">${esc(d.message)}</td></tr>`; return; }
                const c = d.corte;
                document.getElementById('tituloDetalleCorte').innerText = `Corte #${c.id_corte} — ${c.sucursal} — ${c.fecha}`;
                const dif = c.efectivo_contado !== null ? parseFloat(c.efectivo_contado) - parseFloat(c.efectivo) : null;
                const tarjeta = (x) => `<div class="bg-gray-50 border rounded p-3"><div class="text-xs text-gray-500 uppercase font-bold">${x[0]}</div><div class="font-black text-lg ${x[2] || ''}">${x[1]}</div></div>`;
                document.getElementById('detalle-corte-info').innerHTML = [
                    ['Total del día', fmtMXN.format(c.total_general), 'text-blue-800'], ['Pacientes', esc(c.total_pacientes)],
                    ['Efectivo', fmtMXN.format(c.efectivo)], ['Tarjetas', fmtMXN.format(parseFloat(c.credito) + parseFloat(c.debito))],
                    ['Transferencias', fmtMXN.format(c.transferencia)], ['Efectivo contado', c.efectivo_contado !== null ? fmtMXN.format(c.efectivo_contado) : '—'],
                    ['Diferencia de caja', dif === null ? '—' : fmtMXN.format(dif), dif && Math.abs(dif) > 0.009 ? 'text-red-600' : 'text-green-700'],
                    ['Guardado por', esc(c.hecho_por || '—')],
                ].map(tarjeta).join('');
                body.innerHTML = d.remisiones.length ? d.remisiones.map(r => `<tr><td class="px-3 py-2 font-mono text-xs">${esc(r.folio)}</td><td class="px-3 py-2 font-bold">${esc(r.paciente)}</td><td class="px-3 py-2">${esc(r.servicio)}</td><td class="px-3 py-2 text-right">${fmtMXN.format(r.efectivo)}</td><td class="px-3 py-2 text-right">${fmtMXN.format(parseFloat(r.credito) + parseFloat(r.debito))}</td><td class="px-3 py-2 text-right">${fmtMXN.format(r.transferencia)}</td><td class="px-3 py-2 text-right font-black">${fmtMXN.format(r.total)}</td><td class="px-3 py-2 text-xs text-gray-500">${esc(r.cobro || '—')}</td><td class="px-3 py-2">${r.pdf ? `<a href="${esc(r.pdf)}" target="_blank" class="text-blue-700 underline text-xs">PDF</a>` : '—'}</td></tr>`).join('')
                    : '<tr><td colspan="9" class="text-center py-6 text-gray-500">Sin remisiones vigentes.</td></tr>';
            }).catch(() => { body.innerHTML = '<tr><td colspan="9" class="text-center py-6 text-red-500">Error de red.</td></tr>'; });
        }

        // ===== Imprimir carnet en la clínica =====
        function imprimirCarnet() {
            const t = document.getElementById('carnet-render');
            html2pdf().set({ margin: 0, image: { type: 'jpeg', quality: 1 }, html2canvas: { scale: 3, useCORS: true }, jsPDF: { unit: 'pt', format: [t.offsetWidth, t.offsetHeight], orientation: 'landscape' } })
                .from(t).output('bloburl').then(url => window.open(url, '_blank'));
        }

        // ===== Notas del médico =====
        function cargarNotas() {
            const cont = document.getElementById('lista-notas'); if (!cont) return; cont.innerHTML = '<p class="text-gray-400 text-sm">Cargando…</p>';
            fetch(`obtener_valoraciones.php?id_cita=${encodeURIComponent(document.getElementById('inputIdCita').value)}`).then(r => r.json()).then(d => {
                const lista = d.data || [];
                cont.innerHTML = lista.length ? lista.map(v => `<div class="bg-white border border-blue-100 rounded-lg p-3 text-sm"><div class="flex justify-between text-xs text-gray-500 mb-1"><span>${esc(v.creado_en)} · ${esc(v.autor || '')}</span><span>${v.rodilla ? '🦵 ' + esc(v.rodilla) : ''} ${v.dolor_eva !== null ? '· Dolor ' + esc(v.dolor_eva) + '/10' : ''}</span></div>${v.diagnostico ? `<div class="font-bold text-gray-800">${esc(v.diagnostico)}</div>` : ''}${v.notas ? `<div class="text-gray-700 whitespace-pre-line">${esc(v.notas)}</div>` : ''}</div>`).join('')
                    : '<p class="text-gray-500 text-sm italic">Sin notas todavía.</p>';
            });
        }
        function guardarNota(ev) {
            ev.preventDefault(); const fd = new FormData(ev.target); fd.append('id_cita', document.getElementById('inputIdCita').value);
            fetch('guardar_valoracion.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                if (d.status === 'success') { ev.target.reset(); cargarNotas(); } else { alert('Error: ' + d.message); }
            }).catch(() => alert('Error de red.'));
        }

        document.addEventListener('DOMContentLoaded', () => { sucursalActual = SUCURSAL_FIJA; document.querySelectorAll('.vista-btn').forEach(b => b.setAttribute('aria-pressed', b.dataset.vista === vistaActual ? 'true' : 'false')); lunesActual = lunesDe(diaActual); cargarCitas(SUCURSAL_FIJA, SUCURSAL_FIJA ? 'menu-suc-0' : 'menu-todas'); });
    </script>
<script>if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(() => {})); }</script>
</body>
</html>