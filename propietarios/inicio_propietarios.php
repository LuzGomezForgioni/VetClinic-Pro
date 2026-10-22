<?php

session_start();
require_once __DIR__ . '/../config/mascotas.php';

// Zona horaria de Argentina

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Verificar que haya un usuario logueado

if (empty($_SESSION['logueado']) || $_SESSION['tipo_usuario'] !== 'propietario') {

    header('Location: ../index.php');

    exit;

}

// Datos del propietario logueado

$nombre = $_SESSION['usuario_nombre'] ?? 'Propietario/a';

$apellido = $_SESSION['usuario_apellido'] ?? '';

// Día de la semana y meses en español

$dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

// Fecha actual

$fecha_actual = $dias[date('w')] . ' ' . date('j') . ' de ' .

    $meses[date('n') - 1] . ' de ' . date('Y');

$hora = (int) date('H');

if ($hora >= 5 && $hora < 12) {

    $saludo = 'Buen día';

} elseif ($hora >= 12 && $hora < 20) {

    $saludo = 'Buenas tardes';

} else {

    $saludo = 'Buenas noches';

}

// ============================================================

// CONEXIÓN A LA BASE DE DATOS

// ============================================================

include("../config/conexion.php");

// ============================================================

// ID DEL PROPIETARIO LOGUEADO

// ============================================================

$id_propietario = $_SESSION["usuario_id"];

