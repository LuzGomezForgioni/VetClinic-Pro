<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (empty($_SESSION['logueado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'veterinario') {
    header('Location: ../index.php');
    exit;
}

include("../config/conexion.php");
require_once '../config/proteger_acceso_veterinario.php';

$nombre = $_SESSION['usuario_nombre'] ?? 'Doctor/a';
$apellido = $_SESSION['usuario_apellido'] ?? '';

$id_mascota = (int) ($_GET['id_mascota'] ?? 0);

if ($id_mascota <= 0) {
    header('Location: mascotas_veterinario.php');
    exit;
}

// ======================================================
// MASCOTA + PROPIETARIO
// ======================================================

$sql_mascota = "
    SELECT
        m.id_mascota,
        m.id_propietario,
        m.nombre,
        m.fecha_nacimiento,
        m.sexo,
        m.peso_kg,
        m.color,
        m.castrado,
        m.fallecido,
        e.nombre_especie,
        r.nombre_raza,
        p.nombre AS nombre_propietario,
        p.apellido AS apellido_propietario,
        p.email AS email_propietario,
        p.telefono AS telefono_propietario,
        p.fecha_registro
    FROM mascotas m
    INNER JOIN propietarios p
        ON m.id_propietario = p.id_propietario
    LEFT JOIN especies e
        ON m.id_especie = e.id_especie
    LEFT JOIN razas r
        ON m.id_raza = r.id_raza
    WHERE m.id_mascota = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql_mascota);
$stmt->bind_param("i", $id_mascota);
$stmt->execute();
$mascota = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$mascota) {
    header('Location: mascotas_veterinario.php');
    exit;
}


// ======================================================
// MENSAJES DE ACCIONES
// ======================================================

$mensaje_accion = $_SESSION['mensaje_ficha_mascota'] ?? '';
$tipo_mensaje_accion = $_SESSION['tipo_mensaje_ficha_mascota'] ?? '';
unset($_SESSION['mensaje_ficha_mascota'], $_SESSION['tipo_mensaje_ficha_mascota']);

$id_veterinario_actual = (int) ($_SESSION['usuario_id'] ?? 0);

