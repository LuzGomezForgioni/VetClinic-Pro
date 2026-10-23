<?php

session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');

// ============================================================
// VERIFICAR VETERINARIO LOGUEADO
// ============================================================

if (
    empty($_SESSION['logueado']) ||
    ($_SESSION['tipo_usuario'] ?? '') !== 'veterinario'
) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';
require_once '../config/proteger_acceso_veterinario.php';

$id_veterinario = (int) ($_SESSION['usuario_id'] ?? 0);

if ($id_veterinario <= 0) {
    header('Location: ../index.php');
    exit;
}

// ============================================================
// TOKEN SIMPLE PARA LOS FORMULARIOS
// ============================================================

if (empty($_SESSION['csrf_config_veterinario'])) {
    $_SESSION['csrf_config_veterinario'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_config_veterinario'];

// ============================================================
// BUSCAR DATOS DEL VETERINARIO
// ============================================================

function obtenerVeterinario(mysqli $conn, int $id_veterinario): ?array
{
    $stmt = $conn->prepare("
        SELECT
            id_veterinario,
            nombre,
            apellido,
            especialidad,
            matricula,
            telefono,
            email,
            password,
            activo,
            sexo
        FROM veterinarios
        WHERE id_veterinario = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $id_veterinario);
    $stmt->execute();

    $veterinario = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $veterinario ?: null;
}

$veterinario = obtenerVeterinario($conn, $id_veterinario);

if (!$veterinario) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// ============================================================
// MENSAJES
// ============================================================

$mensaje = $_SESSION['mensaje_config_veterinario'] ?? '';
$tipo_mensaje = $_SESSION['tipo_config_veterinario'] ?? '';

unset(
    $_SESSION['mensaje_config_veterinario'],
    $_SESSION['tipo_config_veterinario']
);

$errores_telefono = [];
$errores_password = [];

// ============================================================
// PROCESAR FORMULARIOS
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token_recibido = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrf_token, $token_recibido)) {
        $errores_password[] = 'La solicitud no es válida. Recargá la página e intentá nuevamente.';
        $errores_telefono[] = 'La solicitud no es válida. Recargá la página e intentá nuevamente.';
    } else {

        $accion = $_POST['accion'] ?? '';

        // ========================================================
        // ACTUALIZAR TELÉFONO
        // ========================================================

        if ($accion === 'actualizar_telefono') {

            $telefono = trim($_POST['telefono'] ?? '');

            if (mb_strlen($telefono) > 20) {
                $errores_telefono[] = 'El teléfono no puede superar los 20 caracteres.';
            }

            if (
                $telefono !== '' &&
                !preg_match('/^[0-9+()\-\s]+$/', $telefono)
            ) {
                $errores_telefono[] = 'El teléfono contiene caracteres no válidos.';
            }

            if (empty($errores_telefono)) {

                $telefono_final = $telefono !== ''
                    ? $telefono
                    : null;

                $stmt = $conn->prepare("
                    UPDATE veterinarios
                    SET telefono = ?
                    WHERE id_veterinario = ?
                ");

                $stmt->bind_param(
                    'si',
                    $telefono_final,
                    $id_veterinario
                );

                if ($stmt->execute()) {
                    $stmt->close();

                    $_SESSION['mensaje_config_veterinario'] =
                        'Teléfono actualizado correctamente.';

                    $_SESSION['tipo_config_veterinario'] =
                        'exito';

                    header('Location: configuracion_veterinario.php');
                    exit;
                }

                $stmt->close();

                $errores_telefono[] =
                    'No se pudo actualizar el teléfono.';
            }
        }

        // ========================================================
        // CAMBIAR CONTRASEÑA
        // ========================================================

        if ($accion === 'cambiar_password') {

            $password_actual = (string) ($_POST['password_actual'] ?? '');
            $password_nueva = (string) ($_POST['password_nueva'] ?? '');
            $password_repetida = (string) ($_POST['password_repetida'] ?? '');

            if ($password_actual === '') {
                $errores_password[] = 'Ingresá tu contraseña actual.';
            }

            if (mb_strlen($password_nueva) < 4) {
                $errores_password[] = 'La nueva contraseña debe tener al menos 4 caracteres.';
            }

            if (mb_strlen($password_nueva) > 72) {
                $errores_password[] = 'La nueva contraseña es demasiado larga.';
            }

            if ($password_nueva !== $password_repetida) {
                $errores_password[] = 'Las nuevas contraseñas no coinciden.';
            }

            // Volvemos a leer la contraseña actual desde la base.
            $veterinario_actual = obtenerVeterinario(
                $conn,
                $id_veterinario
            );

            $password_guardada =
                (string) ($veterinario_actual['password'] ?? '');

            // Compatible con el sistema actual en texto plano y también
            // con una futura migración a password_hash().
            $info_hash = password_get_info($password_guardada);
            $usa_hash = !empty($info_hash['algo']);

            $password_correcta = $usa_hash
                ? password_verify($password_actual, $password_guardada)
                : hash_equals($password_guardada, $password_actual);

            if (!$password_correcta) {
                $errores_password[] = 'La contraseña actual es incorrecta.';
            }

            if (
                $password_correcta &&
                $password_nueva !== ''
            ) {
                $misma_password = $usa_hash
                    ? password_verify($password_nueva, $password_guardada)
                    : hash_equals($password_guardada, $password_nueva);

                if ($misma_password) {
                    $errores_password[] = 'La nueva contraseña debe ser diferente de la actual.';
                }
            }

            if (empty($errores_password)) {

                // Si la cuenta ya usa hash, lo conservamos.
                // Si hoy usa texto plano, no cambiamos tu sistema de login.
                $password_para_guardar = $usa_hash
                    ? password_hash($password_nueva, PASSWORD_DEFAULT)
                    : $password_nueva;

                $stmt = $conn->prepare("
                    UPDATE veterinarios
                    SET password = ?
                    WHERE id_veterinario = ?
                ");

                $stmt->bind_param(
                    'si',
                    $password_para_guardar,
                    $id_veterinario
                );

                if ($stmt->execute()) {
                    $stmt->close();

                    $_SESSION['mensaje_config_veterinario'] =
                        'Contraseña actualizada correctamente.';

                    $_SESSION['tipo_config_veterinario'] =
                        'exito';

                    header('Location: configuracion_veterinario.php');
                    exit;
                }

                $stmt->close();

                $errores_password[] =
                    'No se pudo actualizar la contraseña.';
            }
        }
    }
}

// Refrescar los datos luego de cualquier intento de modificación.
$veterinario = obtenerVeterinario($conn, $id_veterinario);

$nombre = $veterinario['nombre'];
$apellido = $veterinario['apellido'];

$sexo = strtoupper(
    trim($veterinario['sexo'] ?? '')
);

$titulo = ($sexo === 'F')
    ? 'Dra.'
    : 'Dr.';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">
    <title>
        Configuración — Veterinario · VetClinic Pro
    </title>
    <link
        rel="icon"
        type="image/png"
        href="../img/VetClinic Pro.png">
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">
    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link
        rel="stylesheet"
        href="../estilos/inicio.css">
    <link
        rel="stylesheet"
        href="../estilos/configuracion_veterinario.css">
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">
        <!-- ================================================== -->
        <!-- BARRA LATERAL -->
        <!-- ================================================== -->
        <div class="col-12 col-lg-2 panel-lateral">
            <a
                href="inicio_veterinario.php"
                class="logo-lateral">
                <img
                    src="../img/VetClinic Pro.png"
                    alt="Logo VetClinic Pro">
                <div>
                    <div class="nombre-sistema">
                        VetClinic Pro
                    </div>
                    <div class="subtitulo-sistema">
                        Portal del veterinario
                    </div>
                </div>
            </a>
            <div class="seccion-nav">
                Principal
            </div>
            <a
                href="inicio_veterinario.php"
                class="item-menu">
                <i class="bi bi-grid"></i>
                Inicio
            </a>
            <a
                href="turnos_veterinario.php"
                class="item-menu">
                <i class="bi bi-calendar-check"></i>
                Turnos
            </a>
            <a
                href="mascotas_veterinario.php"
                class="item-menu">
                <i class="bi bi-heart-pulse"></i>
                Mascotas
            </a>
            <a
                href="historiales_veterinario.php"
                class="item-menu">
                <i class="bi bi-journal-medical"></i>
                Historial clínico
            </a>
            <div class="seccion-nav">
                Gestión
            </div>
            <a
                href="propietarios_veterinario.php"
                class="item-menu">
                <i class="bi bi-people"></i>
                Propietarios
            </a>
            <a
                href="vacunaciones_veterinario.php"
                class="item-menu">
                <i class="bi bi-shield-plus"></i>
                Vacunaciones
            </a>
            <a href="mensajes.php" class="item-menu"><i class="bi bi-chat-dots"></i>
                Mensajes</a>                <a href="estadisticas.php" class="item-menu">
                    <i class="bi bi-graph-up-arrow"></i> Estadísticas
                </a>

            <div class="seccion-nav">
                Configuración
            </div>
            <a
                href="configuracion_veterinario.php"
                class="item-menu activo">
                <i class="bi bi-gear"></i>
                Mi configuración
            </a>
            <a
                href="horarios_atencion.php"
                class="item-menu">
                <i class="bi bi-clock"></i>
                Horarios de atención
            </a>
            <a href="../suscripciones/mi_suscripcion.php" class="item-menu">
                <i class="bi bi-credit-card-2-front"></i> Mi suscripción
            </a>
            <div class="usuario-lateral">
                <div class="avatar-usuario">
                    <?= strtoupper(
                        substr($nombre, 0, 1) .
                        substr($apellido, 0, 1)
                    ) ?>
                </div>
                <div>
                    <div class="nombre-usuario">
                        <?= htmlspecialchars(
                            $nombre . ' ' . $apellido
                        ) ?>
                    </div>
                    <div class="rol-usuario">
                        Veterinario
                    </div>
                </div>
            </div>
        </div>
        <!-- ================================================== -->
        <!-- ÁREA PRINCIPAL -->
        <!-- ================================================== -->
        <div class="col-12 col-lg-10 area-principal">
            <div class="barra-superior">
                <div>
                    <p class="titulo-pagina">
                        Configuración
                    </p>
                    <p class="fecha-hoy">
                        Administrá tu información personal, seguridad y horarios.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a
                        href="inicio_veterinario.php"
                        class="icono-barra"
                        title="Volver al inicio">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <a
                        href="../logout.php"
                        class="icono-barra"
                        title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
            <!-- ================================================== -->
            <!-- MENSAJE GENERAL -->
            <!-- ================================================== -->
            <?php if ($mensaje !== ''): ?>
                <div class="alerta-config alerta-config-exito mb-4">
                    <i class="bi bi-check-circle"></i>
                    <span>
                        <?= htmlspecialchars($mensaje) ?>
                    </span>
                </div>
            <?php endif; ?>
            <!-- ================================================== -->
            <!-- CABECERA DEL PERFIL -->
            <!-- ================================================== -->
            <section class="cabecera-configuracion mb-4">
                <div class="avatar-configuracion">
                    <?= strtoupper(
                        substr($nombre, 0, 1) .
                        substr($apellido, 0, 1)
                    ) ?>
                </div>
                <div>
                    <span class="etiqueta-configuracion">
                        Mi cuenta
                    </span>
                    <h1>
                        <?= htmlspecialchars(
                            $titulo . ' ' . $nombre . ' ' . $apellido
                        ) ?>
                    </h1>
                    <p>
                        <?= htmlspecialchars(
                            $veterinario['especialidad'] ?: 'Veterinario/a'
                        ) ?>
                    </p>
                </div>
            </section>
            <div class="row g-4">
                <!-- ================================================== -->
                <!-- COLUMNA IZQUIERDA -->
                <!-- ================================================== -->
                <div class="col-12 col-xl-7">
                    <!-- ============================================== -->
                    <!-- MI PERFIL -->
                    <!-- ============================================== -->
                    <section class="tarjeta tarjeta-configuracion mb-4">
                        <div class="cabecera-seccion-config">
                            <div>
                                <div class="titulo-tarjeta mb-1">
                                    <i class="bi bi-person-vcard"></i>
                                    Mi perfil
                                </div>
                                <p>
                                    Los datos profesionales son administrados por el responsable del sistema.
                                </p>
                            </div>
                            <span class="badge-datos-protegidos">
                                <i class="bi bi-lock"></i>
                                Datos protegidos
                            </span>
                        </div>
                        <div class="grilla-datos-perfil">
                            <div class="dato-perfil-config">
                                <span>
                                    Nombre
                                </span>
                                <strong>
                                    <?= htmlspecialchars($veterinario['nombre']) ?>
                                </strong>
                                <i class="bi bi-lock"></i>
                            </div>
                            <div class="dato-perfil-config">
                                <span>
                                    Apellido
                                </span>
                                <strong>
                                    <?= htmlspecialchars($veterinario['apellido']) ?>
                                </strong>
                                <i class="bi bi-lock"></i>
                            </div>
                            <div class="dato-perfil-config">
                                <span>
                                    Matrícula
                                </span>
                                <strong>
                                    <?= htmlspecialchars($veterinario['matricula']) ?>
                                </strong>
                                <i class="bi bi-lock"></i>
                            </div>
                            <div class="dato-perfil-config">
                                <span>
                                    Especialidad
                                </span>
                                <strong>
                                    <?= htmlspecialchars(
                                        $veterinario['especialidad'] ?: 'No registrada'
                                    ) ?>
                                </strong>
                                <i class="bi bi-lock"></i>
                            </div>
                            <div class="dato-perfil-config dato-perfil-ancho">
                                <span>
                                    Email asociado a la cuenta
                                </span>
                                <strong>
                                    <?= htmlspecialchars($veterinario['email']) ?>
                                </strong>
                                <i class="bi bi-lock"></i>
                            </div>
                        </div>
                        <div class="nota-email-config">
                            <i class="bi bi-info-circle"></i>
                            <span>
                                El correo identifica tu cuenta. Para modificar nombre, matrícula, especialidad o email, contactá al administrador del sistema.
                            </span>
                        </div>
                    </section>
                    <!-- ============================================== -->
                    <!-- TELÉFONO -->
                    <!-- ============================================== -->
                    <section class="tarjeta tarjeta-configuracion">
                        <div class="titulo-tarjeta mb-1">
                            <i class="bi bi-telephone"></i>
                            Datos de contacto
                        </div>
                        <p class="texto-seccion-config">
                            Podés actualizar únicamente tu número de teléfono.
                        </p>
                        <?php if (!empty($errores_telefono)): ?>
                            <div class="alerta-config alerta-config-error mb-3">
                                <i class="bi bi-exclamation-triangle"></i>
                                <div>
                                    <?php foreach ($errores_telefono as $error): ?>
                                        <div>
                                            <?= htmlspecialchars($error) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($csrf_token) ?>">
                            <input
                                type="hidden"
                                name="accion"
                                value="actualizar_telefono">
                            <label
                                for="telefono"
                                class="form-label">
                                Teléfono
                            </label>
                            <div class="input-group input-contacto-config">
                                <span class="input-group-text">
                                    <i class="bi bi-phone"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="telefono"
                                    name="telefono"
                                    maxlength="20"
                                    placeholder="Ej.: 3537 123456"
                                    value="<?= htmlspecialchars(
                                        $_POST['accion'] ?? '' === 'actualizar_telefono'
                                            ? ($_POST['telefono'] ?? '')
                                            : ($veterinario['telefono'] ?? '')
                                    ) ?>">
                            </div>
                            <div class="acciones-configuracion">
                                <button
                                    type="submit"
                                    class="btn-config-principal">
                                    <i class="bi bi-check-circle"></i>
                                    Guardar teléfono
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
                <!-- ================================================== -->
                <!-- COLUMNA DERECHA -->
                <!-- ================================================== -->
                <div class="col-12 col-xl-5">
                    <!-- ============================================== -->
                    <!-- HORARIOS -->
                    <!-- ============================================== -->
                    <section class="tarjeta tarjeta-opcion-config mb-4">
                        <div class="icono-opcion-config">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="contenido-opcion-config">
                            <h2>
                                Horarios de atención
                            </h2>
                            <p>
                                Configurá los días y bloques horarios en los que atendés.
                            </p>
                            <a
                                href="horarios_atencion.php"
                                class="btn-config-secundario">
                                Administrar horarios
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </section>
                    <!-- ============================================== -->
                    <!-- SEGURIDAD -->
                    <!-- ============================================== -->
                    <section class="tarjeta tarjeta-configuracion">
                        <div class="titulo-tarjeta mb-1">
                            <i class="bi bi-shield-lock"></i>
                            Seguridad
                        </div>
                        <p class="texto-seccion-config">
                            Cambiá la contraseña utilizada para ingresar a tu cuenta.
                        </p>
                        <?php if (!empty($errores_password)): ?>
                            <div class="alerta-config alerta-config-error mb-3">
                                <i class="bi bi-exclamation-triangle"></i>
                                <div>
                                    <?php foreach ($errores_password as $error): ?>
                                        <div>
                                            <?= htmlspecialchars($error) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($csrf_token) ?>">
                            <input
                                type="hidden"
                                name="accion"
                                value="cambiar_password">
                            <div class="campo-password-config">
                                <label
                                    for="password_actual"
                                    class="form-label">
                                    Contraseña actual
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password_actual"
                                        name="password_actual"
                                        autocomplete="current-password"
                                        required>
                                </div>
                            </div>
                            <div class="campo-password-config">
                                <label
                                    for="password_nueva"
                                    class="form-label">
                                    Nueva contraseña
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-key"></i>
                                    </span>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password_nueva"
                                        name="password_nueva"
                                        minlength="4"
                                        maxlength="72"
                                        autocomplete="new-password"
                                        required>
                                </div>
                            </div>
                            <div class="campo-password-config">
                                <label
                                    for="password_repetida"
                                    class="form-label">
                                    Repetir nueva contraseña
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-key-fill"></i>
                                    </span>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password_repetida"
                                        name="password_repetida"
                                        minlength="4"
                                        maxlength="72"
                                        autocomplete="new-password"
                                        required>
                                </div>
                            </div>
                            <div class="acciones-configuracion">
                                <button
                                    type="submit"
                                    class="btn-config-principal">
                                    <i class="bi bi-shield-check"></i>
                                    Cambiar contraseña
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script><script src="../mensajeria/notificaciones.js" defer></script>
</body>
</html>
