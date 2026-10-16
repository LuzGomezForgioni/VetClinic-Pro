<?php

session_start();

include("config/conexion.php");

function conexionSeguraLogout(): bool
{
    return
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

$cookie = $_COOKIE['vetclinic_recordarme'] ?? '';

if ($cookie !== '' && str_contains($cookie, ':')) {

    [$selector] = explode(':', $cookie, 2);

    if (preg_match('/^[a-f0-9]{16}$/', $selector)) {

        $stmt = $conn->prepare("
            DELETE FROM sesiones_recordadas
            WHERE selector = ?
        ");

        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $stmt->close();
    }
}

setcookie(
    'vetclinic_recordarme',
    '',
    [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => conexionSeguraLogout(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]
);

$_SESSION = [];

if (ini_get('session.use_cookies')) {

    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros['path'],
        $parametros['domain'],
        $parametros['secure'],
        $parametros['httponly']
    );
}

session_destroy();

header('Location: index.php');
exit();