// Mensajes pendientes del propietario (si la migración aún no está aplicada, no bloquear el inicio).
$mensajes_pendientes_inicio = 0;
try {
    $stmtMsg = $conn->prepare("SELECT COUNT(*) AS total FROM mensajes m
        INNER JOIN conversaciones c ON c.id_conversacion = m.id_conversacion
        WHERE c.id_propietario = ? AND m.remitente_tipo = 'veterinario' AND m.leido_en IS NULL");
    $stmtMsg->bind_param('i', $id_propietario);
    $stmtMsg->execute();
    $mensajes_pendientes_inicio = (int)($stmtMsg->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtMsg->close();
} catch (mysqli_sql_exception $e) {
    // Las tablas de mensajería se crean con migraciones/crear_mensajeria.sql.
    error_log('VetClinic Pro: ejecutar la migración de mensajería para mostrar pendientes.');
}


require_once __DIR__ . '/includes/notificaciones_propietario.php';

// ============================================================

// BUSCAR EL PRÓXIMO TURNO DEL PROPIETARIO

// ============================================================

// Buscamos el turno confirmado más próximo.

// Además traemos los datos de la mascota y del veterinario.

$sql_proximo_turno = "

    SELECT

        t.id_turno,

        t.fecha_hora,

        t.motivo,

        t.estado,

        m.id_mascota,

        m.nombre AS nombre_mascota,

        m.fecha_nacimiento,

        m.sexo,

        m.peso_kg,

        m.color,

        m.castrado,

        e.nombre_especie,

        r.nombre_raza,

        v.nombre AS nombre_veterinario,

        v.apellido AS apellido_veterinario,

        v.sexo AS sexo_veterinario

    FROM turnos t

    INNER JOIN mascotas m

        ON t.id_mascota = m.id_mascota

    INNER JOIN especies e

        ON m.id_especie = e.id_especie

    LEFT JOIN razas r

        ON m.id_raza = r.id_raza

    INNER JOIN veterinarios v

        ON t.id_veterinario = v.id_veterinario

    WHERE t.id_propietario = ?

      AND t.estado = 'confirmado'

      AND t.fecha_hora >= NOW()

    ORDER BY t.fecha_hora ASC

    LIMIT 1

";

$stmt = $conn->prepare($sql_proximo_turno);

$stmt->bind_param("i", $id_propietario);

$stmt->execute();

$resultado_turno = $stmt->get_result();

$proximo_turno = $resultado_turno->fetch_assoc();

$stmt->close();

// ============================================================

// SI NO HAY TURNO, BUSCAR LA PRIMERA MASCOTA DEL PROPIETARIO

// ============================================================

$mascota = null;

if ($proximo_turno) {

    // Si hay próximo turno, usamos esa mascota

    $mascota = $proximo_turno;

} else {

    // Si no hay turnos, mostramos la primera mascota registrada

    $sql_mascota = "

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

        LIMIT 1

    ";

    $stmt = $conn->prepare($sql_mascota);

    $stmt->bind_param("i", $id_propietario);

    $stmt->execute();

    $resultado_mascota = $stmt->get_result();

    $mascota = $resultado_mascota->fetch_assoc();

    $stmt->close();

}

// ============================================================

// CALCULAR EDAD DE LA MASCOTA

// ============================================================

$edad_mascota = 'Edad no registrada';

if (!empty($mascota["fecha_nacimiento"])) {

    $fecha_nacimiento = new DateTime($mascota["fecha_nacimiento"]);

    $fecha_hoy = new DateTime();

    $diferencia = $fecha_hoy->diff($fecha_nacimiento);

    if ($diferencia->y > 0) {

        $edad_mascota =

            $diferencia->y .

            ($diferencia->y == 1 ? ' año' : ' años');

    } elseif ($diferencia->m > 0) {

        $edad_mascota =

            $diferencia->m .

            ($diferencia->m == 1 ? ' mes' : ' meses');

    } else {

        $edad_mascota = 'Menos de 1 mes';

    }

}

// ============================================================

// FORMATEAR DATOS DE LA MASCOTA

// ============================================================

$nombre_mascota = $mascota["nombre_mascota"] ?? $mascota["nombre"] ?? 'Sin mascotas';

$especie_mascota = $mascota["nombre_especie"] ?? '';

$raza_mascota = $mascota["nombre_raza"] ?? 'Sin raza registrada';

$sexo_mascota = $mascota["sexo"] ?? 'Desconocido';

$peso_mascota = !empty($mascota["peso_kg"])

    ? $mascota["peso_kg"] . ' kg'

    : 'Peso no registrado';

// ============================================================

// ICONO SEGÚN EL SEXO

// ============================================================

if ($sexo_mascota === 'Macho') {

    $icono_sexo = 'bi-gender-male';

} elseif ($sexo_mascota === 'Hembra') {

    $icono_sexo = 'bi-gender-female';

} else {

    $icono_sexo = 'bi-question-circle';

}

// ============================================================

// FUNCIONES PARA VACUNACIONES E HISTORIA CLÍNICA

// ============================================================

function formatearFechaInicio(?string $fecha): string

{

    if (empty($fecha)) {

        return 'Sin fecha';

    }

    try {

        $fecha_objeto = new DateTime($fecha);

        return $fecha_objeto->format('d/m/Y');

    } catch (Exception $e) {

        return 'Sin fecha';

    }

}

// ============================================================

// CALCULAR ESTADO DE UNA VACUNA

// ============================================================

function estadoVacunaInicio(?string $fecha_proxima): array

{

    // Si no tiene próxima fecha cargada

    if (empty($fecha_proxima)) {

        return [

            'texto' => 'Sin fecha',

            'clase' => 'estado-pendiente',

            'icono' => 'bi-shield-fill-check',

            'vencida' => false

        ];

    }

    try {

        $hoy = new DateTime('today');

        $proxima = new DateTime($fecha_proxima);

        // La fecha ya pasó

        if ($proxima < $hoy) {

            return [

                'texto' => 'Vencida',

                'clase' => 'estado-vencida',

                'icono' => 'bi-shield-fill-exclamation',

                'vencida' => true

            ];

        }

        // Cantidad de días que faltan

        $diferencia = $hoy->diff($proxima);

        $dias_restantes = (int) $diferencia->days;

        // Si faltan 30 días o menos

        if ($dias_restantes <= 30) {

            return [

                'texto' => 'Próxima',

                'clase' => 'estado-alerta',

                'icono' => 'bi-shield-fill-check',

                'vencida' => false

            ];

        }

        // Si falta más de un mes

        return [

            'texto' => 'Al día',

            'clase' => 'estado-ok',

            'icono' => 'bi-shield-fill-check',

            'vencida' => false

        ];

    } catch (Exception $e) {

        return [

            'texto' => 'Sin fecha',

            'clase' => 'estado-pendiente',

            'icono' => 'bi-shield-fill-check',

            'vencida' => false

        ];

    }

}

// ============================================================

// VACUNACIONES E HISTORIAL PARA LA MASCOTA DEL INICIO

// ============================================================

$vacunas_inicio = [];

$ultimas_consultas = [];

// Solamente hacemos las consultas si existe una mascota

if (!empty($mascota['id_mascota'])) {

    $id_mascota_inicio = (int) $mascota['id_mascota'];

    // ========================================================

    // ÚLTIMAS VACUNACIONES

    // ========================================================

    $sql_vacunas_inicio = "

        SELECT

            id_vacunacion,

            nombre_vacuna,

            fecha_aplicacion,

            fecha_proxima,

            observaciones

        FROM vacunaciones

        WHERE id_mascota = ?

        ORDER BY

            fecha_aplicacion DESC,

            id_vacunacion DESC

        LIMIT 4

    ";

    $stmt = $conn->prepare($sql_vacunas_inicio);

    $stmt->bind_param(

        "i",

        $id_mascota_inicio

    );

    $stmt->execute();

    $resultado_vacunas_inicio = $stmt->get_result();

    while ($fila = $resultado_vacunas_inicio->fetch_assoc()) {

        $vacunas_inicio[] = $fila;

    }

    $stmt->close();

    // ========================================================

    // ÚLTIMAS CONSULTAS DE HISTORIA CLÍNICA

    // ========================================================

    $sql_historial_inicio = "

        SELECT

            h.id_historia,

            h.fecha_consulta,

            h.motivo_consulta,

            v.nombre AS nombre_veterinario,

            v.apellido AS apellido_veterinario,

            v.sexo AS sexo_veterinario

        FROM historia_clinica h

        LEFT JOIN veterinarios v

            ON h.id_veterinario = v.id_veterinario

        WHERE h.id_mascota = ?

        ORDER BY

            h.fecha_consulta DESC,

            h.id_historia DESC

        LIMIT 4

    ";

    $stmt = $conn->prepare($sql_historial_inicio);

    $stmt->bind_param(

        "i",

        $id_mascota_inicio

    );

    $stmt->execute();

    $resultado_historial_inicio = $stmt->get_result();

    while ($fila = $resultado_historial_inicio->fetch_assoc()) {

        $ultimas_consultas[] = $fila;

    }

    $stmt->close();

}

// ============================================================

// DATOS DEL PRÓXIMO TURNO

// ============================================================

if ($proximo_turno) {

    $fecha_turno = new DateTime($proximo_turno["fecha_hora"]);

    $dias_turno = [

        'Domingo',

        'Lunes',

        'Martes',

        'Miércoles',

        'Jueves',

        'Viernes',

        'Sábado'

    ];

    $meses_turno = [

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

    // Fecha del turno

    $dia_turno =

        $dias_turno[(int)$fecha_turno->format('w')] .

        ' ' .

        $fecha_turno->format('j') .

        ' de ' .

        $meses_turno[(int)$fecha_turno->format('n') - 1];

    // Hora del turno

    $hora_turno = $fecha_turno->format('H:i') . ' hs';

    // ========================================================

    // NOMBRE DEL VETERINARIO

    // ========================================================

    if ($proximo_turno["sexo_veterinario"] === "F") {

        $tratamiento_veterinario = "Dra.";

    } else {

        $tratamiento_veterinario = "Dr.";

    }

    $veterinario_turno =

        $tratamiento_veterinario . ' ' .

        $proximo_turno["nombre_veterinario"] . ' ' .

        $proximo_turno["apellido_veterinario"];

    // ========================================================

    // MOTIVO DEL TURNO

    // ========================================================

    $motivo_turno = $proximo_turno["motivo"];

} else {

    $dia_turno = 'No tenés próximos turnos';

    $hora_turno = '';

    $veterinario_turno = '';

    $motivo_turno = '';

}

// ============================================================

// RECORDATORIOS DEL INICIO

// ============================================================

$recordatorios_inicio = [];

// ============================================================

// VACUNAS PRÓXIMAS O VENCIDAS

// ============================================================

$sql_alertas_vacunas = "

    SELECT

        v.id_vacunacion,

        v.nombre_vacuna,

        v.fecha_proxima,

        m.id_mascota,

        m.nombre AS nombre_mascota

    FROM vacunaciones v

    INNER JOIN mascotas m

        ON v.id_mascota = m.id_mascota

    WHERE m.id_propietario = ?

      AND v.fecha_proxima IS NOT NULL

      AND v.fecha_proxima <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)

    ORDER BY v.fecha_proxima ASC

    LIMIT 3

";

$stmt = $conn->prepare($sql_alertas_vacunas);

$stmt->bind_param(

    "i",

    $id_propietario

);

$stmt->execute();

$resultado_alertas_vacunas =

    $stmt->get_result();

$hoy_recordatorio =

    new DateTime('today');

while (

    $vacuna_alerta =

    $resultado_alertas_vacunas->fetch_assoc()

) {

    $fecha_proxima =

        new DateTime(

            $vacuna_alerta['fecha_proxima']

        );

    // ========================================================

    // VACUNA VENCIDA

    // ========================================================

    if ($fecha_proxima < $hoy_recordatorio) {

        $texto_recordatorio =

            $vacuna_alerta['nombre_vacuna'] .

            ' de ' .

            $vacuna_alerta['nombre_mascota'] .

            ' está vencida';

        $subtexto_recordatorio =

            'Venció el ' .

            $fecha_proxima->format('d/m/Y');

    }

    // ========================================================

    // VACUNA PRÓXIMA

    // ========================================================

    else {

        $diferencia =

            $hoy_recordatorio->diff(

                $fecha_proxima

            );

        $dias_faltantes =

            (int) $diferencia->days;

        if ($dias_faltantes === 0) {

            $cuando = 'vence hoy';

        } elseif ($dias_faltantes === 1) {

            $cuando = 'vence mañana';

        } else {

            $cuando =

                'vence en ' .

                $dias_faltantes .

                ' días';

        }

        $texto_recordatorio =

            $vacuna_alerta['nombre_vacuna'] .

            ' de ' .

            $vacuna_alerta['nombre_mascota'] .

            ' ' .

            $cuando;

        $subtexto_recordatorio =

            'Próxima dosis: ' .

            $fecha_proxima->format('d/m/Y');

    }

    // Guardamos el recordatorio

    $recordatorios_inicio[] = [

        'clase' => 'urgente',

        'icono' =>

        'bi-exclamation-circle-fill',

        'texto' =>

        $texto_recordatorio,

        'subtexto' =>

        $subtexto_recordatorio,

        'url' =>

        'vacunaciones.php?id_mascota=' .

            (int) $vacuna_alerta['id_mascota']

    ];

}

$stmt->close();

// ============================================================

// PRÓXIMO TURNO

// ============================================================

if ($proximo_turno) {

    $fecha_turno_recordatorio =

        new DateTime(

            $proximo_turno['fecha_hora']

        );

    $ahora_recordatorio =

        new DateTime();

    $diferencia_turno =

        $ahora_recordatorio->diff(

            $fecha_turno_recordatorio

        );

    $dias_para_turno =

        (int) $diferencia_turno->days;

    /*

        Mostrar solamente si el turno

        es dentro de los próximos 7 días.

    */

    if (

        $fecha_turno_recordatorio >= $ahora_recordatorio &&

        $dias_para_turno <= 7

    ) {

        $recordatorios_inicio[] = [

            'clase' => 'info',

            'icono' =>

            'bi-calendar-event',

            'texto' =>

            'Próximo turno de ' .

                $nombre_mascota .

                ': ' .

                $fecha_turno_recordatorio

                ->format('d/m'),

            'subtexto' =>

            $fecha_turno_recordatorio

                ->format('H:i') .

                ' hs · ' .

                $veterinario_turno,

            'url' =>

            '../turnos/inicio_turnos.php'

        ];

    }

}

// ============================================================

// MÁXIMO 4 RECORDATORIOS

// ============================================================

$recordatorios_inicio =

    array_slice(

        $recordatorios_inicio,

        0,

        4

    );

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inicio — Propietario · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet" />
    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet" />
    <!-- CSS -->
    <link rel="stylesheet" href="../estilos/inicio.css" />
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">
            <!-- =====================================================
         SIDEBAR
    ====================================================== -->
            <div class="col-12 col-lg-2 panel-lateral">
                <!-- Logo -->
                <a href="../propietarios/inicio_propietarios.php" class="logo-lateral">
                    <img src="../img/VetClinic Pro.png" alt="Logo VetClinic Pro" />
                    <div>
                        <div class="nombre-sistema">VetClinic Pro</div>
                        <div class="subtitulo-sistema">Portal del propietario</div>
                    </div>
                </a>
                <!-- Navegación -->
                <div class="seccion-nav">
                    Mi espacio
                </div>
                <a
                    href="inicio_propietarios.php"
                    class="item-menu activo">
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
                <a href="historial_clinico.php" class="item-menu">
                    <i class="bi bi-journal-medical"></i>
                    Historial clínico
                </a>
                <div class="seccion-nav">
                    Más
                </div>
                <a href="vacunaciones.php" class="item-menu">
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
                <!-- Usuario desde sesión -->
                <div class="usuario-lateral">
                    <div class="avatar-usuario">
                        <?= strtoupper(substr($nombre, 0, 1)) ?>
                    </div>
                    <div>
                        <div class="nombre-usuario">
                            <?= htmlspecialchars($nombre . ' ' . $apellido) ?>
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
                <!-- Barra superior -->
                <div class="barra-superior">
                    <div>
                        <p class="titulo-pagina">
                            <?= $saludo ?>, <?= htmlspecialchars($nombre) ?> 👋
                        </p>
                        <p class="fecha-hoy">
                            <?= $fecha_actual ?> · VetClinic Pro
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
             FILA SUPERIOR
             Mascota + Turno + Vacunas
        ================================================== -->
                <div class="row g-3 mb-4">
                    <!-- MASCOTA PRINCIPAL -->
                    <div class="col-12 col-lg-4">
                        <div class="tarjeta-mascota-principal">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div>
                                    <div class="nombre-mascota-hero">
                                        <?= htmlspecialchars($nombre_mascota) ?>
                                    </div>
                                    <div class="raza-mascota">
                                        <?= htmlspecialchars($raza_mascota) ?>
                                        ·
                                        <?= htmlspecialchars($edad_mascota) ?>
                                    </div>
                                </div>
                                <div class="avatar-mascota-hero">
                                    <?= iconoEspecieMascota($especie_mascota) ?>
                                </div>
                            </div>
                            <div class="dato-mascota">
                                <i class="bi <?= $icono_sexo ?>"></i>
                                <span>
                                    <?= htmlspecialchars($sexo_mascota) ?>
                                    ·
                                    <?= htmlspecialchars($peso_mascota) ?>
                                </span>
                            </div>
                            <?php if (!empty($mascota["color"])): ?>
                                <div class="dato-mascota">
                                    <i class="bi bi-palette"></i>
                                    <span>
                                        Color:
                                        <?= htmlspecialchars($mascota["color"]) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="dato-mascota">
                                <i class="bi bi-heart-pulse"></i>
                                <span>
                                    <?= htmlspecialchars($especie_mascota) ?>
                                </span>
                            </div>
                            <div class="dato-mascota">
                                <i class="bi bi-check-circle"></i>
                                <span>
                                    <?php if (!empty($mascota["castrado"])): ?>
                                        Castrado/a
                                    <?php else: ?>
                                        No castrado/a
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div
                                class="mt-3 pt-3 vc-inicio-propietarios-33d3bc6"
                                >
                                <a
                                    href="../mascotas/inicio_mascota.php"
                                    class="btn-secundario vc-inicio-propietarios-10e56a2"
                                    >
                                    <i class="bi bi-eye"></i>
                                    Ver ficha completa
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- PRÓXIMO TURNO -->
                    <div class="col-12 col-lg-4">
                        <div class="tarjeta h-100">
                            <div class="titulo-tarjeta">
                                <i class="bi bi-calendar-event"></i>
                                Próximo turno
                            </div>
                            <?php if ($proximo_turno): ?>
                                <div class="text-center py-2">
                                    <div
                                        class="icono-metrica icono-azul-oscuro mx-auto mb-3 vc-inicio-propietarios-792bd2d"
                                        >
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <div
                                        class="vc-inicio-propietarios-37dc9ec" >
                                        <?= htmlspecialchars($dia_turno) ?>
                                    </div>
                                    <div
                                        class="vc-inicio-propietarios-4117867" >
                                        <?= htmlspecialchars($hora_turno) ?>
                                    </div>
                                    <div
                                        class="vc-inicio-propietarios-47aa402" >
                                        <?= htmlspecialchars($veterinario_turno) ?>
                                        ·
                                        <?= htmlspecialchars($motivo_turno) ?>
                                    </div>
                                    <div
                                        class="vc-inicio-propietarios-45ce0e3" >
                                        Mascota:
                                        <strong>
                                            <?= htmlspecialchars($nombre_mascota) ?>
                                        </strong>
                                    </div>
                                </div>
                                <div
                                    class="mt-3 pt-3 vc-divisor-acciones"
                                    >
                                    <div class="d-flex gap-2">
                                        <a
                                            href="../turnos/editar_turno.php?id=<?= $proximo_turno["id_turno"] ?>"
                                            class="btn-secundario flex-grow-1 justify-content-center vc-inicio-propietarios-5e0faad"
                                            >
                                            <i class="bi bi-pencil"></i>
                                            Modificar
                                        </a>
                                        <a
                                            href="../turnos/inicio_turnos.php"
                                            class="btn-secundario flex-grow-1 justify-content-center vc-inicio-propietarios-b0506e8"
                                            >
                                            <i class="bi bi-x-circle"></i>
                                            Cancelar
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <div
                                        class="icono-metrica icono-azul-oscuro mx-auto mb-3 vc-inicio-propietarios-792bd2d"
                                        >
                                        <i class="bi bi-calendar-x"></i>
                                    </div>
                                    <div
                                        class="vc-inicio-propietarios-3c6965c" >
                                        No tenés próximos turnos
                                    </div>
                                    <div
                                        class="vc-inicio-propietarios-ecd7d17" >
                                        Podés solicitar un nuevo turno cuando quieras.
                                    </div>
                                    <div class="mt-3">
                                        <a
                                            href="../turnos/alta_turno.php"
                                            class="btn-principal justify-content-center">
                                            <i class="bi bi-calendar-plus"></i>
                                            Solicitar turno
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- VACUNACIÓN -->
                    <!-- =====================================================
     ESTADO DE VACUNACIÓN
====================================================== -->
                    <div class="col-12 col-lg-4">
                        <div class="tarjeta h-100">
                            <div class="titulo-tarjeta">
                                <i class="bi bi-shield-check"></i>
                                Estado de vacunación
                            </div>
                            <div
                                class="vc-inicio-propietarios-c99cf0a" >
                                <?= htmlspecialchars($nombre_mascota) ?>
                            </div>
                            <?php if (empty($mascota['id_mascota'])): ?>
                                <!-- No existe mascota -->
                                <div class="text-center py-4">
                                    <div
                                        class="icono-metrica icono-azul-claro mx-auto mb-3">
                                        <i class="bi bi-heart-pulse"></i>
                                    </div>
                                    <div
                                        class="vc-texto-destacado" >
                                        No hay mascotas registradas
                                    </div>
                                </div>
                            <?php elseif (empty($vacunas_inicio)): ?>
                                <!-- La mascota existe pero no tiene vacunas -->
                                <div class="text-center py-4">
                                    <div
                                        class="icono-metrica icono-azul-claro mx-auto mb-3">
                                        <i class="bi bi-shield-plus"></i>
                                    </div>
                                    <div
                                        class="vc-texto-destacado" >
                                        <?= htmlspecialchars($nombre_mascota) ?>
                                        todavía no tiene vacunas registradas
                                    </div>
                                    <div
                                        class="vc-inicio-propietarios-45ce0e3" >
                                        Las vacunas cargadas aparecerán acá.
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- VACUNAS REALES -->
                                <ul class="lista-vacunas">
                                    <?php foreach ($vacunas_inicio as $vacuna): ?>
                                        <?php

                                        $estado_vacuna = estadoVacunaInicio(

                                            $vacuna['fecha_proxima']

                                        );

                                        ?>
                                        <li class="item-vacuna">
                                            <!-- ICONO -->
                                            <div
                                                class="icono-vacuna"
                                                <?php if ($estado_vacuna['vencida']): ?>
                                                class="vc-inicio-propietarios-2f04701"
                                                <?php endif; ?>>
                                                <i
                                                    class="bi <?= htmlspecialchars(

                                                                    $estado_vacuna['icono']

                                                                ) ?>"></i>
                                            </div>
                                            <!-- INFORMACIÓN -->
                                            <div class="flex-grow-1">
                                                <div class="nombre-vacuna">
                                                    <?= htmlspecialchars(

                                                        $vacuna['nombre_vacuna']

                                                    ) ?>
                                                </div>
                                                <div class="fecha-vacuna">
                                                    <?php if (!empty($vacuna['fecha_proxima'])): ?>
                                                        <?php

                                                        $fecha_proxima = new DateTime(

                                                            $vacuna['fecha_proxima']

                                                        );

                                                        $hoy_vacuna = new DateTime('today');

                                                        ?>
                                                        <?php if ($fecha_proxima < $hoy_vacuna): ?>
                                                            Venció:
                                                        <?php else: ?>
                                                            Vence:
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars(

                                                            formatearFechaInicio(

                                                                $vacuna['fecha_proxima']

                                                            )

                                                        ) ?>
                                                    <?php else: ?>
                                                        Sin próxima dosis indicada
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <!-- ESTADO -->
                                            <span
                                                class="badge-estado <?= htmlspecialchars(

                                                                        $estado_vacuna['clase']

                                                                    ) ?>">
                                                <?= htmlspecialchars(

                                                    $estado_vacuna['texto']

                                                ) ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <!-- VER TODAS -->
                                <div class="mt-3">
                                    <a
                                        href="vacunaciones.php?id_mascota=<?= (int) $mascota['id_mascota'] ?>"
                                        class="btn-secundario w-100 justify-content-center vc-inicio-propietarios-5e0faad"
                                        >
                                        Ver vacunaciones completas
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- =================================================
             BOTONES DE ACCIÓN
        ================================================== -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <div class="tarjeta">
                                <div class="titulo-tarjeta">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    ¿Qué necesitás hacer hoy?
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <a
                                        href="../mascotas/inicio_mascota.php"
                                        class="btn-principal">
                                        <i class="bi bi-calendar-plus"></i>
                                        Ver Mis Mascotas
                                    </a>
                                    <a href="../turnos/alta_turno.php" class="btn-principal">
                                        <i class="bi bi-calendar-plus"></i> Solicitar turno
                                    </a>
                                    <a
                                        href="historial_clinico.php"
                                        class="btn-secundario">
                                        <i class="bi bi-journal-medical"></i>
                                        Ver historial clínico
                                    </a>
                                    <a
                                        href="vacunaciones.php"
                                        class="btn-secundario">
                                        <i class="bi bi-shield-plus"></i>
                                        Consultar vacunaciones
                                    </a>
                                    <span class="btn-secundario accion-no-disponible" title="Mensajería todavía no disponible" aria-disabled="true">
                                        <i class="bi bi-chat-dots"></i>
                                        Escribir a la veterinaria
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- =================================================
             HISTORIAL + RECORDATORIOS + MENSAJES
        ================================================== -->
                    <div class="row g-3">
                        <!-- =====================================================
     ÚLTIMAS CONSULTAS
