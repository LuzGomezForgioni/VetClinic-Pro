<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once '../config/conexion.php';
require_once '../config/estadisticas.php';
require_once '../config/pdf_simple.php';

$tipo = trim((string) ($_GET['tipo'] ?? ''));
$id = max(0, (int) ($_GET['id'] ?? 0));
$logo = __DIR__ . '/../img/VetClinic Pro.png';

function abortarPdf(string $mensaje = 'Documento no encontrado.', int $codigo = 404): void
{
    http_response_code($codigo);
    exit($mensaje);
}

function fechaPdf(?string $fecha, bool $hora = false): string
{
    if (!$fecha) {
        return '—';
    }

    $timestamp = strtotime($fecha);

    if ($timestamp === false) {
        return $fecha;
    }

    return date($hora ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
}

function nombreCompletoPdf(array $fila, string $prefijo = ''): string
{
    return trim(
        (string) ($fila[$prefijo . 'nombre'] ?? '') . ' ' .
        (string) ($fila[$prefijo . 'apellido'] ?? '')
    );
}

function etiquetaEstadoPdf(string $estado): string
{
    return ucfirst(str_replace('_', ' ', $estado));
}

function esVeterinarioSesion(): bool
{
    return !empty($_SESSION['logueado']) &&
        ($_SESSION['tipo_usuario'] ?? '') === 'veterinario' &&
        (int) ($_SESSION['usuario_id'] ?? 0) > 0;
}

function esPropietarioSesion(): bool
{
    return !empty($_SESSION['logueado']) &&
        ($_SESSION['tipo_usuario'] ?? '') === 'propietario' &&
        (int) ($_SESSION['usuario_id'] ?? 0) > 0;
}

function esAdminSesion(): bool
{
    return !empty($_SESSION['admin_logueado']) &&
        (int) ($_SESSION['admin_id'] ?? 0) > 0;
}

function exigirVeterinarioParaReporte(): int
{
    if (!esVeterinarioSesion()) {
        abortarPdf('Acceso no autorizado.', 403);
    }

    global $conn;
    require_once '../config/proteger_acceso_veterinario.php';

    return (int) $_SESSION['usuario_id'];
}

function exigirAdminParaReporte(): void
{
    if (!esAdminSesion()) {
        abortarPdf('Acceso no autorizado.', 403);
    }
}

function mascotaPermitida(mysqli $conn, int $idMascota): ?array
{
    if ($idMascota <= 0) {
        return null;
    }

    $condicion = '';
    $tipos = 'i';
    $parametros = [$idMascota];

    if (esVeterinarioSesion()) {
        $condicion = "AND EXISTS (
            SELECT 1
            FROM turnos tv
            WHERE tv.id_mascota = m.id_mascota
              AND tv.id_veterinario = ?
        )";
        $tipos .= 'i';
        $parametros[] = (int) $_SESSION['usuario_id'];
    } elseif (esPropietarioSesion()) {
        $condicion = 'AND m.id_propietario = ?';
        $tipos .= 'i';
        $parametros[] = (int) $_SESSION['usuario_id'];
    } else {
        return null;
    }

    $filas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT m.id_mascota, m.id_propietario, m.nombre,
                m.fecha_nacimiento, m.sexo, m.peso_kg,
                m.color, m.castrado, m.fallecido,
                e.nombre_especie, r.nombre_raza,
                p.nombre AS propietario_nombre,
                p.apellido AS propietario_apellido,
                p.email AS propietario_email,
                p.telefono AS propietario_telefono
         FROM mascotas m
         INNER JOIN propietarios p ON p.id_propietario = m.id_propietario
         LEFT JOIN especies e ON e.id_especie = m.id_especie
         LEFT JOIN razas r ON r.id_raza = m.id_raza
         WHERE m.id_mascota = ? {$condicion}
         LIMIT 1",
        $tipos,
        $parametros
    );

    return $filas[0] ?? null;
}

function bloqueDatoPdf(
    PdfSimple $pdf,
    float $x,
    float &$y,
    string $etiqueta,
    string $valor,
    float $ancho = 511
): void {
    $pdf->colorTexto(107, 114, 128);
    $pdf->texto($x, $y, $etiqueta, 7, true);
    $y += 12;
    $pdf->colorTexto(48, 54, 75);
    $pdf->parrafo($x, $y, $valor !== '' ? $valor : '—', $ancho, 8.5, 11);
    $y += 5;
}

function nuevaPaginaClinica(PdfSimple $pdf, string $titulo): float
{
    pdfPie($pdf);
    $pdf->agregarPagina();
    return pdfCabecera($pdf, $titulo);
}

$periodo = trim((string) ($_GET['periodo'] ?? 'mes_actual'));
$rango = rangoEstadisticas(
    $periodo,
    $_GET['desde'] ?? null,
    $_GET['hasta'] ?? null
);

// ============================================================
// REPORTES DEL VETERINARIO
// ============================================================

