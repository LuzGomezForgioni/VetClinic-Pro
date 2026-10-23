<?php
// Plantilla compartida. La sesión, los permisos y la conexión fueron validados en la página de cada rol.
if (!isset($rolMensajeria, $conn) || !mensajeRolValido($rolMensajeria)) {
    http_response_code(403);
    exit('Acceso denegado.');
}
header('Cache-Control: private, no-store, max-age=0');
$miId = (int) $_SESSION['usuario_id'];
$soyVet = $rolMensajeria === 'veterinario';
$nombreUsuario = trim((string)($_SESSION['usuario_nombre'] ?? '') . ' ' . (string)($_SESSION['usuario_apellido'] ?? ''));
$contactoId = max(0, (int)($_GET['contacto'] ?? 0));
$conversacionId = max(0, (int)($_GET['conversacion'] ?? 0));
$antes = max(0, (int)($_GET['antes'] ?? 0));
$error = '';
$aviso = $_SESSION['mensaje_ok_mensajeria'] ?? '';
unset($_SESSION['mensaje_ok_mensajeria']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $conversacionId = max(0, (int)($_POST['conversacion'] ?? 0));
    $contactoId = max(0, (int)($_POST['contacto'] ?? 0));
    if (($_POST['accion'] ?? '') === 'enviar') {
        try {
            $idEnviado = mensajeEnviar($conn, $rolMensajeria, $miId, $conversacionId, $contactoId, (string)($_POST['contenido'] ?? ''), $_FILES['adjunto'] ?? null);
            $_SESSION['mensaje_ok_mensajeria'] = 'Mensaje enviado.';
            header('Location: mensajes.php?conversacion=' . $idEnviado);
            exit;
        } catch (DomainException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            error_log('VetClinic Pro: error al enviar un mensaje: ' . $e->getMessage());
            $error = 'No se pudo enviar el mensaje. Verificá que la migración de mensajería esté instalada.';
        }
    } else {
        $error = 'Acción no válida.';
    }
}

try {
    $conversaciones = mensajeConversaciones($conn, $rolMensajeria, $miId);
    $contactos = mensajeContactos($conn, $rolMensajeria, $miId);
    $conversacionActual = $conversacionId > 0
        ? mensajeConversacion($conn, $rolMensajeria, $miId, $conversacionId) : null;
    if ($conversacionId > 0 && !$conversacionActual) {
        http_response_code(403);
        $error = 'La conversación solicitada no existe o no te pertenece.';
        $conversacionId = 0;
    }
    $nombreContactoNuevo = '';
    if (!$conversacionActual && $contactoId > 0) {
        foreach ($contactos as $contacto) {
            if ((int)$contacto['id'] === $contactoId) {
                $nombreContactoNuevo = trim($contacto['nombre'] . ' ' . $contacto['apellido']);
                break;
            }
        }
        if ($nombreContactoNuevo === '') {
            http_response_code(403);
            $error = 'Solo podés escribir a personas vinculadas con tus turnos.';
            $contactoId = 0;
        }
    }
    $historial = $conversacionActual ? mensajeHistorial($conn, $conversacionId, $antes) : [];
} catch (Throwable $e) {
    error_log('VetClinic Pro: error de mensajería: ' . $e->getMessage());
    http_response_code(503);
    exit('La mensajería todavía no está configurada. Importá migraciones/crear_mensajeria.sql en phpMyAdmin.');
}

$contactoSeleccionado = $conversacionActual
    ? trim($soyVet
        ? $conversacionActual['nombre_propietario'] . ' ' . $conversacionActual['apellido_propietario']
        : $conversacionActual['nombre_veterinario'] . ' ' . $conversacionActual['apellido_veterinario'])
    : $nombreContactoNuevo;
