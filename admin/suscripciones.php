<?php

session_start();

require_once '_auth.php';
require_once '../config/conexion.php';
require_once '../config/suscripciones.php';

actualizarTodasSuscripciones($conn);

// ============================================================
// FILTRO
// ============================================================

$estado = trim((string) ($_GET['estado'] ?? ''));

$estadosValidos = [
    'activa',
    'atrasada',
    'suspendida',
    'cancelada',
    'pendiente_pago'
];

if (!in_array($estado, $estadosValidos, true)) {
    $estado = '';
}

// ============================================================
// RESUMEN GENERAL
// ============================================================

$resumen = [
    'total' => 0,
    'activa' => 0,
    'atrasada' => 0,
    'suspendida' => 0,
    'cancelada' => 0,
    'pendiente_pago' => 0,
    'por_vencer' => 0
];

$resultadoResumen = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(estado = 'activa') AS activa,
        SUM(estado = 'atrasada') AS atrasada,
        SUM(estado = 'suspendida') AS suspendida,
        SUM(estado = 'cancelada') AS cancelada,
        SUM(estado = 'pendiente_pago') AS pendiente_pago,
        SUM(
            estado = 'activa'
            AND fecha_vencimiento IS NOT NULL
            AND fecha_vencimiento BETWEEN CURDATE()
            AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ) AS por_vencer
    FROM suscripciones
");

if ($resultadoResumen && ($filaResumen = $resultadoResumen->fetch_assoc())) {

    foreach ($resumen as $clave => $valor) {
        $resumen[$clave] = (int) ($filaResumen[$clave] ?? 0);
    }
}

// ============================================================
// SUSCRIPCIONES
// ============================================================

$sqlBase = "
    SELECT
        s.*,
        v.nombre,
        v.apellido,
        v.email,
        v.matricula,
        v.especialidad,
        p.nombre AS plan_nombre,
        p.precio_mensual
    FROM suscripciones s
    INNER JOIN veterinarios v
        ON v.id_veterinario = s.id_veterinario
    INNER JOIN planes p
        ON p.id_plan = s.id_plan
";

if ($estado !== '') {

    $stmt = $conn->prepare(
        $sqlBase . "
        WHERE s.estado = ?
        ORDER BY
            s.fecha_vencimiento IS NULL,
            s.fecha_vencimiento ASC,
            v.apellido ASC,
            v.nombre ASC
        "
    );

    $stmt->bind_param('s', $estado);
    $stmt->execute();

    $suscripciones =
        $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

} else {

    $resultado = $conn->query(
        $sqlBase . "
        ORDER BY
            FIELD(
                s.estado,
                'suspendida',
                'atrasada',
                'pendiente_pago',
                'activa',
                'cancelada'
            ),
            s.fecha_vencimiento IS NULL,
            s.fecha_vencimiento ASC,
            v.apellido ASC,
            v.nombre ASC
        "
    );

    $suscripciones =
        $resultado
            ? $resultado->fetch_all(MYSQLI_ASSOC)
            : [];
}

// ============================================================
// FUNCIONES DE VISTA
// ============================================================

function fechaSuscripcion(?string $fecha): string
{
    if (!$fecha) {
        return '—';
    }

    return date(
        'd/m/Y',
        strtotime($fecha)
    );
}

function inicialesVeterinario(
    string $nombre,
    string $apellido
): string {

    $primeraNombre =
        mb_substr(trim($nombre), 0, 1);

    $primeraApellido =
        mb_substr(trim($apellido), 0, 1);

    return strtoupper(
        $primeraNombre . $primeraApellido
    );
}

