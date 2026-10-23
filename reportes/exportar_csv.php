<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once '../config/conexion.php';
require_once '../config/estadisticas.php';

$tipo = trim((string) ($_GET['tipo'] ?? ''));
$tiposVet = ['vet_pacientes', 'vet_turnos', 'vet_vacunas'];
$tiposAdmin = ['admin_pagos', 'admin_suscripciones', 'admin_clientes'];

function salidaCsv(string $nombre, array $cabeceras, array $filas): void
{
    $nombre = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) ?: 'reporte.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('X-Content-Type-Options: nosniff');

    echo "\xEF\xBB\xBF";
    $salida = fopen('php://output', 'wb');
    fputcsv($salida, $cabeceras, ';');

    foreach ($filas as $fila) {
        fputcsv($salida, $fila, ';');
    }

    fclose($salida);
    exit;
}

function textoFechaCsv(?string $fecha, bool $conHora = false): string
{
    if (!$fecha) {
        return '';
    }

    $timestamp = strtotime($fecha);

    if ($timestamp === false) {
        return $fecha;
    }

    return date($conHora ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
}

$periodo = trim((string) ($_GET['periodo'] ?? 'mes_actual'));
$rango = rangoEstadisticas(
    $periodo,
    $_GET['desde'] ?? null,
    $_GET['hasta'] ?? null
);

if (in_array($tipo, $tiposVet, true)) {
    if (
        empty($_SESSION['logueado']) ||
        ($_SESSION['tipo_usuario'] ?? '') !== 'veterinario'
    ) {
        http_response_code(403);
        exit('Acceso no autorizado.');
    }

    require_once '../config/proteger_acceso_veterinario.php';

    $idVeterinario = (int) ($_SESSION['usuario_id'] ?? 0);
    $filtros = filtrosVeterinarioDesdeGet();

    if ($tipo === 'vet_turnos') {
        $datos = reporteTurnosVeterinario(
            $conn,
            $idVeterinario,
            $rango,
            $filtros,
            null
        );
        $filas = [];

        foreach ($datos as $fila) {
            $filas[] = [
                (int) $fila['id_turno'],
                textoFechaCsv($fila['fecha_hora'], true),
                $fila['mascota'],
                $fila['especie'],
                trim($fila['propietario_nombre'] . ' ' . $fila['propietario_apellido']),
                $fila['motivo'],
                ucfirst($fila['estado'])
            ];
        }

        salidaCsv(
            'turnos_' . $rango['desde'] . '_' . $rango['hasta'] . '.csv',
            ['Turno', 'Fecha y hora', 'Mascota', 'Especie', 'Propietario', 'Motivo', 'Estado'],
            $filas
        );
    }

    if ($tipo === 'vet_vacunas') {
        $datos = reporteVacunasVeterinario(
            $conn,
            $idVeterinario,
            $rango,
            $filtros,
            null
        );
        $filas = [];

        foreach ($datos as $fila) {
            $filas[] = [
                (int) $fila['id_vacunacion'],
                $fila['mascota'],
                $fila['especie'],
                trim($fila['propietario_nombre'] . ' ' . $fila['propietario_apellido']),
                $fila['nombre_vacuna'],
                textoFechaCsv($fila['fecha_aplicacion']),
                textoFechaCsv($fila['fecha_proxima']),
                $fila['observaciones'] ?? ''
            ];
        }

        salidaCsv(
            'vacunaciones_' . $rango['desde'] . '_' . $rango['hasta'] . '.csv',
            ['ID', 'Mascota', 'Especie', 'Propietario', 'Vacuna', 'Aplicación', 'Próxima dosis', 'Observaciones'],
            $filas
        );
    }

    $datos = reportePacientesVeterinario(
        $conn,
        $idVeterinario,
        $rango,
        $filtros,
        null
    );
    $filas = [];

    foreach ($datos as $fila) {
        $filas[] = [
            textoFechaCsv($fila['fecha_hora'], true),
            $fila['mascota'],
            $fila['especie'],
            trim($fila['propietario_nombre'] . ' ' . $fila['propietario_apellido']),
            $fila['motivo'],
            $fila['diagnostico'] ?? ''
        ];
    }

    salidaCsv(
        'pacientes_atendidos_' . $rango['desde'] . '_' . $rango['hasta'] . '.csv',
        ['Fecha', 'Mascota', 'Especie', 'Propietario', 'Motivo', 'Diagnóstico'],
        $filas
    );
}

if (in_array($tipo, $tiposAdmin, true)) {
    if (empty($_SESSION['admin_logueado']) || empty($_SESSION['admin_id'])) {
        http_response_code(403);
        exit('Acceso no autorizado.');
    }

    $filtros = filtrosAdminDesdeGet();

    if ($tipo === 'admin_suscripciones') {
        $datos = reporteSuscripcionesAdmin($conn, $filtros, null);
        $filas = [];

        foreach ($datos as $fila) {
            $filas[] = [
                (int) $fila['id_veterinario'],
                trim($fila['nombre'] . ' ' . $fila['apellido']),
                $fila['matricula'],
                $fila['email'],
                $fila['plan_nombre'],
                ucfirst(str_replace('_', ' ', $fila['estado'])),
                textoFechaCsv($fila['fecha_inicio']),
                textoFechaCsv($fila['fecha_vencimiento']),
                textoFechaCsv($fila['fecha_fin_tolerancia']),
                textoFechaCsv($fila['fecha_cancelacion'])
            ];
        }

        salidaCsv(
            'suscripciones_' . date('Y-m-d') . '.csv',
            ['ID cliente', 'Veterinario', 'Matrícula', 'Email', 'Plan', 'Estado', 'Inicio', 'Vencimiento', 'Fin tolerancia', 'Cancelación'],
            $filas
        );
    }

    if ($tipo === 'admin_clientes') {
        $datos = reporteClientesAdmin($conn, $rango, $filtros, null);
        $filas = [];

        foreach ($datos as $fila) {
            $filas[] = [
                (int) $fila['id_veterinario'],
                trim($fila['nombre'] . ' ' . $fila['apellido']),
                $fila['matricula'],
                $fila['especialidad'],
                $fila['email'],
                $fila['localidad'] ?? '',
                textoFechaCsv($fila['fecha_registro']),
                $fila['plan_nombre'] ?? '',
                ucfirst(str_replace('_', ' ', (string) ($fila['estado_suscripcion'] ?? ''))),
                textoFechaCsv($fila['fecha_vencimiento'])
            ];
        }

        salidaCsv(
            'clientes_vetclinic_' . $rango['desde'] . '_' . $rango['hasta'] . '.csv',
            ['ID cliente', 'Veterinario', 'Matrícula', 'Especialidad', 'Email', 'Localidad', 'Alta', 'Plan', 'Estado', 'Vencimiento'],
            $filas
        );
    }

    $datos = reportePagosAdmin($conn, $rango, $filtros, null);
    $filas = [];

    foreach ($datos as $fila) {
        $filas[] = [
            textoFechaCsv($fila['fecha_operacion'], true),
            trim($fila['nombre'] . ' ' . $fila['apellido']),
            $fila['matricula'],
            $fila['numero_operacion'],
            textoFechaCsv($fila['periodo_desde']),
            textoFechaCsv($fila['periodo_hasta']),
            $fila['medio_pago'],
            number_format((float) $fila['importe'], 2, ',', ''),
            ucfirst($fila['estado'])
        ];
    }

    salidaCsv(
        'pagos_' . $rango['desde'] . '_' . $rango['hasta'] . '.csv',
        ['Fecha', 'Veterinario', 'Matrícula', 'Operación', 'Período desde', 'Período hasta', 'Medio', 'Importe', 'Estado'],
        $filas
    );
}

http_response_code(400);
echo 'Tipo de exportación no válido.';
