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

// ======================================================
// OBTENER PROPIETARIO
// ======================================================

$id_propietario = (int) ($_GET['id_propietario'] ?? 0);

if ($id_propietario <= 0) {
    header('Location: propietarios_veterinario.php');
    exit;
}

// ======================================================
// BUSCAR DATOS DEL PROPIETARIO
// ======================================================

$sql_propietario = "
    SELECT
        id_propietario,
        nombre,
        apellido,
        email,
        telefono,
        fecha_registro

    FROM propietarios

    WHERE id_propietario = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql_propietario);
$stmt->bind_param("i", $id_propietario);
$stmt->execute();

$propietario = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$propietario) {
    header('Location: propietarios_veterinario.php');
    exit;
}


// ======================================================
// NOTAS INTERNAS DEL PROPIETARIO
// ======================================================

$id_veterinario_actual = (int) ($_SESSION['usuario_id'] ?? 0);
$mensaje_nota = $_SESSION['mensaje_nota_propietario'] ?? '';
$tipo_mensaje_nota = $_SESSION['tipo_mensaje_nota_propietario'] ?? '';
unset($_SESSION['mensaje_nota_propietario'], $_SESSION['tipo_mensaje_nota_propietario']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar_nota') {

    $comentario = trim($_POST['comentario'] ?? '');

    if ($comentario === '') {
        $_SESSION['mensaje_nota_propietario'] = 'Escribí un comentario antes de guardarlo.';
        $_SESSION['tipo_mensaje_nota_propietario'] = 'danger';
    } elseif (mb_strlen($comentario) > 2000) {
        $_SESSION['mensaje_nota_propietario'] = 'La nota es demasiado extensa. Máximo: 2000 caracteres.';
        $_SESSION['tipo_mensaje_nota_propietario'] = 'danger';
    } else {
        $stmt = $conn->prepare("\n            INSERT INTO notas_propietarios\n                (id_propietario, id_veterinario, comentario)\n            VALUES (?, ?, ?)\n        ");
        $stmt->bind_param('iis', $id_propietario, $id_veterinario_actual, $comentario);

        if ($stmt->execute()) {
            $_SESSION['mensaje_nota_propietario'] = 'La nota interna se guardó correctamente.';
            $_SESSION['tipo_mensaje_nota_propietario'] = 'success';
        } else {
            $_SESSION['mensaje_nota_propietario'] = 'No se pudo guardar la nota.';
            $_SESSION['tipo_mensaje_nota_propietario'] = 'danger';
        }
        $stmt->close();
    }

    header('Location: ficha_propietario.php?id_propietario=' . $id_propietario);
    exit;
}

$notas_propietario = [];

$stmt = $conn->prepare("\n    SELECT\n        np.id_nota,\n        np.comentario,\n        np.fecha,\n        v.nombre AS nombre_veterinario,\n        v.apellido AS apellido_veterinario\n    FROM notas_propietarios np\n    INNER JOIN veterinarios v\n        ON np.id_veterinario = v.id_veterinario\n    WHERE np.id_propietario = ?\n    ORDER BY np.fecha DESC, np.id_nota DESC\n");
$stmt->bind_param('i', $id_propietario);
$stmt->execute();
$resultado_notas = $stmt->get_result();

while ($fila = $resultado_notas->fetch_assoc()) {
    $notas_propietario[] = $fila;
}
$stmt->close();

// ======================================================
// MASCOTAS DEL PROPIETARIO
// ======================================================

$sql_mascotas = "
    SELECT
        m.id_mascota,
        m.nombre,
        m.fecha_nacimiento,
        m.sexo,
        m.peso_kg,
        m.color,
        m.castrado,
        m.fallecido,

        e.nombre_especie,
        r.nombre_raza,

        (
            SELECT COUNT(*)
            FROM historia_clinica hc
            WHERE hc.id_mascota = m.id_mascota
        ) AS cantidad_consultas,

        (
            SELECT COUNT(*)
            FROM vacunaciones va
            WHERE va.id_mascota = m.id_mascota
        ) AS cantidad_vacunas,

        (
            SELECT MIN(t.fecha_hora)
            FROM turnos t
            WHERE t.id_mascota = m.id_mascota
              AND t.fecha_hora >= NOW()
              AND t.estado <> 'cancelado'
        ) AS proximo_turno

    FROM mascotas m

    LEFT JOIN especies e
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

