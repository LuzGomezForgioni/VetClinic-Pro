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
require_once '../config/proteger_acceso_veterinario.php';

// ======================================================
// DATOS DEL VETERINARIO
// ======================================================

$nombre = $_SESSION['usuario_nombre'] ?? 'Doctor/a';
$apellido = $_SESSION['usuario_apellido'] ?? '';

$sexo = strtoupper(
    trim($_SESSION['usuario_sexo'] ?? '')
);

$titulo = ($sexo === 'F') ? 'Dra.' : 'Dr.';

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
    $dias[date('w')] . ' ' .
    date('j') . ' de ' .
    $meses[date('n') - 1] . ' de ' .
    date('Y');

// ======================================================
// BUSCADOR
// ======================================================

$busqueda = trim($_GET['buscar'] ?? '');

$sql = "
    SELECT
        p.id_propietario,
        p.nombre,
        p.apellido,
        p.email,
        p.telefono,
        p.fecha_registro,

        (
            SELECT COUNT(*)
            FROM mascotas m
            WHERE m.id_propietario = p.id_propietario
        ) AS cantidad_mascotas

    FROM propietarios p
";

if ($busqueda !== '') {

    $sql .= "
        WHERE
            p.nombre LIKE ?
            OR p.apellido LIKE ?
            OR CONCAT(p.nombre, ' ', p.apellido) LIKE ?
            OR p.email LIKE ?
            OR p.telefono LIKE ?
            OR EXISTS (
                SELECT 1
                FROM mascotas mb
                WHERE mb.id_propietario = p.id_propietario
                  AND mb.nombre LIKE ?
            )
    ";
}

$sql .= " ORDER BY p.apellido ASC, p.nombre ASC";

$stmt = $conn->prepare($sql);

if ($busqueda !== '') {

    $texto_busqueda = '%' . $busqueda . '%';

    $stmt->bind_param(
        "ssssss",
        $texto_busqueda,
        $texto_busqueda,
        $texto_busqueda,
        $texto_busqueda,
        $texto_busqueda,
        $texto_busqueda
    );
}

$stmt->execute();

$resultado = $stmt->get_result();

$propietarios = [];