if (str_starts_with($tipo, 'vet_')) {
    $idVeterinario = exigirVeterinarioParaReporte();
    $filtros = filtrosVeterinarioDesdeGet();

    $vet = ejecutarConsultaEstadistica(
        $conn,
        "SELECT nombre, apellido, matricula, especialidad
         FROM veterinarios
         WHERE id_veterinario = ?
         LIMIT 1",
        'i',
        [$idVeterinario]
    )[0] ?? null;

    if (!$vet) {
        abortarPdf('Veterinario no encontrado.');
    }

    $subtitulo = nombreCompletoPdf($vet) . ' · ' .
        $rango['desde'] . ' al ' . $rango['hasta'];
    $pdf = new PdfSimple($logo);

    if ($tipo === 'vet_resumen') {
        $metricas = metricasVeterinario($conn, $idVeterinario, $rango, $filtros);
        $turnosEstado = distribucionTurnosVeterinario($conn, $idVeterinario, $rango, $filtros);
        $y = pdfCabecera($pdf, 'Reporte de actividad profesional', $subtitulo);

        $pdf->colorTexto(76, 82, 103);
        $pdf->texto(42, $y, 'Profesional', 8, true);
        $pdf->colorTexto(35, 41, 75);
        $pdf->texto(42, $y + 14, nombreCompletoPdf($vet), 10, true);
        $pdf->colorTexto(107, 114, 128);
        $pdf->texto(
            42,
            $y + 28,
            'Matrícula: ' . ($vet['matricula'] ?: '—') .
            ' · Especialidad: ' . ($vet['especialidad'] ?: '—'),
            8
        );
        $y += 48;

        $ancho = 158;
        $gap = 18;
        pdfTarjetaMetrica($pdf, 42, $y, $ancho, 'Pacientes atendidos', (string) $metricas['actual']['pacientes'], $metricas['comparaciones']['pacientes']['texto']);
        pdfTarjetaMetrica($pdf, 42 + $ancho + $gap, $y, $ancho, 'Consultas realizadas', (string) $metricas['actual']['consultas'], $metricas['comparaciones']['consultas']['texto']);
        pdfTarjetaMetrica($pdf, 42 + (($ancho + $gap) * 2), $y, $ancho, 'Nuevos pacientes', (string) $metricas['actual']['nuevos_pacientes'], $metricas['comparaciones']['nuevos_pacientes']['texto']);
        $y += 82;
        pdfTarjetaMetrica($pdf, 42, $y, $ancho, 'Propietarios atendidos', (string) $metricas['actual']['propietarios'], $metricas['comparaciones']['propietarios']['texto']);
        pdfTarjetaMetrica($pdf, 42 + $ancho + $gap, $y, $ancho, 'Vacunas registradas', (string) $metricas['actual']['vacunas'], $metricas['comparaciones']['vacunas']['texto']);
        pdfTarjetaMetrica($pdf, 42 + (($ancho + $gap) * 2), $y, $ancho, 'Historias clínicas', (string) $metricas['actual']['historias'], $metricas['comparaciones']['historias']['texto']);
        $y += 92;

        $filasEstado = [];
        foreach ($turnosEstado as $estado => $total) {
            $filasEstado[] = [
                'estado' => etiquetaEstadoPdf($estado),
                'total' => (string) $total
            ];
        }
        pdfTabla(
            $pdf,
            $y,
            [
                ['key' => 'estado', 'title' => 'Estado de turno', 'width' => 255],
                ['key' => 'total', 'title' => 'Cantidad', 'width' => 256]
            ],
            $filasEstado,
            'Turnos del período'
        );

        pdfPie($pdf);
        $pdf->salida('reporte_actividad_' . $rango['desde'] . '_' . $rango['hasta'] . '.pdf');
    }

    if ($tipo === 'vet_turnos') {
        $datos = reporteTurnosVeterinario($conn, $idVeterinario, $rango, $filtros, null);
        $filas = [];
        foreach ($datos as $fila) {
            $filas[] = [
                'fecha' => fechaPdf($fila['fecha_hora'], true),
                'mascota' => $fila['mascota'],
                'propietario' => trim($fila['propietario_nombre'] . ' ' . $fila['propietario_apellido']),
                'motivo' => $fila['motivo'],
                'estado' => etiquetaEstadoPdf($fila['estado'])
            ];
        }
        $y = pdfCabecera($pdf, 'Reporte de turnos', $subtitulo);
        pdfTabla($pdf, $y, [
            ['key' => 'fecha', 'title' => 'Fecha', 'width' => 86],
            ['key' => 'mascota', 'title' => 'Mascota', 'width' => 75],
            ['key' => 'propietario', 'title' => 'Propietario', 'width' => 120],
            ['key' => 'motivo', 'title' => 'Motivo', 'width' => 145],
            ['key' => 'estado', 'title' => 'Estado', 'width' => 85]
        ], $filas);
        pdfPie($pdf);
        $pdf->salida('reporte_turnos_' . $rango['desde'] . '_' . $rango['hasta'] . '.pdf');
    }

    if ($tipo === 'vet_vacunas') {
        $datos = reporteVacunasVeterinario($conn, $idVeterinario, $rango, $filtros, null);
        $filas = [];
        foreach ($datos as $fila) {
            $filas[] = [
                'fecha' => fechaPdf($fila['fecha_aplicacion']),
                'mascota' => $fila['mascota'],
                'propietario' => trim($fila['propietario_nombre'] . ' ' . $fila['propietario_apellido']),
                'vacuna' => $fila['nombre_vacuna'],
                'proxima' => fechaPdf($fila['fecha_proxima'])
            ];
        }
        $y = pdfCabecera($pdf, 'Reporte de vacunaciones', $subtitulo);
        pdfTabla($pdf, $y, [
            ['key' => 'fecha', 'title' => 'Aplicación', 'width' => 78],
            ['key' => 'mascota', 'title' => 'Mascota', 'width' => 80],
            ['key' => 'propietario', 'title' => 'Propietario', 'width' => 125],
            ['key' => 'vacuna', 'title' => 'Vacuna', 'width' => 145],
            ['key' => 'proxima', 'title' => 'Próxima', 'width' => 83]
        ], $filas);
        pdfPie($pdf);
        $pdf->salida('reporte_vacunaciones_' . $rango['desde'] . '_' . $rango['hasta'] . '.pdf');
    }

    $datos = reportePacientesVeterinario($conn, $idVeterinario, $rango, $filtros, null);
    $filas = [];
    foreach ($datos as $fila) {
        $filas[] = [
            'fecha' => fechaPdf($fila['fecha_hora']),
            'mascota' => $fila['mascota'],
            'especie' => $fila['especie'],
            'propietario' => trim($fila['propietario_nombre'] . ' ' . $fila['propietario_apellido']),
            'motivo' => $fila['motivo']
        ];
    }
    $y = pdfCabecera($pdf, 'Reporte de pacientes atendidos', $subtitulo);
    pdfTabla($pdf, $y, [
        ['key' => 'fecha', 'title' => 'Fecha', 'width' => 70],
        ['key' => 'mascota', 'title' => 'Mascota', 'width' => 85],
        ['key' => 'especie', 'title' => 'Especie', 'width' => 75],
        ['key' => 'propietario', 'title' => 'Propietario', 'width' => 135],
        ['key' => 'motivo', 'title' => 'Motivo', 'width' => 146]
    ], $filas);
    pdfPie($pdf);
    $pdf->salida('reporte_pacientes_' . $rango['desde'] . '_' . $rango['hasta'] . '.pdf');
}

