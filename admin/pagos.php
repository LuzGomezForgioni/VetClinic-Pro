<?php
session_start();
require_once '_auth.php';
require_once '../config/conexion.php';
require_once '../config/suscripciones.php';
$estado = trim((string)($_GET['estado'] ?? ''));
$validos = ['aprobado', 'pendiente', 'rechazado'];
if (in_array($estado, $validos, true)) {
    $stmt = $conn->prepare("SELECT pa.*,v.nombre,v.apellido,pl.nombre AS plan_nombre FROM pagos pa INNER JOIN veterinarios v ON v.id_veterinario=pa.id_veterinario INNER JOIN planes pl ON pl.id_plan=pa.id_plan WHERE pa.estado=? ORDER BY pa.fecha_operacion DESC");
    $stmt->bind_param('s', $estado);
    $stmt->execute();
    $pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $estado = '';
    $res = $conn->query("SELECT pa.*,v.nombre,v.apellido,pl.nombre AS plan_nombre FROM pagos pa INNER JOIN veterinarios v ON v.id_veterinario=pa.id_veterinario INNER JOIN planes pl ON pl.id_plan=pa.id_plan ORDER BY pa.fecha_operacion DESC LIMIT 300");
    $pagos = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
$seccionActiva = 'pagos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pagos · Superadmin</title>
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
                        <h1 class="h3 fw-bold mb-1">Pagos</h1>
                        <div class="texto-suave">Operaciones aprobadas, pendientes y rechazadas.</div>
                    </div>
                </div>
                <div class="d-flex gap-2 mb-3"><a class="btn btn-sm <?= $estado === '' ? 'btn-dark' : 'btn-outline-secondary' ?>" href="pagos.php">Todos</a><?php foreach ($validos as $e): ?><a class="btn btn-sm <?= $estado === $e ? 'btn-dark' : 'btn-outline-secondary' ?>" href="?estado=<?= $e ?>"><?= ucfirst($e) ?></a><?php endforeach; ?></div>
                <div class="card-admin">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Operación</th>
                                    <th>Veterinario</th>
                                    <th>Medio</th>
                                    <th>Importe</th>
                                    <th>Estado</th>
                                    <th>Modo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagos as $p): ?><tr>
                                        <td><?= date('d/m/Y H:i', strtotime($p['fecha_operacion'])) ?></td>
                                        <td><?= htmlspecialchars($p['numero_operacion']) ?></td>
                                        <td><a href="ficha_veterinario.php?id=<?= (int)$p['id_veterinario'] ?>"><?= htmlspecialchars($p['nombre'] . ' ' . $p['apellido']) ?></a></td>
                                        <td><?= htmlspecialchars(ucfirst($p['medio_pago'])) ?></td>
                                        <td><?= formatoDinero((float)$p['importe']) ?></td>
                                        <td><span class="badge <?= $p['estado'] === 'aprobado' ? 'text-bg-success' : ($p['estado'] === 'pendiente' ? 'text-bg-warning' : 'text-bg-danger') ?>"><?= htmlspecialchars(ucfirst($p['estado'])) ?></span></td>
                                        <td><?= (int)$p['modo_demo'] === 1 ? 'Demo' : 'Manual' ?></td>
                                    </tr><?php endforeach; ?>
                                <?php if (!$pagos): ?><tr>
                                        <td colspan="7" class="text-center texto-suave py-4">No hay pagos registrados.</td>
                                    </tr><?php endif; ?></tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>