<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['admin_logueado']) || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/seguridad.php';

$idAdministradorSesion = (int) $_SESSION['admin_id'];
$stmtAdminActivo = $conn->prepare("\n    SELECT id_administrador, nombre, apellido, activo\n    FROM administradores\n    WHERE id_administrador = ?\n    LIMIT 1\n");
$stmtAdminActivo->bind_param('i', $idAdministradorSesion);
$stmtAdminActivo->execute();
$adminSesion = $stmtAdminActivo->get_result()->fetch_assoc();
$stmtAdminActivo->close();

if (!$adminSesion || (int) $adminSesion['activo'] !== 1) {
    unset(
        $_SESSION['admin_logueado'],
        $_SESSION['admin_id'],
        $_SESSION['admin_nombre'],
        $_SESSION['admin_apellido']
    );
    header('Location: login.php?estado=inactivo');
    exit;
}

$_SESSION['admin_nombre'] = $adminSesion['nombre'];
$_SESSION['admin_apellido'] = $adminSesion['apellido'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
}
