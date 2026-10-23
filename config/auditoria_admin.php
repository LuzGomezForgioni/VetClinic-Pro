<?php
/**
 * Registra acciones relevantes realizadas desde el panel Superadmin.
 * No guarda contraseñas, tokens ni datos bancarios.
 */
function registrarAuditoriaAdmin(
    mysqli $conn,
    string $accion,
    string $entidad,
    ?int $idEntidad = null,
    ?string $detalle = null
): void {
    $idAdministrador = (int) ($_SESSION['admin_id'] ?? 0);

    if ($idAdministrador <= 0) {
        return;
    }

    $accion = mb_substr(trim($accion), 0, 100);
    $entidad = mb_substr(trim($entidad), 0, 80);
    $detalle = $detalle !== null ? mb_substr(trim($detalle), 0, 2000) : null;
    $ip = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    try {
        $stmt = $conn->prepare("\n            INSERT INTO auditoria_administradores\n                (id_administrador, accion, entidad, id_entidad, detalle, ip, user_agent)\n            VALUES (?, ?, ?, ?, ?, ?, ?)\n        ");
        $stmt->bind_param(
            'ississs',
            $idAdministrador,
            $accion,
            $entidad,
            $idEntidad,
            $detalle,
            $ip,
            $userAgent
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // La auditoría nunca debe bloquear la operación principal.
        // Si la tabla todavía no existe, la acción continúa normalmente.
    }
}

function textoAccionAuditoria(string $accion): string
{
    return match ($accion) {
        'inicio_sesion' => 'Inició sesión',
        'cierre_sesion' => 'Cerró sesión',
        'crear_administrador' => 'Creó un Superadmin',
        'activar_administrador' => 'Activó un Superadmin',
        'desactivar_administrador' => 'Desactivó un Superadmin',
        'cambiar_password_propia' => 'Cambió su contraseña',
        'actualizar_plan' => 'Actualizó el plan profesional',
        'pago_manual' => 'Registró un pago manual',
        'extender_tolerancia' => 'Extendió la tolerancia',
        'suspender_suscripcion' => 'Suspendió una suscripción',
        'reactivar_suscripcion' => 'Reactivó una suscripción',
        'baja_veterinario' => 'Dio de baja un veterinario',
        'habilitar_veterinario' => 'Habilitó un veterinario',
        default => ucfirst(str_replace('_', ' ', $accion))
    };
}
