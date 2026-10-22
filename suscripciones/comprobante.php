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
$idPago = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT
        p.*,
        pl.nombre AS nombre_plan,
        v.nombre,
        v.apellido
    FROM pagos p
    INNER JOIN planes pl
        ON pl.id_plan = p.id_plan
    INNER JOIN veterinarios v
        ON v.id_veterinario = p.id_veterinario
    WHERE p.id_pago = ?
      AND p.id_veterinario = ?
    LIMIT 1
");

$stmt->bind_param(
    'ii',
    $idPago,
    $idVeterinario
);

$stmt->execute();

$pago = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$pago) {
    http_response_code(404);
    exit('Comprobante no encontrado.');
}

$estado = (string) $pago['estado'];

$configEstado = match ($estado) {

    'aprobado' => [
        'icono' => 'bi-check-lg',
        'clase' => 'aprobado',
        'titulo' => 'Pago aprobado',
        'texto' =>
        'Tu suscripción fue actualizada correctamente.'
    ],

    'pendiente' => [
        'icono' => 'bi-hourglass-split',
        'clase' => 'pendiente',
        'titulo' => 'Pago pendiente',
        'texto' =>
        'La suscripción se actualizará cuando el pago sea aprobado.'
    ],

    default => [
        'icono' => 'bi-x-lg',
        'clase' => 'rechazado',
        'titulo' => 'Pago rechazado',
        'texto' =>
        'No se realizó ningún cobro. Podés volver a intentarlo.'
    ]
};

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($configEstado['titulo']) ?>
        · VetClinic Pro
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
<body class="sus-page sus-receipt-page">
    <nav class="plan-navbar">
        <div class="container plan-navbar-inner">
            <a
                href="mi_suscripcion.php"
                class="plan-marca">
                <img
                    src="../img/VetClinic Pro.png"
                    alt="VetClinic Pro">
                <div>
                    <span class="plan-marca-nombre">
                        VetClinic Pro
                    </span>
                    <span class="plan-marca-subtitulo">
                        Comprobante
                    </span>
                </div>
            </a>
            <a
                href="mi_suscripcion.php"
                class="sus-top-link no-print">
                <i class="bi bi-arrow-left"></i>
                Mi suscripción
            </a>
        </div>
    </nav>
    <main class="sus-receipt-main">
        <div class="container">
            <div class="sus-receipt-shell">
                <section
                    class="sus-receipt-status <?= htmlspecialchars(
                                                    $configEstado['clase']
                                                ) ?>">
                    <div class="sus-receipt-status-icon">
                        <i class="bi <?= htmlspecialchars(
                                            $configEstado['icono']
                                        ) ?>"></i>
                    </div>
                    <span>
                        Resultado de la operación
                    </span>
                    <h1>
                        <?= htmlspecialchars(
                            $configEstado['titulo']
                        ) ?>
                    </h1>
                    <p>
                        <?= htmlspecialchars(
                            $configEstado['texto']
                        ) ?>
                    </p>
                </section>
                <section class="sus-receipt-card">
                    <div class="sus-receipt-brand">
                        <div class="plan-marca">
                            <img
                                src="../img/VetClinic Pro.png"
                                alt="VetClinic Pro">
                            <div>
                                <span class="plan-marca-nombre">
                                    VetClinic Pro
                                </span>
                                <span class="plan-marca-subtitulo">
                                    Comprobante de suscripción
                                </span>
                            </div>
                        </div>
                        <span class="sus-receipt-demo">
                            DEMO
                        </span>
                    </div>
                    <div class="sus-receipt-number">
                        <span>
                            Número de operación
                        </span>
                        <strong>
                            <?= htmlspecialchars(
                                $pago['numero_operacion']
                            ) ?>
                        </strong>
                    </div>
                    <div class="sus-receipt-grid">
                        <div>
                            <span>
                                Profesional
                            </span>
                            <strong>
                                <?= htmlspecialchars(
                                    trim(
                                        $pago['nombre']
                                            . ' '
                                            . $pago['apellido']
                                    )
                                ) ?>
                            </strong>
                        </div>
                        <div>
                            <span>
                                Plan
                            </span>
                            <strong>
                                <?= htmlspecialchars(
                                    $pago['nombre_plan']
                                ) ?>
                            </strong>
                        </div>
                        <div>
                            <span>
                                Fecha
                            </span>
                            <strong>
                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $pago['fecha_operacion']
                                    )
                                ) ?>
                            </strong>
                        </div>
                        <div>
                            <span>
                                Medio de pago
                            </span>
                            <strong>
                                <?= htmlspecialchars(
                                    ucfirst(
                                        $pago['medio_pago']
                                    )
                                ) ?>
                            </strong>
                        </div>
                    </div>
                    <div class="sus-receipt-amount">
                        <span>
                            Total
                        </span>
                        <strong>
                            <?= formatoDinero(
                                (float) $pago['importe']
                            ) ?>
                        </strong>
                    </div>
                    <div class="sus-receipt-result">
                        <span>
                            Estado
                        </span>
                        <strong
                            class="<?= htmlspecialchars(
                                        $configEstado['clase']
                                    ) ?>">
                            <i class="bi <?= htmlspecialchars(
                                                $configEstado['icono']
                                            ) ?>"></i>
                            <?= htmlspecialchars(
                                ucfirst($estado)
                            ) ?>
                        </strong>
                    </div>
                    <?php if (
                        !empty($pago['periodo_desde']) ||
                        !empty($pago['periodo_hasta'])
                    ): ?>
                        <div class="sus-receipt-period">
                            <i class="bi bi-calendar3"></i>
                            <div>
                                <span>
                                    Período asociado
                                </span>
                                <strong>
                                    <?= !empty($pago['periodo_desde'])
                                        ? date(
                                            'd/m/Y',
                                            strtotime(
                                                $pago['periodo_desde']
                                            )
                                        )
                                        : '—'
                                    ?>
                                    —
                                    <?= !empty($pago['periodo_hasta'])
                                        ? date(
                                            'd/m/Y',
                                            strtotime(
                                                $pago['periodo_hasta']
                                            )
                                        )
                                        : '—'
                                    ?>
                                </strong>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="sus-receipt-warning">
                        <i class="bi bi-flask"></i>
                        <span>
                            Comprobante generado en un entorno de demostración.
                            Esta operación no representa un cobro bancario real.
                        </span>
                    </div>
                </section>
                <div class="sus-receipt-actions no-print">
                    <a
                        class="plan-btn plan-btn-principal"
                        href="mi_suscripcion.php">
                        Ir a Mi suscripción
                        <i class="bi bi-arrow-right"></i>
                    </a>
                    <button
                        type="button"
                        class="plan-btn plan-btn-secundario"
                        onclick="window.print();">
                        <i class="bi bi-printer"></i>
                        Imprimir
                    </button>
                    <?php if ($estado !== 'aprobado'): ?>
                        <a
                            class="plan-btn plan-btn-secundario"
                            href="checkout_demo.php">
                            Intentar nuevamente
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>