// ======================================================
// ACCIONES DE GESTIÓN DE LA MASCOTA
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    // --------------------------------------------------
    // CAMBIAR PROPIETARIO
    // --------------------------------------------------
    if ($accion === 'cambiar_propietario') {

        $nuevo_propietario = (int) ($_POST['nuevo_propietario'] ?? 0);

        if ($nuevo_propietario <= 0) {
            $_SESSION['mensaje_ficha_mascota'] = 'Seleccioná un propietario válido.';
            $_SESSION['tipo_mensaje_ficha_mascota'] = 'danger';
        } elseif ($nuevo_propietario === (int) $mascota['id_propietario']) {
            $_SESSION['mensaje_ficha_mascota'] = 'La mascota ya pertenece a ese propietario.';
            $_SESSION['tipo_mensaje_ficha_mascota'] = 'warning';
        } else {

            $stmt = $conn->prepare("SELECT id_propietario FROM propietarios WHERE id_propietario = ? LIMIT 1");
            $stmt->bind_param('i', $nuevo_propietario);
            $stmt->execute();
            $propietario_existe = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$propietario_existe) {
                $_SESSION['mensaje_ficha_mascota'] = 'El propietario seleccionado no existe.';
                $_SESSION['tipo_mensaje_ficha_mascota'] = 'danger';
            } else {
                try {
                    $conn->begin_transaction();

                    $stmt = $conn->prepare("UPDATE mascotas SET id_propietario = ? WHERE id_mascota = ?");
                    $stmt->bind_param('ii', $nuevo_propietario, $id_mascota);
                    $stmt->execute();
                    $stmt->close();

                    // Los turnos futuros no realizados pasan al nuevo responsable.
                    // Los turnos históricos se conservan tal como ocurrieron.
                    $stmt = $conn->prepare("\n                        UPDATE turnos\n                        SET id_propietario = ?\n                        WHERE id_mascota = ?\n                          AND fecha_hora >= NOW()\n                          AND estado NOT IN ('cancelado', 'realizado')\n                    ");
                    $stmt->bind_param('ii', $nuevo_propietario, $id_mascota);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();

                    $_SESSION['mensaje_ficha_mascota'] = 'El propietario de la mascota se actualizó correctamente.';
                    $_SESSION['tipo_mensaje_ficha_mascota'] = 'success';
                } catch (Throwable $e) {
                    try { $conn->rollback(); } catch (Throwable $ignorado) {}
                    $_SESSION['mensaje_ficha_mascota'] = 'No se pudo cambiar el propietario.';
                    $_SESSION['tipo_mensaje_ficha_mascota'] = 'danger';
                }
            }
        }

        header('Location: ficha_mascota.php?id_mascota=' . $id_mascota);
        exit;
    }

    // --------------------------------------------------
    // MARCAR COMO FALLECIDA / ACTIVA
    // --------------------------------------------------
    if ($accion === 'cambiar_estado') {

        $nuevo_estado = ((int) ($_POST['fallecido'] ?? 0) === 1) ? 1 : 0;

        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("UPDATE mascotas SET fallecido = ? WHERE id_mascota = ?");
            $stmt->bind_param('ii', $nuevo_estado, $id_mascota);
            $stmt->execute();
            $stmt->close();

            if ($nuevo_estado === 1) {
                // No dejamos turnos futuros activos para una mascota fallecida.
                $stmt = $conn->prepare("\n                    UPDATE turnos\n                    SET estado = 'cancelado'\n                    WHERE id_mascota = ?\n                      AND fecha_hora >= NOW()\n                      AND estado IN ('pendiente', 'confirmado')\n                ");
                $stmt->bind_param('i', $id_mascota);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            $_SESSION['mensaje_ficha_mascota'] = $nuevo_estado === 1
                ? 'La mascota fue marcada como fallecida. Sus turnos futuros activos fueron cancelados.'
                : 'La mascota volvió a quedar marcada como activa.';
            $_SESSION['tipo_mensaje_ficha_mascota'] = 'success';
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $ignorado) {}
            $_SESSION['mensaje_ficha_mascota'] = 'No se pudo actualizar el estado de la mascota.';
            $_SESSION['tipo_mensaje_ficha_mascota'] = 'danger';
        }

        header('Location: ficha_mascota.php?id_mascota=' . $id_mascota);
        exit;
    }

    // --------------------------------------------------
    // ELIMINAR REGISTRO SOLO SI FUE UN ERROR DE CARGA
    // --------------------------------------------------
    if ($accion === 'eliminar_mascota') {

        $stmt = $conn->prepare("\n            SELECT\n                (SELECT COUNT(*) FROM turnos WHERE id_mascota = ?) AS turnos,\n                (SELECT COUNT(*) FROM historia_clinica WHERE id_mascota = ?) AS historias,\n                (SELECT COUNT(*) FROM vacunaciones WHERE id_mascota = ?) AS vacunas\n        ");
        $stmt->bind_param('iii', $id_mascota, $id_mascota, $id_mascota);
        $stmt->execute();
        $relaciones = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $tiene_datos =
            (int) ($relaciones['turnos'] ?? 0) > 0 ||
            (int) ($relaciones['historias'] ?? 0) > 0 ||
            (int) ($relaciones['vacunas'] ?? 0) > 0;

        if ($tiene_datos) {
            $_SESSION['mensaje_ficha_mascota'] = 'No se puede eliminar esta mascota porque ya tiene turnos, historia clínica o vacunaciones. Podés cambiarla de propietario o marcarla como fallecida.';
            $_SESSION['tipo_mensaje_ficha_mascota'] = 'danger';

            header('Location: ficha_mascota.php?id_mascota=' . $id_mascota);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM mascotas WHERE id_mascota = ?");
        $stmt->bind_param('i', $id_mascota);

        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['mensaje_mascotas'] = 'La mascota fue eliminada porque no tenía información clínica asociada.';
            $_SESSION['tipo_mensaje_mascotas'] = 'success';
            header('Location: mascotas_veterinario.php');
            exit;
        }

        $stmt->close();
        $_SESSION['mensaje_ficha_mascota'] = 'No se pudo eliminar la mascota.';
        $_SESSION['tipo_mensaje_ficha_mascota'] = 'danger';
        header('Location: ficha_mascota.php?id_mascota=' . $id_mascota);
        exit;
    }
}

