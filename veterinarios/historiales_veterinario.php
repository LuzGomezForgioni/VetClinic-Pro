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
// FILTROS
// ======================================================

$busqueda = trim($_GET['buscar'] ?? '');
$id_mascota_filtro = (int) ($_GET['id_mascota'] ?? 0);
$fecha_desde = trim($_GET['fecha_desde'] ?? '');
$fecha_hasta = trim($_GET['fecha_hasta'] ?? '');

// Validar fechas simples.
// Si vienen con un formato incorrecto, se ignoran.
if (
    $fecha_desde !== '' &&
    DateTime::createFromFormat('Y-m-d', $fecha_desde) === false
) {
    $fecha_desde = '';
}

if (
    $fecha_hasta !== '' &&
    DateTime::createFromFormat('Y-m-d', $fecha_hasta) === false
) {
    $fecha_hasta = '';
}

// ======================================================
// MASCOTAS PARA EL SELECT
// ======================================================

$mascotas_filtro = [];

$sql_mascotas = "
    SELECT
        m.id_mascota,
        m.nombre,
        p.nombre AS nombre_propietario,
        p.apellido AS apellido_propietario

    FROM mascotas m

    INNER JOIN propietarios p
        ON m.id_propietario = p.id_propietario

    ORDER BY
        m.nombre ASC,
        p.apellido ASC,
        p.nombre ASC
";

$resultado_mascotas = $conn->query($sql_mascotas);

if ($resultado_mascotas) {

    while ($fila = $resultado_mascotas->fetch_assoc()) {
        $mascotas_filtro[] = $fila;
    }
}

// ======================================================
// CONSULTA GENERAL DEL HISTORIAL
// ======================================================

$sql = "
    SELECT
        hc.id_historia,
        hc.id_turno,
        hc.id_mascota,
        hc.id_veterinario,
        hc.fecha_consulta,
        hc.motivo_consulta,
        hc.diagnostico,
        hc.tratamiento,
        hc.medicacion,
        hc.peso_registro,
        hc.proximos_pasos,

        m.nombre AS nombre_mascota,

        e.nombre_especie,
        r.nombre_raza,

        p.id_propietario,
        p.nombre AS nombre_propietario,
        p.apellido AS apellido_propietario,

        v.nombre AS nombre_veterinario,
        v.apellido AS apellido_veterinario,
        v.sexo AS sexo_veterinario,

        t.fecha_hora

    FROM historia_clinica hc

    INNER JOIN mascotas m
        ON hc.id_mascota = m.id_mascota

    INNER JOIN propietarios p
        ON m.id_propietario = p.id_propietario

    INNER JOIN veterinarios v
        ON hc.id_veterinario = v.id_veterinario

    LEFT JOIN especies e
        ON m.id_especie = e.id_especie

    LEFT JOIN razas r
        ON m.id_raza = r.id_raza

    LEFT JOIN turnos t
        ON hc.id_turno = t.id_turno

    WHERE 1 = 1
";

$tipos = "";
$parametros = [];

// ======================================================
// BUSCAR TEXTO
// ======================================================

if ($busqueda !== '') {

    $sql .= "
        AND (
            m.nombre LIKE ?
            OR p.nombre LIKE ?
            OR p.apellido LIKE ?
            OR CONCAT(p.nombre, ' ', p.apellido) LIKE ?
            OR hc.motivo_consulta LIKE ?
            OR hc.diagnostico LIKE ?
            OR hc.tratamiento LIKE ?
        )
    ";

    $texto_busqueda = '%' . $busqueda . '%';

    for ($i = 0; $i < 7; $i++) {
        $tipos .= "s";
        $parametros[] = $texto_busqueda;
    }
}

// ======================================================
// FILTRAR POR MASCOTA
// ======================================================

if ($id_mascota_filtro > 0) {

    $sql .= "
        AND hc.id_mascota = ?
    ";

    $tipos .= "i";
    $parametros[] = $id_mascota_filtro;
}

// ======================================================
// FILTRAR POR FECHA
// ======================================================

if ($fecha_desde !== '') {

    $sql .= "
        AND hc.fecha_consulta >= ?
    ";

    $tipos .= "s";
    $parametros[] = $fecha_desde;
}

if ($fecha_hasta !== '') {

    $sql .= "
        AND hc.fecha_consulta <= ?
    ";

    $tipos .= "s";
    $parametros[] = $fecha_hasta;
}

$sql .= "
    ORDER BY
        COALESCE(
            t.fecha_hora,
            CONCAT(hc.fecha_consulta, ' 00:00:00')
        ) DESC,
        hc.id_historia DESC
