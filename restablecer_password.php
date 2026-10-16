<?php

include("config/conexion.php");

date_default_timezone_set('America/Argentina/Buenos_Aires');

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errores = [];
$recuperacion = null;
$password_cambiada = false;

// Limpiar enlaces vencidos.
$conn->query("
    DELETE FROM recuperaciones_password
    WHERE fecha_expiracion < NOW()
");

function buscarRecuperacion(mysqli $conn, string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $token_hash = hash('sha256', $token);

    $stmt = $conn->prepare("
        SELECT
            id_recuperacion,
            email,
            tipo_usuario,
            fecha_expiracion
        FROM recuperaciones_password
        WHERE token_hash = ?
          AND fecha_expiracion >= NOW()
        LIMIT 1
    ");

    $stmt->bind_param('s', $token_hash);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ?: null;
}

$recuperacion = buscarRecuperacion($conn, $token);

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $recuperacion
) {

    $password_nueva =
        (string) ($_POST['password_nueva'] ?? '');

    $password_repetida =
        (string) ($_POST['password_repetida'] ?? '');

    if (mb_strlen($password_nueva) < 4) {
        $errores[] =
            'La nueva contraseña debe tener al menos 4 caracteres.';
    }

    if (mb_strlen($password_nueva) > 72) {
        $errores[] =
            'La nueva contraseña es demasiado larga.';
    }

    if ($password_nueva !== $password_repetida) {
        $errores[] =
            'Las contraseñas nuevas no coinciden.';
    }

    if (empty($errores)) {

        $password_hash =
            password_hash(
                $password_nueva,
                PASSWORD_DEFAULT
            );

        $email =
            (string) $recuperacion['email'];

        $tipo_usuario =
            (string) $recuperacion['tipo_usuario'];

        try {

            $conn->begin_transaction();

            if ($tipo_usuario === 'propietario') {

                $stmt = $conn->prepare("
                    UPDATE propietarios
                    SET password = ?
                    WHERE email = ?
                    LIMIT 1
                ");

            } else {

                $stmt = $conn->prepare("
                    UPDATE veterinarios
                    SET password = ?
                    WHERE email = ?
                    LIMIT 1
                ");
            }

            $stmt->bind_param(
                'ss',
                $password_hash,
                $email
            );

            $stmt->execute();

            if ($stmt->affected_rows < 1) {
                throw new RuntimeException(
                    'No se encontró la cuenta.'
                );
            }

            $stmt->close();

            // Invalidar todos los enlaces de recuperación
            // de esta cuenta una vez usada la contraseña nueva.
            $stmt = $conn->prepare("
                DELETE FROM recuperaciones_password
                WHERE email = ?
                  AND tipo_usuario = ?
            ");

            $stmt->bind_param(
                'ss',
                $email,
                $tipo_usuario
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $password_cambiada = true;
            $recuperacion = null;

        } catch (Throwable $e) {

            try {
                $conn->rollback();
            } catch (Throwable $ignorado) {
            }

            $errores[] =
                'No se pudo actualizar la contraseña. Intentá nuevamente.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Nueva contraseña · VetClinic Pro
    </title>

    <link
        rel="icon"
        type="image/png"
        href="img/VetClinic Pro.png">

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
        href="estilos/index.css">

</head>

<body>

<div class="container-fluid min-vh-100">

    <div class="row min-vh-100">

        <!-- PANEL IZQUIERDO -->
        <div class="col-12 col-lg-6 panel-izquierdo d-flex flex-column">

            <div class="logo">

                <img
                    src="img/VetClinic Pro.png"
                    alt="Logo"
                    class="logo-img">

                <div>
                    <h4>VetClinic Pro</h4>
                    <span>Sistema de Gestión Veterinaria</span>
                </div>

            </div>

            <div class="contenido-principal flex-grow-1 d-flex flex-column justify-content-center">

                <h1>
                    Protegé el acceso a
                    <span><i>tu cuenta.</i></span>
                </h1>

                <p>
                    Creá una contraseña nueva para volver a ingresar
                    a VetClinic Pro.
                </p>

            </div>

            <div class="beneficios mt-2">

                <p>
                    <i class="bi bi-check-circle-fill me-2"></i>
                    La nueva contraseña queda guardada con hash
                </p>

                <p>
                    <i class="bi bi-check-circle-fill me-2"></i>
                    El enlace solamente puede utilizarse una vez
                </p>

                <p>
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Los enlaces vencen automáticamente
                </p>

            </div>

        </div>

        <!-- PANEL DERECHO -->
        <div class="col-12 col-lg-6 panel-derecho d-flex justify-content-center align-items-center">

            <div class="login-container">

                <?php if ($password_cambiada): ?>

                    <span class="portal-acceso">
                        Contraseña actualizada
                    </span>

                    <h2>
                        ¡Listo!
                    </h2>

                    <p class="subtitulo">
                        Tu nueva contraseña fue guardada correctamente.
                    </p>

                    <div class="alert alert-success">

                        <i class="bi bi-check-circle me-2"></i>
                        Ya podés iniciar sesión con tu nueva contraseña.

                    </div>

                    <a
                        href="index.php"
                        class="btn btn-login">

                        Ir a iniciar sesión

                        <i class="bi bi-arrow-right ms-2"></i>

                    </a>

                <?php elseif (!$recuperacion): ?>

                    <span class="portal-acceso">
                        Enlace no válido
                    </span>

                    <h2>
                        Recuperación vencida
                    </h2>

                    <p class="subtitulo">
                        Este enlace no existe, ya fue utilizado o venció.
                    </p>

                    <div class="alert alert-warning">

                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Solicitá un nuevo enlace de recuperación.

                    </div>

                    <a
                        href="forgot_password.php"
                        class="btn btn-login">

                        Solicitar otro enlace

                        <i class="bi bi-arrow-right ms-2"></i>

                    </a>

                <?php else: ?>

                    <span class="portal-acceso">
                        Nueva contraseña
                    </span>

                    <h2>
                        Restablecer contraseña
                    </h2>

                    <p class="subtitulo">
                        Escribí y repetí tu nueva contraseña.
                    </p>

                    <?php if (!empty($errores)): ?>

                        <div class="alert alert-danger">

                            <?php foreach ($errores as $error): ?>

                                <div>
                                    <i class="bi bi-exclamation-circle me-2"></i>
                                    <?= htmlspecialchars($error) ?>
                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                    <form
                        method="POST"
                        action="restablecer_password.php">

                        <input
                            type="hidden"
                            name="token"
                            value="<?= htmlspecialchars($token) ?>">

                        <div class="mb-4">

                            <label
                                class="form-label"
                                for="password_nueva">

                                Nueva contraseña

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
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

                                <span
                                    class="input-group-text icono-ojo"
                                    data-password-target="password_nueva"
                                    role="button"
                                    tabindex="0"
                                    aria-label="Mostrar contraseña"
                                    title="Mostrar contraseña"
                                    style="cursor:pointer;">
                                    <i class="bi bi-eye"></i>
                                </span>

                            </div>

                        </div>

                        <div class="mb-4">

                            <label
                                class="form-label"
                                for="password_repetida">

                                Repetir nueva contraseña

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    <i class="bi bi-lock-fill"></i>
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

                                <span
                                    class="input-group-text icono-ojo"
                                    data-password-target="password_repetida"
                                    role="button"
                                    tabindex="0"
                                    aria-label="Mostrar contraseña"
                                    title="Mostrar contraseña"
                                    style="cursor:pointer;">
                                    <i class="bi bi-eye"></i>
                                </span>

                            </div>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-login">

                            Guardar nueva contraseña

                            <i class="bi bi-check-circle ms-2"></i>

                        </button>

                    </form>

                    <div class="registrarse">

                        <a
                            class="custom-link"
                            href="index.php">

                            <i class="bi bi-arrow-left"></i>
                            Volver a iniciar sesión

                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


    <script>
        document.querySelectorAll('[data-password-target]').forEach(function (boton) {

            function alternarPassword() {

                const targetId = boton.getAttribute('data-password-target');
                const campo = document.getElementById(targetId);
                const icono = boton.querySelector('i');

                if (!campo) return;

                const mostrar = campo.type === 'password';

                campo.type = mostrar ? 'text' : 'password';

                if (icono) {
                    icono.classList.toggle('bi-eye', !mostrar);
                    icono.classList.toggle('bi-eye-slash', mostrar);
                }

                boton.setAttribute(
                    'aria-label',
                    mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'
                );

                boton.setAttribute(
                    'title',
                    mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'
                );
            }

            boton.addEventListener('click', alternarPassword);

            boton.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    alternarPassword();
                }
            });
        });
    </script>

</body>

</html>