function detalleVencimiento(array $suscripcion): array
{
    $estado =
        (string) ($suscripcion['estado'] ?? '');

    $fecha =
        $suscripcion['fecha_vencimiento'] ?? null;

    if (!$fecha) {
        return [
            'texto' => 'Se define al activar',
            'clase' => 'neutral',
            'icono' => 'bi-calendar2'
        ];
    }

    $hoy =
        new DateTimeImmutable('today');

    $vencimiento =
        new DateTimeImmutable($fecha);

    $dias =
        (int) $hoy->diff($vencimiento)->format('%r%a');

    if ($estado === 'suspendida') {
        return [
            'texto' => 'Período vencido',
            'clase' => 'critico',
            'icono' => 'bi-lock-fill'
        ];
    }

    if ($estado === 'atrasada') {

        $diasAtraso =
            abs(min($dias, 0));

        return [
            'texto' =>
                $diasAtraso === 1
                    ? '1 día de atraso'
                    : $diasAtraso . ' días de atraso',
            'clase' => 'alerta',
            'icono' => 'bi-exclamation-triangle-fill'
        ];
    }

    if ($estado === 'activa') {

        if ($dias === 0) {
            return [
                'texto' => 'Vence hoy',
                'clase' => 'alerta',
                'icono' => 'bi-clock-fill'
            ];
        }

        if ($dias > 0 && $dias <= 7) {
            return [
                'texto' =>
                    $dias === 1
                        ? 'Vence mañana'
                        : 'Vence en ' . $dias . ' días',
                'clase' => 'proximo',
                'icono' => 'bi-clock'
            ];
        }

        if ($dias > 7) {
            return [
                'texto' => 'Al día',
                'clase' => 'correcto',
                'icono' => 'bi-check-circle-fill'
            ];
        }
    }

    if ($estado === 'pendiente_pago') {
        return [
            'texto' => 'Primer pago pendiente',
            'clase' => 'pendiente',
            'icono' => 'bi-hourglass-split'
        ];
    }

    if ($estado === 'cancelada') {
        return [
            'texto' => 'Sin renovación',
            'clase' => 'neutral',
            'icono' => 'bi-dash-circle'
        ];
    }

    return [
        'texto' => 'Consultar estado',
        'clase' => 'neutral',
        'icono' => 'bi-info-circle'
    ];
}

