<?php
require_once __DIR__ . '/auth.php';
$usuario = requiere_staff_pagina();   // sin sesión → login_staff.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Clínica de Rodillas - Agenda</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .filtro-activo { border-color: black !important; color: black !important; }
        .menu-activo { text-decoration: underline; font-weight: bold; text-underline-offset: 4px; }
        
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
<body class="bg-gray-100 min-h-screen">

    <nav class="bg-[#4361ee] text-white pt-4 pb-4 px-8 shadow-md">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-4"><h1 class="text-xl font-bold">Clínica de rodillas</h1><div class="text-sm">👤 <?= htmlspecialchars($usuario['nombre']) ?> · <a href="logout_staff.php" class="underline hover:text-gray-200">Salir</a></div></div>
            <ul class="flex flex-wrap gap-x-8 gap-y-2 text-sm font-medium cursor-pointer">
                <li id="menu-del-valle" onclick="cargarCitas('Del Valle', 'menu-del-valle')" class="menu-item hover:text-gray-200">Del Valle</li>
                <li id="menu-tepeyac" onclick="cargarCitas('Tepeyac', 'menu-tepeyac')" class="menu-item hover:text-gray-200">Tepeyac</li>
                <li id="menu-balbuena" onclick="cargarCitas('Balbuena', 'menu-balbuena')" class="menu-item hover:text-gray-200">Balbuena</li>
                <li id="menu-pachuca" onclick="cargarCitas('Pachuca', 'menu-pachuca')" class="menu-item hover:text-gray-200">Pachuca</li>
                <li id="menu-puebla" onclick="cargarCitas('Puebla', 'menu-puebla')" class="menu-item hover:text-gray-200">Puebla</li>
                <li id="menu-todas" onclick="cargarCitas('', 'menu-todas')" class="menu-item hover:text-gray-200 menu-activo">Ver Todas</li>
            </ul>
        </div>
    </nav>

    <div class="max-w-[1200px] mx-auto mt-6 flex justify-center gap-4 flex-wrap">
        <button id="btn-filtro-todos" onclick="filtrarPorTipo('', 'btn-filtro-todos')" class="filtro-btn px-4 py-2 bg-gray-300 text-gray-800 border-2 border-transparent rounded shadow hover:bg-gray-400 font-bold text-sm transition filtro-activo">Todos</button>
        <button id="btn-filtro-sports" onclick="filtrarPorTipo('Sports medicine', 'btn-filtro-sports')" class="filtro-btn px-4 py-2 bg-blue-100 text-blue-800 border-2 border-blue-200 rounded shadow hover:bg-blue-200 font-bold text-sm transition">Sports Medicine</button>
        <button id="btn-filtro-clinica" onclick="filtrarPorTipo('Clínica de rodillas', 'btn-filtro-clinica')" class="filtro-btn px-4 py-2 bg-green-100 text-green-800 border-2 border-green-200 rounded shadow hover:bg-green-200 font-bold text-sm transition">Clínica de Rodillas</button>
        <button id="btn-filtro-camara" onclick="filtrarPorTipo('Cámara Hiperbárica', 'btn-filtro-camara')" class="filtro-btn px-4 py-2 bg-purple-100 text-purple-800 border-2 border-purple-200 rounded shadow hover:bg-purple-200 font-bold text-sm transition">Cámara Hiperbárica</button>
    </div>

    <div class="max-w-[1200px] mx-auto mt-3 flex justify-center gap-3 items-center flex-wrap">
        <span class="text-sm font-bold text-gray-500 uppercase tracking-wide">Estatus:</span>
        <button id="btn-pac-todos" onclick="filtrarPorPaciente('', 'btn-pac-todos')" class="filtro-pac-btn px-3 py-1 bg-gray-200 text-gray-700 border-2 border-transparent rounded shadow-sm hover:bg-gray-300 font-bold text-xs transition filtro-activo">Ambos</button>
        <button id="btn-pac-si" onclick="filtrarPorPaciente('Si', 'btn-pac-si')" class="filtro-pac-btn px-3 py-1 bg-teal-100 text-teal-800 border-2 border-teal-200 rounded shadow-sm hover:bg-teal-200 font-bold text-xs transition">Ya es paciente</button>
        <button id="btn-pac-no" onclick="filtrarPorPaciente('No', 'btn-pac-no')" class="filtro-pac-btn px-3 py-1 bg-orange-100 text-orange-800 border-2 border-orange-200 rounded shadow-sm hover:bg-orange-200 font-bold text-xs transition">Primera vez (No)</button>
    </div>

    <div class="max-w-[1200px] mx-auto mt-4 p-6 bg-white rounded-lg shadow-lg mb-10">
        <div class="flex justify-center items-center gap-3 mb-6 flex-wrap">
            <h2 id="titulo-calendario" class="text-2xl font-bold text-[#1e3a8a] mr-2">Agenda - General</h2>
            <button id="btn-toggle-vista" onclick="toggleVista()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md shadow-md text-sm font-bold transition-colors">🗓️ Cambiar a Mensual</button>
            <button onclick="abrirModalAsistencias()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md shadow-md text-sm font-bold flex items-center gap-2 transition-colors">📊 Ver Asistencias</button>
            <button onclick="abrirRemisiones()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-md shadow-md text-sm font-bold flex items-center gap-2 transition-colors">🧾 Remisiones</button>
            <button onclick="abrirCitaExpress()" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-md shadow-md text-sm font-bold flex items-center gap-2 transition-colors ml-2">⚡ Cita en el Momento</button>
            <button onclick="abrirDirectorio()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md shadow-md text-sm font-bold flex items-center gap-2 transition-colors ml-2">📋 Directorio</button>
            <button onclick="abrirHistorialCortes()" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-md shadow-md text-sm font-bold flex items-center gap-2 transition-colors ml-2">📈 Historial de Cortes</button>
        </div>
        
        <div class="flex justify-between items-center mb-6">
            <button onclick="cambiarRango(-1)" class="bg-[#2563eb] hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-semibold shadow transition-colors">&lt; Anterior</button>
            <h3 id="texto-rango" class="text-xl font-bold text-[#1e3a8a]">Cargando...</h3>
            <button onclick="cambiarRango(1)" class="bg-[#2563eb] hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-semibold shadow transition-colors">Siguiente &gt;</button>
        </div>
        
        <div id="cuadricula-calendario" class="border border-gray-300 rounded-lg overflow-hidden bg-gray-50 shadow-inner"></div>
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
                        <select id="filtroHC_Sucursal" onchange="cargarHistorialCortes()" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-teal-500 outline-none font-medium">
                            <option value="">Todas las Sucursales</option>
                            <option value="Del Valle">Del Valle</option>
                            <option value="Tepeyac">Tepeyac</option>
                            <option value="Pachuca">Pachuca</option>
                            <option value="Puebla">Puebla</option>
                            <option value="Balbuena">Balbuena</option>
                        </select>
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
                        <label class="block font-bold text-gray-700 text-sm mb-1 uppercase">Fecha del Corte Diario:</label>
                        <input type="date" id="inputFechaRemision" onchange="cargarDatosRemision()" class="border border-gray-400 rounded-md p-2 shadow-sm font-bold text-gray-700 focus:ring-emerald-500">
                    </div>
                    <div class="flex gap-2">
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

                <div class="flex justify-between items-end gap-6 mt-4">
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
                <div><label class="block text-sm font-medium text-gray-700">Paciente</label><input type="text" name="paciente" id="inputPaciente" required class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div>
                <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-medium text-gray-700">Teléfono</label><input type="tel" name="telefono" id="inputTelefono" required class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div><div><label class="block text-sm font-medium text-gray-700">¿Ya es paciente?</label><select name="ya_es_paciente" id="inputYaPaciente" class="mt-1 block w-full border border-gray-300 rounded-md p-2"><option value="No">No</option><option value="Si">Sí</option></select></div></div>
                <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-medium text-gray-700">Tipo (Tratamiento)</label><select name="tipo" id="inputTipo" class="mt-1 block w-full border border-gray-300 rounded-md p-2"><option value="Sports medicine">Sports medicine</option><option value="Clínica de rodillas">Clínica de rodillas</option><option value="Cámara Hiperbárica">Cámara Hiperbárica</option></select></div><div><label class="block text-sm font-medium text-gray-700">Sucursal</label><select name="sucursal" id="inputSucursal" class="mt-1 block w-full border border-gray-300 rounded-md p-2"><option value="Del Valle">Del Valle</option><option value="Tepeyac">Tepeyac</option><option value="Pachuca">Pachuca</option><option value="Puebla">Puebla</option><option value="Balbuena">Balbuena</option></select></div></div>
                <div><label class="block text-sm font-medium text-gray-700">Asistente</label><input type="text" name="asistente" id="inputAsistente" class="mt-1 block w-full border border-gray-300 rounded-md p-2"></div>
                
                <div id="botonesEdicion" class="grid grid-cols-2 gap-2 mt-4 w-full">
                    <button type="button" id="btnPerfil" onclick="abrirPerfilPaciente()" class="hidden bg-blue-600 text-white font-bold py-2 px-1 rounded hover:bg-blue-700 transition duration-200 text-sm flex items-center justify-center gap-1">👤 Perfil</button>
                    <button type="button" id="btnCarnet" onclick="abrirCarnet()" class="hidden bg-purple-500 text-white font-bold py-2 px-1 rounded hover:bg-purple-600 transition duration-200 text-sm flex items-center justify-center gap-1">🪪 Carnet</button>
                    <button type="button" id="btnSubsecuente" onclick="prepararSubsecuente()" class="hidden bg-teal-500 text-white font-bold py-2 px-1 rounded hover:bg-teal-600 transition duration-200 text-sm flex items-center justify-center gap-1">🔁 Subsecuente</button>
                    <button type="button" id="btnEliminar" onclick="eliminarCita()" class="hidden bg-red-500 text-white font-bold py-2 px-1 rounded hover:bg-red-600 transition duration-200 text-sm flex items-center justify-center gap-1">🗑️ Eliminar</button>
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

        let sucursalActual = ''; let tipoTratamientoActual = ''; let yaPacienteActual = ''; let citasGlobales = []; let vistaActual = 'semana';
        const nombresMesesCorto = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const nombresMesesCompletos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const diasNombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const horariosBase = ["09:00", "09:30", "10:00", "10:30", "11:00", "11:30", "12:00", "12:30", "13:00", "13:30", "14:00", "14:30", "15:00", "15:30", "16:00", "16:30", "17:00"];

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
                            html += `<tr class="hover:bg-gray-50 transition border-b border-gray-100"><td class="px-4 py-3 font-mono text-xs text-gray-500">#${c.id_corte}</td><td class="px-4 py-3 font-bold text-gray-800">${esc(c.fecha)}</td><td class="px-4 py-3 text-gray-600 font-medium">${esc(c.sucursal)}</td><td class="px-4 py-3 text-center font-bold text-blue-600">${c.total_pacientes}</td><td class="px-4 py-3 text-right text-gray-600">${fmt.format(c.efectivo)}</td><td class="px-4 py-3 text-right text-gray-600">${fmt.format(parseFloat(c.credito) + parseFloat(c.debito))}</td><td class="px-4 py-3 text-right text-gray-600">${fmt.format(c.transferencia)}</td><td class="px-4 py-3 text-right font-black text-blue-800 print:text-black">${fmt.format(c.total_general)}</td></tr>`;
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
            document.getElementById('perfil-nombre').innerText = "Cargando..."; document.getElementById('modalPerfil').classList.remove('hidden');
            fetch(`obtener_perfil_paciente.php?id_cita=${encodeURIComponent(idCita)}`).then(res => res.json()).then(data => {
                if(data.status === 'success') {
                    const info = data.data; document.getElementById('perfil-nombre').innerText = info.paciente; document.getElementById('perfil-telefono').innerText = "📞 " + (info.telefono || telefono || 'Sin registrar'); document.getElementById('perfil-folio').innerText = info.folio; document.getElementById('perfil-total-citas').innerText = info.total_citas; document.getElementById('perfil-asistencias').innerText = info.asistencias; document.getElementById('perfil-faltas').innerText = info.faltas;
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
                let asistencias = 0; if(data.status === 'success') { asistencias = data.data.asistencias; document.getElementById('inputExpedienteCarnet').value = data.data.folio; } document.getElementById('inputTomadasCarnet').value = asistencias; document.getElementById('inputTotalesCarnet').value = 10; dibujarCarnetVisual(); document.getElementById('modalCarnet').classList.remove('hidden');
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
                            + `<td class="px-2 py-1 text-center no-print"><button onclick="emitirReciboCliente(${parseInt(c.id_cita)})" class="${emitida ? 'bg-gray-400 hover:bg-gray-500' : 'bg-emerald-500 hover:bg-emerald-600'} text-white px-3 py-1.5 rounded shadow text-xs font-bold transition-colors w-full">${emitida ? '➕ Otro cobro' : '📤 Emitir PDF'}</button></td></tr>`;
                    }).join('');
                }
                document.getElementById('tot-pacientes').innerText = remisionesDia.length;
                const folios = remisionesDia.filter(c => c.folio).map(c => c.folio).sort();
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
            const d = document.createElement('div');
            d.innerHTML = `<div style="padding: 40px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; background: #fff;"><div style="text-align: center; border-bottom: 3px solid #1e3a8a; padding-bottom: 15px; margin-bottom: 25px;"><h1 style="color: #1e3a8a; margin: 0; font-size: 26px; text-transform: uppercase; font-weight: 900;">Clínica de Rodillas</h1><p style="margin: 5px 0 0 0; color: #666; font-size: 14px; font-weight: bold; letter-spacing: 1px;">SUCURSAL ${esc(c.sucursal.toUpperCase())}</p></div><div style="display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 14px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;"><div><p style="margin: 4px 0;"><strong>Paciente:</strong> ${esc(c.paciente)}</p><p style="margin: 4px 0;"><strong>Teléfono:</strong> ${esc(c.telefono)}</p><p style="margin: 4px 0; color: #1e3a8a;"><strong>Especialidad:</strong> ${esc(c.tipo_tratamiento)}</p></div><div style="text-align: right;"><p style="margin: 4px 0; color: #e11d48; font-weight: bold; font-size: 16px;"><strong>Folio:</strong> ${esc(folio)}</p><p style="margin: 4px 0;"><strong>Fecha:</strong> ${esc(fecha)}</p></div></div><h3 style="color: #1e3a8a; font-size: 16px; border-bottom: 2px solid #cbd5e1; padding-bottom: 5px; margin-bottom: 15px; text-transform: uppercase;">Detalle del Servicio</h3><div style="padding: 15px; background: #f1f5f9; border-radius: 6px; font-size: 15px; margin-bottom: 30px; border-left: 4px solid #1e3a8a;">${esc(servicio)}</div><div style="width: 280px; margin-left: auto; font-size: 14px;"><div style="display: flex; justify-content: space-between; padding: 5px 0; color: #475569;"><span>Efectivo:</span> <span>${fmtMXN.format(efectivo)}</span></div><div style="display: flex; justify-content: space-between; padding: 5px 0; color: #475569;"><span>Tarjetas:</span> <span>${fmtMXN.format(credito + debito)}</span></div><div style="display: flex; justify-content: space-between; padding: 5px 0; color: #475569; border-bottom: 1px solid #cbd5e1; padding-bottom: 10px; margin-bottom: 10px;"><span>Transferencia:</span> <span>${fmtMXN.format(transf)}</span></div><div style="display: flex; justify-content: space-between; align-items: center;"><span style="font-weight: bold; font-size: 16px; color: #1e293b;">TOTAL PAGADO:</span> <span style="font-weight: 900; font-size: 22px; color: #1e3a8a;">${fmtMXN.format(total)}</span></div></div><div style="margin-top: 60px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 15px;"><p style="font-size: 12px; color: #64748b; font-style: italic;">Este documento es un comprobante de pago interno válido en nuestras instalaciones.</p></div></div>`;
            const opt = { margin: 0, filename: `Remision_${folio}.pdf`, image: { type: 'jpeg', quality: 0.95 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' } };
            return html2pdf().set(opt).from(d).output('datauristring');
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
                    else if (confirm(`✅ Recibo guardado (Folio: ${data.folio}).\n¿Enviarlo por WhatsApp al paciente?`)) { window.open(d2.wa_link, '_blank'); }
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
        function toggleVista() { vistaActual = vistaActual === 'semana' ? 'mes' : 'semana'; document.getElementById('btn-toggle-vista').innerHTML = vistaActual === 'semana' ? '🗓️ Cambiar a Mensual' : '📅 Cambiar a Semanal'; actualizarCuadricula(); cargarCitas(sucursalActual, ''); }
        function cambiarRango(direccion) { if (vistaActual === 'semana') { lunesActual.setDate(lunesActual.getDate() + (direccion * 7)); } else { mesActualDate.setMonth(mesActualDate.getMonth() + direccion); } actualizarCuadricula(); cargarCitas(sucursalActual, ''); }
        function actualizarCuadricula() { if (vistaActual === 'semana') generarCuadriculaSemanal(); else generarCuadriculaMensual(); }

        function generarCuadriculaMensual() {
            const mes = mesActualDate.getMonth(); const anio = mesActualDate.getFullYear(); document.getElementById('texto-rango').innerText = `${nombresMesesCompletos[mes]} ${anio}`;
            const primerDiaSemana = new Date(anio, mes, 1).getDay(); const diasEnMes = new Date(anio, mes + 1, 0).getDate();
            let html = '<div class="grid grid-cols-7 gap-2 border-none bg-transparent shadow-none p-2">';
            ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'].forEach(d => html += `<div class="bg-[#007bff] text-white text-center py-2 rounded font-semibold text-sm">${d}</div>`);
            for (let i = 0; i < primerDiaSemana; i++) { html += `<div class="border rounded h-32 bg-gray-50 opacity-50"></div>`; }
            for (let i = 1; i <= diasEnMes; i++) {
                const fechaStr = `${anio}-${(mes + 1).toString().padStart(2, '0')}-${i.toString().padStart(2, '0')}`;
                html += `<div class="border border-gray-300 rounded h-32 flex flex-col bg-white hover:border-blue-400 transition-colors overflow-hidden shadow-sm"><div class="bg-gray-50 border-b border-gray-200 px-2 py-1 flex justify-between items-center"><span class="font-bold text-gray-700 text-sm">${i}</span><button onclick="abrirModalNueva('${fechaStr}', '09:00')" class="bg-blue-100 text-blue-700 hover:bg-blue-600 hover:text-white rounded w-6 h-6 flex items-center justify-center font-bold text-lg leading-none transition-colors" title="Agendar Cita">+</button></div><div id="celda-mes-${fechaStr}" class="flex-1 overflow-y-auto p-1 space-y-1 bg-white"></div></div>`;
            }
            html += '</div>'; document.getElementById('cuadricula-calendario').innerHTML = html;
        }

        function generarCuadriculaSemanal() {
            const fechasSemana = []; for (let i = 0; i < 6; i++) { let d = new Date(lunesActual); d.setDate(lunesActual.getDate() + i); fechasSemana.push(d); }
            document.getElementById('texto-rango').innerText = `Semana: ${fechasSemana[0].getDate()} ${nombresMesesCorto[fechasSemana[0].getMonth()]} al ${fechasSemana[5].getDate()} ${nombresMesesCorto[fechasSemana[5].getMonth()]} ${fechasSemana[0].getFullYear()}`;
            let html = '<div class="grid grid-cols-7 divide-x divide-y border-b border-gray-300"><div class="bg-[#007bff] text-white font-bold p-2 flex items-center justify-center text-sm">Hora</div>';
            fechasSemana.forEach((fecha, index) => { html += `<div class="bg-[#007bff] text-white p-2 text-center flex flex-col justify-center"><span class="font-bold text-sm">${diasNombres[index]}</span><span class="text-xs text-blue-100">${fecha.getDate()} ${nombresMesesCorto[fecha.getMonth()]}</span></div>`; }); html += '</div>';
            horariosBase.forEach(hora => {
                html += `<div class="grid grid-cols-7 divide-x divide-y border-b border-gray-200"><div class="bg-gray-100 text-gray-700 font-bold p-1 flex items-center justify-center text-xs w-full">${hora}</div>`;
                fechasSemana.forEach(fecha => {
                    const fechaStr = formatearFechaISO(fecha);
                    html += `<div id="celda-sem-${fechaStr}-${hora}" class="bg-white min-h-[4rem] p-1 cursor-pointer hover:bg-blue-50 transition-colors flex flex-col gap-1 relative group" onclick="abrirModalNueva('${fechaStr}', '${hora}')"><div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 bg-blue-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-[10px] font-bold z-10 transition-opacity" title="Agregar">+</div></div>`;
                });
                html += '</div>';
            });
            document.getElementById('cuadricula-calendario').innerHTML = html;
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
            const fechasVisiblesStr = []; let fechaReferencia = vistaActual === 'semana' ? lunesActual : mesActualDate;
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
            if(sucursalActual !== '') document.getElementById('inputSucursal').value = sucursalActual; if(tipoTratamientoActual !== '') document.getElementById('inputTipo').value = tipoTratamientoActual;
            document.getElementById('botonesEdicion').classList.add('hidden'); document.getElementById('btnGuardar').classList.remove('w-full'); document.getElementById('btnGuardar').classList.add('w-full'); document.getElementById('btnGuardar').innerText = "Guardar Cita"; document.getElementById('mensaje').classList.add('hidden');
        }

        function abrirModalEditar(cita) {
            document.getElementById('modalCita').classList.remove('hidden'); document.getElementById('tituloModal').innerText = "Editar Cita"; document.getElementById('checkSiAsistio').checked = false; document.getElementById('checkNoAsistio').checked = false;
            if (cita.asistencia === 'Asistió') document.getElementById('checkSiAsistio').checked = true; else if (cita.asistencia === 'No Asistió') document.getElementById('checkNoAsistio').checked = true;
            document.getElementById('inputIdCita').value = cita.id_cita; document.getElementById('inputFecha').value = cita.fecha; document.getElementById('inputHora').value = cita.hora.substring(0, 5); document.getElementById('inputEstado').value = cita.estado; document.getElementById('inputPaciente').value = cita.paciente; document.getElementById('inputTelefono').value = cita.telefono; document.getElementById('inputEdad').value = cita.edad; document.getElementById('inputYaPaciente').value = cita.ya_es_paciente; document.getElementById('inputTipo').value = cita.tipo_tratamiento; document.getElementById('inputAsistente').value = cita.asistente; document.getElementById('inputSucursal').value = cita.sucursal;
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
            if (vistaActual === 'semana') {
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
            document.getElementById('titulo-calendario').innerText = `Agenda: ${sucursal === '' ? 'Todas las Sucursales' : sucursal}`;
            const r = rangoVisible();
            const qs = new URLSearchParams({ sucursal, tipo: tipoTratamientoActual, ya_es_paciente: yaPacienteActual, desde: r.desde, hasta: r.hasta });
            fetch(`obtener_citas.php?${qs}`).then(res => res.json()).then(data => {
                if (data.status === 'success') { citasGlobales = data.data; renderizarCitasEnCalendario(citasGlobales); }
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
            document.querySelectorAll('.cita-indicador').forEach(el => el.remove()); document.querySelectorAll('.btn-ver-mas').forEach(el => el.remove());
            const citasPorCelda = {};
            citas.forEach(cita => {
                const horaCorta = cita.hora.substring(0, 5); let idCelda = '';
                if (vistaActual === 'semana') { const partes = horaCorta.split(':'); idCelda = `celda-sem-${cita.fecha}-${partes[0]}:${parseInt(partes[1]) >= 30 ? "30" : "00"}`; } else { idCelda = `celda-mes-${cita.fecha}`; }
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

        document.getElementById('formCita').addEventListener('submit', function(e) {
            e.preventDefault(); const formData = new FormData(this); let asistenciaFinal = 'Pendiente';
            if (document.getElementById('checkSiAsistio').checked) asistenciaFinal = 'Asistió'; else if (document.getElementById('checkNoAsistio').checked) asistenciaFinal = 'No Asistió';
            formData.append('asistencia', asistenciaFinal); const idCita = document.getElementById('inputIdCita').value;
            fetch(idCita ? 'actualizar_cita.php' : 'guardar_cita.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                const msg = document.getElementById('mensaje'); msg.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700', 'bg-blue-100', 'text-blue-800', 'bg-orange-100', 'text-orange-800');
                if (data.status === 'success') { msg.textContent = data.message; msg.classList.add('bg-green-100', 'text-green-700'); cargarCitas(sucursalActual, ''); setTimeout(() => cerrarModal(), 1500); } 
                else { msg.textContent = data.message; msg.classList.add('bg-red-100', 'text-red-700'); }
            });
        });

        document.addEventListener('DOMContentLoaded', () => { actualizarCuadricula(); cargarCitas('', 'menu-todas'); });
    </script>
</body>
</html>