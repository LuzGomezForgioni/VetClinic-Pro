<?php
session_start();
require_once '../config/seguridad.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
}


require_once '../config/conexion.php';
require_once '../config/auditoria_admin.php';

if (!empty($_SESSION['admin_logueado'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM administradores WHERE email = ? AND activo = 1 LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_logueado'] = true;
        $_SESSION['admin_id'] = (int)$admin['id_administrador'];
        $_SESSION['admin_nombre'] = $admin['nombre'];
        $_SESSION['admin_apellido'] = $admin['apellido'];
        registrarAuditoriaAdmin($conn, 'inicio_sesion', 'administrador', (int)$admin['id_administrador'], 'Inicio de sesión correcto.');
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Email o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Superadmin · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos/admin.css">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="text-center mb-4"><img src="../img/VetClinic Pro.png" alt="Logo" class="admin-logo-acceso" >
                <h1 class="h3 fw-bold mt-3">Administración General</h1>
                <p class="texto-suave">VetClinic Pro</p>
            </div>
            <?php if (($_GET['estado'] ?? '') === 'inactivo'): ?><div class="alert alert-warning">Tu cuenta de Superadmin está desactivada. Contactá a otro administrador activo.</div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-4"><label class="form-label">Contraseña</label><input type="password" name="password" class="form-control" required></div><button class="btn-admin w-100 justify-content-center">Ingresar</button>
            </form>
            <div class="text-center mt-3"><a href="../index.php" class="small">Volver a VetClinic Pro</a></div>
        </div>
    </div>
</body>
</html>