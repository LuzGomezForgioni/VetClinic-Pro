<?php
session_start();
require_once '../config/seguridad.php';


date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once '../config/conexion.php';
require_once '../config/suscripciones.php';

if (
    empty($_SESSION['logueado']) ||
    ($_SESSION['tipo_usuario'] ?? '') !== 'veterinario'
) {
    header('Location: ../index.php');
    exit;
}

$idVeterinario = (int) ($_SESSION['usuario_id'] ?? 0);

$suscripcion = actualizarEstadoSuscripcion(
    $conn,
    $idVeterinario
);

$stmt = $conn->prepare("
    SELECT activo
    FROM veterinarios
    WHERE id_veterinario = ?
    LIMIT 1
");

$stmt->bind_param('i', $idVeterinario);
$stmt->execute();

$estadoCuenta = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (
    $suscripcion &&
    (
        (
            (int) ($estadoCuenta['activo'] ?? 0) === 0 &&
            $suscripcion['estado'] !== 'pendiente_pago'
        )
        ||
        (int) ($suscripcion['suspension_manual'] ?? 0) === 1
    )
) {
    header('Location: mi_suscripcion.php');
    exit;
}

if (!$suscripcion) {
    exit(
        'No encontramos una suscripción asociada a tu cuenta.'
    );
}

$stmt = $conn->prepare("
    SELECT
        nombre,
        apellido,
        email
    FROM veterinarios
    WHERE id_veterinario = ?
    LIMIT 1
");

$stmt->bind_param('i', $idVeterinario);
$stmt->execute();

$veterinario = $stmt->get_result()->fetch_assoc();

$stmt->close();

$primerPago =
    isset($_GET['primer_pago']) ||
    $suscripcion['estado'] === 'pendiente_pago';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">
    <title>Pago de suscripción · VetClinic Pro</title>
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
        href="../estilos/suscripciones.css">
</head>
<body class="sus-page sus-checkout-page">
<nav class="plan-navbar">
    <div class="container plan-navbar-inner">
        <a href="mi_suscripcion.php" class="plan-marca">
            <img src="../img/VetClinic Pro.png" alt="VetClinic Pro">
            <div>
                <span class="plan-marca-nombre">
                    VetClinic Pro
                </span>
                <span class="plan-marca-subtitulo">
                    Checkout seguro
                </span>
            </div>
        </a>
        <a
            href="mi_suscripcion.php"
            class="sus-top-link">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>
    </div>
</nav>
<main class="sus-checkout-main">
    <div class="container">
        <?php if ($primerPago): ?>
            <div class="sus-stepper sus-stepper-checkout">
                <div class="sus-step completado">
                    <span>
                        <i class="bi bi-check-lg"></i>
                    </span>
                    <div>
                        <strong>Datos profesionales</strong>
                        <small>Cuenta creada</small>
                    </div>
                </div>
                <div class="sus-step-linea completada"></div>
                <div class="sus-step activo">
                    <span>2</span>
                    <div>
                        <strong>Primer pago</strong>
                        <small>Activá el acceso</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="sus-checkout-heading">
            <div>
                <div class="sus-section-kicker">
                    <?= $primerPago
                        ? 'Último paso'
                        : 'Renovación'
                    ?>
                </div>
                <h1>
                    <?= $primerPago
                        ? 'Activá tu suscripción'
                        : 'Renová tu suscripción'
                    ?>
                </h1>
                <p>
                    Elegí cómo querés pagar. Esta pantalla reproduce
                    un checkout completo, pero no realiza cargos reales.
                </p>
            </div>
            <div class="sus-secure-badge">
                <i class="bi bi-shield-lock-fill"></i>
                <div>
                    <strong>
                        Entorno protegido
                    </strong>
                    <span>
                        Datos bancarios no almacenados
                    </span>
                </div>
            </div>
        </div>
        <div class="row g-4 g-xl-5 align-items-start">
            <div class="col-lg-7">
                <section class="sus-surface sus-payment-card">
                    <div class="sus-payment-section-head">
                        <div>
                            <span>
                                Medio de pago
                            </span>
                            <h2>
                                ¿Cómo querés pagar?
                            </h2>
                        </div>
                        <i class="bi bi-credit-card-2-front"></i>
                    </div>
                    <div
                        class="sus-payment-methods"
                        id="metodos">
                        <button
                            type="button"
                            class="sus-payment-method activo"
                            data-metodo="credito">
                            <div class="sus-payment-method-icon">
                                <i class="bi bi-credit-card"></i>
                            </div>
                            <div>
                                <strong>
                                    Crédito
                                </strong>
                                <span>
                                    Tarjeta de prueba
                                </span>
                            </div>
                            <i class="bi bi-check-circle-fill sus-method-check"></i>
                        </button>
                        <button
                            type="button"
                            class="sus-payment-method"
                            data-metodo="debito">
                            <div class="sus-payment-method-icon">
                                <i class="bi bi-credit-card-2-front"></i>
                            </div>
                            <div>
                                <strong>
                                    Débito
                                </strong>
                                <span>
                                    Tarjeta de prueba
                                </span>
                            </div>
                            <i class="bi bi-check-circle-fill sus-method-check"></i>
                        </button>
                        <button
                            type="button"
                            class="sus-payment-method"
                            data-metodo="billetera">
                            <div class="sus-payment-method-icon">
                                <i class="bi bi-phone"></i>
                            </div>
                            <div>
                                <strong>
                                    Billetera virtual
                                </strong>
                                <span>
                                    Flujo externo simulado
                                </span>
                            </div>
                            <i class="bi bi-check-circle-fill sus-method-check"></i>
                        </button>
                    </div>
                    <form
                        method="POST"
                        action="procesar_pago_demo.php"
                        id="formPago">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input
                            type="hidden"
                            name="medio_pago"
                            id="medio_pago"
                            value="credito">
                        <input
                            type="hidden"
                            name="resultado_demo"
                            id="resultado_demo"
                            value="aprobado">
                        <div
                            class="sus-payment-fields"
                            id="datosTarjeta">
                            <div class="sus-payment-fields-title">
                                <span>
                                    Datos de la tarjeta
                                </span>
                                <div>
                                    <i class="bi bi-shield-check"></i>
                                    Solo datos ficticios
                                </div>
                            </div>
                            <div class="mb-3">
                                <label
                                    class="form-label"
                                    for="numero_tarjeta">
                                    Número de tarjeta de prueba
                                </label>
                                <div class="sus-card-number">
                                    <input
                                        class="form-control"
                                        id="numero_tarjeta"
                                        inputmode="numeric"
                                        value="4509 9535 6623 3704"
                                        maxlength="19">
                                    <i class="bi bi-credit-card-fill"></i>
                                </div>
                                <div class="sus-field-help">
                                    <i class="bi bi-info-circle"></i>
                                    No ingreses una tarjeta real.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">
                                    Nombre del titular
                                </label>
                                <input
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        trim(
                                            ($veterinario['nombre'] ?? '')
                                            . ' '
                                            . ($veterinario['apellido'] ?? '')
                                        )
                                    ) ?>">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        Vencimiento
                                    </label>
                                    <input
                                        class="form-control"
                                        value="12/30"
                                        maxlength="5">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        Código de seguridad
                                    </label>
                                    <input
                                        class="form-control"
                                        value="123"
                                        maxlength="3">
                                </div>
                            </div>
                        </div>
                        <div
                            id="datosBilletera"
                            class="sus-wallet-box d-none">
                            <div class="sus-wallet-logo">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <div>
                                <span class="sus-wallet-label">
                                    Billetera virtual
                                </span>
                                <h3>
                                    Continuá con tu cuenta de prueba
                                </h3>
                                <p>
                                    Simulamos la redirección a una
                                    plataforma externa de pagos.
                                </p>
                            </div>
                            <label class="form-label">
                                Correo asociado
                            </label>
                            <input
                                type="email"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $veterinario['email']
                                    ?? 'demo@ejemplo.com'
                                ) ?>">
                        </div>
                        <div class="sus-demo-control">
                            <div class="sus-demo-control-head">
                                <div>
                                    <i class="bi bi-flask"></i>
                                    <span>
                                        Control de demostración
                                    </span>
                                </div>
                                <small>
                                    Solo para pruebas
                                </small>
                            </div>
                            <p>
                                Elegí qué respuesta querés simular para
                                comprobar el comportamiento de la suscripción.
                            </p>
                            <div class="sus-demo-options">
                                <button
                                    type="button"
                                    class="sus-demo-option aprobado activo"
                                    data-resultado="aprobado">
                                    <i class="bi bi-check-circle-fill"></i>
                                    Pago aprobado
                                </button>
                                <button
                                    type="button"
                                    class="sus-demo-option pendiente"
                                    data-resultado="pendiente">
                                    <i class="bi bi-hourglass-split"></i>
                                    Pago pendiente
                                </button>
                                <button
                                    type="button"
                                    class="sus-demo-option rechazado"
                                    data-resultado="rechazado">
                                    <i class="bi bi-x-circle-fill"></i>
                                    Pago rechazado
                                </button>
                            </div>
                        </div>
                        <button
                            class="plan-btn plan-btn-principal sus-pay-button"
                            type="submit">
                            <i class="bi bi-lock-fill"></i>
                            Pagar
                            <?= formatoDinero(
                                (float) $suscripcion['precio_mensual']
                            ) ?>
                        </button>
                        <div class="sus-payment-disclaimer">
                            <i class="bi bi-shield-check"></i>
                            <span>
                                Esta operación es ficticia.
                                VetClinic Pro no procesa ni almacena
                                información bancaria real.
                            </span>
                        </div>
                    </form>
                </section>
            </div>
            <div class="col-lg-5">
                <aside class="sus-sticky">
                    <div class="sus-order-card">
                        <div class="sus-order-head">
                            <div>
                                <span>
                                    Resumen de compra
                                </span>
                                <h2>
                                    <?= htmlspecialchars(
                                        $suscripcion['nombre_plan']
                                    ) ?>
                                </h2>
                            </div>
                            <div class="sus-order-icon">
                                <i class="bi bi-stars"></i>
                            </div>
                        </div>
                        <div class="sus-order-row">
                            <span>
                                Mensualidad
                            </span>
                            <strong>
                                <?= formatoDinero(
                                    (float) $suscripcion['precio_mensual']
                                ) ?>
                            </strong>
                        </div>
                        <div class="sus-order-row">
                            <span>
                                Período
                            </span>
                            <strong>
                                1 mes
                            </strong>
                        </div>
                        <div class="sus-order-row">
                            <span>
                                Tolerancia
                            </span>
                            <strong>
                                <?= (int) $suscripcion['dias_tolerancia'] ?>
                                días
                            </strong>
                        </div>
                        <div class="sus-order-total">
                            <span>
                                Total
                            </span>
                            <strong>
                                <?= formatoDinero(
                                    (float) $suscripcion['precio_mensual']
                                ) ?>
                            </strong>
                        </div>
                        <div class="sus-order-note">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>
                                Al aprobarse el pago, la suscripción
                                se actualiza automáticamente y se
                                calcula el próximo vencimiento.
                            </span>
                        </div>
                    </div>
                    <div class="sus-security-card">
                        <div>
                            <i class="bi bi-lock"></i>
                        </div>
                        <span>
                            Los campos de tarjeta y billetera son
                            únicamente visuales y no se envían a la
                            base de datos.
                        </span>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</main>