// ============================================================
// REPORTES DEL SUPERADMINISTRADOR
// ============================================================

if (str_starts_with($tipo, 'admin_')) {
    exigirAdminParaReporte();
    require_once '../config/suscripciones.php';
    actualizarTodasSuscripciones($conn);

    $filtros = filtrosAdminDesdeGet();
    $pdf = new PdfSimple($logo);
    $subtitulo = $rango['desde'] . ' al ' . $rango['hasta'];

    if ($tipo === 'admin_resumen') {
        $metricas = metricasAdmin($conn, $rango, $filtros);
        $pagos = reportePagosAdmin($conn, $rango, $filtros, 12);
        $estadosPagoResumen = estadosPagosAdmin($conn, $rango, $filtros);
        $estadosSuscripcionResumen = estadosSuscripcionAdmin($conn, $filtros);
        $y = pdfCabecera($pdf, 'Reporte general de VetClinic Pro', $subtitulo);

        $ancho = 158;
        $gap = 18;
        pdfTarjetaMetrica($pdf, 42, $y, $ancho, 'Clientes totales', (string) $metricas['clientes_total']);
        pdfTarjetaMetrica($pdf, 42 + $ancho + $gap, $y, $ancho, 'Clientes activos', (string) $metricas['clientes_activos']);
        pdfTarjetaMetrica($pdf, 42 + (($ancho + $gap) * 2), $y, $ancho, 'Nuevos clientes', (string) $metricas['nuevos_actual'], $metricas['comparacion_nuevos']['texto']);
        $y += 82;
        pdfTarjetaMetrica($pdf, 42, $y, $ancho, 'Ingresos aprobados', formatoDineroEstadistica((float) $metricas['ingresos_actual']), $metricas['comparacion_ingresos']['texto']);
        pdfTarjetaMetrica($pdf, 42 + $ancho + $gap, $y, $ancho, 'Atrasadas', (string) $metricas['clientes_atrasados']);
        pdfTarjetaMetrica($pdf, 42 + (($ancho + $gap) * 2), $y, $ancho, 'Suspendidas', (string) $metricas['clientes_suspendidos']);
        $y += 94;

        $filasEstados = [
            ['grupo' => 'Suscripciones', 'estado' => 'Activas', 'total' => (string) $estadosSuscripcionResumen['activa']],
            ['grupo' => 'Suscripciones', 'estado' => 'Atrasadas', 'total' => (string) $estadosSuscripcionResumen['atrasada']],
            ['grupo' => 'Suscripciones', 'estado' => 'Suspendidas', 'total' => (string) $estadosSuscripcionResumen['suspendida']],
            ['grupo' => 'Suscripciones', 'estado' => 'Canceladas', 'total' => (string) $estadosSuscripcionResumen['cancelada']],
            ['grupo' => 'Suscripciones', 'estado' => 'Pendiente de pago', 'total' => (string) $estadosSuscripcionResumen['pendiente_pago']],
            ['grupo' => 'Pagos', 'estado' => 'Aprobados', 'total' => (string) $estadosPagoResumen['aprobado']],
            ['grupo' => 'Pagos', 'estado' => 'Pendientes', 'total' => (string) $estadosPagoResumen['pendiente']],
            ['grupo' => 'Pagos', 'estado' => 'Rechazados', 'total' => (string) $estadosPagoResumen['rechazado']]
        ];
        pdfTabla($pdf, $y, [
            ['key' => 'grupo', 'title' => 'Grupo', 'width' => 150],
            ['key' => 'estado', 'title' => 'Estado', 'width' => 250],
            ['key' => 'total', 'title' => 'Cantidad', 'width' => 111]
        ], $filasEstados, 'Resumen de estados');
        $y += 12;

        $filas = [];
        foreach ($pagos as $fila) {
            $filas[] = [
                'fecha' => fechaPdf($fila['fecha_operacion']),
                'cliente' => trim($fila['nombre'] . ' ' . $fila['apellido']),
                'operacion' => $fila['numero_operacion'],
                'importe' => formatoDineroEstadistica((float) $fila['importe']),
                'estado' => etiquetaEstadoPdf($fila['estado'])
            ];
        }
        pdfTabla($pdf, $y, [
            ['key' => 'fecha', 'title' => 'Fecha', 'width' => 65],
            ['key' => 'cliente', 'title' => 'Cliente', 'width' => 125],
            ['key' => 'operacion', 'title' => 'Operación', 'width' => 140],
            ['key' => 'importe', 'title' => 'Importe', 'width' => 85],
            ['key' => 'estado', 'title' => 'Estado', 'width' => 96]
        ], $filas, 'Últimos pagos del período');
        pdfPie($pdf);
        $pdf->salida('reporte_general_vetclinic_' . $rango['desde'] . '_' . $rango['hasta'] . '.pdf');
    }

    if ($tipo === 'admin_ingresos') {
        $filtrosAprobados = $filtros;
        $filtrosAprobados['estado_pago'] = 'aprobado';
        $metricas = metricasAdmin($conn, $rango, $filtrosAprobados);
        $pagos = reportePagosAdmin($conn, $rango, $filtrosAprobados, null);
        $y = pdfCabecera($pdf, 'Reporte de ingresos', $subtitulo);

        pdfTarjetaMetrica(
            $pdf,
            42,
            $y,
            245,
            'Ingresos aprobados',
            formatoDineroEstadistica((float) $metricas['ingresos_actual']),
            $metricas['comparacion_ingresos']['texto']
        );
        pdfTarjetaMetrica(
            $pdf,
            308,
            $y,
            245,
            'Pagos aprobados',
            (string) $metricas['pagos_aprobados_actual'],
            'Solo operaciones aprobadas'
        );
        $y += 88;

        $filas = [];
        foreach ($pagos as $fila) {
            $filas[] = [
                'fecha' => fechaPdf($fila['fecha_operacion']),
                'cliente' => trim($fila['nombre'] . ' ' . $fila['apellido']),
                'operacion' => $fila['numero_operacion'],
                'medio' => ucfirst($fila['medio_pago']),
                'importe' => formatoDineroEstadistica((float) $fila['importe'])
            ];
        }

        pdfTabla($pdf, $y, [
            ['key' => 'fecha', 'title' => 'Fecha', 'width' => 70],
            ['key' => 'cliente', 'title' => 'Cliente', 'width' => 135],
            ['key' => 'operacion', 'title' => 'Operación', 'width' => 140],
            ['key' => 'medio', 'title' => 'Medio', 'width' => 76],
            ['key' => 'importe', 'title' => 'Importe', 'width' => 90]
        ], $filas, 'Pagos aprobados del período');
        pdfPie($pdf);
        $pdf->salida('reporte_ingresos_' . $rango['desde'] . '_' . $rango['hasta'] . '.pdf');
    }

    if ($tipo === 'admin_suscripciones') {
        $datos = reporteSuscripcionesAdmin($conn, $filtros, null);
        $filas = [];
        foreach ($datos as $fila) {
            $filas[] = [
                'cliente' => trim($fila['nombre'] . ' ' . $fila['apellido']),
                'matricula' => $fila['matricula'],
                'plan' => $fila['plan_nombre'],
                'estado' => etiquetaEstadoPdf($fila['estado']),
                'vence' => fechaPdf($fila['fecha_vencimiento'])
            ];
        }
        $y = pdfCabecera($pdf, 'Reporte de suscripciones', 'Estado actual de la cartera');
        pdfTabla($pdf, $y, [
            ['key' => 'cliente', 'title' => 'Cliente', 'width' => 130],
            ['key' => 'matricula', 'title' => 'Matrícula', 'width' => 90],
            ['key' => 'plan', 'title' => 'Plan', 'width' => 125],
            ['key' => 'estado', 'title' => 'Estado', 'width' => 90],
            ['key' => 'vence', 'title' => 'Vencimiento', 'width' => 76]
        ], $filas);
        pdfPie($pdf);
        $pdf->salida('reporte_suscripciones_' . date('Y-m-d') . '.pdf');
    }

    if ($tipo === 'admin_clientes') {
        $datos = reporteClientesAdmin($conn, $rango, $filtros, null);
        $filas = [];
        foreach ($datos as $fila) {
            $filas[] = [
                'cliente' => trim($fila['nombre'] . ' ' . $fila['apellido']),
                'matricula' => $fila['matricula'],
                'especialidad' => $fila['especialidad'],
                'alta' => fechaPdf($fila['fecha_registro']),
                'estado' => etiquetaEstadoPdf((string) ($fila['estado_suscripcion'] ?? ''))
            ];
        }
        $y = pdfCabecera($pdf, 'Reporte de clientes', $subtitulo);
        pdfTabla($pdf, $y, [
            ['key' => 'cliente', 'title' => 'Cliente', 'width' => 125],
            ['key' => 'matricula', 'title' => 'Matrícula', 'width' => 80],
            ['key' => 'especialidad', 'title' => 'Especialidad', 'width' => 150],
            ['key' => 'alta', 'title' => 'Alta', 'width' => 75],
            ['key' => 'estado', 'title' => 'Estado', 'width' => 81]
        ], $filas);
        pdfPie($pdf);
        $pdf->salida('reporte_clientes_' . $rango['desde'] . '_' . $rango['hasta'] . '.pdf');
    }

    $datos = reportePagosAdmin($conn, $rango, $filtros, null);
    $filas = [];
    foreach ($datos as $fila) {
        $filas[] = [
            'fecha' => fechaPdf($fila['fecha_operacion']),
            'cliente' => trim($fila['nombre'] . ' ' . $fila['apellido']),
            'operacion' => $fila['numero_operacion'],
            'medio' => ucfirst($fila['medio_pago']),
            'importe' => formatoDineroEstadistica((float) $fila['importe']),
            'estado' => etiquetaEstadoPdf($fila['estado'])
        ];
    }
    $y = pdfCabecera($pdf, 'Reporte de pagos', $subtitulo);
    pdfTabla($pdf, $y, [
        ['key' => 'fecha', 'title' => 'Fecha', 'width' => 65],
        ['key' => 'cliente', 'title' => 'Cliente', 'width' => 115],
        ['key' => 'operacion', 'title' => 'Operación', 'width' => 125],
        ['key' => 'medio', 'title' => 'Medio', 'width' => 70],
        ['key' => 'importe', 'title' => 'Importe', 'width' => 75],
        ['key' => 'estado', 'title' => 'Estado', 'width' => 61]
    ], $filas);
    pdfPie($pdf);
    $pdf->salida('reporte_pagos_' . $rango['desde'] . '_' . $rango['hasta'] . '.pdf');
}

