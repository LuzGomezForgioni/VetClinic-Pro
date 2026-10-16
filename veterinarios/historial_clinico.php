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



$id_veterinario = $_SESSION['usuario_id'];



// ======================================================

// OBTENER ID DEL TURNO

// ======================================================



$id_turno = intval($_GET['id_turno'] ?? 0);



if ($id_turno <= 0) {

    header('Location: turnos_veterinario.php');

    exit;

}



// ======================================================

// BUSCAR EL TURNO

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



        WHERE t.id_turno = ?

        AND t.id_veterinario = ?";



$stmt = $conn->prepare($sql);



$stmt->bind_param(

    "ii",

    $id_turno,

    $id_veterinario

);



$stmt->execute();



$resultado = $stmt->get_result();



$turno = $resultado->fetch_assoc();



$stmt->close();



// ======================================================

// SI EL TURNO NO EXISTE O NO PERTENECE AL VETERINARIO

// ======================================================



if (!$turno) {



    header('Location: turnos_veterinario.php');



    exit;

}



// ======================================================

// BUSCAR SI YA EXISTE UNA HISTORIA PARA ESTE TURNO

// ======================================================



$sql_historia = "SELECT *



                 FROM historia_clinica



                 WHERE id_turno = ?



                 AND id_veterinario = ?



                 LIMIT 1";



$stmt_historia = $conn->prepare($sql_historia);



$stmt_historia->bind_param(

    "ii",

    $id_turno,

    $id_veterinario

);



$stmt_historia->execute();



$resultado_historia = $stmt_historia->get_result();



$historia = $resultado_historia->fetch_assoc();



$stmt_historia->close();



// ======================================================

// GUARDAR HISTORIA CLÍNICA

// ======================================================



$mensaje = '';

$error = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    $motivo_consulta = trim(

        $_POST['motivo_consulta'] ?? ''

    );



    $diagnostico = trim(

        $_POST['diagnostico'] ?? ''

    );



    $tratamiento = trim(

        $_POST['tratamiento'] ?? ''

    );



    $medicacion = trim(

        $_POST['medicacion'] ?? ''

    );



    $peso_registro = trim(

        $_POST['peso_registro'] ?? ''

    );



    $proximos_pasos = trim(

        $_POST['proximos_pasos'] ?? ''

    );



    // ==================================================

    // VALIDAR

    // ==================================================



    // Una visita (turno) solo puede generar una historia clínica.
    // Si ya existe una para este turno, no se permite sobrescribirla.
    if ($historia) {

        $error = 'Esta visita ya tiene una historia clínica registrada. Para una nueva atención debe existir un nuevo turno.';

    } elseif ($motivo_consulta === '') {

        $error = 'El motivo de consulta es obligatorio.';

    } else {

        // ==============================================

        // CREAR UNA NUEVA HISTORIA PARA ESTA VISITA

        // ==============================================




            $fecha_consulta = date(

                'Y-m-d',

                strtotime($turno['fecha_hora'])

            );



            $sql_insert = "INSERT INTO historia_clinica (



                               id_turno,

                               id_mascota,

                               id_veterinario,

                               fecha_consulta,

                               motivo_consulta,

                               diagnostico,

                               tratamiento,

                               medicacion,

                               peso_registro,

                               proximos_pasos



                           ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?)";



            $stmt = $conn->prepare($sql_insert);



            $stmt->bind_param(

                "iiisssssss",

                $id_turno,

                $turno['id_mascota'],

                $id_veterinario,

                $fecha_consulta,

                $motivo_consulta,

                $diagnostico,

                $tratamiento,

                $medicacion,

                $peso_registro,

                $proximos_pasos

            );



            if ($stmt->execute()) {



                // ======================================

                // MARCAR TURNO COMO REALIZADO

                // ======================================



                $sql_estado = "UPDATE turnos



                               SET estado = 'realizado'



                               WHERE id_turno = ?



                               AND id_veterinario = ?";



                $stmt_estado = $conn->prepare($sql_estado);



                $stmt_estado->bind_param(

                    "ii",

                    $id_turno,

                    $id_veterinario

                );



                $stmt_estado->execute();



                $stmt_estado->close();



                $mensaje = 'Historia clínica guardada correctamente.';



                // ======================================

                // VOLVER A BUSCAR LA HISTORIA

                // ======================================



                $sql_historia = "SELECT *



                                 FROM historia_clinica



                                 WHERE id_turno = ?



                                 AND id_veterinario = ?



                                 LIMIT 1";



                $stmt_historia = $conn->prepare($sql_historia);



                $stmt_historia->bind_param(

                    "ii",

                    $id_turno,

                    $id_veterinario

                );



                $stmt_historia->execute();



                $resultado_historia =

                    $stmt_historia->get_result();



                $historia =

                    $resultado_historia->fetch_assoc();



                $stmt_historia->close();



            } else {



                $error =

                    'No se pudo guardar la historia clínica.';

            }



            $stmt->close();

    }

}



