<?php
session_start();
require_once '_auth.php';
require_once '../config/conexion.php';
require_once '../config/suscripciones.php';
$res = $conn->query("SELECT DATE_FORMAT(fecha_operacion,'%Y-%m') AS periodo,COUNT(*) AS cantidad,SUM(importe) AS total FROM pagos WHERE estado='aprobado' GROUP BY DATE_FORMAT(fecha_operacion,'%Y-%m') ORDER BY periodo DESC LIMIT 12");
$meses = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$res = $conn->query("SELECT medio_pago,COUNT(*) AS cantidad,SUM(importe) AS total FROM pagos WHERE estado='aprobado' GROUP BY medio_pago ORDER BY total DESC");
$medios = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$totalHistorico = 0;
foreach ($meses as $m) {
    $totalHistorico += (float)$m['total'];
}
$seccionActiva = 'finanzas';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Finanzas · Superadmin</title>
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
                        <h1 class="h3 fw-bold mb-1">Finanzas</h1>
                        <div class="texto-suave">Resumen basado en pagos aprobados de la demostración.</div>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card-admin">
                            <h2 class="h5 fw-bold">Ingresos mensuales</h2>
                            <div class="table-responsive mt-3">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Mes</th>
                                            <th>Pagos</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($meses as $m): ?><tr>
                                                <td><?= htmlspecialchars($m['periodo']) ?></td>
                                                <td><?= (int)$m['cantidad'] ?></td>
                                                <td><strong><?= formatoDinero((float)$m['total']) ?></strong></td>
                                            </tr><?php endforeach; ?><?php if (!$meses): ?><tr>
                                                <td colspan="3" class="text-center texto-suave py-4">Sin movimientos.</td>
                                            </tr><?php endif; ?></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card-admin mb-4">
                            <div class="small texto-suave">Ingresos mostrados (hasta 12 meses)</div>
                            <div class="metric"><?= formatoDinero($totalHistorico) ?></div>
                            <div class="small texto-suave">Datos ficticios del entorno académico.</div>
                        </div>
                        <div class="card-admin">
                            <h2 class="h5 fw-bold">Por medio de pago</h2><?php foreach ($medios as $m): ?><div class="d-flex justify-content-between py-2 border-bottom"><span><?= htmlspecialchars(ucfirst($m['medio_pago'])) ?></span><strong><?= formatoDinero((float)$m['total']) ?></strong></div><?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>

</html>