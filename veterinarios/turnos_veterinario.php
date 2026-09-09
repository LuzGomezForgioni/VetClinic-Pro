<?php

session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');

// ======================================================
// VERIFICAR VETERINARIO LOGUEADO
// ======================================================

if (
    empty($_SESSION['logueado']) ||
    $_SESSION['tipo_usuario'] !== 'veterinario'
) {
    header('Location: ../index.php');
    exit;
}

include("../config/conexion.php");


// ======================================================
// DATOS DEL VETERINARIO
// ======================================================

$nombre = $_SESSION['usuario_nombre'] ?? 'Doctor/a';
$apellido = $_SESSION['usuario_apellido'] ?? '';

$sexo = strtoupper(
    trim($_SESSION['usuario_sexo'] ?? '')
);

$titulo = ($sexo === 'F') ? 'Dra.' : 'Dr.';

$id_veterinario = $_SESSION['usuario_id'];

// ======================================================
// FECHA ACTUAL
// ======================================================

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
    $dias[date('w')] .
    ' ' .
    date('j') .
    ' de ' .
    $meses[date('n') - 1] .
    ' de ' .
    date('Y');


// ======================================================
// FILTROS
// ======================================================

$filtro_fecha = trim(
    $_GET['fecha'] ?? ''
);

$filtro_mascota = trim(
    $_GET['mascota'] ?? ''
);

$filtro_propietario = trim(
    $_GET['propietario'] ?? ''
);

$filtro_estado = trim(
    $_GET['estado'] ?? ''
);


// ======================================================
// CONSULTA DE TURNOS
// ======================================================

$sql = "SELECT

            t.id_turno,
            t.fecha_hora,
            t.motivo,
            t.observaciones,
            t.estado,

            m.id_mascota,
            m.nombre AS nombre_mascota,
            m.fecha_nacimiento,
            m.peso_kg,
            m.color,
            m.castrado,
            m.sexo,

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

        WHERE t.id_veterinario = ?";


// ======================================================
// PARÁMETROS
// ======================================================

$parametros = [
    $id_veterinario
];

$tipos = "i";


// ======================================================
// FILTRO POR FECHA
// ======================================================

if ($filtro_fecha !== '') {

    $sql .= " AND DATE(t.fecha_hora) = ?";

    $parametros[] = $filtro_fecha;

    $tipos .= "s";
}


// ======================================================
// FILTRO POR MASCOTA
// ======================================================

if ($filtro_mascota !== '') {

    $sql .= " AND m.nombre LIKE ?";

    $parametros[] =
        "%" . $filtro_mascota . "%";

    $tipos .= "s";
}


// ======================================================
// FILTRO POR PROPIETARIO
// ======================================================

if ($filtro_propietario !== '') {

    $sql .= " AND (
                p.nombre LIKE ?
                OR p.apellido LIKE ?
                OR CONCAT(
                    p.nombre,
                    ' ',
                    p.apellido
                ) LIKE ?
              )";

    $busqueda_propietario =
        "%" . $filtro_propietario . "%";

    $parametros[] =
        $busqueda_propietario;

    $parametros[] =
        $busqueda_propietario;

    $parametros[] =
        $busqueda_propietario;

    $tipos .= "sss";
}


// ======================================================
// FILTRO POR ESTADO
// ======================================================

if ($filtro_estado !== '') {

    $sql .= " AND t.estado = ?";

    $parametros[] =
        $filtro_estado;

    $tipos .= "s";
}


// ======================================================
// ORDEN
// ======================================================

$sql .= " ORDER BY t.fecha_hora ASC";


// ======================================================
// EJECUTAR CONSULTA
// ======================================================

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $tipos,
    ...$parametros
);

$stmt->execute();

$resultado = $stmt->get_result();


// ======================================================
// GUARDAR TURNOS
// ======================================================

$turnos = [];

while ($turno = $resultado->fetch_assoc()) {

    $turnos[] = $turno;
}