// ======================================================

// FUNCIONES

// ======================================================



function calcularEdad(?string $fecha_nacimiento)

{

    if (empty($fecha_nacimiento)) {

        return 'No registrada';

    }



    $nacimiento =

        new DateTime($fecha_nacimiento);



    $hoy =

        new DateTime();



    $edad =

        $hoy->diff($nacimiento);



    if ($edad->y > 0) {



        return $edad->y .

            ($edad->y == 1

                ? ' año'

                : ' años');

    }



    if ($edad->m > 0) {



        return $edad->m .

            ($edad->m == 1

                ? ' mes'

                : ' meses');

    }



    return 'Menos de 1 mes';

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

        Historia clínica · VetClinic Pro

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

        class="item-menu">



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

        href="historial_clinico.php?id_turno=<?= $id_turno ?>"

        class="item-menu activo">



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



    <!-- Barra superior -->



    <div class="barra-superior">



        <div>



            <p class="titulo-pagina">

                Historia clínica

            </p>



            <p class="fecha-hoy">



                <?= date('d/m/Y') ?>



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



            Historia clínica



        </h2>



        <p class="fecha-hoy">



            Registro de atención de la mascota.



        </p>



    </div>





    <!-- ================================================== -->

    <!-- MENSAJES -->

    <!-- ================================================== -->



    <?php if ($mensaje !== ''): ?>



        <div class="alert alert-success">



            <i class="bi bi-check-circle me-2"></i>



            <?= htmlspecialchars($mensaje) ?>



        </div>



    <?php endif; ?>





    <?php if ($error !== ''): ?>



        <div class="alert alert-danger">



            <i class="bi bi-exclamation-circle me-2"></i>



            <?= htmlspecialchars($error) ?>



        </div>



    <?php endif; ?>





    <!-- ================================================== -->

    <!-- INFORMACIÓN DE LA MASCOTA -->

    <!-- ================================================== -->



    <div class="tarjeta mb-4">



        <div class="titulo-tarjeta mb-3">



            <i class="bi bi-heart-pulse"></i>



            Información de la mascota



        </div>



        <div class="row g-3">



            <div class="col-12 col-md-6 col-xl-3">



                <div class="fw-semibold">

                    Mascota

                </div>



                <div>

                    <?= htmlspecialchars(

                        $turno['nombre_mascota']

                    ) ?>

                </div>



            </div>



            <div class="col-12 col-md-6 col-xl-3">



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



            <div class="col-12 col-md-6 col-xl-3">



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



            <div class="col-12 col-md-6 col-xl-3">



                <div class="fw-semibold">

                    Edad

                </div>



                <div>

                    <?= calcularEdad(

                        $turno['fecha_nacimiento']

                    ) ?>

                </div>



            </div>



            <div class="col-12 col-md-6">



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



            <div class="col-12 col-md-6">



                <div class="fw-semibold">

                    Veterinario

                </div>



                <div>



                    <?= htmlspecialchars(

                        $titulo .

                        ' ' .

                        $nombre .

                        ' ' .

                        $apellido

                    ) ?>



                </div>



            </div>



        </div>



    </div>





    <!-- ================================================== -->

    <!-- DATOS DEL TURNO -->

    <!-- ================================================== -->



    <div class="tarjeta mb-4">



        <div class="titulo-tarjeta mb-3">



            <i class="bi bi-calendar-check"></i>



            Datos de la consulta



        </div>



        <div class="row g-3">



            <div class="col-12 col-md-4">



                <label class="form-label">

                    Fecha

                </label>



                <input

                    type="text"

                    class="form-control"

                    value="<?= date(

                        'd/m/Y',

                        strtotime($turno['fecha_hora'])

                    ) ?>"

                    readonly>



            </div>



            <div class="col-12 col-md-4">



                <label class="form-label">

                    Hora

                </label>



                <input

                    type="text"

                    class="form-control"

                    value="<?= date(

                        'H:i',

                        strtotime($turno['fecha_hora'])

                    ) ?>"

                    readonly>



            </div>



            <div class="col-12 col-md-4">



                <label class="form-label">

                    Estado del turno

                </label>



                <input

                    type="text"

                    class="form-control"

                    value="<?= ucfirst(

                        $turno['estado']

                    ) ?>"

                    readonly>



            </div>



        </div>



    </div>





    <!-- ================================================== -->

    <!-- FORMULARIO HISTORIA CLÍNICA -->

    <!-- ================================================== -->



    <form

        method="POST"

        action="historial_clinico.php?id_turno=<?= $id_turno ?>">



        <div class="tarjeta mb-4">



            <div class="titulo-tarjeta mb-4">



                <i class="bi bi-journal-medical"></i>



                <?= $historia

                    ? 'Consulta registrada'

                    : 'Registrar nueva consulta'

                ?>



            </div>



            <?php if ($historia): ?>

                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    Esta visita ya fue registrada. Los datos se muestran solo para consulta y no se pueden sobrescribir.
                    Para una nueva atención, ingresá desde un nuevo turno.
                </div>

            <?php endif; ?>



            <!-- Motivo -->



            <div class="mb-3">



                <label

                    for="motivo_consulta"

                    class="form-label">



                    Motivo de consulta



                </label>



                <textarea

                    id="motivo_consulta"

                    name="motivo_consulta"

                    class="form-control"

                    rows="3"

                    <?= $historia ? 'readonly' : '' ?>

                    required><?= htmlspecialchars(

                        $historia

                            ? $historia['motivo_consulta']

                            : $turno['motivo']

                    ) ?></textarea>



            </div>





            <!-- Diagnóstico -->



            <div class="mb-3">



                <label

                    for="diagnostico"

                    class="form-label">



                    Diagnóstico



                </label>



                <textarea

                    id="diagnostico"

                    name="diagnostico"

                    class="form-control"

                    rows="4"

                    <?= $historia ? 'readonly' : '' ?>

                    placeholder="Ingrese el diagnóstico realizado..."><?= htmlspecialchars(

                        $historia['diagnostico'] ?? ''

                    ) ?></textarea>



            </div>





            <!-- Tratamiento -->



            <div class="mb-3">



                <label

                    for="tratamiento"

                    class="form-label">



                    Tratamiento



                </label>



                <textarea

                    id="tratamiento"

                    name="tratamiento"

                    class="form-control"

                    rows="4"

                    <?= $historia ? 'readonly' : '' ?>

                    placeholder="Indique el tratamiento recomendado..."><?= htmlspecialchars(

                        $historia['tratamiento'] ?? ''

                    ) ?></textarea>



            </div>





            <!-- Medicación -->



            <div class="mb-3">



                <label

                    for="medicacion"

                    class="form-label">



                    Medicación



                </label>



                <textarea

                    id="medicacion"

                    name="medicacion"

                    class="form-control"

                    rows="3"

                    <?= $historia ? 'readonly' : '' ?>

                    placeholder="Indique la medicación, dosis y frecuencia si corresponde..."><?= htmlspecialchars(

                        $historia['medicacion'] ?? ''

                    ) ?></textarea>



            </div>





            <!-- Peso -->



            <div class="mb-3">



                <label

                    for="peso_registro"

                    class="form-label">



                    Peso registrado (kg)



                </label>



                <input

                    type="number"

                    step="0.01"

                    min="0"

                    id="peso_registro"

                    name="peso_registro"

                    class="form-control"

                    <?= $historia ? 'readonly' : '' ?>

                    placeholder="Ej. 60.00"

                    value="<?= htmlspecialchars(

                        $historia['peso_registro']

                        ?? $turno['peso_kg']

                        ?? ''

                    ) ?>">



            </div>





            <!-- Próximos pasos -->



            <div class="mb-3">



                <label

                    for="proximos_pasos"

                    class="form-label">



                    Próximos pasos



                </label>



                <textarea

                    id="proximos_pasos"

                    name="proximos_pasos"

                    class="form-control"

                    rows="4"

                    <?= $historia ? 'readonly' : '' ?>

                    placeholder="Indique controles, estudios o recomendaciones futuras..."><?= htmlspecialchars(

                        $historia['proximos_pasos'] ?? ''

                    ) ?></textarea>



            </div>





            <!-- Botones -->



            <div class="d-flex gap-2 mt-4">



                <?php if (!$historia): ?>

                    <button

                        type="submit"

                        class="btn btn-primary">



                        <i class="bi bi-check-circle me-1"></i>



                        Guardar consulta



                    </button>

                <?php endif; ?>



                <a

                    href="turnos_veterinario.php"

                    class="btn btn-light">



                    <i class="bi bi-arrow-left me-1"></i>



                    Volver a turnos



                </a>



            </div>



        </div>



    </form>



</div>



</div>



</div>



</div>



</body>



</html>