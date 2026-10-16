<?php

session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');


// ============================================================
// VERIFICAR SESIÓN
// ============================================================

if (
    empty($_SESSION['logueado']) ||
    $_SESSION['tipo_usuario'] !== 'propietario'
) {
    header('Location: ../index.php');
    exit;
}


// ============================================================
// DATOS DEL PROPIETARIO
// ============================================================

$nombre = $_SESSION['usuario_nombre'] ?? 'Propietario/a';
$apellido = $_SESSION['usuario_apellido'] ?? '';
$id_propietario = (int) ($_SESSION['usuario_id'] ?? 0);


// ============================================================
// FECHA ACTUAL
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


// ============================================================
// CONEXIÓN
// ============================================================

include("../config/conexion.php");

// ============================================================
// NOTIFICACIONES
// ============================================================

require_once __DIR__ . '/includes/notificaciones_propietario.php';


// ============================================================
// FUNCIONES
// ============================================================

function calcularEdad(?string $fecha_nacimiento): string
{
    if (empty($fecha_nacimiento)) {
        return 'Edad no registrada';
    }

    try {

        $nacimiento = new DateTime($fecha_nacimiento);
        $hoy = new DateTime();

        $diferencia = $hoy->diff($nacimiento);

        if ($diferencia->y > 0) {

            return $diferencia->y .
                ($diferencia->y === 1 ? ' año' : ' años');
        }

        if ($diferencia->m > 0) {

            return $diferencia->m .
                ($diferencia->m === 1 ? ' mes' : ' meses');
        }

        return 'Menos de 1 mes';
    } catch (Exception $e) {

        return 'Edad no registrada';
    }
}


function formatearFecha(?string $fecha): string
{
    if (empty($fecha)) {
        return 'Fecha no registrada';
    }

    try {

        $fecha_objeto = new DateTime($fecha);

        return $fecha_objeto->format('d/m/Y');
    } catch (Exception $e) {

        return 'Fecha no registrada';
    }
}


function iconoEspecie(?string $especie): string
{
    $especie = strtolower(trim($especie ?? ''));

    if (str_contains($especie, 'perro') || str_contains($especie, 'canino')) {
        return '🐶';
    }

    if (str_contains($especie, 'gato') || str_contains($especie, 'felino')) {
        return '🐱';
    }

    return '🐾';
}


function nombreVeterinario(array $historia): string
{
    if (empty($historia['nombre_veterinario'])) {
        return 'Veterinario no registrado';
    }

    $sexo = strtoupper(trim($historia['sexo_veterinario'] ?? ''));

    $tratamiento = $sexo === 'F'
        ? 'Dra.'
        : 'Dr.';

    return trim(
        $tratamiento . ' ' .
            $historia['nombre_veterinario'] . ' ' .
            ($historia['apellido_veterinario'] ?? '')
    );
}


// ============================================================
// BUSCAR TODAS LAS MASCOTAS DEL PROPIETARIO
// ============================================================

$sql_mascotas = "
    SELECT
        m.id_mascota,
        m.nombre,
        m.fecha_nacimiento,
        m.sexo,
        m.peso_kg,
        m.color,
        m.castrado,

        e.nombre_especie,

        r.nombre_raza

    FROM mascotas m

    INNER JOIN especies e
        ON m.id_especie = e.id_especie

    LEFT JOIN razas r
        ON m.id_raza = r.id_raza

    WHERE m.id_propietario = ?

    ORDER BY m.nombre ASC
";

$stmt = $conn->prepare($sql_mascotas);
$stmt->bind_param("i", $id_propietario);
$stmt->execute();

$resultado_mascotas = $stmt->get_result();

$mascotas = [];

while ($fila = $resultado_mascotas->fetch_assoc()) {
    $mascotas[] = $fila;
}

$stmt->close();


// ============================================================
// DETERMINAR QUÉ MASCOTA ESTÁ SELECCIONADA
// ============================================================

// Si viene un ID por URL, intentamos seleccionar esa mascota.
// Pero solamente se acepta si pertenece al propietario logueado.