====================================================== -->
                        <div class="col-12 col-lg-4">
                            <div class="tarjeta h-100">
                                <div class="titulo-tarjeta">
                                    <i class="bi bi-clock-history"></i>
                                    Últimas consultas
                                </div>
                                <div
                                    class="vc-inicio-propietarios-c99cf0a" >
                                    <?= htmlspecialchars($nombre_mascota) ?>
                                </div>
                                <?php if (empty($mascota['id_mascota'])): ?>
                                    <div class="text-center py-4">
                                        <div
                                            class="icono-metrica icono-azul-claro mx-auto mb-3">
                                            <i class="bi bi-journal-medical"></i>
                                        </div>
                                        <div
                                            class="vc-texto-destacado" >
                                            No hay mascotas registradas
                                        </div>
                                    </div>
                                <?php elseif (empty($ultimas_consultas)): ?>
                                    <!-- SIN HISTORIA CLÍNICA -->
                                    <div class="text-center py-4">
                                        <div
                                            class="icono-metrica icono-azul-claro mx-auto mb-3">
                                            <i class="bi bi-journal-medical"></i>
                                        </div>
                                        <div
                                            class="vc-texto-destacado" >
                                            <?= htmlspecialchars($nombre_mascota) ?>
                                            todavía no tiene consultas registradas
                                        </div>
                                        <div
                                            class="vc-inicio-propietarios-45ce0e3" >
                                            Cuando el veterinario cargue una consulta,
                                            aparecerá acá.
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <ul class="lista-historial">
                                        <?php foreach ($ultimas_consultas as $consulta): ?>
                                            <?php

                                            // Determinar Dr. o Dra.

                                            $sexo_veterinario = strtoupper(

                                                trim(

                                                    $consulta['sexo_veterinario'] ?? ''

                                                )

                                            );

                                            if ($sexo_veterinario === 'F') {

                                                $titulo_veterinario = 'Dra.';

                                            } else {

                                                $titulo_veterinario = 'Dr.';

                                            }

                                            $nombre_completo_veterinario = trim(

                                                $titulo_veterinario . ' ' .

                                                    ($consulta['nombre_veterinario'] ?? '') . ' ' .

                                                    ($consulta['apellido_veterinario'] ?? '')

                                            );

                                            ?>
                                            <li class="item-historial">
                                                <div class="icono-historial">
                                                    <i class="bi bi-stethoscope"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="tipo-registro">
                                                        <?= htmlspecialchars(

                                                            $consulta['motivo_consulta']

                                                                ?: 'Consulta veterinaria'

                                                        ) ?>
                                                    </div>
                                                    <div class="fecha-registro">
                                                        <?= htmlspecialchars(

                                                            formatearFechaInicio(

                                                                $consulta['fecha_consulta']

                                                            )

                                                        ) ?>
                                                        <?php if (!empty($consulta['nombre_veterinario'])): ?>
                                                            ·
                                                            <?= htmlspecialchars(

                                                                $nombre_completo_veterinario

                                                            ) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <!-- VER HISTORIAL COMPLETO -->
                                    <div class="mt-3">
                                        <a
                                            href="historial_clinico.php?id_mascota=<?= (int) $mascota['id_mascota'] ?>"
                                            class="btn-secundario w-100 justify-content-center vc-inicio-propietarios-5e0faad"
                                            >
                                            Ver historial completo
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- RECORDATORIOS -->
                        <div class="col-12 col-lg-4">
                            <div class="tarjeta h-100">
                                <div class="titulo-tarjeta">
                                    <i class="bi bi-bell-fill"></i>
                                    Recordatorios
                                </div>
                                <?php if (empty($recordatorios_inicio)): ?>
                                    <div class="item-recordatorio ok">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <div>
                                            <div class="texto-recordatorio">
                                                Todo al día
                                            </div>
                                            <div class="sub-recordatorio">
                                                No tenés recordatorios pendientes.
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <ul class="lista-recordatorios">
                                        <?php foreach ($recordatorios_inicio as $recordatorio): ?>
                                            <li
                                                class="item-recordatorio <?= htmlspecialchars(

                                                                                $recordatorio['clase']

                                                                            ) ?>">
                                                <i
                                                    class="bi <?= htmlspecialchars(

                                                                    $recordatorio['icono']

                                                                ) ?>"></i>
                                                <div class="flex-grow-1">
                                                    <div class="texto-recordatorio">
                                                        <?= htmlspecialchars(

                                                            $recordatorio['texto']

                                                        ) ?>
                                                    </div>
                                                    <div class="sub-recordatorio">
                                                        <?= htmlspecialchars(

                                                            $recordatorio['subtexto']

                                                        ) ?>
                                                    </div>
                                                    <a
                                                        href="<?= htmlspecialchars(

                                                                    $recordatorio['url']

                                                                ) ?>"
                                                        class="enlace-recordatorio">
                                                        Ver detalle
                                                        <i class="bi bi-arrow-right"></i>
                                                    </a>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- MENSAJES -->
                        <div class="col-12 col-lg-4">
                            <div class="tarjeta h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="titulo-tarjeta mb-0">
                                        <i class="bi bi-chat-left-dots"></i>
                                        Mensajes
                                    </div>
                                    <a href="mensajes.php" class="btn-secundario vc-etiqueta-compacta">
                                        Ver todos <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                                <p class="text-muted mb-0">
                                    <?php if ($mensajes_pendientes_inicio > 0): ?>
                                        Tenés <strong><?= $mensajes_pendientes_inicio ?></strong> mensaje<?= $mensajes_pendientes_inicio === 1 ? '' : 's' ?> sin leer.
                                    <?php else: ?>
                                        Tus conversaciones con profesionales de tus turnos, en un mismo lugar.
                                    <?php endif; ?>
                                </p>
                                <div class="mt-3">
                                    <a href="mensajes.php" class="btn-principal w-100 justify-content-center vc-inicio-propietarios-5e0faad">
                                        <i class="bi bi-chat-dots"></i> Enviar mensaje
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="../mensajeria/notificaciones.js" defer></script>
</body>
</html>