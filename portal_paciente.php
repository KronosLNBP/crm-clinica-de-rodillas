<?php
require_once __DIR__ . '/auth.php';

// Validar que el paciente haya iniciado sesión, si no, lo regresa al login
$pac = paciente_actual();
if (!$pac) {
    header("Location: login.php");
    exit;
}
header('X-Frame-Options: DENY');

$nombre_paciente = $pac['nombre'];
$folio_paciente = $pac['folio'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Expediente - Clínica de Rodillas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Navegación del Portal -->
    <nav class="bg-[#1e3a8a] text-white pt-4 pb-4 px-8 shadow-md">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold flex items-center gap-2">
                🗂️ Mi Expediente Clínico
            </h1>
            <button onclick="cerrarSesion()" class="text-sm font-bold bg-blue-700 hover:bg-blue-800 px-4 py-2 rounded-lg transition-colors shadow">
                Cerrar Sesión 🚪
            </button>
        </div>
    </nav>

    <!-- Contenedor Principal -->
    <div class="max-w-4xl mx-auto mt-8 px-4 pb-12">
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            
            <div class="p-8">
                <!-- Tarjeta de Identificación -->
                <div class="border border-gray-200 rounded-lg p-6 mb-8 flex flex-col sm:flex-row justify-between items-center bg-gray-50 shadow-sm relative overflow-hidden">
                    <div class="absolute right-0 top-0 w-32 h-32 bg-blue-100 rounded-full mix-blend-multiply filter blur-2xl opacity-50 transform translate-x-10 -translate-y-10"></div>
                    <div class="z-10 text-center sm:text-left mb-4 sm:mb-0">
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">Paciente</p>
                        <h2 class="text-3xl font-black text-gray-800"><?php echo htmlspecialchars($nombre_paciente); ?></h2>
                        <p id="perfil-telefono" class="text-gray-600 font-medium mt-1">📞 Cargando...</p>
                    </div>
                    <div class="z-10 text-center sm:text-right">
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">Folio / Expediente</p>
                        <div class="bg-blue-100 text-blue-800 font-mono font-bold px-5 py-2 rounded-lg text-xl border border-blue-200 inline-block shadow-sm">
                            <?php echo htmlspecialchars($folio_paciente); ?>
                        </div>
                    </div>
                </div>

                <!-- Métricas -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8 text-center">
                    <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition">
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">Total Citas</p>
                        <p id="perfil-total-citas" class="text-4xl font-black text-blue-600 mt-2">0</p>
                    </div>
                    <div class="bg-white p-5 rounded-lg shadow-sm border border-green-200 hover:shadow-md transition">
                        <p class="text-xs text-green-600 font-bold uppercase tracking-wider">Asistencias</p>
                        <p id="perfil-asistencias" class="text-4xl font-black text-green-600 mt-2">0</p>
                    </div>
                    <div class="bg-white p-5 rounded-lg shadow-sm border border-red-200 hover:shadow-md transition">
                        <p class="text-xs text-red-600 font-bold uppercase tracking-wider">Faltas</p>
                        <p id="perfil-faltas" class="text-4xl font-black text-red-600 mt-2">0</p>
                    </div>
                    <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-5 rounded-lg shadow-sm border border-yellow-300 transform md:scale-105">
                        <p class="text-xs text-yellow-700 font-bold uppercase tracking-wider">Inversión Total</p>
                        <p id="perfil-inversion" class="text-2xl font-black text-yellow-800 mt-3">$0.00</p>
                    </div>
                </div>

                <!-- Botón de Descarga Carnet -->
                <div class="mb-8 flex justify-end">
                    <button onclick="descargarMiCarnet()" id="btn-descargar-carnet" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition-colors flex items-center gap-2">
                        🪪 Descargar Mi Carnet Virtual
                    </button>
                </div>

                <!-- Historial Clínico -->
                <h3 class="font-bold text-gray-700 border-b-2 border-gray-100 pb-2 mb-4 text-sm uppercase tracking-wider">Historial de Tratamiento</h3>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-700 min-w-[600px]">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-5 py-4">Fecha y Hora</th>
                                    <th class="px-5 py-4">Tratamiento</th>
                                    <th class="px-5 py-4">Sucursal</th>
                                    <th class="px-5 py-4 text-center">Asistencia</th>
                                </tr>
                            </thead>
                            <tbody id="perfil-historial-body" class="divide-y divide-gray-100">
                                <tr><td colspan="4" class="text-center py-8 text-gray-400 font-medium">Cargando historial...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ZONA INVISIBLE PARA RENDERIZAR EL CARNET EN PDF -->
    <div style="position: absolute; left: -9999px; top: -9999px;">
        <div id="carnet-render" style="width: 600px; background: white; border: 1px solid #ccc; border-radius: 12px; overflow: hidden;">
            <div style="background: linear-gradient(90deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="margin: 0; font-size: 20px; font-weight: 900; letter-spacing: 1px;">CLÍNICA DE RODILLAS</h2>
                    <p style="margin: 2px 0 0 0; font-size: 11px; opacity: 0.9; letter-spacing: 1px;">CARNET DE REHABILITACIÓN FÍSICA</p>
                </div>
                <div style="text-align: right;">
                    <p style="margin: 0; font-size: 10px; opacity: 0.8; text-transform: uppercase;">Expediente</p>
                    <p style="margin: 0; font-size: 16px; font-weight: bold; font-family: monospace;"><?php echo htmlspecialchars($folio_paciente); ?></p>
                </div>
            </div>
            <div style="padding: 20px; background-color: #f8fafc; border-bottom: 2px dashed #cbd5e1;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <tr>
                        <td style="padding: 4px 0; color: #64748b; width: 100px; font-weight: bold;">Paciente:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #0f172a; font-size: 15px;"><?php echo htmlspecialchars($nombre_paciente); ?></td>
                    </tr>
                </table>
            </div>
            <div style="padding: 25px 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: #1e3a8a; font-size: 14px; font-weight: bold; text-transform: uppercase;">Control de Sesiones</h3>
                    <p style="margin: 0; font-size: 12px; font-weight: bold; color: #64748b; background: #e2e8f0; padding: 3px 8px; border-radius: 10px;">
                        Avance: <span id="txt-carnet-avance" style="color: #1e3a8a;">0 / 10</span>
                    </p>
                </div>
                <div id="carnet-grid" style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: flex-start;">
                    <!-- Círculos inyectados -->
                </div>
            </div>
            <div style="background-color: #1e293b; color: #94a3b8; padding: 10px 20px; font-size: 10px; text-align: center;">
                Elaborado por Clínica de Rodillas | Presente este carnet en cada sesión.
            </div>
        </div>
    </div>

    <script>
        const nombrePaciente = <?php echo json_encode($nombre_paciente, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
        function esc(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
        let asistenciasGlobal = 0;

        document.addEventListener('DOMContentLoaded', () => {
            // Hacemos la petición al mismo archivo que usa el CRM para buscar el perfil
            fetch('portal_datos.php', { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    const info = data.data;
                    
                    // Llenar métricas superiores
                    document.getElementById('perfil-total-citas').innerText = info.total_citas;
                    document.getElementById('perfil-asistencias').innerText = info.asistencias;
                    document.getElementById('perfil-faltas').innerText = info.faltas;
                    
                    const usdFormatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
                    document.getElementById('perfil-inversion').innerText = usdFormatter.format(info.inversion);

                    asistenciasGlobal = info.asistencias; // Guardar para el carnet
                    document.getElementById('perfil-telefono').innerText = "📞 " + (info.telefono || '');
                    prepararCarnetOculto(info.asistencias);

                    // Llenar tabla de historial
                    let htmlTabla = '';
                    if(info.historial.length === 0) {
                        htmlTabla = '<tr><td colspan="4" class="text-center py-6 text-gray-500 font-medium">Aún no tienes citas registradas.</td></tr>';
                    } else {

                        info.historial.forEach(c => {
                            let badge = c.asistencia === 'Asistió' ? '<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold border border-green-200">✅ Asistió</span>' : 
                                       (c.asistencia === 'No Asistió' ? '<span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-xs font-bold border border-red-200">❌ Faltó</span>' : 
                                       '<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold border border-yellow-200">⏳ Pendiente</span>');
                            
                            htmlTabla += `
                                <tr class="hover:bg-gray-50 transition border-b border-gray-100">
                                    <td class="px-5 py-4 font-bold text-gray-800">${esc(c.fecha)} <span class="text-gray-400 font-normal ml-2 text-xs">${esc(c.hora.substring(0,5))}</span></td>
                                    <td class="px-5 py-4 text-gray-600 font-medium">${esc(c.tipo_tratamiento)}</td>
                                    <td class="px-5 py-4 text-gray-500">${esc(c.sucursal)}</td>
                                    <td class="px-5 py-4 text-center">${badge}</td>
                                </tr>
                            `;
                        });
                    }
                    document.getElementById('perfil-historial-body').innerHTML = htmlTabla;
                }
            })
            .catch(err => {
                document.getElementById('perfil-historial-body').innerHTML = '<tr><td colspan="4" class="text-center py-6 text-red-500 font-medium">Error al cargar la información.</td></tr>';
            });
        });

        // Prepara el HTML invisible que html2pdf convertirá
        function prepararCarnetOculto(tomadas) {
            const totales = 10; // Paquete base
            document.getElementById('txt-carnet-avance').innerText = `${tomadas} / ${totales}`;
            
            let html = '';
            for(let i=1; i<=totales; i++) {
                if(i <= tomadas) {
                    html += `<div style="width: 45px; height: 45px; border-radius: 50%; background-color: #10b981; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; border: 3px solid #d1fae5; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><svg style="width:24px; height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg></div>`;
                } else {
                    html += `<div style="width: 45px; height: 45px; border-radius: 50%; background-color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #94a3b8; border: 2px dashed #cbd5e1; font-size: 16px;">${i}</div>`;
                }
            }
            document.getElementById('carnet-grid').innerHTML = html;
        }

        // Convierte el HTML invisible en PDF y lo descarga
        function descargarMiCarnet() {
            const btn = document.getElementById('btn-descargar-carnet');
            const txtOrig = btn.innerHTML;
            btn.innerHTML = "⏳ Procesando PDF...";
            btn.disabled = true;

            const target = document.getElementById('carnet-render');
            
            const opt = {
                margin:       0,
                filename:     `Mi_Carnet_${nombrePaciente.replace(/\s+/g, '_')}.pdf`,
                image:        { type: 'jpeg', quality: 1 },
                html2canvas:  { scale: 3, useCORS: true }, 
                jsPDF:        { unit: 'pt', format: [600, 350], orientation: 'landscape' }
            };

            html2pdf().set(opt).from(target).save().then(() => {
                btn.innerHTML = txtOrig;
                btn.disabled = false;
            });
        }

        // Función para destruir la sesión y salir
        function cerrarSesion() {
            if(confirm('¿Seguro que deseas salir del portal?')) {
                // Creamos una petición para destruir la sesión en PHP
                fetch('logout.php').then(() => {
                    window.location.href = 'login.php';
                });
            }
        }
    </script>
</body>
</html>