<?php
// ============================================================
//  VetClinic Pro — Inicio Mascotas
//  Archivo: mascotas/inicio_mascota.php
// ============================================================
session_start();

// ── Verificar sesión ──────────────────────────────────────
// Cambiá 'logueado' por el nombre exacto que uses en tu login
if (empty($_SESSION['logueado'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php'; // usa $conn (mysqli)

$idPropietario = $_SESSION['usuario_id'];
$nombre        = $_SESSION['usuario_nombre'];
$apellido      = $_SESSION['usuario_apellido'] ?? '';

// Mensaje de operación anterior
$mensaje = $_SESSION['mensaje']      ?? '';
$tipoMsg = $_SESSION['tipo_mensaje'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

// ── Traer mascotas del propietario ────────────────────────
$sql = "
    SELECT
        m.id_mascota,
        m.nombre,
        m.sexo,
        m.peso_kg,
        m.color,
        m.castrado,
        m.fecha_nacimiento,
        e.nombre_especie,
        r.nombre_raza
    FROM mascotas m
    LEFT JOIN especies e ON m.id_especie = e.id_especie
    LEFT JOIN razas    r ON m.id_raza    = r.id_raza
    WHERE m.id_propietario = ?
    ORDER BY m.nombre ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $idPropietario);
$stmt->execute();
$resultado = $stmt->get_result();
$mascotas  = $resultado->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Helpers ───────────────────────────────────────────────
function calcularEdad(?string $fecha): string {
    if (!$fecha) return 'Sin datos';
    $diff = (new DateTime())->diff(new DateTime($fecha));
    if ($diff->y > 0) return $diff->y . ' año' . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return $diff->m . ' mes' . ($diff->m > 1 ? 'es' : '');
    return $diff->d . ' día' . ($diff->d > 1 ? 's' : '');
}

function emojiEspecie(?string $esp): string {
    $map = ['perro'=>'🐕','gato'=>'🐱','conejo'=>'🐇','pájaro'=>'🦜','pajaro'=>'🦜','hámster'=>'🐹','hamster'=>'🐹'];
    return $map[mb_strtolower($esp ?? '')] ?? '🐾';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mis Mascotas · VetClinic Pro</title>
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
        <a href="../propietarios/inicio_propietarios.php" class="logo-lateral">
            <img src="../img/VetClinic Pro.png" alt="Logo VetClinic Pro" />
            <div>
                <div class="nombre-sistema">VetClinic Pro</div>
                <div class="subtitulo-sistema">Portal del propietario</div>
            </div>
        </a>

        <div class="seccion-nav">Mi espacio</div>
        <a href="../propietarios/inicio_propietarios.php" class="item-menu">
            <i class="bi bi-grid"></i> Inicio
        </a>
        <a href="inicio_mascota.php" class="item-menu activo">
            <i class="bi bi-heart-pulse"></i> Mis mascotas
        </a>
        <a href="#" class="item-menu">
            <i class="bi bi-calendar-check"></i> Mis turnos
        </a>
        <a href="#" class="item-menu">
            <i class="bi bi-journal-medical"></i> Historial clínico
        </a>
        <div class="seccion-nav">Más</div>
        <a href="#" class="item-menu">
            <i class="bi bi-shield-plus"></i> Vacunaciones
        </a>
        <a href="#" class="item-menu">
            <i class="bi bi-chat-dots"></i> Mensajes
        </a>

        <div class="usuario-lateral">
            <div class="avatar-usuario">
                <?= strtoupper(substr($nombre, 0, 1)) ?>
            </div>
            <div>
                <div class="nombre-usuario"><?= htmlspecialchars($nombre . ' ' . $apellido) ?></div>
                <div class="rol-usuario">Propietario</div>
            </div>
        </div>
    </div>

    <!-- ÁREA PRINCIPAL -->
    <div class="col-12 col-lg-10 area-principal">

        <div class="barra-superior">
            <div>
                <p class="titulo-pagina">Mis Mascotas 🐾</p>
                <p class="fecha-hoy">
                    <?= count($mascotas) ?> mascota<?= count($mascotas) !== 1 ? 's' : '' ?> registrada<?= count($mascotas) !== 1 ? 's' : '' ?>
                </p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <a href="alta_mascota.php" class="btn-abm-principal">
                    <i class="bi bi-plus-lg"></i> Agregar mascota
                </a>
                <a href="../index.php" class="icono-barra" title="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Alerta de operación -->
        <?php if ($mensaje): ?>
            <div class="alerta-operacion alerta-<?= $tipoMsg ?>" role="alert">
                <i class="bi bi-<?= $tipoMsg === 'exito' ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i>
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <!-- Sin mascotas -->
        <?php if (empty($mascotas)): ?>
            <div class="tarjeta estado-vacio">
                <div class="emoji-vacio">🐾</div>
                <h4>Todavía no tenés mascotas registradas</h4>
                <p>Agregá tu primera mascota para gestionar su historial clínico, turnos y vacunaciones.</p>
                <a href="alta_mascota.php" class="btn-abm-principal">
                    <i class="bi bi-plus-lg"></i> Agregar mi primera mascota
                </a>
            </div>

        <!-- Grilla de mascotas -->
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($mascotas as $m): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="tarjeta-mascota">
                            <div class="cabecera-mascota">
                                <div class="emoji-mascota"><?= emojiEspecie($m['nombre_especie']) ?></div>
                                <div class="flex-grow-1">
                                    <div class="nombre-mascota-card"><?= htmlspecialchars($m['nombre']) ?></div>
                                    <div class="especie-raza">
                                        <?= htmlspecialchars($m['nombre_especie'] ?? '—') ?>
                                        <?= $m['nombre_raza'] ? ' · ' . htmlspecialchars($m['nombre_raza']) : '' ?>
                                    </div>
                                </div>
                            </div>

                            <div class="datos-mascota">
                                <div class="dato-fila">
                                    <i class="bi bi-calendar3"></i>
                                    <span><?= calcularEdad($m['fecha_nacimiento']) ?></span>
                                </div>
                                <div class="dato-fila">
                                    <i class="bi bi-gender-<?= $m['sexo'] === 'Macho' ? 'male' : 'female' ?>"></i>
                                    <span><?= htmlspecialchars($m['sexo'] ?? '—') ?></span>
                                </div>
                                <div class="dato-fila">
                                    <i class="bi bi-speedometer2"></i>
                                    <span><?= $m['peso_kg'] ? $m['peso_kg'] . ' kg' : '—' ?></span>
                                </div>
                                <?php if ($m['color']): ?>
                                <div class="dato-fila">
                                    <i class="bi bi-palette"></i>
                                    <span><?= htmlspecialchars($m['color']) ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="dato-fila">
                                    <i class="bi bi-shield-check"></i>
                                    <span><?= $m['castrado'] ? 'Castrado/a' : 'No castrado/a' ?></span>
                                </div>
                            </div>

                            <div class="acciones-mascota">
                                <a href="editar_mascota.php?id=<?= $m['id_mascota'] ?>" class="btn-accion btn-editar">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                                <a href="eliminar_mascota.php?id=<?= $m['id_mascota'] ?>"
   class="btn-accion btn-eliminar"
   onclick="return confirmarEliminar(event, this, '<?= htmlspecialchars(addslashes($m['nombre'])) ?>')">
    <i class="bi bi-trash3"></i> Eliminar
</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function confirmarEliminar(e, enlace, nombre) {
    e.preventDefault();
    Swal.fire({
        title: '¿Eliminar a ' + nombre + '?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280'
    }).then(function(result) {
        if (result.isConfirmed) {
            window.location.href = enlace.href;
        }
    });
}
</script>
</body>
</html>
