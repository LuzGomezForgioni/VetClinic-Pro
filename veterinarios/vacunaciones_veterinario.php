<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (empty($_SESSION['logueado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'veterinario') {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';
require_once '../config/proteger_acceso_veterinario.php';
require_once '../config/seguridad.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
}

$nombre   = $_SESSION['usuario_nombre'] ?? 'Doctor/a';
$apellido = $_SESSION['usuario_apellido'] ?? '';
$idVeterinario = (int) ($_SESSION['usuario_id'] ?? 0);

$errores = [];
$mensaje = $_SESSION['mensaje_vacuna'] ?? '';
unset($_SESSION['mensaje_vacuna']);

function formatearFechaVacuna(?string $fecha): string
{
    if (empty($fecha)) return 'Sin fecha';

    try {
        return (new DateTime($fecha))->format('d/m/Y');
    } catch (Exception $e) {
        return 'Sin fecha';
    }
}

function estadoVacunaVeterinario(?string $fechaProxima): array
{
    if (empty($fechaProxima)) {
        return [
            'codigo' => 'sin_fecha',
            'texto'  => 'Sin fecha',
            'clase'  => 'estado-vacuna-sin-fecha',
            'icono'  => 'bi-dash-circle'
        ];
    }

    try {
        $hoy = new DateTime('today');
        $proxima = new DateTime($fechaProxima);

        if ($proxima < $hoy) {
            return [
                'codigo' => 'vencida',
                'texto'  => 'Vencida',
                'clase'  => 'estado-vacuna-vencida',
                'icono'  => 'bi-exclamation-triangle'
            ];
        }

        $diasRestantes = (int) $hoy->diff($proxima)->days;

        if ($diasRestantes <= 30) {
            return [
                'codigo' => 'proxima',
                'texto'  => 'Próxima',
                'clase'  => 'estado-vacuna-proxima',
                'icono'  => 'bi-clock-history'
            ];
        }

        return [
            'codigo' => 'al_dia',
            'texto'  => 'Al día',
            'clase'  => 'estado-vacuna-ok',
            'icono'  => 'bi-check-circle'
        ];

    } catch (Exception $e) {
        return [
            'codigo' => 'sin_fecha',
            'texto'  => 'Sin fecha',
            'clase'  => 'estado-vacuna-sin-fecha',
            'icono'  => 'bi-dash-circle'
        ];
    }
}

// ============================================================
// MASCOTAS ACTIVAS PARA REGISTRAR VACUNAS
// ============================================================

$mascotasActivas = [];

$stmt = $conn->prepare("
    SELECT DISTINCT
        m.id_mascota,
        m.nombre,
        p.nombre AS nombre_propietario,
        p.apellido AS apellido_propietario
    FROM mascotas m
    INNER JOIN propietarios p
        ON m.id_propietario = p.id_propietario
    INNER JOIN turnos t
        ON t.id_mascota = m.id_mascota
    WHERE m.fallecido = 0
      AND t.id_veterinario = ?
    ORDER BY m.nombre ASC, p.apellido ASC, p.nombre ASC
");
$stmt->bind_param('i', $idVeterinario);
$stmt->execute();
$resultadoMascotasActivas = $stmt->get_result();

while ($fila = $resultadoMascotasActivas->fetch_assoc()) {
    $mascotasActivas[] = $fila;
}

$stmt->close();

// ============================================================
// REGISTRAR NUEVA VACUNACIÓN
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registrar') {

    $idMascota       = (int) ($_POST['id_mascota'] ?? 0);
    $nombreVacuna    = trim($_POST['nombre_vacuna'] ?? '');
    $fechaAplicacion = trim($_POST['fecha_aplicacion'] ?? '');
    $fechaProxima    = trim($_POST['fecha_proxima'] ?? '');
    $observaciones   = trim($_POST['observaciones'] ?? '');

    if ($idMascota <= 0) {
        $errores[] = 'Seleccioná una mascota.';
    } else {
        $stmt = $conn->prepare("
            SELECT DISTINCT m.id_mascota
            FROM mascotas m
            INNER JOIN turnos t
                ON t.id_mascota = m.id_mascota
            WHERE m.id_mascota = ?
              AND m.fallecido = 0
              AND t.id_veterinario = ?
            LIMIT 1
        ");
        $stmt->bind_param('ii', $idMascota, $idVeterinario);
        $stmt->execute();
        $mascotaValida = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$mascotaValida) {
            $errores[] = 'La mascota seleccionada no está disponible para registrar una vacuna.';
        }
    }

    if ($nombreVacuna === '') {
        $errores[] = 'El nombre de la vacuna es obligatorio.';
    }

    $fechaAplicacionObj = null;

    if ($fechaAplicacion === '') {
        $errores[] = 'La fecha de aplicación es obligatoria.';
    } else {
        $fechaAplicacionObj = DateTime::createFromFormat('!Y-m-d', $fechaAplicacion);
        $erroresFecha = DateTime::getLastErrors();

        if (
            !$fechaAplicacionObj ||
            ($erroresFecha !== false && ($erroresFecha['warning_count'] > 0 || $erroresFecha['error_count'] > 0))
        ) {
            $errores[] = 'La fecha de aplicación no es válida.';
        } elseif ($fechaAplicacionObj > new DateTime('today')) {
            $errores[] = 'La fecha de aplicación no puede estar en el futuro.';
        }
    }

    if ($fechaProxima !== '') {
        $fechaProximaObj = DateTime::createFromFormat('Y-m-d', $fechaProxima);
        $erroresFechaProxima = DateTime::getLastErrors();

        if (
            !$fechaProximaObj ||
            ($erroresFechaProxima !== false && ($erroresFechaProxima['warning_count'] > 0 || $erroresFechaProxima['error_count'] > 0))
        ) {
            $errores[] = 'La fecha de próxima dosis no es válida.';
        } elseif ($fechaAplicacionObj && $fechaProximaObj < $fechaAplicacionObj) {
            $errores[] = 'La próxima dosis no puede ser anterior a la fecha de aplicación.';
        }
    }

    if (empty($errores)) {
        $fechaProximaFinal = $fechaProxima !== '' ? $fechaProxima : null;
        $observacionesFinal = $observaciones !== '' ? $observaciones : null;

        $stmt = $conn->prepare("
            INSERT INTO vacunaciones
                (id_mascota, id_veterinario, nombre_vacuna, fecha_aplicacion, fecha_proxima, observaciones)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'iissss',
            $idMascota,
            $idVeterinario,
            $nombreVacuna,
            $fechaAplicacion,
            $fechaProximaFinal,
            $observacionesFinal
        );

        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['mensaje_vacuna'] = 'Vacunación registrada correctamente.';
            header('Location: vacunaciones_veterinario.php');
            exit;
        }

        $stmt->close();
        $errores[] = 'No se pudo registrar la vacunación. Intentá nuevamente.';
    }
}

// ============================================================
// FILTROS
// ============================================================

$busqueda        = trim($_GET['buscar'] ?? '');
$idMascotaFiltro = (int) ($_GET['id_mascota'] ?? 0);
$estadoFiltro    = trim($_GET['estado'] ?? '');

$estadosPermitidos = ['', 'al_dia', 'proxima', 'vencida', 'sin_fecha'];

if (!in_array($estadoFiltro, $estadosPermitidos, true)) {
    $estadoFiltro = '';
}

$idMascotaPreseleccionada =
    (int) ($_POST['id_mascota'] ?? ($idMascotaFiltro > 0 ? $idMascotaFiltro : 0));

// ============================================================
// TODAS LAS MASCOTAS PARA EL FILTRO
// ============================================================

$mascotasFiltro = [];

$stmt = $conn->prepare("
    SELECT DISTINCT
        m.id_mascota,
        m.nombre,
        p.nombre AS nombre_propietario,
        p.apellido AS apellido_propietario
    FROM mascotas m
    INNER JOIN propietarios p
        ON p.id_propietario = m.id_propietario
    INNER JOIN turnos t
        ON t.id_mascota = m.id_mascota
    WHERE t.id_veterinario = ?
    ORDER BY m.nombre ASC, p.apellido ASC, p.nombre ASC
");
$stmt->bind_param('i', $idVeterinario);
$stmt->execute();
$resultadoMascotasFiltro = $stmt->get_result();

while ($fila = $resultadoMascotasFiltro->fetch_assoc()) {
    $mascotasFiltro[] = $fila;
}

$stmt->close();

// ============================================================
// CONSULTAR VACUNACIONES
// ============================================================

$sqlVacunas = "
    SELECT
        va.id_vacunacion,
        va.id_mascota,
        va.id_veterinario,
        va.nombre_vacuna,
        va.fecha_aplicacion,
        va.fecha_proxima,
        va.observaciones,

        m.nombre AS nombre_mascota,
        m.fallecido,

        e.nombre_especie,
        r.nombre_raza,

        p.id_propietario,
        p.nombre AS nombre_propietario,
        p.apellido AS apellido_propietario

    FROM vacunaciones va

    INNER JOIN mascotas m
        ON va.id_mascota = m.id_mascota

    INNER JOIN propietarios p
        ON m.id_propietario = p.id_propietario

    LEFT JOIN especies e
        ON m.id_especie = e.id_especie

    LEFT JOIN razas r
        ON m.id_raza = r.id_raza

    WHERE EXISTS (
        SELECT 1
        FROM turnos tv
        WHERE tv.id_mascota = va.id_mascota
          AND tv.id_veterinario = ?
    )
      AND (
            ? = ''
            OR m.nombre LIKE CONCAT('%', ?, '%')
            OR p.nombre LIKE CONCAT('%', ?, '%')
            OR p.apellido LIKE CONCAT('%', ?, '%')
            OR CONCAT(p.nombre, ' ', p.apellido) LIKE CONCAT('%', ?, '%')
            OR va.nombre_vacuna LIKE CONCAT('%', ?, '%')
        )
        AND (
            ? = 0
            OR va.id_mascota = ?
        )

    ORDER BY va.fecha_aplicacion DESC, va.id_vacunacion DESC
";

$stmt = $conn->prepare($sqlVacunas);
$stmt->bind_param(
    'issssssii',
    $idVeterinario,
    $busqueda,
    $busqueda,
    $busqueda,
    $busqueda,
    $busqueda,
    $busqueda,
    $idMascotaFiltro,
    $idMascotaFiltro
);
$stmt->execute();
$resultadoVacunas = $stmt->get_result();

$vacunacionesBase = [];

while ($fila = $resultadoVacunas->fetch_assoc()) {
    $fila['estado_calculado'] = estadoVacunaVeterinario($fila['fecha_proxima']);
    $vacunacionesBase[] = $fila;
}

$stmt->close();

$contadorAlDia = 0;
$contadorProximas = 0;
$contadorVencidas = 0;
$contadorSinFecha = 0;

foreach ($vacunacionesBase as $vacuna) {
    $codigo = $vacuna['estado_calculado']['codigo'];

    if ($codigo === 'al_dia') $contadorAlDia++;
    if ($codigo === 'proxima') $contadorProximas++;
    if ($codigo === 'vencida') $contadorVencidas++;
    if ($codigo === 'sin_fecha') $contadorSinFecha++;
}

$vacunaciones = array_values(array_filter(
    $vacunacionesBase,
    function ($vacuna) use ($estadoFiltro) {
        return $estadoFiltro === '' || $vacuna['estado_calculado']['codigo'] === $estadoFiltro;
    }
));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vacunaciones · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos/inicio.css">
    <link rel="stylesheet" href="../estilos/vacunaciones_veterinario.css">
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">
        <div class="col-12 col-lg-2 panel-lateral">
            <a href="inicio_veterinario.php" class="logo-lateral">
                <img src="../img/VetClinic Pro.png" alt="Logo VetClinic Pro">
                <div>
                    <div class="nombre-sistema">VetClinic Pro</div>
                    <div class="subtitulo-sistema">Portal del veterinario</div>
                </div>
            </a>
            <div class="seccion-nav">Principal</div>
            <a href="inicio_veterinario.php" class="item-menu">
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
            <a href="vacunaciones_veterinario.php" class="item-menu activo">
                <i class="bi bi-shield-plus"></i> Vacunaciones
            </a>
            <a href="mensajes.php" class="item-menu"><i class="bi bi-chat-dots"></i> Mensajes</a>            <a href="estadisticas.php" class="item-menu">
                <i class="bi bi-graph-up-arrow"></i> Estadísticas
            </a>

            <div class="seccion-nav">Configuración</div>
            <a href="configuracion_veterinario.php" class="item-menu">
                    <i class="bi bi-gear"></i> Mi configuración
                </a>
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
        <div class="col-12 col-lg-10 area-principal">
            <div class="barra-superior">
                <div>
                    <p class="titulo-pagina">Vacunaciones</p>
                    <p class="fecha-hoy">Control y registro de vacunas de los pacientes.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="inicio_veterinario.php" class="icono-barra" title="Volver al inicio">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <a href="../logout.php" class="icono-barra" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="encabezado-vacunaciones mb-4">
                <div>
                    <h2 class="titulo-pagina">Vacunaciones registradas</h2>
                    <p class="fecha-hoy">Consultá el estado de las vacunas y registrá nuevas aplicaciones.</p>
                </div>
                <button
                    class="btn-registrar-vacuna"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#formularioVacuna"
                    aria-expanded="<?= !empty($errores) ? 'true' : 'false' ?>">
                    <i class="bi bi-plus-circle"></i>
                    Registrar vacunación
                </button>
            </div>
            <?php if ($mensaje !== ''): ?>
                <div class="alerta-vacunacion alerta-vacunacion-exito mb-4">
                    <i class="bi bi-check-circle"></i>
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($errores)): ?>
                <div class="alerta-vacunacion alerta-vacunacion-error mb-4">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>
                        <strong>Revisá los siguientes datos:</strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($errores as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            <div class="collapse <?= !empty($errores) ? 'show' : '' ?>" id="formularioVacuna">
                <section class="tarjeta formulario-vacunacion mb-4">
                    <div class="titulo-tarjeta mb-1">
                        <i class="bi bi-shield-plus"></i>
                        Registrar vacunación
                    </div>
                    <p class="texto-ayuda-formulario">Cargá la vacuna aplicada a una mascota activa.</p>
                    <?php if (!empty($mascotasActivas)): ?>
                        <form method="POST" action="vacunaciones_veterinario.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="accion" value="registrar">
                            <div class="row g-3">
                                <div class="col-12 col-lg-6">
                                    <label for="id_mascota_registro" class="form-label">
                                        Mascota <span class="campo-requerido">*</span>
                                    </label>
                                    <select class="form-select" id="id_mascota_registro" name="id_mascota" required>
                                        <option value="">Seleccioná una mascota...</option>
                                        <?php foreach ($mascotasActivas as $mascota): ?>
                                            <option
                                                value="<?= (int) $mascota['id_mascota'] ?>"
                                                <?= $idMascotaPreseleccionada === (int) $mascota['id_mascota'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(
                                                    $mascota['nombre'] . ' — ' .
                                                    $mascota['nombre_propietario'] . ' ' .
                                                    $mascota['apellido_propietario']
                                                ) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label for="nombre_vacuna" class="form-label">
                                        Vacuna <span class="campo-requerido">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="nombre_vacuna"
                                        name="nombre_vacuna"
                                        placeholder="Ej.: Antirrábica"
                                        value="<?= htmlspecialchars($_POST['nombre_vacuna'] ?? '') ?>"
                                        required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="fecha_aplicacion" class="form-label">
                                        Fecha de aplicación <span class="campo-requerido">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        id="fecha_aplicacion"
                                        name="fecha_aplicacion"
                                        max="<?= date('Y-m-d') ?>"
                                        value="<?= htmlspecialchars($_POST['fecha_aplicacion'] ?? date('Y-m-d')) ?>"
                                        required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="fecha_proxima" class="form-label">Próxima dosis</label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        id="fecha_proxima"
                                        name="fecha_proxima"
                                        value="<?= htmlspecialchars($_POST['fecha_proxima'] ?? '') ?>">
                                    <div class="ayuda-campo-vacuna">Puede quedar vacío si todavía no corresponde definirla.</div>
                                </div>
                                <div class="col-12">
                                    <label for="observaciones" class="form-label">Observaciones</label>
                                    <textarea
                                        class="form-control"
                                        id="observaciones"
                                        name="observaciones"
                                        rows="3"
                                        placeholder="Información adicional sobre la aplicación..."><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>                                </div>
                            </div>
                            <div class="acciones-formulario-vacuna">
                                <button type="submit" class="btn-guardar-vacuna">
                                    <i class="bi bi-check-circle"></i>
                                    Guardar vacunación
                                </button>
                                <button
                                    type="button"
                                    class="btn-cancelar-vacuna"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#formularioVacuna">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="estado-vacio-formulario-vacuna">
                            <i class="bi bi-heart-pulse"></i>
                            <div>
                                <strong>No hay mascotas activas</strong>
                                <span>Para registrar una vacuna debe existir al menos una mascota activa.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            <div class="resumen-vacunaciones mb-4">
                <div class="tarjeta-resumen-vacuna">
                    <span>Registros</span>
                    <strong><?= count($vacunacionesBase) ?></strong>
                </div>
                <div class="tarjeta-resumen-vacuna resumen-ok">
                    <span>Al día</span>
                    <strong><?= $contadorAlDia ?></strong>
                </div>
                <div class="tarjeta-resumen-vacuna resumen-proxima">
                    <span>Próximas</span>
                    <strong><?= $contadorProximas ?></strong>
                </div>
                <div class="tarjeta-resumen-vacuna resumen-vencida">
                    <span>Vencidas</span>
                    <strong><?= $contadorVencidas ?></strong>
                </div>
            </div>
            <section class="tarjeta filtros-vacunaciones mb-4">
                <div class="titulo-tarjeta mb-3">
                    <i class="bi bi-funnel"></i>
                    Buscar y filtrar
                </div>
                <form method="GET" action="vacunaciones_veterinario.php">
                    <div class="grilla-filtros-vacunaciones">
                        <div class="campo-filtro-vacuna">
                            <label for="buscar">Buscar</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="buscar"
                                    name="buscar"
                                    placeholder="Mascota, propietario o vacuna"
                                    value="<?= htmlspecialchars($busqueda) ?>">
                            </div>
                        </div>
                        <div class="campo-filtro-vacuna">
                            <label for="id_mascota">Mascota</label>
                            <select class="form-select" id="id_mascota" name="id_mascota">
                                <option value="0">Todas las mascotas</option>
                                <?php foreach ($mascotasFiltro as $mascota): ?>
                                    <option
                                        value="<?= (int) $mascota['id_mascota'] ?>"
                                        <?= $idMascotaFiltro === (int) $mascota['id_mascota'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(
                                            $mascota['nombre'] . ' — ' .
                                            $mascota['nombre_propietario'] . ' ' .
                                            $mascota['apellido_propietario']
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo-filtro-vacuna">
                            <label for="estado">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="" <?= $estadoFiltro === '' ? 'selected' : '' ?>>Todos los estados</option>
                                <option value="al_dia" <?= $estadoFiltro === 'al_dia' ? 'selected' : '' ?>>Al día</option>
                                <option value="proxima" <?= $estadoFiltro === 'proxima' ? 'selected' : '' ?>>Próxima</option>
                                <option value="vencida" <?= $estadoFiltro === 'vencida' ? 'selected' : '' ?>>Vencida</option>
                                <option value="sin_fecha" <?= $estadoFiltro === 'sin_fecha' ? 'selected' : '' ?>>Sin fecha</option>
                            </select>
                        </div>
                    </div>
                    <div class="acciones-filtros-vacunaciones">
                        <button type="submit" class="btn-buscar-vacunacion">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                        <?php if ($busqueda !== '' || $idMascotaFiltro > 0 || $estadoFiltro !== ''): ?>
                            <a href="vacunaciones_veterinario.php" class="btn-limpiar-vacunacion">
                                <i class="bi bi-x-circle"></i> Limpiar filtros
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>
            <?php if (!empty($vacunaciones)): ?>
                <div class="lista-vacunaciones-veterinario">
                    <?php foreach ($vacunaciones as $vacuna): ?>
                        <?php $estado = $vacuna['estado_calculado']; ?>
                        <article class="tarjeta tarjeta-vacunacion">
                            <div class="icono-vacunacion <?= htmlspecialchars($estado['clase']) ?>">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div class="contenido-vacunacion">
                                <div class="cabecera-registro-vacuna">
                                    <div>
                                        <h3><?= htmlspecialchars($vacuna['nombre_vacuna']) ?></h3>
                                        <div class="paciente-vacunacion">
                                            <a href="ficha_mascota.php?id_mascota=<?= (int) $vacuna['id_mascota'] ?>">
                                                <?= htmlspecialchars($vacuna['nombre_mascota']) ?>
                                            </a>
                                            <span>
                                                <?= htmlspecialchars($vacuna['nombre_especie'] ?? 'Especie no registrada') ?>
                                                <?php if (!empty($vacuna['nombre_raza'])): ?>
                                                    · <?= htmlspecialchars($vacuna['nombre_raza']) ?>
                                                <?php endif; ?>
                                            </span>
                                            <?php if ((int) $vacuna['fallecido'] === 1): ?>
                                                <span class="badge-mascota-fallecida">Fallecida</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="badge-estado-vacuna <?= htmlspecialchars($estado['clase']) ?>">
                                        <i class="bi <?= htmlspecialchars($estado['icono']) ?>"></i>
                                        <?= htmlspecialchars($estado['texto']) ?>
                                    </span>
                                </div>
                                <div class="datos-vacunacion">
                                    <div>
                                        <span>Aplicada</span>
                                        <strong><?= htmlspecialchars(formatearFechaVacuna($vacuna['fecha_aplicacion'])) ?></strong>
                                    </div>
                                    <div>
                                        <span>Próxima dosis</span>
                                        <strong><?= htmlspecialchars(formatearFechaVacuna($vacuna['fecha_proxima'])) ?></strong>
                                    </div>
                                    <div>
                                        <span>Propietario</span>
                                        <a href="ficha_propietario.php?id_propietario=<?= (int) $vacuna['id_propietario'] ?>">
                                            <?= htmlspecialchars(
                                                $vacuna['nombre_propietario'] . ' ' .
                                                $vacuna['apellido_propietario']
                                            ) ?>
                                        </a>
                                    </div>
                                </div>
                                <?php if (!empty($vacuna['observaciones'])): ?>
                                    <div class="observaciones-vacunacion">
                                        <i class="bi bi-chat-left-text"></i>
                                        <p><?= nl2br(htmlspecialchars($vacuna['observaciones'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                <div class="acciones-registro-vacuna">
                                    <a
                                        href="ficha_mascota.php?id_mascota=<?= (int) $vacuna['id_mascota'] ?>"
                                        class="btn-ver-mascota-vacuna">
                                        <i class="bi bi-heart-pulse"></i>
                                        Ver mascota
                                    </a>
                                    <a
                                        href="historiales_veterinario.php?id_mascota=<?= (int) $vacuna['id_mascota'] ?>"
                                        class="btn-ver-historial-vacuna">
                                        <i class="bi bi-journal-medical"></i>
                                        Ver historial
                                    </a>
                                    <a
                                        href="../reportes/pdf.php?tipo=vacuna&id=<?= (int) $vacuna['id_vacunacion'] ?>"
                                        class="btn-ver-historial-vacuna">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                        Constancia PDF
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="tarjeta estado-vacio-vacunaciones">
                    <div class="icono-vacio-vacunaciones">
                        <i class="bi bi-shield-x"></i>
                    </div>
                    <div>
                        <h3>No encontramos vacunaciones</h3>
                        <p>
                            <?php if ($busqueda !== '' || $idMascotaFiltro > 0 || $estadoFiltro !== ''): ?>
                                No hay registros que coincidan con los filtros seleccionados.
                            <?php else: ?>
                                Todavía no hay vacunaciones registradas.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="../mensajeria/notificaciones.js" defer></script>
</body>
</html>
