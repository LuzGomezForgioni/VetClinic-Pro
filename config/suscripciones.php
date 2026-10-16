<?php

// ============================================================
// VetClinic Pro - Funciones de suscripciones y pagos
// ============================================================

date_default_timezone_set('America/Argentina/Buenos_Aires');

function obtenerPlanProfesional(mysqli $conn): ?array
{
    $stmt = $conn->prepare("\n        SELECT *\n        FROM planes\n        WHERE codigo = 'PROFESIONAL'\n          AND activo = 1\n        LIMIT 1\n    ");
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $plan ?: null;
}

function obtenerSuscripcionVeterinario(mysqli $conn, int $idVeterinario): ?array
{
    $stmt = $conn->prepare("\n        SELECT\n            s.*,\n            p.codigo AS codigo_plan,\n            p.nombre AS nombre_plan,\n            p.precio_mensual,\n            p.dias_tolerancia\n        FROM suscripciones s\n        INNER JOIN planes p ON p.id_plan = s.id_plan\n        WHERE s.id_veterinario = ?\n        LIMIT 1\n    ");
    $stmt->bind_param('i', $idVeterinario);
    $stmt->execute();
    $suscripcion = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $suscripcion ?: null;
}

function actualizarEstadoSuscripcion(mysqli $conn, int $idVeterinario): ?array
{
    $suscripcion = obtenerSuscripcionVeterinario($conn, $idVeterinario);

    if (!$suscripcion) {
        return null;
    }

    $estadoActual = (string) $suscripcion['estado'];

    if ($estadoActual === 'pendiente_pago') {
        return $suscripcion;
    }

    // Una suspensión manual hecha por Superadmin no se levanta por fechas.
    if ((int)($suscripcion['suspension_manual'] ?? 0) === 1) {
        if ($estadoActual !== 'suspendida') {
            $stmt = $conn->prepare("UPDATE suscripciones SET estado = 'suspendida' WHERE id_suscripcion = ?");
            $idSuscripcion = (int)$suscripcion['id_suscripcion'];
            $stmt->bind_param('i', $idSuscripcion);
            $stmt->execute();
            $stmt->close();
        }
        return obtenerSuscripcionVeterinario($conn, $idVeterinario);
    }

    $hoy = new DateTimeImmutable('today');
    $vencimiento = !empty($suscripcion['fecha_vencimiento'])
        ? new DateTimeImmutable($suscripcion['fecha_vencimiento'])
        : null;
    $finTolerancia = !empty($suscripcion['fecha_fin_tolerancia'])
        ? new DateTimeImmutable($suscripcion['fecha_fin_tolerancia'])
        : null;

    // Cancelación solicitada: conserva acceso hasta terminar el período pagado.
    if ((int) $suscripcion['cancelacion_fin_periodo'] === 1 && $vencimiento) {
        if ($hoy > $vencimiento) {
            if ($estadoActual !== 'cancelada') {
                $stmt = $conn->prepare("\n                    UPDATE suscripciones\n                    SET estado = 'cancelada',\n                        fecha_cancelacion = COALESCE(fecha_cancelacion, NOW())\n                    WHERE id_suscripcion = ?\n                ");
                $idSuscripcion = (int) $suscripcion['id_suscripcion'];
                $stmt->bind_param('i', $idSuscripcion);
                $stmt->execute();
                $stmt->close();
            }
            return obtenerSuscripcionVeterinario($conn, $idVeterinario);
        }
    }

    // Una cancelación ya efectiva queda cancelada hasta que el usuario pague de nuevo.
    if ($estadoActual === 'cancelada') {
        return $suscripcion;
    }

    if (!$vencimiento) {
        return $suscripcion;
    }

    if ($hoy <= $vencimiento) {
        $nuevoEstado = 'activa';
    } elseif ($finTolerancia && $hoy <= $finTolerancia) {
        $nuevoEstado = 'atrasada';
    } else {
        $nuevoEstado = 'suspendida';
    }

    if ($nuevoEstado !== $estadoActual) {
        $stmt = $conn->prepare("\n            UPDATE suscripciones\n            SET estado = ?,\n                motivo_suspension = CASE\n                    WHEN ? = 'suspendida' THEN 'Suspensión automática por falta de pago.'\n                    ELSE NULL\n                END\n            WHERE id_suscripcion = ?\n        ");
        $idSuscripcion = (int) $suscripcion['id_suscripcion'];
        $stmt->bind_param('ssi', $nuevoEstado, $nuevoEstado, $idSuscripcion);
        $stmt->execute();
        $stmt->close();
    }

    return obtenerSuscripcionVeterinario($conn, $idVeterinario);
}

function actualizarTodasSuscripciones(mysqli $conn): void
{
    $resultado = $conn->query("SELECT id_veterinario FROM suscripciones");

    if (!$resultado) {
        return;
    }

    while ($fila = $resultado->fetch_assoc()) {
        actualizarEstadoSuscripcion($conn, (int) $fila['id_veterinario']);
    }
}

function suscripcionPermiteUso(?array $suscripcion): bool
{
    if (!$suscripcion) {
        return false;
    }

    return in_array(
        $suscripcion['estado'],
        ['activa', 'atrasada'],
        true
    );
}

function etiquetaEstadoSuscripcion(string $estado): string
{
    return match ($estado) {
        'activa' => 'Activa',
        'atrasada' => 'Atrasada',
        'suspendida' => 'Suspendida',
        'cancelada' => 'Cancelada',
        'pendiente_pago' => 'Pendiente de pago',
        default => ucfirst($estado),
    };
}

