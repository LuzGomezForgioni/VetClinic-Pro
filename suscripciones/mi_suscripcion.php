<?php
session_start();
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

$idVeterinario = (int) $_SESSION['usuario_id'];

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

$vetEstadoCuenta = $stmt->get_result()->fetch_assoc();

$stmt->close();

$cuentaHabilitada =
    (int) ($vetEstadoCuenta['activo'] ?? 0) === 1;

$bloqueoAdministrativo =
    (
        !$cuentaHabilitada &&
        ($suscripcion['estado'] ?? '') !== 'pendiente_pago'
    )
    ||
    (int) ($suscripcion['suspension_manual'] ?? 0) === 1;

if (!$suscripcion) {
    exit(
        'No existe una suscripción asociada a esta cuenta.'
    );
}

if (empty($_SESSION['csrf_suscripcion'])) {
    $_SESSION['csrf_suscripcion'] =
        bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = (string) ($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');

    if (
        hash_equals(
            $_SESSION['csrf_suscripcion'],
            $token
        )
    ) {

        $idSuscripcion =
            (int) $suscripcion['id_suscripcion'];

        if ($accion === 'cancelar') {

            if (
                in_array(
                    $suscripcion['estado'],
                    ['activa', 'atrasada'],
                    true
                )
            ) {

                $stmt = $conn->prepare("
                    UPDATE suscripciones
                    SET
                        cancelacion_fin_periodo = 1,
                        fecha_cancelacion = NOW()
                    WHERE id_suscripcion = ?
                ");

                $stmt->bind_param(
                    'i',
                    $idSuscripcion
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION['msg_suscripcion'] =
                    'La cancelación quedó programada para el final del período abonado.';

            } else {

                $stmt = $conn->prepare("
                    UPDATE suscripciones
                    SET
                        estado = 'cancelada',
                        cancelacion_fin_periodo = 1,
                        fecha_cancelacion = NOW()
                    WHERE id_suscripcion = ?
                ");

                $stmt->bind_param(
                    'i',
                    $idSuscripcion
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION['msg_suscripcion'] =
                    'La suscripción fue cancelada.';
            }
        }

        if ($accion === 'mantener') {

            $stmt = $conn->prepare("
                UPDATE suscripciones
                SET
                    cancelacion_fin_periodo = 0,
                    fecha_cancelacion = NULL
                WHERE id_suscripcion = ?
            ");

            $stmt->bind_param(
                'i',
                $idSuscripcion
            );

            $stmt->execute();
            $stmt->close();

            $_SESSION['msg_suscripcion'] =
                'Tu suscripción continuará normalmente.';
        }
    }

    header('Location: mi_suscripcion.php');
    exit;
}

$suscripcion = actualizarEstadoSuscripcion(
    $conn,
    $idVeterinario
);

$mensaje =
    $_SESSION['msg_suscripcion'] ?? '';

unset(
    $_SESSION['msg_suscripcion']
);

$stmt = $conn->prepare("
    SELECT *
    FROM pagos
    WHERE id_veterinario = ?
    ORDER BY fecha_operacion DESC
    LIMIT 20
");

$stmt->bind_param(
    'i',
    $idVeterinario
);

$stmt->execute();

$pagos =
    $stmt->get_result()->fetch_all(
        MYSQLI_ASSOC
    );

$stmt->close();

$hoy = new DateTimeImmutable('today');

$diasRestantes = null;

if (!empty($suscripcion['fecha_vencimiento'])) {

    $vencimiento =
        new DateTimeImmutable(
            $suscripcion['fecha_vencimiento']
        );

    $diasRestantes =
        (int) $hoy
            ->diff($vencimiento)
            ->format('%r%a');
}

$puedeUsar =
    suscripcionPermiteUso(
        $suscripcion
    );

$estado =
    (string) $suscripcion['estado'];

$textoPrincipal = match ($estado) {

    'activa' =>
        'Tu plan está activo y podés utilizar todas las funciones de VetClinic Pro.',

    'atrasada' =>
        'Tu mensualidad está vencida, pero todavía te encontrás dentro del período de tolerancia.',

    'suspendida' =>
        'El período de tolerancia terminó. Regularizá el pago para recuperar el acceso.',

    'cancelada' =>
        'Tu suscripción se encuentra cancelada. Podés reactivarla abonando una nueva mensualidad.',

    'pendiente_pago' =>
        'Completá el primer pago para activar tu cuenta profesional.',

    default =>
        'Consultá el estado actual de tu suscripción.'
};

$proximoPagoTexto =
    !empty($suscripcion['fecha_vencimiento'])
        ? date(
            'd/m/Y',
            strtotime(
                $suscripcion['fecha_vencimiento']
            )
        )
        : 'Se define al pagar';

$finToleranciaTexto =
    !empty($suscripcion['fecha_fin_tolerancia'])
        ? date(
            'd/m/Y',
            strtotime(
                $suscripcion['fecha_fin_tolerancia']
            )
        )
        : '—';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">
    <title>
        Mi suscripción · VetClinic Pro
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
        href="../estilos/suscripciones.css">
</head>
<body class="sus-page">
<nav class="plan-navbar">
    <div class="container plan-navbar-inner">
        <a
            href="<?= $puedeUsar
                ? '../veterinarios/inicio_veterinario.php'
                : 'mi_suscripcion.php'
            ?>"
            class="plan-marca">
            <img
                src="../img/VetClinic Pro.png"
                alt="VetClinic Pro">
            <div>
                <span class="plan-marca-nombre">
                    VetClinic Pro
                </span>
                <span class="plan-marca-subtitulo">
                    Mi suscripción
                </span>
            </div>
        </a>
        <div class="sus-nav-actions">
            <?php if ($puedeUsar): ?>
                <a
                    href="../veterinarios/inicio_veterinario.php"
                    class="sus-top-link">
                    <i class="bi bi-grid"></i>
                    Volver al sistema
                </a>
            <?php endif; ?>
            <a
                href="../logout.php"
                class="sus-logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Cerrar sesión
            </a>
        </div>
    </div>
</nav>
<main class="sus-account-main">
    <div class="container">
        <?php if ($mensaje): ?>
            <div class="sus-message">
                <i class="bi bi-check-circle-fill"></i>
                <span>
                    <?= htmlspecialchars($mensaje) ?>
                </span>
            </div>
        <?php endif; ?>
        <div class="sus-account-heading">
            <div>
                <div class="sus-section-kicker">
                    Cuenta profesional
                </div>
                <h1>
                    Mi suscripción
                </h1>
                <p>
                    Revisá el estado de tu plan, próximos vencimientos,
                    pagos realizados y opciones de administración.
                </p>
            </div>
            <span
                class="estado-chip sus-account-status <?= htmlspecialchars(
                    claseEstadoSuscripcion($estado)
                ) ?>">
                <i class="bi bi-circle-fill"></i>
                <?= htmlspecialchars(
                    etiquetaEstadoSuscripcion($estado)
                ) ?>
            </span>
        </div>
        <?php if ($bloqueoAdministrativo): ?>
            <div class="sus-state-banner administrativo">
                <div class="sus-state-banner-icon">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div>
                    <strong>
                        Cuenta deshabilitada por administración
                    </strong>
                    <p>
                        Esta cuenta no puede reactivarse automáticamente mediante
                        un pago. Contactate con la administración de VetClinic Pro.
                    </p>
                </div>
            </div>
        <?php elseif ($estado === 'suspendida'): ?>
            <div class="sus-state-banner suspendida">
                <div class="sus-state-banner-icon">
                    <i class="bi bi-lock-fill"></i>
                </div>
                <div>
                    <strong>
                        Suscripción temporalmente suspendida
                    </strong>
                    <p>
                        El período de tolerancia finalizó. Tus datos siguen guardados
                        y recuperarás el acceso automáticamente cuando regularices
                        la mensualidad.
                    </p>
                </div>
                <a
                    href="checkout_demo.php"
                    class="sus-banner-action">
                    Regularizar ahora
                </a>
            </div>
        <?php elseif ($estado === 'atrasada'): ?>
            <div class="sus-state-banner atrasada">
                <div class="sus-state-banner-icon">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div>
                    <strong>
                        Tenés una mensualidad pendiente
                    </strong>
                    <p>
                        Podés seguir usando VetClinic Pro hasta el
                        <b><?= htmlspecialchars($finToleranciaTexto) ?></b>.
                        Después de esa fecha la cuenta se suspenderá automáticamente.
                    </p>
                </div>
                <a
                    href="checkout_demo.php"
                    class="sus-banner-action">
                    Pagar ahora
                </a>
            </div>
        <?php elseif ($estado === 'pendiente_pago'): ?>
            <div class="sus-state-banner pendiente">
                <div class="sus-state-banner-icon">
                    <i class="bi bi-credit-card-fill"></i>
                </div>
                <div>
                    <strong>
                        Falta activar tu cuenta profesional
                    </strong>
                    <p>
                        Completá el primer pago de demostración y el acceso
                        a VetClinic Pro se habilitará inmediatamente.
                    </p>
                </div>
                <a
                    href="checkout_demo.php?primer_pago=1"
                    class="sus-banner-action">
                    Activar cuenta
                </a>
            </div>
        <?php elseif ($estado === 'cancelada'): ?>
            <div class="sus-state-banner cancelada">
                <div class="sus-state-banner-icon">
                    <i class="bi bi-slash-circle-fill"></i>
                </div>
                <div>
                    <strong>
                        Suscripción cancelada
                    </strong>
                    <p>
                        Podés volver a utilizar VetClinic Pro abonando
                        una nueva mensualidad.
                    </p>
                </div>
                <?php if (!$bloqueoAdministrativo): ?>
                    <a
                        href="checkout_demo.php"
                        class="sus-banner-action">
                        Reactivar
                    </a>
                <?php endif; ?>
            </div>
        <?php elseif (
            $diasRestantes !== null &&
            $diasRestantes >= 0 &&
            $diasRestantes <= 7
        ): ?>
            <div class="sus-state-banner proxima">
                <div class="sus-state-banner-icon">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div>
                    <strong>
                        Tu próximo vencimiento está cerca
                    </strong>
                    <p>
                        Vence en
                        <b>
                            <?= $diasRestantes ?>
                            <?= $diasRestantes === 1
                                ? 'día'
                                : 'días'
                            ?>
                        </b>.
                        Podés pagar anticipadamente sin perder tu fecha habitual.
                    </p>
                </div>
                <a
                    href="checkout_demo.php"
                    class="sus-banner-action">
                    Pagar anticipado
                </a>
            </div>
        <?php endif; ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <section class="sus-subscription-hero">
                    <div class="sus-subscription-hero-top">
                        <div>
                            <span>
                                Plan actual
                            </span>
                            <h2>
                                <?= htmlspecialchars(
                                    $suscripcion['nombre_plan']
                                ) ?>
                            </h2>
                            <p>
                                <?= htmlspecialchars(
                                    $textoPrincipal
                                ) ?>
                            </p>
                        </div>
                        <div class="sus-subscription-price">
                            <?= formatoDinero(
                                (float) $suscripcion['precio_mensual']
                            ) ?>
                            <small>
                                / mes
                            </small>
                        </div>
                    </div>
                    <div class="sus-subscription-stats">
                        <div>
                            <div class="sus-stat-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <span>
                                Inicio
                            </span>
                            <strong>
                                <?= $suscripcion['fecha_inicio']
                                    ? date(
                                        'd/m/Y',
                                        strtotime(
                                            $suscripcion['fecha_inicio']
                                        )
                                    )
                                    : 'Pendiente'
                                ?>
                            </strong>
                        </div>
                        <div>
                            <div class="sus-stat-icon">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <span>
                                Próximo vencimiento
                            </span>
                            <strong>
                                <?= htmlspecialchars(
                                    $proximoPagoTexto
                                ) ?>
                            </strong>
                        </div>
                        <div>
                            <div class="sus-stat-icon">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <span>
                                Tolerancia
                            </span>
                            <strong>
                                <?= (int) $suscripcion['dias_tolerancia'] ?>
                                días
                            </strong>
                        </div>
                    </div>
                    <?php if (!$bloqueoAdministrativo): ?>
                        <div class="sus-subscription-actions">
                            <a
                                href="checkout_demo.php"
                                class="plan-btn plan-btn-principal">
                                <i class="bi bi-credit-card"></i>
                                <?= in_array(
                                    $estado,
                                    [
                                        'suspendida',
                                        'cancelada',
                                        'pendiente_pago'
                                    ],
                                    true
                                )
                                    ? 'Activar / regularizar'
                                    : 'Pagar próxima mensualidad'
                                ?>
                            </a>
                            <?php if ($puedeUsar): ?>
                                <a
                                    href="../veterinarios/inicio_veterinario.php"
                                    class="plan-btn plan-btn-secundario">
                                    Volver al panel
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="sus-surface sus-manage-card">
                    <div class="sus-manage-icon">
                        <i class="bi bi-sliders"></i>
                    </div>
                    <h2>
                        Administrar suscripción
                    </h2>
                    <p>
                        La cancelación mantiene el acceso hasta finalizar
                        el período que ya abonaste.
                    </p>
                    <?php if (
                        (int) $suscripcion['cancelacion_fin_periodo'] === 1 &&
                        $estado !== 'cancelada'
                    ): ?>
                        <div class="sus-cancel-pending">
                            <i class="bi bi-calendar-x"></i>
                            <div>
                                <strong>
                                    Cancelación programada
                                </strong>
                                <span>
                                    El plan finalizará al terminar
                                    el período vigente.
                                </span>
                            </div>
                        </div>
                        <form method="POST">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    $_SESSION['csrf_suscripcion']
                                ) ?>">
                            <input
                                type="hidden"
                                name="accion"
                                value="mantener">
                            <button
                                class="sus-manage-button mantener"
                                type="submit">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                Mantener mi suscripción
                            </button>
                        </form>
                    <?php elseif (
                        $estado !== 'cancelada' &&
                        $estado !== 'pendiente_pago'
                    ): ?>
                        <form
                            method="POST"
                            onsubmit="return confirm('¿Querés cancelar al finalizar el período abonado?');">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    $_SESSION['csrf_suscripcion']
                                ) ?>">
                            <input
                                type="hidden"
                                name="accion"
                                value="cancelar">
                            <button
                                class="sus-manage-button cancelar"
                                type="submit">
                                <i class="bi bi-x-circle"></i>
                                Cancelar suscripción
                            </button>
                        </form>
                    <?php endif; ?>
                    <div class="sus-manage-help">
                        <i class="bi bi-headset"></i>
                        <span>
                            ¿Necesitás ayuda con pagos, vencimientos o tu cuenta?
                            Contactate con la administración de VetClinic Pro.
                        </span>
                    </div>
                </section>
            </div>
        </div>
        <section class="sus-surface sus-cycle-card">
            <div class="sus-card-heading">
                <div>
                    <span>
                        Ciclo mensual
                    </span>
                    <h2>
                        Cómo se gestiona tu acceso
                    </h2>
                </div>
                <i class="bi bi-arrow-repeat"></i>
            </div>
            <div class="sus-cycle">
                <div class="sus-cycle-item activo">
                    <div>
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <strong>
                        Suscripción activa
                    </strong>
                    <span>
                        Acceso completo al sistema
                    </span>
                </div>
                <div class="sus-cycle-line"></div>
                <div class="sus-cycle-item">
                    <div>
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <strong>
                        Vencimiento
                    </strong>
                    <span>
                        <?= htmlspecialchars(
                            $proximoPagoTexto
                        ) ?>
                    </span>
                </div>
                <div class="sus-cycle-line"></div>
                <div class="sus-cycle-item">
                    <div>
                        <i class="bi bi-hourglass"></i>
                    </div>
                    <strong>
                        Tolerancia
                    </strong>
                    <span>
                        <?= (int) $suscripcion['dias_tolerancia'] ?>
                        días
                    </span>
                </div>
                <div class="sus-cycle-line"></div>
                <div class="sus-cycle-item">
                    <div>
                        <i class="bi bi-lock"></i>
                    </div>
                    <strong>
                        Suspensión
                    </strong>
                    <span>
                        Solo si no se regulariza
                    </span>
                </div>
            </div>
        </section>
        <section class="sus-surface sus-history-card">
            <div class="sus-card-heading">
                <div>
                    <span>
                        Movimientos
                    </span>
                    <h2>
                        Historial de pagos
                    </h2>
                </div>
                <small>
                    Últimas 20 operaciones
                </small>
            </div>
            <div class="table-responsive">
                <table class="table sus-history-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Operación</th>
                            <th>Medio</th>
                            <th>Importe</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$pagos): ?>
                            <tr>
                                <td colspan="6" class="p-0">
                                    <div class="sus-empty-history">
                                        <i class="bi bi-receipt"></i>
                                        <strong>
                                            Todavía no hay pagos registrados
                                        </strong>
                                        <span>
                                            Las operaciones aparecerán acá
                                            después de realizar tu primer pago.
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($pagos as $pago): ?>
                            <tr>
                                <td>
                                    <div class="sus-history-date">
                                        <strong>
                                            <?= date(
                                                'd/m/Y',
                                                strtotime(
                                                    $pago['fecha_operacion']
                                                )
                                            ) ?>
                                        </strong>
                                        <span>
                                            <?= date(
                                                'H:i',
                                                strtotime(
                                                    $pago['fecha_operacion']
                                                )
                                            ) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="sus-operation-number">
                                        <?= htmlspecialchars(
                                            $pago['numero_operacion']
                                        ) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="sus-payment-label">
                                        <i class="bi <?= $pago['medio_pago'] === 'billetera'
                                            ? 'bi-phone'
                                            : 'bi-credit-card'
                                        ?>"></i>
                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $pago['medio_pago']
                                            )
                                        ) ?>
                                    </div>
                                </td>
                                <td>
                                    <strong class="sus-history-amount">
                                        <?= formatoDinero(
                                            (float) $pago['importe']
                                        ) ?>
                                    </strong>
                                </td>
                                <td>
                                    <span
                                        class="sus-payment-state <?= htmlspecialchars(
                                            $pago['estado']
                                        ) ?>">
                                        <i class="bi <?= $pago['estado'] === 'aprobado'
                                            ? 'bi-check-circle-fill'
                                            : (
                                                $pago['estado'] === 'pendiente'
                                                    ? 'bi-hourglass-split'
                                                    : 'bi-x-circle-fill'
                                            )
                                        ?>"></i>
                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $pago['estado']
                                            )
                                        ) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a
                                        href="comprobante.php?id=<?= (int) $pago['id_pago'] ?>"
                                        class="sus-view-receipt">
                                        Ver comprobante
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <div class="sus-demo-footer-note">
            <i class="bi bi-flask"></i>
            <span>
                Los pagos de esta versión son simulados para fines de demostración
                y no representan movimientos bancarios reales.
            </span>
        </div>
    </div>
</main>
</body>
</html>
