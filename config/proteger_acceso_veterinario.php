<?php

// Este archivo se incluye en las páginas internas del veterinario.
// Permite el acceso normal solo si la suscripción está activa o atrasada.
// Si está suspendida/cancelada/pendiente, deriva a Mi suscripción.

require_once __DIR__ . '/suscripciones.php';

if (empty($_SESSION['logueado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'veterinario') {
    header('Location: ../index.php');
    exit;
}

$idVetGuard = (int) ($_SESSION['usuario_id'] ?? 0);

if ($idVetGuard <= 0) {
    header('Location: ../index.php');
    exit;
}

// Bloquear sesiones ya iniciadas si la cuenta fue dada de baja por administración.
$stmtGuard = $conn->prepare('SELECT activo FROM veterinarios WHERE id_veterinario = ? LIMIT 1');
$stmtGuard->bind_param('i', $idVetGuard);
$stmtGuard->execute();
$usuarioGuard = $stmtGuard->get_result()->fetch_assoc();
$stmtGuard->close();
if (!$usuarioGuard || (int)$usuarioGuard['activo'] !== 1) {
    header('Location: ../logout.php');
    exit;
}

$suscripcionGuard = actualizarEstadoSuscripcion($conn, $idVetGuard);

$archivoActualGuard = basename($_SERVER['PHP_SELF'] ?? '');
$permitidosSinSuscripcion = [
    'configuracion_veterinario.php',
];

if (!suscripcionPermiteUso($suscripcionGuard) && !in_array($archivoActualGuard, $permitidosSinSuscripcion, true)) {
    header('Location: ../suscripciones/mi_suscripcion.php');
    exit;
}
