```php
<?php
// ============================================================
// VetClinic Pro — Alta Turno
// Archivo: turnos/alta_turno.php
// ============================================================

session_start();

if (empty($_SESSION['logueado']) || $_SESSION['tipo_usuario'] !== 'propietario') {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';
require_once '../config/suscripciones.php';
actualizarTodasSuscripciones($conn);

$idPropietario = (int)$_SESSION['usuario_id'];
$errores = [];


// ============================================================
// MASCOTAS DEL PROPIETARIO
// ============================================================

$stmt = $conn->prepare("
    SELECT id_mascota, nombre
    FROM mascotas
    WHERE id_propietario = ?
      AND COALESCE(fallecido, 0) = 0
    ORDER BY nombre ASC
");

$stmt->bind_param('i', $idPropietario);
$stmt->execute();

$mascotas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();


// ============================================================
// VETERINARIOS ACTIVOS
// ============================================================

$resultadoVeterinarios = $conn->query("
    SELECT
        v.id_veterinario,
        v.nombre,
        v.apellido,
        v.especialidad
    FROM veterinarios v
    INNER JOIN suscripciones s
        ON s.id_veterinario = v.id_veterinario
    WHERE v.activo = 1
      AND s.estado IN ('activa', 'atrasada')
    ORDER BY v.apellido ASC, v.nombre ASC
");

$veterinarios = $resultadoVeterinarios
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
    ORDER BY id_veterinario, dia_semana, hora_desde
");

$stmt->execute();

$resultadoHorarios = $stmt->get_result();

while ($fila = $resultadoHorarios->fetch_assoc()) {

    $idVet = (int)$fila['id_veterinario'];
    $dia = (int)$fila['dia_semana'];

    if (!isset($horariosVeterinarios[$idVet])) {
        $horariosVeterinarios[$idVet] = [];
    }

    if (!isset($horariosVeterinarios[$idVet][$dia])) {
        $horariosVeterinarios[$idVet][$dia] = [];
    }

    $horariosVeterinarios[$idVet][$dia][] = [
        'desde' => substr($fila['hora_desde'], 0, 5),
        'hasta' => substr($fila['hora_hasta'], 0, 5)
    ];
}

$stmt->close();


// ============================================================
// TURNOS YA OCUPADOS
// ============================================================
// Se cargan los turnos futuros para no mostrar horarios
// que ya fueron reservados.
//
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

$resultadoTurnos = $stmt->get_result();

while ($fila = $resultadoTurnos->fetch_assoc()) {

    $idVet = (int)$fila['id_veterinario'];

    $fecha = date(
        'Y-m-d',
        strtotime($fila['fecha_hora'])
    );

    $hora = date(
        'H:i',
        strtotime($fila['fecha_hora'])
    );

    if (!isset($turnosOcupados[$idVet])) {
        $turnosOcupados[$idVet] = [];
    }

    if (!isset($turnosOcupados[$idVet][$fecha])) {
        $turnosOcupados[$idVet][$fecha] = [];
    }

    $turnosOcupados[$idVet][$fecha][] = $hora;
}

$stmt->close();


// ============================================================
// PROCESAR FORMULARIO
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $idMascota     = (int)($_POST['id_mascota'] ?? 0);
    $idVeterinario = (int)($_POST['id_veterinario'] ?? 0);

    $fechaTurno = trim($_POST['fecha_turno'] ?? '');
    $horaTurno  = trim($_POST['hora_turno'] ?? '');

    $fechaHora = '';

    if ($fechaTurno !== '' && $horaTurno !== '') {
        $fechaHora = $fechaTurno . ' ' . $horaTurno . ':00';
    }

    $motivo        = trim($_POST['motivo'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');


    // ========================================================
    // VALIDAR MASCOTA Y PERTENENCIA
    // ========================================================

    if ($idMascota <= 0) {

        $errores[] = 'Seleccioná una mascota.';

    } else {

        $stmt = $conn->prepare("
            SELECT id_mascota
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

        $mascotaValida = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$mascotaValida) {
            $errores[] =
                'La mascota seleccionada no pertenece al propietario logueado.';
        }
    }


    // ========================================================
    // VALIDAR VETERINARIO ACTIVO
    // ========================================================

    if ($idVeterinario <= 0) {

        $errores[] = 'Seleccioná un veterinario.';

    } else {

        $stmt = $conn->prepare("
            SELECT v.id_veterinario
            FROM veterinarios v
            INNER JOIN suscripciones s
                ON s.id_veterinario = v.id_veterinario
            WHERE v.id_veterinario = ?
              AND v.activo = 1
              AND s.estado IN ('activa', 'atrasada')
            LIMIT 1
        ");

        $stmt->bind_param(
            'i',
            $idVeterinario
        );

        $stmt->execute();

        $veterinarioValido = $stmt
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

        $errores[] = 'Seleccioná una fecha.';

    } elseif ($horaTurno === '') {

        $errores[] = 'Seleccioná un horario disponible.';

    } else {

        $fechaObjeto = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $fechaHora
        );

        $erroresFecha = DateTime::getLastErrors();

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

        } elseif ($fechaObjeto <= new DateTime()) {

            $errores[] =
                'No se pueden solicitar turnos en el pasado.';
        }
    }


    // ========================================================
    // VALIDAR MOTIVO
    // ========================================================

    if ($motivo === '') {

        $errores[] =
            'El motivo de la consulta es obligatorio.';

    } elseif (mb_strlen($motivo) > 200) {

        $errores[] =
            'El motivo no puede superar los 200 caracteres.';
    }


    // ========================================================
    // VALIDAR OBSERVACIONES
    // ========================================================

    if (mb_strlen($observaciones) > 65535) {

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

        // 1 = lunes
        // 2 = martes
        // ...
        // 7 = domingo

        $diaSemana = (int)$fechaObjeto->format('N');

        $horarioValido = false;

        if (
            isset(
                $horariosVeterinarios[
                    $idVeterinario
                ][$diaSemana]
            )
        ) {

            foreach (
                $horariosVeterinarios[
                    $idVeterinario
                ][$diaSemana] as $horario
            ) {

                $desde = $horario['desde'];
                $hasta = $horario['hasta'];

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
        // VALIDAR QUE EL TURNO NO ESTÉ OCUPADO
        // ====================================================

        if (
            empty($errores) &&
            isset(
                $turnosOcupados[
                    $idVeterinario
                ][$fechaTurno]
            )
        ) {

            if (
                in_array(
                    $horaTurno,
                    $turnosOcupados[
                        $idVeterinario
                    ][$fechaTurno],
                    true
                )
            ) {

                $errores[] =
                    'Ese horario ya está ocupado. Seleccioná otro.';
            }
        }
    }


    // ========================================================
    // ÚLTIMA VERIFICACIÓN DIRECTAMENTE EN LA BASE
    // ========================================================
    // Esto evita que dos personas puedan reservar el mismo
    // horario si una de ellas abrió la página antes que la otra.
    // ========================================================

    if (empty($errores)) {

        $stmt = $conn->prepare("
            SELECT id_turno
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

        $turnoExistente = $stmt
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

    if (empty($errores)) {

        $observacionesFinal =
            $observaciones !== ''
                ? $observaciones
                : null;

        // Los turnos nuevos quedan confirmados
        // automáticamente porque el horario fue
        // previamente habilitado por el veterinario.

        $estadoInicial = 'confirmado';

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

        if ($stmt->execute()) {

            $stmt->close();

            $_SESSION['mensaje'] =
                '¡Turno confirmado con éxito! 📅';

            $_SESSION['tipo_mensaje'] =
                'exito';

            header('Location: inicio_turnos.php');
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

    <meta charset="UTF-8" />

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    />

    <title>Solicitar Turno · VetClinic Pro</title>

    <link
        rel="icon"
        type="image/png"
        href="../img/VetClinic Pro.png"
    >

    <link rel="preconnect" href="https://fonts.googleapis.com" />

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    />

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    />

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    />

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    />

    <link
        rel="stylesheet"
        href="../estilos/inicio.css"
    />

    <link
        rel="stylesheet"
        href="../estilos/abm.css"
    />

</head>


<body>

    <div class="container-fluid p-0">

        <div class="row g-0 min-vh-100">


            <!-- ==================================================
                 PANEL LATERAL
            =================================================== -->

            <div class="col-12 col-lg-2 panel-lateral">

                <a
                    href="../propietarios/inicio_propietarios.php"
                    class="logo-lateral"
                >

                    <img
                        src="../img/VetClinic Pro.png"
                        alt="Logo VetClinic Pro"
                    />

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
                    class="item-menu"
                >
                    <i class="bi bi-grid"></i>
                    Inicio
                </a>


                <a
                    href="../mascotas/inicio_mascota.php"
                    class="item-menu"
                >
                    <i class="bi bi-heart-pulse"></i>
                    Mis mascotas
                </a>


                <a
                    href="inicio_turnos.php"
                    class="item-menu activo"
                >
                    <i class="bi bi-calendar-check"></i>
                    Mis turnos
                </a>


                <a
                    href="#"
                    class="item-menu"
                >
                    <i class="bi bi-journal-medical"></i>
                    Historial clínico
                </a>


                <div class="seccion-nav">
                    Más
                </div>


                <a
                    href="#"
                    class="item-menu"
                >
                    <i class="bi bi-shield-plus"></i>
                    Vacunaciones
                </a>


                <a
                    href="#"
                    class="item-menu"
                >
                    <i class="bi bi-chat-dots"></i>
                    Mensajes
                </a>


                <!-- USUARIO -->

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


            <!-- ==================================================
                 ÁREA PRINCIPAL
            =================================================== -->

            <div class="col-12 col-lg-10 area-principal">


                <!-- BARRA SUPERIOR -->

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
                        class="btn-accion btn-volver"
                    >

                        <i class="bi bi-arrow-left"></i>

                        Volver

                    </a>

                </div>


                <!-- ==================================================
                     ERRORES PHP
                =================================================== -->

                <?php if (!empty($errores)): ?>

                    <div
                        class="alerta-operacion alerta-error"
                        role="alert"
                    >

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


                <!-- ==================================================
                     SIN MASCOTAS
                =================================================== -->

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
                            class="btn-abm-principal"
                        >

                            <i class="bi bi-plus-lg"></i>

                            Agregar mascota

                        </a>

                    </div>


                <?php else: ?>


                    <!-- ==================================================
                         FORMULARIO
                    =================================================== -->

                    <div class="tarjeta-formulario">

                        <form
                            action="alta_turno.php"
                            method="POST"
                            id="formAltaTurno"
                            novalidate
                        >


                            <div class="row g-3">


                                <!-- MASCOTA -->

                                <div class="col-12 col-md-6">

                                    <label
                                        class="form-label"
                                        for="id_mascota"
                                    >

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
                                            required
                                        >

                                            <option value="">
                                                Seleccioná una mascota...
                                            </option>


                                            <?php foreach ($mascotas as $m): ?>

                                                <option
                                                    value="<?= (int)$m['id_mascota'] ?>"
                                                    <?= (
                                                        ($_POST['id_mascota'] ?? '')
                                                        == $m['id_mascota']
                                                    )
                                                        ? 'selected'
                                                        : ''
                                                    ?>
                                                >

                                                    <?= htmlspecialchars(
                                                        $m['nombre']
                                                    ) ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>


                                    <div
                                        class="error-campo"
                                        id="errorMascota"
                                    ></div>

                                </div>


                                <!-- VETERINARIO -->

                                <div class="col-12 col-md-6">

                                    <label
                                        class="form-label"
                                        for="id_veterinario"
                                    >

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
                                            required
                                        >

                                            <option value="">
                                                Seleccioná un veterinario...
                                            </option>


                                            <?php foreach ($veterinarios as $v): ?>

                                                <option
                                                    value="<?= (int)$v['id_veterinario'] ?>"
                                                    <?= (
                                                        ($_POST['id_veterinario'] ?? '')
                                                        == $v['id_veterinario']
                                                    )
                                                        ? 'selected'
                                                        : ''
                                                    ?>
                                                >

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
                                        id="errorVeterinario"
                                    ></div>

                                </div>


                                <!-- FECHA -->

                                <div class="col-12 col-md-6">

                                    <label
                                        class="form-label"
                                        for="fecha_turno"
                                    >

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
                                            min="<?= date('Y-m-d') ?>"
                                            value="<?= htmlspecialchars(
                                                $_POST['fecha_turno'] ?? ''
                                            ) ?>"
                                            required
                                        />

                                    </div>


                                    <div
                                        class="error-campo"
                                        id="errorFecha"
                                    ></div>

                                </div>


                                <!-- HORARIO -->

                                <div class="col-12 col-md-6">

                                    <label
                                        class="form-label"
                                        for="hora_turno"
                                    >

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
                                            disabled
                                        >

                                            <option value="">
                                                Primero seleccioná veterinario y fecha...
                                            </option>

                                        </select>

                                    </div>


                                    <div
                                        class="form-text"
                                        id="ayudaHorario"
                                    >
                                        Los turnos tienen una duración de
                                        30 minutos.
                                    </div>


                                    <div
                                        class="error-campo"
                                        id="errorHora"
                                    ></div>

                                </div>


                                <!-- MOTIVO -->

                                <div class="col-12 col-md-6">

                                    <label
                                        class="form-label"
                                        for="motivo"
                                    >

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
                                                $_POST['motivo'] ?? ''
                                            ) ?>"
                                            required
                                        />

                                    </div>


                                    <div class="form-text">
                                        Máximo 200 caracteres.
                                    </div>


                                    <div
                                        class="error-campo"
                                        id="errorMotivo"
                                    ></div>

                                </div>


                                <!-- OBSERVACIONES -->

                                <div class="col-12">

                                    <label
                                        class="form-label"
                                        for="observaciones"
                                    >
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
                                            placeholder="Información adicional para la consulta..."
                                        ><?= htmlspecialchars(
                                            $_POST['observaciones'] ?? ''
                                        ) ?></textarea>

                                    </div>

                                </div>

                            </div>


                            <!-- ==================================================
                                 INFORMACIÓN
                            =================================================== -->

                            <div class="nota-info mt-4">

                                <i class="bi bi-info-circle-fill"></i>

                                <p>
                                    Tu turno quedará
                                    <strong>confirmado</strong>
                                    automáticamente porque el horario fue
                                    previamente habilitado por el veterinario.
                                </p>

                            </div>


                            <!-- ==================================================
                                 BOTONES
                            =================================================== -->

                            <div
                                class="d-flex gap-3 mt-4 pt-3 acciones-formulario"
                            >

                                <button
                                    type="submit"
                                    class="btn-abm-principal"
                                >

                                    <i class="bi bi-calendar-plus"></i>

                                    Confirmar turno

                                </button>


                                <a
                                    href="inicio_turnos.php"
                                    class="btn-accion btn-volver"
                                >
                                    Cancelar
                                </a>

                            </div>


                        </form>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- ============================================================
         BOOTSTRAP
    ============================================================= -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    <!-- ============================================================
         JAVASCRIPT DEL FORMULARIO
    ============================================================= -->

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            const form = document.getElementById('formAltaTurno');

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
            // ELEMENTOS DEL FORMULARIO
            // ========================================================

            const mascota =
                document.getElementById('id_mascota');

            const veterinario =
                document.getElementById('id_veterinario');

            const fecha =
                document.getElementById('fecha_turno');

            const hora =
                document.getElementById('hora_turno');

            const motivo =
                document.getElementById('motivo');


            // ========================================================
            // MOSTRAR ERROR
            // ========================================================

            function mostrarError(id, mensaje) {

                const div =
                    document.getElementById(id);

                if (!div) {
                    return;
                }

                div.textContent = mensaje;

                div.style.display =
                    mensaje ? 'block' : 'none';
            }


            // ========================================================
            // CONVERTIR HORA A MINUTOS
            // ========================================================

            function horaAMinutos(horaTexto) {

                const partes =
                    horaTexto.split(':');

                return (
                    parseInt(partes[0], 10) * 60
                    +
                    parseInt(partes[1], 10)
                );
            }


            // ========================================================
            // CONVERTIR MINUTOS A HORA
            // ========================================================

            function minutosAHora(minutos) {

                const horas =
                    Math.floor(minutos / 60);

                const minutosRestantes =
                    minutos % 60;

                return (
                    String(horas).padStart(2, '0')
                    +
                    ':'
                    +
                    String(minutosRestantes).padStart(2, '0')
                );
            }


            // ========================================================
            // OBTENER DÍA DE LA SEMANA
            // ========================================================
            // JavaScript:
            // 0 = domingo
            // 1 = lunes
            // ...
            // 6 = sábado
            //
            // Nuestra base:
            // 1 = lunes
            // ...
            // 7 = domingo
            // ========================================================

            function obtenerDiaSemana(fechaTexto) {

                const fechaObjeto =
                    new Date(fechaTexto + 'T00:00:00');

                const diaJS =
                    fechaObjeto.getDay();

                if (diaJS === 0) {
                    return 7;
                }

                return diaJS;
            }


            // ========================================================
            // CARGAR HORARIOS DISPONIBLES
            // ========================================================

            function cargarHorarios() {

                hora.innerHTML = '';

                hora.disabled = true;


                if (
                    !veterinario.value ||
                    !fecha.value
                ) {

                    const opcion =
                        document.createElement('option');

                    opcion.value = '';

                    opcion.textContent =
                        'Primero seleccioná veterinario y fecha...';

                    hora.appendChild(opcion);

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


                // ====================================================
                // VERIFICAR SI EXISTE HORARIO
                // ====================================================

                if (
                    !horariosVeterinarios[idVet] ||
                    !horariosVeterinarios[idVet][diaSemana]
                ) {

                    const opcion =
                        document.createElement('option');

                    opcion.value = '';

                    opcion.textContent =
                        'No hay horarios disponibles para ese día.';

                    hora.appendChild(opcion);

                    return;
                }


                // ====================================================
                // OBTENER HORARIOS OCUPADOS
                // ====================================================

                let ocupados = [];

                if (
                    turnosOcupados[idVet] &&
                    turnosOcupados[idVet][fechaSeleccionada]
                ) {

                    ocupados =
                        turnosOcupados[
                            idVet
                        ][fechaSeleccionada];
                }


                // ====================================================
                // GENERAR TURNOS DE 30 MINUTOS
                // ====================================================

                let cantidadHorarios = 0;


                horariosVeterinarios[
                    idVet
                ][diaSemana].forEach(function (horario) {

                    const desde =
                        horaAMinutos(
                            horario.desde
                        );

                    const hasta =
                        horaAMinutos(
                            horario.hasta
                        );


                    for (
                        let minutos = desde;
                        minutos < hasta;
                        minutos += 30
                    ) {

                        const horaDisponible =
                            minutosAHora(minutos);


                        // ==========================================
                        // SI YA ESTÁ OCUPADO, NO SE MUESTRA
                        // ==========================================

                        if (
                            ocupados.includes(
                                horaDisponible
                            )
                        ) {
                            continue;
                        }


                        const opcion =
                            document.createElement('option');

                        opcion.value =
                            horaDisponible;

                        opcion.textContent =
                            horaDisponible;


                        // Si hubo un error PHP y se vuelve a
                        // cargar el formulario, conservar la hora.

                        <?php if (!empty($_POST['hora_turno'])): ?>

                            if (
                                horaDisponible ===
                                <?= json_encode($_POST['hora_turno']) ?>
                            ) {

                                opcion.selected = true;
                            }

                        <?php endif; ?>


                        hora.appendChild(opcion);

                        cantidadHorarios++;
                    }

                });


                // ====================================================
                // RESULTADO
                // ====================================================

                if (cantidadHorarios === 0) {

                    hora.innerHTML = '';

                    const opcion =
                        document.createElement('option');

                    opcion.value = '';

                    opcion.textContent =
                        'No hay horarios disponibles para ese día.';

                    hora.appendChild(opcion);

                    hora.disabled = true;

                    return;
                }


                // Agregar opción inicial

                const opcionInicial =
                    document.createElement('option');

                opcionInicial.value = '';

                opcionInicial.textContent =
                    'Seleccioná un horario...';

                hora.insertBefore(
                    opcionInicial,
                    hora.firstChild
                );


                // Si no había hora seleccionada,
                // mostrar la opción inicial.

                if (!hora.value) {
                    opcionInicial.selected = true;
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
                function () {

                    hora.innerHTML = '';

                    hora.disabled = true;

                    const opcion =
                        document.createElement('option');

                    opcion.value = '';

                    opcion.textContent =
                        'Primero seleccioná fecha...';

                    hora.appendChild(opcion);


                    mostrarError(
                        'errorVeterinario',
                        ''
                    );

                    mostrarError(
                        'errorHora',
                        ''
                    );


                    if (fecha.value) {
                        cargarHorarios();
                    }

                }
            );


            // ========================================================
            // CAMBIAR FECHA
            // ========================================================

            fecha.addEventListener(
                'change',
                function () {

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


                    if (fecha.value) {

                        cargarHorarios();
                    }

                }
            );


            // ========================================================
            // VALIDACIÓN AL ENVIAR
            // ========================================================

            form.addEventListener(
                'submit',
                function (e) {

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


                    // ==============================================
                    // MASCOTA
                    // ==============================================

                    if (!mascota.value) {

                        mostrarError(
                            'errorMascota',
                            'Seleccioná una mascota.'
                        );

                        valido = false;
                    }


                    // ==============================================
                    // VETERINARIO
                    // ==============================================

                    if (!veterinario.value) {

                        mostrarError(
                            'errorVeterinario',
                            'Seleccioná un veterinario.'
                        );

                        valido = false;
                    }


                    // ==============================================
                    // FECHA
                    // ==============================================

                    if (!fecha.value) {

                        mostrarError(
                            'errorFecha',
                            'Seleccioná una fecha.'
                        );

                        valido = false;

                    } else {

                        const fechaSeleccionada =
                            new Date(
                                fecha.value + 'T00:00:00'
                            );

                        const hoy =
                            new Date();

                        hoy.setHours(
                            0,
                            0,
                            0,
                            0
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


                    // ==============================================
                    // HORA
                    // ==============================================

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


                    // ==============================================
                    // MOTIVO
                    // ==============================================

                    if (
                        !motivo.value.trim()
                    ) {

                        mostrarError(
                            'errorMotivo',
                            'El motivo de la consulta es obligatorio.'
                        );

                        valido = false;

                    } else if (
                        motivo.value.trim().length > 200
                    ) {

                        mostrarError(
                            'errorMotivo',
                            'El motivo no puede superar los 200 caracteres.'
                        );

                        valido = false;
                    }


                    // ==============================================
                    // DETENER ENVÍO SI HAY ERRORES
                    // ==============================================

                    if (!valido) {

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

        });

    </script>

</body>

</html>
```
