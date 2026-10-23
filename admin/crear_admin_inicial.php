<?php
session_start();
require_once '../config/conexion.php';
require_once '../config/seguridad.php';

$total = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM administradores");
if ($res) $total = (int)($res->fetch_assoc()['total'] ?? 0);

$errores = [];
$creado = false;

if ($total > 0) {
    $bloqueado = true;
} else {
    $bloqueado = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        exigirCsrf();
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $apellido = trim((string)($_POST['apellido'] ?? ''));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');

        if ($nombre === '' || $apellido === '') $errores[] = 'Completá nombre y apellido.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingresá un email válido.';
        if (mb_strlen($password) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($password !== $password2) $errores[] = 'Las contraseñas no coinciden.';

        if (!$errores) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO administradores (nombre, apellido, email, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $nombre, $apellido, $email, $hash);
            $stmt->execute();
            $stmt->close();
            $creado = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Crear Superadmin · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos/admin.css">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <h1 class="h3 fw-bold">Crear Superadministrador</h1>
            <p class="texto-suave">Este asistente crea solamente el primer Superadmin. Los demás se agregan luego desde Administración → Administradores.</p>
            <?php if ($bloqueado): ?><div class="alert alert-warning">Ya existe un administrador. Por seguridad, este asistente está bloqueado.</div><a href="login.php" class="btn-admin">Ir al login</a>
            <?php elseif ($creado): ?><div class="alert alert-success">Administrador creado correctamente. Ya podés iniciar sesión.</div><a href="login.php" class="btn-admin">Iniciar sesión</a>
                <p class="small texto-suave mt-3 mb-0">Después ingresá al panel. Desde Administración → Administradores podés crear la cuenta independiente del segundo dueño.</p>
            <?php else: ?>
                <?php foreach ($errores as $e): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
                    <div class="col-md-6"><label class="form-label">Apellido</label><input class="form-control" name="apellido" required></div>
                    <div class="col-12"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
                    <div class="col-12"><label class="form-label">Contraseña</label><input type="password" class="form-control" name="password" required minlength="8"></div>
                    <div class="col-12"><label class="form-label">Repetir contraseña</label><input type="password" class="form-control" name="password2" required minlength="8"></div>
                    <div class="col-12"><button class="btn-admin w-100 justify-content-center">Crear administrador</button></div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>