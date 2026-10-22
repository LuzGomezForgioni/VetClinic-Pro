<?php
session_start();
require_once 'config/seguridad.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
}




include("config/conexion.php");
require_once "config/suscripciones.php";

date_default_timezone_set('America/Argentina/Buenos_Aires');

// ============================================================
// CONTRASEÑAS
// ============================================================

function passwordCorrecta(string $passwordIngresada, string $passwordGuardada): bool
{
    $infoHash = password_get_info($passwordGuardada);
    $usaHash = !empty($infoHash['algo']);

    if ($usaHash) {
        return password_verify($passwordIngresada, $passwordGuardada);
    }

    // Compatibilidad temporal con cuentas antiguas en texto plano.
    return hash_equals($passwordGuardada, $passwordIngresada);
}

function passwordUsaHash(string $passwordGuardada): bool
{
    $infoHash = password_get_info($passwordGuardada);
    return !empty($infoHash['algo']);
}

// ============================================================
// RECORDARME
// ============================================================
// Nunca se guarda la contraseña en una cookie.
// Se usa un token aleatorio; en la base se guarda solamente su hash.

function conexionSegura(): bool
{
    return
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function guardarCookieRecordarme(string $selector, string $token, int $expira): void
{
    setcookie(
        'vetclinic_recordarme',
        $selector . ':' . $token,
        [
            'expires' => $expira,
            'path' => '/',
            'secure' => conexionSegura(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

function borrarCookieRecordarme(): void
{
    setcookie(
        'vetclinic_recordarme',
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => conexionSegura(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

function eliminarSesionRecordadaActual(mysqli $conn): void
{
    $cookie = $_COOKIE['vetclinic_recordarme'] ?? '';

    if ($cookie !== '' && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);

        if (preg_match('/^[a-f0-9]{16}$/', $selector)) {
            $stmt = $conn->prepare("
                DELETE FROM sesiones_recordadas
                WHERE selector = ?
            ");
            $stmt->bind_param('s', $selector);
            $stmt->execute();
            $stmt->close();
        }
    }

    borrarCookieRecordarme();
}

function crearSesionRecordada(
    mysqli $conn,
    string $tipoUsuario,
    int $idUsuario
): void {
    // Eliminar sesiones vencidas.
    $conn->query("
        DELETE FROM sesiones_recordadas
        WHERE fecha_expiracion < NOW()
    ");

    $selector = bin2hex(random_bytes(8));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    // 30 días.
    $expira = time() + (60 * 60 * 24 * 30);
    $fechaExpiracion = date('Y-m-d H:i:s', $expira);

    $stmt = $conn->prepare("
        INSERT INTO sesiones_recordadas
            (
                selector,
                token_hash,
                tipo_usuario,
                id_usuario,
                fecha_expiracion
            )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'sssis',
        $selector,
        $tokenHash,
        $tipoUsuario,
        $idUsuario,
        $fechaExpiracion
    );

    $stmt->execute();
    $stmt->close();

    guardarCookieRecordarme(
        $selector,
        $token,
        $expira
    );
}

function cargarSesionUsuario(
    array $usuario,
    string $tipoUsuario
): void {
    session_regenerate_id(true);
    $_SESSION["logueado"] = true;
    $_SESSION["tipo_usuario"] = $tipoUsuario;

    if ($tipoUsuario === 'propietario') {
        $_SESSION["usuario_id"] = $usuario["id_propietario"];
    } else {
        $_SESSION["usuario_id"] = $usuario["id_veterinario"];
    }

    $_SESSION["usuario_nombre"] = $usuario["nombre"];
    $_SESSION["usuario_apellido"] = $usuario["apellido"];
    $_SESSION["usuario_email"] = $usuario["email"];

    if ($tipoUsuario === 'veterinario') {
        $_SESSION["usuario_sexo"] =
            strtoupper(trim($usuario["sexo"] ?? ''));
    }
}

function redirigirInicio(string $tipoUsuario): void
{
    global $conn;

    if ($tipoUsuario === 'propietario') {
        header("Location: propietarios/inicio_propietarios.php");
        exit();
    }

    $idVeterinario = (int) ($_SESSION['usuario_id'] ?? 0);
    $suscripcion = $idVeterinario > 0
        ? actualizarEstadoSuscripcion($conn, $idVeterinario)
        : null;

    if (!suscripcionPermiteUso($suscripcion)) {
        header("Location: suscripciones/mi_suscripcion.php");
    } else {
        header("Location: veterinarios/inicio_veterinario.php");
    }

    exit();
}

// ============================================================
// INICIO AUTOMÁTICO MEDIANTE "RECORDARME"
// ============================================================

if (
    empty($_SESSION['logueado']) &&
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    !isset($_GET['error']) &&
    !empty($_COOKIE['vetclinic_recordarme'])
) {
    $cookie = (string) $_COOKIE['vetclinic_recordarme'];

    if (str_contains($cookie, ':')) {

        [$selector, $token] = explode(':', $cookie, 2);

        if (
            preg_match('/^[a-f0-9]{16}$/', $selector) &&
            preg_match('/^[a-f0-9]{64}$/', $token)
        ) {
            $stmt = $conn->prepare("
                SELECT
                    selector,
                    token_hash,
                    tipo_usuario,
                    id_usuario,
                    fecha_expiracion
                FROM sesiones_recordadas
                WHERE selector = ?
                  AND fecha_expiracion >= NOW()
                LIMIT 1
            ");

            $stmt->bind_param('s', $selector);
            $stmt->execute();
            $sesionRecordada = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (
                $sesionRecordada &&
                hash_equals(
                    $sesionRecordada['token_hash'],
                    hash('sha256', $token)
                )
            ) {
                $tipoUsuario =
                    (string) $sesionRecordada['tipo_usuario'];

                $idUsuario =
                    (int) $sesionRecordada['id_usuario'];

                if ($tipoUsuario === 'propietario') {

                    $stmt = $conn->prepare("
                        SELECT *
                        FROM propietarios
                        WHERE id_propietario = ?
                        LIMIT 1
                    ");

                } else {

                    $stmt = $conn->prepare("
                        SELECT *
                        FROM veterinarios
                        WHERE id_veterinario = ?
                        LIMIT 1
                    ");
                }

                $stmt->bind_param('i', $idUsuario);
                $stmt->execute();
                $usuario = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($usuario) {

                    cargarSesionUsuario(
                        $usuario,
                        $tipoUsuario
                    );

                    // Rotar el token al recuperar la sesión.
                    $nuevoToken = bin2hex(random_bytes(32));
                    $nuevoHash = hash('sha256', $nuevoToken);

                    $nuevoVencimiento =
                        time() + (60 * 60 * 24 * 30);

                    $fechaExpiracion =
                        date(
                            'Y-m-d H:i:s',
                            $nuevoVencimiento
                        );

                    $stmt = $conn->prepare("
                        UPDATE sesiones_recordadas
                        SET
                            token_hash = ?,
                            fecha_expiracion = ?
                        WHERE selector = ?
                    ");

                    $stmt->bind_param(
                        'sss',
                        $nuevoHash,
                        $fechaExpiracion,
                        $selector
                    );

                    $stmt->execute();
                    $stmt->close();

                    guardarCookieRecordarme(
                        $selector,
                        $nuevoToken,
                        $nuevoVencimiento
                    );

                    redirigirInicio($tipoUsuario);
                }
            }
        }
    }

    // Cookie inválida o vencida.
    eliminarSesionRecordadaActual($conn);
}

// Si ya hay sesión activa y se entra al login, ir al inicio.
if (
    !empty($_SESSION['logueado']) &&
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    redirigirInicio(
        (string) ($_SESSION['tipo_usuario'] ?? '')
    );
}

// ============================================================
// LOGIN NORMAL
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? '');
    $password = (string) ($_POST["password"] ?? '');
    $recordarme = isset($_POST['recordarme']);

    // ============================================================
    // 1. PROPIETARIO
    // ============================================================

    $stmt = $conn->prepare("
        SELECT *
        FROM propietarios
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $propietario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($propietario) {

        $passwordGuardada =
            (string) $propietario["password"];

        if (
            !passwordCorrecta(
                $password,
                $passwordGuardada
            )
        ) {
            header("Location: index.php?error=password");
            exit();
        }

        // Migración automática de cuentas antiguas.
        if (
            !passwordUsaHash($passwordGuardada) ||
            password_needs_rehash(
                $passwordGuardada,
                PASSWORD_DEFAULT
            )
        ) {
            $nuevoHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $stmt = $conn->prepare("
                UPDATE propietarios
                SET password = ?
                WHERE id_propietario = ?
            ");

            $stmt->bind_param(
                "si",
                $nuevoHash,
                $propietario["id_propietario"]
            );

            $stmt->execute();
            $stmt->close();
        }

        cargarSesionUsuario(
            $propietario,
            'propietario'
        );

        if ($recordarme) {
            crearSesionRecordada(
                $conn,
                'propietario',
                (int) $propietario["id_propietario"]
            );
        } else {
            eliminarSesionRecordadaActual($conn);
        }

        redirigirInicio('propietario');
    }

    // ============================================================
    // 2. VETERINARIO
    // ============================================================

    $stmt = $conn->prepare("
        SELECT *
        FROM veterinarios
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $veterinario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($veterinario) {

    if ((int) ($veterinario['activo'] ?? 0) !== 1) {
        header('Location: index.php?error=cuenta_inactiva');
        exit;
    }

        $passwordGuardada =
            (string) $veterinario["password"];

        if (
            !passwordCorrecta(
                $password,
                $passwordGuardada
            )
        ) {
            header("Location: index.php?error=password");
            exit();
        }

        if (
            !passwordUsaHash($passwordGuardada) ||
            password_needs_rehash(
                $passwordGuardada,
                PASSWORD_DEFAULT
            )
        ) {
            $nuevoHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $stmt = $conn->prepare("
                UPDATE veterinarios
                SET password = ?
                WHERE id_veterinario = ?
            ");

            $stmt->bind_param(
                "si",
                $nuevoHash,
                $veterinario["id_veterinario"]
            );

            $stmt->execute();
            $stmt->close();
        }

        cargarSesionUsuario(
            $veterinario,
            'veterinario'
        );

        if ($recordarme) {
            crearSesionRecordada(
                $conn,
                'veterinario',
                (int) $veterinario["id_veterinario"]
            );
        } else {
            eliminarSesionRecordadaActual($conn);
        }

        redirigirInicio('veterinario');
    }

    // ============================================================
    // 3. EMAIL NO ENCONTRADO
    // ============================================================

    header("Location: index.php?error=email");
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Iniciar Sesión · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="img/VetClinic Pro.png">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="estilos/index.css" />
</head>
<body>
    <div class="container-fluid min-vh-100">
        <div class="row min-vh-100">
            <!-- PANEL IZQUIERDO -->
            <div class="col-12 col-lg-6 panel-izquierdo d-flex flex-column">
                <div class="logo">
                    <img src="img/VetClinic Pro.png" alt="Logo" class="logo-img">
                    <div>
                        <h4>VetClinic Pro</h4>
                        <span>Sistema de Gestión Veterinaria</span>
                    </div>
                </div>
                <div class="contenido-principal flex-grow-1 d-flex flex-column justify-content-center">
                    <h1>Una plataforma diseñada para el <span><i>bienestar animal.</i></span></h1>
                    <p>Accedé con tu cuenta para gestionar turnos, historiales clínicos y comunicación desde un solo lugar.</p>
                </div>
                <div class="card mascota-card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="pet-icon me-3">🐶</div>
                                <div>
                                    <h6 class="mb-1">Max — Labrador, 4 años</h6>
                                    <small>Próx. turno: hoy 09:00</small>
                                </div>
                            </div>
                            <span class="badge bg-success">Confirmado</span>
                        </div>
                    </div>
                </div>
                <div class="beneficios mt-2">
                    <p><i class="bi bi-check-circle-fill me-2"></i>Historial clínico digital completo</p>
                    <p><i class="bi bi-check-circle-fill me-2"></i>Agendá turnos online y recibí recordatorios automáticos</p>
                    <p><i class="bi bi-check-circle-fill me-2"></i>Acceso rápido a información de vacunaciones y tratamientos</p>
                </div>
            </div>
            <!-- PANEL DERECHO -->
            <div class="col-12 col-lg-6 panel-derecho d-flex justify-content-center align-items-start">
                <div class="login-container">
                    <span class="portal-acceso">Portal de acceso</span>
                    <h2>Iniciar sesión</h2>
                    <p class="subtitulo">Accedé a tu cuenta para continuar.</p>
                    <form action="index.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="login-campo">
                            <label class="form-label" for="email">Correo electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" placeholder="correo@ejemplo.com"
                                    id="email" name="email" required>
                            </div>
                            <div class="form-text">Nunca compartiremos tu correo electrónico con nadie más.</div>
                        </div>
                        <div class="login-campo">
                            <label class="form-label" for="password">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" placeholder="••••••••"
                                    id="password" name="password" required>
                                <span
                                    class="input-group-text border-start-0 bg-white icono-ojo"
                                    data-password-target="password"
                                    role="button"
                                    tabindex="0"
                                    aria-label="Mostrar contraseña"
                                    title="Mostrar contraseña">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center login-opciones">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="recordarme" name="recordarme" value="1">
                                <label class="form-check-label" for="recordarme">Recordarme</label>
                            </div>
                            <a href="forgot_password.php" class="custom-link">¿Olvidaste tu contraseña?</a>
                        </div>
                        <button type="submit" class="btn btn-login">
                            Iniciar sesión
                            <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </form>
                    <!-- Separador -->
                    <div class="d-flex align-items-center gap-3 login-separador">
                        <hr class="flex-grow-1 m-0">
                        <span class="text-secondary small">o continuar con</span>
                        <hr class="flex-grow-1 m-0">
                    </div>
                    <!-- Botón oficial de Google Identity Services -->
                    <div id="google-signin-button" class="d-flex justify-content-center"></div>
                    <!-- Aclaración para cuentas profesionales -->
                    <div class="text-center login-aviso-veterinario">
                        <p class="small text-secondary mb-0">
                            Las cuentas nuevas creadas con Google son de propietario.
                            <strong>¿Sos veterinario?</strong>
                            <a class="custom-link" href="suscripciones/plan_profesional.php">Conocé el plan profesional y empezá ahora</a>.
                        </p>
                    </div>
                    <div class="registrarse">
                        ¿No tenés cuenta?
                        <a class="custom-link" href="propietarios/alta_propietario.php">Registrarse</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- SweetAlert2 para los mensajes de error -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>    <?php if (isset($_GET["error"])): ?>
        <?php if ($_GET["error"] == "email"): ?>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Correo no encontrado',
                    text: 'No existe una cuenta registrada con ese correo.',
                    confirmButtonColor: '#1a1f5e'
                });
            </script>        <?php endif; ?>
        <?php if ($_GET["error"] == "password"): ?>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Contraseña incorrecta',
                    text: 'La contraseña ingresada no es correcta.',
                    confirmButtonColor: '#1a1f5e'
                });
            </script>        <?php endif; ?>
        <?php if ($_GET["error"] == "google_creacion"): ?>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo crear la cuenta',
                    text: 'No pudimos completar el registro con Google. Intentá nuevamente.',
                    confirmButtonColor: '#1a1f5e'
                });
            </script>        <?php endif; ?>
        <?php if ($_GET["error"] == "cuenta_inactiva"): ?>
            <script>
                Swal.fire({ icon: 'warning', title: 'Cuenta inactiva', text: 'Tu cuenta profesional está deshabilitada. Contactá con administración.' });
            </script>        <?php endif; ?>
        <?php if ($_GET["error"] == "google_invalido"): ?>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo iniciar con Google',
                    text: 'La autenticación de Google no pudo validarse. Intentá nuevamente.',
                    confirmButtonColor: '#1a1f5e'
                });
            </script>        <?php endif; ?>
    <?php endif; ?>
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
    </script>    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>    <script>
        window.addEventListener('load', function () {

            if (!window.google || !google.accounts || !google.accounts.id) {
                return;
            }

            google.accounts.id.initialize({
                client_id: '785496114191-s89mvrpaa4k9sntikpfrdvjmlbno07b1.apps.googleusercontent.com',
                callback: manejarInicioGoogle
            });

            google.accounts.id.renderButton(
                document.getElementById('google-signin-button'),
                {
                    theme: 'outline',
                    size: 'large',
                    shape: 'rectangular',
                    text: 'continue_with',
                    logo_alignment: 'left',
                    width: 360
                }
            );
        });

        function manejarInicioGoogle(respuesta) {

            if (!respuesta || !respuesta.credential) {
                window.location.href = 'index.php?error=google_invalido';
                return;
            }

            const formulario = document.createElement('form');
            formulario.method = 'POST';
            formulario.action = 'google_login.php';

            const credential = document.createElement('input');
            credential.type = 'hidden';
            credential.name = 'credential';
            credential.value = respuesta.credential;
            formulario.appendChild(credential);

            const recordarme = document.createElement('input');
            recordarme.type = 'hidden';
            recordarme.name = 'recordarme';
            recordarme.value = document.getElementById('recordarme')?.checked ? '1' : '0';
            formulario.appendChild(recordarme);

            document.body.appendChild(formulario);
            formulario.submit();
        }
    </script></body>
</html>
