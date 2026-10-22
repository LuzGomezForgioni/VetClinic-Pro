<?php
session_start();
require_once '_auth.php';
require_once '../config/conexion.php';
require_once '../config/suscripciones.php';
$idVet = (int)($_GET['id'] ?? 0);
if ($idVet <= 0) {
    header('Location:veterinarios.php');
    exit;
}
$sus = actualizarEstadoSuscripcion($conn, $idVet);
$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string)($_POST['accion'] ?? '');
    try {
        if ($accion === 'pago_manual') {
            registrarPagoDemo($conn, $idVet, 'manual', 'aprobado', 'Pago registrado manualmente por Superadmin.');
            $msg = 'Pago manual registrado y suscripción actualizada.';
        } elseif ($accion === 'extender' && $sus) {
            $dias = max(1, min(30, (int)($_POST['dias'] ?? 5)));
            $idSus = (int)$sus['id_suscripcion'];
            $stmt = $conn->prepare("UPDATE suscripciones SET fecha_fin_tolerancia=DATE_ADD(COALESCE(fecha_fin_tolerancia,CURDATE()),INTERVAL ? DAY), estado=CASE WHEN estado='suspendida' THEN 'atrasada' ELSE estado END, motivo_suspension=NULL WHERE id_suscripcion=?");
            $stmt->bind_param('ii', $dias, $idSus);
            $stmt->execute();
            $stmt->close();
            $msg = 'Tolerancia extendida.';
        } elseif ($accion === 'suspender' && $sus) {
            $idSus = (int)$sus['id_suscripcion'];
            $motivo = trim((string)($_POST['motivo'] ?? 'Suspensión manual por administración.'));
            $stmt = $conn->prepare("UPDATE suscripciones SET estado='suspendida',suspension_manual=1,motivo_suspension=? WHERE id_suscripcion=?");
            $stmt->bind_param('si', $motivo, $idSus);
            $stmt->execute();
            $stmt->close();
            $msg = 'Suscripción suspendida manualmente.';
        } elseif ($accion === 'reactivar' && $sus) {
            $idSus = (int)$sus['id_suscripcion'];
            $dias = (int)$sus['dias_tolerancia'];
            $stmt = $conn->prepare("UPDATE suscripciones SET estado='atrasada', suspension_manual=0, fecha_fin_tolerancia=DATE_ADD(CURDATE(),INTERVAL ? DAY), motivo_suspension=NULL WHERE id_suscripcion=?");
            $stmt->bind_param('ii', $dias, $idSus);
            $stmt->execute();
            $stmt->close();
            $msg = 'Acceso reactivado temporalmente; el pago sigue pendiente.';
        } elseif ($accion === 'baja') {
            $stmt = $conn->prepare("UPDATE veterinarios SET activo=0 WHERE id_veterinario=?");
            $stmt->bind_param('i', $idVet);
            $stmt->execute();
            $stmt->close();
            if ($sus) {
                $idSus = (int)$sus['id_suscripcion'];
                $stmt = $conn->prepare("UPDATE suscripciones SET estado='cancelada',cancelacion_fin_periodo=1,suspension_manual=1,fecha_cancelacion=NOW(),motivo_suspension='Baja administrativa.' WHERE id_suscripcion=?");
                $stmt->bind_param('i', $idSus);
                $stmt->execute();
                $stmt->close();
            }
            $msg = 'Cuenta profesional dada de baja.';
        } elseif ($accion === 'habilitar') {
            $stmt = $conn->prepare("UPDATE veterinarios SET activo=1 WHERE id_veterinario=?");
            $stmt->bind_param('i', $idVet);
            $stmt->execute();
            $stmt->close();
            $msg = 'Cuenta profesional habilitada.';
        }
    } catch (Throwable $e) {
        $err = 'No se pudo completar la acción.';
    }
    $sus = actualizarEstadoSuscripcion($conn, $idVet);
}
$stmt = $conn->prepare("SELECT * FROM veterinarios WHERE id_veterinario=? LIMIT 1");
$stmt->bind_param('i', $idVet);
$stmt->execute();
$vet = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$vet) {
    header('Location:veterinarios.php');
    exit;
}
$stmt = $conn->prepare("SELECT * FROM pagos WHERE id_veterinario=? ORDER BY fecha_operacion DESC LIMIT 12");
$stmt->bind_param('i', $idVet);
$stmt->execute();
$pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$seccionActiva = 'veterinarios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Ficha veterinario · Superadmin</title>
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
                    <div><a href="veterinarios.php" class="small">← Volver</a>
                        <h1 class="h3 fw-bold mb-1 mt-2"><?= htmlspecialchars($vet['nombre'] . ' ' . $vet['apellido']) ?></h1>
                        <div class="texto-suave"><?= htmlspecialchars($vet['especialidad']) ?> · MP <?= htmlspecialchars($vet['matricula']) ?></div>
                    </div>
                </div>
                <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?><?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card-admin">
                            <div class="d-flex justify-content-between">
                                <h2 class="h5 fw-bold">Datos profesionales</h2><span class="badge <?= (int)$vet['activo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int)$vet['activo'] === 1 ? 'Cuenta habilitada' : 'Cuenta deshabilitada' ?></span>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <div class="small texto-suave">Email</div><strong><?= htmlspecialchars($vet['email']) ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <div class="small texto-suave">Teléfono</div><strong><?= htmlspecialchars($vet['telefono'] ?? '-') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <div class="small texto-suave">Localidad</div><strong><?= htmlspecialchars($vet['localidad'] ?? '-') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <div class="small texto-suave">Alta</div><strong><?= !empty($vet['fecha_registro']) ? date('d/m/Y', strtotime($vet['fecha_registro'])) : '-' ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="card-admin mt-4">
                            <h2 class="h5 fw-bold">Historial de pagos</h2>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Operación</th>
                                            <th>Importe</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($pagos as $p): ?><tr>
                                                <td><?= date('d/m/Y', strtotime($p['fecha_operacion'])) ?></td>
                                                <td><?= htmlspecialchars($p['numero_operacion']) ?></td>
                                                <td><?= formatoDinero((float)$p['importe']) ?></td>
                                                <td><?= htmlspecialchars(ucfirst($p['estado'])) ?></td>
                                            </tr><?php endforeach; ?><?php if (!$pagos): ?><tr>
                                                <td colspan="4" class="texto-suave text-center py-3">Sin pagos.</td>
                                            </tr><?php endif; ?></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card-admin">
                            <h2 class="h5 fw-bold">Suscripción</h2><?php if ($sus): ?><div class="d-flex justify-content-between align-items-center mb-3"><span><?= htmlspecialchars($sus['nombre_plan']) ?></span><span class="badge-estado <?= htmlspecialchars($sus['estado']) ?>"><?= htmlspecialchars(etiquetaEstadoSuscripcion($sus['estado'])) ?></span></div>
                                <div class="d-flex justify-content-between py-2 border-bottom"><span>Precio</span><strong><?= formatoDinero((float)$sus['precio_mensual']) ?></strong></div>
                                <div class="d-flex justify-content-between py-2 border-bottom"><span>Vencimiento</span><strong><?= $sus['fecha_vencimiento'] ? date('d/m/Y', strtotime($sus['fecha_vencimiento'])) : '-' ?></strong></div>
                                <div class="d-flex justify-content-between py-2"><span>Tolerancia hasta</span><strong><?= $sus['fecha_fin_tolerancia'] ? date('d/m/Y', strtotime($sus['fecha_fin_tolerancia'])) : '-' ?></strong></div>
                                <hr>
                                <form method="POST" class="mb-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="pago_manual"><button class="btn-admin w-100 justify-content-center" onclick="return confirm('¿Registrar una mensualidad como pagada?')"><i class="bi bi-cash"></i> Registrar pago manual</button></form>
                                <form method="POST" class="row g-2 mb-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="extender">
                                    <div class="col-5"><input type="number" class="form-control" name="dias" value="5" min="1" max="30"></div>
                                    <div class="col-7"><button class="btn btn-outline-warning w-100 h-100">Extender tolerancia</button></div>
                                </form>
                                <?php if ($sus['estado'] !== 'suspendida'): ?><form method="POST" class="mb-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="suspender"><input type="hidden" name="motivo" value="Suspensión manual por administración."><button class="btn btn-outline-danger w-100" onclick="return confirm('¿Suspender esta suscripción?')">Suspender manualmente</button></form><?php else: ?><form method="POST" class="mb-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="reactivar"><button class="btn btn-outline-success w-100">Reactivar temporalmente</button></form><?php endif; ?>
                            <?php else: ?><div class="alert alert-warning">Sin suscripción asociada.</div><?php endif; ?>
                            <hr><?php if ((int)$vet['activo'] === 1): ?><form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="baja"><button class="btn btn-outline-dark w-100" onclick="return confirm('¿Dar de baja esta cuenta profesional?')">Dar de baja</button></form><?php else: ?><form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="habilitar"><button class="btn btn-outline-primary w-100">Habilitar cuenta</button></form><?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>