$textoGuardado = $error !== '' ? (string)($_POST['contenido'] ?? '') : '';
$numeroSinLeer = array_sum(array_map(static fn($fila) => (int)$fila['sin_leer'], $conversaciones));
$primerId = $historial ? (int)$historial[0]['id_mensaje'] : 0;
$tieneAnteriores = false;
if ($conversacionActual && $primerId > 0) {
    $stmtAnterior = $conn->prepare('SELECT 1 FROM mensajes WHERE id_conversacion = ? AND id_mensaje < ? LIMIT 1');
    $stmtAnterior->bind_param('ii', $conversacionId, $primerId);
    $stmtAnterior->execute();
    $tieneAnteriores = (bool)$stmtAnterior->get_result()->fetch_row();
    $stmtAnterior->close();
}
$ultimoId = $historial ? (int)$historial[count($historial) - 1]['id_mensaje'] : 0;
$avatar = strtoupper(substr($nombreUsuario ?: 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes — VetClinic Pro</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos/inicio.css">
    <link rel="stylesheet" href="../estilos/mensajes.css">
</head>

<body>
    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">
            <nav class="col-12 col-lg-2 panel-lateral" aria-label="Menú principal">
                <a href="<?= $soyVet ? '../veterinarios/inicio_veterinario.php' : '../propietarios/inicio_propietarios.php' ?>" class="logo-lateral">
                    <img src="../img/VetClinic Pro.png" alt="VetClinic Pro">
                    <div>
                        <div class="nombre-sistema">VetClinic Pro</div>
                        <div class="subtitulo-sistema">Portal del <?= $soyVet ? 'veterinario' : 'propietario' ?></div>
                    </div>
                </a>
                <div class="seccion-nav"><?= $soyVet ? 'Principal' : 'Mi espacio' ?></div>
                <?php if ($soyVet): ?>
                    <a href="inicio_veterinario.php" class="item-menu"><i class="bi bi-grid"></i> Inicio</a>
                    <a href="turnos_veterinario.php" class="item-menu"><i class="bi bi-calendar-check"></i> Turnos</a>
                    <a href="mascotas_veterinario.php" class="item-menu"><i class="bi bi-heart-pulse"></i> Mascotas</a>
                    <a href="historiales_veterinario.php" class="item-menu"><i class="bi bi-journal-medical"></i> Historial clínico</a>
                    <div class="seccion-nav">Gestión</div>
                    <a href="propietarios_veterinario.php" class="item-menu"><i class="bi bi-people"></i> Propietarios</a>
                    <a href="vacunaciones_veterinario.php" class="item-menu"><i class="bi bi-shield-plus"></i> Vacunaciones</a>

                <?php else: ?>
                    <a href="inicio_propietarios.php" class="item-menu"><i class="bi bi-grid"></i> Inicio</a>
                    <a href="../mascotas/inicio_mascota.php" class="item-menu"><i class="bi bi-heart-pulse"></i> Mis mascotas</a>
                    <a href="../turnos/inicio_turnos.php" class="item-menu"><i class="bi bi-calendar-check"></i> Mis turnos</a>
                    <a href="historial_clinico.php" class="item-menu"><i class="bi bi-journal-medical"></i> Historial clínico</a>
                    <div class="seccion-nav">Más</div>
                    <a href="vacunaciones.php" class="item-menu"><i class="bi bi-shield-plus"></i> Vacunaciones</a>
                <?php endif; ?>
                <a href="mensajes.php" class="item-menu activo" aria-current="page">
                    <i class="bi bi-chat-dots"></i> Mensajes
                    <?php if ($numeroSinLeer > 0): ?>
                        <span class="contador-mensajes-nav"><?= (int)$numeroSinLeer ?></span>
                    <?php endif; ?>
                </a>

                <?php if ($soyVet): ?>
                    <a href="estadisticas.php" class="item-menu">
                        <i class="bi bi-graph-up-arrow"></i> Estadísticas
                    </a>
                <?php endif; ?>

                <div class="seccion-nav">Configuración</div>
                <a href="<?= $soyVet ? 'configuracion_veterinario.php' : 'configuracion_propietario.php' ?>" class="item-menu"><i class="bi bi-gear"></i> Mi configuración</a>
                <?php if ($soyVet): ?>
                    <a href="horarios_atencion.php" class="item-menu"><i class="bi bi-clock"></i> Horarios de atención</a>
                    <a href="../suscripciones/mi_suscripcion.php" class="item-menu"><i class="bi bi-credit-card-2-front"></i> Mi suscripción</a>
                <?php endif; ?>
                <a href="../logout.php" class="item-menu"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
                <div class="usuario-lateral">
                    <div class="avatar-usuario"><?= mensajeEsc($avatar) ?></div>
                    <div>
                        <div class="nombre-usuario"><?= mensajeEsc($nombreUsuario) ?></div>
                        <div class="rol-usuario"><?= $soyVet ? 'Veterinario' : 'Propietario' ?></div>
                    </div>
                </div>
            </nav>
            <main class="col-12 col-lg-10 area-principal">
                <header class="barra-superior">
                    <div>
                        <p class="titulo-pagina">Mensajes</p>
                        <p class="fecha-hoy">Conversaciones privadas con <?= $soyVet ? 'tus propietarios' : 'tus veterinarios' ?>.</p>
                    </div>
                    <a class="icono-barra" href="<?= $soyVet ? 'inicio_veterinario.php' : 'inicio_propietarios.php' ?>" title="Volver al inicio" aria-label="Volver al inicio"><i class="bi bi-house"></i></a>
                </header>
                <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= mensajeEsc($error) ?></div><?php endif; ?>
                <?php if ($aviso): ?><div class="alert alert-success" role="status"><?= mensajeEsc((string)$aviso) ?></div><?php endif; ?>
                <div class="mensajes-panel">
                    <aside class="mensajes-listado" aria-label="Conversaciones">
                        <h2><i class="bi bi-inboxes"></i> Conversaciones</h2>
                        <label for="buscarConversaciones" class="visually-hidden">Buscar una conversación</label>
                        <input id="buscarConversaciones" class="form-control mensajes-busqueda" type="search" placeholder="Buscar conversación" autocomplete="off">
                        <?php if ($contactos): ?>
                            <label for="nuevoContacto" class="mensajes-etiqueta">Nueva conversación</label>
                            <select id="nuevoContacto" class="form-select" aria-label="Elegir persona para conversar">
                                <option value="">Seleccionar <?= $soyVet ? 'propietario' : 'veterinario' ?></option>
                                <?php foreach ($contactos as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= $contactoId === (int)$c['id'] ? 'selected' : '' ?>><?= mensajeEsc(trim($c['nombre'] . ' ' . $c['apellido'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <div class="mensajes-contactos" id="listaConversaciones">
                            <?php foreach ($conversaciones as $c): ?>
                                <?php
                                $nombreLista = trim($c['nombre_contacto'] . ' ' . $c['apellido_contacto']);
                                $vistaPrevia = trim((string)($c['ultimo_mensaje'] ?? ''));
                                if (mensajeLongitud($vistaPrevia) > 65) {
                                    $vistaPrevia = function_exists('mb_substr') ? mb_substr($vistaPrevia, 0, 65) . '…' : substr($vistaPrevia, 0, 65) . '…';
                                }
                                ?>
                                <a href="mensajes.php?conversacion=<?= (int)$c['id_conversacion'] ?>"
                                    class="mensajes-contacto <?= (int)$c['id_conversacion'] === $conversacionId ? 'seleccionado' : '' ?>"
                                    data-contacto-nombre="<?= mensajeEsc($nombreLista) ?>">
                                    <span class="mensajes-avatar"><i class="bi bi-person"></i></span>
                                    <span class="mensajes-contacto-texto">
                                        <strong><?= mensajeEsc($nombreLista) ?></strong>
                                        <small><?= mensajeEsc($vistaPrevia ?: 'Sin mensajes') ?></small>
                                    </span>
                                    <?php if ((int)$c['sin_leer'] > 0): ?>
                                        <span class="mensajes-pendientes" title="Mensajes no leídos"><?= (int)$c['sin_leer'] ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if (!$conversaciones): ?><p class="mensajes-sin-contactos">Todavía no hay conversaciones.</p><?php endif; ?>
                        </div>
                    </aside>
                    <section class="mensajes-chat" aria-label="Chat">
                        <?php if ($conversacionActual || $nombreContactoNuevo): ?>
                            <div class="mensajes-chat-encabezado">
                                <span class="mensajes-avatar"><i class="bi bi-person"></i></span>
                                <div>
                                    <h2><?= mensajeEsc($contactoSeleccionado) ?></h2>
                                    <small><?= $soyVet ? 'Propietario' : 'Veterinario' ?> · Mensajería interna</small>
                                </div>
                            </div>
                            <?php if ($antes > 0): ?>
                                <a href="mensajes.php?conversacion=<?= $conversacionId ?>" class="mensajes-volver-nuevos">Volver a los mensajes recientes</a>
                            <?php endif; ?>
                            <?php if ($tieneAnteriores): ?>
                                <a class="mensajes-anteriores" href="mensajes.php?conversacion=<?= $conversacionId ?>&amp;antes=<?= $primerId ?>">Ver mensajes anteriores</a>
                            <?php endif; ?>
                            <div id="mensajesHistorial" class="mensajes-historial" aria-label="Mensajes de la conversación"
                                data-conversacion="<?= $conversacionId ?>" data-ultimo="<?= $ultimoId ?>"
                                data-csrf="<?= mensajeEsc(tokenCsrf()) ?>" data-rol="<?= mensajeEsc($rolMensajeria) ?>"
                                data-historico="<?= $antes > 0 ? '1' : '0' ?>">
                                <?php if (!$historial): ?><p class="mensajes-vacio" id="mensajesVacio">Escribí el primer mensaje para comenzar.</p><?php endif; ?>
                                <?php foreach ($historial as $m): ?>
                                    <article class="mensaje-burbuja <?= $m['remitente_tipo'] === $rolMensajeria ? 'propio' : 'recibido' ?>" data-id="<?= (int)$m['id_mensaje'] ?>">
                                        <?php if ($m['contenido'] !== ''): ?><div class="mensaje-contenido"><?= nl2br(mensajeEsc($m['contenido'])) ?></div><?php endif; ?>
                                        <?= mensajeAdjuntoHtml($m) ?>
                                        <time datetime="<?= mensajeEsc(str_replace(' ', 'T', $m['fecha_envio'])) ?>"><?= mensajeEsc(date('d/m H:i', strtotime($m['fecha_envio']))) ?></time>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($antes === 0): ?>
                                <form method="POST" action="mensajes.php" enctype="multipart/form-data" class="mensajes-redactar" id="formEnviarMensaje">
                                    <input type="hidden" name="csrf_token" value="<?= mensajeEsc(tokenCsrf()) ?>">
                                    <input type="hidden" name="accion" value="enviar">
                                    <input type="hidden" name="conversacion" value="<?= $conversacionId ?>">
                                    <input type="hidden" name="contacto" value="<?= $contactoId ?>">
                                    <label for="mensajeContenido" class="visually-hidden">Escribí tu mensaje</label>
                                    <div class="mensajes-compositor">
                                        <textarea id="mensajeContenido" name="contenido" maxlength="2000" rows="2" class="form-control" placeholder="Escribí un mensaje o adjuntá una foto / archivo"><?= mensajeEsc($textoGuardado) ?></textarea>
                                        <div class="mensajes-adjuntar">
                                            <label for="mensajeAdjunto" class="mensajes-adjuntar-boton"><i class="bi bi-paperclip"></i> Adjuntar foto o archivo</label>
                                            <input id="mensajeAdjunto" type="file" name="adjunto" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.docx,.xlsx" aria-describedby="indicadorAdjunto limiteAdjunto">
                                            <span id="indicadorAdjunto" class="mensajes-archivo-seleccionado" role="status"></span>
                                            <button type="button" class="btn btn-sm btn-link mensajes-quitar-adjunto" id="quitarAdjunto" hidden>Quitar</button>
                                        </div>
                                        <small id="limiteAdjunto" class="mensajes-ayuda">Máximo 10 MB · JPG, PNG, WEBP, GIF, PDF, TXT, DOCX, XLSX</small>
                                    </div>
                                    <button class="btn btn-primary mensajes-enviar" type="submit"><i class="bi bi-send"></i> Enviar</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="mensajes-bienvenida">
                                <i class="bi bi-chat-square-heart"></i>
                                <h2>Tu espacio de mensajes</h2>
                                <p>Seleccioná una conversación o elegí una persona del listado para comenzar.</p>
                                <?php if (!$contactos && !$conversaciones): ?>
                                    <p class="mensajes-explicacion">Los contactos aparecen cuando tenés al menos un turno no cancelado con <?= $soyVet ? 'un propietario' : 'un veterinario' ?>.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
                <div class="mensajes-opciones-aviso">
                    <button type="button" id="activarAvisosMensajes" class="btn btn-sm btn-outline-primary"><i class="bi bi-bell"></i> Activar avisos en este navegador</button>
                    <span id="estadoAvisosMensajes" class="mensajes-ayuda" role="status">Recibirás avisos dentro del sistema mientras tengas una página abierta.</span>
                </div>
                <p class="mensajes-aviso"><i class="bi bi-shield-lock"></i> Los mensajes solo son visibles para el propietario y el veterinario de cada conversación. Este canal no reemplaza la atención de urgencias.</p>
            </main>
        </div>
    </div>
    <script src="../mensajeria/mensajes.js" defer></script>
    <script src="../mensajeria/notificaciones.js" defer></script>
</body>

</html>