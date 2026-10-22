<?php
session_start();
require_once '../config/seguridad.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
}




date_default_timezone_set('America/Argentina/Buenos_Aires');

// ============================================================
// VERIFICAR PROPIETARIO LOGUEADO
// ============================================================

if (
    empty($_SESSION['logueado']) ||
    ($_SESSION['tipo_usuario'] ?? '') !== 'propietario'
) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';

$id_propietario = (int) $_SESSION['usuario_id'];

// ============================================================
// OBTENER DATOS ACTUALES
// ============================================================

$stmt = $conn->prepare("\n    SELECT\n        id_propietario,\n        nombre,\n        apellido,\n        email,\n        telefono,\n        password,\n        fecha_registro\n    FROM propietarios\n    WHERE id_propietario = ?\n    LIMIT 1\n");

$stmt->bind_param('i', $id_propietario);
$stmt->execute();
$propietario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$propietario) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$mensaje = $_SESSION['mensaje_config_propietario'] ?? '';
$tipo_mensaje = $_SESSION['tipo_config_propietario'] ?? '';
unset(
    $_SESSION['mensaje_config_propietario'],
    $_SESSION['tipo_config_propietario']
);

$errores = [];

// Valores del formulario
$form_nombre = $propietario['nombre'];
$form_apellido = $propietario['apellido'];
$form_email = $propietario['email'];
$form_telefono = $propietario['telefono'] ?? '';

