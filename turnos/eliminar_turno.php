<?php
// ============================================================
// VetClinic Pro — Eliminar Turno
// Archivo: turnos/eliminar_turno.php
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

$idPropietario = (int)$_SESSION['usuario_id'];
$idTurno = (int)($_POST['id'] ?? 0);

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

// ── Cancelar conservando el registro y su historial ─────────────
$stmt = $conn->prepare("
    UPDATE turnos
    SET estado = 'cancelado'
    WHERE id_turno = ? AND id_propietario = ?
      AND estado IN ('pendiente', 'confirmado')
");
$stmt->bind_param('ii', $idTurno, $idPropietario);
try {
    $stmt->execute();
    $eliminado = $stmt->affected_rows > 0;
} catch (mysqli_sql_exception $e) {
    $eliminado = false;
}
$stmt->close();

if ($eliminado) {
    $_SESSION['mensaje'] = 'El turno fue cancelado correctamente.';
    $_SESSION['tipo_mensaje'] = 'exito';
} else {
    $_SESSION['mensaje'] = 'No se pudo cancelar el turno.';
    $_SESSION['tipo_mensaje'] = 'error';
}

header('Location: inicio_turnos.php');
exit;
