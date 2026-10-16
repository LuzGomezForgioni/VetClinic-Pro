<?php
$seccionActiva = $seccionActiva ?? '';
$nombreAdmin = trim(($_SESSION['admin_nombre'] ?? '') . ' ' . ($_SESSION['admin_apellido'] ?? ''));
?>
<div class="admin-sidebar">
    <a href="dashboard.php" class="admin-brand">
        <img src="../img/VetClinic Pro.png" alt="VetClinic Pro">
        <div><div>VetClinic Pro</div><small style="font-weight:500;opacity:.7">Superadministrador</small></div>
    </a>

    <div class="admin-section">General</div>
    <a class="admin-link <?= $seccionActiva==='dashboard'?'activo':'' ?>" href="dashboard.php"><i class="bi bi-grid"></i> Dashboard</a>
    <a class="admin-link <?= $seccionActiva==='veterinarios'?'activo':'' ?>" href="veterinarios.php"><i class="bi bi-person-badge"></i> Veterinarios</a>

    <div class="admin-section">Comercial</div>
    <a class="admin-link <?= $seccionActiva==='suscripciones'?'activo':'' ?>" href="suscripciones.php"><i class="bi bi-credit-card-2-front"></i> Suscripciones</a>
    <a class="admin-link <?= $seccionActiva==='pagos'?'activo':'' ?>" href="pagos.php"><i class="bi bi-cash-stack"></i> Pagos</a>
    <a class="admin-link <?= $seccionActiva==='finanzas'?'activo':'' ?>" href="finanzas.php"><i class="bi bi-bar-chart"></i> Finanzas</a>
    <a class="admin-link <?= $seccionActiva==='plan'?'activo':'' ?>" href="plan.php"><i class="bi bi-tags"></i> Plan profesional</a>

    <div class="admin-section">Cuenta</div>
    <div class="px-2 small mb-2" style="opacity:.75"><?= htmlspecialchars($nombreAdmin ?: 'Administrador') ?></div>
    <a class="admin-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
</div>
