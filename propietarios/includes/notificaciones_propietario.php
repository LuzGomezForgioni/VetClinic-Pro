<?php

/*
|--------------------------------------------------------------------------
| NOTIFICACIONES DEL PROPIETARIO
|--------------------------------------------------------------------------
|
| Este archivo se incluye en todas las pantallas del propietario.
|
| Antes de incluirlo deben existir:
|
| $conn
| $id_propietario
|
*/

// ============================================================
// SEGURIDAD
// ============================================================

if (!isset($conn) || !isset($id_propietario)) {
    return;
}

// ============================================================
// PÁGINA ACTUAL
// ============================================================

$pagina_actual_notificaciones = basename($_SERVER['PHP_SELF']);

// ============================================================
// MARCAR UNA NOTIFICACIÓN COMO LEÍDA
// ============================================================

if (isset($_GET['leer_notificacion'])) {

    $id_notificacion = (int) $_GET['leer_notificacion'];

    // Primero comprobamos que pertenezca al propietario logueado

    $sql_notificacion = "
        SELECT
            id_notificacion,
            id_mascota,
            id_turno,
            tipo

        FROM notificaciones

        WHERE id_notificacion = ?
          AND id_propietario = ?

        LIMIT 1
    ";

    $stmt_notificacion = $conn->prepare($sql_notificacion);

    $stmt_notificacion->bind_param(
        "ii",
        $id_notificacion,
        $id_propietario
    );

    $stmt_notificacion->execute();

    $notificacion_seleccionada =
        $stmt_notificacion
        ->get_result()
        ->fetch_assoc();

    $stmt_notificacion->close();

    if ($notificacion_seleccionada) {

        // Marcar como leída

        $sql_leer = "
            UPDATE notificaciones

            SET leida = 1

            WHERE id_notificacion = ?
              AND id_propietario = ?
        ";

        $stmt_notificacion = $conn->prepare($sql_leer);

        $stmt_notificacion->bind_param(
            "ii",
            $id_notificacion,
            $id_propietario
        );

        $stmt_notificacion->execute();

        $stmt_notificacion->close();

        // ====================================================
        // DECIDIR A DÓNDE IR
        // ====================================================

        $destino = 'inicio_propietarios.php';

        // Vacunación

        if (
            $notificacion_seleccionada['tipo']
            === 'vacuna_pendiente'
        ) {

            $destino =
                'vacunaciones.php?id_mascota=' .
                (int) $notificacion_seleccionada['id_mascota'];
        }

        // Turnos

        elseif (
            $notificacion_seleccionada['tipo']
            === 'recordatorio_turno' ||
            $notificacion_seleccionada['tipo']
            === 'turno_cancelado'
        ) {

            $destino = '../turnos/inicio_turnos.php';
        }

        // Historia clínica

        elseif (
            $notificacion_seleccionada['tipo']
            === 'resultado_listo'
        ) {

            $destino =
                'historial_clinico.php?id_mascota=' .
                (int) $notificacion_seleccionada['id_mascota'];
        }

        header('Location: ' . $destino);
        exit;
    }
}

// ============================================================
// MARCAR TODAS COMO LEÍDAS
// ============================================================

if (isset($_GET['leer_todas'])) {

    $sql_leer_todas = "
        UPDATE notificaciones

        SET leida = 1

        WHERE id_propietario = ?
          AND leida = 0
    ";

    $stmt_notificacion = $conn->prepare($sql_leer_todas);

    $stmt_notificacion->bind_param(
        "i",
        $id_propietario
    );

    $stmt_notificacion->execute();

    $stmt_notificacion->close();

    /*
        Volvemos a la misma pantalla.

        Si estaba viendo una mascota determinada,
        conservamos ese id.
    */

    $destino_retorno = $pagina_actual_notificaciones;

    if (!empty($_GET['id_mascota'])) {

        $destino_retorno .=
            '?id_mascota=' .
            (int) $_GET['id_mascota'];
    }

    header('Location: ' . $destino_retorno);
    exit;
}

// ============================================================
// CREAR NOTIFICACIONES DE VACUNAS
// ============================================================

$sql_vacunas_notificaciones = "
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
      AND m.fallecido = 0
      AND v.fecha_proxima IS NOT NULL
      AND v.fecha_proxima <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)

    ORDER BY v.fecha_proxima ASC
";

$stmt_notificacion = $conn->prepare(
    $sql_vacunas_notificaciones
);

$stmt_notificacion->bind_param(
    "i",
    $id_propietario
);

$stmt_notificacion->execute();

$resultado_vacunas_notificacion =
    $stmt_notificacion->get_result();

