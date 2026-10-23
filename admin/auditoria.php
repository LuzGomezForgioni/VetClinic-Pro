<?php
require_once '_auth.php';
require_once '../config/auditoria_admin.php';

$tablaAuditoriaDisponible = false;
$comprobarTabla = $conn->query("SHOW TABLES LIKE 'auditoria_administradores'");
if ($comprobarTabla && $comprobarTabla->num_rows > 0) {
    $tablaAuditoriaDisponible = true;
}

$idFiltro = max(0, (int) ($_GET['id_administrador'] ?? 0));
$accionFiltro = trim((string) ($_GET['accion'] ?? ''));
$desde = trim((string) ($_GET['desde'] ?? ''));
$hasta = trim((string) ($_GET['hasta'] ?? ''));

if ($desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = '';
if ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = '';
if (mb_strlen($accionFiltro) > 100) $accionFiltro = '';

$registros = [];
$acciones = [];

if ($tablaAuditoriaDisponible) {
    $sql = "
        SELECT
            a.*,
            ad.nombre,
            ad.apellido,
            ad.email
        FROM auditoria_administradores a
        LEFT JOIN administradores ad
            ON ad.id_administrador = a.id_administrador
        WHERE (? = 0 OR a.id_administrador = ?)
          AND (? = '' OR a.accion = ?)
          AND (? = '' OR a.fecha >= CONCAT(?, ' 00:00:00'))
          AND (? = '' OR a.fecha <= CONCAT(?, ' 23:59:59'))
        ORDER BY a.fecha DESC, a.id_auditoria DESC
        LIMIT 500
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'iissssss',
        $idFiltro,
        $idFiltro,
        $accionFiltro,
        $accionFiltro,
        $desde,
        $desde,
        $hasta,
        $hasta
    );
    $stmt->execute();
    $registros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$resAdmins = $conn->query("SELECT id_administrador, nombre, apellido FROM administradores ORDER BY apellido, nombre");
$admins = $resAdmins ? $resAdmins->fetch_all(MYSQLI_ASSOC) : [];
if ($tablaAuditoriaDisponible) {
    $resAcciones = $conn->query("SELECT DISTINCT accion FROM auditoria_administradores ORDER BY accion");
    $acciones = $resAcciones ? array_column($resAcciones->fetch_all(MYSQLI_ASSOC), 'accion') : [];
}
$seccionActiva = 'auditoria';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Actividad administrativa · VetClinic Pro</title>
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
                    <div class="admin-kicker"><i class="bi bi-clock-history"></i> Trazabilidad</div>
                    <h1 class="h3 fw-bold mb-1">Actividad administrativa</h1>
                    <div class="texto-suave">Consultá quién realizó cada acción sensible dentro del panel.</div>
                </div>
                <a href="administradores.php" class="btn btn-outline-secondary"><i class="bi bi-people"></i> Administradores</a>
            </div>

            <?php if (!$tablaAuditoriaDisponible): ?>
                <div class="alert alert-warning">Para comenzar a registrar actividad, ejecutá una vez <strong>migraciones/multi_superadmin_auditoria.sql</strong> en phpMyAdmin.</div>
            <?php endif; ?>

            <section class="card-admin admin-audit-filters mb-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3"><label class="form-label">Administrador</label><select class="form-select" name="id_administrador"><option value="0">Todos</option><?php foreach ($admins as $a): ?><option value="<?= (int)$a['id_administrador'] ?>" <?= $idFiltro === (int)$a['id_administrador'] ? 'selected' : '' ?>><?= htmlspecialchars($a['nombre'] . ' ' . $a['apellido']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Acción</label><select class="form-select" name="accion"><option value="">Todas</option><?php foreach ($acciones as $a): ?><option value="<?= htmlspecialchars($a) ?>" <?= $accionFiltro === $a ? 'selected' : '' ?>><?= htmlspecialchars(textoAccionAuditoria($a)) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><label class="form-label">Desde</label><input type="date" class="form-control" name="desde" value="<?= htmlspecialchars($desde) ?>"></div>
                    <div class="col-md-2"><label class="form-label">Hasta</label><input type="date" class="form-control" name="hasta" value="<?= htmlspecialchars($hasta) ?>"></div>
                    <div class="col-md-2 d-flex gap-2"><button class="btn-admin flex-grow-1 justify-content-center">Filtrar</button><a class="btn btn-outline-secondary" href="auditoria.php" title="Restablecer"><i class="bi bi-arrow-counterclockwise"></i></a></div>
                </form>
            </section>

            <section class="card-admin p-0 overflow-hidden">
                <div class="admin-card-head"><div><h2>Historial</h2><p>Se muestran hasta 500 acciones, ordenadas desde la más reciente.</p></div><span class="admin-audit-count"><?= count($registros) ?> registros</span></div>
                <div class="table-responsive">
                    <table class="table admin-audit-table align-middle mb-0">
                        <thead><tr><th>Fecha</th><th>Administrador</th><th>Acción</th><th>Detalle</th><th>Referencia</th><th>IP</th></tr></thead>
                        <tbody>
                        <?php foreach ($registros as $r): ?>
                            <tr>
                                <td><strong><?= date('d/m/Y', strtotime($r['fecha'])) ?></strong><small><?= date('H:i:s', strtotime($r['fecha'])) ?></small></td>
                                <td><strong><?= htmlspecialchars(trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')) ?: 'Administrador eliminado') ?></strong><small><?= htmlspecialchars($r['email'] ?? '—') ?></small></td>
                                <td><span class="admin-audit-action"><?= htmlspecialchars(textoAccionAuditoria($r['accion'])) ?></span></td>
                                <td><?= htmlspecialchars($r['detalle'] ?: '—') ?></td>
                                <td><span class="admin-audit-ref"><?= htmlspecialchars($r['entidad']) ?><?= $r['id_entidad'] !== null ? ' #' . (int)$r['id_entidad'] : '' ?></span></td>
                                <td><span class="admin-audit-ip"><?= htmlspecialchars($r['ip'] ?: '—') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$registros): ?><tr><td colspan="6"><div class="admin-audit-empty"><i class="bi bi-clock-history"></i><strong>No hay actividad para estos filtros</strong><span>Las nuevas acciones administrativas aparecerán acá.</span></div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="admin-audit-note"><i class="bi bi-shield-check"></i><span>El historial registra acciones de administración, pero nunca contraseñas, tokens ni datos bancarios.</span></div>
        </main>
    </div>
</div>
</body>
</html>
