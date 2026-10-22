<?php
session_start();
require_once '_auth.php';
require_once '../config/conexion.php';
require_once '../config/suscripciones.php';
$plan = obtenerPlanProfesional($conn);
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $plan) {
    $precio = (float)str_replace(',', '.', (string)($_POST['precio'] ?? 0));
    $dias = (int)($_POST['dias_tolerancia'] ?? 5);
    if ($precio > 0 && $dias >= 0 && $dias <= 30) {
        $stmt = $conn->prepare("UPDATE planes SET precio_mensual=?,dias_tolerancia=? WHERE id_plan=?");
        $id = (int)$plan['id_plan'];
        $stmt->bind_param('dii', $precio, $dias, $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = 'Plan actualizado correctamente.';
        $plan = obtenerPlanProfesional($conn);
    }
}
$seccionActiva = 'plan';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Plan profesional · Superadmin</title>
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
                        <h1 class="h3 fw-bold mb-1">Plan profesional</h1>
                        <div class="texto-suave">Precio mensual y período de tolerancia.</div>
                    </div><a target="_blank" class="btn-admin" href="../suscripciones/plan_profesional.php">Ver página pública</a>
                </div>
                <div class="card-admin admin-plan-ancho" ><?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?><h2 class="h5 fw-bold"><?= htmlspecialchars($plan['nombre'] ?? 'Plan') ?></h2>
                    <form method="POST" class="row g-3 mt-1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="col-md-7"><label class="form-label">Precio mensual (ARS)</label><input type="number" step="0.01" min="1" class="form-control" name="precio" value="<?= htmlspecialchars((string)($plan['precio_mensual'] ?? 30000)) ?>" required></div>
                        <div class="col-md-5"><label class="form-label">Días de tolerancia</label><input type="number" min="0" max="30" class="form-control" name="dias_tolerancia" value="<?= (int)($plan['dias_tolerancia'] ?? 5) ?>" required></div>
                        <div class="col-12"><button class="btn-admin">Guardar cambios</button></div>
                    </form>
                    <div class="small texto-suave mt-3">El precio actualizado se utilizará en los próximos pagos. Los pagos ya registrados conservan su importe histórico.</div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>