";

// ======================================================
// EJECUTAR CONSULTA
// ======================================================

$stmt = $conn->prepare($sql);

if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}

$stmt->execute();

$resultado = $stmt->get_result();

$historias = [];

while ($fila = $resultado->fetch_assoc()) {
    $historias[] = $fila;
}

$stmt->close();

// ======================================================
// FUNCIONES
// ======================================================

function fechaHistorial(?string $fecha): string
{
    if (empty($fecha)) {
        return 'Sin fecha';
    }

    return date('d/m/Y', strtotime($fecha));
}

function horaHistorial(?string $fecha_hora): string
{
    if (empty($fecha_hora)) {
        return '';
    }

    return date('H:i', strtotime($fecha_hora)) . ' hs';
}

function nombreVeterinarioHistorial(array $historia): string
{
    $sexo = strtoupper(
        trim($historia['sexo_veterinario'] ?? '')
    );

    $titulo = $sexo === 'F'
        ? 'Dra.'
        : 'Dr.';

    return trim(
        $titulo . ' ' .
        ($historia['nombre_veterinario'] ?? '') . ' ' .
        ($historia['apellido_veterinario'] ?? '')
    );
}

function textoSeguro(?string $texto, string $alternativa = 'No registrado'): string
{
    $texto = trim((string) $texto);

    return $texto !== ''
        ? $texto
        : $alternativa;
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
        Historial clínico · VetClinic Pro
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
        href="../estilos/historiales_veterinario.css">
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
                href="historiales_veterinario.php"
                class="item-menu activo">
                <i class="bi bi-journal-medical"></i>
                Historial clínico
            </a>
            <div class="seccion-nav">
                Gestión
            </div>
            <a
                href="propietarios_veterinario.php"
                class="item-menu">
                <i class="bi bi-people"></i>
                Propietarios
            </a>
            <a
                href="vacunaciones_veterinario.php"
                class="item-menu">
                <i class="bi bi-shield-plus"></i>
                Vacunaciones
            </a>
            <a href="mensajes.php" class="item-menu"><i class="bi bi-chat-dots"></i>
                Mensajes</a>                <a href="estadisticas.php" class="item-menu">
                    <i class="bi bi-graph-up-arrow"></i> Estadísticas
                </a>

            <div class="seccion-nav">
                Configuración
            </div>
            <a href="configuracion_veterinario.php" class="item-menu">
                    <i class="bi bi-gear"></i> Mi configuración
                </a>
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
            <!-- BARRA SUPERIOR -->
            <div class="barra-superior">
                <div>
                    <p class="titulo-pagina">
                        Historial clínico
                    </p>
                    <p class="fecha-hoy">
                        Consultá las visitas registradas de los pacientes.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a
                        href="inicio_veterinario.php"
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
            <!-- ================================================== -->
            <!-- ENCABEZADO -->
            <!-- ================================================== -->
            <div class="encabezado-historial-general mb-4">
                <div>
                    <h2 class="titulo-pagina">
                        Consultas registradas
                    </h2>
                    <p class="fecha-hoy">
                        Buscá por mascota, propietario, motivo o diagnóstico.
                    </p>
                </div>
                <div class="contador-historias">
                    <i class="bi bi-journal-medical"></i>
                    <strong>
                        <?= count($historias) ?>
                    </strong>
                    <span>
                        <?= count($historias) === 1
                            ? 'consulta'
                            : 'consultas'
                        ?>
                    </span>
                </div>
            </div>
            <!-- ================================================== -->
            <!-- FILTROS -->
            <!-- ================================================== -->
            <section class="tarjeta filtros-historial mb-4">
                <div class="titulo-tarjeta mb-3">
                    <i class="bi bi-funnel"></i>
                    Buscar y filtrar
                </div>
                <form
                    method="GET"
                    action="historiales_veterinario.php">
                    <div class="grilla-filtros-historial">
                        <div class="campo-filtro-historial campo-busqueda-historial">
                            <label for="buscar">
                                Buscar
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="buscar"
                                    name="buscar"
                                    placeholder="Mascota, propietario, motivo o diagnóstico"
                                    value="<?= htmlspecialchars($busqueda) ?>">
                            </div>
                        </div>
                        <div class="campo-filtro-historial">
                            <label for="id_mascota">
                                Mascota
                            </label>
                            <select
                                class="form-select"
                                id="id_mascota"
                                name="id_mascota">
                                <option value="0">
                                    Todas las mascotas
                                </option>
                                <?php foreach ($mascotas_filtro as $mascota): ?>
                                    <option
                                        value="<?= (int) $mascota['id_mascota'] ?>"
                                        <?= $id_mascota_filtro === (int) $mascota['id_mascota']
                                            ? 'selected'
                                            : ''
                                        ?>>
                                        <?= htmlspecialchars(
                                            $mascota['nombre'] .
                                            ' — ' .
                                            $mascota['nombre_propietario'] .
                                            ' ' .
                                            $mascota['apellido_propietario']
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo-filtro-historial">
                            <label for="fecha_desde">
                                Desde
                            </label>
                            <input
                                type="date"
                                class="form-control"
                                id="fecha_desde"
                                name="fecha_desde"
                                value="<?= htmlspecialchars($fecha_desde) ?>">
                        </div>
                        <div class="campo-filtro-historial">
                            <label for="fecha_hasta">
                                Hasta
                            </label>
                            <input
                                type="date"
                                class="form-control"
                                id="fecha_hasta"
                                name="fecha_hasta"
                                value="<?= htmlspecialchars($fecha_hasta) ?>">
                        </div>
                    </div>
                    <div class="acciones-filtros-historial">
                        <button
                            type="submit"
                            class="btn-buscar-historial">
                            <i class="bi bi-search"></i>
                            Buscar
                        </button>
                        <?php if (
                            $busqueda !== '' ||
                            $id_mascota_filtro > 0 ||
                            $fecha_desde !== '' ||
                            $fecha_hasta !== ''
                        ): ?>
                            <a
                                href="historiales_veterinario.php"
                                class="btn-limpiar-historial">
                                <i class="bi bi-x-circle"></i>
                                Limpiar filtros
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>
            <!-- ================================================== -->
            <!-- RESULTADOS -->
            <!-- ================================================== -->
            <?php if (!empty($historias)): ?>
                <div class="lista-historial-general">
                    <?php foreach ($historias as $historia): ?>
                        <article class="tarjeta tarjeta-consulta-historial">
                            <div class="cabecera-consulta-historial">
                                <div class="fecha-consulta-historial">
                                    <div class="dia-consulta">
                                        <?= date(
                                            'd',
                                            strtotime(
                                                $historia['fecha_consulta']
                                            )
                                        ) ?>
                                    </div>
                                    <div>
                                        <div class="mes-anio-consulta">
                                            <?= date(
                                                'm/Y',
                                                strtotime(
                                                    $historia['fecha_consulta']
                                                )
                                            ) ?>
                                        </div>
                                        <?php if (!empty($historia['fecha_hora'])): ?>
                                            <div class="hora-consulta">
                                                <?= htmlspecialchars(
                                                    horaHistorial(
                                                        $historia['fecha_hora']
                                                    )
                                                ) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="datos-principales-consulta">
                                    <div class="fila-nombre-consulta">
                                        <a
                                            href="ficha_mascota.php?id_mascota=<?= (int) $historia['id_mascota'] ?>"
                                            class="nombre-mascota-historial">
                                            <i class="bi bi-heart-pulse"></i>
                                            <?= htmlspecialchars(
                                                $historia['nombre_mascota']
                                            ) ?>
                                        </a>
                                        <span class="especie-raza-historial">
                                            <?= htmlspecialchars(
                                                $historia['nombre_especie']
                                                    ?? 'Especie no registrada'
                                            ) ?>
                                            <?php if (!empty($historia['nombre_raza'])): ?>
                                                ·
                                                <?= htmlspecialchars(
                                                    $historia['nombre_raza']
                                                ) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="meta-consulta-historial">
                                        <a
                                            href="ficha_propietario.php?id_propietario=<?= (int) $historia['id_propietario'] ?>">
                                            <i class="bi bi-person"></i>
                                            <?= htmlspecialchars(
                                                $historia['nombre_propietario'] .
                                                ' ' .
                                                $historia['apellido_propietario']
                                            ) ?>
                                        </a>
                                        <span>
                                            <i class="bi bi-person-badge"></i>
                                            <?= htmlspecialchars(
                                                nombreVeterinarioHistorial(
                                                    $historia
                                                )
                                            ) ?>
                                        </span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="btn-detalle-consulta"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#consulta-<?= (int) $historia['id_historia'] ?>"
                                    aria-expanded="false"
                                    aria-controls="consulta-<?= (int) $historia['id_historia'] ?>">
                                    Ver consulta completa
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </div>
                            <div class="resumen-consulta-historial">
                                <div>
                                    <span class="etiqueta-resumen">
                                        Motivo
                                    </span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            textoSeguro(
                                                $historia['motivo_consulta'],
                                                'Sin motivo registrado'
                                            )
                                        ) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span class="etiqueta-resumen">
                                        Diagnóstico
                                    </span>
                                    <p>
                                        <?= htmlspecialchars(
                                            textoSeguro(
                                                $historia['diagnostico']
                                            )
                                        ) ?>
                                    </p>
                                </div>
                            </div>
                            <!-- DETALLE DESPLEGABLE -->
                            <div
                                class="collapse"
                                id="consulta-<?= (int) $historia['id_historia'] ?>">
                                <div class="detalle-consulta-completa">
                                    <div class="grilla-detalle-consulta">
                                        <div class="dato-detalle-consulta">
                                            <span>
                                                Fecha
                                            </span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    fechaHistorial(
                                                        $historia['fecha_consulta']
                                                    )
                                                ) ?>
                                            </strong>
                                        </div>
                                        <div class="dato-detalle-consulta">
                                            <span>
                                                Veterinario/a
                                            </span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    nombreVeterinarioHistorial(
                                                        $historia
                                                    )
                                                ) ?>
                                            </strong>
                                        </div>
                                        <div class="dato-detalle-consulta">
                                            <span>
                                                Peso registrado
                                            </span>
                                            <strong>
                                                <?php if (
                                                    $historia['peso_registro'] !== null &&
                                                    $historia['peso_registro'] !== ''
                                                ): ?>
                                                    <?= htmlspecialchars(
                                                        rtrim(
                                                            rtrim(
                                                                (string) $historia['peso_registro'],
                                                                '0'
                                                            ),
                                                            '.'
                                                        )
                                                    ) ?> kg
                                                <?php else: ?>
                                                    No registrado
                                                <?php endif; ?>
                                            </strong>
                                        </div>
                                    </div>
                                    <div class="bloque-texto-consulta">
                                        <span>
                                            Motivo de consulta
                                        </span>
                                        <p>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    textoSeguro(
                                                        $historia['motivo_consulta'],
                                                        'Sin motivo registrado'
                                                    )
                                                )
                                            ) ?>
                                        </p>
                                    </div>
                                    <div class="bloque-texto-consulta">
                                        <span>
                                            Diagnóstico
                                        </span>
                                        <p>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    textoSeguro(
                                                        $historia['diagnostico']
                                                    )
                                                )
                                            ) ?>
                                        </p>
                                    </div>
                                    <div class="bloque-texto-consulta">
                                        <span>
                                            Tratamiento
                                        </span>
                                        <p>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    textoSeguro(
                                                        $historia['tratamiento']
                                                    )
                                                )
                                            ) ?>
                                        </p>
                                    </div>
                                    <div class="bloque-texto-consulta">
                                        <span>
                                            Medicación
                                        </span>
                                        <p>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    textoSeguro(
                                                        $historia['medicacion']
                                                    )
                                                )
                                            ) ?>
                                        </p>
                                    </div>
                                    <div class="bloque-texto-consulta">
                                        <span>
                                            Próximos pasos
                                        </span>
                                        <p>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    textoSeguro(
                                                        $historia['proximos_pasos']
                                                    )
                                                )
                                            ) ?>
                                        </p>
                                    </div>
                                    <div class="pie-detalle-consulta">
                                        <a
                                            href="ficha_mascota.php?id_mascota=<?= (int) $historia['id_mascota'] ?>"
                                            class="btn-ficha-desde-historial">
                                            <i class="bi bi-heart-pulse"></i>
                                            Ver ficha de <?= htmlspecialchars($historia['nombre_mascota']) ?>
                                        </a>
                                        <a
                                            href="ficha_propietario.php?id_propietario=<?= (int) $historia['id_propietario'] ?>"
                                            class="btn-propietario-desde-historial">
                                            <i class="bi bi-person"></i>
                                            Ver propietario
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="tarjeta estado-vacio-historial">
                    <div class="icono-vacio-historial">
                        <i class="bi bi-journal-x"></i>
                    </div>
                    <div>
                        <h3>
                            No encontramos consultas
                        </h3>
                        <p>
                            <?php if (
                                $busqueda !== '' ||
                                $id_mascota_filtro > 0 ||
                                $fecha_desde !== '' ||
                                $fecha_hasta !== ''
                            ): ?>
                                No hay historias clínicas que coincidan con los filtros seleccionados.
                            <?php else: ?>
                                Todavía no hay historias clínicas registradas.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Bootstrap JS -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="../mensajeria/notificaciones.js" defer></script>
</body>
</html>
