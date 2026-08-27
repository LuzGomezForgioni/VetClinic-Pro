<?php
// ============================================================
//  VetClinic Pro — Editar Mascota
//  Archivo: mascotas/editar_mascota.php
// ============================================================
session_start();

if (empty($_SESSION['logueado'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';

$idPropietario = $_SESSION['usuario_id'];
$idMascota     = (int)($_GET['id'] ?? 0);
$errores       = [];

if ($idMascota === 0) {
    header('Location: inicio_mascota.php');
    exit;
}

// ── Traer la mascota (solo si es del propietario logueado) ──
$stmt = $conn->prepare("SELECT * FROM mascotas WHERE id_mascota = ? AND id_propietario = ? LIMIT 1");
$stmt->bind_param('ii', $idMascota, $idPropietario);
$stmt->execute();
$mascota = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$mascota) {
    header('Location: inicio_mascota.php');
    exit;
}

// ── Cargar especies y razas ──
$especies = $conn->query("SELECT id_especie, nombre_especie FROM especies ORDER BY nombre_especie")->fetch_all(MYSQLI_ASSOC);
$razas    = $conn->query("SELECT id_raza, id_especie, nombre_raza FROM razas ORDER BY nombre_raza")->fetch_all(MYSQLI_ASSOC);

// ── Procesar POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre          = trim($_POST['nombre']           ?? '');
    $idEspecie       = (int)($_POST['id_especie']      ?? 0);
    $idRaza          = (int)($_POST['id_raza']         ?? 0);
    $fechaNacimiento = trim($_POST['fecha_nacimiento']  ?? '');
    $sexo            = trim($_POST['sexo']             ?? '');
    $pesoKg          = trim($_POST['peso_kg']          ?? '');
    $color           = trim($_POST['color']            ?? '');
    $castrado        = isset($_POST['castrado']) ? 1 : 0;

    if ($nombre === '')   $errores[] = 'El nombre es obligatorio.';
    if ($idEspecie === 0) $errores[] = 'Seleccioná una especie.';
    if ($sexo === '')     $errores[] = 'Seleccioná el sexo.';
    if ($pesoKg !== '' && (!is_numeric($pesoKg) || $pesoKg <= 0)) {
        $errores[] = 'El peso debe ser mayor a cero.';
    }
    if ($fechaNacimiento !== '') {
        $f = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
        if (!$f || $f > new DateTime()) $errores[] = 'La fecha no puede ser en el futuro.';
    }

    if (empty($errores)) {
        $stmt = $conn->prepare("
            UPDATE mascotas SET
                id_especie       = ?,
                id_raza          = ?,
                nombre           = ?,
                fecha_nacimiento = ?,
                sexo             = ?,
                peso_kg          = ?,
                color            = ?,
                castrado         = ?
            WHERE id_mascota = ? AND id_propietario = ?
        ");

        $idRazaFinal = $idRaza ?: null;
        $fechaFinal  = $fechaNacimiento ?: null;
        $pesoFinal   = $pesoKg !== '' ? $pesoKg : null;
        $colorFinal  = $color ?: null;

        $stmt->bind_param(
            'iisssssiii',
            $idEspecie,
            $idRazaFinal,
            $nombre,
            $fechaFinal,
            $sexo,
            $pesoFinal,
            $colorFinal,
            $castrado,
            $idMascota,
            $idPropietario
        );
        $stmt->execute();
        $stmt->close();

        $_SESSION['mensaje']      = "Los datos de {$nombre} fueron actualizados con éxito. ✏️";
        $_SESSION['tipo_mensaje'] = 'exito';
        header('Location: inicio_mascota.php');
        exit;
    }

    // Repoblar con lo que envió el usuario si hay errores
    $mascota = array_merge($mascota, [
        'nombre'           => $nombre,
        'id_especie'       => $idEspecie,
        'id_raza'          => $idRaza,
        'fecha_nacimiento' => $fechaNacimiento,
        'sexo'             => $sexo,
        'peso_kg'          => $pesoKg,
        'color'            => $color,
        'castrado'         => $castrado,
    ]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Editar Mascota · VetClinic Pro</title>
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

    <!-- SIDEBAR -->
    <div class="col-12 col-lg-2 panel-lateral">
        <div class="logo-lateral">
            <img src="../img/VetClinic Pro.png" alt="Logo" />
            <div>
                <div class="nombre-sistema">VetClinic Pro</div>
                <div class="subtitulo-sistema">Portal del propietario</div>
            </div>
        </div>
        <div class="seccion-nav">Mi espacio</div>
        <a href="../propietarios/inicio_propietarios.php" class="item-menu"><i class="bi bi-grid"></i> Inicio</a>
        <a href="inicio_mascota.php" class="item-menu activo"><i class="bi bi-heart-pulse"></i> Mis mascotas</a>
        <a href="#" class="item-menu"><i class="bi bi-calendar-check"></i> Mis turnos</a>
        <a href="#" class="item-menu"><i class="bi bi-journal-medical"></i> Historial clínico</a>
        <div class="seccion-nav">Más</div>
        <a href="#" class="item-menu"><i class="bi bi-shield-plus"></i> Vacunaciones</a>
        <a href="#" class="item-menu"><i class="bi bi-chat-dots"></i> Mensajes</a>
        <div class="usuario-lateral">
            <div class="avatar-usuario"><?= strtoupper(substr($_SESSION['usuario_nombre'], 0, 1)) ?></div>
            <div>
                <div class="nombre-usuario"><?= htmlspecialchars($_SESSION['usuario_nombre'] . ' ' . ($_SESSION['usuario_apellido'] ?? '')) ?></div>
                <div class="rol-usuario">Propietario</div>
            </div>
        </div>
    </div>

    <!-- ÁREA PRINCIPAL -->
    <div class="col-12 col-lg-10 area-principal">

        <div class="barra-superior">
            <div>
                <p class="titulo-pagina">Editar mascota</p>
                <p class="fecha-hoy">Modificá los datos de <?= htmlspecialchars($mascota['nombre']) ?></p>
            </div>
            <a href="inicio_mascota.php" class="btn-accion btn-volver">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        <?php if (!empty($errores)): ?>
            <div class="alerta-operacion alerta-error" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div><?php foreach ($errores as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
            </div>
        <?php endif; ?>

        <div class="tarjeta-formulario">
            <form action="editar_mascota.php?id=<?= $idMascota ?>" method="POST" id="formEditar" novalidate>
                <div class="row g-3">

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="nombre">Nombre <span class="campo-requerido">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-tag"></i></span>
                            <input type="text" class="form-control" id="nombre" name="nombre"
                                   value="<?= htmlspecialchars($mascota['nombre']) ?>" />
                        </div>
                        <div class="error-campo" id="errorNombre"></div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="id_especie">Especie <span class="campo-requerido">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-grid-3x3-gap"></i></span>
                            <select class="form-control" id="id_especie" name="id_especie">
                                <option value="">Seleccioná una especie...</option>
                                <?php foreach ($especies as $esp): ?>
                                    <option value="<?= $esp['id_especie'] ?>"
                                        <?= ($mascota['id_especie'] == $esp['id_especie']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($esp['nombre_especie']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="error-campo" id="errorEspecie"></div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="id_raza">Raza</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-award"></i></span>
                            <select class="form-control" id="id_raza" name="id_raza">
                                <option value="">Sin raza específica</option>
                                <?php foreach ($razas as $r): ?>
                                    <option value="<?= $r['id_raza'] ?>"
        data-especie="<?= $r['id_especie'] ?>"
    <?= ($mascota['id_raza'] == $r['id_raza']) ? 'selected' : '' ?>>
    <?= htmlspecialchars($r['nombre_raza']) ?>
</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="sexo">Sexo <span class="campo-requerido">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-gender-ambiguous"></i></span>
                            <select class="form-control" id="sexo" name="sexo">
                                <option value="">Seleccioná...</option>
                                <option value="Macho"  <?= $mascota['sexo'] === 'Macho'  ? 'selected' : '' ?>>Macho</option>
                                <option value="Hembra" <?= $mascota['sexo'] === 'Hembra' ? 'selected' : '' ?>>Hembra</option>
                            </select>
                        </div>
                        <div class="error-campo" id="errorSexo"></div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento"
                                   max="<?= date('Y-m-d') ?>"
                                   value="<?= htmlspecialchars($mascota['fecha_nacimiento'] ?? '') ?>" />
                        </div>
                        <div class="error-campo" id="errorFecha"></div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="peso_kg">Peso (kg)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-speedometer2"></i></span>
                            <input type="number" class="form-control" id="peso_kg" name="peso_kg"
                                   step="0.1" min="0.1"
                                   value="<?= htmlspecialchars($mascota['peso_kg'] ?? '') ?>" />
                        </div>
                        <div class="error-campo" id="errorPeso"></div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="color">Color / Pelaje</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-palette"></i></span>
                            <input type="text" class="form-control" id="color" name="color"
                                   value="<?= htmlspecialchars($mascota['color'] ?? '') ?>" />
                        </div>
                    </div>

                    <div class="col-12 col-md-6 d-flex align-items-center">
                        <div class="check-castrado w-100">
                            <input class="form-check-input" type="checkbox" id="castrado" name="castrado"
                                   <?= $mascota['castrado'] ? 'checked' : '' ?> />
                            <label class="form-check-label ms-2" for="castrado">
                                <i class="bi bi-shield-check me-1"></i> Está castrado/a
                            </label>
                        </div>
                    </div>

                </div>

                <div class="d-flex gap-3 mt-4 pt-3" style="border-top: 1px solid var(--borde);">
                    <button type="submit" class="btn-abm-principal">
                        <i class="bi bi-check-lg"></i> Guardar cambios
                    </button>
                    <a href="inicio_mascota.php" class="btn-accion btn-volver">Cancelar</a>
                </div>
            </form>
        </div>

    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="abm_mascota.js"></script>
</body>
</html>
