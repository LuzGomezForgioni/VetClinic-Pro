<?php

session_start();

require_once 'config/conexion.php';
require_once 'config/suscripciones.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

const GOOGLE_CLIENT_ID = '785496114191-s89mvrpaa4k9sntikpfrdvjmlbno07b1.apps.googleusercontent.com';

// ============================================================
// FUNCIONES PARA "RECORDARME"
// ============================================================

function conexionSegura(): bool
{
    return
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function guardarCookieRecordarme(
    string $selector,
    string $token,
    int $expira
): void {
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

function crearSesionRecordada(
    mysqli $conn,
    string $tipoUsuario,
    int $idUsuario
): void {
    $conn->query("
        DELETE FROM sesiones_recordadas
        WHERE fecha_expiracion < NOW()
    ");

    $selector = bin2hex(random_bytes(8));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

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
    $_SESSION['logueado'] = true;
    $_SESSION['tipo_usuario'] = $tipoUsuario;

    if ($tipoUsuario === 'propietario') {
        $_SESSION['usuario_id'] = $usuario['id_propietario'];
    } else {
        $_SESSION['usuario_id'] = $usuario['id_veterinario'];
    }

    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_apellido'] = $usuario['apellido'];
    $_SESSION['usuario_email'] = $usuario['email'];

    if ($tipoUsuario === 'veterinario') {
        $_SESSION['usuario_sexo'] =
            strtoupper(trim($usuario['sexo'] ?? ''));
    }
}

function redirigirInicio(string $tipoUsuario): void
{
    global $conn;

    if ($tipoUsuario === 'propietario') {
        header('Location: propietarios/inicio_propietarios.php');
        exit;
    }

    $idVeterinario = (int) ($_SESSION['usuario_id'] ?? 0);
    $suscripcion = $idVeterinario > 0
        ? actualizarEstadoSuscripcion($conn, $idVeterinario)
        : null;

    if (!suscripcionPermiteUso($suscripcion)) {
        header('Location: suscripciones/mi_suscripcion.php');
    } else {
        header('Location: veterinarios/inicio_veterinario.php');
    }

    exit;
}

// ============================================================
// VALIDAR EL ID TOKEN DE GOOGLE
// ============================================================

function validarTokenGoogle(string $credential): ?array
{
    if ($credential === '') {
        return null;
    }

    $url =
        'https://oauth2.googleapis.com/tokeninfo?id_token=' .
        urlencode($credential);

    // Preferimos cURL porque normalmente viene habilitado en XAMPP.
    if (function_exists('curl_init')) {

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]);

        $respuesta = curl_exec($ch);
        $codigoHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($respuesta === false || $codigoHttp !== 200) {
            return null;
        }

    } else {

        $contexto = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);

        $respuesta = @file_get_contents(
            $url,
            false,
            $contexto
        );

        if ($respuesta === false) {
            return null;
        }
    }

    $datos = json_decode($respuesta, true);

    if (!is_array($datos)) {
        return null;
    }

    // El token tiene que pertenecer exactamente a VetClinic Pro.
    if (($datos['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
        return null;
    }

    $issuer = (string) ($datos['iss'] ?? '');

    if (
        $issuer !== 'accounts.google.com' &&
        $issuer !== 'https://accounts.google.com'
    ) {
        return null;
    }

    if ((int) ($datos['exp'] ?? 0) < time()) {
        return null;
    }

    $emailVerificado =
        filter_var(
            $datos['email_verified'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

    if (!$emailVerificado) {
        return null;
    }

    $email = trim((string) ($datos['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return $datos;
}

// ============================================================
// PROCESAR LOGIN CON GOOGLE
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$credential = trim((string) ($_POST['credential'] ?? ''));
$recordarme = (string) ($_POST['recordarme'] ?? '') === '1';

$datosGoogle = validarTokenGoogle($credential);

if (!$datosGoogle) {
    header('Location: index.php?error=google_invalido');
    exit;
}

$email = trim((string) $datosGoogle['email']);

// ============================================================
// 1. BUSCAR COMO PROPIETARIO
// ============================================================

$stmt = $conn->prepare("
    SELECT *
    FROM propietarios
    WHERE email = ?
    LIMIT 1
");

$stmt->bind_param('s', $email);
$stmt->execute();
$propietario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($propietario) {

    cargarSesionUsuario(
        $propietario,
        'propietario'
    );

    if ($recordarme) {
        crearSesionRecordada(
            $conn,
            'propietario',
            (int) $propietario['id_propietario']
        );
    }

    redirigirInicio('propietario');
}

// ============================================================
// 2. SI NO ES PROPIETARIO, BUSCAR COMO VETERINARIO
// ============================================================

$stmt = $conn->prepare("
    SELECT *
    FROM veterinarios
    WHERE email = ?
    LIMIT 1
");

$stmt->bind_param('s', $email);
$stmt->execute();
$veterinario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($veterinario) {

    if ((int) ($veterinario['activo'] ?? 0) !== 1) {
        header('Location: index.php?error=cuenta_inactiva');
        exit;
    }

    cargarSesionUsuario(
        $veterinario,
        'veterinario'
    );

    if ($recordarme) {
        crearSesionRecordada(
            $conn,
            'veterinario',
            (int) $veterinario['id_veterinario']
        );
    }

    redirigirInicio('veterinario');
}

// ============================================================
// 3. EL CORREO NO EXISTE: CREAR PROPIETARIO AUTOMÁTICAMENTE
// ============================================================
//
// Las altas automáticas con Google son solamente para propietarios.
// Los veterinarios deben tener una cuenta profesional creada/habilitada
// previamente por VetClinic Pro.

$nombreGoogle =
    trim((string) ($datosGoogle['given_name'] ?? ''));

$apellidoGoogle =
    trim((string) ($datosGoogle['family_name'] ?? ''));

// Algunas cuentas de Google pueden no traer nombre y apellido separados.
// En ese caso intentamos obtenerlos desde "name".
if ($nombreGoogle === '') {

    $nombreCompleto =
        trim((string) ($datosGoogle['name'] ?? ''));

    if ($nombreCompleto !== '') {

        $partesNombre =
            preg_split('/\s+/', $nombreCompleto);

        $nombreGoogle =
            array_shift($partesNombre) ?: 'Usuario';

        if ($apellidoGoogle === '' && !empty($partesNombre)) {
            $apellidoGoogle = implode(' ', $partesNombre);
        }
    }
}

if ($nombreGoogle === '') {
    $nombreGoogle = 'Usuario';
}

// Evitar superar el tamaño habitual de los campos.
$nombreGoogle = mb_substr($nombreGoogle, 0, 80);
$apellidoGoogle = mb_substr($apellidoGoogle, 0, 80);

// La persona entra con Google, por lo que no necesita conocer esta clave.
// Guardamos una contraseña aleatoria ya hasheada para mantener compatible
// la estructura actual de la tabla propietarios.
$passwordAleatoria = bin2hex(random_bytes(32));

$passwordHash =
    password_hash(
        $passwordAleatoria,
        PASSWORD_DEFAULT
    );

$telefono = '';

try {

    $stmt = $conn->prepare("
        INSERT INTO propietarios
            (
                nombre,
                apellido,
                email,
                telefono,
                password
            )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'sssss',
        $nombreGoogle,
        $apellidoGoogle,
        $email,
        $telefono,
        $passwordHash
    );

    $stmt->execute();

    $idNuevoPropietario =
        (int) $conn->insert_id;

    $stmt->close();

    // Cargar la cuenta recién creada para utilizar exactamente
    // el mismo formato de sesión que el resto del sistema.
    $stmt = $conn->prepare("
        SELECT *
        FROM propietarios
        WHERE id_propietario = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        'i',
        $idNuevoPropietario
    );

    $stmt->execute();

    $nuevoPropietario =
        $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$nuevoPropietario) {
        header('Location: index.php?error=google_creacion');
        exit;
    }

    cargarSesionUsuario(
        $nuevoPropietario,
        'propietario'
    );

    // Marca útil por si más adelante queremos mostrar
    // un mensaje de bienvenida en el portal del propietario.
    $_SESSION['registro_google_nuevo'] = true;

    if ($recordarme) {
        crearSesionRecordada(
            $conn,
            'propietario',
            $idNuevoPropietario
        );
    }

    redirigirInicio('propietario');

} catch (mysqli_sql_exception $e) {

    // Si por alguna condición de carrera el correo se creó justo antes,
    // volvemos a buscarlo y permitimos el acceso.
    if ($e->getCode() === 1062) {

        $stmt = $conn->prepare("
            SELECT *
            FROM propietarios
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->bind_param('s', $email);
        $stmt->execute();

        $propietarioExistente =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if ($propietarioExistente) {

            cargarSesionUsuario(
                $propietarioExistente,
                'propietario'
            );

            if ($recordarme) {
                crearSesionRecordada(
                    $conn,
                    'propietario',
                    (int) $propietarioExistente['id_propietario']
                );
            }

            redirigirInicio('propietario');
        }
    }

    header('Location: index.php?error=google_creacion');
    exit;
}