while (
    $vacuna_notificacion =
    $resultado_vacunas_notificacion->fetch_assoc()
) {

    $fecha_proxima =
        new DateTime(
            $vacuna_notificacion['fecha_proxima']
        );

    $hoy_notificaciones =
        new DateTime('today');

    // ========================================================
    // MENSAJE
    // ========================================================

    if ($fecha_proxima < $hoy_notificaciones) {

        $mensaje_notificacion =
            'La vacuna ' .
            $vacuna_notificacion['nombre_vacuna'] .
            ' de ' .
            $vacuna_notificacion['nombre_mascota'] .
            ' está vencida desde el ' .
            $fecha_proxima->format('d/m/Y') .
            '.';

    } else {

        $mensaje_notificacion =
            'La vacuna ' .
            $vacuna_notificacion['nombre_vacuna'] .
            ' de ' .
            $vacuna_notificacion['nombre_mascota'] .
            ' vence el ' .
            $fecha_proxima->format('d/m/Y') .
            '.';
    }

    // ========================================================
    // COMPROBAR QUE NO EXISTA
    // ========================================================

    $sql_existe = "
        SELECT id_notificacion

        FROM notificaciones

        WHERE id_propietario = ?
          AND id_mascota = ?
          AND tipo = 'vacuna_pendiente'
          AND mensaje = ?

        LIMIT 1
    ";

    $stmt_existe = $conn->prepare($sql_existe);

    $id_mascota_notificacion =
        (int) $vacuna_notificacion['id_mascota'];

    $stmt_existe->bind_param(
        "iis",
        $id_propietario,
        $id_mascota_notificacion,
        $mensaje_notificacion
    );

    $stmt_existe->execute();

    $existe =
        $stmt_existe
        ->get_result()
        ->fetch_assoc();

    $stmt_existe->close();

    // Crear solamente si todavía no existe

    if (!$existe) {

        $sql_insertar = "
            INSERT INTO notificaciones
            (
                id_propietario,
                id_mascota,
                tipo,
                mensaje,
                enviada
            )
            VALUES (
                ?,
                ?,
                'vacuna_pendiente',
                ?,
                1
            )
        ";

        $stmt_insertar = $conn->prepare(
            $sql_insertar
        );

        $stmt_insertar->bind_param(
            "iis",
            $id_propietario,
            $id_mascota_notificacion,
            $mensaje_notificacion
        );

        $stmt_insertar->execute();

        $stmt_insertar->close();
    }
}

$stmt_notificacion->close();

// ============================================================
// CREAR NOTIFICACIONES DE TURNOS PRÓXIMOS
// ============================================================

$sql_turnos_notificaciones = "
    SELECT
        t.id_turno,
        t.id_mascota,
        t.fecha_hora,

        m.nombre AS nombre_mascota

    FROM turnos t

    INNER JOIN mascotas m
        ON t.id_mascota = m.id_mascota

    WHERE t.id_propietario = ?
      AND t.estado = 'confirmado'
      AND t.fecha_hora >= NOW()
      AND t.fecha_hora <= DATE_ADD(NOW(), INTERVAL 7 DAY)

    ORDER BY t.fecha_hora ASC
";

$stmt_notificacion = $conn->prepare(
    $sql_turnos_notificaciones
);

$stmt_notificacion->bind_param(
    "i",
    $id_propietario
);

$stmt_notificacion->execute();

$resultado_turnos_notificacion =
    $stmt_notificacion->get_result();

while (
    $turno_notificacion =
    $resultado_turnos_notificacion->fetch_assoc()
) {

    $fecha_turno_notificacion =
        new DateTime(
            $turno_notificacion['fecha_hora']
        );

    $mensaje_turno =
        $turno_notificacion['nombre_mascota'] .
        ' tiene un turno confirmado para el ' .
        $fecha_turno_notificacion->format('d/m/Y') .
        ' a las ' .
        $fecha_turno_notificacion->format('H:i') .
        ' hs.';

    // ========================================================
    // COMPROBAR DUPLICADO
    // ========================================================

    $sql_existe_turno = "
        SELECT id_notificacion

        FROM notificaciones

        WHERE id_propietario = ?
          AND id_turno = ?
          AND tipo = 'recordatorio_turno'

        LIMIT 1
    ";

    $stmt_existe = $conn->prepare(
        $sql_existe_turno
    );

    $id_turno_notificacion =
        (int) $turno_notificacion['id_turno'];

    $stmt_existe->bind_param(
        "ii",
        $id_propietario,
        $id_turno_notificacion
    );

    $stmt_existe->execute();

    $existe_turno =
        $stmt_existe
        ->get_result()
        ->fetch_assoc();

    $stmt_existe->close();

    // Insertar si todavía no existe

    if (!$existe_turno) {

        $sql_insertar_turno = "
            INSERT INTO notificaciones
            (
                id_propietario,
                id_mascota,
                id_turno,
                tipo,
                mensaje,
                enviada
            )
            VALUES (
                ?,
                ?,
                ?,
                'recordatorio_turno',
                ?,
                1
            )
        ";

        $stmt_insertar = $conn->prepare(
            $sql_insertar_turno
        );

        $id_mascota_turno =
            (int) $turno_notificacion['id_mascota'];

        $stmt_insertar->bind_param(
            "iiis",
            $id_propietario,
            $id_mascota_turno,
            $id_turno_notificacion,
            $mensaje_turno
        );

        $stmt_insertar->execute();

        $stmt_insertar->close();
    }
}

