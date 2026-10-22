<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$tipo = (string)($_SESSION['tipo_usuario'] ?? '');
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
if (empty($_SESSION['logueado']) || !in_array($tipo, ['propietario', 'veterinario'], true) || $usuarioId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión vencida.']);
    exit;
}
try {
    require_once __DIR__ . '/../config/conexion.php';
    require_once __DIR__ . '/../config/seguridad.php';
    require_once __DIR__ . '/../config/mensajeria.php';
    if ($tipo === 'veterinario') {
        // La validación de suscripción y estado de la cuenta se aplica también a las peticiones AJAX.
        require_once __DIR__ . '/../config/suscripciones.php';
        $stmt = $conn->prepare('SELECT activo FROM veterinarios WHERE id_veterinario = ? LIMIT 1');
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $activo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$activo || (int)$activo['activo'] !== 1 || !suscripcionPermiteUso(actualizarEstadoSuscripcion($conn, $usuarioId))) {
            http_response_code(403);
            echo json_encode(['error' => 'Cuenta sin acceso a mensajería.']);
            exit;
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['accion'] ?? '') === 'estado') {
        echo json_encode(mensajeEstadoNoLeidos($conn, $tipo, $usuarioId), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $idConversacion = max(0, (int)($_REQUEST['conversacion'] ?? 0));
    if (!mensajeConversacion($conn, $tipo, $usuarioId, $idConversacion)) {
        http_response_code(403);
        echo json_encode(['error' => 'Conversación no autorizada.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validarCsrf($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'Token de seguridad inválido.']);
            exit;
        }
        if (($_POST['accion'] ?? '') !== 'leer') {
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida.']);
            exit;
        }
        mensajeMarcarLeidos($conn, $idConversacion, $tipo);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
        exit;
    }
    $desde = max(0, (int)($_GET['desde'] ?? 0));
    $stmt = $conn->prepare('SELECT m.id_mensaje, m.remitente_tipo, m.contenido, m.fecha_envio,
        a.id_adjunto, a.nombre_original, a.tipo_mime, a.tamano_bytes
        FROM mensajes m LEFT JOIN mensajes_adjuntos a ON a.id_mensaje = m.id_mensaje
        WHERE m.id_conversacion = ? AND m.id_mensaje > ?
        ORDER BY m.id_mensaje ASC LIMIT 100');
    $stmt->bind_param('ii', $idConversacion, $desde);
    $stmt->execute();
    $datos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['mensajes' => $datos], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('VetClinic Pro: error en API mensajería: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'No se pudo actualizar la conversación.']);
}
