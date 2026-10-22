<?php
session_start();

include('../config/conexion.php');
require_once '../config/proteger_acceso_veterinario.php';
require_once '../config/seguridad.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
}

// Verificar que esté logueado como veterinario
if (
    empty($_SESSION['logueado']) ||
    $_SESSION['tipo_usuario'] !== 'veterinario'
) {
    header('Location: ../index.php');
    exit();
}

// El ID del veterinario sale de la sesión
$id_veterinario = $_SESSION['usuario_id'];
$nombre = $_SESSION['usuario_nombre'] ?? '';
$apellido = $_SESSION['usuario_apellido'] ?? '';
$mensaje = '';
$tipo_mensaje = '';

// Días de la semana
$dias = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo',
];

/* --------------------------------------------------------------------------
   VALIDAR HORAS
-------------------------------------------------------------------------- */
function horaEnIntervaloDe30(string $hora): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):(?:00|30)$/', $hora) === 1;
}

/* --------------------------------------------------------------------------
   GUARDAR HORARIOS
-------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $horarios_recibidos = $_POST['horarios'] ?? [];
    $horarios_validados = [];

    try {
        // Primero validamos todo, sin modificar la base de datos.
        foreach ($dias as $numero_dia => $nombre_dia) {
            if (!isset($horarios_recibidos[$numero_dia])) {
                continue;
            }

            $bloques = $horarios_recibidos[$numero_dia];

            if (!is_array($bloques)) {
                continue;
            }

            foreach ($bloques as $bloque) {
                $hora_desde = trim($bloque['desde'] ?? '');
                $hora_hasta = trim($bloque['hasta'] ?? '');

                // Si ambos campos están vacíos, ignoramos el bloque.
                if ($hora_desde === '' && $hora_hasta === '') {
                    continue;
                }

                // Si falta alguno de los horarios, no se puede guardar.
                if ($hora_desde === '' || $hora_hasta === '') {
                    throw new InvalidArgumentException(
                        'Completá la hora de inicio y de finalización del ' . strtolower($nombre_dia) . '.'
                    );
                }

                // Los turnos del sistema son de 30 minutos.
                if (
                    !horaEnIntervaloDe30($hora_desde) ||
                    !horaEnIntervaloDe30($hora_hasta)
                ) {
                    throw new InvalidArgumentException(
                        'Los horarios del ' . strtolower($nombre_dia) . ' deben comenzar o terminar en :00 o :30.'
                    );
                }

                // La hora de inicio debe ser anterior a la de finalización.
                if ($hora_desde >= $hora_hasta) {
                    throw new InvalidArgumentException(
                        'En ' . strtolower($nombre_dia) . ', la hora de inicio debe ser anterior a la hora de finalización.'
                    );
                }

                $horarios_validados[$numero_dia][] = [
                    'desde' => $hora_desde,
                    'hasta' => $hora_hasta,
                ];
            }

            // Ordenar los bloques del día y comprobar superposiciones.
            if (!empty($horarios_validados[$numero_dia])) {
                usort(
                    $horarios_validados[$numero_dia],
                    function ($a, $b) {
                        return strcmp($a['desde'], $b['desde']);
                    }
                );

                for ($i = 1; $i < count($horarios_validados[$numero_dia]); $i++) {
                    $bloque_anterior = $horarios_validados[$numero_dia][$i - 1];
                    $bloque_actual = $horarios_validados[$numero_dia][$i];

                    if ($bloque_actual['desde'] < $bloque_anterior['hasta']) {
                        throw new InvalidArgumentException(
                            'Hay horarios superpuestos el ' . strtolower($nombre_dia) . '. Revisalos antes de guardar.'
                        );
                    }
                }
            }
        }

        // Si todo es válido, recién ahora guardamos.
        $conn->begin_transaction();

        $stmt_eliminar = $conn->prepare('
            DELETE FROM horarios_atencion
            WHERE id_veterinario = ?
        ');
        $stmt_eliminar->bind_param('i', $id_veterinario);
        $stmt_eliminar->execute();
        $stmt_eliminar->close();

        $stmt_insertar = $conn->prepare('
            INSERT INTO horarios_atencion (
                id_veterinario,
                dia_semana,
                hora_desde,
                hora_hasta,
                activo
            ) VALUES (?, ?, ?, ?, 1)
        ');

        foreach ($horarios_validados as $numero_dia => $bloques) {
            foreach ($bloques as $bloque) {
                $hora_desde = $bloque['desde'];
                $hora_hasta = $bloque['hasta'];

                $stmt_insertar->bind_param(
                    'iiss',
                    $id_veterinario,
                    $numero_dia,
                    $hora_desde,
                    $hora_hasta
                );
                $stmt_insertar->execute();
            }
        }

        $stmt_insertar->close();
        $conn->commit();

        $mensaje = 'Los horarios de atención se guardaron correctamente.';
        $tipo_mensaje = 'success';
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $error_rollback) {
            // Evita interrumpir la página si no había transacción activa.
        }

        $mensaje = 'No se pudieron guardar los horarios. Intentá nuevamente.';
        $tipo_mensaje = 'danger';
    }
}

/* --------------------------------------------------------------------------
   OBTENER HORARIOS ACTUALES
-------------------------------------------------------------------------- */
$horarios = [];

