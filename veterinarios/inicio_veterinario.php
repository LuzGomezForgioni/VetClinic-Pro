<?php

session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');

// ============================================================
// VERIFICAR VETERINARIO LOGUEADO
// ============================================================

if (
    empty($_SESSION['logueado']) ||
    ($_SESSION['tipo_usuario'] ?? '') !== 'veterinario'
) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';
require_once '../config/proteger_acceso_veterinario.php';

// ============================================================
// DATOS DEL VETERINARIO
// ============================================================

$id_veterinario = (int) ($_SESSION['usuario_id'] ?? 0);
$nombre = $_SESSION['usuario_nombre'] ?? 'Doctor/a';
$apellido = $_SESSION['usuario_apellido'] ?? '';
$sexo = strtoupper(trim($_SESSION['usuario_sexo'] ?? ''));
$titulo = ($sexo === 'F') ? 'Dra.' : 'Dr.';

// ============================================================
// FECHA Y SALUDO
// ============================================================

$dias = [
    'Domingo',
    'Lunes',
    'Martes',
    'Miércoles',
    'Jueves',
    'Viernes',
    'Sábado'
];

$meses = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre'
];

$fecha_actual =
    $dias[date('w')] . ' ' .
    date('j') . ' de ' .
    $meses[date('n') - 1] . ' de ' .
    date('Y');

$hora_actual = (int) date('H');

if ($hora_actual >= 5 && $hora_actual < 12) {
    $saludo = 'Buen día';
} elseif ($hora_actual >= 12 && $hora_actual < 20) {
    $saludo = 'Buenas tardes';
} else {
    $saludo = 'Buenas noches';
}

// ============================================================
// FUNCIONES
// ============================================================

function mostrarEstadoInicio(string $estado): string
{
    if ($estado === 'confirmado') {
        return '<span class="badge-estado estado-confirmado">Confirmado</span>';
    }

    if ($estado === 'realizado') {
        return '<span class="badge-estado estado-confirmado">Realizado</span>';
    }

    if ($estado === 'cancelado') {
        return '<span class="badge-estado estado-pendiente">Cancelado</span>';
    }

    return '<span class="badge-estado estado-pendiente">' .
        htmlspecialchars(ucfirst($estado)) .
        '</span>';
}

function calcularEdadInicio(?string $fecha_nacimiento): string
{
    if (empty($fecha_nacimiento)) {
        return 'Edad no registrada';
    }

    try {
        $nacimiento = new DateTime($fecha_nacimiento);
        $hoy = new DateTime('today');

        if ($nacimiento > $hoy) {
            return 'Edad no registrada';
        }

        $edad = $hoy->diff($nacimiento);

        if ($edad->y > 0) {
            return $edad->y . ($edad->y === 1 ? ' año' : ' años');
        }

        if ($edad->m > 0) {
            return $edad->m . ($edad->m === 1 ? ' mes' : ' meses');
        }

        return 'Menos de 1 mes';
    } catch (Exception $e) {
        return 'Edad no registrada';
    }
}

function iconoMascotaInicio(?string $especie): string
{
    $especie = strtolower(trim((string) $especie));

    if ($especie === 'perro') {
        return '🐶';
    }

    if ($especie === 'gato') {
        return '🐱';
    }

    if ($especie === 'conejo') {
        return '🐰';
    }

    if ($especie === 'ave') {
        return '🐦';
    }

    if ($especie === 'reptil') {
        return '🦎';
    }

    return '🐾';
}

function fechaRelativaInicio(?string $fecha): string
{
    if (empty($fecha)) {
        return '';
    }

    try {
        $hoy = new DateTime('today');
        $fecha_obj = new DateTime($fecha);
        $fecha_solo_dia = new DateTime($fecha_obj->format('Y-m-d'));

        $diferencia = (int) $hoy->diff($fecha_solo_dia)->format('%r%a');

        if ($diferencia === 0) {
            return 'Hoy';
        }

        if ($diferencia === -1) {
            return 'Ayer';
        }

        return $fecha_obj->format('d/m');
    } catch (Exception $e) {
        return '';
    }
}

