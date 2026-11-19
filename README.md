# CRM Clínica de Rodillas

Agenda multi-sucursal, remisiones (recibos PDF por WhatsApp), corte de caja diario y portal del paciente.
PHP 8.1+ · MariaDB 10.4+ / MySQL 8 · Tailwind (CDN) · html2pdf.js

## Instalación / actualización

> Haz **respaldo de la base y de la carpeta** antes de empezar. Prueba primero en una copia.

1. **Cambia la contraseña del usuario MySQL `grupoem8_web`** en cPanel (la anterior estaba escrita en `conexion.php`).
2. Corre `migracion_v2.sql` en phpMyAdmin sobre la base `grupoem8_crm_clinica_rodillas`.
3. Copia `config.example.php` → `config.php` y llena: datos de BD, `base_url` y `app_secret`
   (cadena aleatoria larga; p. ej. `php -r "echo bin2hex(random_bytes(32));"`).
4. Sube todos los archivos (incluye los `.htaccess` de la raíz, `remisiones_guardadas/` y `carnets_guardados/`).
5. **Borra del servidor** `obtener_asistencias_paciente.php` (ya no se usa y exponía datos sin login).
6. Abre `crear_admin.php`, crea el primer administrador y **borra `crear_admin.php`**.
7. Entra en `login_staff.php`. Las demás cuentas se crean en la tabla `usuarios`
   (pendiente: pantalla de administración de usuarios).
8. Comprueba que `https://…/app/remisiones_guardadas/` y `https://…/app/config.php` respondan **403**.

## Deploy automático (GitHub Actions → FTP)

Cada push a `main` sube el código por FTP (`.github/workflows/deploy.yml`) y genera `config.php` en el
servidor a partir de los secretos, así las credenciales nunca están en el repo (que es público).

Secretos en **Settings → Secrets and variables → Actions**:

| Secreto | Ejemplo |
|---|---|
| `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` | datos de la cuenta FTP de cPanel |
| `FTP_SERVER_DIR` | `/public_html/dash/` (con diagonal final; `./` si la cuenta FTP ya apunta a esa carpeta) |
| `DB_NAME`, `DB_USER`, `DB_PASS` | base de datos |
| `APP_SECRET` | cadena aleatoria larga |

Variable opcional `BASE_URL` (pestaña *Variables*): por defecto `https://grupoempresarialdiaz.com.mx/dash`.
Para re-deployar sin cambios: pestaña **Actions → Deploy FTP → Run workflow**.

## Archivos

| Archivo | Para qué |
|---|---|
| `index.php` | CRM (agenda, remisiones, cortes, directorio). Requiere sesión del personal |
| `login_staff.php` / `logout_staff.php` | Acceso del personal |
| `login.php` / `validar_login.php` / `portal_paciente.php` / `portal_datos.php` / `logout.php` | Portal del paciente (folio `CR-00012` + teléfono) |
| `obtener_citas.php`, `guardar_cita.php`, `actualizar_cita.php`, `actualizar_asistencia.php`, `eliminar_cita.php` | Citas |
| `obtener_remisiones_dia.php`, `guardar_remision.php`, `adjuntar_pdf_remision.php`, `cancelar_remision.php` | Cobros |
| `guardar_corte.php`, `obtener_cortes.php` | Corte de caja (calculado en servidor) |
| `guardar_carnet.php`, `ver_archivo.php` | PDFs con link firmado |
| `conexion.php`, `lib.php`, `auth.php`, `citas_lib.php` | Internos (bloqueados por `.htaccess`) |

## Qué cambió en v2

**Seguridad**
- El CRM y todos los endpoints exigen sesión del personal (antes cualquiera con la URL veía y modificaba todo).
- Usuarios con `password_hash`, roles, bloqueo tras 5 intentos, token CSRF en todas las peticiones POST.
- Credenciales fuera del código (`config.php`, ignorado por Git). Los errores SQL ya no se muestran al navegador.
- Escape de HTML en todo lo que viene de la BD (XSS; también arregla nombres con apóstrofo como "D'Angelo").
- PDFs de pacientes ya no son públicos: se sirven con link firmado (WhatsApp) o con sesión.
- Portal del paciente: ya no se puede consultar a otro paciente cambiando el nombre en la URL.

**Errores corregidos**
- Folios de remisión: los genera el servidor, consecutivos por sucursal (`DV-000001`). Antes se calculaban en el
  navegador con la fecha (`2610231`), se repetían entre sucursales, chocaban con el día siguiente a partir del
  paciente 10 y se volvían a emitir duplicados.
- El corte se calcula en el servidor con las remisiones emitidas (antes se mandaba lo tecleado en pantalla).
- El corte salía incompleto si había un filtro de tratamiento/paciente activo en el calendario.
- Botones de filtro "Sports Medicine / Clínica / Cámara" y "Ya es paciente / Primera vez" no funcionaban
  (faltaban las funciones `filtrarPorTipo` y `filtrarPorPaciente`).
- "Guardar asistencias" guardaba el texto `"null"` en edad/asistente.
- El expediente del paciente se buscaba por nombre (dos pacientes homónimos se mezclaban) y su folio cambiaba con
  cada cita nueva. Ahora es `CR-` + id del paciente.
- El portal nunca mostraba el teléfono; los montos se mostraban en USD (ahora MXN).
- El calendario cargaba **todo** el historial en cada clic; ahora solo el rango visible.
- Validación en servidor de fechas, horas, teléfono (10 dígitos), edad, sucursal y tratamiento.
- Cancelar cita pide motivo, no permite cancelar si asistió o si ya tiene cobro. Bitácora de cambios.

## Pendientes recomendados
- Pantalla de usuarios y restringir a recepción a su sucursal (`usuarios.id_sucursal`).
- Catálogo de servicios con precio en la remisión (tabla `servicios` ya existe) y varios conceptos por recibo (`remision_detalle`).
- Recordatorios automáticos de cita por WhatsApp (API de WhatsApp Business).
- Mostrar domingo y horarios después de las 17:00 en la vista semanal (hoy esas citas no aparecen ahí).
- Paquetes de sesiones reales en el carnet (hoy siempre es 10).
- Cambiar Tailwind CDN por un CSS compilado (el CDN no se recomienda en producción).
- Aviso de privacidad (LFPDPPP) y consentimiento en `pacientes.acepto_aviso_privacidad`.
