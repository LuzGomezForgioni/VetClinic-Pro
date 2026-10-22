<?php
/** Consultas compartidas de mensajería. Todas las operaciones requieren sesión comprobada. */

function mensajeEsc(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mensajeLongitud(string $texto): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($texto, 'UTF-8');
    }
    return preg_match_all('/./us', $texto) ?: 0;
}

function mensajeRolValido(string $tipo): bool
{
    return $tipo === 'propietario' || $tipo === 'veterinario';
}

function mensajeColumnaUsuario(string $tipo): string
{
    return $tipo === 'veterinario' ? 'id_veterinario' : 'id_propietario';
}

function mensajeConversacion(mysqli $conn, string $tipo, int $usuarioId, int $conversacionId): ?array
{
    if (!mensajeRolValido($tipo) || $usuarioId <= 0 || $conversacionId <= 0) {
        return null;
    }
    $columna = mensajeColumnaUsuario($tipo);
    $sql = "SELECT c.id_conversacion, c.id_propietario, c.id_veterinario,
                   p.nombre AS nombre_propietario, p.apellido AS apellido_propietario,
                   v.nombre AS nombre_veterinario, v.apellido AS apellido_veterinario
            FROM conversaciones c
            INNER JOIN propietarios p ON p.id_propietario = c.id_propietario
            INNER JOIN veterinarios v ON v.id_veterinario = c.id_veterinario
            WHERE c.id_conversacion = ? AND c.$columna = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $conversacionId, $usuarioId);
    $stmt->execute();
    $conversacion = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $conversacion;
}