$seccionActiva = 'suscripciones';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">
    <title>
        Suscripciones · Superadmin
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
        href="../estilos/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row admin-shell">
            <div class="col-12 col-lg-2 p-0">
                <?php include '_menu.php'; ?>
            </div>
            <main class="col-12 col-lg-10 admin-main">
                <!-- ==================================================
                     ENCABEZADO
                =================================================== -->
                <div class="sus-admin-header">
                    <div>
                        <div class="sus-admin-kicker">
                            <i class="bi bi-credit-card-2-front"></i>
                            Gestión comercial
                        </div>
                        <h1>
                            Suscripciones
                        </h1>
                        <p>
                            Controlá vencimientos, períodos de tolerancia,
                            cuentas atrasadas y suspensiones desde un solo lugar.
                        </p>
                    </div>
                    <div class="sus-admin-header-badge">
                        <span>
                            <?= (int) $resumen['total'] ?>
                        </span>
                        <small>
                            suscripciones registradas
                        </small>
                    </div>
                </div>
                <!-- ==================================================
                     MÉTRICAS
                =================================================== -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="sus-admin-metrica">
                            <div class="sus-admin-metrica-icono activa">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <div>
                                <small>
                                    Activas
                                </small>
                                <strong>
                                    <?= (int) $resumen['activa'] ?>
                                </strong>
                                <span>
                                    con acceso habilitado
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="sus-admin-metrica">
                            <div class="sus-admin-metrica-icono proximo">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div>
                                <small>
                                    Próximas a vencer
                                </small>
                                <strong>
                                    <?= (int) $resumen['por_vencer'] ?>
                                </strong>
                                <span>
                                    durante los próximos 7 días
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="sus-admin-metrica">
                            <div class="sus-admin-metrica-icono atrasada">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div>
                                <small>
                                    Atrasadas
                                </small>
                                <strong>
                                    <?= (int) $resumen['atrasada'] ?>
                                </strong>
                                <span>
                                    dentro del período de tolerancia
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="sus-admin-metrica">
                            <div class="sus-admin-metrica-icono suspendida">
                                <i class="bi bi-lock"></i>
                            </div>
                            <div>
                                <small>
                                    Suspendidas
                                </small>
                                <strong>
                                    <?= (int) $resumen['suspendida'] ?>
                                </strong>
                                <span>
                                    sin acceso al sistema
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ==================================================
                     ALERTA DE GESTIÓN
                =================================================== -->
                <?php if (
                    $resumen['atrasada'] > 0 ||
                    $resumen['suspendida'] > 0
                ): ?>
                    <div class="sus-admin-alerta">
                        <div class="sus-admin-alerta-icono">
                            <i class="bi bi-bell-fill"></i>
                        </div>
                        <div>
                            <strong>
                                Hay cuentas que requieren seguimiento
                            </strong>
                            <p>
                                Actualmente tenés
                                <?php if ($resumen['atrasada'] > 0): ?>
                                    <b>
                                        <?= (int) $resumen['atrasada'] ?>
                                        <?= $resumen['atrasada'] === 1
                                            ? 'suscripción atrasada'
                                            : 'suscripciones atrasadas'
                                        ?>
                                    </b>
                                <?php endif; ?>
                                <?php if (
                                    $resumen['atrasada'] > 0 &&
                                    $resumen['suspendida'] > 0
                                ): ?>
                                    y
                                <?php endif; ?>
                                <?php if ($resumen['suspendida'] > 0): ?>
                                    <b>
                                        <?= (int) $resumen['suspendida'] ?>
                                        <?= $resumen['suspendida'] === 1
                                            ? 'cuenta suspendida'
                                            : 'cuentas suspendidas'
                                        ?>
                                    </b>
                                <?php endif; ?>.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- ==================================================
                     CONTENEDOR PRINCIPAL
                =================================================== -->
                <section class="sus-admin-panel">
                    <div class="sus-admin-panel-head">
                        <div>
                            <h2>
                                Estado de las suscripciones
                            </h2>
                            <p>
                                Filtrá la cartera y entrá a cada cuenta
                                para gestionar excepciones o consultar detalles.
                            </p>
                        </div>
                        <div class="sus-admin-resultado">
                            <?= count($suscripciones) ?>
                            <span>
                                <?= count($suscripciones) === 1
                                    ? 'resultado'
                                    : 'resultados'
                                ?>
                            </span>
                        </div>
                    </div>
                    <!-- FILTROS -->
                    <div class="sus-admin-filtros">
                        <a
                            href="suscripciones.php"
                            class="sus-admin-filtro <?= $estado === '' ? 'activo' : '' ?>">
                            Todas
                            <span>
                                <?= (int) $resumen['total'] ?>
                            </span>
                        </a>
                        <?php foreach ($estadosValidos as $estadoFiltro): ?>
                            <a
                                href="?estado=<?= urlencode($estadoFiltro) ?>"
                                class="sus-admin-filtro <?= $estado === $estadoFiltro ? 'activo' : '' ?>">
                                <?= htmlspecialchars(
                                    etiquetaEstadoSuscripcion($estadoFiltro)
                                ) ?>
                                <span>
                                    <?= (int) ($resumen[$estadoFiltro] ?? 0) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <!-- TABLA -->
                    <div class="table-responsive">
                        <table class="table sus-admin-tabla align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>
                                        Profesional
                                    </th>
                                    <th>
                                        Plan
                                    </th>
                                    <th>
                                        Estado
                                    </th>
                                    <th>
                                        Vencimiento
                                    </th>
                                    <th>
                                        Tolerancia
                                    </th>
                                    <th class="text-end">
                                        Importe
                                    </th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suscripciones as $suscripcion): ?>
                                    <?php
                                    $detalle =
                                        detalleVencimiento($suscripcion);
                                    ?>
                                    <tr>
                                        <!-- PROFESIONAL -->
                                        <td>
                                            <div class="sus-admin-profesional">
                                                <div class="sus-admin-avatar">
                                                    <?= htmlspecialchars(
                                                        inicialesVeterinario(
                                                            $suscripcion['nombre'],
                                                            $suscripcion['apellido']
                                                        )
                                                    ) ?>
                                                </div>
                                                <div>
                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            trim(
                                                                $suscripcion['nombre']
                                                                . ' '
                                                                . $suscripcion['apellido']
                                                            )
                                                        ) ?>
                                                    </strong>
                                                    <span>
                                                        <?php if (!empty($suscripcion['matricula'])): ?>
                                                            MP
                                                            <?= htmlspecialchars(
                                                                $suscripcion['matricula']
                                                            ) ?>
                                                        <?php else: ?>
                                                            Sin matrícula informada
                                                        <?php endif; ?>
                                                        <?php if (!empty($suscripcion['especialidad'])): ?>
                                                            ·
                                                            <?= htmlspecialchars(
                                                                $suscripcion['especialidad']
                                                            ) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                    <small>
                                                        <?= htmlspecialchars(
                                                            $suscripcion['email']
                                                        ) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- PLAN -->
                                        <td>
                                            <div class="sus-admin-plan">
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $suscripcion['plan_nombre']
                                                    ) ?>
                                                </strong>
                                                <span>
                                                    Mensual
                                                </span>
                                            </div>
                                        </td>
                                        <!-- ESTADO -->
                                        <td>
                                            <span
                                                class="badge-estado <?= htmlspecialchars(
                                                    $suscripcion['estado']
                                                ) ?>">
                                                <?= htmlspecialchars(
                                                    etiquetaEstadoSuscripcion(
                                                        $suscripcion['estado']
                                                    )
                                                ) ?>
                                            </span>
                                        </td>
                                        <!-- VENCIMIENTO -->
                                        <td>
                                            <div class="sus-admin-fecha">
                                                <strong>
                                                    <?= fechaSuscripcion(
                                                        $suscripcion['fecha_vencimiento']
                                                    ) ?>
                                                </strong>
                                                <span class="<?= htmlspecialchars(
                                                    $detalle['clase']
                                                ) ?>">
                                                    <i class="bi <?= htmlspecialchars(
                                                        $detalle['icono']
                                                    ) ?>"></i>
                                                    <?= htmlspecialchars(
                                                        $detalle['texto']
                                                    ) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <!-- TOLERANCIA -->
                                        <td>
                                            <div class="sus-admin-fecha">
                                                <strong>
                                                    <?= fechaSuscripcion(
                                                        $suscripcion['fecha_fin_tolerancia']
                                                    ) ?>
                                                </strong>
                                                <span class="neutral">
                                                    <?= (int) $suscripcion['dias_tolerancia'] ?>
                                                    días
                                                </span>
                                            </div>
                                        </td>
                                        <!-- IMPORTE -->
                                        <td class="text-end">
                                            <div class="sus-admin-importe">
                                                <strong>
                                                    <?= formatoDinero(
                                                        (float) $suscripcion['precio_mensual']
                                                    ) ?>
                                                </strong>
                                                <span>
                                                    / mes
                                                </span>
                                            </div>
                                        </td>
                                        <!-- ACCIÓN -->
                                        <td class="text-end">
                                            <a
                                                href="ficha_veterinario.php?id=<?= (int) $suscripcion['id_veterinario'] ?>"
                                                class="sus-admin-accion"
                                                title="Gestionar cuenta">
                                                Ver cuenta
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$suscripciones): ?>
                                    <tr>
                                        <td
                                            colspan="7"
                                            class="p-0">
                                            <div class="sus-admin-vacio">
                                                <div>
                                                    <i class="bi bi-inbox"></i>
                                                </div>
                                                <h3>
                                                    No hay suscripciones en este estado
                                                </h3>
                                                <p>
                                                    Probá con otro filtro para ver
                                                    el resto de la cartera.
                                                </p>
                                                <a
                                                    href="suscripciones.php"
                                                    class="btn-admin">
                                                    Ver todas las suscripciones
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <!-- ==================================================
                     ACLARACIÓN
                =================================================== -->
                <div class="sus-admin-nota">
                    <i class="bi bi-shield-check"></i>
                    <div>
                        <strong>
                            Estados actualizados automáticamente
                        </strong>
                        <span>
                            VetClinic Pro revisa vencimientos y períodos
                            de tolerancia antes de mostrar esta pantalla.
                            Una cuenta atrasada pasa a suspendida cuando
                            supera su fecha límite.
                        </span>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script></body>
</html>