$stmt->close();


// ======================================================
// SEPARAR TURNOS DE HOY Y PRÓXIMOS
// ======================================================

$turnos_hoy = [];

$turnos_proximos = [];

$turnos_fecha_seleccionada = [];

$fecha_hoy = date('Y-m-d');


// ======================================================
// SI SE FILTRÓ UNA FECHA ESPECÍFICA
// ======================================================

if ($filtro_fecha !== '') {

    // Todos los resultados ya vienen filtrados
    // por la fecha seleccionada desde SQL.

    $turnos_fecha_seleccionada = $turnos;
}


// ======================================================
// SI NO HAY FILTRO DE FECHA
// ======================================================

else {

    foreach ($turnos as $turno) {

        $fecha_turno = date(
            'Y-m-d',
            strtotime($turno['fecha_hora'])
        );

        $fecha_hora_turno = strtotime(
            $turno['fecha_hora']
        );


        // Turnos de hoy que todavía no pasaron

        if (
            $fecha_turno === $fecha_hoy &&
            $fecha_hora_turno >= time()
        ) {

            $turnos_hoy[] = $turno;
        }


        // Próximos turnos

        elseif (
            $fecha_hora_turno > time()
        ) {

            $turnos_proximos[] = $turno;
        }
    }
}


// ======================================================
// CALCULAR EDAD
// ======================================================

function calcularEdad($fecha_nacimiento)
{
    if (
        empty($fecha_nacimiento)
    ) {
        return 'No registrada';
    }

    $nacimiento = new DateTime(
        $fecha_nacimiento
    );

    $hoy = new DateTime();

    $edad = $hoy->diff(
        $nacimiento
    );


    if ($edad->y > 0) {

        return $edad->y .
            (
                $edad->y == 1
                ? ' año'
                : ' años'
            );
    }


    if ($edad->m > 0) {

        return $edad->m .
            (
                $edad->m == 1
                ? ' mes'
                : ' meses'
            );
    }


    return 'Menos de 1 mes';
}


// ======================================================
// MOSTRAR ESTADO
// ======================================================

