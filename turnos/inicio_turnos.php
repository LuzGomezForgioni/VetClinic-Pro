<?php
// ============================================================
// VetClinic Pro — Inicio Turnos
// Archivo: turnos/inicio_turnos.php
// ============================================================
session_start();

if (empty($_SESSION['logueado'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';

$idPropietario = (int)$_SESSION['usuario_id'];
$nombre        = $_SESSION['usuario_nombre'];
$apellido      = $_SESSION['usuario_apellido'] ?? '';

$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMsg = $_SESSION['tipo_mensaje'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

// ── Traer solamente los turnos del propietario logueado ───
$sql = "
    SELECT
        t.id_turno,
        t.fecha_hora,
        t.motivo,
        t.estado,
        t.observaciones,
        m.id_mascota,
        m.nombre AS nombre_mascota,
        e.nombre_especie,
        v.id_veterinario,
        v.nombre AS nombre_veterinario,
        v.apellido AS apellido_veterinario,
        v.especialidad
    FROM turnos t
    INNER JOIN mascotas m
        ON t.id_mascota = m.id_mascota
       AND m.id_propietario = t.id_propietario
    LEFT JOIN especies e
        ON m.id_especie = e.id_especie
    LEFT JOIN veterinarios v
        ON t.id_veterinario = v.id_veterinario
    WHERE t.id_propietario = ?
    ORDER BY t.fecha_hora DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $idPropietario);
$stmt->execute();
$resultado = $stmt->get_result();
$turnos = $resultado->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function claseEstado(string $estado): string
{
    switch (mb_strtolower($estado)) {
        case 'pendiente':
            return 'estado-pendiente';
        case 'confirmado':
            return 'estado-confirmado';
        case 'cancelado':
            return 'estado-cancelado';
        case 'realizado':
            return 'estado-realizado';
        default:
            return 'estado-otro';
    }
}

function iconoEstado(string $estado): string
{
    switch (mb_strtolower($estado)) {
        case 'pendiente':
            return 'clock';
        case 'confirmado':
            return 'check-circle';
        case 'cancelado':
            return 'x-circle';
        case 'realizado':
            return 'clipboard-check';
        default:
            return 'info-circle';
    }
}

function fechaTurno(string $fechaHora): string
{
    $fecha = new DateTime($fechaHora);
    return $fecha->format('d/m/Y');
}

function horaTurno(string $fechaHora): string
{
    $fecha = new DateTime($fechaHora);
    return $fecha->format('H:i');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mis Turnos · VetClinic Pro</title>
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
                <a href="../mascotas/inicio_mascota.php" class="item-menu">
                    <i class="bi bi-heart-pulse"></i> Mis mascotas
                </a>
                <a href="inicio_turnos.php" class="item-menu activo">
                    <i class="bi bi-calendar-check"></i> Mis turnos
                </a>
                <a href="../propietarios/historial_clinico.php" class="item-menu">
                    <i class="bi bi-journal-medical"></i>
                    Historial clínico
                </a>
                <div class="seccion-nav">Más</div>
                <a href="../propietarios/vacunaciones.php" class="item-menu">
                    <i class="bi bi-shield-plus"></i> Vacunaciones
                </a>
                <a href="#" class="item-menu">
                    <i class="bi bi-chat-dots"></i> Mensajes
                </a>
                <div class="seccion-nav">
                    Configuración
                </div>

                <a
                    href="../propietarios/configuracion_propietario.php"
                    class="item-menu">

                    <i class="bi bi-gear"></i>
                    Mi configuración

                </a>

                <div class="usuario-lateral">
                    <div class="avatar-usuario">
                        <?= htmlspecialchars(strtoupper(mb_substr($nombre, 0, 1))) ?>
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
                        <p class="titulo-pagina">Mis Turnos 📅</p>
                        <p class="fecha-hoy">
                            <?= count($turnos) ?> turno<?= count($turnos) !== 1 ? 's' : '' ?> registrado<?= count($turnos) !== 1 ? 's' : '' ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <a href="alta_turno.php" class="btn-abm-principal">
                            <i class="bi bi-plus-lg"></i> Solicitar turno
                        </a>
                        <a
                            href="../propietarios/configuracion_propietario.php"
                            class="icono-barra"
                            title="Mi configuración">

                            <i class="bi bi-gear"></i>

                        </a>
                        <a href="../index.php" class="icono-barra" title="Cerrar sesión">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alerta-operacion alerta-<?= $tipoMsg ?>" role="alert">
                        <i class="bi bi-<?= $tipoMsg === 'exito' ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i>
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($turnos)): ?>
                    <div class="tarjeta estado-vacio">
                        <div class="emoji-vacio">📅</div>
                        <h4>Todavía no tenés turnos registrados</h4>
                        <p>Solicitá un turno para una de tus mascotas y gestioná tus próximas consultas veterinarias.</p>
                        <a href="alta_turno.php" class="btn-abm-principal">
                            <i class="bi bi-plus-lg"></i> Solicitar mi primer turno
                        </a>
                    </div>
                <?php else: ?>

                    <div class="lista-turnos">
                        <?php foreach ($turnos as $t): ?>
                            <div class="tarjeta-turno">

                                <div class="cabecera-turno">
                                    <div class="fecha-turno">
                                        <div class="dia-turno"><?= htmlspecialchars((new DateTime($t['fecha_hora']))->format('d')) ?></div>
                                        <div class="mes-turno"><?= htmlspecialchars(mb_strtoupper((new DateTime($t['fecha_hora']))->format('M'))) ?></div>
                                    </div>

                                    <div class="datos-turno">
                                        <div class="nombre-mascota-turno">
                                            <?= htmlspecialchars($t['nombre_mascota']) ?>
                                            <?php if ($t['nombre_especie']): ?>
                                                <span>· <?= htmlspecialchars($t['nombre_especie']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="veterinario-turno">
                                            <i class="bi bi-person-badge"></i>
                                            Dr./Dra. <?= htmlspecialchars(trim($t['nombre_veterinario'] . ' ' . $t['apellido_veterinario'])) ?>
                                            <?php if ($t['especialidad']): ?>
                                                · <?= htmlspecialchars($t['especialidad']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <span class="estado-turno <?= claseEstado($t['estado']) ?>">
                                        <i class="bi bi-<?= iconoEstado($t['estado']) ?>"></i>
                                        <?= htmlspecialchars(ucfirst($t['estado'])) ?>
                                    </span>
                                </div>

                                <div class="cuerpo-turno">
                                    <div class="dato-fila">
                                        <i class="bi bi-clock"></i>
                                        <span><?= fechaTurno($t['fecha_hora']) ?> · <?= horaTurno($t['fecha_hora']) ?> hs</span>
                                    </div>
                                    <div class="dato-fila">
                                        <i class="bi bi-chat-left-text"></i>
                                        <span><?= htmlspecialchars($t['motivo']) ?></span>
                                    </div>
                                    <?php if (!empty($t['observaciones'])): ?>
                                        <div class="dato-fila">
                                            <i class="bi bi-info-circle"></i>
                                            <span><?= htmlspecialchars($t['observaciones']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (mb_strtolower($t['estado']) !== 'cancelado' && mb_strtolower($t['estado']) !== 'realizado'): ?>
                                    <div class="acciones-turno">
                                        <a href="editar_turno.php?id=<?= (int)$t['id_turno'] ?>" class="btn-accion btn-editar">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                        <a href="eliminar_turno.php?id=<?= (int)$t['id_turno'] ?>"
                                            class="btn-accion btn-eliminar"
                                            onclick="return confirmarEliminar(event, this)">
                                            <i class="bi bi-x-lg"></i> Cancelar
                                        </a>
                                    </div>
                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmarEliminar(e, enlace) {
            e.preventDefault();

            Swal.fire({
                title: '¿Cancelar este turno?',
                text: 'Esta acción eliminará el turno del sistema y no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, cancelar turno',
                cancelButtonText: 'Volver',
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