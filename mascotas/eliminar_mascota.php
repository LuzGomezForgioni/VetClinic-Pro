<?php
// ============================================================
//  VetClinic Pro — Eliminar Mascota
//  Archivo: mascotas/eliminar_mascota.php
// ============================================================
session_start();

if (empty($_SESSION['logueado'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';

$idPropietario = $_SESSION['usuario_id'];
$idMascota     = (int)($_GET['id'] ?? 0);

if ($idMascota === 0) {
    header('Location: inicio_mascota.php');
    exit;
}

// Verificar que la mascota pertenezca al propietario logueado
$stmt = $conn->prepare("SELECT nombre FROM mascotas WHERE id_mascota = ? AND id_propietario = ? LIMIT 1");
$stmt->bind_param('ii', $idMascota, $idPropietario);
$stmt->execute();
$mascota = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$mascota) {
    header('Location: inicio_mascota.php');
    exit;
}

// Eliminar
$stmt = $conn->prepare("DELETE FROM mascotas WHERE id_mascota = ? AND id_propietario = ?");
$stmt->bind_param('ii', $idMascota, $idPropietario);
$stmt->execute();
$stmt->close();

$_SESSION['mensaje']      = "{$mascota['nombre']} fue eliminado/a del sistema.";
$_SESSION['tipo_mensaje'] = 'exito';

header('Location: inicio_mascota.php');
exit;
