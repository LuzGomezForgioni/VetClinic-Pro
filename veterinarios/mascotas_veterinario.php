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

$mensaje_mascotas = $_SESSION['mensaje_mascotas'] ?? '';
$tipo_mensaje_mascotas = $_SESSION['tipo_mensaje_mascotas'] ?? '';
unset($_SESSION['mensaje_mascotas'], $_SESSION['tipo_mensaje_mascotas']);

$dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$fecha_actual = $dias[date('w')] . ' ' . date('j') . ' de ' . $meses[date('n') - 1] . ' de ' . date('Y');

$busqueda = trim($_GET['buscar'] ?? '');

$sql = "
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
        p.id_propietario,
        p.nombre AS nombre_propietario,
        p.apellido AS apellido_propietario,
        p.email AS email_propietario,
        p.telefono AS telefono_propietario,
        (
            SELECT COUNT(*)
            FROM historia_clinica hc
            WHERE hc.id_mascota = m.id_mascota
        ) AS cantidad_consultas,
        (
            SELECT COUNT(*)
            FROM vacunaciones va
            WHERE va.id_mascota = m.id_mascota
        ) AS cantidad_vacunas
    FROM mascotas m
    INNER JOIN propietarios p
        ON m.id_propietario = p.id_propietario
    LEFT JOIN especies e
        ON m.id_especie = e.id_especie
    LEFT JOIN razas r
        ON m.id_raza = r.id_raza
";

if ($busqueda !== '') {
    $sql .= "
        WHERE
            m.nombre LIKE ?
            OR p.nombre LIKE ?
            OR p.apellido LIKE ?
            OR CONCAT(p.nombre, ' ', p.apellido) LIKE ?
            OR e.nombre_especie LIKE ?
            OR r.nombre_raza LIKE ?
    ";
}

$sql .= " ORDER BY m.fallecido ASC, m.nombre ASC, p.apellido ASC, p.nombre ASC";

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

$mascotas = [];
while ($fila = $resultado->fetch_assoc()) {
    $mascotas[] = $fila;
}
$stmt->close();

function calcularEdadMascota(?string $fecha_nacimiento): string
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

