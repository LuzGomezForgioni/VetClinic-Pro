<?php
// ============================================================
// VetClinic Pro — Alta Turno
// Archivo: turnos/alta_turno.php
// ============================================================

session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');

if (
    empty($_SESSION['logueado']) ||
    $_SESSION['tipo_usuario'] !== 'propietario'
) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';

$idPropietario = (int) $_SESSION['usuario_id'];

$errores = [];


// ============================================================
// FECHA Y HORA ACTUAL DEL SERVIDOR
// ============================================================

$fechaActualServidor = date('Y-m-d');

$horaActualServidor = (int) date('H');

$minutosActualesServidor =
    ($horaActualServidor * 60) +
    (int) date('i');


// ============================================================
// MASCOTAS DEL PROPIETARIO
// ============================================================

$stmt = $conn->prepare("
    SELECT
        id_mascota,
        nombre
    FROM mascotas
    WHERE id_propietario = ?
    ORDER BY nombre ASC
");

$stmt->bind_param(
    'i',
    $idPropietario
);

$stmt->execute();

$mascotas =
    $stmt
    ->get_result()
    ->fetch_all(MYSQLI_ASSOC);

$stmt->close();


// ============================================================
// VETERINARIOS ACTIVOS
// ============================================================

$resultadoVeterinarios = $conn->query("
    SELECT
        id_veterinario,
        nombre,
        apellido,
        especialidad
    FROM veterinarios
    WHERE activo = 1
    ORDER BY apellido ASC, nombre ASC
");

$veterinarios =
    $resultadoVeterinarios
    ? $resultadoVeterinarios->fetch_all(MYSQLI_ASSOC)
    : [];


// ============================================================
// HORARIOS CONFIGURADOS POR LOS VETERINARIOS
// ============================================================

$horariosVeterinarios = [];

$stmt = $conn->prepare("
    SELECT
        id_veterinario,
        dia_semana,
        hora_desde,
        hora_hasta
    FROM horarios_atencion
    WHERE activo = 1
    ORDER BY
        id_veterinario,
        dia_semana,
        hora_desde
");

$stmt->execute();

$resultadoHorarios =
    $stmt->get_result();

while (
    $fila =
    $resultadoHorarios->fetch_assoc()
) {

    $idVet =
        (int) $fila['id_veterinario'];

    $dia =
        (int) $fila['dia_semana'];


    if (
        !isset(
            $horariosVeterinarios[$idVet]
        )
    ) {
        $horariosVeterinarios[$idVet] = [];
    }


    if (
        !isset(
            $horariosVeterinarios[$idVet][$dia]
        )
    ) {
        $horariosVeterinarios[$idVet][$dia] = [];
    }


    $horariosVeterinarios[$idVet][$dia][] = [
        'desde' =>
        substr(
            $fila['hora_desde'],
            0,
            5
        ),

        'hasta' =>
        substr(
            $fila['hora_hasta'],
            0,
            5
        )
    ];
}

$stmt->close();


// ============================================================
// TURNOS YA OCUPADOS
// Los turnos cancelados NO bloquean el horario.
// ============================================================

$turnosOcupados = [];

$stmt = $conn->prepare("
    SELECT
        id_veterinario,
        fecha_hora
    FROM turnos
    WHERE fecha_hora >= NOW()
      AND estado <> 'cancelado'
");

$stmt->execute();

$resultadoTurnos =
    $stmt->get_result();

while (
    $fila =
    $resultadoTurnos->fetch_assoc()
) {

    $idVet =
        (int) $fila['id_veterinario'];


    $fecha =
        date(
            'Y-m-d',
            strtotime(
                $fila['fecha_hora']
            )
        );


    $hora =
        date(
            'H:i',
            strtotime(
                $fila['fecha_hora']
            )
        );


    if (
        !isset(
            $turnosOcupados[$idVet]
        )
    ) {
        $turnosOcupados[$idVet] = [];
    }


    if (
        !isset(
            $turnosOcupados[$idVet][$fecha]
        )
    ) {
        $turnosOcupados[$idVet][$fecha] = [];
    }


    $turnosOcupados[$idVet][$fecha][] =
        $hora;
}

$stmt->close();


// ============================================================
// PROCESAR FORMULARIO
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $idMascota =
        (int) (
            $_POST['id_mascota']
            ?? 0
        );


    $idVeterinario =
        (int) (
            $_POST['id_veterinario']
            ?? 0
        );


    $fechaTurno =
        trim(
            $_POST['fecha_turno']
                ?? ''
        );


    $horaTurno =
        trim(
            $_POST['hora_turno']
                ?? ''
        );


    $fechaHora = '';


    if (
        $fechaTurno !== '' &&
        $horaTurno !== ''
    ) {

        $fechaHora =
            $fechaTurno .
            ' ' .
            $horaTurno .
            ':00';
    }


    $motivo =
        trim(
            $_POST['motivo']
                ?? ''
        );


    $observaciones =
        trim(
            $_POST['observaciones']
                ?? ''
        );



    // ========================================================
    // VALIDAR MASCOTA
    // ========================================================

    if ($idMascota <= 0) {

        $errores[] =
            'Seleccioná una mascota.';
    } else {

        $stmt = $conn->prepare("
            SELECT
                id_mascota
            FROM mascotas
            WHERE id_mascota = ?
              AND id_propietario = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            'ii',
            $idMascota,
            $idPropietario
        );

        $stmt->execute();

        $mascotaValida =
            $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();


        if (!$mascotaValida) {

            $errores[] =
                'La mascota seleccionada no pertenece al propietario logueado.';
        }
    }



    // ========================================================
    // VALIDAR VETERINARIO
    // ========================================================

    if ($idVeterinario <= 0) {

        $errores[] =
            'Seleccioná un veterinario.';
    } else {

        $stmt = $conn->prepare("
            SELECT
                id_veterinario
            FROM veterinarios
            WHERE id_veterinario = ?
              AND activo = 1
            LIMIT 1
        ");

        $stmt->bind_param(
            'i',
            $idVeterinario
        );

        $stmt->execute();

        $veterinarioValido =
            $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();


        if (!$veterinarioValido) {

            $errores[] =
                'El veterinario seleccionado no está disponible.';
        }
    }



    // ========================================================
    // VALIDAR FECHA Y HORA
    // ========================================================

    $fechaObjeto = null;


    if ($fechaTurno === '') {

        $errores[] =
            'Seleccioná una fecha.';
    } elseif ($horaTurno === '') {

        $errores[] =
            'Seleccioná un horario disponible.';
    } else {

        $fechaObjeto =
            DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $fechaHora
            );


        $erroresFecha =
            DateTime::getLastErrors();


        if (
            !$fechaObjeto ||
            (
                $erroresFecha !== false &&
                (
                    $erroresFecha['warning_count'] > 0 ||
                    $erroresFecha['error_count'] > 0
                )
            )
        ) {

            $errores[] =
                'La fecha y hora seleccionadas no son válidas.';
        } elseif (
            $fechaObjeto <= new DateTime()
        ) {

            $errores[] =
                'No se pueden solicitar turnos en horarios que ya pasaron.';
        }
    }



    // ========================================================
    // VALIDAR MOTIVO
    // ========================================================

    if ($motivo === '') {

        $errores[] =
            'El motivo de la consulta es obligatorio.';
    } elseif (
        mb_strlen($motivo) > 200
    ) {

        $errores[] =
            'El motivo no puede superar los 200 caracteres.';
    }



    // ========================================================
    // VALIDAR OBSERVACIONES
    // ========================================================

    if (
        mb_strlen($observaciones) > 65535
    ) {

        $errores[] =
            'Las observaciones son demasiado extensas.';
    }



    // ========================================================
    // VALIDAR HORARIO DEL VETERINARIO
    // ========================================================

    if (
        empty($errores) &&
        $fechaObjeto instanceof DateTime
    ) {

        $diaSemana =
            (int) $fechaObjeto->format('N');


        $horarioValido = false;


        if (
            isset(
                $horariosVeterinarios[$idVeterinario][$diaSemana]
            )
        ) {

            foreach (
                $horariosVeterinarios[$idVeterinario][$diaSemana] as $horario
            ) {

                $desde =
                    $horario['desde'];

                $hasta =
                    $horario['hasta'];


                if (
                    $horaTurno >= $desde &&
                    $horaTurno < $hasta
                ) {

                    $horarioValido = true;

                    break;
                }
            }
        }


        if (!$horarioValido) {

            $errores[] =
                'El veterinario no atiende en el día y horario seleccionados.';
        }



        // ====================================================
        // VALIDAR INTERVALOS DE 30 MINUTOS
        // ====================================================

        if (
            $fechaObjeto->format('i') !== '00' &&
            $fechaObjeto->format('i') !== '30'
        ) {

            $errores[] =
                'Los turnos deben comenzar en intervalos de 30 minutos.';
        }



        // ====================================================
        // VALIDAR QUE NO SEA UN HORARIO PASADO
        // ====================================================

        if (
            empty($errores) &&
            $fechaTurno === $fechaActualServidor
        ) {

            $minutosTurno =
                ((int) $fechaObjeto->format('H') * 60) +
                (int) $fechaObjeto->format('i');


            if (
                $minutosTurno <=
                $minutosActualesServidor
            ) {

                $errores[] =
                    'No podés seleccionar un horario que ya pasó.';
            }
        }



        // ====================================================
        // VALIDAR QUE NO ESTÉ OCUPADO
        // ====================================================

        if (
            empty($errores) &&
            isset(
                $turnosOcupados[$idVeterinario][$fechaTurno]
            )
        ) {

            if (
                in_array(
                    $horaTurno,
                    $turnosOcupados[$idVeterinario][$fechaTurno],
                    true
                )
            ) {

                $errores[] =
                    'Ese horario ya está ocupado. Seleccioná otro.';
            }
        }
    }



    // ========================================================
    // ÚLTIMA VERIFICACIÓN EN BASE DE DATOS
    // ========================================================

    if (
        empty($errores)
    ) {

        $stmt = $conn->prepare("
            SELECT
                id_turno
            FROM turnos
            WHERE id_veterinario = ?
              AND fecha_hora = ?
              AND estado <> 'cancelado'
            LIMIT 1
        ");

        $stmt->bind_param(
            'is',
            $idVeterinario,
            $fechaHora
        );

        $stmt->execute();

        $turnoExistente =
            $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();


        if ($turnoExistente) {

            $errores[] =
                'Ese horario acaba de ser ocupado. Seleccioná otro.';
        }
    }



    // ========================================================
    // GUARDAR TURNO
    // ========================================================

    if (
        empty($errores)
    ) {

        $observacionesFinal =
            $observaciones !== ''
            ? $observaciones
            : null;


        $estadoInicial =
            'confirmado';


        $stmt = $conn->prepare("
            INSERT INTO turnos
            (
                id_mascota,
                id_veterinario,
                id_propietario,
                fecha_hora,
                motivo,
                estado,
                observaciones
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");


        $stmt->bind_param(
            'iiissss',
            $idMascota,
            $idVeterinario,
            $idPropietario,
            $fechaHora,
            $motivo,
            $estadoInicial,
            $observacionesFinal
        );


        if (
            $stmt->execute()
        ) {

            $stmt->close();


            $_SESSION['mensaje'] =
                '¡Turno confirmado con éxito! 📅';

            $_SESSION['tipo_mensaje'] =
                'exito';


            header(
                'Location: inicio_turnos.php'
            );

            exit;
        }


        $stmt->close();


        $errores[] =
            'No se pudo registrar el turno. Intentá nuevamente.';
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
        Solicitar Turno · VetClinic Pro
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


    <!-- Estilos -->

    <link
        rel="stylesheet"
        href="../estilos/inicio.css">

    <link
        rel="stylesheet"
        href="../estilos/abm.css">

</head>


<body>

    <div class="container-fluid p-0">

        <div class="row g-0 min-vh-100">


            <!-- PANEL LATERAL -->

            <div class="col-12 col-lg-2 panel-lateral">

                <a
                    href="../propietarios/inicio_propietarios.php"
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
                    href="../propietarios/inicio_propietarios.php"
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
                    href="inicio_turnos.php"
                    class="item-menu activo">

                    <i class="bi bi-calendar-check"></i>

                    Mis turnos

                </a>


                <a
                    href="#"
                    class="item-menu">

                    <i class="bi bi-journal-medical"></i>

                    Historial clínico

                </a>


                <div class="seccion-nav">
                    Más
                </div>


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


                <div class="usuario-lateral">

                    <div class="avatar-usuario">

                        <?= htmlspecialchars(
                            strtoupper(
                                mb_substr(
                                    $_SESSION['usuario_nombre'],
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>


                    <div>

                        <div class="nombre-usuario">

                            <?= htmlspecialchars(
                                $_SESSION['usuario_nombre']
                                    . ' '
                                    . (
                                        $_SESSION['usuario_apellido']
                                        ?? ''
                                    )
                            ) ?>

                        </div>


                        <div class="rol-usuario">
                            Propietario
                        </div>

                    </div>

                </div>

            </div>


            <!-- ÁREA PRINCIPAL -->

            <div class="col-12 col-lg-10 area-principal">


                <div class="barra-superior">

                    <div>

                        <p class="titulo-pagina">
                            Solicitar turno
                        </p>

                        <p class="fecha-hoy">
                            Completá los datos de la consulta
                        </p>

                    </div>


                    <a
                        href="inicio_turnos.php"
                        class="btn-accion btn-volver">

                        <i class="bi bi-arrow-left"></i>

                        Volver

                    </a>

                </div>


                <!-- ERRORES -->

                <?php if (!empty($errores)): ?>

                    <div
                        class="alerta-operacion alerta-error"
                        role="alert">

                        <i class="bi bi-exclamation-circle-fill"></i>

                        <div>

                            <?php foreach ($errores as $e): ?>

                                <div>
                                    <?= htmlspecialchars($e) ?>
                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- SIN MASCOTAS -->

                <?php if (empty($mascotas)): ?>

                    <div class="tarjeta estado-vacio">

                        <div class="emoji-vacio">
                            🐾
                        </div>

                        <h4>
                            Primero necesitás registrar una mascota
                        </h4>

                        <p>
                            Para solicitar un turno tenés que tener
                            al menos una mascota asociada a tu cuenta.
                        </p>

                        <a
                            href="../mascotas/alta_mascota.php"
                            class="btn-abm-principal">

                            <i class="bi bi-plus-lg"></i>

                            Agregar mascota

                        </a>

                    </div>


                <?php else: ?>


                    <!-- FORMULARIO -->

                    <div class="tarjeta-formulario">

                        <form
                            action="alta_turno.php"
                            method="POST"
                            id="formAltaTurno"
                            novalidate>

                            <div class="row g-3">


                                <!-- MASCOTA -->

                                <div class="col-12 col-md-6">

                                    <label
                                        class="form-label"
                                        for="id_mascota">

                                        Mascota

                                        <span class="campo-requerido">
                                            *
                                        </span>

                                    </label>


                                    <div class="input-group">

                                        <span class="input-group-text">
                                            <i class="bi bi-heart-pulse"></i>
                                        </span>


                                        <select
                                            class="form-control"
                                            id="id_mascota"
                                            name="id_mascota"
                                            required>

                                            <option value="">
                                                Seleccioná una mascota...
                                            </option>


                                            <?php foreach ($mascotas as $m): ?>

                                                <option
                                                    value="<?= (int) $m['id_mascota'] ?>"
                                                    <?= (
                                                        ($_POST['id_mascota'] ?? '')
                                                        == $m['id_mascota']
                                                    )
                                                        ? 'selected'
                                                        : ''
                                                    ?>>

                                                    <?= htmlspecialchars(
                                                        $m['nombre']
                                                    ) ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                    <div
                                        class="error-campo"
                                        id="errorMascota"></div>

                                </div>


                                <!-- VETERINARIO -->

                                <div class="col-12 col-md-6">

                                    <label
                                        class="form-label"
                                        for="id_veterinario">

                                        Veterinario

                                        <span class="campo-requerido">
                                            *
                                        </span>

                                    </label>


                                    <div class="input-group">

                                        <span class="input-group-text">
                                            <i class="bi bi-person-badge"></i>
                                        </span>


                                        <select
                                            class="form-control"
                                            id="id_veterinario"
                                            name="id_veterinario"
                                            required>

                                            <option value="">
                                                Seleccioná un veterinario...
                                            </option>


                                            <?php foreach ($veterinarios as $v): ?>

                                                <option
                                                    value="<?= (int) $v['id_veterinario'] ?>"
                                                    <?= (
                                                        ($_POST['id_veterinario'] ?? '')
                                                        == $v['id_veterinario']
                                                    )
                                                        ? 'selected'
                                                        : ''
                                                    ?>>

                                                    Dr./Dra.
                                                    <?= htmlspecialchars(
                                                        trim(
                                                            $v['nombre']
                                                                . ' '
                                                                . $v['apellido']
                                                        )
                                                    ) ?>

                                                    <?php if ($v['especialidad']): ?>

                                                        ·

                                                        <?= htmlspecialchars(
                                                            $v['especialidad']
                                                        ) ?>

                                                    <?php endif; ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                    <div
                                        class="error-campo"
                                        id="errorVeterinario"></div>

                                </div>


                                <!-- FECHA -->

                                <div class="col-12 col-md-6">

                                    <label
                                        class="form-label"
                                        for="fecha_turno">

                                        Fecha

                                        <span class="campo-requerido">
                                            *
                                        </span>

                                    </label>


                                    <div class="input-group">

                                        <span class="input-group-text">
                                            <i class="bi bi-calendar3"></i>
                                        </span>


                                        <input
                                            type="date"
                                            class="form-control"
                                            id="fecha_turno"
                                            name="fecha_turno"
                                            min="<?= $fechaActualServidor ?>"
                                            value="<?= htmlspecialchars(
                                                        $_POST['fecha_turno']
                                                            ?? ''
                                                    ) ?>"
                                            required>

                                    </div>

                                    <div
                                        class="error-campo"
                                        id="errorFecha"></div>

                                </div>


                                <!-- HORARIO -->

                                <div class="col-12 col-md-6">

                                    <label
                                        class="form-label"
                                        for="hora_turno">

                                        Horario disponible

                                        <span class="campo-requerido">
                                            *
                                        </span>

                                    </label>


                                    <div class="input-group">

                                        <span class="input-group-text">
                                            <i class="bi bi-clock"></i>
                                        </span>


                                        <select
                                            class="form-control"
                                            id="hora_turno"
                                            name="hora_turno"
                                            required
                                            disabled>

                                            <option value="">
                                                Primero seleccioná veterinario y fecha...
                                            </option>

                                        </select>

                                    </div>


                                    <div
                                        class="form-text"
                                        id="ayudaHorario">

                                        Los turnos tienen una duración de
                                        30 minutos.

                                    </div>


                                    <div
                                        class="error-campo"
                                        id="errorHora"></div>

                                </div>


                                <!-- MOTIVO -->

                                <div class="col-12 col-md-6">

                                    <label
                                        class="form-label"
                                        for="motivo">

                                        Motivo

                                        <span class="campo-requerido">
                                            *
                                        </span>

                                    </label>


                                    <div class="input-group">

                                        <span class="input-group-text">
                                            <i class="bi bi-chat-left-text"></i>
                                        </span>


                                        <input
                                            type="text"
                                            class="form-control"
                                            id="motivo"
                                            name="motivo"
                                            maxlength="200"
                                            placeholder="Ej: Control general, vacunación..."
                                            value="<?= htmlspecialchars(
                                                        $_POST['motivo']
                                                            ?? ''
                                                    ) ?>"
                                            required>

                                    </div>


                                    <div class="form-text">
                                        Máximo 200 caracteres.
                                    </div>


                                    <div
                                        class="error-campo"
                                        id="errorMotivo"></div>

                                </div>


                                <!-- OBSERVACIONES -->

                                <div class="col-12">

                                    <label
                                        class="form-label"
                                        for="observaciones">

                                        Observaciones

                                    </label>


                                    <div class="input-group">

                                        <span class="input-group-text">
                                            <i class="bi bi-card-text"></i>
                                        </span>


                                        <textarea
                                            class="form-control"
                                            id="observaciones"
                                            name="observaciones"
                                            rows="4"
                                            placeholder="Información adicional para la consulta..."><?= htmlspecialchars(
                                                                                                        $_POST['observaciones']
                                                                                                            ?? ''
                                                                                                    ) ?></textarea>

                                    </div>

                                </div>

                            </div>


                            <div class="nota-info mt-4">

                                <i class="bi bi-info-circle-fill"></i>

                                <p>

                                    Tu turno quedará
                                    <strong>confirmado</strong>
                                    automáticamente porque el horario fue
                                    previamente habilitado por el veterinario.

                                </p>

                            </div>


                            <div
                                class="d-flex gap-3 mt-4 pt-3 acciones-formulario">

                                <button
                                    type="submit"
                                    class="btn-abm-principal">

                                    <i class="bi bi-calendar-plus"></i>

                                    Confirmar turno

                                </button>


                                <a
                                    href="inicio_turnos.php"
                                    class="btn-accion btn-volver">

                                    Cancelar

                                </a>

                            </div>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- BOOTSTRAP -->

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>


    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function() {

                const form =
                    document.getElementById(
                        'formAltaTurno'
                    );


                if (!form) {
                    return;
                }


                // ========================================================
                // DATOS GENERADOS POR PHP
                // ========================================================

                const horariosVeterinarios =
                    <?= json_encode(
                        $horariosVeterinarios,
                        JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                    ) ?>;


                const turnosOcupados =
                    <?= json_encode(
                        $turnosOcupados,
                        JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                    ) ?>;


                // ========================================================
                // FECHA Y HORA DEL SERVIDOR
                // ========================================================

                const fechaActualServidor =
                    <?= json_encode(
                        $fechaActualServidor
                    ) ?>;


                const minutosActualesServidor =
                    <?= (int) $minutosActualesServidor ?>;


                const horaSeleccionadaAnterior =
                    <?= json_encode(
                        $_POST['hora_turno']
                            ?? ''
                    ) ?>;


                // ========================================================
                // ELEMENTOS
                // ========================================================

                const mascota =
                    document.getElementById(
                        'id_mascota'
                    );


                const veterinario =
                    document.getElementById(
                        'id_veterinario'
                    );


                const fecha =
                    document.getElementById(
                        'fecha_turno'
                    );


                const hora =
                    document.getElementById(
                        'hora_turno'
                    );


                const motivo =
                    document.getElementById(
                        'motivo'
                    );


                // ========================================================
                // MOSTRAR ERROR
                // ========================================================

                function mostrarError(
                    id,
                    mensaje
                ) {

                    const div =
                        document.getElementById(id);


                    if (!div) {
                        return;
                    }


                    div.textContent =
                        mensaje;


                    div.style.display =
                        mensaje ?
                        'block' :
                        'none';
                }


                // ========================================================
                // HORA → MINUTOS
                // ========================================================

                function horaAMinutos(
                    horaTexto
                ) {

                    const partes =
                        horaTexto.split(':');


                    return (
                            parseInt(
                                partes[0],
                                10
                            ) * 60
                        ) +
                        parseInt(
                            partes[1],
                            10
                        );
                }


                // ========================================================
                // MINUTOS → HORA
                // ========================================================

                function minutosAHora(
                    minutos
                ) {

                    const horas =
                        Math.floor(
                            minutos / 60
                        );


                    const minutosRestantes =
                        minutos % 60;


                    return (
                        String(horas).padStart(
                            2,
                            '0'
                        ) +
                        ':' +
                        String(
                            minutosRestantes
                        ).padStart(
                            2,
                            '0'
                        )
                    );
                }


                // ========================================================
                // OBTENER DÍA DE LA SEMANA
                // ========================================================

                function obtenerDiaSemana(
                    fechaTexto
                ) {

                    const fechaObjeto =
                        new Date(
                            fechaTexto +
                            'T00:00:00'
                        );


                    const diaJS =
                        fechaObjeto.getDay();


                    if (diaJS === 0) {
                        return 7;
                    }


                    return diaJS;
                }


                // ========================================================
                // CARGAR HORARIOS
                // ========================================================

                function cargarHorarios() {

                    hora.innerHTML = '';

                    hora.disabled = true;


                    // ----------------------------------------------------
                    // VERIFICAR VETERINARIO Y FECHA
                    // ----------------------------------------------------

                    if (
                        !veterinario.value ||
                        !fecha.value
                    ) {

                        const opcion =
                            document.createElement(
                                'option'
                            );


                        opcion.value = '';

                        opcion.textContent =
                            'Primero seleccioná veterinario y fecha...';


                        hora.appendChild(
                            opcion
                        );

                        return;
                    }


                    const idVet =
                        veterinario.value;


                    const fechaSeleccionada =
                        fecha.value;


                    const diaSemana =
                        obtenerDiaSemana(
                            fechaSeleccionada
                        );


                    // ----------------------------------------------------
                    // VERIFICAR SI EL VETERINARIO ATIENDE ESE DÍA
                    // ----------------------------------------------------

                    if (
                        !horariosVeterinarios[idVet] ||
                        !horariosVeterinarios[idVet][diaSemana]
                    ) {

                        const opcion =
                            document.createElement(
                                'option'
                            );


                        opcion.value = '';

                        opcion.textContent =
                            'El veterinario no atiende ese día.';


                        hora.appendChild(
                            opcion
                        );

                        return;
                    }


                    // ----------------------------------------------------
                    // TURNOS OCUPADOS
                    // ----------------------------------------------------

                    let ocupados = [];


                    if (
                        turnosOcupados[idVet] &&
                        turnosOcupados[idVet][fechaSeleccionada]
                    ) {

                        ocupados =
                            turnosOcupados[idVet][fechaSeleccionada];
                    }


                    // ----------------------------------------------------
                    // GENERAR HORARIOS DISPONIBLES
                    // ----------------------------------------------------

                    let cantidadHorarios = 0;


                    horariosVeterinarios[idVet][diaSemana]
                        .forEach(
                            function(horario) {

                                const desde =
                                    horaAMinutos(
                                        horario.desde
                                    );


                                const hasta =
                                    horaAMinutos(
                                        horario.hasta
                                    );


                                for (
                                    let minutos = desde; minutos < hasta; minutos += 30
                                ) {

                                    const horaDisponible =
                                        minutosAHora(
                                            minutos
                                        );


                                    // ======================================
                                    // SI LA FECHA ES HOY,
                                    // OCULTAR HORARIOS QUE YA PASARON
                                    // ======================================

                                    if (
                                        fechaSeleccionada ===
                                        fechaActualServidor &&
                                        minutos <=
                                        minutosActualesServidor
                                    ) {

                                        continue;
                                    }


                                    // ======================================
                                    // SI EL HORARIO ESTÁ OCUPADO
                                    // ======================================

                                    if (
                                        ocupados.includes(
                                            horaDisponible
                                        )
                                    ) {

                                        continue;
                                    }


                                    // ======================================
                                    // CREAR OPCIÓN
                                    // ======================================

                                    const opcion =
                                        document.createElement(
                                            'option'
                                        );


                                    opcion.value =
                                        horaDisponible;


                                    opcion.textContent =
                                        horaDisponible;


                                    // ======================================
                                    // MANTENER HORA SELECCIONADA
                                    // DESPUÉS DE UN ERROR DEL FORMULARIO
                                    // ======================================

                                    if (
                                        horaDisponible ===
                                        horaSeleccionadaAnterior
                                    ) {

                                        opcion.selected =
                                            true;
                                    }


                                    hora.appendChild(
                                        opcion
                                    );


                                    cantidadHorarios++;
                                }

                            }
                        );


                    // ----------------------------------------------------
                    // NO QUEDAN HORARIOS
                    // ----------------------------------------------------

                    if (
                        cantidadHorarios === 0
                    ) {

                        hora.innerHTML = '';


                        const opcion =
                            document.createElement(
                                'option'
                            );


                        opcion.value = '';


                        if (
                            fechaSeleccionada ===
                            fechaActualServidor
                        ) {

                            opcion.textContent =
                                'No quedan horarios disponibles para hoy.';

                        } else {

                            opcion.textContent =
                                'No hay horarios disponibles para ese día.';
                        }


                        hora.appendChild(
                            opcion
                        );


                        hora.disabled = true;

                        return;
                    }


                    // ----------------------------------------------------
                    // OPCIÓN INICIAL
                    // ----------------------------------------------------

                    const opcionInicial =
                        document.createElement(
                            'option'
                        );


                    opcionInicial.value = '';

                    opcionInicial.textContent =
                        'Seleccioná un horario...';


                    hora.insertBefore(
                        opcionInicial,
                        hora.firstChild
                    );


                    if (
                        !hora.value
                    ) {

                        opcionInicial.selected =
                            true;
                    }


                    hora.disabled = false;


                    mostrarError(
                        'errorHora',
                        ''
                    );
                }


                // ========================================================
                // CAMBIAR VETERINARIO
                // ========================================================

                veterinario.addEventListener(
                    'change',
                    function() {

                        hora.innerHTML = '';

                        hora.disabled = true;


                        const opcion =
                            document.createElement(
                                'option'
                            );


                        opcion.value = '';

                        opcion.textContent =
                            'Primero seleccioná fecha...';


                        hora.appendChild(
                            opcion
                        );


                        mostrarError(
                            'errorVeterinario',
                            ''
                        );


                        mostrarError(
                            'errorHora',
                            ''
                        );


                        if (
                            fecha.value
                        ) {

                            cargarHorarios();
                        }

                    }
                );


                // ========================================================
                // CAMBIAR FECHA
                // ========================================================

                fecha.addEventListener(
                    'change',
                    function() {

                        hora.innerHTML = '';

                        hora.disabled = true;


                        mostrarError(
                            'errorFecha',
                            ''
                        );


                        mostrarError(
                            'errorHora',
                            ''
                        );


                        if (
                            fecha.value
                        ) {

                            cargarHorarios();
                        }

                    }
                );


                // ========================================================
                // VALIDACIÓN AL ENVIAR
                // ========================================================

                form.addEventListener(
                    'submit',
                    function(e) {

                        let valido = true;


                        mostrarError(
                            'errorMascota',
                            ''
                        );

                        mostrarError(
                            'errorVeterinario',
                            ''
                        );

                        mostrarError(
                            'errorFecha',
                            ''
                        );

                        mostrarError(
                            'errorHora',
                            ''
                        );

                        mostrarError(
                            'errorMotivo',
                            ''
                        );


                        // MASCOTA

                        if (
                            !mascota.value
                        ) {

                            mostrarError(
                                'errorMascota',
                                'Seleccioná una mascota.'
                            );

                            valido = false;
                        }


                        // VETERINARIO

                        if (
                            !veterinario.value
                        ) {

                            mostrarError(
                                'errorVeterinario',
                                'Seleccioná un veterinario.'
                            );

                            valido = false;
                        }


                        // FECHA

                        if (
                            !fecha.value
                        ) {

                            mostrarError(
                                'errorFecha',
                                'Seleccioná una fecha.'
                            );

                            valido = false;

                        } else {

                            const fechaSeleccionada =
                                new Date(
                                    fecha.value +
                                    'T00:00:00'
                                );


                            const hoy =
                                new Date(
                                    fechaActualServidor +
                                    'T00:00:00'
                                );


                            if (
                                fechaSeleccionada < hoy
                            ) {

                                mostrarError(
                                    'errorFecha',
                                    'La fecha debe ser igual o posterior a hoy.'
                                );

                                valido = false;
                            }
                        }


                        // HORARIO

                        if (
                            !hora.value ||
                            hora.disabled
                        ) {

                            mostrarError(
                                'errorHora',
                                'Seleccioná un horario disponible.'
                            );

                            valido = false;
                        }


                        // MOTIVO

                        if (
                            !motivo.value.trim()
                        ) {

                            mostrarError(
                                'errorMotivo',
                                'El motivo de la consulta es obligatorio.'
                            );

                            valido = false;

                        } else if (
                            motivo.value
                            .trim()
                            .length > 200
                        ) {

                            mostrarError(
                                'errorMotivo',
                                'El motivo no puede superar los 200 caracteres.'
                            );

                            valido = false;
                        }


                        if (
                            !valido
                        ) {

                            e.preventDefault();
                        }

                    }
                );


                // ========================================================
                // CARGAR HORARIOS SI YA HABÍA DATOS
                // ========================================================

                if (
                    veterinario.value &&
                    fecha.value
                ) {

                    cargarHorarios();
                }

            }
        );
    </script>

</body>

</html>