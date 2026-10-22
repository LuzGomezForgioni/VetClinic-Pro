<?php
session_start();
require_once 'config/seguridad.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
}



include("config/conexion.php");

date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = '';
$enlace_prueba = '';
$email_form = '';

function esEntornoLocal(): bool
{
    $host = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');

    return
        str_contains($host, 'localhost') ||
        str_starts_with($host, '127.0.0.1') ||
        str_starts_with($host, '[::1]');
}

function construirEnlaceRecuperacion(string $token): string
{
    $usaHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $protocolo = $usaHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $carpeta = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/'));
    $carpeta = rtrim($carpeta, '/');

    return $protocolo . '://' . $host . $carpeta .
        '/restablecer_password.php?token=' . urlencode($token);
}

// Limpiar enlaces vencidos.
$conn->query("
    DELETE FROM recuperaciones_password
    WHERE fecha_expiracion < NOW()
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email_form = trim($_POST['email'] ?? '');

    if ($email_form === '' || !filter_var($email_form, FILTER_VALIDATE_EMAIL)) {

        $mensaje =
            'Ingresá un correo electrónico válido.';

    } else {

        $tipo_usuario = '';
        $usuario_encontrado = false;

        // ========================================================
        // 1. BUSCAR COMO PROPIETARIO
        // ========================================================

        $stmt = $conn->prepare("
            SELECT id_propietario
            FROM propietarios
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->bind_param('s', $email_form);
        $stmt->execute();

        $propietario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($propietario) {

            $tipo_usuario = 'propietario';
            $usuario_encontrado = true;

        } else {

            // ====================================================
            // 2. SI NO ES PROPIETARIO, BUSCAR COMO VETERINARIO
            // ====================================================

            $stmt = $conn->prepare("
                SELECT id_veterinario
                FROM veterinarios
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->bind_param('s', $email_form);
            $stmt->execute();

            $veterinario = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($veterinario) {
                $tipo_usuario = 'veterinario';
                $usuario_encontrado = true;
            }
        }

        if ($usuario_encontrado) {

            // Un token aleatorio se envía al usuario.
            // En la base solamente guardamos su hash.
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);

            // Invalidar recuperaciones anteriores para esta cuenta.
            $stmt = $conn->prepare("
                DELETE FROM recuperaciones_password
                WHERE email = ?
                  AND tipo_usuario = ?
            ");

            $stmt->bind_param(
                'ss',
                $email_form,
                $tipo_usuario
            );

            $stmt->execute();
            $stmt->close();

            // El enlace dura 30 minutos.
            $stmt = $conn->prepare("
                INSERT INTO recuperaciones_password
                    (
                        email,
                        tipo_usuario,
                        token_hash,
                        fecha_expiracion
                    )
                VALUES
                    (
                        ?,
                        ?,
                        ?,
                        DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                    )
            ");

            $stmt->bind_param(
                'sss',
                $email_form,
                $tipo_usuario,
                $token_hash
            );

            $stmt->execute();
            $stmt->close();

            $enlace = construirEnlaceRecuperacion($token);

            if (esEntornoLocal()) {

                // En XAMPP normalmente no hay servidor SMTP configurado.
                // Mostramos el enlace solamente para poder probar el flujo.
                $enlace_prueba = $enlace;

            } else {

                // En un hosting con mail() habilitado, se intenta enviar
                // el enlace por correo. Si el hosting no lo permite,
                // más adelante se puede reemplazar por PHPMailer/SMTP.
                $asunto = 'Restablecer contraseña - VetClinic Pro';

                $cuerpo =
                    "Recibimos una solicitud para restablecer tu contraseña.\n\n" .
                    "Abrí este enlace dentro de los próximos 30 minutos:\n" .
                    $enlace . "\n\n" .
                    "Si no pediste este cambio, ignorá este mensaje.";

                $headers =
                    "From: VetClinic Pro <no-reply@vetclinicpro.local>\r\n" .
                    "Content-Type: text/plain; charset=UTF-8\r\n";

                @mail(
                    $email_form,
                    $asunto,
                    $cuerpo,
                    $headers
                );
            }
        }

        // Mensaje genérico para no revelar si una cuenta existe o no.
        $mensaje =
            'Si el correo está registrado, se generó un enlace para restablecer la contraseña.';
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
        Recuperar contraseña · VetClinic Pro
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
                    Recuperá el acceso a
                    <span><i>tu cuenta.</i></span>
                </h1>
                <p>
                    Ingresá el correo asociado a tu cuenta y te ayudaremos
                    a crear una nueva contraseña.
                </p>
            </div>
            <div class="beneficios mt-2">
                <p>
                    <i class="bi bi-check-circle-fill me-2"></i>
                    El enlace de recuperación dura 30 minutos
                </p>
                <p>
                    <i class="bi bi-check-circle-fill me-2"></i>
                    La contraseña anterior nunca se muestra
                </p>
                <p>
                    <i class="bi bi-check-circle-fill me-2"></i>
                    La nueva contraseña se guarda protegida con hash
                </p>
            </div>
        </div>
        <!-- PANEL DERECHO -->
        <div class="col-12 col-lg-6 panel-derecho d-flex justify-content-center align-items-center">
            <div class="login-container">
                <span class="portal-acceso">
                    Recuperar acceso
                </span>
                <h2>
                    ¿Olvidaste tu contraseña?
                </h2>
                <p class="subtitulo">
                    Escribí el correo electrónico de tu cuenta.
                </p>
                <?php if ($mensaje !== ''): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="forgot_password.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-4">
                        <label
                            class="form-label"
                            for="email">
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
                                placeholder="correo@ejemplo.com"
                                value="<?= htmlspecialchars($email_form) ?>"
                                autocomplete="email"
                                required>
                        </div>
                    </div>
                    <button
                        type="submit"
                        class="btn btn-login">
                        Generar enlace de recuperación
                        <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>
                <?php if ($enlace_prueba !== ''): ?>
                    <div class="alert alert-warning mt-4">
                        <strong>
                            Modo de prueba en XAMPP
                        </strong>
                        <div class="mt-2">
                            Como estás en localhost, el correo no se envía.
                            Usá este enlace para probar la recuperación:
                        </div>
                        <a
                            href="<?= htmlspecialchars($enlace_prueba) ?>"
                            class="btn btn-outline-dark mt-3">
                            <i class="bi bi-key"></i>
                            Abrir enlace de recuperación
                        </a>
                    </div>
                <?php endif; ?>
                <div class="registrarse">
                    <a
                        class="custom-link"
                        href="index.php">
                        <i class="bi bi-arrow-left"></i>
                        Volver a iniciar sesión
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
