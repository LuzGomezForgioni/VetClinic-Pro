<?php
// ============================================================
// VetClinic Pro — Editar Turno
// Archivo: turnos/editar_turno.php
// ============================================================
session_start();

if (empty($_SESSION['logueado'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';

$idPropietario = (int)$_SESSION['usuario_id'];
$idTurno = (int)($_GET['id'] ?? 0);
$errores = [];

if ($idTurno === 0) {
    header('Location: inicio_turnos.php');
    exit;
}

// ── Traer el turno únicamente si pertenece al propietario ──
$stmt = $conn->prepare("
    SELECT
        t.id_turno,
        t.id_mascota,
        t.id_veterinario,
        t.id_propietario,
        t.fecha_hora,
        t.motivo,
        t.estado,
        t.observaciones
    FROM turnos t
    WHERE t.id_turno = ? AND t.id_propietario = ?
    LIMIT 1
");
$stmt->bind_param('ii', $idTurno, $idPropietario);
$stmt->execute();
$turno = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$turno) {
    header('Location: inicio_turnos.php');
    exit;
}

// El propietario no puede editar estados finales.
$estadoTurno = mb_strtolower($turno['estado']);
if ($estadoTurno === 'cancelado' || $estadoTurno === 'realizado') {
    $_SESSION['mensaje'] = 'Este turno ya no puede modificarse.';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: inicio_turnos.php');
    exit;
}

// ── Mascotas propias ───────────────────────────────────────
$stmt = $conn->prepare("
    SELECT id_mascota, nombre
    FROM mascotas
    WHERE id_propietario = ?
    ORDER BY nombre ASC
");
$stmt->bind_param('i', $idPropietario);
$stmt->execute();
$mascotas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Veterinarios activos + veterinario actual ─────────────
$resultadoVeterinarios = $conn->query("
    SELECT id_veterinario, nombre, apellido, especialidad
    FROM veterinarios
    WHERE activo = 1
    ORDER BY apellido ASC, nombre ASC
");
$veterinarios = $resultadoVeterinarios
    ? $resultadoVeterinarios->fetch_all(MYSQLI_ASSOC)
    : [];

// Si el veterinario actual quedó inactivo, se permite mostrarlo
// solamente para conservar el valor existente, pero no se ofrece
// como una nueva opción disponible.
$actualEnLista = false;
foreach ($veterinarios as $v) {
    if ((int)$v['id_veterinario'] === (int)$turno['id_veterinario']) {
        $actualEnLista = true;
        break;
    }
}

if (!$actualEnLista) {
    $stmt = $conn->prepare("
        SELECT id_veterinario, nombre, apellido, especialidad, activo
        FROM veterinarios
        WHERE id_veterinario = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $turno['id_veterinario']);
    $stmt->execute();
    $veterinarioActual = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($veterinarioActual) {
        array_unshift($veterinarios, $veterinarioActual);
    }
}

// ── Procesar POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $idMascota     = (int)($_POST['id_mascota'] ?? 0);
    $idVeterinario = (int)($_POST['id_veterinario'] ?? 0);
    $fechaHora     = trim($_POST['fecha_hora'] ?? '');
    $motivo        = trim($_POST['motivo'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    // El estado y propietario NO se reciben del formulario.

    // ── Mascota: debe seguir perteneciendo al propietario ──
    if ($idMascota <= 0) {
        $errores[] = 'Seleccioná una mascota.';
    } else {
        $stmt = $conn->prepare("
            SELECT id_mascota
            FROM mascotas
            WHERE id_mascota = ? AND id_propietario = ?
            LIMIT 1
        ");
        $stmt->bind_param('ii', $idMascota, $idPropietario);
        $stmt->execute();
        $mascotaValida = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$mascotaValida) {
            $errores[] = 'La mascota seleccionada no pertenece al propietario logueado.';
        }
    }

    // ── Veterinario: debe existir y estar activo ──────────
    if ($idVeterinario <= 0) {
        $errores[] = 'Seleccioná un veterinario.';
    } else {
        $stmt = $conn->prepare("
            SELECT id_veterinario
            FROM veterinarios
            WHERE id_veterinario = ? AND activo = 1
            LIMIT 1
        ");
        $stmt->bind_param('i', $idVeterinario);
        $stmt->execute();
        $veterinarioValido = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Si se está conservando el veterinario actual y está inactivo,
        // no se permite guardar hasta seleccionar uno activo.
        if (!$veterinarioValido) {
            $errores[] = 'El veterinario seleccionado no está disponible.';
        }
    }

    // ── Fecha/hora ─────────────────────────────────────────
    $fechaObjeto = DateTime::createFromFormat('Y-m-d\TH:i', $fechaHora);
    $erroresFecha = DateTime::getLastErrors();

    if (!$fechaObjeto || ($erroresFecha !== false && ($erroresFecha['warning_count'] > 0 || $erroresFecha['error_count'] > 0))) {
        $errores[] = 'Ingresá una fecha y hora válidas.';
    } elseif ($fechaObjeto <= new DateTime()) {
        $errores[] = 'No se pueden guardar turnos en el pasado.';
    }

    // ── Motivo ─────────────────────────────────────────────
    if ($motivo === '') {
        $errores[] = 'El motivo de la consulta es obligatorio.';
    } elseif (mb_strlen($motivo) > 200) {
        $errores[] = 'El motivo no puede superar los 200 caracteres.';
    }

    if (empty($errores)) {

        $observacionesFinal = $observaciones !== '' ? $observaciones : null;

        $stmt = $conn->prepare("
            UPDATE turnos
            SET
                id_mascota = ?,
                id_veterinario = ?,
                fecha_hora = ?,
                motivo = ?,
                observaciones = ?
            WHERE id_turno = ? AND id_propietario = ?
        ");

        $stmt->bind_param(
            'iisssii',
            $idMascota,
            $idVeterinario,
            $fechaHora,
            $motivo,
            $observacionesFinal,
            $idTurno,
            $idPropietario
        );

        if ($stmt->execute()) {
            $stmt->close();

            $_SESSION['mensaje'] = 'Los datos del turno fueron actualizados con éxito. ✏️';
            $_SESSION['tipo_mensaje'] = 'exito';

            header('Location: inicio_turnos.php');
            exit;
        }

        $stmt->close();
        $errores[] = 'No se pudo actualizar el turno. Intentá nuevamente.';
    }

    // Repoblar con los datos enviados si hay errores.
    $turno['id_mascota'] = $idMascota;
    $turno['id_veterinario'] = $idVeterinario;
    $turno['fecha_hora'] = $fechaHora;
    $turno['motivo'] = $motivo;
    $turno['observaciones'] = $observaciones;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Editar Turno · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../estilos/inicio.css" />
    <link rel="stylesheet" href="../estilos/abm.css" />
</head>
<body>
<div class="container-fluid p-0">
<div class="row g-0 min-vh-100">

    <div class="col-12 col-lg-2 panel-lateral">
        <a href="../propietarios/inicio_propietarios.php" class="logo-lateral">
            <img src="../img/VetClinic Pro.png" alt="Logo VetClinic Pro"/>
            <div>
                <div class="nombre-sistema">VetClinic Pro</div>
                <div class="subtitulo-sistema">Portal del propietario</div>
            </div>
        </a>
        <div class="seccion-nav">Mi espacio</div>
        <a href="../propietarios/inicio_propietarios.php" class="item-menu"><i class="bi bi-grid"></i> Inicio</a>
        <a href="../mascotas/inicio_mascota.php" class="item-menu"><i class="bi bi-heart-pulse"></i> Mis mascotas</a>
        <a href="inicio_turnos.php" class="item-menu activo"><i class="bi bi-calendar-check"></i> Mis turnos</a>
        <a href="#" class="item-menu"><i class="bi bi-journal-medical"></i> Historial clínico</a>
        <div class="seccion-nav">Más</div>
        <a href="#" class="item-menu"><i class="bi bi-shield-plus"></i> Vacunaciones</a>
        <a href="#" class="item-menu"><i class="bi bi-chat-dots"></i> Mensajes</a>
        <div class="usuario-lateral">
            <div class="avatar-usuario"><?= htmlspecialchars(strtoupper(mb_substr($_SESSION['usuario_nombre'], 0, 1))) ?></div>
            <div>
                <div class="nombre-usuario"><?= htmlspecialchars($_SESSION['usuario_nombre'] . ' ' . ($_SESSION['usuario_apellido'] ?? '')) ?></div>
                <div class="rol-usuario">Propietario</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-10 area-principal">

        <div class="barra-superior">
            <div>
                <p class="titulo-pagina">Editar turno</p>
                <p class="fecha-hoy">Modificá los datos de tu consulta</p>
            </div>
            <a href="inicio_turnos.php" class="btn-accion btn-volver">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        <?php if (!empty($errores)): ?>
            <div class="alerta-operacion alerta-error" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div>
                    <?php foreach ($errores as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="tarjeta-formulario">
            <form action="editar_turno.php?id=<?= $idTurno ?>" method="POST" id="formEditarTurno" novalidate>
                <div class="row g-3">

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="id_mascota">Mascota <span class="campo-requerido">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-heart-pulse"></i></span>
                            <select class="form-control" id="id_mascota" name="id_mascota" required>
                                <option value="">Seleccioná una mascota...</option>
                                <?php foreach ($mascotas as $m): ?>
                                    <option value="<?= (int)$m['id_mascota'] ?>"
                                        <?= ((int)$turno['id_mascota'] === (int)$m['id_mascota']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="error-campo" id="errorMascota"></div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="id_veterinario">Veterinario <span class="campo-requerido">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <select class="form-control" id="id_veterinario" name="id_veterinario" required>
                                <option value="">Seleccioná un veterinario...</option>
                                <?php foreach ($veterinarios as $v): ?>
                                    <option value="<?= (int)$v['id_veterinario'] ?>"
                                        <?= ((int)$turno['id_veterinario'] === (int)$v['id_veterinario']) ? 'selected' : '' ?>>
                                        Dr./Dra. <?= htmlspecialchars(trim($v['nombre'] . ' ' . $v['apellido'])) ?>
                                        <?= $v['especialidad'] ? ' · ' . htmlspecialchars($v['especialidad']) : '' ?>
                                        <?= isset($v['activo']) && !(int)$v['activo'] ? ' · No disponible' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="error-campo" id="errorVeterinario"></div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="fecha_hora">Fecha y hora <span class="campo-requerido">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                            <input type="datetime-local" class="form-control" id="fecha_hora" name="fecha_hora"
                                   min="<?= date('Y-m-d\TH:i') ?>"
                                   value="<?= htmlspecialchars(str_replace(' ', 'T', substr($turno['fecha_hora'], 0, 16))) ?>"
                                   required />
                        </div>
                        <div class="error-campo" id="errorFechaHora"></div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="motivo">Motivo <span class="campo-requerido">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-chat-left-text"></i></span>
                            <input type="text" class="form-control" id="motivo" name="motivo"
                                   maxlength="200"
                                   value="<?= htmlspecialchars($turno['motivo']) ?>"
                                   required />
                        </div>
                        <div class="form-text">Máximo 200 caracteres.</div>
                        <div class="error-campo" id="errorMotivo"></div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="observaciones">Observaciones</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                            <textarea class="form-control" id="observaciones" name="observaciones"
                                      rows="4"><?= htmlspecialchars($turno['observaciones'] ?? '') ?></textarea>
                        </div>
                    </div>

                </div>

                <div class="nota-info mt-4">
                    <i class="bi bi-info-circle-fill"></i>
                    <p>El estado del turno no se modifica desde este formulario. La gestión del estado corresponde a la veterinaria.</p>
                </div>

                <div class="d-flex gap-3 mt-4 pt-3" style="border-top: 1px solid var(--borde);">
                    <button type="submit" class="btn-abm-principal">
                        <i class="bi bi-check-lg"></i> Guardar cambios
                    </button>
                    <a href="inicio_turnos.php" class="btn-accion btn-volver">Cancelar</a>
                </div>
            </form>
        </div>

    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('formEditarTurno');
    if (!form) return;

    function mostrarError(id, mensaje) {
        var div = document.getElementById(id);
        if (div) {
            div.textContent = mensaje;
            div.style.display = mensaje ? 'block' : 'none';
        }
    }

    form.addEventListener('submit', function (e) {
        var valido = true;

        mostrarError('errorMascota', '');
        mostrarError('errorVeterinario', '');
        mostrarError('errorFechaHora', '');
        mostrarError('errorMotivo', '');

        if (!document.getElementById('id_mascota').value) {
            mostrarError('errorMascota', 'Seleccioná una mascota.');
            valido = false;
        }

        if (!document.getElementById('id_veterinario').value) {
            mostrarError('errorVeterinario', 'Seleccioná un veterinario.');
            valido = false;
        }

        var fechaHora = document.getElementById('fecha_hora');
        if (!fechaHora.value) {
            mostrarError('errorFechaHora', 'Ingresá una fecha y hora.');
            valido = false;
        } else if (new Date(fechaHora.value) <= new Date()) {
            mostrarError('errorFechaHora', 'El turno debe ser en el futuro.');
            valido = false;
        }

        var motivo = document.getElementById('motivo');
        if (!motivo.value.trim()) {
            mostrarError('errorMotivo', 'El motivo de la consulta es obligatorio.');
            valido = false;
        } else if (motivo.value.trim().length > 200) {
            mostrarError('errorMotivo', 'El motivo no puede superar los 200 caracteres.');
            valido = false;
        }

        if (!valido) e.preventDefault();
    });
});
</script>
</body>
</html>