function claseEstadoSuscripcion(string $estado): string
{
    return match ($estado) {
        'activa' => 'estado-activa',
        'atrasada' => 'estado-atrasada',
        'suspendida' => 'estado-suspendida',
        'cancelada' => 'estado-cancelada',
        'pendiente_pago' => 'estado-pendiente',
        default => 'estado-pendiente',
    };
}

function generarNumeroOperacion(): string
{
    return 'VCP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Registra un intento de pago de demostración.
 * Los datos de tarjeta o billetera nunca se guardan.
 */
function registrarPagoDemo(
    mysqli $conn,
    int $idVeterinario,
    string $medioPago,
    string $resultado,
    ?string $observaciones = null
): array {
    $suscripcion = actualizarEstadoSuscripcion($conn, $idVeterinario);

    if (!$suscripcion) {
        throw new RuntimeException('El veterinario no tiene una suscripción asociada.');
    }

    $mediosValidos = ['credito', 'debito', 'billetera', 'manual'];
    $resultadosValidos = ['aprobado', 'pendiente', 'rechazado'];

    if (!in_array($medioPago, $mediosValidos, true)) {
        throw new InvalidArgumentException('Medio de pago inválido.');
    }

    if (!in_array($resultado, $resultadosValidos, true)) {
        throw new InvalidArgumentException('Resultado de pago inválido.');
    }

    $hoy = new DateTimeImmutable('today');
    $precio = (float) $suscripcion['precio_mensual'];
    $diasTolerancia = (int) $suscripcion['dias_tolerancia'];

    $periodoDesde = null;
    $periodoHasta = null;

    if ($resultado === 'aprobado') {
        $estado = (string) $suscripcion['estado'];
        $vencimientoActual = !empty($suscripcion['fecha_vencimiento'])
            ? new DateTimeImmutable($suscripcion['fecha_vencimiento'])
            : null;
        $finToleranciaActual = !empty($suscripcion['fecha_fin_tolerancia'])
            ? new DateTimeImmutable($suscripcion['fecha_fin_tolerancia'])
            : null;

        $primerPago = (
            $estado === 'pendiente_pago' ||
            !$vencimientoActual
        );

        $reactivacionTardia = (
            in_array($estado, ['suspendida', 'cancelada'], true) &&
            (!$finToleranciaActual || $hoy > $finToleranciaActual)
        );

        if ($primerPago || $reactivacionTardia) {
            $inicio = $hoy;
            $nuevoVencimiento = $hoy->modify('+1 month');
        } else {
            $inicio = $vencimientoActual;
            $nuevoVencimiento = $vencimientoActual->modify('+1 month');
        }

        $nuevoFinTolerancia = $nuevoVencimiento->modify('+' . $diasTolerancia . ' days');

        $periodoDesde = $inicio->format('Y-m-d');
        $periodoHasta = $nuevoVencimiento->format('Y-m-d');

        $fechaInicioSistema = $suscripcion['fecha_inicio'] ?: $hoy->format('Y-m-d');
        $fechaVencimientoSql = $nuevoVencimiento->format('Y-m-d');
        $fechaFinToleranciaSql = $nuevoFinTolerancia->format('Y-m-d');
        $idSuscripcion = (int) $suscripcion['id_suscripcion'];

        $stmt = $conn->prepare("\n            UPDATE suscripciones\n            SET estado = 'activa',\n                fecha_inicio = ?,\n                fecha_vencimiento = ?,\n                fecha_fin_tolerancia = ?,\n                cancelacion_fin_periodo = 0,\n                fecha_cancelacion = NULL,\n                motivo_suspension = NULL\n            WHERE id_suscripcion = ?\n        ");
        $stmt->bind_param(
            'sssi',
            $fechaInicioSistema,
            $fechaVencimientoSql,
            $fechaFinToleranciaSql,
            $idSuscripcion
        );
        $stmt->execute();
        $stmt->close();
        // Solo el PRIMER pago habilita automáticamente una cuenta nueva.
        // Una baja administrativa no puede revertirse desde el checkout.
        if ($estado === 'pendiente_pago') {
            $stmt = $conn->prepare("
                UPDATE veterinarios
                SET activo = 1
                WHERE id_veterinario = ?
            ");
            $stmt->bind_param('i', $idVeterinario);
            $stmt->execute();
            $stmt->close();
        }
    }

    $numeroOperacion = generarNumeroOperacion();
    $idSuscripcion = (int) $suscripcion['id_suscripcion'];
    $idPlan = (int) $suscripcion['id_plan'];
    $fechaAprobacion = $resultado === 'aprobado' ? date('Y-m-d H:i:s') : null;
    $modoDemo = $medioPago === 'manual' ? 0 : 1;

    $stmt = $conn->prepare("\n        INSERT INTO pagos (\n            id_suscripcion,\n            id_veterinario,\n            id_plan,\n            numero_operacion,\n            periodo_desde,\n            periodo_hasta,\n            importe,\n            medio_pago,\n            estado,\n            modo_demo,\n            fecha_aprobacion,\n            observaciones\n        )\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n    ");

    $stmt->bind_param(
        'iiisssdssiss',
        $idSuscripcion,
        $idVeterinario,
        $idPlan,
        $numeroOperacion,
        $periodoDesde,
        $periodoHasta,
        $precio,
        $medioPago,
        $resultado,
        $modoDemo,
        $fechaAprobacion,
        $observaciones
    );
    $stmt->execute();
    $idPago = $stmt->insert_id;
    $stmt->close();

    return [
        'id_pago' => (int) $idPago,
        'numero_operacion' => $numeroOperacion,
        'estado' => $resultado,
        'suscripcion' => actualizarEstadoSuscripcion($conn, $idVeterinario),
    ];
}

function formatoDinero(float $importe): string
{
    return '$' . number_format($importe, 0, ',', '.');
}