function mostrarEstado($estado)
{
    if ($estado === 'confirmado') {

        return '
            <span class="badge-estado estado-confirmado">
                Confirmado
            </span>
        ';
    } elseif ($estado === 'realizado') {

        return '
            <span class="badge-estado estado-confirmado">
                Realizado
            </span>
        ';
    } elseif ($estado === 'cancelado') {

        return '
            <span class="badge-estado estado-pendiente">
                Cancelado
            </span>
        ';
    } else {

        return '
            <span class="badge-estado estado-pendiente">
                ' .
            htmlspecialchars(
                ucfirst($estado)
            )
            .
            '</span>
        ';
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Turnos — Veterinario · VetClinic Pro
    </title>


    <link
        rel="icon"
        type="image/png"
        href="../img/VetClinic Pro.png">


    <!-- Google Fonts -->

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


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- Bootstrap Icons -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">


    <!-- CSS del sistema -->

    <link
        rel="stylesheet"
        href="../estilos/inicio.css">

</head>


<body>


    <div class="container-fluid p-0">

        <div class="row g-0 min-vh-100">


            <!-- ================================================== -->
            <!-- BARRA LATERAL -->
            <!-- ================================================== -->

            <div class="col-12 col-lg-2 panel-lateral">


                <!-- Logo -->

                <a
                    href="inicio_veterinario.php"
                    class="logo-lateral">

                    <img
                        src="../img/VetClinic Pro.png"
                        alt="Logo VetClinic Pro">

                    <div>

                        <div class="nombre-sistema">
                            VetClinic Pro
                        </div>

                        <div class="subtitulo-sistema">
                            Portal del veterinario
                        </div>

                    </div>

                </a>


                <!-- Navegación -->

                <div class="seccion-nav">
                    Principal
                </div>


                <a
                    href="inicio_veterinario.php"
                    class="item-menu">

                    <i class="bi bi-grid"></i>

                    Inicio

                </a>


                <a
                    href="turnos_veterinario.php"
                    class="item-menu activo">

                    <i class="bi bi-calendar-check"></i>

                    Turnos

                </a>


                <a
                    href="#"
                    class="item-menu">

                    <i class="bi bi-heart-pulse"></i>

                    Mascotas

                </a>


                <a
                    href="#"
                    class="item-menu">

                    <i class="bi bi-journal-medical"></i>

                    Historial clínico

                </a>


                <div class="seccion-nav">
                    Gestión
                </div>


                <a
                    href="#"
                    class="item-menu">

                    <i class="bi bi-people"></i>

                    Propietarios

                </a>


                <a
                    href="#"
                    class="item-menu">

                    <i class="bi bi-shield-plus"></i>

                    Vacunaciones

                </a>


                <a
                    href="#"
                    class="item-menu">

                    <i class="bi bi-chat-dots"></i>

                    Mensajes

                    <span
                        class="badge ms-auto"
                        style="background:var(--azul-claro);color:var(--azul-oscuro);font-size:10px;">
                        3
                    </span>

                </a>


                <!-- Configuración -->

                <div class="seccion-nav">
                    Configuración
                </div>


                <a
                    href="horarios_atencion.php"
                    class="item-menu">

                    <i class="bi bi-clock"></i>

                    Horarios de atención

                </a>


                <!-- Usuario -->

                <div class="usuario-lateral">

                    <div class="avatar-usuario">

                        <?= strtoupper(
                            substr($nombre, 0, 1) .
                                substr($apellido, 0, 1)
                        ) ?>

                    </div>


                    <div>

                        <div class="nombre-usuario">

                            <?= htmlspecialchars(
                                $nombre .
                                    ' ' .
                                    $apellido
                            ) ?>

                        </div>


                        <div class="rol-usuario">

                            Veterinario

                        </div>

                    </div>

                </div>

            </div>


            <!-- ================================================== -->
            <!-- ÁREA PRINCIPAL -->
            <!-- ================================================== -->

            <div class="col-12 col-lg-10 area-principal">


                <!-- Barra superior -->

                <div class="barra-superior">

                    <div>

                        <p class="titulo-pagina">
                            Turnos
                        </p>

                        <p class="fecha-hoy">

                            <?= $fecha_actual ?>

                        </p>

                    </div>


                    <div class="d-flex gap-2">

                        <a
                            href="#"
                            class="icono-barra"
                            title="Notificaciones">

                            <i class="bi bi-bell"></i>

                        </a>


                        <a
                            href="#"
                            class="icono-barra"
                            title="Configuración">

                            <i class="bi bi-gear"></i>

                        </a>


                        <a
                            href="../logout.php"
                            class="icono-barra"
                            title="Cerrar sesión">

                            <i class="bi bi-box-arrow-right"></i>

                        </a>

                    </div>

                </div>


                <!-- ================================================== -->
                <!-- ENCABEZADO -->
                <!-- ================================================== -->

                <div class="mb-4">

                    <h2 class="titulo-pagina">

                        Mis turnos

                    </h2>

                    <p class="fecha-hoy">

                        Consultá los turnos asignados a tu agenda.

                    </p>

                </div>


                <!-- ================================================== -->
                <!-- FILTROS -->
                <!-- ================================================== -->

                <div class="tarjeta mb-4">


                    <div class="titulo-tarjeta mb-3">

                        <i class="bi bi-funnel"></i>

                        Filtrar turnos

                    </div>


                    <form
                        method="GET"
                        action="turnos_veterinario.php">


                        <div class="row g-3">


                            <!-- Fecha -->

                            <div class="col-12 col-md-6 col-xl-3">

                                <label
                                    for="fecha"
                                    class="form-label">

                                    Fecha

                                </label>


                                <input
                                    type="date"
                                    id="fecha"
                                    name="fecha"
                                    class="form-control"
                                    value="<?= htmlspecialchars($filtro_fecha) ?>">

                            </div>


                            <!-- Mascota -->

                            <div class="col-12 col-md-6 col-xl-3">

                                <label
                                    for="mascota"
                                    class="form-label">

                                    Mascota

                                </label>


                                <input
                                    type="text"
                                    id="mascota"
                                    name="mascota"
                                    class="form-control"
                                    placeholder="Ej. Luna"
                                    value="<?= htmlspecialchars($filtro_mascota) ?>">

                            </div>


                            <!-- Propietario -->

                            <div class="col-12 col-md-6 col-xl-3">

                                <label
                                    for="propietario"
                                    class="form-label">

                                    Propietario

                                </label>


                                <input
                                    type="text"
                                    id="propietario"
                                    name="propietario"
                                    class="form-control"
                                    placeholder="Nombre o apellido"
                                    value="<?= htmlspecialchars($filtro_propietario) ?>">

                            </div>


                            <!-- Estado -->

                            <div class="col-12 col-md-6 col-xl-3">

                                <label
                                    for="estado"
                                    class="form-label">

                                    Estado

                                </label>


                                <select
                                    id="estado"
                                    name="estado"
                                    class="form-select">

                                    <option value="">
                                        Todos
                                    </option>


                                    <option
                                        value="confirmado"
                                        <?= $filtro_estado === 'confirmado'
                                            ? 'selected'
                                            : ''
                                        ?>>
                                        Confirmados
                                    </option>


                                    <option
                                        value="realizado"
                                        <?= $filtro_estado === 'realizado'
                                            ? 'selected'
                                            : ''
                                        ?>>
                                        Realizados
                                    </option>


                                    <option
                                        value="cancelado"
                                        <?= $filtro_estado === 'cancelado'
                                            ? 'selected'
                                            : ''
                                        ?>>
                                        Cancelados
                                    </option>

                                </select>

                            </div>

                        </div>


                        <!-- Botones -->

                        <div class="d-flex gap-2 mt-3">


                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="bi bi-search"></i>

                                Buscar

                            </button>


                            <a
                                href="turnos_veterinario.php"
                                class="btn btn-light">

                                <i class="bi bi-x-circle"></i>

                                Limpiar filtros

                            </a>

                        </div>


                    </form>

                </div>


                <!-- ================================================== -->
                <!-- TURNOS -->
                <!-- ================================================== -->

                <?php if ($filtro_fecha !== ''): ?>

                    <!-- ================================================== -->
                    <!-- FECHA SELECCIONADA -->
                    <!-- ================================================== -->

                    <div class="tarjeta mb-4">

                        <div class="d-flex align-items-center justify-content-between mb-3">

                            <div class="titulo-tarjeta mb-0">

                                <i class="bi bi-calendar2-week"></i>

                                Turnos de la fecha seleccionada

                            </div>

                            <span class="badge-estado estado-confirmado">

                                <?= count($turnos_fecha_seleccionada) ?>

                                <?= count($turnos_fecha_seleccionada) == 1
                                    ? 'turno'
                                    : 'turnos'
                                ?>

                            </span>

                        </div>


                        <?php if (count($turnos_fecha_seleccionada) > 0): ?>

                            <div class="table-responsive">

                                <table class="table align-middle mb-0">

                                    <thead>

                                        <tr>

                                            <th>
                                                Hora
                                            </th>

                                            <th>
                                                Mascota
                                            </th>

                                            <th>
                                                Propietario
                                            </th>

                                            <th>
                                                Motivo
                                            </th>

                                            <th>
                                                Estado
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php foreach ($turnos_fecha_seleccionada as $turno): ?>

                                            <tr>

                                                <!-- Hora -->

                                                <td>

                                                    <strong>

                                                        <?= date(
                                                            'H:i',
                                                            strtotime(
                                                                $turno['fecha_hora']
                                                            )
                                                        ) ?>

                                                    </strong>

                                                </td>


                                                <!-- Mascota -->

                                                <td>

                                                    <strong>

                                                        <?= htmlspecialchars(
                                                            $turno['nombre_mascota']
                                                        ) ?>

                                                    </strong>


                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-primary ms-2"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalMascota<?= $turno['id_mascota'] ?>">

                                                        <i class="bi bi-eye"></i>

                                                        Ver

                                                    </button>

                                                </td>


                                                <!-- Propietario -->

                                                <td>

                                                    <?= htmlspecialchars(
                                                        $turno['nombre_propietario']
                                                            . ' '
                                                            . $turno['apellido_propietario']
                                                    ) ?>

                                                </td>


                                                <!-- Motivo -->

                                                <td>

                                                    <?= htmlspecialchars(
                                                        $turno['motivo']
                                                    ) ?>

                                                </td>


                                                <!-- Estado -->

                                                <td>

                                                    <?= mostrarEstado(
                                                        $turno['estado']
                                                    ) ?>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>


                        <?php else: ?>

                            <div class="text-center py-4">

                                <i
                                    class="bi bi-calendar-x"
                                    style="font-size:32px;color:var(--azul-medio);">
                                </i>


                                <p class="mt-3 mb-0">

                                    No hay turnos para la fecha seleccionada
                                    con los filtros aplicados.

                                </p>

                            </div>

                        <?php endif; ?>

                    </div>


                <?php else: ?>


                    <!-- ================================================== -->
                    <!-- SIN FILTRO DE FECHA: TURNOS DE HOY -->
                    <!-- ================================================== -->

                    <div class="tarjeta mb-4">

                        <div class="d-flex align-items-center justify-content-between mb-3">

                            <div class="titulo-tarjeta mb-0">

                                <i class="bi bi-calendar2-week"></i>

                                Turnos de hoy

                            </div>

                            <span class="badge-estado estado-confirmado">

                                <?= count($turnos_hoy) ?>

                                <?= count($turnos_hoy) == 1
                                    ? 'turno'
                                    : 'turnos'
                                ?>

                            </span>

                        </div>


                        <?php if (count($turnos_hoy) > 0): ?>

                            <ul class="lista-turnos">

                                <?php foreach ($turnos_hoy as $turno): ?>

                                    <li class="item-turno">

                                        <span class="hora-turno">

                                            <?= date(
                                                'H:i',
                                                strtotime(
                                                    $turno['fecha_hora']
                                                )
                                            ) ?>

                                        </span>


                                        <div class="separador-turno"></div>


                                        <div class="flex-grow-1">

                                            <div class="nombre-paciente">

                                                <?= htmlspecialchars(
                                                    $turno['nombre_mascota']
                                                ) ?>


                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary ms-2"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalMascota<?= $turno['id_mascota'] ?>">

                                                    <i class="bi bi-eye"></i>

                                                    Ver mascota

                                                </button>

                                            </div>


                                            <div class="tipo-consulta">

                                                <?= htmlspecialchars(
                                                    $turno['motivo']
                                                ) ?>

                                                ·

                                                <?= htmlspecialchars(
                                                    $turno['nombre_propietario']
                                                        . ' '
                                                        . $turno['apellido_propietario']
                                                ) ?>

                                            </div>

                                        </div>


                                        <?= mostrarEstado(
                                            $turno['estado']
                                        ) ?>

                                    </li>

                                <?php endforeach; ?>

                            </ul>


                        <?php else: ?>

                            <div class="text-center py-4">

                                <i
                                    class="bi bi-calendar-x"
                                    style="font-size:32px;color:var(--azul-medio);">
                                </i>


                                <p class="mt-3 mb-0">

                                    No hay turnos para hoy.

                                </p>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- ================================================== -->
                    <!-- SIN FILTRO DE FECHA: PRÓXIMOS TURNOS -->
                    <!-- ================================================== -->

                    <div class="tarjeta mb-4">

                        <div class="titulo-tarjeta mb-3">

                            <i class="bi bi-calendar-plus"></i>

                            Próximos turnos

                        </div>


                        <?php if (count($turnos_proximos) > 0): ?>

                            <div class="table-responsive">

                                <table class="table align-middle mb-0">

                                    <thead>

                                        <tr>

                                            <th>
                                                Fecha
                                            </th>

                                            <th>
                                                Hora
                                            </th>

                                            <th>
                                                Mascota
                                            </th>

                                            <th>
                                                Propietario
                                            </th>

                                            <th>
                                                Motivo
                                            </th>

                                            <th>
                                                Estado
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php foreach ($turnos_proximos as $turno): ?>

                                            <tr>

                                                <td>

                                                    <?= date(
                                                        'd/m/Y',
                                                        strtotime(
                                                            $turno['fecha_hora']
                                                        )
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= date(
                                                        'H:i',
                                                        strtotime(
                                                            $turno['fecha_hora']
                                                        )
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <strong>

                                                        <?= htmlspecialchars(
                                                            $turno['nombre_mascota']
                                                        ) ?>

                                                    </strong>


                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-primary ms-2"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalMascota<?= $turno['id_mascota'] ?>">

                                                        <i class="bi bi-eye"></i>

                                                        Ver

                                                    </button>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $turno['nombre_propietario']
                                                            . ' '
                                                            . $turno['apellido_propietario']
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $turno['motivo']
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= mostrarEstado(
                                                        $turno['estado']
                                                    ) ?>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>


                        <?php else: ?>

                            <div class="text-center py-4">

                                <i
                                    class="bi bi-calendar-check"
                                    style="font-size:32px;color:var(--azul-medio);">
                                </i>


                                <p class="mt-3 mb-0">

                                    No hay próximos turnos.

                                </p>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>
                <!-- ========================================================== -->
                <!-- MODALES DE LAS MASCOTAS -->
                <!-- ========================================================== -->

                <?php

                $mascotas_mostradas = [];

                foreach ($turnos as $turno):


                    // Evitar repetir el mismo modal
                    if (
                        in_array(
                            $turno['id_mascota'],
                            $mascotas_mostradas
                        )
                    ) {
                        continue;
                    }


                    $mascotas_mostradas[] =
                        $turno['id_mascota'];

                ?>


                    <div
                        class="modal fade"
                        id="modalMascota<?= $turno['id_mascota'] ?>"
                        tabindex="-1"
                        aria-labelledby="tituloMascota<?= $turno['id_mascota'] ?>"
                        aria-hidden="true">


                        <div class="modal-dialog modal-dialog-centered">


                            <div class="modal-content">


                                <!-- Encabezado -->

                                <div class="modal-header">


                                    <h5
                                        class="modal-title"
                                        id="tituloMascota<?= $turno['id_mascota'] ?>">

                                        <i class="bi bi-heart-pulse"></i>

                                        Información de
                                        <?= htmlspecialchars(
                                            $turno['nombre_mascota']
                                        ) ?>

                                    </h5>


                                    <button
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Cerrar"></button>

                                </div>


                                <!-- Contenido -->

                                <div class="modal-body">


                                    <div class="row g-3">


                                        <!-- Nombre -->

                                        <div class="col-12">

                                            <div class="fw-semibold">
                                                Nombre
                                            </div>

                                            <div>

                                                <?= htmlspecialchars(
                                                    $turno['nombre_mascota']
                                                ) ?>

                                            </div>

                                        </div>


                                        <!-- Especie -->

                                        <div class="col-6">

                                            <div class="fw-semibold">
                                                Especie
                                            </div>

                                            <div>

                                                <?= !empty($turno['nombre_especie'])
                                                    ? htmlspecialchars(
                                                        $turno['nombre_especie']
                                                    )
                                                    : 'No registrada'
                                                ?>

                                            </div>

                                        </div>


                                        <!-- Raza -->

                                        <div class="col-6">

                                            <div class="fw-semibold">
                                                Raza
                                            </div>

                                            <div>

                                                <?= !empty($turno['nombre_raza'])
                                                    ? htmlspecialchars(
                                                        $turno['nombre_raza']
                                                    )
                                                    : 'No registrada'
                                                ?>

                                            </div>

                                        </div>


                                        <!-- Edad -->

                                        <div class="col-6">

                                            <div class="fw-semibold">
                                                Edad
                                            </div>

                                            <div>

                                                <?= calcularEdad(
                                                    $turno['fecha_nacimiento']
                                                ) ?>

                                            </div>

                                        </div>


                                        <!-- Fecha de nacimiento -->

                                        <div class="col-6">

                                            <div class="fw-semibold">
                                                Fecha de nacimiento
                                            </div>

                                            <div>


                                                <?php if (
                                                    !empty($turno['fecha_nacimiento'])
                                                ): ?>


                                                    <?= date(
                                                        'd/m/Y',
                                                        strtotime(
                                                            $turno['fecha_nacimiento']
                                                        )
                                                    ) ?>


                                                <?php else: ?>

                                                    No registrada

                                                <?php endif; ?>


                                            </div>

                                        </div>


                                        <!-- Sexo -->

                                        <div class="col-6">

                                            <div class="fw-semibold">
                                                Sexo
                                            </div>

                                            <div>

                                                <?= !empty($turno['sexo'])
                                                    ? htmlspecialchars(
                                                        $turno['sexo']
                                                    )
                                                    : 'No registrado'
                                                ?>

                                            </div>

                                        </div>


                                        <!-- Peso -->

                                        <div class="col-6">

                                            <div class="fw-semibold">
                                                Peso
                                            </div>

                                            <div>


                                                <?php if (
                                                    $turno['peso_kg'] !== null &&
                                                    $turno['peso_kg'] !== ''
                                                ): ?>


                                                    <?= htmlspecialchars(
                                                        $turno['peso_kg']
                                                    ) ?>

                                                    kg


                                                <?php else: ?>

                                                    No registrado

                                                <?php endif; ?>


                                            </div>

                                        </div>


                                        <!-- Color -->

                                        <div class="col-6">

                                            <div class="fw-semibold">
                                                Color
                                            </div>

                                            <div>

                                                <?= !empty($turno['color'])
                                                    ? htmlspecialchars(
                                                        $turno['color']
                                                    )
                                                    : 'No registrado'
                                                ?>

                                            </div>

                                        </div>


                                        <!-- Castrado -->

                                        <div class="col-6">

                                            <div class="fw-semibold">
                                                Castrado/a
                                            </div>

                                            <div>


                                                <?php

                                                if (
                                                    $turno['castrado'] === null ||
                                                    $turno['castrado'] === ''
                                                ) {

                                                    echo 'No registrado';
                                                } elseif (
                                                    $turno['castrado'] == 1
                                                ) {

                                                    echo 'Sí';
                                                } else {

                                                    echo 'No';
                                                }

                                                ?>

                                            </div>

                                        </div>


                                        <!-- Propietario -->

                                        <div class="col-12">

                                            <hr>


                                            <div class="fw-semibold">
                                                Propietario
                                            </div>


                                            <div>

                                                <?= htmlspecialchars(
                                                    $turno['nombre_propietario']
                                                        . ' '
                                                        . $turno['apellido_propietario']
                                                ) ?>

                                            </div>

                                        </div>


                                    </div>

                                </div>


                                <!-- Pie -->

                                <div class="modal-footer">


                                    <button
                                        type="button"
                                        class="btn btn-secondary"
                                        data-bs-dismiss="modal">

                                        Cerrar

                                    </button>


                                </div>


                            </div>

                        </div>

                    </div>


                <?php endforeach; ?>


                <!-- ========================================================== -->
                <!-- BOOTSTRAP JS -->
                <!-- ========================================================== -->

                <script
                    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>