// ============================================================
// TURNOS DE HOY DEL VETERINARIO
// ============================================================

$turnos_hoy = [];

$stmt = $conn->prepare("
    SELECT
        t.id_turno,
        t.fecha_hora,
        t.motivo,
        t.estado,
        m.id_mascota,
        m.nombre AS nombre_mascota,
        e.nombre_especie,
        r.nombre_raza,
        p.id_propietario,
        p.nombre AS nombre_propietario,
        p.apellido AS apellido_propietario
    FROM turnos t
    INNER JOIN mascotas m
        ON t.id_mascota = m.id_mascota
    INNER JOIN propietarios p
        ON t.id_propietario = p.id_propietario
    LEFT JOIN especies e
        ON m.id_especie = e.id_especie
    LEFT JOIN razas r
        ON m.id_raza = r.id_raza
    WHERE t.id_veterinario = ?
      AND DATE(t.fecha_hora) = CURDATE()
      AND t.estado <> 'cancelado'
    ORDER BY t.fecha_hora ASC
");

$stmt->bind_param('i', $id_veterinario);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    $turnos_hoy[] = $fila;
}

$stmt->close();

$total_turnos_hoy = count($turnos_hoy);
$turnos_pendientes_hoy = 0;

foreach ($turnos_hoy as $turno) {
    if ($turno['estado'] === 'pendiente') {
        $turnos_pendientes_hoy++;
    }
}

// ============================================================
// MASCOTAS ATENDIDAS ESTA SEMANA POR EL VETERINARIO
// ============================================================

$mascotas_atendidas_semana = 0;

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT id_mascota) AS total
    FROM historia_clinica
    WHERE id_veterinario = ?
      AND YEARWEEK(fecha_consulta, 1) = YEARWEEK(CURDATE(), 1)
");

$stmt->bind_param('i', $id_veterinario);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$mascotas_atendidas_semana = (int) ($fila['total'] ?? 0);
$stmt->close();

// ============================================================
// VACUNACIONES PRÓXIMAS EN LOS PRÓXIMOS 7 DÍAS
// ============================================================

$vacunaciones_proximas = 0;

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM vacunaciones v
    INNER JOIN mascotas m
        ON v.id_mascota = m.id_mascota
    WHERE m.fallecido = 0
      AND v.fecha_proxima IS NOT NULL
      AND v.fecha_proxima BETWEEN CURDATE()
          AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $vacunaciones_proximas = (int) ($fila['total'] ?? 0);
}

// ============================================================
// PACIENTES ACTIVOS
// ============================================================

$pacientes_activos = 0;

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM mascotas
    WHERE fallecido = 0
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $pacientes_activos = (int) ($fila['total'] ?? 0);
}

// ============================================================
// ÚLTIMAS MASCOTAS ATENDIDAS POR EL VETERINARIO
// ============================================================

$ultimas_atendidas = [];

$stmt = $conn->prepare("
    SELECT
        h.id_historia,
        h.fecha_consulta,
        h.motivo_consulta,
        m.id_mascota,
        m.nombre AS nombre_mascota,
        m.fecha_nacimiento,
        e.nombre_especie,
        r.nombre_raza
    FROM historia_clinica h
    INNER JOIN mascotas m
        ON h.id_mascota = m.id_mascota
    LEFT JOIN especies e
        ON m.id_especie = e.id_especie
    LEFT JOIN razas r
        ON m.id_raza = r.id_raza
    WHERE h.id_veterinario = ?
    ORDER BY h.fecha_consulta DESC, h.id_historia DESC
    LIMIT 5
");

$stmt->bind_param('i', $id_veterinario);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    $ultimas_atendidas[] = $fila;
}

$stmt->close();

// ============================================================
// RECORDATORIOS IMPORTANTES DE VACUNACIÓN
// ============================================================

$recordatorios = [];