// ============================================================
// HISTORIA CLÍNICA / FICHA DE MASCOTA
// ============================================================

if (in_array($tipo, ['historia', 'ficha_mascota'], true)) {
    if (!esVeterinarioSesion() && !esPropietarioSesion()) {
        abortarPdf('Acceso no autorizado.', 403);
    }

    if (esVeterinarioSesion()) {
        require_once '../config/proteger_acceso_veterinario.php';
    }

    $mascota = mascotaPermitida($conn, $id);

    if (!$mascota) {
        abortarPdf();
    }

    $historias = ejecutarConsultaEstadistica(
        $conn,
        "SELECT hc.fecha_consulta, hc.motivo_consulta,
                hc.diagnostico, hc.tratamiento, hc.medicacion,
                hc.peso_registro, hc.proximos_pasos,
                v.nombre AS veterinario_nombre,
                v.apellido AS veterinario_apellido,
                v.matricula
         FROM historia_clinica hc
         INNER JOIN veterinarios v ON v.id_veterinario = hc.id_veterinario
         WHERE hc.id_mascota = ?
         ORDER BY hc.fecha_consulta ASC, hc.id_historia ASC",
        'i',
        [$id]
    );

    $vacunas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT nombre_vacuna, fecha_aplicacion, fecha_proxima, observaciones
         FROM vacunaciones
         WHERE id_mascota = ?
         ORDER BY fecha_aplicacion DESC, id_vacunacion DESC",
        'i',
        [$id]
    );

    $pdf = new PdfSimple($logo);
    $tituloDoc = $tipo === 'historia'
        ? 'Historia clínica de ' . $mascota['nombre']
        : 'Ficha completa de ' . $mascota['nombre'];
    $y = pdfCabecera(
        $pdf,
        $tituloDoc,
        'Emitido el ' . date('d/m/Y')
    );

    $pdf->colorTexto(26, 31, 94);
    $pdf->texto(42, $y, 'Datos del paciente', 12, true);
    $y += 18;

    $datosPaciente = [
        'Mascota' => $mascota['nombre'],
        'Especie / raza' => trim(($mascota['nombre_especie'] ?: '—') . ' / ' . ($mascota['nombre_raza'] ?: '—')),
        'Nacimiento' => fechaPdf($mascota['fecha_nacimiento']),
        'Sexo' => $mascota['sexo'],
        'Peso actual' => $mascota['peso_kg'] !== null ? number_format((float) $mascota['peso_kg'], 2, ',', '.') . ' kg' : '—',
        'Color' => $mascota['color'] ?: '—',
        'Castrado' => (int) $mascota['castrado'] === 1 ? 'Sí' : 'No',
        'Estado' => (int) $mascota['fallecido'] === 1 ? 'Fallecido/a' : 'Activo/a',
        'Propietario' => trim($mascota['propietario_nombre'] . ' ' . $mascota['propietario_apellido']),
        'Contacto' => trim(($mascota['propietario_email'] ?: '') . ' ' . ($mascota['propietario_telefono'] ?: '')) ?: '—'
    ];

    $filasDatos = [];
    foreach ($datosPaciente as $etiqueta => $valor) {
        $filasDatos[] = ['dato' => $etiqueta, 'valor' => $valor];
    }
    pdfTabla($pdf, $y, [
        ['key' => 'dato', 'title' => 'Dato', 'width' => 150],
        ['key' => 'valor', 'title' => 'Información', 'width' => 361]
    ], $filasDatos);
    $y += 12;

    if ($tipo === 'ficha_mascota') {
        $filasVacuna = [];
        foreach (array_slice($vacunas, 0, 12) as $vacuna) {
            $filasVacuna[] = [
                'vacuna' => $vacuna['nombre_vacuna'],
                'aplicacion' => fechaPdf($vacuna['fecha_aplicacion']),
                'proxima' => fechaPdf($vacuna['fecha_proxima'])
            ];
        }
        pdfTabla($pdf, $y, [
            ['key' => 'vacuna', 'title' => 'Vacuna', 'width' => 250],
            ['key' => 'aplicacion', 'title' => 'Aplicación', 'width' => 130],
            ['key' => 'proxima', 'title' => 'Próxima', 'width' => 131]
        ], $filasVacuna, 'Vacunaciones');
        $y += 12;
    }

    if (!$historias) {
        $pdf->colorTexto(26, 31, 94);
        $pdf->texto(42, $y, 'Historia clínica', 12, true);
        $pdf->colorTexto(107, 114, 128);
        $pdf->texto(42, $y + 18, 'No hay consultas clínicas registradas.', 8);
    } else {
        foreach ($historias as $indice => $historia) {
            if ($y > 660) {
                $y = nuevaPaginaClinica($pdf, $tituloDoc);
            }

            $pdf->colorRelleno(247, 248, 252);
            $pdf->rectangulo(42, $y, 511, 38, true);
            $pdf->colorTexto(26, 31, 94);
            $pdf->texto(54, $y + 16, 'Consulta ' . ($indice + 1) . ' · ' . fechaPdf($historia['fecha_consulta']), 10, true);
            $pdf->colorTexto(96, 103, 123);
            $pdf->texto(
                54,
                $y + 30,
                'Profesional: ' . trim($historia['veterinario_nombre'] . ' ' . $historia['veterinario_apellido']) .
                ' · Matrícula ' . ($historia['matricula'] ?: '—'),
                7
            );
            $y += 51;

            bloqueDatoPdf($pdf, 54, $y, 'Motivo de consulta', (string) $historia['motivo_consulta'], 487);
            bloqueDatoPdf($pdf, 54, $y, 'Diagnóstico', (string) ($historia['diagnostico'] ?? ''), 487);
            bloqueDatoPdf($pdf, 54, $y, 'Tratamiento', (string) ($historia['tratamiento'] ?? ''), 487);
            bloqueDatoPdf($pdf, 54, $y, 'Medicación', (string) ($historia['medicacion'] ?? ''), 487);
            bloqueDatoPdf(
                $pdf,
                54,
                $y,
                'Peso registrado',
                $historia['peso_registro'] !== null
                    ? number_format((float) $historia['peso_registro'], 2, ',', '.') . ' kg'
                    : '—',
                487
            );
            bloqueDatoPdf($pdf, 54, $y, 'Próximos pasos', (string) ($historia['proximos_pasos'] ?? ''), 487);
            $y += 8;
        }
    }

    pdfPie($pdf);
    $pdf->salida(
        ($tipo === 'historia' ? 'historia_clinica_' : 'ficha_mascota_') .
        $mascota['nombre'] . '.pdf'
    );
}

