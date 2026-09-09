<?php
// ============================================================
// VetClinic Pro — Eliminar Turno
// Archivo: turnos/eliminar_turno.php
// ============================================================
session_start();

if (empty($_SESSION['logueado'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';

$idPropietario = (int)$_SESSION['usuario_id'];
$idTurno = (int)($_GET['id'] ?? 0);

if ($idTurno === 0) {
    header('Location: inicio_turnos.php');
    exit;
}

// ── Verificar que el turno pertenezca al propietario ───────
$stmt = $conn->prepare("
    SELECT id_turno, fecha_hora, motivo, estado
    FROM turnos
    WHERE id_turno = ? AND id_propietario = ?
    LIMIT 1
");
$stmt->bind_param('ii', $idTurno, $idPropietario);
$stmt->execute();
$turno = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$turno) {
    header('Location: inicio_turnos.php');
    exit;
}

// No permitir que el propietario elimine turnos ya realizados
// o que ya estén cancelados.
$estado = mb_strtolower($turno['estado']);
if ($estado === 'realizado' || $estado === 'cancelado') {
    $_SESSION['mensaje'] = 'Este turno ya no puede cancelarse.';
    $_SESSION['tipo_mensaje'] = 'error';

    header('Location: inicio_turnos.php');
    exit;
}

// ── Eliminar usando id_turno + id_propietario ─────────────
$stmt = $conn->prepare("
    DELETE FROM turnos
    WHERE id_turno = ? AND id_propietario = ?
");
$stmt->bind_param('ii', $idTurno, $idPropietario);
$stmt->execute();
$eliminado = $stmt->affected_rows > 0;
$stmt->close();

if ($eliminado) {
    $_SESSION['mensaje'] = 'El turno fue cancelado/eliminado correctamente.';
    $_SESSION['tipo_mensaje'] = 'exito';
} else {
    $_SESSION['mensaje'] = 'No se pudo cancelar el turno.';
    $_SESSION['tipo_mensaje'] = 'error';
}

header('Location: inicio_turnos.php');
exit;
