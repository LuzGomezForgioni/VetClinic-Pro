<?php

// ============================================================
// CONFIGURACIÓN DE CORREO - VETCLINIC PRO
// ============================================================
//
// En desarrollo local (XAMPP), las credenciales deben guardarse en:
// config/mail_config.local.php
//
// Ese archivo NO se sube a Git.
//
// En un servidor publicado también se pueden usar variables de entorno:
// VETCLINIC_SMTP_HOST
// VETCLINIC_SMTP_PORT
// VETCLINIC_SMTP_USUARIO
// VETCLINIC_SMTP_PASSWORD

$config = [
    'smtp_host' => getenv('VETCLINIC_SMTP_HOST') ?: 'smtp.gmail.com',
    'smtp_port' => (int) (getenv('VETCLINIC_SMTP_PORT') ?: 465),
    'smtp_usuario' => getenv('VETCLINIC_SMTP_USUARIO') ?: '',
    'smtp_password' => getenv('VETCLINIC_SMTP_PASSWORD') ?: '',
    'nombre_remitente' => 'VetClinic Pro',
];

$rutaLocal = __DIR__ . '/mail_config.local.php';

if (file_exists($rutaLocal)) {
    $configLocal = require $rutaLocal;

    if (is_array($configLocal)) {
        $config = array_merge($config, $configLocal);
    }
}

return $config;