<script>
const metodos =
    document.querySelectorAll(
        '.sus-payment-method'
    );

const medioInput =
    document.getElementById(
        'medio_pago'
    );

const datosTarjeta =
    document.getElementById(
        'datosTarjeta'
    );

const datosBilletera =
    document.getElementById(
        'datosBilletera'
    );

metodos.forEach(function (elemento) {

    elemento.addEventListener(
        'click',
        function () {

            metodos.forEach(
                function (item) {
                    item.classList.remove('activo');
                }
            );

            elemento.classList.add('activo');

            const metodo =
                elemento.dataset.metodo;

            medioInput.value =
                metodo;

            datosTarjeta.classList.toggle(
                'd-none',
                metodo === 'billetera'
            );

            datosBilletera.classList.toggle(
                'd-none',
                metodo !== 'billetera'
            );

        }
    );

});

const resultadoInput =
    document.getElementById(
        'resultado_demo'
    );

document.querySelectorAll(
    '.sus-demo-option'
).forEach(function (boton) {

    boton.addEventListener(
        'click',
        function () {

            resultadoInput.value =
                boton.dataset.resultado;

            document.querySelectorAll(
                '.sus-demo-option'
            ).forEach(
                function (item) {
                    item.classList.remove('activo');
                }
            );

            boton.classList.add('activo');

        }
    );

});
</script></body>
</html>