$stmt_notificacion->close();

// ============================================================
// CANTIDAD DE NO LEÍDAS
// ============================================================

$sql_cantidad_notificaciones = "
    SELECT COUNT(*) AS total

    FROM notificaciones

    WHERE id_propietario = ?
      AND leida = 0
";

$stmt_notificacion = $conn->prepare(
    $sql_cantidad_notificaciones
);

$stmt_notificacion->bind_param(
    "i",
    $id_propietario
);

$stmt_notificacion->execute();

$fila_cantidad =
    $stmt_notificacion
    ->get_result()
    ->fetch_assoc();

$cantidad_notificaciones =
    (int) ($fila_cantidad['total'] ?? 0);

$stmt_notificacion->close();

// ============================================================
// ÚLTIMAS 5 NOTIFICACIONES
// ============================================================

$sql_notificaciones = "
    SELECT
        id_notificacion,
        id_mascota,
        id_turno,
        tipo,
        mensaje,
        fecha_envio,
        leida

    FROM notificaciones

    WHERE id_propietario = ?

    ORDER BY
        leida ASC,
        fecha_envio DESC

    LIMIT 5
";

$stmt_notificacion = $conn->prepare(
    $sql_notificaciones
);

$stmt_notificacion->bind_param(
    "i",
    $id_propietario
);

$stmt_notificacion->execute();

$resultado_notificaciones =
    $stmt_notificacion->get_result();

$notificaciones_inicio = [];

while (
    $fila =
    $resultado_notificaciones->fetch_assoc()
) {

    $notificaciones_inicio[] = $fila;
}

$stmt_notificacion->close();

// ============================================================
// ICONO SEGÚN TIPO
// ============================================================

function iconoNotificacionPropietario(string $tipo): string
{
    switch ($tipo) {

        case 'vacuna_pendiente':
            return 'bi-shield-exclamation';

        case 'recordatorio_turno':
            return 'bi-calendar-event';

        case 'turno_cancelado':
            return 'bi-calendar-x';

        case 'resultado_listo':
            return 'bi-journal-medical';

        default:
            return 'bi-bell';
    }
}

// ============================================================
// DIBUJAR CAMPANITA
// ============================================================

function mostrarCampanaNotificaciones(
    int $cantidad_notificaciones,
    array $notificaciones
): void {

    $pagina_actual =
        basename($_SERVER['PHP_SELF']);

    ?>
    <div class="dropdown">
        <button
            type="button"
            class="icono-barra boton-notificaciones"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            title="Notificaciones"
        >
            <i class="bi bi-bell"></i>
            <?php if ($cantidad_notificaciones > 0): ?>
                <span class="contador-notificaciones">
                    <?= $cantidad_notificaciones > 9
                        ? '9+'
                        : $cantidad_notificaciones
                    ?>
                </span>
            <?php endif; ?>
        </button>
        <div
            class="dropdown-menu dropdown-menu-end menu-notificaciones"
        >
            <div class="encabezado-notificaciones">
                <div>
                    <strong>
                        Notificaciones
                    </strong>
                    <div class="subtitulo-notificaciones">
                        <?= $cantidad_notificaciones ?>
                        sin leer
                    </div>
                </div>
                <?php if ($cantidad_notificaciones > 0): ?>
                    <?php

                    $url_leer_todas =
                        $pagina_actual .
                        '?leer_todas=1';

                    if (!empty($_GET['id_mascota'])) {

                        $url_leer_todas .=
                            '&id_mascota=' .
                            (int) $_GET['id_mascota'];
                    }

                    ?>
                    <a
                        href="<?= htmlspecialchars($url_leer_todas) ?>"
                        class="leer-todas"
                    >
                        Marcar todas como leídas
                    </a>
                <?php endif; ?>
            </div>
            <?php if (empty($notificaciones)): ?>
                <div class="sin-notificaciones">
                    <i class="bi bi-bell-slash"></i>
                    <div>
                        No tenés notificaciones
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notificaciones as $notificacion): ?>
                    <a
                        href="<?= htmlspecialchars($pagina_actual) ?>?leer_notificacion=<?= (int) $notificacion['id_notificacion'] ?>"
                        class="item-notificacion <?= !$notificacion['leida'] ? 'no-leida' : '' ?>"
                    >
                        <div class="icono-notificacion">
                            <i
                                class="bi <?= htmlspecialchars(
                                    iconoNotificacionPropietario(
                                        $notificacion['tipo']
                                    )
                                ) ?>"
                            ></i>
                        </div>
                        <div class="contenido-notificacion">
                            <div class="mensaje-notificacion">
                                <?= htmlspecialchars(
                                    $notificacion['mensaje']
                                ) ?>
                            </div>
                            <div class="fecha-notificacion">
                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $notificacion['fecha_envio']
                                    )
                                ) ?>
                            </div>
                        </div>
                        <?php if (!$notificacion['leida']): ?>
                            <span class="punto-notificacion"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}