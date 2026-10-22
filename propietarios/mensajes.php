<?php
session_start();
if (empty($_SESSION['logueado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'propietario' || (int)($_SESSION['usuario_id'] ?? 0) <= 0) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/mensajeria.php';
$rolMensajeria = 'propietario';
require __DIR__ . '/../mensajeria/pagina.php';