while ($fila = $resultado->fetch_assoc()) {
    $propietarios[] = $fila;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Propietarios — Veterinario · VetClinic Pro</title>

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

    <!-- CSS general -->
    <link
        rel="stylesheet"
        href="../estilos/inicio.css">

    <!-- CSS específico -->
    <link
        rel="stylesheet"
        href="../estilos/propietarios_veterinario.css">

</head>

<body>

<div class="container-fluid p-0">

    <div class="row g-0 min-vh-100">

        <!-- ================================================== -->
        <!-- BARRA LATERAL -->
        <!-- ================================================== -->

        <div class="col-12 col-lg-2 panel-lateral">

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
                class="item-menu">

                <i class="bi bi-calendar-check"></i>
                Turnos

            </a>

            <a
                href="mascotas_veterinario.php"
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
                href="propietarios_veterinario.php"
                class="item-menu activo">

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

            </a>

            <div class="seccion-nav">
                Configuración
            </div>

            <a
                href="horarios_atencion.php"
                class="item-menu">

                <i class="bi bi-clock"></i>
                Horarios de atención

            </a>

            <a href="../suscripciones/mi_suscripcion.php" class="item-menu">
                <i class="bi bi-credit-card-2-front"></i> Mi suscripción
            </a>

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
                            $nombre . ' ' . $apellido
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

            <div class="barra-superior">

                <div>

                    <p class="titulo-pagina">
                        Propietarios
                    </p>

                    <p class="fecha-hoy">
                        <?= htmlspecialchars($fecha_actual) ?>
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

            <div class="encabezado-propietarios">

                <div>

                    <h2 class="titulo-pagina">
                        Propietarios registrados
                    </h2>

                    <p class="fecha-hoy">
                        Consultá los propietarios y las mascotas asociadas a cada uno.
                    </p>

                </div>

                <span class="contador-propietarios">

                    <i class="bi bi-people"></i>

                    <?= count($propietarios) ?>

                    <?= count($propietarios) === 1
                        ? 'propietario'
                        : 'propietarios'
                    ?>

                </span>

            </div>

            <!-- ================================================== -->
            <!-- BUSCADOR -->
            <!-- ================================================== -->

            <div class="tarjeta tarjeta-buscador-propietarios mb-4">

                <form
                    method="GET"
                    action="propietarios_veterinario.php">

                    <label
                        for="buscar"
                        class="etiqueta-buscador-propietarios">

                        <i class="bi bi-search"></i>
                        Buscar propietario

                    </label>

                    <div class="fila-buscador-propietarios">

                        <input
                            type="text"
                            id="buscar"
                            name="buscar"
                            class="form-control"
                            placeholder="Nombre, apellido, email, teléfono o mascota"
                            value="<?= htmlspecialchars($busqueda) ?>">

                        <button
                            type="submit"
                            class="btn-buscar-propietario">

                            <i class="bi bi-search"></i>
                            Buscar

                        </button>

                        <?php if ($busqueda !== ''): ?>

                            <a
                                href="propietarios_veterinario.php"
                                class="btn-limpiar-propietario">

                                <i class="bi bi-x-circle"></i>
                                Limpiar

                            </a>

                        <?php endif; ?>

                    </div>

                </form>

            </div>

            <!-- ================================================== -->
            <!-- LISTADO -->
            <!-- ================================================== -->

            <?php if (!empty($propietarios)): ?>

                <div class="grilla-propietarios">

                    <?php foreach ($propietarios as $propietario): ?>

                        <article class="tarjeta-propietario-veterinario">

                            <div class="cabecera-propietario-card">

                                <div class="avatar-propietario-card">

                                    <?= strtoupper(
                                        substr(
                                            $propietario['nombre'],
                                            0,
                                            1
                                        ) .
                                        substr(
                                            $propietario['apellido'],
                                            0,
                                            1
                                        )
                                    ) ?>

                                </div>

                                <div class="flex-grow-1">

                                    <div class="nombre-propietario-card">

                                        <?= htmlspecialchars(
                                            $propietario['nombre'] .
                                            ' ' .
                                            $propietario['apellido']
                                        ) ?>

                                    </div>

                                    <div class="subtitulo-propietario-card">

                                        <?= (int) $propietario['cantidad_mascotas'] ?>

                                        <?= (int) $propietario['cantidad_mascotas'] === 1
                                            ? 'mascota registrada'
                                            : 'mascotas registradas'
                                        ?>

                                    </div>

                                </div>

                            </div>

                            <div class="datos-contacto-card">

                                <?php if (!empty($propietario['email'])): ?>

                                    <div>

                                        <i class="bi bi-envelope"></i>

                                        <span>

                                            <?= htmlspecialchars(
                                                $propietario['email']
                                            ) ?>

                                        </span>

                                    </div>

                                <?php endif; ?>

                                <?php if (!empty($propietario['telefono'])): ?>

                                    <div>

                                        <i class="bi bi-telephone"></i>

                                        <span>

                                            <?= htmlspecialchars(
                                                $propietario['telefono']
                                            ) ?>

                                        </span>

                                    </div>

                                <?php endif; ?>

                                <div>

                                    <i class="bi bi-calendar3"></i>

                                    <span>

                                        Registrado:

                                        <?= date(
                                            'd/m/Y',
                                            strtotime(
                                                $propietario['fecha_registro']
                                            )
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                            <a
                                href="ficha_propietario.php?id_propietario=<?= (int) $propietario['id_propietario'] ?>"
                                class="btn-ver-propietario">

                                Ver propietario

                                <i class="bi bi-arrow-right"></i>

                            </a>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="tarjeta estado-vacio-propietarios">

                    <div class="icono-vacio-propietarios">

                        <i class="bi bi-people"></i>

                    </div>

                    <h3>
                        No encontramos propietarios
                    </h3>

                    <p>

                        <?php if ($busqueda !== ''): ?>

                            Probá buscando por otro nombre, email, teléfono o mascota.

                        <?php else: ?>

                            Todavía no hay propietarios registrados.

                        <?php endif; ?>

                    </p>

                    <?php if ($busqueda !== ''): ?>

                        <a
                            href="propietarios_veterinario.php"
                            class="btn-limpiar-propietario">

                            Limpiar búsqueda

                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
