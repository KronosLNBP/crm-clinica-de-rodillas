<?php
// Copia este archivo como config.php y llena tus datos.
// config.php NO se sube a GitHub (está en .gitignore) y .htaccess bloquea abrirlo por URL.
return [
    'db_host'  => 'localhost',
    'db_name'  => 'grupoem8_crm_clinica_rodillas',
    'db_user'  => 'grupoem8_web',
    'db_pass'  => 'CAMBIA_ESTA_CONTRASEÑA',

    // URL pública de la app, sin diagonal al final (se usa en los links de WhatsApp)
    'base_url' => 'https://grupoempresarialdiaz.com.mx/app',

    // Llave para firmar los links de PDFs que se mandan al paciente.
    // Genera una con:  php -r "echo bin2hex(random_bytes(32));"
    'app_secret' => 'PON_AQUI_UNA_CADENA_LARGA_Y_ALEATORIA',
];
