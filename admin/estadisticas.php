<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once '_auth.php';
require_once '../config/conexion.php';
require_once '../config/suscripciones.php';
require_once '../config/estadisticas.php';

actualizarTodasSuscripciones($conn);

$periodo = trim((string) ($_GET['periodo'] ?? 'mes_actual'));
$rango = rangoEstadisticas(
    $periodo,
    $_GET['desde'] ?? null,
    $_GET['hasta'] ?? null
);
$filtros = filtrosAdminDesdeGet();
$graficoMeses = (int) ($_GET['grafico_meses'] ?? 12);
$graficoMeses = in_array($graficoMeses, [6, 12], true) ? $graficoMeses : 12;

$metricas = metricasAdmin($conn, $rango, $filtros);
$serieClientes = serieClientesAdmin($conn, $graficoMeses, $filtros);
$serieIngresos = serieIngresosAdmin($conn, $graficoMeses, $filtros);
$estadosSuscripcion = estadosSuscripcionAdmin($conn, $filtros);
$estadosPago = estadosPagosAdmin($conn, $rango, $filtros);
$altasBajas = serieAltasBajasAdmin($conn, $graficoMeses, $filtros);
$veterinariosFiltro = opcionesFiltrosAdmin($conn);

$reporte = trim((string) ($_GET['reporte'] ?? 'pagos'));
$reportesPermitidos = ['pagos', 'suscripciones', 'clientes'];

if (!in_array($reporte, $reportesPermitidos, true)) {
    $reporte = 'pagos';
}

if ($reporte === 'suscripciones') {
    $filasReporte = reporteSuscripcionesAdmin($conn, $filtros, 100);
} elseif ($reporte === 'clientes') {
    $filasReporte = reporteClientesAdmin($conn, $rango, $filtros, 100);
} else {
    $filasReporte = reportePagosAdmin($conn, $rango, $filtros, 100);
}

function claseComparacionAdmin(array $comparacion): string
{
    if ($comparacion['direccion'] === 'sube') {
        return 'admin-stat-comparacion sube';
    }

    if ($comparacion['direccion'] === 'baja') {
        return 'admin-stat-comparacion baja';
    }

    return 'admin-stat-comparacion igual';
}

function iconoComparacionAdmin(array $comparacion): string
{
    if ($comparacion['direccion'] === 'sube') {
        return 'bi-arrow-up-right';
    }

    if ($comparacion['direccion'] === 'baja') {
        return 'bi-arrow-down-right';
    }

    return 'bi-dash';
}

