<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once '../config/conexion.php';
require_once '../config/suscripciones.php';

if (!empty($_SESSION['logueado'])) {
    if (($_SESSION['tipo_usuario'] ?? '') === 'veterinario') {
        header('Location: mi_suscripcion.php');
    } else {
        header('Location: ../propietarios/inicio_propietarios.php');
    }
    exit;
}

$plan = obtenerPlanProfesional($conn);

if (!$plan) {
    exit('No hay un plan profesional disponible.');
}

if (empty($_SESSION['csrf_registro_vet'])) {
    $_SESSION['csrf_registro_vet'] = bin2hex(random_bytes(32));
}

$errores = [];

$datos = [
    'nombre' => '',
    'apellido' => '',
    'matricula' => '',
    'especialidad' => '',
    'telefono' => '',
    'email' => '',
    'localidad' => '',
    'sexo' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach (array_keys($datos) as $campo) {
        $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_registro_vet'], $token)) {
        $errores[] = 'La solicitud no es válida. Recargá la página.';
    }

    if ($datos['nombre'] === '' || $datos['apellido'] === '') {
        $errores[] = 'Completá nombre y apellido.';
    }

    if ($datos['matricula'] === '') {
        $errores[] = 'Ingresá tu matrícula profesional.';
    }

    if ($datos['especialidad'] === '') {
        $errores[] = 'Ingresá tu especialidad.';
    }

    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresá un correo electrónico válido.';
    }

    if (!in_array($datos['sexo'], ['M', 'F'], true)) {
        $errores[] = 'Seleccioná una opción en sexo.';
    }

    if (mb_strlen($password) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    if ($password !== $password2) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {

        $email = mb_strtolower($datos['email']);

        $stmt = $conn->prepare("
            SELECT id_propietario
            FROM propietarios
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $existePropietario = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT id_veterinario
            FROM veterinarios
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $existeVeterinario = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existePropietario || $existeVeterinario) {

            $errores[] = 'Ya existe una cuenta registrada con ese correo.';

        } else {

            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Se activa automáticamente con el primer pago aprobado.
            $activo = 0;

            $stmt = $conn->prepare("
                INSERT INTO veterinarios
                    (
                        nombre,
                        apellido,
                        especialidad,
                        matricula,
                        telefono,
                        localidad,
                        email,
                        password,
                        activo,
                        sexo
                    )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                'ssssssssis',
                $datos['nombre'],
                $datos['apellido'],
                $datos['especialidad'],
                $datos['matricula'],
                $datos['telefono'],
                $datos['localidad'],
                $email,
                $hash,
                $activo,
                $datos['sexo']
            );

            $stmt->execute();

            $idVeterinario = (int) $conn->insert_id;

            $stmt->close();

            $idPlan = (int) $plan['id_plan'];

            $stmt = $conn->prepare("
                INSERT INTO suscripciones
                    (
                        id_veterinario,
                        id_plan,
                        estado
                    )
                VALUES (?, ?, 'pendiente_pago')
            ");

            $stmt->bind_param(
                'ii',
                $idVeterinario,
                $idPlan
            );

            $stmt->execute();
            $stmt->close();

            $_SESSION['logueado'] = true;
            $_SESSION['tipo_usuario'] = 'veterinario';
            $_SESSION['usuario_id'] = $idVeterinario;
            $_SESSION['usuario_nombre'] = $datos['nombre'];
            $_SESSION['usuario_apellido'] = $datos['apellido'];
            $_SESSION['usuario_email'] = $email;
            $_SESSION['usuario_sexo'] = $datos['sexo'];

            header('Location: checkout_demo.php?primer_pago=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta profesional · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos/suscripciones.css">
</head>

<body class="sus-page">

<nav class="plan-navbar">
    <div class="container plan-navbar-inner">

        <a href="plan_profesional.php" class="plan-marca">
            <img src="../img/VetClinic Pro.png" alt="VetClinic Pro">
            <div>
                <span class="plan-marca-nombre">VetClinic Pro</span>
                <span class="plan-marca-subtitulo">Cuenta profesional</span>
            </div>
        </a>

        <a href="plan_profesional.php" class="sus-top-link">
            <i class="bi bi-arrow-left"></i>
            Volver al plan
        </a>

    </div>
</nav>

<main class="sus-registro-main">
    <div class="container">

        <div class="sus-stepper">
            <div class="sus-step activo">
                <span>1</span>
                <div>
                    <strong>Datos profesionales</strong>
                    <small>Creá tu cuenta</small>
                </div>
            </div>

            <div class="sus-step-linea"></div>

            <div class="sus-step">
                <span>2</span>
                <div>
                    <strong>Primer pago</strong>
                    <small>Activá el acceso</small>
                </div>
            </div>
        </div>

        <div class="row g-4 g-xl-5 align-items-start">

            <div class="col-lg-8">

                <section class="sus-surface sus-registro-card">

                    <div class="sus-section-kicker">Alta profesional</div>

                    <h1>Creá tu cuenta de veterinario</h1>

                    <p class="sus-intro">
                        Completá tus datos profesionales. Después vas a pasar al checkout
                        de demostración para activar automáticamente tu suscripción.
                    </p>

                    <?php if ($errores): ?>
                        <div class="sus-error-box">
                            <div class="sus-error-icon">
                                <i class="bi bi-exclamation-circle-fill"></i>
                            </div>
                            <div>
                                <strong>Revisá los siguientes datos</strong>
                                <?php foreach ($errores as $error): ?>
                                    <span><?= htmlspecialchars($error) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="row g-3 sus-form" id="formRegistroVeterinario">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_registro_vet']) ?>">

                        <div class="col-md-6">
                            <label class="form-label" for="nombre">
                                Nombre <span>*</span>
                            </label>
                            <div class="sus-input-wrap">
                                <i class="bi bi-person"></i>
                                <input
                                    class="form-control"
                                    id="nombre"
                                    name="nombre"
                                    required
                                    autocomplete="given-name"
                                    value="<?= htmlspecialchars($datos['nombre']) ?>"
                                    placeholder="Ej: Juan">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="apellido">
                                Apellido <span>*</span>
                            </label>
                            <div class="sus-input-wrap">
                                <i class="bi bi-person"></i>
                                <input
                                    class="form-control"
                                    id="apellido"
                                    name="apellido"
                                    required
                                    autocomplete="family-name"
                                    value="<?= htmlspecialchars($datos['apellido']) ?>"
                                    placeholder="Ej: Pérez">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="matricula">
                                Matrícula profesional <span>*</span>
                            </label>
                            <div class="sus-input-wrap">
                                <i class="bi bi-person-vcard"></i>
                                <input
                                    class="form-control"
                                    id="matricula"
                                    name="matricula"
                                    required
                                    value="<?= htmlspecialchars($datos['matricula']) ?>"
                                    placeholder="Ej: MP 12345">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="especialidad">
                                Especialidad <span>*</span>
                            </label>
                            <div class="sus-input-wrap">
                                <i class="bi bi-heart-pulse"></i>
                                <input
                                    class="form-control"
                                    id="especialidad"
                                    name="especialidad"
                                    required
                                    value="<?= htmlspecialchars($datos['especialidad']) ?>"
                                    placeholder="Ej: Clínica general">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="telefono">Teléfono</label>
                            <div class="sus-input-wrap">
                                <i class="bi bi-telephone"></i>
                                <input
                                    class="form-control"
                                    id="telefono"
                                    name="telefono"
                                    autocomplete="tel"
                                    value="<?= htmlspecialchars($datos['telefono']) ?>"
                                    placeholder="Ej: 3534 123456">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="localidad">Localidad</label>
                            <div class="sus-input-wrap">
                                <i class="bi bi-geo-alt"></i>
                                <input
                                    class="form-control"
                                    id="localidad"
                                    name="localidad"
                                    value="<?= htmlspecialchars($datos['localidad']) ?>"
                                    placeholder="Ej: Bell Ville">
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label" for="email">
                                Correo electrónico <span>*</span>
                            </label>
                            <div class="sus-input-wrap">
                                <i class="bi bi-envelope"></i>
                                <input
                                    type="email"
                                    class="form-control"
                                    id="email"
                                    name="email"
                                    required
                                    autocomplete="email"
                                    value="<?= htmlspecialchars($datos['email']) ?>"
                                    placeholder="tu@email.com">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="sexo">
                                Sexo <span>*</span>
                            </label>
                            <select class="form-select" id="sexo" name="sexo" required>
                                <option value="">Seleccionar</option>
                                <option value="F" <?= $datos['sexo'] === 'F' ? 'selected' : '' ?>>Femenino</option>
                                <option value="M" <?= $datos['sexo'] === 'M' ? 'selected' : '' ?>>Masculino</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="sus-form-divider">
                                <span>Seguridad de la cuenta</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="password">
                                Contraseña <span>*</span>
                            </label>
                            <div class="sus-input-wrap sus-password-wrap">
                                <i class="bi bi-lock"></i>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    required
                                    minlength="6"
                                    autocomplete="new-password"
                                    placeholder="Mínimo 6 caracteres">
                                <button
                                    type="button"
                                    class="sus-eye"
                                    data-target="password"
                                    aria-label="Mostrar contraseña">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="password2">
                                Confirmar contraseña <span>*</span>
                            </label>
                            <div class="sus-input-wrap sus-password-wrap">
                                <i class="bi bi-lock-fill"></i>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password2"
                                    name="password2"
                                    required
                                    minlength="6"
                                    autocomplete="new-password"
                                    placeholder="Repetí tu contraseña">
                                <button
                                    type="button"
                                    class="sus-eye"
                                    data-target="password2"
                                    aria-label="Mostrar contraseña">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="sus-terms">
                                <input class="form-check-input" type="checkbox" id="terminos" required>
                                <label class="form-check-label" for="terminos">
                                    Acepto las condiciones de contratación y entiendo que el pago
                                    funciona en un entorno de demostración sin cobros reales.
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <button class="plan-btn plan-btn-principal sus-submit" type="submit">
                                Continuar al pago
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>

                    </form>

                </section>

            </div>

            <div class="col-lg-4">

                <aside class="sus-sticky">

                    <div class="sus-plan-summary">

                        <div class="sus-plan-summary-top">
                            <div>
                                <span>Tu plan</span>
                                <h2><?= htmlspecialchars($plan['nombre']) ?></h2>
                            </div>
                            <div class="sus-plan-icon">
                                <i class="bi bi-stars"></i>
                            </div>
                        </div>

                        <div class="sus-plan-price">
                            <?= formatoDinero((float) $plan['precio_mensual']) ?>
                            <small>/ mes</small>
                        </div>

                        <div class="sus-plan-feature">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Acceso profesional completo</span>
                        </div>

                        <div class="sus-plan-feature">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Activación automática al aprobarse el primer pago</span>
                        </div>

                        <div class="sus-plan-feature">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>
                                <?= (int) $plan['dias_tolerancia'] ?>
                                días de tolerancia luego del vencimiento
                            </span>
                        </div>

                        <div class="sus-plan-feature">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Sin permanencia</span>
                        </div>

                        <div class="sus-plan-summary-note">
                            <i class="bi bi-shield-check"></i>
                            <span>
                                Tus datos profesionales quedan asociados únicamente
                                a tu cuenta de VetClinic Pro.
                            </span>
                        </div>

                    </div>

                    <div class="sus-help-card">
                        <i class="bi bi-info-circle"></i>
                        <div>
                            <strong>¿Qué pasa después?</strong>
                            <span>
                                Al continuar vas a elegir un medio de pago de demostración.
                                Si seleccionás “aprobado”, tu acceso queda habilitado inmediatamente.
                            </span>
                        </div>
                    </div>

                </aside>

            </div>

        </div>

    </div>
</main>

<script>
document.querySelectorAll('.sus-eye').forEach(function (boton) {
    boton.addEventListener('click', function () {
        const input = document.getElementById(boton.dataset.target);
        const icono = boton.querySelector('i');

        if (!input) {
            return;
        }

        const mostrar = input.type === 'password';

        input.type = mostrar ? 'text' : 'password';
        icono.className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
});
</script>

</body>
</html>