// ============================================================
// CONSTANCIA DE VACUNACIÓN
// ============================================================

if ($tipo === 'vacuna') {
    if (!esVeterinarioSesion() && !esPropietarioSesion()) {
        abortarPdf('Acceso no autorizado.', 403);
    }

    if (esVeterinarioSesion()) {
        require_once '../config/proteger_acceso_veterinario.php';
    }

    $condicion = '';
    $tipos = 'i';
    $params = [$id];

    if (esVeterinarioSesion()) {
        $condicion = "AND EXISTS (
            SELECT 1
            FROM turnos tv
            WHERE tv.id_mascota = va.id_mascota
              AND tv.id_veterinario = ?
        )";
        $tipos .= 'i';
        $params[] = (int) $_SESSION['usuario_id'];
    } else {
        $condicion = 'AND m.id_propietario = ?';
        $tipos .= 'i';
        $params[] = (int) $_SESSION['usuario_id'];
    }

    $vacuna = ejecutarConsultaEstadistica(
        $conn,
        "SELECT va.*, m.nombre AS mascota,
                e.nombre_especie, r.nombre_raza,
                p.nombre AS propietario_nombre,
                p.apellido AS propietario_apellido,
                v.nombre AS veterinario_nombre,
                v.apellido AS veterinario_apellido,
                v.matricula
         FROM vacunaciones va
         INNER JOIN mascotas m ON m.id_mascota = va.id_mascota
         INNER JOIN propietarios p ON p.id_propietario = m.id_propietario
         LEFT JOIN especies e ON e.id_especie = m.id_especie
         LEFT JOIN razas r ON r.id_raza = m.id_raza
         LEFT JOIN veterinarios v ON v.id_veterinario = va.id_veterinario
         WHERE va.id_vacunacion = ? {$condicion}
         LIMIT 1",
        $tipos,
        $params
    )[0] ?? null;

    if (!$vacuna) {
        abortarPdf();
    }

    $pdf = new PdfSimple($logo);
    $y = pdfCabecera($pdf, 'Constancia de vacunación', 'Documento emitido el ' . date('d/m/Y'));
    $pdf->colorRelleno(240, 247, 255);
    $pdf->rectangulo(42, $y, 511, 65, true);
    $pdf->colorTexto(26, 31, 94);
    $pdf->texto(58, $y + 23, $vacuna['nombre_vacuna'], 16, true);
    $pdf->colorTexto(84, 91, 112);
    $pdf->texto(58, $y + 42, 'Aplicada el ' . fechaPdf($vacuna['fecha_aplicacion']), 9);
    $y += 88;

    $filas = [
        ['dato' => 'Mascota', 'valor' => $vacuna['mascota']],
        ['dato' => 'Especie / raza', 'valor' => trim(($vacuna['nombre_especie'] ?: '—') . ' / ' . ($vacuna['nombre_raza'] ?: '—'))],
        ['dato' => 'Propietario', 'valor' => trim($vacuna['propietario_nombre'] . ' ' . $vacuna['propietario_apellido'])],
        ['dato' => 'Fecha de aplicación', 'valor' => fechaPdf($vacuna['fecha_aplicacion'])],
        ['dato' => 'Próxima dosis', 'valor' => fechaPdf($vacuna['fecha_proxima'])],
        ['dato' => 'Veterinario', 'valor' => trim(($vacuna['veterinario_nombre'] ?? '') . ' ' . ($vacuna['veterinario_apellido'] ?? '')) ?: 'No informado'],
        ['dato' => 'Matrícula', 'valor' => $vacuna['matricula'] ?: '—']
    ];
    pdfTabla($pdf, $y, [
        ['key' => 'dato', 'title' => 'Dato', 'width' => 165],
        ['key' => 'valor', 'title' => 'Información', 'width' => 346]
    ], $filas);
    $y += 18;
    bloqueDatoPdf($pdf, 42, $y, 'Observaciones', (string) ($vacuna['observaciones'] ?? ''), 511);
    pdfPie($pdf);
    $pdf->salida('constancia_vacunacion_' . $vacuna['mascota'] . '_' . $vacuna['id_vacunacion'] . '.pdf');
}