$resultado = $conn->query("
    SELECT
        v.id_vacunacion,
        v.nombre_vacuna,
        v.fecha_proxima,
        m.id_mascota,
        m.nombre AS nombre_mascota,
        e.nombre_especie,
        r.nombre_raza,
        p.id_propietario,
        p.nombre AS nombre_propietario,
        p.apellido AS apellido_propietario,
        DATEDIFF(v.fecha_proxima, CURDATE()) AS dias_restantes
    FROM vacunaciones v
    INNER JOIN mascotas m
        ON v.id_mascota = m.id_mascota
    INNER JOIN propietarios p
        ON m.id_propietario = p.id_propietario
    LEFT JOIN especies e
        ON m.id_especie = e.id_especie
    LEFT JOIN razas r
        ON m.id_raza = r.id_raza
    WHERE m.fallecido = 0
      AND v.fecha_proxima IS NOT NULL
      AND v.fecha_proxima <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY v.fecha_proxima ASC
    LIMIT 4
");

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $recordatorios[] = $fila;
    }
}

// ============================================================
// PRÓXIMOS TURNOS DEL VETERINARIO
// ============================================================

$proximos_turnos = [];

$stmt = $conn->prepare("
    SELECT
        t.id_turno,
        t.fecha_hora,
        t.motivo,
        t.estado,
        m.id_mascota,
        m.nombre AS nombre_mascota,
        p.nombre AS nombre_propietario,
        p.apellido AS apellido_propietario
    FROM turnos t
    INNER JOIN mascotas m
        ON t.id_mascota = m.id_mascota
    INNER JOIN propietarios p
        ON t.id_propietario = p.id_propietario
    WHERE t.id_veterinario = ?
      AND t.fecha_hora > NOW()
      AND t.estado <> 'cancelado'
    ORDER BY t.fecha_hora ASC
    LIMIT 4
");

$stmt->bind_param('i', $id_veterinario);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    $proximos_turnos[] = $fila;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inicio — Veterinario · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

    <link rel="stylesheet" href="../estilos/inicio.css" />
</head>

<body>
<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">

        <!-- ================================================== -->
        <!-- BARRA LATERAL -->
        <!-- ================================================== -->

        <div class="col-12 col-lg-2 panel-lateral">

            <a href="inicio_veterinario.php" class="logo-lateral">
                <img src="../img/VetClinic Pro.png" alt="Logo VetClinic Pro" />
                <div>
                    <div class="nombre-sistema">VetClinic Pro</div>
                    <div class="subtitulo-sistema">Portal del veterinario</div>
                </div>
            </a>

            <div class="seccion-nav">Principal</div>

            <a href="inicio_veterinario.php" class="item-menu activo">
                <i class="bi bi-grid"></i> Inicio
            </a>

            <a href="turnos_veterinario.php" class="item-menu">
                <i class="bi bi-calendar-check"></i> Turnos
            </a>

            <a href="mascotas_veterinario.php" class="item-menu">
                <i class="bi bi-heart-pulse"></i> Mascotas
            </a>

            <a href="historiales_veterinario.php" class="item-menu">
                <i class="bi bi-journal-medical"></i> Historial clínico
            </a>

            <div class="seccion-nav">Gestión</div>

            <a href="propietarios_veterinario.php" class="item-menu">
                <i class="bi bi-people"></i> Propietarios
            </a>

            <a href="vacunaciones_veterinario.php" class="item-menu">
                <i class="bi bi-shield-plus"></i> Vacunaciones
            </a>

            <!-- Mensajes queda pendiente por ahora -->
            <a href="#" class="item-menu" title="Disponible más adelante">
                <i class="bi bi-chat-dots"></i> Mensajes
            </a>

            <div class="seccion-nav">Configuración</div>

            <a href="horarios_atencion.php" class="item-menu">
                <i class="bi bi-clock"></i> Horarios de atención
            </a>

            <a href="../suscripciones/mi_suscripcion.php" class="item-menu">
                <i class="bi bi-credit-card-2-front"></i> Mi suscripción
            </a>

            <div class="usuario-lateral">
                <div class="avatar-usuario">
                    <?= strtoupper(substr($nombre, 0, 1) . substr($apellido, 0, 1)) ?>
                </div>

                <div>
                    <div class="nombre-usuario">
                        <?= htmlspecialchars($nombre . ' ' . $apellido) ?>
                    </div>
                    <div class="rol-usuario">Veterinario</div>
                </div>
            </div>

        </div>

        <!-- ================================================== -->
        <!-- ÁREA PRINCIPAL -->
        <!-- ================================================== -->

        <div class="col-12 col-lg-10 area-principal">

            <!-- BARRA SUPERIOR -->

            <div class="barra-superior">
                <div>
                    <p class="titulo-pagina">
                        <?= htmlspecialchars($saludo) ?>,
                        <?= htmlspecialchars($titulo) ?>
                        <?= htmlspecialchars($apellido) ?> 👋
                    </p>

                    <p class="fecha-hoy">
                        <?= htmlspecialchars($fecha_actual) ?>
                    </p>
                </div>

                <div class="d-flex gap-2">
                    <a href="#recordatorios" class="icono-barra" title="Recordatorios">
                        <i class="bi bi-bell"></i>
                    </a>

                    <a href="horarios_atencion.php" class="icono-barra" title="Horarios de atención">
                        <i class="bi bi-gear"></i>
                    </a>

                    <a href="../logout.php" class="icono-barra" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- ESTADO DE LA SUSCRIPCIÓN -->
            <!-- ================================================== -->

            <?php if (!empty($suscripcionGuard)): ?>
                <?php
                    $estadoSuscripcionInicio = (string) $suscripcionGuard['estado'];
                    $fechaVencimientoInicio = $suscripcionGuard['fecha_vencimiento'] ?? null;
                    $diasParaVencerInicio = null;
                    if ($fechaVencimientoInicio) {
                        $hoySuscripcionInicio = new DateTimeImmutable('today');
                        $venceSuscripcionInicio = new DateTimeImmutable($fechaVencimientoInicio);
                        $diasParaVencerInicio = (int) $hoySuscripcionInicio->diff($venceSuscripcionInicio)->format('%r%a');
                    }
                ?>
                <div class="tarjeta mb-4" style="border-left:4px solid #1a1f5e;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Mi suscripción</div>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <strong style="color:var(--azul-oscuro);font-size:16px;"><?= htmlspecialchars($suscripcionGuard['nombre_plan']) ?></strong>
                                <span class="badge <?= $estadoSuscripcionInicio === 'activa' ? 'text-bg-success' : 'text-bg-warning' ?>">
                                    <?= htmlspecialchars(etiquetaEstadoSuscripcion($estadoSuscripcionInicio)) ?>
                                </span>
                            </div>
                            <div class="text-muted mt-1" style="font-size:12px;">
                                <?= formatoDinero((float)$suscripcionGuard['precio_mensual']) ?> / mes
                                <?php if ($fechaVencimientoInicio): ?>
                                    · Próximo vencimiento: <strong><?= date('d/m/Y', strtotime($fechaVencimientoInicio)) ?></strong>
                                <?php endif; ?>
                                <?php if ($diasParaVencerInicio !== null && $diasParaVencerInicio >= 0 && $diasParaVencerInicio <= 7): ?>
                                    · <strong>Vence en <?= $diasParaVencerInicio ?> <?= $diasParaVencerInicio === 1 ? 'día' : 'días' ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="../suscripciones/mi_suscripcion.php" class="btn-secundario">
                            Ver suscripción <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================================================== -->
            <!-- TARJETAS RESUMEN -->
            <!-- ================================================== -->

            <div class="row g-3 mb-4">

                <div class="col-6 col-lg-3">
                    <div class="tarjeta-resumen">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="icono-metrica icono-azul-oscuro">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <span class="etiqueta-metrica">Turnos hoy</span>
                        </div>

                        <div class="numero-metrica"><?= $total_turnos_hoy ?></div>

                        <div class="sub-metrica">
                            <?php if ($turnos_pendientes_hoy > 0): ?>
                                <?= $turnos_pendientes_hoy ?>
                                <?= $turnos_pendientes_hoy === 1 ? 'pendiente' : 'pendientes' ?> de confirmar
                            <?php else: ?>
                                Sin turnos pendientes
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="tarjeta-resumen">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="icono-metrica icono-azul-medio">
                                <i class="bi bi-heart-pulse"></i>
                            </div>
                            <span class="etiqueta-metrica">Mascotas atendidas</span>
                        </div>

                        <div class="numero-metrica"><?= $mascotas_atendidas_semana ?></div>
                        <div class="sub-metrica">Esta semana</div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="tarjeta-resumen">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="icono-metrica icono-amarillo">
                                <i class="bi bi-shield-plus"></i>
                            </div>
                            <span class="etiqueta-metrica">Vacunaciones próximas</span>
                        </div>

                        <div class="numero-metrica"><?= $vacunaciones_proximas ?></div>
                        <div class="sub-metrica">En los próximos 7 días</div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="tarjeta-resumen">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="icono-metrica icono-azul-claro">
                                <i class="bi bi-clipboard2-pulse"></i>
                            </div>
                            <span class="etiqueta-metrica">Pacientes activos</span>
                        </div>

                        <div class="numero-metrica"><?= $pacientes_activos ?></div>
                        <div class="sub-metrica">Registrados en la clínica</div>
                    </div>
                </div>

            </div>

            <!-- ================================================== -->
            <!-- ACCESOS RÁPIDOS -->
            <!-- ================================================== -->

            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="tarjeta">
                        <div class="titulo-tarjeta">
                            <i class="bi bi-lightning-charge-fill"></i>
                            Accesos rápidos
                        </div>

                        <div class="row g-3">

                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="mascotas_veterinario.php" class="acceso-rapido">
                                    <div class="icono-acceso"><i class="bi bi-heart-pulse"></i></div>
                                    Gestionar mascotas
                                </a>
                            </div>

                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="propietarios_veterinario.php" class="acceso-rapido">
                                    <div class="icono-acceso"><i class="bi bi-people"></i></div>
                                    Propietarios
                                </a>
                            </div>

                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="historiales_veterinario.php" class="acceso-rapido">
                                    <div class="icono-acceso"><i class="bi bi-journal-medical"></i></div>
                                    Historial clínico
                                </a>
                            </div>

                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="vacunaciones_veterinario.php" class="acceso-rapido">
                                    <div class="icono-acceso"><i class="bi bi-shield-plus"></i></div>
                                    Vacunaciones
                                </a>
                            </div>

                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="turnos_veterinario.php" class="acceso-rapido">
                                    <div class="icono-acceso"><i class="bi bi-calendar-check"></i></div>
                                    Ver turnos
                                </a>
                            </div>

                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="horarios_atencion.php" class="acceso-rapido">
                                    <div class="icono-acceso"><i class="bi bi-clock"></i></div>
                                    Horarios
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- TURNOS DE HOY + ÚLTIMAS ATENDIDAS -->
            <!-- ================================================== -->

            <div class="row g-3 mb-4">

                <!-- TURNOS DE HOY -->

                <div class="col-12 col-lg-6">
                    <div class="tarjeta h-100">

                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="titulo-tarjeta mb-0">
                                <i class="bi bi-calendar2-week"></i>
                                Turnos de hoy
                            </div>

                            <a href="turnos_veterinario.php" class="btn-secundario" style="font-size:12px; padding:6px 14px;">
                                Ver todos <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>

                        <?php if (!empty($turnos_hoy)): ?>

                            <ul class="lista-turnos">

                                <?php foreach (array_slice($turnos_hoy, 0, 5) as $turno): ?>

                                    <li class="item-turno">
                                        <span class="hora-turno">
                                            <?= date('H:i', strtotime($turno['fecha_hora'])) ?>
                                        </span>

                                        <div class="separador-turno"></div>

                                        <div class="flex-grow-1">
                                            <div class="nombre-paciente">
                                                <?= htmlspecialchars($turno['nombre_mascota']) ?>

                                                <?php if (!empty($turno['nombre_raza'])): ?>
                                                    — <?= htmlspecialchars($turno['nombre_raza']) ?>
                                                <?php elseif (!empty($turno['nombre_especie'])): ?>
                                                    — <?= htmlspecialchars($turno['nombre_especie']) ?>
                                                <?php endif; ?>
                                            </div>

                                            <div class="tipo-consulta">
                                                <?= htmlspecialchars($turno['motivo']) ?>
                                                ·
                                                <?= htmlspecialchars(
                                                    $turno['nombre_propietario'] . ' ' .
                                                    $turno['apellido_propietario']
                                                ) ?>
                                            </div>
                                        </div>

                                        <?= mostrarEstadoInicio($turno['estado']) ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <div class="text-center py-4">
                                <div class="icono-metrica icono-azul-claro mx-auto mb-3">
                                    <i class="bi bi-calendar-x"></i>
                                </div>

                                <div style="font-size:14px;font-weight:700;color:var(--azul-oscuro);">
                                    No tenés turnos para hoy
                                </div>

                                <div class="text-muted mt-1" style="font-size:11px;">
                                    Tu agenda de hoy está libre.
                                </div>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>

                <!-- ÚLTIMAS ATENDIDAS -->

                <div class="col-12 col-lg-6">
                    <div class="tarjeta h-100">

                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="titulo-tarjeta mb-0">
                                <i class="bi bi-clock-history"></i>
                                Últimas atendidas
                            </div>

                            <a href="historiales_veterinario.php" class="btn-secundario" style="font-size:12px; padding:6px 14px;">
                                Ver historial <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>

                        <?php if (!empty($ultimas_atendidas)): ?>

                            <ul class="lista-mascotas">

                                <?php foreach ($ultimas_atendidas as $mascota): ?>

                                    <li class="item-mascota">
                                        <div class="avatar-mascota">
                                            <?= iconoMascotaInicio($mascota['nombre_especie']) ?>
                                        </div>

                                        <div class="informacion-mascota flex-grow-1">
                                            <div class="nombre-mascota">
                                                <?= htmlspecialchars($mascota['nombre_mascota']) ?>
                                            </div>

                                            <div class="detalle-mascota">
                                                <?= htmlspecialchars(
                                                    $mascota['nombre_raza']
                                                        ?: ($mascota['nombre_especie'] ?: 'Especie no registrada')
                                                ) ?>
                                                ·
                                                <?= htmlspecialchars(calcularEdadInicio($mascota['fecha_nacimiento'])) ?>
                                                ·
                                                <?= htmlspecialchars($mascota['motivo_consulta']) ?>
                                            </div>
                                        </div>

                                        <span class="text-muted" style="font-size:12px;">
                                            <?= htmlspecialchars(fechaRelativaInicio($mascota['fecha_consulta'])) ?>
                                        </span>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <div class="text-center py-4">
                                <div class="icono-metrica icono-azul-claro mx-auto mb-3">
                                    <i class="bi bi-journal-medical"></i>
                                </div>

                                <div style="font-size:14px;font-weight:700;color:var(--azul-oscuro);">
                                    Todavía no registraste consultas
                                </div>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>

            </div>

            <!-- ================================================== -->
            <!-- RECORDATORIOS + PRÓXIMOS TURNOS -->
            <!-- ================================================== -->

            <div class="row g-3">

                <!-- RECORDATORIOS -->

                <div class="col-12 col-lg-6" id="recordatorios">
                    <div class="tarjeta h-100">

                        <div class="titulo-tarjeta">
                            <i class="bi bi-bell-fill"></i>
                            Recordatorios importantes
                        </div>

                        <?php if (!empty($recordatorios)): ?>

                            <ul class="lista-recordatorios">

                                <?php foreach ($recordatorios as $recordatorio): ?>

                                    <?php
                                    $dias_restantes = (int) $recordatorio['dias_restantes'];
                                    $clase_recordatorio = 'info';
                                    $icono_recordatorio = 'bi-calendar-event';

                                    if ($dias_restantes < 0) {
                                        $clase_recordatorio = 'urgente';
                                        $icono_recordatorio = 'bi-exclamation-circle-fill';
                                        $texto_recordatorio =
                                            'La vacuna ' . $recordatorio['nombre_vacuna'] .
                                            ' de ' . $recordatorio['nombre_mascota'] .
                                            ' está vencida';
                                    } elseif ($dias_restantes === 0) {
                                        $clase_recordatorio = 'urgente';
                                        $icono_recordatorio = 'bi-exclamation-circle-fill';
                                        $texto_recordatorio =
                                            'La vacuna ' . $recordatorio['nombre_vacuna'] .
                                            ' de ' . $recordatorio['nombre_mascota'] .
                                            ' vence hoy';
                                    } elseif ($dias_restantes === 1) {
                                        $clase_recordatorio = 'urgente';
                                        $icono_recordatorio = 'bi-exclamation-circle-fill';
                                        $texto_recordatorio =
                                            'La vacuna ' . $recordatorio['nombre_vacuna'] .
                                            ' de ' . $recordatorio['nombre_mascota'] .
                                            ' vence mañana';
                                    } else {
                                        $texto_recordatorio =
                                            'Refuerzo de ' . $recordatorio['nombre_vacuna'] .
                                            ' de ' . $recordatorio['nombre_mascota'] .
                                            ' en ' . $dias_restantes . ' días';
                                    }
                                    ?>

                                    <li class="item-recordatorio <?= $clase_recordatorio ?>">
                                        <i class="bi <?= $icono_recordatorio ?>"></i>

                                        <div>
                                            <div class="texto-recordatorio">
                                                <?= htmlspecialchars($texto_recordatorio) ?>
                                            </div>

                                            <div class="sub-recordatorio">
                                                <?= htmlspecialchars($recordatorio['nombre_mascota']) ?>

                                                <?php if (!empty($recordatorio['nombre_raza'])): ?>
                                                    — <?= htmlspecialchars($recordatorio['nombre_raza']) ?>
                                                <?php endif; ?>

                                                · Prop.
                                                <?= htmlspecialchars(
                                                    $recordatorio['nombre_propietario'] . ' ' .
                                                    $recordatorio['apellido_propietario']
                                                ) ?>
                                            </div>
                                        </div>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <div class="text-center py-4">
                                <div class="icono-metrica icono-azul-claro mx-auto mb-3">
                                    <i class="bi bi-check-circle"></i>
                                </div>

                                <div style="font-size:14px;font-weight:700;color:var(--azul-oscuro);">
                                    No hay vacunas próximas o vencidas
                                </div>

                                <div class="text-muted mt-1" style="font-size:11px;">
                                    No hay recordatorios para los próximos 30 días.
                                </div>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>

                <!-- PRÓXIMOS TURNOS -->

                <div class="col-12 col-lg-6">
                    <div class="tarjeta h-100">

                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="titulo-tarjeta mb-0">
                                <i class="bi bi-calendar-plus"></i>
                                Próximos turnos
                            </div>

                            <a href="turnos_veterinario.php" class="btn-secundario" style="font-size:12px; padding:6px 14px;">
                                Ver todos <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>

                        <?php if (!empty($proximos_turnos)): ?>

                            <ul class="lista-turnos">

                                <?php foreach ($proximos_turnos as $turno): ?>

                                    <li class="item-turno">
                                        <span class="hora-turno" style="min-width:52px;">
                                            <?= date('d/m', strtotime($turno['fecha_hora'])) ?>
                                        </span>

                                        <div class="separador-turno"></div>

                                        <div class="flex-grow-1">
                                            <div class="nombre-paciente">
                                                <?= htmlspecialchars($turno['nombre_mascota']) ?>
                                                ·
                                                <?= date('H:i', strtotime($turno['fecha_hora'])) ?> hs
                                            </div>

                                            <div class="tipo-consulta">
                                                <?= htmlspecialchars($turno['motivo']) ?>
                                                ·
                                                <?= htmlspecialchars(
                                                    $turno['nombre_propietario'] . ' ' .
                                                    $turno['apellido_propietario']
                                                ) ?>
                                            </div>
                                        </div>

                                        <?= mostrarEstadoInicio($turno['estado']) ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <div class="text-center py-4">
                                <div class="icono-metrica icono-azul-claro mx-auto mb-3">
                                    <i class="bi bi-calendar-check"></i>
                                </div>

                                <div style="font-size:14px;font-weight:700;color:var(--azul-oscuro);">
                                    No tenés próximos turnos
                                </div>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
