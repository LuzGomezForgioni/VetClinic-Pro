<?php
// ============================================================
//  VetClinic Pro — Eliminar Mascota
//  Archivo: mascotas/eliminar_mascota.php
// ============================================================
session_start();

if (empty($_SESSION['logueado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'propietario') {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';
require_once '../config/seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido.');
}
exigirCsrf();

$idPropietario = $_SESSION['usuario_id'];
$idMascota     = (int)($_POST['id'] ?? 0);

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

// No borrar una historia clínica, una vacunación o un turno por cascada.
$stmt = $conn->prepare("SELECT
    (SELECT COUNT(*) FROM vacunaciones WHERE id_mascota = ?) AS vacunas,
    (SELECT COUNT(*) FROM turnos WHERE id_mascota = ?) AS turnos,
    (SELECT COUNT(*) FROM historia_clinica WHERE id_mascota = ?) AS historias");
$stmt->bind_param('iii', $idMascota, $idMascota, $idMascota);
$stmt->execute();
$relaciones = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ((int)$relaciones['vacunas'] + (int)$relaciones['turnos'] + (int)$relaciones['historias'] > 0) {
    $_SESSION['mensaje'] = 'No se puede eliminar esta mascota porque tiene antecedentes clínicos, vacunas o turnos. Podés editar su ficha sin perder el historial.';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: inicio_mascota.php');
    exit;
}

// Eliminar
$stmt = $conn->prepare("DELETE FROM mascotas WHERE id_mascota = ? AND id_propietario = ?");
$stmt->bind_param('ii', $idMascota, $idPropietario);
try {
    $stmt->execute();
    $eliminado = $stmt->affected_rows > 0;
} catch (mysqli_sql_exception $e) {
    $eliminado = false;
}
$stmt->close();

if (!$eliminado) {
    $_SESSION['mensaje'] = 'No se pudo eliminar esta mascota. Revisá si tiene información asociada.';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: inicio_mascota.php');
    exit;
}

$_SESSION['mensaje']      = "{$mascota['nombre']} fue eliminado/a del sistema.";
$_SESSION['tipo_mensaje'] = 'exito';

header('Location: inicio_mascota.php');
exit;
