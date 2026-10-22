<?php

session_start();
require_once __DIR__ . '/../config/mascotas.php';

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
        return 'No registrada';
    }

    try {

        $fecha_objeto = new DateTime($fecha);

        return $fecha_objeto->format('d/m/Y');
    } catch (Exception $e) {

        return 'No registrada';
    }
}

function iconoEspecie(?string $especie): string
{
    return iconoEspecieMascota($especie);
}

// ============================================================
// CALCULAR ESTADO DE LA VACUNA
// ============================================================

function estadoVacuna(?string $fecha_proxima): array
{
    if (empty($fecha_proxima)) {

        return [
            'texto' => 'Sin próxima dosis',
            'clase' => 'estado-sin-fecha',
            'icono' => 'bi-dash-circle'
        ];
    }

    try {

        $hoy = new DateTime('today');
        $proxima = new DateTime($fecha_proxima);

        /*
            Si la fecha ya pasó, la vacuna está vencida.
        */

        if ($proxima < $hoy) {

            return [
                'texto' => 'Vencida',
                'clase' => 'estado-vencida',
                'icono' => 'bi-exclamation-circle-fill'
            ];
        }

        /*
            Calculamos cuántos días faltan.
        */

        $diferencia = $hoy->diff($proxima);
        $dias_restantes = (int) $diferencia->days;

        /*
            Consideramos "Próxima" cuando faltan
            30 días o menos.
        */

        if ($dias_restantes <= 30) {

            return [
                'texto' => 'Próxima',
                'clase' => 'estado-alerta',
                'icono' => 'bi-clock-fill'
            ];
        }

        /*
            Si falta más de un mes, está al día.
        */

        return [
            'texto' => 'Al día',
            'clase' => 'estado-ok',
            'icono' => 'bi-check-circle-fill'
        ];
    } catch (Exception $e) {

        return [
            'texto' => 'Sin estado',
            'clase' => 'estado-sin-fecha',
            'icono' => 'bi-dash-circle'
        ];
    }
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
// DETERMINAR LA MASCOTA SELECCIONADA
// ============================================================

$id_mascota_solicitada = isset($_GET['id_mascota'])
    ? (int) $_GET['id_mascota']
    : 0;

$mascota_seleccionada = null;

/*
    Buscamos la mascota dentro de las mascotas
    pertenecientes al propietario logueado.

    Esto evita que alguien pueda consultar vacunas
    de una mascota ajena cambiando el ID en la URL.
*/

foreach ($mascotas as $mascota) {

    if (
        $id_mascota_solicitada > 0 &&
        (int) $mascota['id_mascota'] === $id_mascota_solicitada
    ) {

        $mascota_seleccionada = $mascota;
        break;
    }
}

/*
    Si no seleccionó ninguna mascota,
    mostramos automáticamente la primera.
*/

if (!$mascota_seleccionada && !empty($mascotas)) {

    $mascota_seleccionada = $mascotas[0];
}

// ============================================================
// BUSCAR VACUNACIONES DE LA MASCOTA SELECCIONADA
// ============================================================

$vacunaciones = [];

if ($mascota_seleccionada) {

    $id_mascota = (int) $mascota_seleccionada['id_mascota'];

    $sql_vacunaciones = "
        SELECT
            v.id_vacunacion,
            v.nombre_vacuna,
            v.fecha_aplicacion,
            v.fecha_proxima,
            v.observaciones

        FROM vacunaciones v

        INNER JOIN mascotas m
            ON v.id_mascota = m.id_mascota

        WHERE v.id_mascota = ?
          AND m.id_propietario = ?

        ORDER BY
            v.fecha_aplicacion DESC,
            v.id_vacunacion DESC
    ";

    $stmt = $conn->prepare($sql_vacunaciones);

    $stmt->bind_param(
        "ii",
        $id_mascota,
        $id_propietario
    );

    $stmt->execute();

    $resultado_vacunaciones = $stmt->get_result();

    while ($fila = $resultado_vacunaciones->fetch_assoc()) {
        $vacunaciones[] = $fila;
    }

    $stmt->close();
}

// ============================================================
// CONTADORES
// ============================================================

$cantidad_al_dia = 0;
$cantidad_proximas = 0;
$cantidad_vencidas = 0;

foreach ($vacunaciones as $vacuna) {

    $estado = estadoVacuna($vacuna['fecha_proxima']);

    if ($estado['texto'] === 'Al día') {
        $cantidad_al_dia++;
    }

    if ($estado['texto'] === 'Próxima') {
        $cantidad_proximas++;
    }

    if ($estado['texto'] === 'Vencida') {
        $cantidad_vencidas++;
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
        Vacunaciones — Propietario · VetClinic Pro
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
    <!-- CSS ESPECÍFICO -->
    <link
        rel="stylesheet"
        href="../estilos/vacunaciones.css">
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
                    class="item-menu">
                    <i class="bi bi-journal-medical"></i>
                    Historial clínico
                </a>
                <div class="seccion-nav">
                    Más
                </div>
                <a
                    href="vacunaciones.php"
                    class="item-menu activo">
                    <i class="bi bi-shield-plus"></i>
                    Vacunaciones
                </a>
                <a href="mensajes.php" class="item-menu"><i class="bi bi-chat-dots"></i>
                    Mensajes</a>
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
                <!-- BARRA SUPERIOR -->
                <div class="barra-superior">
                    <div>
                        <p class="titulo-pagina">
                            Vacunaciones
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
                <!-- ENCABEZADO -->
                <div class="encabezado-vacunaciones">
                    <h1>
                        Vacunaciones de tus mascotas
                    </h1>
                    <p>
                        Seleccioná una mascota para consultar las vacunas
                        aplicadas y sus próximas dosis.
                    </p>
                </div>
                <?php if (empty($mascotas)): ?>
                    <!-- SIN MASCOTAS -->
                    <div class="tarjeta estado-vacio-vacunacion">
                        <div class="icono-vacio-vacunacion">
                            <i class="bi bi-heart-pulse"></i>
                        </div>
                        <h3>
                            Todavía no tenés mascotas registradas
                        </h3>
                        <p>
                            Cuando registres una mascota vas a poder consultar
                            sus vacunas desde acá.
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
                     SELECTOR DE MASCOTAS
                ================================================== -->
                    <div class="tarjeta mb-4">
                        <div class="titulo-selector-vacunas">
                            <div>
                                <div class="titulo-tarjeta mb-1">
                                    <i class="bi bi-heart-pulse"></i>
                                    Elegí una mascota
                                </div>
                                <p class="texto-ayuda-selector">
                                    Las vacunas que aparecen debajo corresponden
                                    solamente a la mascota seleccionada.
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
                                    href="vacunaciones.php?id_mascota=<?= (int) $mascota['id_mascota'] ?>"
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
                    <div class="tarjeta mascota-vacunacion-seleccionada mb-4">
                        <div class="cabecera-mascota-vacunacion">
                            <div class="mascota-vacunacion-info">
                                <div class="avatar-vacunacion-mascota">
                                    <?= iconoEspecie(
                                        $mascota_seleccionada['nombre_especie']
                                    ) ?>
                                </div>
                                <div>
                                    <div class="etiqueta-vacunacion">
                                        Carnet de vacunación de
                                    </div>
                                    <h2>
                                        <?= htmlspecialchars(
                                            $mascota_seleccionada['nombre']
                                        ) ?>
                                    </h2>
                                    <div class="detalle-mascota-vacunacion">
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
                            <div class="contador-vacunas">
                                <div class="numero-vacunas">
                                    <?= count($vacunaciones) ?>
                                </div>
                                <div class="texto-contador">
                                    <?= count($vacunaciones) === 1
                                        ? 'vacuna registrada'
                                        : 'vacunas registradas'
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($vacunaciones)): ?>
                        <!-- =================================================
                         RESUMEN
                    ================================================== -->
                        <div class="row g-3 mb-4">
                            <div class="col">
                                <div class="tarjeta-resumen h-100">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icono-metrica icono-verde">
                                            <i class="bi bi-shield-check"></i>
                                        </div>
                                        <div>
                                            <div class="numero-metrica">
                                                <?= $cantidad_al_dia ?>
                                            </div>
                                            <div class="etiqueta-metrica">
                                                Al día
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="tarjeta-resumen h-100">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icono-metrica icono-amarillo">
                                            <i class="bi bi-clock"></i>
                                        </div>
                                        <div>
                                            <div class="numero-metrica">
                                                <?= $cantidad_proximas ?>
                                            </div>
                                            <div class="etiqueta-metrica">
                                                Próximas
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="tarjeta-resumen h-100">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icono-metrica icono-rojo-vacunas">
                                            <i class="bi bi-exclamation-triangle"></i>
                                        </div>
                                        <div>
                                            <div class="numero-metrica">
                                                <?= $cantidad_vencidas ?>
                                            </div>
                                            <div class="etiqueta-metrica">
                                                Vencidas
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <!-- =================================================
                     VACUNACIONES
                ================================================== -->
                    <?php if (empty($vacunaciones)): ?>
                        <div class="tarjeta estado-vacio-vacunacion">
                            <div class="icono-vacio-vacunacion">
                                <i class="bi bi-shield-plus"></i>
                            </div>
                            <h3>
                                Todavía no hay vacunas registradas
                            </h3>
                            <p>
                                <?= htmlspecialchars(
                                    $mascota_seleccionada['nombre']
                                ) ?>
                                todavía no tiene vacunas cargadas
                                en el sistema.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="contenedor-vacunaciones">
                            <?php foreach ($vacunaciones as $vacuna): ?>
                                <?php

                                $estado = estadoVacuna(
                                    $vacuna['fecha_proxima']
                                );

                                ?>
                                <div class="tarjeta vacuna-detalle">
                                    <!-- ICONO -->
                                    <div class="icono-vacuna-detalle <?= htmlspecialchars($estado['clase']) ?>">
                                        <i class="bi bi-shield-fill-check"></i>
                                    </div>
                                    <!-- INFORMACIÓN -->
                                    <div class="contenido-vacuna">
                                        <div class="cabecera-vacuna">
                                            <div>
                                                <div class="nombre-vacuna-detalle">
                                                    <?= htmlspecialchars(
                                                        $vacuna['nombre_vacuna']
                                                    ) ?>
                                                </div>
                                                <div class="fecha-aplicacion">
                                                    <i class="bi bi-calendar-check"></i>
                                                    Aplicada:
                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            formatearFecha(
                                                                $vacuna['fecha_aplicacion']
                                                            )
                                                        ) ?>
                                                    </strong>
                                                </div>
                                            </div>
                                            <span class="badge-estado <?= htmlspecialchars($estado['clase']) ?>">
                                                <i class="bi <?= htmlspecialchars($estado['icono']) ?>"></i>
                                                <?= htmlspecialchars(
                                                    $estado['texto']
                                                ) ?>
                                            </span>
                                        </div>
                                        <!-- PRÓXIMA DOSIS -->
                                        <div class="datos-vacuna">
                                            <div class="dato-vacuna">
                                                <div class="titulo-dato-vacuna">
                                                    <i class="bi bi-calendar-event"></i>
                                                    Próxima dosis
                                                </div>
                                                <div class="valor-dato-vacuna">
                                                    <?php if (!empty($vacuna['fecha_proxima'])): ?>
                                                        <?= htmlspecialchars(
                                                            formatearFecha(
                                                                $vacuna['fecha_proxima']
                                                            )
                                                        ) ?>
                                                    <?php else: ?>
                                                        No indicada
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="dato-vacuna dato-observaciones">
                                                <div class="titulo-dato-vacuna">
                                                    <i class="bi bi-card-text"></i>
                                                    Observaciones
                                                </div>
                                                <div class="valor-dato-vacuna">
                                                    <?php if (!empty($vacuna['observaciones'])): ?>
                                                        <?= nl2br(
                                                            htmlspecialchars(
                                                                $vacuna['observaciones']
                                                            )
                                                        ) ?>
                                                    <?php else: ?>
                                                        Sin observaciones
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="../mensajeria/notificaciones.js" defer></script>
</body>
</html>