$seccionActiva = 'estadisticas';
$queryPdfResumen = queryStringEstadisticas(['tipo' => 'admin_resumen']);
$queryPdfIngresos = queryStringEstadisticas(['tipo' => 'admin_ingresos']);
$queryPdfReporte = queryStringEstadisticas(['tipo' => 'admin_' . $reporte]);
$queryCsvReporte = queryStringEstadisticas(['tipo' => 'admin_' . $reporte]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas y reportes · Superadmin</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos/admin.css">
</head>
<body>
<div class="container-fluid">
    <div class="row admin-shell">
        <div class="col-12 col-lg-2 p-0">
            <?php include '_menu.php'; ?>
        </div>

        <main class="col-12 col-lg-10 admin-main">
            <div class="admin-stat-header">
                <div>
                    <div class="admin-stat-kicker"><i class="bi bi-graph-up-arrow"></i> Inteligencia del negocio</div>
                    <h1>Estadísticas y reportes</h1>
                    <p>Analizá clientes, suscripciones, pagos e ingresos de VetClinic Pro con información real.</p>
                </div>
                <div class="admin-stat-header-actions">
                    <a href="../reportes/pdf.php?<?= htmlspecialchars($queryPdfIngresos) ?>" class="admin-stat-btn admin-stat-btn-export ingreso">
                        <span class="admin-stat-btn-icon"><i class="bi bi-cash-coin"></i></span>
                        <span class="admin-stat-btn-text"><strong>Ingresos</strong><small>Descargar PDF</small></span>
                    </a>
                    <a href="../reportes/pdf.php?<?= htmlspecialchars($queryPdfResumen) ?>" class="admin-stat-btn admin-stat-btn-export resumen">
                        <span class="admin-stat-btn-icon"><i class="bi bi-file-earmark-bar-graph"></i></span>
                        <span class="admin-stat-btn-text"><strong>Reporte general</strong><small>Descargar PDF</small></span>
                    </a>
                </div>
            </div>

            <section class="admin-stat-panel admin-stat-filtros mb-4">
                <div class="admin-stat-filtros-head">
                    <div>
                        <div class="admin-stat-panel-title"><i class="bi bi-sliders2"></i> Filtros de análisis</div>
                        <p>Ajustá el período y segmentá la información que querés analizar.</p>
                    </div>
                    <span class="admin-stat-periodo-chip"><i class="bi bi-calendar3"></i><?= htmlspecialchars($rango['etiqueta']) ?></span>
                </div>
                <form method="GET" class="row g-3 align-items-end admin-stat-filter-form">
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label" for="periodo">Período</label>
                        <select class="form-select" name="periodo" id="periodo">
                            <?php foreach ([
                                'mes_actual' => 'Mes actual',
                                'mes_anterior' => 'Mes anterior',
                                '3m' => 'Últimos 3 meses',
                                '6m' => 'Últimos 6 meses',
                                '12m' => 'Últimos 12 meses',
                                'anio_actual' => 'Año actual',
                                'personalizado' => 'Personalizado'
                            ] as $valor => $texto): ?>
                                <option value="<?= htmlspecialchars($valor) ?>" <?= $rango['periodo'] === $valor ? 'selected' : '' ?>><?= htmlspecialchars($texto) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label" for="desde">Desde</label>
                        <input class="form-control" type="date" name="desde" id="desde" value="<?= htmlspecialchars($_GET['desde'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label" for="hasta">Hasta</label>
                        <input class="form-control" type="date" name="hasta" id="hasta" value="<?= htmlspecialchars($_GET['hasta'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label" for="id_veterinario">Veterinario</label>
                        <select class="form-select" name="id_veterinario" id="id_veterinario">
                            <option value="0">Todos los clientes</option>
                            <?php foreach ($veterinariosFiltro as $veterinario): ?>
                                <option value="<?= (int) $veterinario['id_veterinario'] ?>" <?= $filtros['id_veterinario'] === (int) $veterinario['id_veterinario'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($veterinario['apellido'] . ', ' . $veterinario['nombre'] . ' · ' . $veterinario['matricula']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label" for="estado_suscripcion">Suscripción</label>
                        <select class="form-select" name="estado_suscripcion" id="estado_suscripcion">
                            <option value="">Todas</option>
                            <?php foreach (['activa', 'atrasada', 'suspendida', 'cancelada', 'pendiente_pago'] as $estado): ?>
                                <option value="<?= $estado ?>" <?= $filtros['estado_suscripcion'] === $estado ? 'selected' : '' ?>><?= htmlspecialchars(etiquetaEstadoSuscripcion($estado)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label" for="estado_pago">Pago</label>
                        <select class="form-select" name="estado_pago" id="estado_pago">
                            <option value="">Todos</option>
                            <?php foreach (['aprobado', 'pendiente', 'rechazado'] as $estado): ?>
                                <option value="<?= $estado ?>" <?= $filtros['estado_pago'] === $estado ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($estado)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label" for="grafico_meses">Gráficos</label>
                        <select class="form-select" name="grafico_meses" id="grafico_meses">
                            <?php foreach ([6, 12] as $cantidad): ?>
                                <option value="<?= $cantidad ?>" <?= $graficoMeses === $cantidad ? 'selected' : '' ?>>Últimos <?= $cantidad ?> meses</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 admin-stat-filter-actions">
                        <input type="hidden" name="reporte" value="<?= htmlspecialchars($reporte) ?>">
                        <button class="admin-stat-btn admin-stat-btn-primary" type="submit"><i class="bi bi-funnel-fill"></i> Aplicar filtros</button>
                        <a class="admin-stat-btn admin-stat-btn-ghost" href="estadisticas.php"><i class="bi bi-arrow-counterclockwise"></i> Limpiar filtros</a>
                    </div>
                </form>
            </section>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <article class="admin-stat-card admin-stat-card-primary clientes">
                        <div class="admin-stat-icon azul"><i class="bi bi-person-badge"></i></div>
                        <span>Clientes totales</span>
                        <strong><?= (int) $metricas['clientes_total'] ?></strong>
                        <small>Veterinarios con suscripción</small>
                    </article>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <article class="admin-stat-card admin-stat-card-primary activos">
                        <div class="admin-stat-icon verde"><i class="bi bi-check-circle"></i></div>
                        <span>Clientes activos</span>
                        <strong><?= (int) $metricas['clientes_activos'] ?></strong>
                        <small>Suscripción activa</small>
                    </article>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <article class="admin-stat-card admin-stat-card-primary nuevos">
                        <div class="admin-stat-icon celeste"><i class="bi bi-person-plus"></i></div>
                        <span>Nuevos clientes</span>
                        <strong><?= (int) $metricas['nuevos_actual'] ?></strong>
                        <div class="<?= claseComparacionAdmin($metricas['comparacion_nuevos']) ?>">
                            <i class="bi <?= iconoComparacionAdmin($metricas['comparacion_nuevos']) ?>"></i>
                            <?= htmlspecialchars($metricas['comparacion_nuevos']['texto']) ?>
                        </div>
                    </article>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <article class="admin-stat-card admin-stat-card-primary ingresos">
                        <div class="admin-stat-icon violeta"><i class="bi bi-cash-stack"></i></div>
                        <span>Ingresos del período</span>
                        <strong><?= htmlspecialchars(formatoDineroEstadistica((float) $metricas['ingresos_actual'])) ?></strong>
                        <div class="<?= claseComparacionAdmin($metricas['comparacion_ingresos']) ?>">
                            <i class="bi <?= iconoComparacionAdmin($metricas['comparacion_ingresos']) ?>"></i>
                            <?= htmlspecialchars($metricas['comparacion_ingresos']['texto']) ?>
                        </div>
                    </article>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <article class="admin-stat-card compacto advertencia"><div class="admin-stat-mini-icon"><i class="bi bi-clock-history"></i></div><span>Atrasados</span><strong><?= (int) $metricas['clientes_atrasados'] ?></strong><small>Dentro de tolerancia</small></article>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <article class="admin-stat-card compacto peligro"><div class="admin-stat-mini-icon"><i class="bi bi-lock"></i></div><span>Suspendidos</span><strong><?= (int) $metricas['clientes_suspendidos'] ?></strong><small>Sin acceso</small></article>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <article class="admin-stat-card compacto pendiente"><div class="admin-stat-mini-icon"><i class="bi bi-hourglass-split"></i></div><span>Pendientes de pago</span><strong><?= (int) $metricas['clientes_pendientes'] ?></strong><small>Sin primera activación</small></article>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <article class="admin-stat-card compacto bajas"><div class="admin-stat-mini-icon"><i class="bi bi-person-dash"></i></div><span>Bajas del período</span><strong><?= (int) $metricas['bajas_periodo'] ?></strong><small>Cancelaciones registradas</small></article>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <article class="admin-stat-card compacto aprobado"><div class="admin-stat-mini-icon"><i class="bi bi-check2-circle"></i></div><span>Pagos aprobados</span><strong><?= (int) $metricas['pagos_aprobados_actual'] ?></strong><small><?= htmlspecialchars($rango['etiqueta']) ?></small></article>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-7">
                    <section class="admin-stat-panel admin-stat-chart-card">
                        <div class="admin-stat-chart-head"><div class="admin-stat-chart-title"><span class="admin-stat-chart-icon"><i class="bi bi-graph-up-arrow"></i></span><div><span>Crecimiento</span><h2>Nuevos clientes e ingresos</h2></div></div><small>Últimos <?= $graficoMeses ?> meses</small></div>
                        <div class="admin-stat-chart"><canvas id="graficoCrecimiento"></canvas></div>
                    </section>
                </div>
                <div class="col-xl-5">
                    <section class="admin-stat-panel admin-stat-chart-card">
                        <div class="admin-stat-chart-head"><div class="admin-stat-chart-title"><span class="admin-stat-chart-icon"><i class="bi bi-pie-chart"></i></span><div><span>Cartera</span><h2>Estados de suscripción</h2></div></div></div>
                        <div class="admin-stat-chart compacto"><canvas id="graficoSuscripciones"></canvas></div>
                    </section>
                </div>
                <div class="col-xl-6">
                    <section class="admin-stat-panel admin-stat-chart-card">
                        <div class="admin-stat-chart-head"><div class="admin-stat-chart-title"><span class="admin-stat-chart-icon"><i class="bi bi-credit-card-2-front"></i></span><div><span>Operaciones</span><h2>Pagos por estado</h2></div></div></div>
                        <div class="admin-stat-chart compacto"><canvas id="graficoPagos"></canvas></div>
                    </section>
                </div>
                <div class="col-xl-6">
                    <section class="admin-stat-panel admin-stat-chart-card">
                        <div class="admin-stat-chart-head"><div class="admin-stat-chart-title"><span class="admin-stat-chart-icon"><i class="bi bi-people"></i></span><div><span>Movimiento comercial</span><h2>Altas y bajas</h2></div></div></div>
                        <div class="admin-stat-chart compacto"><canvas id="graficoAltasBajas"></canvas></div>
                    </section>
                </div>
            </div>

            <section class="admin-stat-panel admin-stat-reportes">
                <div class="admin-stat-reportes-head">
                    <div>
                        <div class="admin-stat-kicker">Reportes</div>
                        <h2>Información exportable</h2>
                        <p>La vista muestra hasta 100 registros. PDF y CSV incluyen todos los resultados del filtro.</p>
                    </div>
                    <div class="admin-stat-exportaciones">
                        <a href="../reportes/pdf.php?<?= htmlspecialchars($queryPdfReporte) ?>" class="admin-stat-btn admin-stat-btn-download pdf"><i class="bi bi-file-earmark-pdf"></i> Exportar PDF</a>
                        <a href="../reportes/exportar_csv.php?<?= htmlspecialchars($queryCsvReporte) ?>" class="admin-stat-btn admin-stat-btn-download csv"><i class="bi bi-filetype-csv"></i> Exportar CSV</a>
                    </div>
                </div>

                <div class="admin-stat-tabs">
                    <?php foreach (['pagos' => ['Pagos', 'bi-credit-card'], 'suscripciones' => ['Suscripciones', 'bi-arrow-repeat'], 'clientes' => ['Clientes', 'bi-people']] as $clave => $datosTab): ?>
                        <a href="?<?= htmlspecialchars(queryStringEstadisticas(['reporte' => $clave])) ?>" class="admin-stat-tab <?= $reporte === $clave ? 'activo' : '' ?>"><i class="bi <?= htmlspecialchars($datosTab[1]) ?>"></i><?= htmlspecialchars($datosTab[0]) ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="table-responsive">
                    <table class="table admin-stat-table align-middle mb-0">
                        <thead>
                        <?php if ($reporte === 'suscripciones'): ?>
                            <tr><th>Veterinario</th><th>Plan</th><th>Estado</th><th>Inicio</th><th>Vencimiento</th><th>Cancelación</th></tr>
                        <?php elseif ($reporte === 'clientes'): ?>
                            <tr><th>Veterinario</th><th>Matrícula</th><th>Especialidad</th><th>Alta</th><th>Suscripción</th><th>Vencimiento</th></tr>
                        <?php else: ?>
                            <tr><th>Fecha</th><th>Veterinario</th><th>Operación</th><th>Medio</th><th>Importe</th><th>Estado</th></tr>
                        <?php endif; ?>
                        </thead>
                        <tbody>
                        <?php if (!$filasReporte): ?>
                            <tr><td colspan="6"><div class="admin-stat-empty"><i class="bi bi-inbox"></i><strong>No hay datos para este filtro</strong><span>Probá ampliando el período o cambiando los filtros.</span></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($filasReporte as $fila): ?>
                                <?php if ($reporte === 'suscripciones'): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($fila['apellido'] . ', ' . $fila['nombre']) ?></strong><small><?= htmlspecialchars($fila['matricula']) ?></small></td>
                                        <td><?= htmlspecialchars($fila['plan_nombre']) ?></td>
                                        <td><span class="badge-estado <?= htmlspecialchars($fila['estado']) ?>"><?= htmlspecialchars(etiquetaEstadoSuscripcion($fila['estado'])) ?></span></td>
                                        <td><?= $fila['fecha_inicio'] ? date('d/m/Y', strtotime($fila['fecha_inicio'])) : '—' ?></td>
                                        <td><?= $fila['fecha_vencimiento'] ? date('d/m/Y', strtotime($fila['fecha_vencimiento'])) : '—' ?></td>
                                        <td><?= $fila['fecha_cancelacion'] ? date('d/m/Y', strtotime($fila['fecha_cancelacion'])) : '—' ?></td>
                                    </tr>
                                <?php elseif ($reporte === 'clientes'): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($fila['apellido'] . ', ' . $fila['nombre']) ?></strong><small><?= htmlspecialchars($fila['email']) ?></small></td>
                                        <td><?= htmlspecialchars($fila['matricula']) ?></td>
                                        <td><?= htmlspecialchars($fila['especialidad'] ?: '—') ?></td>
                                        <td><?= date('d/m/Y', strtotime($fila['fecha_registro'])) ?></td>
                                        <td><?= htmlspecialchars($fila['estado_suscripcion'] ? etiquetaEstadoSuscripcion($fila['estado_suscripcion']) : 'Sin suscripción') ?></td>
                                        <td><?= $fila['fecha_vencimiento'] ? date('d/m/Y', strtotime($fila['fecha_vencimiento'])) : '—' ?></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($fila['fecha_operacion'])) ?></td>
                                        <td><strong><?= htmlspecialchars($fila['apellido'] . ', ' . $fila['nombre']) ?></strong><small><?= htmlspecialchars($fila['matricula']) ?></small></td>
                                        <td><span class="admin-stat-operation"><?= htmlspecialchars($fila['numero_operacion']) ?></span></td>
                                        <td><?= htmlspecialchars(ucfirst($fila['medio_pago'])) ?></td>
                                        <td><strong><?= htmlspecialchars(formatoDineroEstadistica((float) $fila['importe'])) ?></strong></td>
                                        <td><span class="admin-stat-pago <?= htmlspecialchars($fila['estado']) ?>"><?= htmlspecialchars(ucfirst($fila['estado'])) ?></span></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const serieClientes = <?= json_encode($serieClientes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const serieIngresos = <?= json_encode($serieIngresos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const estadosSuscripcion = <?= json_encode($estadosSuscripcion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const estadosPago = <?= json_encode($estadosPago, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const altasBajas = <?= json_encode($altasBajas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

Chart.defaults.font.family = "Plus Jakarta Sans";
Chart.defaults.color = "#6b7280";

new Chart(document.getElementById('graficoCrecimiento'), {
    data: {
        labels: serieClientes.labels,
        datasets: [
            {
                type: 'bar',
                label: 'Nuevos clientes',
                data: serieClientes.values,
                backgroundColor: '#7cb3fc',
                borderRadius: 7,
                yAxisID: 'y'
            },
            {
                type: 'line',
                label: 'Ingresos',
                data: serieIngresos.values,
                borderColor: '#1a1f5e',
                backgroundColor: 'rgba(26,31,94,.08)',
                tension: .3,
                pointRadius: 3,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom' } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
        }
    }
});

new Chart(document.getElementById('graficoSuscripciones'), {
    type: 'doughnut',
    data: {
        labels: ['Activas', 'Atrasadas', 'Suspendidas', 'Canceladas', 'Pendientes'],
        datasets: [{
            data: [estadosSuscripcion.activa, estadosSuscripcion.atrasada, estadosSuscripcion.suspendida, estadosSuscripcion.cancelada, estadosSuscripcion.pendiente_pago],
            backgroundColor: ['#2f855a', '#d69e2e', '#c05656', '#718096', '#4992b5'],
            borderWidth: 0
        }]
    },
    options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('graficoPagos'), {
    type: 'bar',
    data: {
        labels: ['Aprobados', 'Pendientes', 'Rechazados'],
        datasets: [{
            data: [estadosPago.aprobado, estadosPago.pendiente, estadosPago.rechazado],
            backgroundColor: ['#2f855a', '#d69e2e', '#c05656'],
            borderRadius: 7
        }]
    },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('graficoAltasBajas'), {
    type: 'line',
    data: {
        labels: altasBajas.labels,
        datasets: [
            { label: 'Altas', data: altasBajas.altas, borderColor: '#2f855a', backgroundColor: 'rgba(47,133,90,.08)', tension: .3 },
            { label: 'Bajas', data: altasBajas.bajas, borderColor: '#c05656', backgroundColor: 'rgba(192,86,86,.08)', tension: .3 }
        ]
    },
    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

const periodo = document.getElementById('periodo');
const desde = document.getElementById('desde');
const hasta = document.getElementById('hasta');
function actualizarFechasPersonalizadas() {
    const habilitado = periodo.value === 'personalizado';
    desde.disabled = !habilitado;
    hasta.disabled = !habilitado;
}
periodo.addEventListener('change', actualizarFechasPersonalizadas);
actualizarFechasPersonalizadas();
</script>
</body>
</html>