// ======================================================
// PRÓXIMOS TURNOS DEL PROPIETARIO
// ======================================================

$sql_turnos = "
    SELECT
        t.id_turno,
        t.fecha_hora,
        t.motivo,
        t.estado,

        m.id_mascota,
        m.nombre AS nombre_mascota

    FROM turnos t

    INNER JOIN mascotas m
        ON t.id_mascota = m.id_mascota

    WHERE t.id_propietario = ?
      AND t.fecha_hora >= NOW()
      AND t.estado <> 'cancelado'

    ORDER BY t.fecha_hora ASC

    LIMIT 5
";

$stmt = $conn->prepare($sql_turnos);
$stmt->bind_param("i", $id_propietario);
$stmt->execute();

$resultado_turnos = $stmt->get_result();

$proximos_turnos = [];

while ($fila = $resultado_turnos->fetch_assoc()) {
    $proximos_turnos[] = $fila;
}

$stmt->close();

// ======================================================
// FUNCIONES
// ======================================================

function edadMascotaPropietario(?string $fecha_nacimiento): string
{
    if (empty($fecha_nacimiento)) {
        return 'Edad no registrada';
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
        return 'Edad no registrada';
    }
}

function sexoMascotaPropietario(?string $sexo): string
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

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars(
            $propietario['nombre'] . ' ' . $propietario['apellido']
        ) ?> · VetClinic Pro
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
                        Ficha del propietario
                    </p>

                    <p class="fecha-hoy">
                        Datos de contacto y mascotas registradas.
                    </p>

                </div>

                <div class="d-flex gap-2">

                    <a
                        href="propietarios_veterinario.php"
                        class="icono-barra"
                        title="Volver a propietarios">

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

            <!-- ================================================== -->
            <!-- CABECERA -->
            <!-- ================================================== -->

            <?php if ($mensaje_nota !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($tipo_mensaje_nota) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje_nota) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <section class="cabecera-ficha-propietario mb-4">

                <div class="avatar-ficha-propietario">

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

                    <div class="etiqueta-propietario-ficha">
                        Propietario
                    </div>

                    <h1>

                        <?= htmlspecialchars(
                            $propietario['nombre'] .
                            ' ' .
                            $propietario['apellido']
                        ) ?>

                    </h1>

                    <p>

                        <?= count($mascotas) ?>

                        <?= count($mascotas) === 1
                            ? 'mascota registrada'
                            : 'mascotas registradas'
                        ?>

                    </p>

                </div>

            </section>

            <div class="row g-4">

                <div class="col-12 col-xl-4">

                    <!-- ========================================== -->
                    <!-- DATOS DEL PROPIETARIO -->
                    <!-- ========================================== -->

                    <section class="tarjeta mb-4">

                        <div class="titulo-tarjeta">

                            <i class="bi bi-person-vcard"></i>
                            Datos del propietario

                        </div>

                        <div class="lista-datos-propietario">

                            <div class="dato-propietario-ficha">

                                <i class="bi bi-envelope"></i>

                                <div>

                                    <span>
                                        Email
                                    </span>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $propietario['email']
                                                ?: 'No registrado'
                                        ) ?>

                                    </strong>

                                </div>

                            </div>

                            <div class="dato-propietario-ficha">

                                <i class="bi bi-telephone"></i>

                                <div>

                                    <span>
                                        Teléfono
                                    </span>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $propietario['telefono']
                                                ?: 'No registrado'
                                        ) ?>

                                    </strong>

                                </div>

                            </div>

                            <div class="dato-propietario-ficha">

                                <i class="bi bi-calendar3"></i>

                                <div>

                                    <span>
                                        Registrado desde
                                    </span>

                                    <strong>

                                        <?= date(
                                            'd/m/Y',
                                            strtotime(
                                                $propietario['fecha_registro']
                                            )
                                        ) ?>

                                    </strong>

                                </div>

                            </div>

                        </div>

                    </section>

                    <!-- ========================================== -->
                    <!-- NOTAS INTERNAS -->
                    <!-- ========================================== -->

                    <section class="tarjeta tarjeta-notas-propietario mb-4">

                        <div class="titulo-tarjeta">
                            <i class="bi bi-sticky"></i>
                            Notas internas
                        </div>

                        <p class="texto-ayuda-notas">
                            Solo son visibles dentro del portal del veterinario. No modifican la cuenta del propietario.
                        </p>

                        <form method="POST" class="form-nota-propietario">
                            <input type="hidden" name="accion" value="agregar_nota">

                            <textarea
                                name="comentario"
                                class="form-control"
                                rows="3"
                                maxlength="2000"
                                placeholder="Ej.: Prefiere turnos por la mañana..."
                                required></textarea>

                            <button type="submit" class="btn-guardar-nota">
                                <i class="bi bi-plus-circle"></i>
                                Agregar nota
                            </button>
                        </form>

                        <?php if (!empty($notas_propietario)): ?>

                            <div class="lista-notas-propietario">

                                <?php foreach ($notas_propietario as $nota): ?>

                                    <article class="nota-interna-propietario">
                                        <p><?= nl2br(htmlspecialchars($nota['comentario'])) ?></p>

                                        <div class="meta-nota-propietario">
                                            <span>
                                                <?= htmlspecialchars(
                                                    $nota['nombre_veterinario'] . ' ' .
                                                    $nota['apellido_veterinario']
                                                ) ?>
                                            </span>
                                            <span>·</span>
                                            <span><?= date('d/m/Y H:i', strtotime($nota['fecha'])) ?></span>
                                        </div>
                                    </article>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>
                            <div class="sin-notas-propietario">
                                Todavía no hay notas internas para este propietario.
                            </div>
                        <?php endif; ?>

                    </section>

                    <!-- ========================================== -->
                    <!-- PRÓXIMOS TURNOS -->
                    <!-- ========================================== -->

                    <section class="tarjeta">

                        <div class="titulo-tarjeta">

                            <i class="bi bi-calendar-check"></i>
                            Próximos turnos

                        </div>

                        <?php if (!empty($proximos_turnos)): ?>

                            <div class="lista-turnos-propietario">

                                <?php foreach ($proximos_turnos as $turno): ?>

                                    <a
                                        href="historial_clinico.php?id_turno=<?= (int) $turno['id_turno'] ?>"
                                        class="turno-propietario">

                                        <div class="fecha-turno-propietario">

                                            <strong>

                                                <?= date(
                                                    'd/m',
                                                    strtotime(
                                                        $turno['fecha_hora']
                                                    )
                                                ) ?>

                                            </strong>

                                            <span>

                                                <?= date(
                                                    'H:i',
                                                    strtotime(
                                                        $turno['fecha_hora']
                                                    )
                                                ) ?> hs

                                            </span>

                                        </div>

                                        <div class="flex-grow-1">

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $turno['nombre_mascota']
                                                ) ?>

                                            </strong>

                                            <span>

                                                <?= htmlspecialchars(
                                                    $turno['motivo']
                                                ) ?>

                                            </span>

                                        </div>

                                        <i class="bi bi-chevron-right"></i>

                                    </a>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="estado-vacio-propietario-pequeno">

                                <i class="bi bi-calendar-x"></i>

                                <div>

                                    <strong>
                                        Sin próximos turnos
                                    </strong>

                                    <span>
                                        No hay turnos futuros para este propietario.
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>

                    </section>

                </div>

                <div class="col-12 col-xl-8">

                    <!-- ========================================== -->
                    <!-- MASCOTAS -->
                    <!-- ========================================== -->

                    <section class="tarjeta">

                        <div class="cabecera-mascotas-propietario">

                            <div class="titulo-tarjeta mb-0">

                                <i class="bi bi-heart-pulse"></i>
                                Sus mascotas

                            </div>

                            <span class="cantidad-mascotas-propietario">

                                <?= count($mascotas) ?>

                                <?= count($mascotas) === 1
                                    ? 'mascota'
                                    : 'mascotas'
                                ?>

                            </span>

                        </div>

                        <?php if (!empty($mascotas)): ?>

                            <div class="grilla-mascotas-propietario">

                                <?php foreach ($mascotas as $mascota): ?>

                                    <article class="mascota-del-propietario <?= (int) $mascota['fallecido'] === 1 ? 'mascota-fallecida-propietario' : '' ?>">

                                        <div class="cabecera-mascota-propietario">

                                            <div class="icono-mascota-propietario">

                                                <i class="bi bi-heart-pulse"></i>

                                            </div>

                                            <div class="flex-grow-1">

                                                <div class="fila-nombre-mascota-propietario">
                                                    <h3>
                                                        <?= htmlspecialchars($mascota['nombre']) ?>
                                                    </h3>

                                                    <?php if ((int) $mascota['fallecido'] === 1): ?>
                                                        <span class="estado-fallecida-propietario">Fallecida</span>
                                                    <?php endif; ?>
                                                </div>

                                                <p>

                                                    <?= htmlspecialchars(
                                                        $mascota['nombre_especie']
                                                            ?? 'Especie no registrada'
                                                    ) ?>

                                                    <?php if (!empty($mascota['nombre_raza'])): ?>

                                                        ·

                                                        <?= htmlspecialchars(
                                                            $mascota['nombre_raza']
                                                        ) ?>

                                                    <?php endif; ?>

                                                </p>

                                            </div>

                                        </div>

                                        <div class="datos-mascota-propietario">

                                            <span>

                                                <i class="bi bi-gender-ambiguous"></i>

                                                <?= htmlspecialchars(
                                                    sexoMascotaPropietario(
                                                        $mascota['sexo']
                                                    )
                                                ) ?>

                                            </span>

                                            <span>

                                                <i class="bi bi-calendar3"></i>

                                                <?= htmlspecialchars(
                                                    edadMascotaPropietario(
                                                        $mascota['fecha_nacimiento']
                                                    )
                                                ) ?>

                                            </span>

                                        </div>

                                        <div class="resumen-mascota-propietario">

                                            <div>

                                                <strong>
                                                    <?= (int) $mascota['cantidad_consultas'] ?>
                                                </strong>

                                                <span>
                                                    Consultas
                                                </span>

                                            </div>

                                            <div>

                                                <strong>
                                                    <?= (int) $mascota['cantidad_vacunas'] ?>
                                                </strong>

                                                <span>
                                                    Vacunas
                                                </span>

                                            </div>

                                        </div>

                                        <?php if (!empty($mascota['proximo_turno'])): ?>

                                            <div class="proximo-turno-mascota-propietario">

                                                <i class="bi bi-calendar-check"></i>

                                                Próximo turno:

                                                <strong>

                                                    <?= date(
                                                        'd/m/Y H:i',
                                                        strtotime(
                                                            $mascota['proximo_turno']
                                                        )
                                                    ) ?>

                                                </strong>

                                            </div>

                                        <?php endif; ?>

                                        <a
                                            href="ficha_mascota.php?id_mascota=<?= (int) $mascota['id_mascota'] ?>"
                                            class="btn-ver-mascota-propietario">

                                            Ver ficha de la mascota

                                            <i class="bi bi-arrow-right"></i>

                                        </a>

                                    </article>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="estado-vacio-propietario-pequeno">

                                <i class="bi bi-heart-pulse"></i>

                                <div>

                                    <strong>
                                        Sin mascotas registradas
                                    </strong>

                                    <span>
                                        Este propietario todavía no tiene mascotas asociadas.
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>

                    </section>

                </div>

            </div>

        </div>

    </div>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