$id_mascota_solicitada = isset($_GET['id_mascota'])
    ? (int) $_GET['id_mascota']
    : 0;

$mascota_seleccionada = null;


// Buscar la mascota dentro de las mascotas del propietario.

foreach ($mascotas as $mascota) {

    if (
        $id_mascota_solicitada > 0 &&
        (int) $mascota['id_mascota'] === $id_mascota_solicitada
    ) {

        $mascota_seleccionada = $mascota;
        break;
    }
}


// Si no eligió ninguna mascota, seleccionamos la primera.

if (!$mascota_seleccionada && !empty($mascotas)) {

    $mascota_seleccionada = $mascotas[0];
}


// ============================================================
// BUSCAR HISTORIA CLÍNICA DE LA MASCOTA SELECCIONADA
// ============================================================

$historiales = [];

if ($mascota_seleccionada) {

    $id_mascota = (int) $mascota_seleccionada['id_mascota'];

    /*
        Además de filtrar por mascota,
        volvemos a comprobar que esa mascota pertenece
        al propietario que inició sesión.
    */

    $sql_historial = "
        SELECT
            h.id_historia,
            h.id_turno,
            h.fecha_consulta,
            h.motivo_consulta,
            h.diagnostico,
            h.tratamiento,
            h.medicacion,
            h.peso_registro,
            h.proximos_pasos,

            v.nombre AS nombre_veterinario,
            v.apellido AS apellido_veterinario,
            v.sexo AS sexo_veterinario

        FROM historia_clinica h

        INNER JOIN mascotas m
            ON h.id_mascota = m.id_mascota

        LEFT JOIN veterinarios v
            ON h.id_veterinario = v.id_veterinario

        WHERE h.id_mascota = ?
          AND m.id_propietario = ?

        ORDER BY
            h.fecha_consulta DESC,
            h.id_historia DESC
    ";

    $stmt = $conn->prepare($sql_historial);

    $stmt->bind_param(
        "ii",
        $id_mascota,
        $id_propietario
    );

    $stmt->execute();

    $resultado_historial = $stmt->get_result();

    while ($fila = $resultado_historial->fetch_assoc()) {
        $historiales[] = $fila;
    }

    $stmt->close();
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
        Historia clínica — Propietario · VetClinic Pro
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


    <!-- CSS GENERAL -->

    <link
        rel="stylesheet"
        href="../estilos/inicio.css">


    <!-- CSS ESPECÍFICO DE HISTORIA CLÍNICA -->

    <link
        rel="stylesheet"
        href="../estilos/historial_clinico.css">

</head>


<body>

    <div class="container-fluid p-0">

        <div class="row g-0 min-vh-100">


            <!-- =====================================================
             SIDEBAR
        ====================================================== -->

            <div class="col-12 col-lg-2 panel-lateral">


                <!-- Logo -->

                <a
                    href="inicio_propietarios.php"
                    class="logo-lateral">

                    <img
                        src="../img/VetClinic Pro.png"
                        alt="Logo VetClinic Pro">

                    <div>

                        <div class="nombre-sistema">
                            VetClinic Pro
                        </div>

                        <div class="subtitulo-sistema">
                            Portal del propietario
                        </div>

                    </div>

                </a>


                <!-- Navegación -->

                <div class="seccion-nav">
                    Mi espacio
                </div>


                <a
                    href="inicio_propietarios.php"
                    class="item-menu">

                    <i class="bi bi-grid"></i>

                    Inicio

                </a>


                <a
                    href="../mascotas/inicio_mascota.php"
                    class="item-menu">

                    <i class="bi bi-heart-pulse"></i>

                    Mis mascotas

                </a>


                <a
                    href="../turnos/inicio_turnos.php"
                    class="item-menu">

                    <i class="bi bi-calendar-check"></i>

                    Mis turnos

                </a>


                <a
                    href="historial_clinico.php"
                    class="item-menu activo">

                    <i class="bi bi-journal-medical"></i>

                    Historial clínico

                </a>


                <div class="seccion-nav">
                    Más
                </div>


                <a
                    href="vacunaciones.php"
                    class="item-menu">

                    <i class="bi bi-shield-plus"></i>

                    Vacunaciones

                </a>


                <a href="#" class="item-menu">

                    <i class="bi bi-chat-dots"></i>

                    Mensajes



                    <span

                        class="badge ms-auto"

                        style="background:var(--azul-claro);color:var(--azul-oscuro);font-size:10px;">

                        1

                    </span>

                </a>





                <div class="seccion-nav">

                    Configuración

                </div>



                <a

                    href="configuracion_propietario.php"

                    class="item-menu">

                    <i class="bi bi-gear"></i>

                    Mi configuración

                </a>


                <!-- Usuario -->

                <div class="usuario-lateral">

                    <div class="avatar-usuario">

                        <?= strtoupper(substr($nombre, 0, 1)) ?>

                    </div>

                    <div>

                        <div class="nombre-usuario">

                            <?= htmlspecialchars(
                                $nombre . ' ' . $apellido
                            ) ?>

                        </div>

                        <div class="rol-usuario">
                            Propietario
                        </div>

                    </div>

                </div>

            </div>


            <!-- =====================================================
             ÁREA PRINCIPAL
        ====================================================== -->

            <div class="col-12 col-lg-10 area-principal">


                <!-- =================================================
                 BARRA SUPERIOR
            ================================================== -->

                <div class="barra-superior">

                    <div>

                        <p class="titulo-pagina">

                            Historia clínica

                        </p>

                        <p class="fecha-hoy">

                            <?= htmlspecialchars($fecha_actual) ?>
                            · VetClinic Pro

                        </p>

                    </div>


                    <div class="d-flex gap-2">

                        <?php
                        mostrarCampanaNotificaciones(
                            $cantidad_notificaciones,
                            $notificaciones_inicio
                        );
                        ?>

                        <a
                            href="configuracion_propietario.php"
                            class="icono-barra"
                            title="Mi configuración">

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


                <!-- =================================================
                 ENCABEZADO
            ================================================== -->

                <div class="encabezado-historial">

                    <div>

                        <h1>
                            Historia clínica de tus mascotas
                        </h1>

                        <p>
                            Seleccioná una mascota para consultar sus
                            controles, diagnósticos y tratamientos.
                        </p>

                    </div>

                </div>


                <?php if (empty($mascotas)): ?>


                    <!-- =================================================
                     SIN MASCOTAS
                ================================================== -->

                    <div class="tarjeta estado-vacio-historial">

                        <div class="icono-vacio-historial">

                            <i class="bi bi-heart-pulse"></i>

                        </div>

                        <h3>
                            Todavía no tenés mascotas registradas
                        </h3>

                        <p>
                            Cuando registres una mascota vas a poder
                            consultar su historia clínica desde acá.
                        </p>

                        <a
                            href="../mascotas/inicio_mascota.php"
                            class="btn-principal">

                            <i class="bi bi-plus-circle"></i>

                            Ir a Mis mascotas

                        </a>

                    </div>


                <?php else: ?>


                    <!-- =================================================
                     SELECCIÓN DE MASCOTA
                ================================================== -->

                    <div class="tarjeta mb-4">

                        <div class="titulo-selector">

                            <div>

                                <div class="titulo-tarjeta mb-1">

                                    <i class="bi bi-heart-pulse"></i>

                                    Elegí una mascota

                                </div>

                                <p class="texto-ayuda-selector">

                                    El historial que aparece debajo
                                    corresponde solamente a la mascota
                                    seleccionada.

                                </p>

                            </div>


                            <div class="cantidad-mascotas">

                                <?= count($mascotas) ?>

                                <?= count($mascotas) === 1
                                    ? 'mascota'
                                    : 'mascotas'
                                ?>

                            </div>

                        </div>


                        <!--
                        Las mascotas permanecen en una sola fila.
                        Si hay muchas, se desplazan horizontalmente.
                    -->

                        <div class="lista-selector-mascotas">


                            <?php foreach ($mascotas as $mascota): ?>


                                <?php

                                $activa =
                                    (int) $mascota['id_mascota'] ===
                                    (int) $mascota_seleccionada['id_mascota'];

                                $edad = calcularEdad(
                                    $mascota['fecha_nacimiento']
                                );

                                $raza =
                                    $mascota['nombre_raza']
                                    ?: 'Sin raza registrada';

                                $icono = iconoEspecie(
                                    $mascota['nombre_especie']
                                );

                                ?>


                                <a
                                    href="historial_clinico.php?id_mascota=<?= (int) $mascota['id_mascota'] ?>"
                                    class="tarjeta-selector-mascota <?= $activa ? 'activa' : '' ?>">

                                    <div class="icono-selector-mascota">

                                        <?= $icono ?>

                                    </div>


                                    <div class="datos-selector-mascota">

                                        <div class="nombre-selector-mascota">

                                            <?= htmlspecialchars(
                                                $mascota['nombre']
                                            ) ?>

                                        </div>

                                        <div class="detalle-selector-mascota">

                                            <?= htmlspecialchars(
                                                $mascota['nombre_especie']
                                            ) ?>

                                            ·

                                            <?= htmlspecialchars($edad) ?>

                                        </div>

                                        <div class="raza-selector-mascota">

                                            <?= htmlspecialchars($raza) ?>

                                        </div>

                                    </div>


                                    <?php if ($activa): ?>

                                        <div class="marca-seleccionada">

                                            <i class="bi bi-check-circle-fill"></i>

                                        </div>

                                    <?php endif; ?>


                                </a>


                            <?php endforeach; ?>


                        </div>

                    </div>


                    <!-- =================================================
                     MASCOTA SELECCIONADA
                ================================================== -->

                    <div class="tarjeta mascota-historial-seleccionada mb-4">


                        <div class="cabecera-mascota-historial">


                            <div class="mascota-historial-info">


                                <div class="avatar-historial-mascota">

                                    <?= iconoEspecie(
                                        $mascota_seleccionada['nombre_especie']
                                    ) ?>

                                </div>


                                <div>

                                    <div class="etiqueta-historial">
                                        Historia clínica de
                                    </div>

                                    <h2>

                                        <?= htmlspecialchars(
                                            $mascota_seleccionada['nombre']
                                        ) ?>

                                    </h2>

                                    <div class="detalle-mascota-historial">

                                        <?= htmlspecialchars(
                                            $mascota_seleccionada['nombre_especie']
                                        ) ?>

                                        ·

                                        <?= htmlspecialchars(
                                            $mascota_seleccionada['nombre_raza']
                                                ?: 'Sin raza registrada'
                                        ) ?>

                                        ·

                                        <?= htmlspecialchars(
                                            calcularEdad(
                                                $mascota_seleccionada['fecha_nacimiento']
                                            )
                                        ) ?>

                                    </div>

                                </div>

                            </div>


                            <div class="contador-consultas">

                                <div class="numero-consultas">

                                    <?= count($historiales) ?>

                                </div>

                                <div class="texto-contador">

                                    <?= count($historiales) === 1
                                        ? 'consulta registrada'
                                        : 'consultas registradas'
                                    ?>

                                </div>

                            </div>


                        </div>


                    </div>


                    <!-- =================================================
                     HISTORIA CLÍNICA
                ================================================== -->


                    <?php if (empty($historiales)): ?>


                        <div class="tarjeta estado-vacio-historial">

                            <div class="icono-vacio-historial">

                                <i class="bi bi-journal-medical"></i>

                            </div>

                            <h3>
                                Todavía no hay consultas registradas
                            </h3>

                            <p>

                                <?= htmlspecialchars(
                                    $mascota_seleccionada['nombre']
                                ) ?>

                                todavía no tiene registros en su
                                historia clínica.

                            </p>

                        </div>


                    <?php else: ?>


                        <div class="contenedor-consultas">


                            <?php foreach ($historiales as $indice => $historia): ?>


                                <?php

                                $veterinario = nombreVeterinario(
                                    $historia
                                );

                                ?>


                                <details
                                    class="consulta-clinica"
                                    <?= $indice === 0 ? 'open' : '' ?>>


                                    <!-- RESUMEN DE LA CONSULTA -->

                                    <summary class="resumen-consulta">


                                        <div class="lado-izquierdo-consulta">


                                            <div class="icono-consulta">

                                                <i class="bi bi-stethoscope"></i>

                                            </div>


                                            <div>

                                                <div class="motivo-consulta">

                                                    <?= htmlspecialchars(
                                                        $historia['motivo_consulta']
                                                            ?: 'Consulta veterinaria'
                                                    ) ?>

                                                </div>


                                                <div class="meta-consulta">

                                                    <span>

                                                        <i class="bi bi-calendar3"></i>

                                                        <?= htmlspecialchars(
                                                            formatearFecha(
                                                                $historia['fecha_consulta']
                                                            )
                                                        ) ?>

                                                    </span>


                                                    <span>

                                                        <i class="bi bi-person-badge"></i>

                                                        <?= htmlspecialchars(
                                                            $veterinario
                                                        ) ?>

                                                    </span>

                                                </div>

                                            </div>


                                        </div>


                                        <div class="lado-derecho-consulta">

                                            <?php if ($indice === 0): ?>

                                                <span class="badge-reciente">
                                                    Más reciente
                                                </span>

                                            <?php endif; ?>


                                            <i class="bi bi-chevron-down flecha-consulta"></i>

                                        </div>


                                    </summary>


                                    <!-- DETALLES -->

                                    <div class="contenido-consulta">


                                        <div class="grid-datos-clinicos">


                                            <!-- Diagnóstico -->

                                            <div class="dato-clinico">

                                                <div class="titulo-dato-clinico">

                                                    <i class="bi bi-clipboard2-pulse"></i>

                                                    Diagnóstico

                                                </div>

                                                <div class="texto-dato-clinico">

                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $historia['diagnostico']
                                                                ?: 'No registrado'
                                                        )
                                                    ) ?>

                                                </div>

                                            </div>


                                            <!-- Tratamiento -->

                                            <div class="dato-clinico">

                                                <div class="titulo-dato-clinico">

                                                    <i class="bi bi-bandaid"></i>

                                                    Tratamiento

                                                </div>

                                                <div class="texto-dato-clinico">

                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $historia['tratamiento']
                                                                ?: 'No registrado'
                                                        )
                                                    ) ?>

                                                </div>

                                            </div>


                                            <!-- Medicación -->

                                            <div class="dato-clinico">

                                                <div class="titulo-dato-clinico">

                                                    <i class="bi bi-capsule"></i>

                                                    Medicación

                                                </div>

                                                <div class="texto-dato-clinico">

                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $historia['medicacion']
                                                                ?: 'No registrada'
                                                        )
                                                    ) ?>

                                                </div>

                                            </div>


                                            <!-- Peso -->

                                            <div class="dato-clinico">

                                                <div class="titulo-dato-clinico">

                                                    <i class="bi bi-speedometer2"></i>

                                                    Peso registrado

                                                </div>

                                                <div class="texto-dato-clinico">

                                                    <?php if (
                                                        $historia['peso_registro'] !== null &&
                                                        $historia['peso_registro'] !== ''
                                                    ): ?>

                                                        <?= htmlspecialchars(
                                                            $historia['peso_registro']
                                                        ) ?>
                                                        kg

                                                    <?php else: ?>

                                                        No registrado

                                                    <?php endif; ?>

                                                </div>

                                            </div>


                                            <!-- Próximos pasos -->

                                            <div class="dato-clinico dato-clinico-ancho">

                                                <div class="titulo-dato-clinico">

                                                    <i class="bi bi-arrow-right-circle"></i>

                                                    Próximos pasos

                                                </div>

                                                <div class="texto-dato-clinico">

                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $historia['proximos_pasos']
                                                                ?: 'No se indicaron próximos pasos.'
                                                        )
                                                    ) ?>

                                                </div>

                                            </div>


                                        </div>


                                        <div class="pie-consulta">

                                            <i class="bi bi-person-check"></i>

                                            Atención realizada por

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $veterinario
                                                ) ?>

                                            </strong>

                                        </div>


                                    </div>


                                </details>


                            <?php endforeach; ?>


                        </div>


                    <?php endif; ?>


                <?php endif; ?>


            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>