// ======================================================
// PROPIETARIOS DISPONIBLES PARA REASIGNAR
// ======================================================

$propietarios_disponibles = [];
$resultado_propietarios = $conn->query("\n    SELECT id_propietario, nombre, apellido\n    FROM propietarios\n    ORDER BY apellido ASC, nombre ASC\n");

if ($resultado_propietarios) {
    $propietarios_disponibles = $resultado_propietarios->fetch_all(MYSQLI_ASSOC);
}

// ======================================================
// HISTORIA CLÍNICA
// ======================================================

$historial = [];

$sql_historial = "
    SELECT
        hc.id_historia,
        hc.fecha_consulta,
        hc.motivo_consulta,
        hc.diagnostico,
        hc.tratamiento,
        hc.medicacion,
        hc.peso_registro,
        hc.proximos_pasos,
        v.nombre AS nombre_veterinario,
        v.apellido AS apellido_veterinario,
        t.id_turno,
        t.fecha_hora
    FROM historia_clinica hc
    INNER JOIN veterinarios v
        ON hc.id_veterinario = v.id_veterinario
    LEFT JOIN turnos t
        ON hc.id_turno = t.id_turno
    WHERE hc.id_mascota = ?
    ORDER BY
        COALESCE(t.fecha_hora, CONCAT(hc.fecha_consulta, ' 00:00:00')) DESC,
        hc.id_historia DESC
    LIMIT 5
";

$stmt = $conn->prepare($sql_historial);
$stmt->bind_param("i", $id_mascota);
$stmt->execute();
$resultado_historial = $stmt->get_result();

while ($fila = $resultado_historial->fetch_assoc()) {
    $historial[] = $fila;
}
$stmt->close();

// ======================================================
// VACUNACIONES
// ======================================================

$vacunaciones = [];

$sql_vacunas = "
    SELECT
        id_vacunacion,
        nombre_vacuna,
        fecha_aplicacion,
        fecha_proxima,
        observaciones
    FROM vacunaciones
    WHERE id_mascota = ?
    ORDER BY fecha_aplicacion DESC, id_vacunacion DESC
    LIMIT 5
";

$stmt = $conn->prepare($sql_vacunas);
$stmt->bind_param("i", $id_mascota);
$stmt->execute();
$resultado_vacunas = $stmt->get_result();

while ($fila = $resultado_vacunas->fetch_assoc()) {
    $vacunaciones[] = $fila;
}
$stmt->close();

// ======================================================
// PRÓXIMOS TURNOS
// ======================================================

$proximos_turnos = [];

$sql_turnos = "
    SELECT
        id_turno,
        fecha_hora,
        motivo,
        estado
    FROM turnos
    WHERE id_mascota = ?
      AND fecha_hora >= NOW()
      AND estado <> 'cancelado'
    ORDER BY fecha_hora ASC
    LIMIT 3
";

$stmt = $conn->prepare($sql_turnos);
$stmt->bind_param("i", $id_mascota);
$stmt->execute();
$resultado_turnos = $stmt->get_result();

while ($fila = $resultado_turnos->fetch_assoc()) {
    $proximos_turnos[] = $fila;
}
$stmt->close();

// ======================================================
// FUNCIONES
// ======================================================

function calcularEdadFicha(?string $fecha_nacimiento): string
{
    if (empty($fecha_nacimiento)) {
        return 'No registrada';
    }

    try {
        $nacimiento = new DateTime($fecha_nacimiento);
        $hoy = new DateTime();
        $edad = $hoy->diff($nacimiento);

        if ($edad->y > 0) {
            return $edad->y . ($edad->y === 1 ? ' año' : ' años');
        }

        if ($edad->m > 0) {
            return $edad->m . ($edad->m === 1 ? ' mes' : ' meses');
        }

        return 'Menos de 1 mes';
    } catch (Exception $e) {
        return 'No registrada';
    }
}