/** Limita nuevos chats a los profesionales y propietarios relacionados por turnos no cancelados. */
function mensajeContactoPermitido(mysqli $conn, string $tipo, int $usuarioId, int $contactoId): bool
{
    if (!mensajeRolValido($tipo) || $usuarioId <= 0 || $contactoId <= 0) {
        return false;
    }
    $idProp = $tipo === 'propietario' ? $usuarioId : $contactoId;
    $idVet = $tipo === 'veterinario' ? $usuarioId : $contactoId;
    $stmt = $conn->prepare("SELECT 1 FROM turnos t
        INNER JOIN veterinarios v ON v.id_veterinario = t.id_veterinario
        WHERE t.id_propietario = ? AND t.id_veterinario = ?
          AND t.estado <> 'cancelado' AND v.activo = 1 LIMIT 1");
    $stmt->bind_param('ii', $idProp, $idVet);
    $stmt->execute();
    $permitido = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $permitido;
}

function mensajeContactos(mysqli $conn, string $tipo, int $usuarioId): array
{
    if ($tipo === 'propietario') {
        $sql = "SELECT DISTINCT v.id_veterinario AS id, v.nombre, v.apellido
                FROM turnos t
                INNER JOIN veterinarios v ON v.id_veterinario = t.id_veterinario
                WHERE t.id_propietario = ? AND t.estado <> 'cancelado' AND v.activo = 1
                ORDER BY v.apellido, v.nombre";
    } else {
        $sql = "SELECT DISTINCT p.id_propietario AS id, p.nombre, p.apellido
                FROM turnos t
                INNER JOIN propietarios p ON p.id_propietario = t.id_propietario
                WHERE t.id_veterinario = ? AND t.estado <> 'cancelado'
                ORDER BY p.apellido, p.nombre";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $datos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $datos;
}

function mensajeConversaciones(mysqli $conn, string $tipo, int $usuarioId): array
{
    $columna = mensajeColumnaUsuario($tipo);
    $nombre = $tipo === 'veterinario' ? 'p' : 'v';
    $sql = "SELECT c.id_conversacion, c.id_propietario, c.id_veterinario, c.fecha_actualizacion,
                   $nombre.nombre AS nombre_contacto, $nombre.apellido AS apellido_contacto,
                   (SELECT CASE WHEN m.contenido <> '' THEN m.contenido
                           WHEN a.id_adjunto IS NOT NULL THEN CONCAT('📎 ', a.nombre_original)
                           ELSE 'Mensaje' END
                    FROM mensajes m
                    LEFT JOIN mensajes_adjuntos a ON a.id_mensaje = m.id_mensaje
                    WHERE m.id_conversacion = c.id_conversacion
                    ORDER BY m.id_mensaje DESC LIMIT 1) AS ultimo_mensaje,
                   (SELECT COUNT(*) FROM mensajes m
                    WHERE m.id_conversacion = c.id_conversacion
                      AND m.remitente_tipo <> ? AND m.leido_en IS NULL) AS sin_leer
            FROM conversaciones c
            INNER JOIN propietarios p ON p.id_propietario = c.id_propietario
            INNER JOIN veterinarios v ON v.id_veterinario = c.id_veterinario
            WHERE c.$columna = ? ORDER BY c.fecha_actualizacion DESC, c.id_conversacion DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $tipo, $usuarioId);
    $stmt->execute();
    $datos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $datos;
}

function mensajeHistorial(mysqli $conn, int $conversacionId, int $antesDe = 0): array
{
    if ($antesDe > 0) {
        $stmt = $conn->prepare("SELECT m.id_mensaje, m.remitente_tipo, m.contenido, m.fecha_envio, m.leido_en,
                a.id_adjunto, a.nombre_original, a.tipo_mime, a.tamano_bytes
            FROM mensajes m LEFT JOIN mensajes_adjuntos a ON a.id_mensaje = m.id_mensaje
            WHERE m.id_conversacion = ? AND m.id_mensaje < ?
            ORDER BY m.id_mensaje DESC LIMIT 60");
        $stmt->bind_param('ii', $conversacionId, $antesDe);
    } else {
        $stmt = $conn->prepare("SELECT m.id_mensaje, m.remitente_tipo, m.contenido, m.fecha_envio, m.leido_en,
                a.id_adjunto, a.nombre_original, a.tipo_mime, a.tamano_bytes
            FROM mensajes m LEFT JOIN mensajes_adjuntos a ON a.id_mensaje = m.id_mensaje
            WHERE m.id_conversacion = ? ORDER BY m.id_mensaje DESC LIMIT 60");
        $stmt->bind_param('i', $conversacionId);
    }
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_reverse($filas);
}

function mensajeEnviar(mysqli $conn, string $tipo, int $usuarioId, int $conversacionId, int $contactoId, string $contenido, ?array $archivo = null): int
{
    if (!mensajeRolValido($tipo) || $usuarioId <= 0) {
        throw new DomainException('Tu sesión no es válida.');
    }
    $contenido = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $contenido) ?? '');
    $datosArchivo = mensajeValidarAdjunto($archivo);
    if (($contenido === '' || !preg_match('/\S/u', $contenido)) && $datosArchivo === null) {
        throw new DomainException('Escribí un mensaje o seleccioná un archivo para enviar.');
    }
    if (strlen($contenido) > 8000 || mensajeLongitud($contenido) > 2000) {
        throw new DomainException('El mensaje no puede superar los 2000 caracteres.');
    }

    if ($conversacionId > 0) {
        if (!mensajeConversacion($conn, $tipo, $usuarioId, $conversacionId)) {
            throw new DomainException('No tenés acceso a esta conversación.');
        }
    } elseif (!mensajeContactoPermitido($conn, $tipo, $usuarioId, $contactoId)) {
        throw new DomainException('Solo podés iniciar una conversación con alguien vinculado a tus turnos.');
    }

    $archivoGuardado = null;
    $conn->begin_transaction();
    try {
        if ($conversacionId === 0) {
            $idProp = $tipo === 'propietario' ? $usuarioId : $contactoId;
            $idVet = $tipo === 'veterinario' ? $usuarioId : $contactoId;
            $stmt = $conn->prepare("INSERT INTO conversaciones (id_propietario, id_veterinario)
                VALUES (?, ?) ON DUPLICATE KEY UPDATE id_conversacion = LAST_INSERT_ID(id_conversacion)");
            $stmt->bind_param('ii', $idProp, $idVet);
            $stmt->execute();
            $conversacionId = (int) $conn->insert_id;
            $stmt->close();
        }
        $stmt = $conn->prepare('INSERT INTO mensajes (id_conversacion, remitente_tipo, contenido) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $conversacionId, $tipo, $contenido);
        $stmt->execute();
        $idMensaje = (int)$conn->insert_id;
        $stmt->close();
        if ($datosArchivo !== null) {
            $directorio = mensajeDirectorioAdjuntos(true);
            $nombreInterno = bin2hex(random_bytes(20));
            $destino = $directorio . DIRECTORY_SEPARATOR . $nombreInterno;
            if (!move_uploaded_file($datosArchivo['temporal'], $destino)) {
                throw new RuntimeException('No se pudo guardar el archivo adjunto.');
            }
            $archivoGuardado = $destino;
            @chmod($destino, 0600);
            $stmt = $conn->prepare('INSERT INTO mensajes_adjuntos
                (id_mensaje, nombre_original, nombre_guardado, tipo_mime, tamano_bytes)
                VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('isssi', $idMensaje, $datosArchivo['nombre'], $nombreInterno,
                $datosArchivo['mime'], $datosArchivo['tamano']);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare('UPDATE conversaciones SET fecha_actualizacion = NOW() WHERE id_conversacion = ?');
        $stmt->bind_param('i', $conversacionId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        return $conversacionId;
    } catch (Throwable $e) {
        $conn->rollback();
        if ($archivoGuardado !== null && is_file($archivoGuardado)) {
            @unlink($archivoGuardado);
        }
        throw $e;
    }
}

function mensajeMarcarLeidos(mysqli $conn, int $conversacionId, string $tipo): void
{
    $stmt = $conn->prepare('UPDATE mensajes SET leido_en = NOW()
        WHERE id_conversacion = ? AND remitente_tipo <> ? AND leido_en IS NULL');
    $stmt->bind_param('is', $conversacionId, $tipo);
    $stmt->execute();
    $stmt->close();
}

/** Archivos siempre privados, fuera del directorio público del servidor. */
function mensajeDirectorioAdjuntos(bool $crear = false): string
{
    $configurado = trim((string)(getenv('VET_MENSAJERIA_STORAGE') ?: ''));
    $directorio = $configurado !== '' ? $configurado : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vetclinic_mensajeria_adjuntos';
    if ($crear && !is_dir($directorio) && !@mkdir($directorio, 0700, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear el almacenamiento privado de mensajería.');
    }
    $real = realpath($directorio);
    if ($real === false || !is_dir($real)) {
        throw new RuntimeException('No existe el almacenamiento privado de mensajería.');
    }
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($documentRoot !== false) {
        $carpeta = str_replace('\\', '/', strtolower($real));
        $publica = rtrim(str_replace('\\', '/', strtolower($documentRoot)), '/');
        if ($carpeta === $publica || str_starts_with($carpeta, $publica . '/')) {
            throw new RuntimeException('Los archivos privados deben estar fuera del directorio público.');
        }
    }
    if ($crear && !is_writable($real)) {
        throw new RuntimeException('El servidor no tiene permiso para guardar adjuntos.');
    }
    return $real;
}

/** Devuelve metadatos validados del archivo temporal; nunca confía en el MIME declarado por el navegador. */
function mensajeValidarAdjunto(?array $archivo): ?array
{
    if ($archivo === null) {
        return null;
    }
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (!is_int($archivo['error'] ?? null) || $archivo['error'] !== UPLOAD_ERR_OK) {
        throw new DomainException('Falló la carga del archivo. Revisá su tamaño (máximo 10 MB).');
    }
    $tamano = (int)($archivo['size'] ?? 0);
    $temporal = (string)($archivo['tmp_name'] ?? '');
    if ($tamano < 1 || $tamano > 10 * 1024 * 1024 || !is_uploaded_file($temporal)) {
        throw new DomainException('El archivo debe tener contenido y no superar los 10 MB.');
    }
    $original = str_replace('\\', '/', (string)($archivo['name'] ?? ''));
    $nombre = trim(basename($original));
    $nombre = preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '', $nombre) ?? '';
    if ($nombre === '' || $nombre === '.' || $nombre === '..') {
        throw new DomainException('El archivo necesita un nombre válido.');
    }
    $nombre = function_exists('mb_substr') ? mb_substr($nombre, 0, 150, 'UTF-8') : substr($nombre, 0, 150);
    $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $tipos = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'webp' => ['image/webp'], 'gif' => ['image/gif'],
        'pdf' => ['application/pdf'], 'txt' => ['text/plain'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']
    ];
    if (!isset($tipos[$extension]) || !class_exists('finfo')) {
        throw new DomainException('Formato no permitido. Usá JPG, PNG, WEBP, GIF, PDF, TXT, DOCX o XLSX.');
    }
    $mimeDetectado = (new finfo(FILEINFO_MIME_TYPE))->file($temporal);
    if (!in_array($mimeDetectado, $tipos[$extension], true)) {
        throw new DomainException('El contenido del archivo no coincide con su formato.');
    }
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $imagen = @getimagesize($temporal);
        if (!$imagen || (int)$imagen[0] < 1 || (int)$imagen[1] < 1 || (int)$imagen[0] * (int)$imagen[1] > 25000000) {
            throw new DomainException('La fotografía no es válida o su resolución es excesiva.');
        }
    }
    if (in_array($extension, ['docx', 'xlsx'], true)) {
        if (!class_exists('ZipArchive')) {
            throw new DomainException('Para documentos de Office, habilitá la extensión ZIP de PHP.');
        }
        $zip = new ZipArchive();
        if ($zip->open($temporal) !== true) {
            throw new DomainException('El documento de Office está dañado.');
        }
        $entrada = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
        $valido = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($entrada) !== false;
        $zip->close();
        if (!$valido) {
            throw new DomainException('El archivo no corresponde a un documento de Office válido.');
        }
    }
    $mimeSeguro = $extension === 'docx' ? $tipos['docx'][0] : ($extension === 'xlsx' ? $tipos['xlsx'][0] : $mimeDetectado);
    return ['temporal' => $temporal, 'nombre' => $nombre, 'mime' => $mimeSeguro, 'tamano' => $tamano];
}

/** Solo se exponen identificador y metadatos, nunca la ruta privada. */
function mensajeAdjuntoHtml(array $m): string
{
    $id = (int)($m['id_adjunto'] ?? 0);
    if ($id <= 0) {
        return '';
    }
    $nombre = mensajeEsc((string)$m['nombre_original']);
    $tamano = (int)ceil((int)$m['tamano_bytes'] / 1024);
    $url = '../mensajeria/archivo.php?id=' . $id;
    $esImagen = str_starts_with((string)$m['tipo_mime'], 'image/');
    if ($esImagen) {
        return '<div class="mensaje-adjunto"><a href="' . $url . '&amp;descargar=1" title="Descargar ' . $nombre . '">' .
            '<img class="mensaje-imagen" src="' . $url . '" loading="lazy" alt="Imagen adjunta: ' . $nombre . '"></a>' .
            '<a class="mensaje-adjunto-enlace" href="' . $url . '&amp;descargar=1"><i class="bi bi-download"></i> ' . $nombre .
            ' <small>(' . $tamano . ' KB)</small></a></div>';
    }
    return '<a class="mensaje-adjunto mensaje-adjunto-enlace" href="' . $url . '&amp;descargar=1">' .
        '<i class="bi bi-file-earmark-arrow-down"></i> ' . $nombre . ' <small>(' . $tamano . ' KB)</small></a>';
}

function mensajeEstadoNoLeidos(mysqli $conn, string $tipo, int $usuarioId): array
{
    $columna = mensajeColumnaUsuario($tipo);
    $alias = $tipo === 'veterinario' ? 'p' : 'v';
    $stmt = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(MAX(m.id_mensaje), 0) AS ultimo_id
        FROM mensajes m INNER JOIN conversaciones c ON c.id_conversacion = m.id_conversacion
        WHERE c.$columna = ? AND m.remitente_tipo <> ? AND m.leido_en IS NULL");
    $stmt->bind_param('is', $usuarioId, $tipo);
    $stmt->execute();
    $resumen = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $stmt = $conn->prepare("SELECT m.id_mensaje, c.id_conversacion,
        CONCAT($alias.nombre, ' ', $alias.apellido) AS contacto
        FROM mensajes m INNER JOIN conversaciones c ON c.id_conversacion = m.id_conversacion
        INNER JOIN propietarios p ON p.id_propietario = c.id_propietario
        INNER JOIN veterinarios v ON v.id_veterinario = c.id_veterinario
        WHERE c.$columna = ? AND m.remitente_tipo <> ? AND m.leido_en IS NULL
        ORDER BY m.id_mensaje DESC LIMIT 1");
    $stmt->bind_param('is', $usuarioId, $tipo);
    $stmt->execute();
    $ultimo = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return ['total' => (int)$resumen['total'], 'ultimo_id' => (int)$resumen['ultimo_id'], 'ultimo' => $ultimo];
}
