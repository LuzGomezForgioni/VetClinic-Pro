<?php
session_start();
require_once '_auth.php';
require_once '../config/conexion.php';
require_once '../config/suscripciones.php';
actualizarTodasSuscripciones($conn);
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("SELECT v.*,s.estado,s.fecha_vencimiento,p.nombre AS plan_nombre FROM veterinarios v LEFT JOIN suscripciones s ON s.id_veterinario=v.id_veterinario LEFT JOIN planes p ON p.id_plan=s.id_plan WHERE v.nombre LIKE ? OR v.apellido LIKE ? OR v.email LIKE ? OR v.matricula LIKE ? ORDER BY v.apellido,v.nombre");
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $veterinarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $res = $conn->query("SELECT v.*,s.estado,s.fecha_vencimiento,p.nombre AS plan_nombre FROM veterinarios v LEFT JOIN suscripciones s ON s.id_veterinario=v.id_veterinario LEFT JOIN planes p ON p.id_plan=s.id_plan ORDER BY v.apellido,v.nombre");
    $veterinarios = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
$seccionActiva = 'veterinarios';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Veterinarios · Superadmin</title>
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
                        <h1 class="h3 fw-bold mb-1">Veterinarios</h1>
                        <div class="texto-suave">Datos profesionales y estado comercial de cada cuenta.</div>
                    </div>
                </div>
                <div class="card-admin">
                    <form class="row g-2 mb-3">
                        <div class="col-md-10"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por nombre, email o matrícula"></div>
                        <div class="col-md-2"><button class="btn-admin w-100 justify-content-center">Buscar</button></div>
                    </form>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Profesional</th>
                                    <th>Contacto</th>
                                    <th>Matrícula</th>
                                    <th>Suscripción</th>
                                    <th>Vencimiento</th>
                                    <th>Cuenta</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($veterinarios as $v): ?><tr>
                                        <td><strong><?= htmlspecialchars($v['nombre'] . ' ' . $v['apellido']) ?></strong>
                                            <div class="small texto-suave"><?= htmlspecialchars($v['especialidad']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($v['email']) ?><div class="small texto-suave"><?= htmlspecialchars($v['telefono'] ?? '') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($v['matricula']) ?></td>
                                        <td><span class="badge-estado <?= htmlspecialchars($v['estado'] ?? 'pendiente_pago') ?>"><?= htmlspecialchars(etiquetaEstadoSuscripcion($v['estado'] ?? 'pendiente_pago')) ?></span></td>
                                        <td><?= $v['fecha_vencimiento'] ? date('d/m/Y', strtotime($v['fecha_vencimiento'])) : '-' ?></td>
                                        <td><?= (int)$v['activo'] === 1 ? 'Habilitada' : 'Deshabilitada' ?></td>
                                        <td><a class="btn btn-sm btn-outline-secondary" href="ficha_veterinario.php?id=<?= (int)$v['id_veterinario'] ?>">Abrir</a></td>
                                    </tr><?php endforeach; ?>
                                <?php if (!$veterinarios): ?><tr>
                                        <td colspan="7" class="text-center texto-suave py-4">No encontramos resultados.</td>
                                    </tr><?php endif; ?></tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>

</html>