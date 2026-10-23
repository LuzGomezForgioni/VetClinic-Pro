<?php
require_once '_auth.php';
require_once '../config/auditoria_admin.php';

$errores = [];
$mensaje = $_SESSION['mensaje_admins'] ?? '';
unset($_SESSION['mensaje_admins']);

$idActual = (int) $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'crear') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $apellido = trim((string) ($_POST['apellido'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');

        if ($nombre === '' || $apellido === '') {
            $errores[] = 'Completá nombre y apellido.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Ingresá un correo electrónico válido.';
        }
        if (mb_strlen($password) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($password !== $password2) {
            $errores[] = 'Las contraseñas no coinciden.';
        }

        if (!$errores) {
            $stmt = $conn->prepare("SELECT id_administrador FROM administradores WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $existe = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existe) {
                $errores[] = 'Ya existe un administrador con ese correo electrónico.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO administradores (nombre, apellido, email, password, activo) VALUES (?, ?, ?, ?, 1)");
                $stmt->bind_param('ssss', $nombre, $apellido, $email, $hash);
                $stmt->execute();
                $nuevoId = (int) $conn->insert_id;
                $stmt->close();

                registrarAuditoriaAdmin(
                    $conn,
                    'crear_administrador',
                    'administrador',
                    $nuevoId,
                    'Superadmin creado: ' . $nombre . ' ' . $apellido . ' (' . $email . ').'
                );

                $_SESSION['mensaje_admins'] = 'Superadministrador creado correctamente.';
                header('Location: administradores.php');
                exit;
            }
        }
    }

    if ($accion === 'cambiar_estado') {
        $idObjetivo = (int) ($_POST['id_administrador'] ?? 0);

        if ($idObjetivo <= 0) {
            $errores[] = 'Administrador no válido.';
        } elseif ($idObjetivo === $idActual) {
            $errores[] = 'No podés desactivar tu propia cuenta desde esta pantalla.';
        } else {
            $stmt = $conn->prepare("SELECT id_administrador, nombre, apellido, email, activo FROM administradores WHERE id_administrador = ? LIMIT 1");
            $stmt->bind_param('i', $idObjetivo);
            $stmt->execute();
            $objetivo = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$objetivo) {
                $errores[] = 'El administrador seleccionado no existe.';
            } else {
                $nuevoEstado = (int) $objetivo['activo'] === 1 ? 0 : 1;

                if ($nuevoEstado === 0) {
                    $res = $conn->query("SELECT COUNT(*) AS total FROM administradores WHERE activo = 1");
                    $activos = $res ? (int) ($res->fetch_assoc()['total'] ?? 0) : 0;
                    if ($activos <= 1) {
                        $errores[] = 'Debe quedar al menos un Superadmin activo.';
                    }
                }

                if (!$errores) {
                    $stmt = $conn->prepare("UPDATE administradores SET activo = ? WHERE id_administrador = ?");
                    $stmt->bind_param('ii', $nuevoEstado, $idObjetivo);
                    $stmt->execute();
                    $stmt->close();

                    registrarAuditoriaAdmin(
                        $conn,
                        $nuevoEstado === 1 ? 'activar_administrador' : 'desactivar_administrador',
                        'administrador',
                        $idObjetivo,
                        ($nuevoEstado === 1 ? 'Se activó a ' : 'Se desactivó a ') . trim($objetivo['nombre'] . ' ' . $objetivo['apellido']) . ' (' . $objetivo['email'] . ').'
                    );

                    $_SESSION['mensaje_admins'] = $nuevoEstado === 1
                        ? 'Superadministrador activado.'
                        : 'Superadministrador desactivado.';
                    header('Location: administradores.php');
                    exit;
                }
            }
        }
    }

    if ($accion === 'cambiar_password') {
        $actual = (string) ($_POST['password_actual'] ?? '');
        $nueva = (string) ($_POST['password_nueva'] ?? '');
        $nueva2 = (string) ($_POST['password_nueva2'] ?? '');

        $stmt = $conn->prepare("SELECT password FROM administradores WHERE id_administrador = ? LIMIT 1");
        $stmt->bind_param('i', $idActual);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$fila || !password_verify($actual, (string) $fila['password'])) {
            $errores[] = 'La contraseña actual no es correcta.';
        }
        if (mb_strlen($nueva) < 8) {
            $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        }
        if ($nueva !== $nueva2) {
            $errores[] = 'Las nuevas contraseñas no coinciden.';
        }

        if (!$errores) {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE administradores SET password = ? WHERE id_administrador = ?");
            $stmt->bind_param('si', $hash, $idActual);
            $stmt->execute();
            $stmt->close();

            registrarAuditoriaAdmin(
                $conn,
                'cambiar_password_propia',
                'administrador',
                $idActual,
                'El administrador cambió su propia contraseña.'
            );

            $_SESSION['mensaje_admins'] = 'Tu contraseña se actualizó correctamente.';
            header('Location: administradores.php');
            exit;
        }
    }
}