// ============================================================
// COMPROBANTE DE TURNO
// ============================================================

if ($tipo === 'turno') {
    if (!esVeterinarioSesion() && !esPropietarioSesion()) {
        abortarPdf('Acceso no autorizado.', 403);
    }

    if (esVeterinarioSesion()) {
        require_once '../config/proteger_acceso_veterinario.php';
    }

    $condicion = esVeterinarioSesion()
        ? 't.id_veterinario = ?'
        : 't.id_propietario = ?';
    $idSesion = (int) $_SESSION['usuario_id'];

    $turno = ejecutarConsultaEstadistica(
        $conn,
        "SELECT t.id_turno, t.fecha_hora, t.motivo, t.estado,
                t.observaciones,
                m.nombre AS mascota, e.nombre_especie,
                p.nombre AS propietario_nombre, p.apellido AS propietario_apellido,
                v.nombre AS veterinario_nombre, v.apellido AS veterinario_apellido,
                v.matricula, v.especialidad
         FROM turnos t
         INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
         INNER JOIN propietarios p ON p.id_propietario = t.id_propietario
         INNER JOIN veterinarios v ON v.id_veterinario = t.id_veterinario
         LEFT JOIN especies e ON e.id_especie = m.id_especie
         WHERE t.id_turno = ? AND {$condicion}
         LIMIT 1",
        'ii',
        [$id, $idSesion]
    )[0] ?? null;

    if (!$turno) {
        abortarPdf();
    }

    $pdf = new PdfSimple($logo);
    $y = pdfCabecera($pdf, 'Comprobante de turno', 'Turno #' . $turno['id_turno']);
    $filas = [
        ['dato' => 'Fecha y hora', 'valor' => fechaPdf($turno['fecha_hora'], true)],
        ['dato' => 'Mascota', 'valor' => $turno['mascota'] . ' · ' . ($turno['nombre_especie'] ?: '—')],
        ['dato' => 'Propietario', 'valor' => trim($turno['propietario_nombre'] . ' ' . $turno['propietario_apellido'])],
        ['dato' => 'Veterinario', 'valor' => trim($turno['veterinario_nombre'] . ' ' . $turno['veterinario_apellido'])],
        ['dato' => 'Matrícula', 'valor' => $turno['matricula'] ?: '—'],
        ['dato' => 'Especialidad', 'valor' => $turno['especialidad'] ?: '—'],
        ['dato' => 'Motivo', 'valor' => $turno['motivo']],
        ['dato' => 'Estado', 'valor' => etiquetaEstadoPdf($turno['estado'])]
    ];
    pdfTabla($pdf, $y, [
        ['key' => 'dato', 'title' => 'Dato', 'width' => 160],
        ['key' => 'valor', 'title' => 'Información', 'width' => 351]
    ], $filas);
    $y += 18;
    bloqueDatoPdf($pdf, 42, $y, 'Observaciones', (string) ($turno['observaciones'] ?? ''), 511);
    pdfPie($pdf);
    $pdf->salida('turno_' . $turno['id_turno'] . '_' . $turno['mascota'] . '.pdf');
}

