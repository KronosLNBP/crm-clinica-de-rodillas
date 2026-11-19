<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Paciente - Clínica de Rodillas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen relative overflow-hidden">

    <!-- Fondo decorativo -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-emerald-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>

    <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-2xl z-10 border border-gray-100">
        
        <div class="text-center mb-8">
            <div class="bg-[#1e3a8a] text-white w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-black text-gray-800">Clínica de Rodillas</h1>
            <p class="text-sm text-gray-500 mt-1">Portal del Paciente</p>
        </div>

        <form id="formLogin" class="space-y-6">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Folio / Expediente</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-400">🗂️</span>
                    </div>
                    <input type="text" id="inputFolio" required placeholder="Ej. CR-00012" class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase font-mono transition shadow-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Teléfono (Contraseña)</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-400">📞</span>
                    </div>
                    <input type="tel" id="inputTelefono" required placeholder="10 dígitos" class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition shadow-sm">
                </div>
            </div>

            <div id="mensajeError" class="hidden bg-red-50 text-red-600 p-3 rounded-lg text-sm font-medium border border-red-200 text-center">
                Folio o teléfono incorrectos. Verifica tus datos.
            </div>

            <button type="submit" id="btnLogin" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-bold text-white bg-[#1e3a8a] hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                Ingresar a mi Expediente
            </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-8">
            Si no conoces tu folio, solicítalo en recepción.
        </p>
    </div>

    <script>
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btnLogin');
            const msgError = document.getElementById('mensajeError');
            const folio = document.getElementById('inputFolio').value.toUpperCase().trim();
            const telefono = document.getElementById('inputTelefono').value.trim();

            btn.innerHTML = "Validando...";
            btn.disabled = true;
            msgError.classList.add('hidden');

            const formData = new FormData();
            formData.append('folio', folio);
            formData.append('telefono', telefono);

            fetch('validar_login.php', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    btn.innerHTML = "¡Acceso Concedido!";
                    btn.classList.replace('bg-[#1e3a8a]', 'bg-green-500');
                    // Redirigir al portal del paciente en 1 segundo
                    setTimeout(() => {
                        window.location.href = 'portal_paciente.php';
                    }, 1000);
                } else {
                    msgError.textContent = data.message;
                    msgError.classList.remove('hidden');
                    btn.innerHTML = "Ingresar a mi Expediente";
                    btn.disabled = false;
                }
            })
            .catch(error => {
                msgError.textContent = "Error de conexión con el servidor.";
                msgError.classList.remove('hidden');
                btn.innerHTML = "Ingresar a mi Expediente";
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>