// ============================================================
// PROCESAR FORMULARIOS
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    // --------------------------------------------------------
    // ACTUALIZAR DATOS PERSONALES
    // --------------------------------------------------------

    if ($accion === 'guardar_datos') {

        $form_nombre = trim($_POST['nombre'] ?? '');
        $form_apellido = trim($_POST['apellido'] ?? '');
        $form_email = trim($_POST['email'] ?? '');
        $form_telefono = trim($_POST['telefono'] ?? '');

        if ($form_nombre === '') {
            $errores[] = 'El nombre es obligatorio.';
        } elseif (strlen($form_nombre) > 80) {
            $errores[] = 'El nombre no puede superar los 80 caracteres.';
        }

        if ($form_apellido === '') {
            $errores[] = 'El apellido es obligatorio.';
        } elseif (strlen($form_apellido) > 80) {
            $errores[] = 'El apellido no puede superar los 80 caracteres.';
        }

        if ($form_email === '') {
            $errores[] = 'El correo electrónico es obligatorio.';
        } elseif (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Ingresá un correo electrónico válido.';
        } elseif (strlen($form_email) > 120) {
            $errores[] = 'El correo no puede superar los 120 caracteres.';
        }

        if (strlen($form_telefono) > 20) {
            $errores[] = 'El teléfono no puede superar los 20 caracteres.';
        }

        // El correo se usa para iniciar sesión y distinguir el tipo de usuario.
        // Por eso no permitimos repetirlo ni entre propietarios ni con veterinarios.
        if (empty($errores)) {

            $stmt = $conn->prepare("\n                SELECT id_propietario\n                FROM propietarios\n                WHERE email = ?\n                  AND id_propietario <> ?\n                LIMIT 1\n            ");

            $stmt->bind_param('si', $form_email, $id_propietario);
            $stmt->execute();
            $email_propietario_repetido = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($email_propietario_repetido) {
                $errores[] = 'Ese correo ya está siendo utilizado por otro propietario.';
            }
        }

        if (empty($errores)) {

            $stmt = $conn->prepare("\n                SELECT id_veterinario\n                FROM veterinarios\n                WHERE email = ?\n                LIMIT 1\n            ");

            $stmt->bind_param('s', $form_email);
            $stmt->execute();
            $email_veterinario_repetido = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($email_veterinario_repetido) {
                $errores[] = 'Ese correo ya pertenece a una cuenta de veterinario.';
            }
        }

        if (empty($errores)) {

            $telefono_final = $form_telefono !== ''
                ? $form_telefono
                : null;

            $stmt = $conn->prepare("\n                UPDATE propietarios\n                SET\n                    nombre = ?,\n                    apellido = ?,\n                    email = ?,\n                    telefono = ?\n                WHERE id_propietario = ?\n            ");

            $stmt->bind_param(
                'ssssi',
                $form_nombre,
                $form_apellido,
                $form_email,
                $telefono_final,
                $id_propietario
            );

            if ($stmt->execute()) {

                $stmt->close();

                // Mantener la sesión sincronizada con los datos nuevos.
                $_SESSION['usuario_nombre'] = $form_nombre;
                $_SESSION['usuario_apellido'] = $form_apellido;
                $_SESSION['usuario_email'] = $form_email;

                $_SESSION['mensaje_config_propietario'] =
                    'Tus datos personales fueron actualizados correctamente.';

                $_SESSION['tipo_config_propietario'] = 'exito';

                header('Location: configuracion_propietario.php');
                exit;
            }

            $stmt->close();
            $errores[] = 'No se pudieron guardar los cambios.';
        }
    }

    // --------------------------------------------------------
    // CAMBIAR CONTRASEÑA
    // --------------------------------------------------------

    if ($accion === 'cambiar_password') {

        $password_actual = (string) ($_POST['password_actual'] ?? '');
        $password_nueva = (string) ($_POST['password_nueva'] ?? '');
        $password_repetida = (string) ($_POST['password_repetida'] ?? '');

        $password_guardada = (string) ($propietario['password'] ?? '');
        $info_hash = password_get_info($password_guardada);
        $usa_hash = !empty($info_hash['algo']);

        $password_actual_correcta = false;

        if ($password_actual === '') {
            $errores[] = 'Ingresá tu contraseña actual.';
        } else {
            $password_actual_correcta = $usa_hash
                ? password_verify($password_actual, $password_guardada)
                : hash_equals($password_guardada, $password_actual);

            if (!$password_actual_correcta) {
                $errores[] = 'La contraseña actual no es correcta.';
            }
        }

        if ($password_nueva === '') {
            $errores[] = 'Ingresá una contraseña nueva.';
        } elseif (strlen($password_nueva) > 30) {
            $errores[] = 'La nueva contraseña no puede superar los 30 caracteres.';
        }

        if ($password_nueva !== $password_repetida) {
            $errores[] = 'Las contraseñas nuevas no coinciden.';
        }

        if (
            $password_actual_correcta &&
            $password_nueva !== ''
        ) {
            $misma_password = $usa_hash
                ? password_verify($password_nueva, $password_guardada)
                : hash_equals($password_guardada, $password_nueva);

            if ($misma_password) {
                $errores[] = 'La nueva contraseña debe ser diferente de la actual.';
            }
        }

        if (empty($errores)) {

            // La nueva contraseña siempre se guarda hasheada.
            $password_hash = password_hash(
                $password_nueva,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare("
                UPDATE propietarios
                SET password = ?
                WHERE id_propietario = ?
            ");

            $stmt->bind_param(
                'si',
                $password_hash,
                $id_propietario
            );

            if ($stmt->execute()) {

                $stmt->close();

                $_SESSION['mensaje_config_propietario'] =
                    'Tu contraseña fue actualizada correctamente.';

                $_SESSION['tipo_config_propietario'] = 'exito';

                header('Location: configuracion_propietario.php');
                exit;
            }

            $stmt->close();
            $errores[] = 'No se pudo actualizar la contraseña.';
        }
    }
}

// ============================================================
// VOLVER A CARGAR DATOS DESPUÉS DE LOS CAMBIOS
// ============================================================

$stmt = $conn->prepare("\n    SELECT\n        id_propietario,\n        nombre,\n        apellido,\n        email,\n        telefono,\n        fecha_registro\n    FROM propietarios\n    WHERE id_propietario = ?\n    LIMIT 1\n");

$stmt->bind_param('i', $id_propietario);
$stmt->execute();
$datos_actuales = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($errores)) {
    $form_nombre = $datos_actuales['nombre'];
    $form_apellido = $datos_actuales['apellido'];
    $form_email = $datos_actuales['email'];
    $form_telefono = $datos_actuales['telefono'] ?? '';
}

$nombre = $_SESSION['usuario_nombre'] ?? $datos_actuales['nombre'];
$apellido = $_SESSION['usuario_apellido'] ?? $datos_actuales['apellido'];

// ============================================================
// CANTIDAD DE MASCOTAS
// ============================================================

$stmt = $conn->prepare("\n    SELECT COUNT(*) AS total\n    FROM mascotas\n    WHERE id_propietario = ?\n");

$stmt->bind_param('i', $id_propietario);
$stmt->execute();
$fila_mascotas = $stmt->get_result()->fetch_assoc();
$cantidad_mascotas = (int) ($fila_mascotas['total'] ?? 0);
$stmt->close();

// ============================================================
// FECHA ACTUAL
// ============================================================

$dias = [
    'Domingo', 'Lunes', 'Martes', 'Miércoles',
    'Jueves', 'Viernes', 'Sábado'
];

$meses = [
    'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
];