function textoSexoMascota(?string $sexo): string
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mascotas — Veterinario · VetClinic Pro</title>

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
                    <p class="titulo-pagina">Mascotas</p>
                    <p class="fecha-hoy"><?= htmlspecialchars($fecha_actual) ?></p>
                </div>

                <div class="d-flex gap-2">
                    <a href="#" class="icono-barra" title="Notificaciones">
                        <i class="bi bi-bell"></i>
                    </a>
                    <a href="#" class="icono-barra" title="Configuración">
                        <i class="bi bi-gear"></i>
                    </a>
                    <a href="../logout.php" class="icono-barra" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>

            <?php if ($mensaje_mascotas !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($tipo_mensaje_mascotas) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje_mascotas) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="encabezado-mascotas">
                <div>
                    <h2 class="titulo-pagina">Pacientes registrados</h2>
                    <p class="fecha-hoy">
                        Consultá las mascotas y el propietario responsable de cada una.
                    </p>
                </div>

                <span class="contador-mascotas">
                    <i class="bi bi-heart-pulse"></i>
                    <?= count($mascotas) ?>
                    <?= count($mascotas) === 1 ? 'mascota' : 'mascotas' ?>
                </span>
            </div>

            <div class="tarjeta tarjeta-buscador-mascotas mb-4">
                <form method="GET" action="mascotas_veterinario.php">

                    <label for="buscar" class="etiqueta-buscador">
                        <i class="bi bi-search"></i>
                        Buscar mascota
                    </label>

                    <div class="fila-buscador">

                        <input
                            type="text"
                            id="buscar"
                            name="buscar"
                            class="form-control"
                            placeholder="Nombre de mascota, propietario, especie o raza"
                            value="<?= htmlspecialchars($busqueda) ?>">

                        <button type="submit" class="btn-buscar-mascota">
                            <i class="bi bi-search"></i>
                            Buscar
                        </button>

                        <?php if ($busqueda !== ''): ?>
                            <a href="mascotas_veterinario.php" class="btn-limpiar-mascota">
                                <i class="bi bi-x-circle"></i>
                                Limpiar
                            </a>
                        <?php endif; ?>

                    </div>
                </form>
            </div>

            <?php if (!empty($mascotas)): ?>

                <div class="grilla-mascotas">

                    <?php foreach ($mascotas as $mascota): ?>

                        <article class="tarjeta-mascota-veterinario <?= (int) $mascota['fallecido'] === 1 ? 'mascota-fallecida' : '' ?>">

                            <div class="cabecera-mascota-veterinario">
                                <div class="avatar-paciente">
                                    <i class="bi bi-heart-pulse"></i>
                                </div>

                                <div class="flex-grow-1">
                                    <div class="fila-nombre-mascota">
                                        <div class="nombre-paciente-listado">
                                            <?= htmlspecialchars($mascota['nombre']) ?>
                                        </div>

                                        <?php if ((int) $mascota['fallecido'] === 1): ?>
                                            <span class="estado-fallecida-listado">
                                                <i class="bi bi-heartbreak"></i>
                                                Fallecida
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="detalle-paciente-listado">
                                        <?= htmlspecialchars($mascota['nombre_especie'] ?? 'Especie no registrada') ?>

                                        <?php if (!empty($mascota['nombre_raza'])): ?>
                                            · <?= htmlspecialchars($mascota['nombre_raza']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="datos-mascota-listado">

                                <span>
                                    <i class="bi bi-gender-ambiguous"></i>
                                    <?= htmlspecialchars(textoSexoMascota($mascota['sexo'])) ?>
                                </span>

                                <span>
                                    <i class="bi bi-calendar3"></i>
                                    <?= htmlspecialchars(calcularEdadMascota($mascota['fecha_nacimiento'])) ?>
                                </span>

                                <?php if ($mascota['peso_kg'] !== null && $mascota['peso_kg'] !== ''): ?>
                                    <span>
                                        <i class="bi bi-speedometer2"></i>
                                        <?= htmlspecialchars(rtrim(rtrim((string) $mascota['peso_kg'], '0'), '.')) ?> kg
                                    </span>
                                <?php endif; ?>

                            </div>

                            <div class="propietario-mascota">
                                <div class="icono-propietario">
                                    <i class="bi bi-person"></i>
                                </div>

                                <div>
                                    <div class="etiqueta-propietario">Propietario</div>
                                    <div class="nombre-propietario">
                                        <?= htmlspecialchars(
                                            $mascota['nombre_propietario'] . ' ' .
                                            $mascota['apellido_propietario']
                                        ) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="resumen-clinico-mascota">
                                <div>
                                    <strong><?= (int) $mascota['cantidad_consultas'] ?></strong>
                                    <span>Consultas</span>
                                </div>

                                <div>
                                    <strong><?= (int) $mascota['cantidad_vacunas'] ?></strong>
                                    <span>Vacunas</span>
                                </div>
                            </div>

                            <div class="acciones-mascota-veterinario">

                                <a
                                    href="ficha_mascota.php?id_mascota=<?= (int) $mascota['id_mascota'] ?>"
                                    class="btn-ficha-mascota">
                                    Ver ficha
                                    <i class="bi bi-arrow-right"></i>
                                </a>

                                <a
                                    href="turnos_veterinario.php?mascota=<?= urlencode($mascota['nombre']) ?>"
                                    class="btn-turnos-mascota"
                                    title="Ver turnos de esta mascota">
                                    <i class="bi bi-calendar-check"></i>
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="tarjeta estado-vacio-mascotas">
                    <div class="icono-vacio-mascotas">
                        <i class="bi bi-search"></i>
                    </div>

                    <h3>No encontramos mascotas</h3>

                    <p>
                        <?php if ($busqueda !== ''): ?>
                            Probá con otro nombre, propietario, especie o raza.
                        <?php else: ?>
                            Todavía no hay mascotas registradas en el sistema.
                        <?php endif; ?>
                    </p>

                    <?php if ($busqueda !== ''): ?>
                        <a href="mascotas_veterinario.php" class="btn-limpiar-mascota">
                            Limpiar búsqueda
                        </a>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