$res = $conn->query("SELECT id_administrador, nombre, apellido, email, activo, fecha_registro FROM administradores ORDER BY activo DESC, apellido ASC, nombre ASC");
$administradores = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$total = count($administradores);
$activos = count(array_filter($administradores, fn($a) => (int) $a['activo'] === 1));
$seccionActiva = 'administradores';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Administradores · VetClinic Pro</title>
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
            <div class="admin-top admin-owners-header">
                <div>
                    <div class="admin-kicker"><i class="bi bi-shield-lock"></i> Seguridad y acceso</div>
                    <h1 class="h3 fw-bold mb-1">Superadministradores</h1>
                    <div class="texto-suave">Cada dueño del proyecto debe utilizar su propia cuenta.</div>
                </div>
                <a href="auditoria.php" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> Ver actividad</a>
            </div>

            <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
            <?php foreach ($errores as $error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3"><div class="card-admin admin-owner-metric"><i class="bi bi-people"></i><div><span>Total</span><strong><?= $total ?></strong><small>cuentas administrativas</small></div></div></div>
                <div class="col-sm-6 col-xl-3"><div class="card-admin admin-owner-metric"><i class="bi bi-person-check"></i><div><span>Activas</span><strong><?= $activos ?></strong><small>con acceso al panel</small></div></div></div>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-xl-8">
                    <section class="card-admin p-0 overflow-hidden">
                        <div class="admin-card-head">
                            <div><h2>Administradores registrados</h2><p>Todos tienen permisos completos de Superadmin.</p></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table admin-owner-table align-middle mb-0">
                                <thead><tr><th>Administrador</th><th>Correo</th><th>Alta</th><th>Estado</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($administradores as $a): ?>
                                    <tr>
                                        <td><div class="admin-owner-person"><div class="admin-owner-avatar"><?= htmlspecialchars(strtoupper(mb_substr($a['nombre'],0,1) . mb_substr($a['apellido'],0,1))) ?></div><div><strong><?= htmlspecialchars($a['nombre'] . ' ' . $a['apellido']) ?></strong><?php if ((int)$a['id_administrador'] === $idActual): ?><small>Tu cuenta</small><?php else: ?><small>Superadmin</small><?php endif; ?></div></div></td>
                                        <td><?= htmlspecialchars($a['email']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($a['fecha_registro'])) ?></td>
                                        <td><span class="admin-owner-status <?= (int)$a['activo'] === 1 ? 'activo' : 'inactivo' ?>"><?= (int)$a['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                                        <td class="text-end">
                                            <?php if ((int)$a['id_administrador'] !== $idActual): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="id_administrador" value="<?= (int)$a['id_administrador'] ?>">
                                                    <button class="btn btn-sm <?= (int)$a['activo'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>" onclick="return confirm('¿Confirmás este cambio de estado?')"><?= (int)$a['activo'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                                </form>
                                            <?php else: ?><span class="admin-owner-self">Sesión actual</span><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <div class="col-xl-4">
                    <section class="card-admin admin-owner-form mb-4">
                        <div class="admin-owner-form-icon"><i class="bi bi-person-plus"></i></div>
                        <h2>Agregar Superadmin</h2>
                        <p>Creá una cuenta independiente para el otro dueño del proyecto.</p>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="accion" value="crear">
                            <div class="col-md-6 col-xl-12"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required maxlength="80"></div>
                            <div class="col-md-6 col-xl-12"><label class="form-label">Apellido</label><input class="form-control" name="apellido" required maxlength="80"></div>
                            <div class="col-12"><label class="form-label">Correo electrónico</label><input type="email" class="form-control" name="email" required maxlength="150"></div>
                            <div class="col-12"><label class="form-label">Contraseña</label><input type="password" class="form-control" name="password" required minlength="8" autocomplete="new-password"></div>
                            <div class="col-12"><label class="form-label">Repetir contraseña</label><input type="password" class="form-control" name="password2" required minlength="8" autocomplete="new-password"></div>
                            <div class="col-12"><button class="btn-admin w-100 justify-content-center"><i class="bi bi-person-plus"></i> Crear Superadmin</button></div>
                        </form>
                    </section>

                    <section class="card-admin admin-owner-form">
                        <div class="admin-owner-form-icon"><i class="bi bi-key"></i></div>
                        <h2>Cambiar mi contraseña</h2>
                        <p>Este cambio afecta únicamente a tu propia cuenta.</p>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="accion" value="cambiar_password">
                            <div class="col-12"><label class="form-label">Contraseña actual</label><input type="password" class="form-control" name="password_actual" required autocomplete="current-password"></div>
                            <div class="col-12"><label class="form-label">Nueva contraseña</label><input type="password" class="form-control" name="password_nueva" required minlength="8" autocomplete="new-password"></div>
                            <div class="col-12"><label class="form-label">Repetir nueva contraseña</label><input type="password" class="form-control" name="password_nueva2" required minlength="8" autocomplete="new-password"></div>
                            <div class="col-12"><button class="btn btn-outline-primary w-100"><i class="bi bi-key"></i> Actualizar contraseña</button></div>
                        </form>
                    </section>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