$stmt = $conn->prepare('
    SELECT id_horario, dia_semana, hora_desde, hora_hasta
    FROM horarios_atencion
    WHERE id_veterinario = ? AND activo = 1
    ORDER BY dia_semana, hora_desde
');
$stmt->bind_param('i', $id_veterinario);
$stmt->execute();

$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    $dia = $fila['dia_semana'];

    if (!isset($horarios[$dia])) {
        $horarios[$dia] = [];
    }

    $horarios[$dia][] = [
        'desde' => substr($fila['hora_desde'], 0, 5),
        'hasta' => substr($fila['hora_hasta'], 0, 5),
    ];
}

$stmt->close();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Horarios de atención · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png" />
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <!-- CSS general del sistema -->
    <link rel="stylesheet" href="../estilos/inicio.css" />
    <!-- CSS específico de horarios -->
    <link rel="stylesheet" href="../estilos/horarios.css" />
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">
            <!-- =====================================================
                 BARRA LATERAL
            ====================================================== -->
            <div class="col-12 col-lg-2 panel-lateral">
                <!-- LOGO -->
                <a href="../veterinarios/inicio_veterinario.php" class="logo-lateral">
                    <img src="../img/VetClinic Pro.png" alt="Logo VetClinic Pro" />
                    <div>
                        <div class="nombre-sistema">VetClinic Pro</div>
                        <div class="subtitulo-sistema">Portal del veterinario</div>
                    </div>
                </a>
                <!-- NAVEGACIÓN -->
                <div class="seccion-nav">Principal</div>
                <a href="../veterinarios/inicio_veterinario.php" class="item-menu">
                    <i class="bi bi-grid"></i>
                    Inicio
                </a>
                <a href="turnos_veterinario.php" class="item-menu">
                    <i class="bi bi-calendar-check"></i>
                    Turnos
                </a>
                <a href="mascotas_veterinario.php" class="item-menu">
                    <i class="bi bi-heart-pulse"></i>
                    Mascotas
                </a>
                <a href="historiales_veterinario.php" class="item-menu">
                    <i class="bi bi-journal-medical"></i>
                    Historial clínico
                </a>
                <!-- GESTIÓN -->
                <div class="seccion-nav">Gestión</div>
                <a href="propietarios_veterinario.php" class="item-menu">
                    <i class="bi bi-people"></i>
                    Propietarios
                </a>
                <a href="vacunaciones_veterinario.php" class="item-menu">
                    <i class="bi bi-shield-plus"></i>
                    Vacunaciones
                </a>
                <a href="mensajes.php" class="item-menu"><i class="bi bi-chat-dots"></i>
                    Mensajes</a>
                <!-- CONFIGURACIÓN -->
                <div class="seccion-nav">Configuración</div>
                <a href="configuracion_veterinario.php" class="item-menu">
                    <i class="bi bi-gear"></i> Mi configuración
                </a>
                <a href="horarios_atencion.php" class="item-menu activo">
                    <i class="bi bi-clock"></i>
                    Horarios de atención
                </a>
                <a href="../suscripciones/mi_suscripcion.php" class="item-menu">
                    <i class="bi bi-credit-card-2-front"></i>
                    Mi suscripción
                </a>
                <!-- USUARIO -->
                <div class="usuario-lateral">
                    <div class="avatar-usuario">
                        <?= strtoupper(substr($nombre, 0, 1) . substr($apellido, 0, 1)) ?>
                    </div>
                    <div>
                        <div class="nombre-usuario">
                            <?= htmlspecialchars($nombre . ' ' . $apellido) ?>
                        </div>
                        <div class="rol-usuario">Veterinario/a</div>
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
                        <p class="titulo-pagina">Horarios de atención</p>
                        <p class="fecha-hoy">Configurá los días y horarios en los que atendés.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="../veterinarios/inicio_veterinario.php" class="icono-barra" title="Volver al inicio">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <a href="../logout.php" class="icono-barra" title="Cerrar sesión">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <!-- MENSAJE -->
                <?php if ($mensaje !== ''): ?>
                    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($mensaje) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <!-- CONTENIDO -->
                <div class="contenedor-horarios">
                    <div class="encabezado-horarios">
                        <div class="titulo-seccion">Mi horario de atención</div>
                        <p class="descripcion-seccion">
                            Indicá los días y los horarios en los que recibís pacientes.
                            Podés agregar más de un horario en el mismo día.
                        </p>
                    </div>
                    <form method="POST" id="form-horarios">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <?php foreach ($dias as $numero_dia => $nombre_dia): ?>
                            <?php $bloques_dia = $horarios[$numero_dia] ?? []; ?>
                            <div class="fila-dia">
                                <div class="row align-items-center">
                                    <!-- DÍA -->
                                    <div class="col-12 col-lg-3">
                                        <div class="nombre-dia"><?= $nombre_dia ?></div>
                                    </div>
                                    <!-- HORARIOS -->
                                    <div class="col-12 col-lg-9">
                                        <div id="horarios-dia-<?= $numero_dia ?>">
                                            <?php if (empty($bloques_dia)): ?>
                                                <div class="texto-no-atiende" id="sin-horarios-<?= $numero_dia ?>">
                                                    Sin atención
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($bloques_dia as $indice => $bloque): ?>
                                                    <div class="bloque-horario">
                                                        <div class="campo-hora">
                                                            <input
                                                                type="time"
                                                                step="1800"
                                                                name="horarios[<?= $numero_dia ?>][<?= $indice ?>][desde]"
                                                                value="<?= htmlspecialchars($bloque['desde']) ?>"
                                                                required />
                                                        </div>
                                                        <span class="texto-a">a</span>
                                                        <div class="campo-hora">
                                                            <input
                                                                type="time"
                                                                step="1800"
                                                                name="horarios[<?= $numero_dia ?>][<?= $indice ?>][hasta]"
                                                                value="<?= htmlspecialchars($bloque['hasta']) ?>"
                                                                required />
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
                            Los turnos para los propietarios se generarán en intervalos de
                            <strong>30 minutos</strong> dentro de estos horarios. Los turnos
                            que ya estén ocupados no estarán disponibles.
                        </div>
                        <!-- GUARDAR -->
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="boton-guardar">
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
            const contenedor = document.getElementById("horarios-dia-" + numeroDia);

            // Eliminar mensaje "Sin atención".
            const mensaje = document.getElementById("sin-horarios-" + numeroDia);
            if (mensaje) {
                mensaje.remove();
            }

            // Usar un índice libre: contar filas reutilizaba índices al borrar un horario
            // intermedio, provocando pérdida de datos en el POST del formulario.
            const indices = Array.from(contenedor.querySelectorAll('input[name*="[desde]"]'))
                .map(input => Number(input.name.match(/\[(\d+)\]\[desde\]$/)?.[1] ?? -1));
            const cantidad = Math.max(-1, ...indices) + 1;

            const bloque = document.createElement("div");
            bloque.className = "bloque-horario";
            bloque.innerHTML = `
                <div class="campo-hora">
                    <input
                        type="time"
                        step="1800"
                        name="horarios[${numeroDia}][${cantidad}][desde]"
                        required
                    >
                </div>

                <span class="texto-a">a</span>

                <div class="campo-hora">
                    <input
                        type="time"
                        step="1800"
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
            const bloque = boton.closest(".bloque-horario");
            const contenedor = bloque.parentElement;
            const numeroDia = contenedor.id.replace("horarios-dia-", "");

            bloque.remove();

            // Si no quedan horarios, mostrar el mensaje nuevamente.
            const quedan = contenedor.querySelectorAll(".bloque-horario").length;
            if (quedan === 0) {
                const mensaje = document.createElement("div");
                mensaje.className = "texto-no-atiende";
                mensaje.id = "sin-horarios-" + numeroDia;
                mensaje.textContent = "Sin atención";
                contenedor.appendChild(mensaje);
            }
        }
    </script>    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="../mensajeria/notificaciones.js" defer></script>
</body>
</html>