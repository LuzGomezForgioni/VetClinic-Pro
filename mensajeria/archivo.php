<?php
session_start();
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$tipo = (string)($_SESSION['tipo_usuario'] ?? '');
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
if (empty($_SESSION['logueado']) || !in_array($tipo, ['propietario', 'veterinario'], true) || $usuarioId < 1) {
    http_response_code(401);
    exit('Iniciá sesión para acceder al archivo.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Método no permitido.');
}
try {
    require_once __DIR__ . '/../config/conexion.php';
    require_once __DIR__ . '/../config/mensajeria.php';
    if ($tipo === 'veterinario') {
        require_once __DIR__ . '/../config/suscripciones.php';
        $stmt = $conn->prepare('SELECT activo FROM veterinarios WHERE id_veterinario = ? LIMIT 1');
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $activo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$activo || (int)$activo['activo'] !== 1 || !suscripcionPermiteUso(actualizarEstadoSuscripcion($conn, $usuarioId))) {
            http_response_code(403);
            exit('Cuenta sin acceso a mensajería.');
        }
    }
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
    $columna = mensajeColumnaUsuario($tipo);
    $stmt = $conn->prepare("SELECT a.nombre_original, a.nombre_guardado, a.tipo_mime, a.tamano_bytes
        FROM mensajes_adjuntos a INNER JOIN mensajes m ON m.id_mensaje = a.id_mensaje
        INNER JOIN conversaciones c ON c.id_conversacion = m.id_conversacion
        WHERE a.id_adjunto = ? AND c.$columna = ? LIMIT 1");
    $stmt->bind_param('ii', $id, $usuarioId);
    $stmt->execute();
    $adjunto = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$adjunto || !preg_match('/^[a-f0-9]{40}$/D', (string)$adjunto['nombre_guardado'])) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
    $ruta = mensajeDirectorioAdjuntos() . DIRECTORY_SEPARATOR . $adjunto['nombre_guardado'];
    if (!is_file($ruta) || !is_readable($ruta)) {
        http_response_code(404);
        exit('El archivo ya no está disponible.');
    }
    $mime = (string)$adjunto['tipo_mime'];
    $esImagen = in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    $descargar = (($_GET['descargar'] ?? '') === '1') || !$esImagen;
    $nombre = str_replace(["\r", "\n", '"', '\\'], '', (string)$adjunto['nombre_original']);
    $nombreAscii = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombre) ?: 'archivo';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($ruta));
    header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $nombreAscii . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
    readfile($ruta);
} catch (Throwable $e) {
    error_log('VetClinic Pro: error al descargar un adjunto: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(503);
    }
    echo 'No fue posible recuperar el archivo.';
}
