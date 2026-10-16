<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once '../config/conexion.php';
require_once '../config/suscripciones.php';

if (empty($_SESSION['logueado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'veterinario') {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mi_suscripcion.php');
    exit;
}

$idVeterinario = (int) $_SESSION['usuario_id'];
$suscripcionActual = actualizarEstadoSuscripcion($conn, $idVeterinario);
$stmt = $conn->prepare("SELECT activo FROM veterinarios WHERE id_veterinario = ? LIMIT 1");
$stmt->bind_param('i', $idVeterinario);
$stmt->execute();
$estadoCuenta = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($suscripcionActual && (((int)($estadoCuenta['activo'] ?? 0) === 0 && $suscripcionActual['estado'] !== 'pendiente_pago') || (int)($suscripcionActual['suspension_manual'] ?? 0) === 1)) {
    header('Location: mi_suscripcion.php');
    exit;
}

$medio = (string) ($_POST['medio_pago'] ?? 'credito');
$resultado = (string) ($_POST['resultado_demo'] ?? 'aprobado');

try {
    $pago = registrarPagoDemo(
        $conn,
        $idVeterinario,
        $medio,
        $resultado,
        'Operación generada desde el checkout de demostración.'
    );

    header('Location: comprobante.php?id=' . (int)$pago['id_pago']);
    exit;
} catch (Throwable $e) {
    header('Location: mi_suscripcion.php?error=pago');
    exit;
}
