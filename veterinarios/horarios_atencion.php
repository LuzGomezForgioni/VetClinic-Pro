<?php

session_start();

include("../config/conexion.php");

// Verificar que esté logueado como veterinario
if (
    empty($_SESSION["logueado"]) ||
    $_SESSION["tipo_usuario"] !== "veterinario"
) {
    header("Location: ../index.php");
    exit();
}

// El ID del veterinario sale de la sesión
$id_veterinario = $_SESSION["usuario_id"];

$nombre = $_SESSION["usuario_nombre"] ?? "";
$apellido = $_SESSION["usuario_apellido"] ?? "";

$mensaje = "";
$tipo_mensaje = "";

// Días de la semana
$dias = [
    1 => "Lunes",
    2 => "Martes",
    3 => "Miércoles",
    4 => "Jueves",
    5 => "Viernes",
    6 => "Sábado",
    7 => "Domingo"
];

/*
|--------------------------------------------------------------------------
| GUARDAR HORARIOS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $horarios_recibidos = $_POST["horarios"] ?? [];

    try {

        // Iniciar transacción
        $conn->begin_transaction();

        /*
        | Primero eliminamos los horarios actuales
        | del veterinario.
        |
        | Después guardamos nuevamente los que envió
        | el formulario.
        */
        $stmt_eliminar = $conn->prepare("
            DELETE FROM horarios_atencion
            WHERE id_veterinario = ?
        ");

        $stmt_eliminar->bind_param(
            "i",
            $id_veterinario
        );

        $stmt_eliminar->execute();
        $stmt_eliminar->close();

        /*
        | Preparar inserción
        */
        $stmt_insertar = $conn->prepare("
            INSERT INTO horarios_atencion
            (
                id_veterinario,
                dia_semana,
                hora_desde,
                hora_hasta,
                activo
            )
            VALUES (?, ?, ?, ?, 1)
        ");

        /*
        | Recorrer los días
        */
        foreach ($dias as $numero_dia => $nombre_dia) {

            if (!isset($horarios_recibidos[$numero_dia])) {
                continue;
            }

            $bloques = $horarios_recibidos[$numero_dia];

            if (!is_array($bloques)) {
                continue;
            }

            foreach ($bloques as $bloque) {

                $hora_desde = trim($bloque["desde"] ?? "");
                $hora_hasta = trim($bloque["hasta"] ?? "");

                // Si falta alguna hora, ignoramos el bloque
                if ($hora_desde === "" || $hora_hasta === "") {
                    continue;
                }

                // Verificar que la hora inicial sea menor que la final
                if ($hora_desde >= $hora_hasta) {
                    throw new Exception(
                        "La hora de inicio debe ser menor que la hora de finalización."
                    );
                }

                $stmt_insertar->bind_param(
                    "iiss",
                    $id_veterinario,
                    $numero_dia,
                    $hora_desde,
                    $hora_hasta
                );

                $stmt_insertar->execute();
            }
        }

        $stmt_insertar->close();

        // Confirmar cambios
        $conn->commit();

        $mensaje = "Los horarios de atención se guardaron correctamente.";
        $tipo_mensaje = "success";
    } catch (Exception $e) {

        // Si ocurre un error, deshacer cambios
        $conn->rollback();

        $mensaje = "No se pudieron guardar los horarios.";
        $tipo_mensaje = "danger";
    }
}

/*
|--------------------------------------------------------------------------
| OBTENER HORARIOS ACTUALES
|--------------------------------------------------------------------------
*/

$horarios = [];