// ============================================================
// COMPROBANTE DE PAGO DE SUSCRIPCIÓN
// ============================================================

if ($tipo === 'pago') {
    if (!esVeterinarioSesion() && !esAdminSesion()) {
        abortarPdf('Acceso no autorizado.', 403);
    }

    $condicion = '';
    $tipos = 'i';
    $params = [$id];

    if (esVeterinarioSesion() && !esAdminSesion()) {
        $condicion = 'AND p.id_veterinario = ?';
        $tipos .= 'i';
        $params[] = (int) $_SESSION['usuario_id'];
    }

    $pago = ejecutarConsultaEstadistica(
        $conn,
        "SELECT p.*, pl.nombre AS plan_nombre,
                v.nombre, v.apellido, v.matricula
         FROM pagos p
         INNER JOIN planes pl ON pl.id_plan = p.id_plan
         INNER JOIN veterinarios v ON v.id_veterinario = p.id_veterinario
         WHERE p.id_pago = ? {$condicion}
         LIMIT 1",
        $tipos,
        $params
    )[0] ?? null;

    if (!$pago) {
        abortarPdf();
    }

    $pdf = new PdfSimple($logo);
    $y = pdfCabecera($pdf, 'Comprobante de pago', $pago['numero_operacion']);
    $filas = [
        ['dato' => 'Veterinario', 'valor' => trim($pago['nombre'] . ' ' . $pago['apellido'])],
        ['dato' => 'Matrícula', 'valor' => $pago['matricula'] ?: '—'],
        ['dato' => 'Plan', 'valor' => $pago['plan_nombre']],
        ['dato' => 'Fecha', 'valor' => fechaPdf($pago['fecha_operacion'], true)],
        ['dato' => 'Medio de pago', 'valor' => ucfirst($pago['medio_pago'])],
        ['dato' => 'Período', 'valor' => fechaPdf($pago['periodo_desde']) . ' — ' . fechaPdf($pago['periodo_hasta'])],
        ['dato' => 'Importe', 'valor' => formatoDineroEstadistica((float) $pago['importe'])],
        ['dato' => 'Estado', 'valor' => etiquetaEstadoPdf($pago['estado'])]
    ];
    pdfTabla($pdf, $y, [
        ['key' => 'dato', 'title' => 'Dato', 'width' => 160],
        ['key' => 'valor', 'title' => 'Información', 'width' => 351]
    ], $filas);
    $y += 18;

    if ((int) $pago['modo_demo'] === 1) {
        $pdf->colorRelleno(255, 249, 232);
        $pdf->rectangulo(42, $y, 511, 52, true);
        $pdf->colorTexto(121, 93, 22);
        $pdf->texto(56, $y + 18, 'Entorno de demostración', 9, true);
        $pdf->texto(56, $y + 34, 'Esta operación no representa un cobro bancario real.', 8);
    }

    pdfPie($pdf);
    $pdf->salida('comprobante_' . $pago['numero_operacion'] . '.pdf');
}

abortarPdf('Tipo de documento no válido.', 400);