$fecha_actual =
    $dias[date('w')] . ' ' .
    date('j') . ' de ' .
    $meses[date('n') - 1] . ' de ' .
    date('Y');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">
    <title>
        Mi configuración · VetClinic Pro
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
        href="../estilos/configuracion_propietario.css">
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">
        <!-- ================================================== -->
        <!-- BARRA LATERAL -->
        <!-- ================================================== -->
        <div class="col-12 col-lg-2 panel-lateral">
            <a
                href="inicio_propietarios.php"
                class="logo-lateral">
                <img
                    src="../img/VetClinic Pro.png"
                    alt="Logo VetClinic Pro">
                <div>
                    <div class="nombre-sistema">
                        VetClinic Pro
                    </div>
                    <div class="subtitulo-sistema">
                        Portal del propietario
                    </div>
                </div>
            </a>
            <div class="seccion-nav">
                Mi espacio
            </div>
            <a
                href="inicio_propietarios.php"
                class="item-menu">
                <i class="bi bi-grid"></i>
                Inicio
            </a>
            <a
                href="../mascotas/inicio_mascota.php"
                class="item-menu">
                <i class="bi bi-heart-pulse"></i>
                Mis mascotas
            </a>
            <a
                href="../turnos/inicio_turnos.php"
                class="item-menu">
                <i class="bi bi-calendar-check"></i>
                Mis turnos
            </a>
            <a
                href="historial_clinico.php"
                class="item-menu">
                <i class="bi bi-journal-medical"></i>
                Historial clínico
            </a>
            <div class="seccion-nav">
                Más
            </div>
            <a
                href="vacunaciones.php"
                class="item-menu">
                <i class="bi bi-shield-plus"></i>
                Vacunaciones
            </a>
            <a href="mensajes.php" class="item-menu"><i class="bi bi-chat-dots"></i>
                Mensajes</a>
            <div class="seccion-nav">
                Configuración
            </div>
            <a
                href="configuracion_propietario.php"
                class="item-menu activo">
                <i class="bi bi-gear"></i>
                Mi configuración
            </a>
            <div class="usuario-lateral">
                <div class="avatar-usuario">
                    <?= strtoupper(substr($nombre, 0, 1)) ?>
                </div>
                <div>
                    <div class="nombre-usuario">
                        <?= htmlspecialchars(
                            $nombre . ' ' . $apellido
                        ) ?>
                    </div>
                    <div class="rol-usuario">
                        Propietario
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
                        Mi configuración
                    </p>
                    <p class="fecha-hoy">
                        <?= htmlspecialchars($fecha_actual) ?> · VetClinic Pro
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a
                        href="configuracion_propietario.php"
                        class="icono-barra"
                        title="Mi configuración">
                        <i class="bi bi-gear"></i>
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
            <!-- ENCABEZADO -->
            <!-- ================================================== -->
            <div class="encabezado-config-propietario mb-4">
                <div>
                    <h1>
                        Administrá tu cuenta
                    </h1>
                    <p>
                        Podés actualizar tus datos personales, correo y contraseña cuando lo necesites.
                    </p>
                </div>
                <div class="avatar-config-propietario">
                    <?= strtoupper(substr($nombre, 0, 1)) ?>
                </div>
            </div>
            <!-- ================================================== -->
            <!-- MENSAJES -->
            <!-- ================================================== -->
            <?php if ($mensaje !== ''): ?>
                <div class="alerta-config alerta-config-exito mb-4">
                    <i class="bi bi-check-circle"></i>
                    <span>
                        <?= htmlspecialchars($mensaje) ?>
                    </span>
                </div>
            <?php endif; ?>
            <?php if (!empty($errores)): ?>
                <div class="alerta-config alerta-config-error mb-4">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>
                        <strong>
                            Revisá los siguientes datos:
                        </strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($errores as $error): ?>
                                <li>
                                    <?= htmlspecialchars($error) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            <div class="row g-4">
                <!-- ================================================== -->
                <!-- DATOS PERSONALES -->
                <!-- ================================================== -->
                <div class="col-12 col-xl-7">
                    <section class="tarjeta tarjeta-config-propietario h-100">
                        <div class="cabecera-tarjeta-config">
                            <div class="icono-seccion-config">
                                <i class="bi bi-person"></i>
                            </div>
                            <div>
                                <h2>
                                    Datos personales
                                </h2>
                                <p>
                                    Estos datos se utilizan en tu cuenta y en la información de tus mascotas y turnos.
                                </p>
                            </div>
                        </div>
                        <form
                            method="POST"
                            action="configuracion_propietario.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                            <input
                                type="hidden"
                                name="accion"
                                value="guardar_datos">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label
                                        for="nombre"
                                        class="form-label">
                                        Nombre
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="nombre"
                                        name="nombre"
                                        maxlength="80"
                                        value="<?= htmlspecialchars($form_nombre) ?>"
                                        required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label
                                        for="apellido"
                                        class="form-label">
                                        Apellido
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="apellido"
                                        name="apellido"
                                        maxlength="80"
                                        value="<?= htmlspecialchars($form_apellido) ?>"
                                        required>
                                </div>
                                <div class="col-12">
                                    <label
                                        for="email"
                                        class="form-label">
                                        Correo electrónico
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-envelope"></i>
                                        </span>
                                        <input
                                            type="email"
                                            class="form-control"
                                            id="email"
                                            name="email"
                                            maxlength="120"
                                            value="<?= htmlspecialchars($form_email) ?>"
                                            required>
                                    </div>
                                    <div class="ayuda-config">
                                        Si cambiás el correo, la próxima vez deberás iniciar sesión con el nuevo.
                                    </div>
                                </div>
                                <div class="col-12 col-md-7">
                                    <label
                                        for="telefono"
                                        class="form-label">
                                        Teléfono
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-telephone"></i>
                                        </span>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="telefono"
                                            name="telefono"
                                            maxlength="20"
                                            value="<?= htmlspecialchars($form_telefono) ?>"
                                            placeholder="Ingresá tu teléfono">
                                    </div>
                                </div>
                                <div class="col-12 col-md-5">
                                    <label class="form-label">
                                        Miembro desde
                                    </label>
                                    <div class="dato-solo-lectura-config">
                                        <i class="bi bi-calendar3"></i>
                                        <?= date(
                                            'd/m/Y',
                                            strtotime(
                                                $datos_actuales['fecha_registro']
                                            )
                                        ) ?>
                                    </div>
                                </div>
                            </div>
                            <button
                                type="submit"
                                class="btn-guardar-config-propietario">
                                <i class="bi bi-check-circle"></i>
                                Guardar cambios
                            </button>
                        </form>
                    </section>
                </div>
                <!-- ================================================== -->
                <!-- SEGURIDAD -->
                <!-- ================================================== -->
                <div class="col-12 col-xl-5">
                    <section class="tarjeta tarjeta-config-propietario mb-4">
                        <div class="cabecera-tarjeta-config">
                            <div class="icono-seccion-config">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                            <div>
                                <h2>
                                    Seguridad
                                </h2>
                                <p>
                                    Cambiá tu contraseña cuando lo necesites.
                                </p>
                            </div>
                        </div>
                        <form
                            method="POST"
                            action="configuracion_propietario.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                            <input
                                type="hidden"
                                name="accion"
                                value="cambiar_password">
                            <div class="mb-3">
                                <label
                                    for="password_actual"
                                    class="form-label">
                                    Contraseña actual
                                </label>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password_actual"
                                    name="password_actual"
                                    autocomplete="current-password"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label
                                    for="password_nueva"
                                    class="form-label">
                                    Nueva contraseña
                                </label>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password_nueva"
                                    name="password_nueva"
                                    maxlength="30"
                                    autocomplete="new-password"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label
                                    for="password_repetida"
                                    class="form-label">
                                    Repetir nueva contraseña
                                </label>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password_repetida"
                                    name="password_repetida"
                                    maxlength="30"
                                    autocomplete="new-password"
                                    required>
                            </div>
                            <button
                                type="submit"
                                class="btn-seguridad-config">
                                <i class="bi bi-key"></i>
                                Cambiar contraseña
                            </button>
                        </form>
                    </section>
                    <!-- ================================================== -->
                    <!-- MIS MASCOTAS -->
                    <!-- ================================================== -->
                    <section class="tarjeta tarjeta-config-propietario">
                        <div class="cabecera-tarjeta-config mb-3">
                            <div class="icono-seccion-config">
                                <i class="bi bi-heart-pulse"></i>
                            </div>
                            <div>
                                <h2>
                                    Mis mascotas
                                </h2>
                                <p>
                                    Accedé a las mascotas asociadas a tu cuenta.
                                </p>
                            </div>
                        </div>
                        <div class="resumen-mascotas-config">
                            <div>
                                <strong>
                                    <?= $cantidad_mascotas ?>
                                </strong>
                                <span>
                                    <?= $cantidad_mascotas === 1
                                        ? 'mascota registrada'
                                        : 'mascotas registradas'
                                    ?>
                                </span>
                            </div>
                            <a
                                href="../mascotas/inicio_mascota.php"
                                class="btn-mascotas-config">
                                Gestionar mis mascotas
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
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