function sexoFicha(?string $sexo): string
{
    $sexo = strtoupper(trim((string) $sexo));

    if (in_array($sexo, ['F', 'HEMBRA'], true)) {
        return 'Hembra';
    }

    if (in_array($sexo, ['M', 'MACHO'], true)) {
        return 'Macho';
    }

    return $sexo !== '' ? ucfirst(strtolower($sexo)) : 'No registrado';
}

function castradoFicha($castrado): string
{
    if ($castrado === null || $castrado === '') {
        return 'No registrado';
    }

    return ((int) $castrado === 1) ? 'Sí' : 'No';
}

function fechaFicha(?string $fecha): string
{
    if (empty($fecha)) {
        return 'No registrada';
    }

    return date('d/m/Y', strtotime($fecha));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($mascota['nombre']) ?> · VetClinic Pro</title>

    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../estilos/inicio.css">
    <link rel="stylesheet" href="../estilos/mascotas_veterinario.css">
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
                <i class="bi bi-grid"></i>
                Inicio
            </a>

            <a href="turnos_veterinario.php" class="item-menu">
                <i class="bi bi-calendar-check"></i>
                Turnos
            </a>

            <a href="mascotas_veterinario.php" class="item-menu activo">
                <i class="bi bi-heart-pulse"></i>
                Mascotas
            </a>

            <a href="#" class="item-menu">
                <i class="bi bi-journal-medical"></i>
                Historial clínico
            </a>

            <div class="seccion-nav">Gestión</div>

            <a href="propietarios_veterinario.php" class="item-menu">
                <i class="bi bi-people"></i>
                Propietarios
            </a>

            <a href="#" class="item-menu">
                <i class="bi bi-shield-plus"></i>
                Vacunaciones
            </a>

            <a href="#" class="item-menu">
                <i class="bi bi-chat-dots"></i>
                Mensajes
            </a>

            <div class="seccion-nav">Configuración</div>

            <a href="horarios_atencion.php" class="item-menu">
                <i class="bi bi-clock"></i>
                Horarios de atención
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
                    <p class="titulo-pagina">Ficha de mascota</p>
                    <p class="fecha-hoy">Información general y clínica del paciente.</p>
                </div>

                <div class="d-flex gap-2">
                    <a href="mascotas_veterinario.php" class="icono-barra" title="Volver a mascotas">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <a href="../logout.php" class="icono-barra" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>

            <?php if ($mensaje_accion !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($tipo_mensaje_accion) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje_accion) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <section class="cabecera-ficha-mascota mb-4 <?= (int) $mascota['fallecido'] === 1 ? 'cabecera-ficha-fallecida' : '' ?>">

                <div class="avatar-ficha-mascota">
                    <i class="bi bi-heart-pulse"></i>
                </div>

                <div class="flex-grow-1">
                    <div class="etiqueta-paciente">Paciente</div>

                    <h1><?= htmlspecialchars($mascota['nombre']) ?></h1>

                    <p>
                        <?= htmlspecialchars($mascota['nombre_especie'] ?? 'Especie no registrada') ?>

                        <?php if (!empty($mascota['nombre_raza'])): ?>
                            · <?= htmlspecialchars($mascota['nombre_raza']) ?>
                        <?php endif; ?>
                    </p>

                    <span class="estado-ficha-mascota <?= (int) $mascota['fallecido'] === 1 ? 'fallecida' : 'activa' ?>">
                        <i class="bi <?= (int) $mascota['fallecido'] === 1 ? 'bi-heartbreak' : 'bi-check-circle' ?>"></i>
                        <?= (int) $mascota['fallecido'] === 1 ? 'Fallecida' : 'Activa' ?>
                    </span>
                </div>

                <a
                    href="turnos_veterinario.php?mascota=<?= urlencode($mascota['nombre']) ?>"
                    class="btn-ver-turnos-ficha">
                    <i class="bi bi-calendar-check"></i>
                    Ver turnos
                </a>

            </section>

            <div class="row g-4">

                <div class="col-12 col-xl-8">

                    <section class="tarjeta mb-4">

                        <div class="titulo-tarjeta">
                            <i class="bi bi-heart-pulse"></i>
                            Datos de la mascota
                        </div>

                        <div class="grilla-datos-ficha">

                            <div class="dato-ficha">
                                <span>Sexo</span>
                                <strong><?= htmlspecialchars(sexoFicha($mascota['sexo'])) ?></strong>
                            </div>

                            <div class="dato-ficha">
                                <span>Edad</span>
                                <strong><?= htmlspecialchars(calcularEdadFicha($mascota['fecha_nacimiento'])) ?></strong>
                            </div>

                            <div class="dato-ficha">
                                <span>Fecha de nacimiento</span>
                                <strong><?= htmlspecialchars(fechaFicha($mascota['fecha_nacimiento'])) ?></strong>
                            </div>

                            <div class="dato-ficha">
                                <span>Peso</span>
                                <strong>
                                    <?php if ($mascota['peso_kg'] !== null && $mascota['peso_kg'] !== ''): ?>
                                        <?= htmlspecialchars(rtrim(rtrim((string) $mascota['peso_kg'], '0'), '.')) ?> kg
                                    <?php else: ?>
                                        No registrado
                                    <?php endif; ?>
                                </strong>
                            </div>

                            <div class="dato-ficha">
                                <span>Color</span>
                                <strong><?= htmlspecialchars($mascota['color'] ?: 'No registrado') ?></strong>
                            </div>

                            <div class="dato-ficha">
                                <span>Castrado/a</span>
                                <strong><?= htmlspecialchars(castradoFicha($mascota['castrado'])) ?></strong>
                            </div>
                            <div class="dato-ficha">
                                <span>Estado</span>
                                <strong class="texto-estado-mascota <?= (int) $mascota['fallecido'] === 1 ? 'fallecida' : 'activa' ?>">
                                    <?= (int) $mascota['fallecido'] === 1 ? 'Fallecida' : 'Activa' ?>
                                </strong>
                            </div>

                        </div>

                    </section>

                    <section class="tarjeta mb-4">

                        <div class="cabecera-seccion-ficha">
                            <div class="titulo-tarjeta mb-0">
                                <i class="bi bi-journal-medical"></i>
                                Últimas consultas
                            </div>

                            <span class="etiqueta-cantidad">
                                <?= count($historial) ?>
                                <?= count($historial) === 1 ? 'registro' : 'registros' ?>
                            </span>
                        </div>

                        <?php if (!empty($historial)): ?>

                            <div class="lista-historial-ficha">

                                <?php foreach ($historial as $registro): ?>

                                    <article class="registro-historial-ficha">

                                        <div class="fecha-registro-ficha">
                                            <strong>
                                                <?= htmlspecialchars(fechaFicha($registro['fecha_consulta'])) ?>
                                            </strong>

                                            <span>
                                                <?= htmlspecialchars(
                                                    trim(
                                                        ($registro['nombre_veterinario'] ?? '') . ' ' .
                                                        ($registro['apellido_veterinario'] ?? '')
                                                    )
                                                ) ?>
                                            </span>
                                        </div>

                                        <div class="contenido-registro-ficha">
                                            <h3>
                                                <?= htmlspecialchars($registro['motivo_consulta'] ?: 'Consulta') ?>
                                            </h3>

                                            <?php if (!empty($registro['diagnostico'])): ?>
                                                <p>
                                                    <strong>Diagnóstico:</strong>
                                                    <?= htmlspecialchars($registro['diagnostico']) ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if (!empty($registro['tratamiento'])): ?>
                                                <p>
                                                    <strong>Tratamiento:</strong>
                                                    <?= htmlspecialchars($registro['tratamiento']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>

                                    </article>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="estado-vacio-seccion">
                                <i class="bi bi-journal-medical"></i>
                                <div>
                                    <strong>Sin consultas registradas</strong>
                                    <span>
                                        Cuando la mascota sea atendida, las visitas aparecerán acá.
                                    </span>
                                </div>
                            </div>

                        <?php endif; ?>

                    </section>

                    <section class="tarjeta">

                        <div class="cabecera-seccion-ficha">
                            <div class="titulo-tarjeta mb-0">
                                <i class="bi bi-shield-plus"></i>
                                Vacunaciones
                            </div>

                            <span class="etiqueta-cantidad">
                                <?= count($vacunaciones) ?>
                                <?= count($vacunaciones) === 1 ? 'vacuna' : 'vacunas' ?>
                            </span>
                        </div>

                        <?php if (!empty($vacunaciones)): ?>

                            <div class="lista-vacunas-ficha">

                                <?php foreach ($vacunaciones as $vacuna): ?>

                                    <?php
                                    $vencida = false;

                                    if (!empty($vacuna['fecha_proxima'])) {
                                        $vencida =
                                            strtotime($vacuna['fecha_proxima']) <
                                            strtotime(date('Y-m-d'));
                                    }
                                    ?>

                                    <article class="vacuna-ficha">

                                        <div class="icono-vacuna-ficha">
                                            <i class="bi bi-shield-check"></i>
                                        </div>

                                        <div class="flex-grow-1">
                                            <h3><?= htmlspecialchars($vacuna['nombre_vacuna']) ?></h3>
                                            <p>
                                                Aplicada:
                                                <?= htmlspecialchars(fechaFicha($vacuna['fecha_aplicacion'])) ?>
                                            </p>
                                        </div>

                                        <?php if (!empty($vacuna['fecha_proxima'])): ?>

                                            <div class="proxima-vacuna-ficha <?= $vencida ? 'vencida' : '' ?>">
                                                <span><?= $vencida ? 'Vencida' : 'Próxima' ?></span>
                                                <strong>
                                                    <?= htmlspecialchars(fechaFicha($vacuna['fecha_proxima'])) ?>
                                                </strong>
                                            </div>

                                        <?php endif; ?>

                                    </article>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="estado-vacio-seccion">
                                <i class="bi bi-shield-plus"></i>
                                <div>
                                    <strong>Sin vacunaciones registradas</strong>
                                    <span>No hay vacunas cargadas para esta mascota.</span>
                                </div>
                            </div>

                        <?php endif; ?>

                    </section>

                </div>

                <div class="col-12 col-xl-4">

                    <section class="tarjeta tarjeta-propietario-ficha mb-4">

                        <div class="titulo-tarjeta">
                            <i class="bi bi-person"></i>
                            Propietario
                        </div>

                        <div class="propietario-destacado">

                            <div class="avatar-propietario-ficha">
                                <?= strtoupper(
                                    substr($mascota['nombre_propietario'], 0, 1) .
                                    substr($mascota['apellido_propietario'], 0, 1)
                                ) ?>
                            </div>

                            <div>
                                <strong>
                                    <?= htmlspecialchars(
                                        $mascota['nombre_propietario'] . ' ' .
                                        $mascota['apellido_propietario']
                                    ) ?>
                                </strong>

                                <span>Responsable de la mascota</span>
                            </div>

                        </div>

                        <?php if (!empty($mascota['telefono_propietario'])): ?>
                            <div class="dato-contacto-propietario">
                                <i class="bi bi-telephone"></i>
                                <span><?= htmlspecialchars($mascota['telefono_propietario']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($mascota['email_propietario'])): ?>
                            <div class="dato-contacto-propietario">
                                <i class="bi bi-envelope"></i>
                                <span><?= htmlspecialchars($mascota['email_propietario']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="nota-propietario-ficha">
                            <i class="bi bi-info-circle"></i>
                            Esta mascota está vinculada a este propietario mediante su registro en el sistema.
                        </div>

                    </section>

                    <section class="tarjeta tarjeta-gestion-mascota mb-4">

                        <div class="titulo-tarjeta">
                            <i class="bi bi-sliders"></i>
                            Gestión de la mascota
                        </div>

                        <div class="bloque-gestion-mascota">
                            <label for="nuevo_propietario">Cambiar propietario</label>

                            <form method="POST" class="form-cambiar-propietario">
                                <input type="hidden" name="accion" value="cambiar_propietario">

                                <select
                                    id="nuevo_propietario"
                                    name="nuevo_propietario"
                                    class="form-select"
                                    required>

                                    <?php foreach ($propietarios_disponibles as $propietario_opcion): ?>
                                        <option
                                            value="<?= (int) $propietario_opcion['id_propietario'] ?>"
                                            <?= (int) $propietario_opcion['id_propietario'] === (int) $mascota['id_propietario'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($propietario_opcion['apellido'] . ', ' . $propietario_opcion['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="submit" class="btn-gestion-principal">
                                    <i class="bi bi-person-check"></i>
                                    Guardar propietario
                                </button>
                            </form>

                            <p class="ayuda-gestion-mascota">
                                La mascota nunca queda sin responsable. Los turnos futuros activos pasan al nuevo propietario; los turnos anteriores conservan el responsable histórico.
                            </p>
                        </div>

                        <div class="separador-gestion-mascota"></div>

                        <div class="bloque-gestion-mascota">
                            <span class="label-gestion-mascota">Estado de la mascota</span>

                            <form method="POST">
                                <input type="hidden" name="accion" value="cambiar_estado">
                                <input type="hidden" name="fallecido" value="<?= (int) $mascota['fallecido'] === 1 ? 0 : 1 ?>">

                                <button
                                    type="submit"
                                    class="<?= (int) $mascota['fallecido'] === 1 ? 'btn-reactivar-mascota' : 'btn-fallecida-mascota' ?>"
                                    onclick="return confirm('¿Confirmás este cambio de estado?');">

                                    <i class="bi <?= (int) $mascota['fallecido'] === 1 ? 'bi-arrow-counterclockwise' : 'bi-heartbreak' ?>"></i>

                                    <?= (int) $mascota['fallecido'] === 1
                                        ? 'Marcar como activa'
                                        : 'Marcar como fallecida'
                                    ?>
                                </button>
                            </form>

                            <?php if ((int) $mascota['fallecido'] === 0): ?>
                                <p class="ayuda-gestion-mascota">
                                    Si la marcás como fallecida, se conservarán su historia clínica y vacunas, pero no podrá recibir nuevos turnos.
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="separador-gestion-mascota"></div>

                        <div class="bloque-gestion-mascota zona-peligro-mascota">
                            <span class="label-gestion-mascota">Registro cargado por error</span>

                            <form method="POST">
                                <input type="hidden" name="accion" value="eliminar_mascota">

                                <button
                                    type="submit"
                                    class="btn-eliminar-registro-mascota"
                                    onclick="return confirm('¿Seguro que querés eliminar este registro? Solo se eliminará si no tiene turnos, historia clínica ni vacunas.');">
                                    <i class="bi bi-trash"></i>
                                    Eliminar registro
                                </button>
                            </form>

                            <p class="ayuda-gestion-mascota">
                                Solo funciona si la mascota no tiene turnos, historia clínica ni vacunaciones. Si ya posee información, el sistema no permitirá borrarla.
                            </p>
                        </div>

                    </section>

                    <section class="tarjeta">

                        <div class="titulo-tarjeta">
                            <i class="bi bi-calendar-check"></i>
                            Próximos turnos
                        </div>

                        <?php if (!empty($proximos_turnos)): ?>

                            <div class="lista-turnos-ficha">

                                <?php foreach ($proximos_turnos as $turno): ?>

                                    <a
                                        href="historial_clinico.php?id_turno=<?= (int) $turno['id_turno'] ?>"
                                        class="turno-ficha">

                                        <div class="fecha-turno-ficha">
                                            <strong>
                                                <?= date('d/m', strtotime($turno['fecha_hora'])) ?>
                                            </strong>
                                            <span>
                                                <?= date('H:i', strtotime($turno['fecha_hora'])) ?> hs
                                            </span>
                                        </div>

                                        <div class="flex-grow-1">
                                            <strong><?= htmlspecialchars($turno['motivo']) ?></strong>
                                            <span><?= htmlspecialchars(ucfirst($turno['estado'])) ?></span>
                                        </div>

                                        <i class="bi bi-chevron-right"></i>

                                    </a>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="estado-vacio-seccion estado-vacio-pequeno">
                                <i class="bi bi-calendar-x"></i>
                                <div>
                                    <strong>Sin próximos turnos</strong>
                                    <span>No hay turnos futuros para esta mascota.</span>
                                </div>
                            </div>

                        <?php endif; ?>

                    </section>

                </div>

            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
