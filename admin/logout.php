<?php
session_start();
require_once '../config/conexion.php';
require_once '../config/auditoria_admin.php';

if (!empty($_SESSION['admin_id'])) {
    registrarAuditoriaAdmin(
        $conn,
        'cierre_sesion',
        'administrador',
        (int) $_SESSION['admin_id'],
        'Cierre de sesión.'
    );
}

unset(
    $_SESSION['admin_logueado'],
    $_SESSION['admin_id'],
    $_SESSION['admin_nombre'],
    $_SESSION['admin_apellido']
);

header('Location: login.php');
exit;