$stmt = $conn->prepare("
    SELECT
        id_horario,
        dia_semana,
        hora_desde,
        hora_hasta
    FROM horarios_atencion
    WHERE id_veterinario = ?
    AND activo = 1
    ORDER BY dia_semana, hora_desde
");

$stmt->bind_param("i", $id_veterinario);
$stmt->execute();

$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {

    $dia = $fila["dia_semana"];

    if (!isset($horarios[$dia])) {
        $horarios[$dia] = [];
    }

    $horarios[$dia][] = [
        "desde" => substr($fila["hora_desde"], 0, 5),
        "hasta" => substr($fila["hora_hasta"], 0, 5)
    ];
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

    <title>Horarios de atención · VetClinic Pro</title>

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
        href="https://fonts.googleapis.com"
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

    <!-- CSS general del sistema -->
    <link
        rel="stylesheet"
        href="../estilos/inicio.css">

    <!-- CSS específico de horarios -->
    <link
        rel="stylesheet"
        href="../estilos/horarios.css">

</head>

<body>

    <div class="container-fluid p-0">

        <div class="row g-0 min-vh-100">

            <!-- =====================================================
             BARRA LATERAL
        ====================================================== -->

            <div class="col-12 col-lg-2 panel-lateral">

                <!-- LOGO -->

                <a
                    href="../veterinarios/inicio_veterinario.php"
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


                <!-- NAVEGACIÓN -->

                <div class="seccion-nav">
                    Principal
                </div>

                <a
                    href="../veterinarios/inicio_veterinario.php"
                    class="item-menu">
                    <i class="bi bi-grid"></i>
                    Inicio
                </a>

                <a href="#" class="item-menu">
                    <i class="bi bi-calendar-check"></i>
                    Turnos
                </a>

                <a href="#" class="item-menu">
                    <i class="bi bi-heart-pulse"></i>
                    Mascotas
                </a>

                <a href="#" class="item-menu">
                    <i class="bi bi-journal-medical"></i>
                    Historial clínico
                </a>


                <!-- GESTIÓN -->

                <div class="seccion-nav">
                    Gestión
                </div>

                <a href="#" class="item-menu">
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
                    <span
                        class="badge ms-auto"
                        style="background:var(--azul-claro);color:var(--azul-oscuro);font-size:10px;">
                        3
                    </span>
                </a>


                <!-- CONFIGURACIÓN -->

                <div class="seccion-nav">
                    Configuración
                </div>
                
                <a href="horarios_atencion.php" class="item-menu activo">
                    <i class="bi bi-clock"></i>
                    Horarios de atención
                </a>


                <!-- USUARIO -->

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
                                $nombre . " " . $apellido
                            ) ?>

                        </div>

                        <div class="rol-usuario">
                            Veterinario/a
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
                            Horarios de atención
                        </p>

                        <p class="fecha-hoy">
                            Configurá los días y horarios en los que atendés.
                        </p>

                    </div>

                    <div class="d-flex gap-2">

                        <a
                            href="../veterinarios/inicio_veterinario.php"
                            class="icono-barra"
                            title="Volver al inicio">
                            <i class="bi bi-arrow-left"></i>
                        </a>

                        <a
                            href="../logout.php"
                            class="icono-barra"
                            title="Cerrar sesión">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>

                    </div>

                </div>


                <!-- MENSAJE -->

                <?php if ($mensaje !== ""): ?>

                    <div
                        class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show"
                        role="alert">

                        <?= htmlspecialchars($mensaje) ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"></button>

                    </div>

                <?php endif; ?>


                <!-- CONTENIDO -->

                <div class="contenedor-horarios">

                    <div class="encabezado-horarios">

                        <div class="titulo-seccion">
                            Mi horario de atención
                        </div>

                        <p class="descripcion-seccion">
                            Indicá los días y los horarios en los que recibís pacientes.
                            Podés agregar más de un horario en el mismo día.
                        </p>

                    </div>


                    <form method="POST">


                        <?php foreach ($dias as $numero_dia => $nombre_dia): ?>

                            <?php
                            $bloques_dia = $horarios[$numero_dia] ?? [];
                            ?>

                            <div class="fila-dia">

                                <div class="row align-items-center">

                                    <!-- DÍA -->

                                    <div class="col-12 col-lg-3">

                                        <div class="nombre-dia">
                                            <?= $nombre_dia ?>
                                        </div>

                                    </div>


                                    <!-- HORARIOS -->

                                    <div class="col-12 col-lg-9">

                                        <div
                                            id="horarios-dia-<?= $numero_dia ?>">

                                            <?php if (empty($bloques_dia)): ?>

                                                <div
                                                    class="texto-no-atiende"
                                                    id="sin-horarios-<?= $numero_dia ?>">
                                                    No atendés este día.
                                                </div>

                                            <?php else: ?>

                                                <?php foreach ($bloques_dia as $indice => $bloque): ?>

                                                    <div class="bloque-horario">

                                                        <div class="campo-hora">

                                                            <input
                                                                type="time"
                                                                name="horarios[<?= $numero_dia ?>][<?= $indice ?>][desde]"
                                                                value="<?= htmlspecialchars($bloque["desde"]) ?>"
                                                                required>

                                                        </div>

                                                        <span class="texto-a">
                                                            a
                                                        </span>

                                                        <div class="campo-hora">

                                                            <input
                                                                type="time"
                                                                name="horarios[<?= $numero_dia ?>][<?= $indice ?>][hasta]"
                                                                value="<?= htmlspecialchars($bloque["hasta"]) ?>"
                                                                required>

                                                        </div>

                                                        <button
                                                            type="button"
                                                            class="boton-eliminar"
                                                            onclick="eliminarHorario(this)"
                                                            title="Eliminar horario">
                                                            <i class="bi bi-trash"></i>
                                                        </button>

                                                    </div>

                                                <?php endforeach; ?>

                                            <?php endif; ?>

                                        </div>


                                        <button
                                            type="button"
                                            class="boton-agregar"
                                            onclick="agregarHorario(<?= $numero_dia ?>)">
                                            <i class="bi bi-plus"></i>
                                            Agregar horario
                                        </button>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>


                        <!-- AYUDA -->

                        <div class="ayuda-horarios">

                            <i class="bi bi-info-circle me-2"></i>

                            Los turnos para los propietarios se generarán en
                            intervalos de <strong>30 minutos</strong> dentro de
                            estos horarios. Los turnos que ya estén ocupados
                            no estarán disponibles.

                        </div>


                        <!-- GUARDAR -->

                        <div class="d-flex justify-content-end mt-4">

                            <button
                                type="submit"
                                class="boton-guardar">

                                <i class="bi bi-check2 me-1"></i>

                                Guardar horarios

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
     JAVASCRIPT
====================================================== -->

    <script>
        function agregarHorario(numeroDia) {

            const contenedor =
                document.getElementById(
                    "horarios-dia-" + numeroDia
                );

            // Eliminar mensaje "No atendés este día"
            const mensaje =
                document.getElementById(
                    "sin-horarios-" + numeroDia
                );

            if (mensaje) {
                mensaje.remove();
            }

            // Contar bloques existentes
            const cantidad =
                contenedor.querySelectorAll(
                    ".bloque-horario"
                ).length;

            const bloque =
                document.createElement("div");

            bloque.className =
                "bloque-horario";

            bloque.innerHTML = `

        <div class="campo-hora">

            <input
                type="time"
                name="horarios[${numeroDia}][${cantidad}][desde]"
                required
            >

        </div>

        <span class="texto-a">
            a
        </span>

        <div class="campo-hora">

            <input
                type="time"
                name="horarios[${numeroDia}][${cantidad}][hasta]"
                required
            >

        </div>

        <button
            type="button"
            class="boton-eliminar"
            onclick="eliminarHorario(this)"
            title="Eliminar horario"
        >
            <i class="bi bi-trash"></i>
        </button>

    `;

            contenedor.appendChild(bloque);
        }


        function eliminarHorario(boton) {

            const bloque =
                boton.closest(".bloque-horario");

            const contenedor =
                bloque.parentElement;

            const numeroDia =
                contenedor.id.replace(
                    "horarios-dia-",
                    ""
                );

            bloque.remove();

            // Si no quedan horarios,
            // mostrar mensaje nuevamente

            const quedan =
                contenedor.querySelectorAll(
                    ".bloque-horario"
                ).length;

            if (quedan === 0) {

                const mensaje =
                    document.createElement("div");

                mensaje.className =
                    "texto-no-atiende";

                mensaje.id =
                    "sin-horarios-" + numeroDia;

                mensaje.textContent =
                    "No atendés este día.";

                contenedor.appendChild(mensaje);
            }
        }
    </script>


    <!-- Bootstrap JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>