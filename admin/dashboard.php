<?php
session_start();
require_once '_auth.php';
require_once '../config/conexion.php';
require_once '../config/suscripciones.php';

actualizarTodasSuscripciones($conn);

function valorUnico(mysqli $conn, string $sql): int|float
{
    $res = $conn->query($sql);
    if (!$res) return 0;
    $fila = $res->fetch_row();
    return $fila ? (float)$fila[0] : 0;
}

$totalVeterinarios = (int) valorUnico($conn, "SELECT COUNT(*) FROM veterinarios");
$activos = (int) valorUnico($conn, "SELECT COUNT(*) FROM suscripciones WHERE estado='activa'");
$atrasados = (int) valorUnico($conn, "SELECT COUNT(*) FROM suscripciones WHERE estado='atrasada'");
$suspendidos = (int) valorUnico($conn, "SELECT COUNT(*) FROM suscripciones WHERE estado='suspendida'");
$pendientes = (int) valorUnico($conn, "SELECT COUNT(*) FROM suscripciones WHERE estado='pendiente_pago'");
$ingresosMes = (float) valorUnico($conn, "SELECT COALESCE(SUM(importe),0) FROM pagos WHERE estado='aprobado' AND YEAR(fecha_operacion)=YEAR(CURDATE()) AND MONTH(fecha_operacion)=MONTH(CURDATE())");

$ultimos = $conn->query("\n    SELECT v.id_veterinario, v.nombre, v.apellido, v.matricula, s.estado, s.fecha_vencimiento, p.precio_mensual\n    FROM veterinarios v\n    LEFT JOIN suscripciones s ON s.id_veterinario=v.id_veterinario\n    LEFT JOIN planes p ON p.id_plan=s.id_plan\n    ORDER BY v.fecha_registro DESC, v.id_veterinario DESC\n    LIMIT 8\n");
$ultimosVet = $ultimos ? $ultimos->fetch_all(MYSQLI_ASSOC) : [];

$seccionActiva = 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard · Superadmin</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos/admin.css">
</head>

<body>
    <div class="container-fluid">
        <div class="row admin-shell">
            <div class="col-12 col-lg-2 p-0"><?php include '_menu.php'; ?></div>
            <main class="col-12 col-lg-10 admin-main">
                <div class="admin-top">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Administración General</h1>
                        <div class="texto-suave">Control de profesionales, suscripciones y pagos.</div>
                    </div><a href="../suscripciones/plan_profesional.php" target="_blank" class="btn-admin"><i class="bi bi-box-arrow-up-right"></i> Ver página comercial</a>
                </div>
                <div class="row g-3 mb-4">
                    <?php foreach ([['Veterinarios', $totalVeterinarios, 'bi-person-badge'], ['Activos', $activos, 'bi-check-circle'], ['Atrasados', $atrasados, 'bi-exclamation-triangle'], ['Suspendidos', $suspendidos, 'bi-lock'], ['Pendientes de pago', $pendientes, 'bi-hourglass'], ['Ingresos del mes', formatoDinero($ingresosMes), 'bi-cash-stack']] as $m): ?>
                        <div class="col-6 col-xl-2">
                            <div class="card-admin h-100"><i class="bi <?= $m[2] ?> texto-suave"></i>
                                <div class="metric mt-2"><?= htmlspecialchars((string)$m[1]) ?></div>
                                <div class="small texto-suave"><?= htmlspecialchars($m[0]) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="row g-4">
                    <div class="col-xl-8">
                        <div class="card-admin">
                            <div class="d-flex justify-content-between mb-3">
                                <h2 class="h5 fw-bold mb-0">Últimas altas</h2><a href="veterinarios.php" class="small">Ver todos</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Veterinario</th>
                                            <th>Matrícula</th>
                                            <th>Suscripción</th>
                                            <th>Vence</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$ultimosVet): ?><tr>
                                                <td colspan="5" class="text-center texto-suave py-4">No hay veterinarios.</td>
                                            </tr><?php endif; ?>
                                        <?php foreach ($ultimosVet as $v): ?><tr>
                                                <td><strong><?= htmlspecialchars($v['nombre'] . ' ' . $v['apellido']) ?></strong></td>
                                                <td><?= htmlspecialchars($v['matricula']) ?></td>
                                                <td><span class="badge-estado <?= htmlspecialchars($v['estado'] ?? 'pendiente_pago') ?>"><?= htmlspecialchars(etiquetaEstadoSuscripcion($v['estado'] ?? 'pendiente_pago')) ?></span></td>
                                                <td><?= $v['fecha_vencimiento'] ? date('d/m/Y', strtotime($v['fecha_vencimiento'])) : '-' ?></td>
                                                <td><a class="btn btn-sm btn-outline-secondary" href="ficha_veterinario.php?id=<?= (int)$v['id_veterinario'] ?>">Ver</a></td>
                                            </tr><?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="card-admin">
                            <h2 class="h5 fw-bold">Atención</h2>
                            <div class="mt-3 d-grid gap-3"><a class="text-decoration-none" href="suscripciones.php?estado=atrasada">
                                    <div class="p-3 rounded-3 bg-warning-subtle"><strong><?= $atrasados ?> suscripciones atrasadas</strong>
                                        <div class="small texto-suave">Todavía están dentro del período de tolerancia.</div>
                                    </div>
                                </a><a class="text-decoration-none" href="suscripciones.php?estado=suspendida">
                                    <div class="p-3 rounded-3 bg-danger-subtle"><strong><?= $suspendidos ?> cuentas suspendidas</strong>
                                        <div class="small texto-suave">Sin acceso al área de gestión.</div>
                                    </div>
                                </a><a class="text-decoration-none" href="suscripciones.php?estado=pendiente_pago">
                                    <div class="p-3 rounded-3 bg-primary-subtle"><strong><?= $pendientes ?> altas sin activar</strong>
                                        <div class="small texto-suave">Esperan el primer pago.</div>
                                    </div>
                                </a></div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>

</html>