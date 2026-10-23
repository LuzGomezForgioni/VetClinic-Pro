<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (
    empty($_SESSION['logueado']) ||
    ($_SESSION['tipo_usuario'] ?? '') !== 'veterinario'
) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';
require_once '../config/proteger_acceso_veterinario.php';
require_once '../config/estadisticas.php';

$idVeterinario = (int) ($_SESSION['usuario_id'] ?? 0);
$nombre = $_SESSION['usuario_nombre'] ?? 'Doctor/a';
$apellido = $_SESSION['usuario_apellido'] ?? '';
$sexo = strtoupper(trim($_SESSION['usuario_sexo'] ?? ''));
$titulo = $sexo === 'F' ? 'Dra.' : 'Dr.';

$periodo = trim((string) ($_GET['periodo'] ?? 'mes_actual'));
$rango = rangoEstadisticas(
    $periodo,
    $_GET['desde'] ?? null,
    $_GET['hasta'] ?? null
);
$filtros = filtrosVeterinarioDesdeGet();
$graficoMeses = (int) ($_GET['grafico_meses'] ?? 6);
$graficoMeses = in_array($graficoMeses, [3, 6, 12], true) ? $graficoMeses : 6;

$metricas = metricasVeterinario(
    $conn,
    $idVeterinario,
    $rango,
    $filtros
);

$turnosEstado = distribucionTurnosVeterinario(
    $conn,
    $idVeterinario,
    $rango,
    $filtros
);

$seriePacientes = seriePacientesVeterinario(
    $conn,
    $idVeterinario,
    $graficoMeses,
    $filtros
);

$serieVacunas = serieVacunasVeterinario(
    $conn,
    $idVeterinario,
    $graficoMeses,
    $filtros
);

$especiesAtendidas = especiesAtendidasVeterinario(
    $conn,
    $idVeterinario,
    $rango,
    $filtros
);

$demandaDias = demandaDiasVeterinario(
    $conn,
    $idVeterinario,
    $rango,
    $filtros
);

$franjaDemanda = franjaMayorDemandaVeterinario(
    $conn,
    $idVeterinario,
    $rango,
    $filtros
);

$opciones = opcionesFiltrosVeterinario(
    $conn,
    $idVeterinario
);

$reporte = trim((string) ($_GET['reporte'] ?? 'pacientes'));
$reportesPermitidos = ['pacientes', 'turnos', 'vacunas'];

if (!in_array($reporte, $reportesPermitidos, true)) {
    $reporte = 'pacientes';
}

if ($reporte === 'turnos') {
    $filasReporte = reporteTurnosVeterinario(
        $conn,
        $idVeterinario,
        $rango,
        $filtros,
        100
    );
} elseif ($reporte === 'vacunas') {
    $filasReporte = reporteVacunasVeterinario(
        $conn,
        $idVeterinario,
        $rango,
        $filtros,
        100
    );
} else {
    $filasReporte = reportePacientesVeterinario(
        $conn,
        $idVeterinario,
        $rango,
        $filtros,
        100
    );
}

function claseComparacion(array $comparacion): string
{
    if ($comparacion['direccion'] === 'sube') {
        return 'estadistica-comparacion sube';
    }

    if ($comparacion['direccion'] === 'baja') {
        return 'estadistica-comparacion baja';
    }

    return 'estadistica-comparacion igual';
}

function iconoComparacion(array $comparacion): string
{
    if ($comparacion['direccion'] === 'sube') {
        return 'bi-arrow-up-right';
    }

    if ($comparacion['direccion'] === 'baja') {
        return 'bi-arrow-down-right';
    }

    return 'bi-dash';
}

$queryBase = queryStringEstadisticas();
$queryPdfResumen = queryStringEstadisticas(['tipo' => 'vet_resumen']);
$queryPdfReporte = queryStringEstadisticas(['tipo' => 'vet_' . $reporte]);
$queryCsvReporte = queryStringEstadisticas(['tipo' => 'vet_' . $reporte]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos/inicio.css">
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">
        <div class="col-12 col-lg-2 panel-lateral">
            <a href="inicio_veterinario.php" class="logo-lateral">
                <img src="../img/VetClinic Pro.png" alt="Logo VetClinic Pro">
                <div>
                    <div class="nombre-sistema">VetClinic Pro</div>
                    <div class="subtitulo-sistema">Portal del veterinario</div>
                </div>
            </a>
            <div class="seccion-nav">Principal</div>
            <a href="inicio_veterinario.php" class="item-menu"><i class="bi bi-grid"></i> Inicio</a>
            <a href="turnos_veterinario.php" class="item-menu"><i class="bi bi-calendar-check"></i> Turnos</a>
            <a href="mascotas_veterinario.php" class="item-menu"><i class="bi bi-heart-pulse"></i> Mascotas</a>
            <a href="historiales_veterinario.php" class="item-menu"><i class="bi bi-journal-medical"></i> Historial clínico</a>
            <div class="seccion-nav">Gestión</div>
            <a href="propietarios_veterinario.php" class="item-menu"><i class="bi bi-people"></i> Propietarios</a>
            <a href="vacunaciones_veterinario.php" class="item-menu"><i class="bi bi-shield-plus"></i> Vacunaciones</a>
            <a href="mensajes.php" class="item-menu"><i class="bi bi-chat-dots"></i> Mensajes</a>
            <a href="estadisticas.php" class="item-menu activo"><i class="bi bi-graph-up-arrow"></i> Estadísticas</a>
            <div class="seccion-nav">Configuración</div>
            <a href="configuracion_veterinario.php" class="item-menu"><i class="bi bi-gear"></i> Mi configuración</a>
            <a href="horarios_atencion.php" class="item-menu"><i class="bi bi-clock"></i> Horarios de atención</a>
            <a href="../suscripciones/mi_suscripcion.php" class="item-menu"><i class="bi bi-credit-card-2-front"></i> Mi suscripción</a>
            <div class="usuario-lateral">
                <div class="avatar-usuario">
                    <?= htmlspecialchars(strtoupper(mb_substr($nombre, 0, 1) . mb_substr($apellido, 0, 1))) ?>
                </div>
                <div>
                    <div class="nombre-usuario"><?= htmlspecialchars($nombre . ' ' . $apellido) ?></div>
                    <div class="rol-usuario">Veterinario</div>
                </div>
            </div>
        </div>

        <main class="col-12 col-lg-10 area-principal">
            <div class="barra-superior">
                <div>
                    <p class="titulo-pagina">Estadísticas y reportes</p>
                    <p class="fecha-hoy">Analizá tu actividad profesional con datos reales del sistema.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="inicio_veterinario.php" class="icono-barra" title="Volver al inicio"><i class="bi bi-arrow-left"></i></a>
                    <a href="../logout.php" class="icono-barra" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></a>
                </div>
            </div>

            <section class="estadisticas-cabecera mb-4">
                <div>
                    <span class="estadisticas-kicker">Rendimiento profesional</span>
                    <h1><?= htmlspecialchars($titulo . ' ' . $apellido) ?></h1>
                    <p>
                        Período analizado: <strong><?= htmlspecialchars($rango['etiqueta']) ?></strong>
                        · <?= date('d/m/Y', strtotime($rango['desde'])) ?> al <?= date('d/m/Y', strtotime($rango['hasta'])) ?>
                    </p>
                </div>
                <div class="estadisticas-acciones-cabecera">
                    <a href="../reportes/pdf.php?<?= htmlspecialchars($queryPdfResumen) ?>" class="btn-estadistica-secundario">
                        <i class="bi bi-file-earmark-pdf"></i> Reporte mensual PDF
                    </a>
                </div>
            </section>

            <section class="tarjeta estadisticas-filtros mb-4">
                <div class="titulo-tarjeta"><i class="bi bi-funnel"></i> Filtros</div>
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-sm-6 col-xl-2">
                        <label for="periodo" class="form-label">Período</label>
                        <select name="periodo" id="periodo" class="form-select">
                            <?php
                            $periodos = [
                                'mes_actual' => 'Mes actual',
                                'mes_anterior' => 'Mes anterior',
                                '3m' => 'Últimos 3 meses',
                                '6m' => 'Últimos 6 meses',
                                '12m' => 'Últimos 12 meses',
                                'anio_actual' => 'Año actual',
                                'personalizado' => 'Personalizado'
                            ];
                            foreach ($periodos as $valor => $texto):
                            ?>
                                <option value="<?= htmlspecialchars($valor) ?>" <?= $rango['periodo'] === $valor ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($texto) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label for="desde" class="form-label">Desde</label>
                        <input type="date" class="form-control" id="desde" name="desde" value="<?= htmlspecialchars($_GET['desde'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label for="hasta" class="form-label">Hasta</label>
                        <input type="date" class="form-control" id="hasta" name="hasta" value="<?= htmlspecialchars($_GET['hasta'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label for="id_mascota" class="form-label">Mascota</label>
                        <select name="id_mascota" id="id_mascota" class="form-select">
                            <option value="0">Todas</option>
                            <?php foreach ($opciones['mascotas'] as $mascota): ?>
                                <option value="<?= (int) $mascota['id_mascota'] ?>" <?= $filtros['id_mascota'] === (int) $mascota['id_mascota'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mascota['nombre'] . ' · ' . $mascota['propietario_nombre'] . ' ' . $mascota['propietario_apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label for="id_propietario" class="form-label">Propietario</label>
                        <select name="id_propietario" id="id_propietario" class="form-select">
                            <option value="0">Todos</option>
                            <?php foreach ($opciones['propietarios'] as $propietario): ?>
                                <option value="<?= (int) $propietario['id_propietario'] ?>" <?= $filtros['id_propietario'] === (int) $propietario['id_propietario'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($propietario['apellido'] . ', ' . $propietario['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label for="id_especie" class="form-label">Especie</label>
                        <select name="id_especie" id="id_especie" class="form-select">
                            <option value="0">Todas</option>
                            <?php foreach ($opciones['especies'] as $especie): ?>
                                <option value="<?= (int) $especie['id_especie'] ?>" <?= $filtros['id_especie'] === (int) $especie['id_especie'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($especie['nombre_especie']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label for="estado" class="form-label">Estado del turno</label>
                        <select name="estado" id="estado" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach (['pendiente', 'confirmado', 'cancelado', 'realizado'] as $estado): ?>
                                <option value="<?= $estado ?>" <?= $filtros['estado'] === $estado ? 'selected' : '' ?>><?= ucfirst($estado) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label for="grafico_meses" class="form-label">Gráficos</label>
                        <select name="grafico_meses" id="grafico_meses" class="form-select">
                            <?php foreach ([3, 6, 12] as $cantidad): ?>
                                <option value="<?= $cantidad ?>" <?= $graficoMeses === $cantidad ? 'selected' : '' ?>>Últimos <?= $cantidad ?> meses</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <input type="hidden" name="reporte" value="<?= htmlspecialchars($reporte) ?>">
                        <button class="btn-estadistica-principal" type="submit"><i class="bi bi-search"></i> Aplicar filtros</button>
                    </div>
                    <div class="col-auto">
                        <a href="estadisticas.php" class="btn-estadistica-secundario"><i class="bi bi-arrow-counterclockwise"></i> Restablecer</a>
                    </div>
                </form>
                <?php if ($filtros['estado'] !== ''): ?>
                    <p class="estadisticas-nota-filtro mb-0 mt-3">
                        <i class="bi bi-info-circle"></i>
                        El filtro de estado afecta métricas basadas en turnos e historias. Las vacunaciones se filtran por profesional, fecha, mascota, propietario y especie.
                    </p>
                <?php endif; ?>
            </section>

            <div class="row g-3 mb-4">
                <?php
                $tarjetas = [
                    ['Pacientes atendidos', 'pacientes', 'bi-heart-pulse', 'icono-azul-oscuro'],
                    ['Consultas realizadas', 'consultas', 'bi-clipboard2-pulse', 'icono-azul-medio'],
                    ['Nuevos pacientes', 'nuevos_pacientes', 'bi-person-plus', 'icono-verde'],
                    ['Propietarios atendidos', 'propietarios', 'bi-people', 'icono-azul-claro'],
                    ['Vacunas registradas', 'vacunas', 'bi-shield-plus', 'icono-amarillo'],
                    ['Historias clínicas', 'historias', 'bi-journal-medical', 'icono-azul-oscuro']
                ];
                foreach ($tarjetas as [$etiqueta, $clave, $icono, $claseIcono]):
                    $comparacion = $metricas['comparaciones'][$clave];
                ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="tarjeta-resumen estadistica-metrica-card">
                            <div class="estadistica-metrica-top">
                                <div class="icono-metrica <?= $claseIcono ?>"><i class="bi <?= $icono ?>"></i></div>
                                <span class="etiqueta-metrica"><?= htmlspecialchars($etiqueta) ?></span>
                            </div>
                            <div class="numero-metrica"><?= (int) $metricas['actual'][$clave] ?></div>
                            <div class="<?= claseComparacion($comparacion) ?>">
                                <i class="bi <?= iconoComparacion($comparacion) ?>"></i>
                                <?= htmlspecialchars($comparacion['texto']) ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>

            <section class="tarjeta mb-4 estadisticas-turnos-resumen">
                <div class="titulo-tarjeta"><i class="bi bi-calendar-check"></i> Turnos del período</div>
                <div class="estadisticas-estados-grid">
                    <?php foreach ([
                        'realizado' => ['Realizados', 'bi-check-circle'],
                        'confirmado' => ['Confirmados', 'bi-calendar2-check'],
                        'pendiente' => ['Pendientes', 'bi-hourglass-split'],
                        'cancelado' => ['Cancelados', 'bi-x-circle']
                    ] as $estado => [$texto, $icono]): ?>
                        <div class="estadisticas-estado-item <?= $estado ?>">
                            <i class="bi <?= $icono ?>"></i>
                            <div><strong><?= (int) $turnosEstado[$estado] ?></strong><span><?= $texto ?></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="estadisticas-demanda-destacada">
                    <i class="bi bi-clock-history"></i>
                    <span>Franja con mayor demanda:</span>
                    <strong><?= htmlspecialchars($franjaDemanda['etiqueta']) ?></strong>
                    <?php if ($franjaDemanda['total'] > 0): ?>
                        <small>· <?= (int) $franjaDemanda['total'] ?> consultas realizadas</small>
                    <?php endif; ?>
                </div>
            </section>

            <div class="row g-4 mb-4">
                <div class="col-xl-7">
                    <section class="tarjeta estadistica-grafico-card">
                        <div class="estadistica-grafico-head">
                            <div>
                                <span>Actividad mensual</span>
                                <h2>Pacientes y vacunaciones</h2>
                            </div>
                            <small>Últimos <?= $graficoMeses ?> meses</small>
                        </div>
                        <div class="estadistica-canvas-wrap"><canvas id="graficoActividad"></canvas></div>
                    </section>
                </div>
                <div class="col-xl-5">
                    <section class="tarjeta estadistica-grafico-card">
                        <div class="estadistica-grafico-head">
                            <div><span>Agenda</span><h2>Turnos por estado</h2></div>
                        </div>
                        <div class="estadistica-canvas-wrap compacto"><canvas id="graficoEstados"></canvas></div>
                    </section>
                </div>
                <div class="col-xl-6">
                    <section class="tarjeta estadistica-grafico-card">
                        <div class="estadistica-grafico-head">
                            <div><span>Pacientes</span><h2>Especies atendidas</h2></div>
                        </div>
                        <div class="estadistica-canvas-wrap compacto"><canvas id="graficoEspecies"></canvas></div>
                    </section>
                </div>
                <div class="col-xl-6">
                    <section class="tarjeta estadistica-grafico-card">
                        <div class="estadistica-grafico-head">
                            <div><span>Demanda</span><h2>Consultas por día de la semana</h2></div>
                        </div>
                        <div class="estadistica-canvas-wrap compacto"><canvas id="graficoDias"></canvas></div>
                    </section>
                </div>
            </div>

            <section class="tarjeta estadisticas-reportes">
                <div class="estadisticas-reportes-head">
                    <div>
                        <span class="estadisticas-kicker">Reportes</span>
                        <h2>Información detallada</h2>
                        <p>La tabla respeta los filtros seleccionados. La vista muestra hasta 100 registros; las exportaciones incluyen todos.</p>
                    </div>
                    <div class="estadisticas-exportaciones">
                        <a href="../reportes/pdf.php?<?= htmlspecialchars($queryPdfReporte) ?>" class="btn-estadistica-secundario"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                        <a href="../reportes/exportar_csv.php?<?= htmlspecialchars($queryCsvReporte) ?>" class="btn-estadistica-secundario"><i class="bi bi-filetype-csv"></i> CSV</a>
                    </div>
                </div>

                <div class="estadisticas-tabs">
                    <?php foreach ([
                        'pacientes' => 'Pacientes atendidos',
                        'turnos' => 'Turnos',
                        'vacunas' => 'Vacunaciones'
                    ] as $clave => $texto): ?>
                        <a class="estadisticas-tab <?= $reporte === $clave ? 'activo' : '' ?>" href="?<?= htmlspecialchars(queryStringEstadisticas(['reporte' => $clave])) ?>">
                            <?= htmlspecialchars($texto) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="table-responsive">
                    <table class="table estadisticas-tabla align-middle mb-0">
                        <thead>
                        <?php if ($reporte === 'turnos'): ?>
                            <tr><th>Fecha</th><th>Mascota</th><th>Propietario</th><th>Motivo</th><th>Estado</th><th></th></tr>
                        <?php elseif ($reporte === 'vacunas'): ?>
                            <tr><th>Aplicación</th><th>Mascota</th><th>Propietario</th><th>Vacuna</th><th>Próxima</th><th></th></tr>
                        <?php else: ?>
                            <tr><th>Fecha</th><th>Mascota</th><th>Especie</th><th>Propietario</th><th>Motivo</th><th>Diagnóstico</th></tr>
                        <?php endif; ?>
                        </thead>
                        <tbody>
                        <?php if (!$filasReporte): ?>
                            <tr><td colspan="6"><div class="estadisticas-vacio"><i class="bi bi-bar-chart"></i><strong>Sin datos para mostrar</strong><span>Probá cambiando el período o los filtros.</span></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($filasReporte as $fila): ?>
                                <?php if ($reporte === 'turnos'): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($fila['fecha_hora'])) ?></td>
                                        <td><strong><?= htmlspecialchars($fila['mascota']) ?></strong><small><?= htmlspecialchars($fila['especie']) ?></small></td>
                                        <td><?= htmlspecialchars($fila['propietario_nombre'] . ' ' . $fila['propietario_apellido']) ?></td>
                                        <td><?= htmlspecialchars($fila['motivo']) ?></td>
                                        <td><span class="estadisticas-estado-tabla <?= htmlspecialchars($fila['estado']) ?>"><?= htmlspecialchars(ucfirst($fila['estado'])) ?></span></td>
                                        <td class="text-end"><a class="estadisticas-link-tabla" href="../reportes/pdf.php?tipo=turno&id=<?= (int) $fila['id_turno'] ?>"><i class="bi bi-file-earmark-pdf"></i> Comprobante</a></td>
                                    </tr>
                                <?php elseif ($reporte === 'vacunas'): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($fila['fecha_aplicacion'])) ?></td>
                                        <td><strong><?= htmlspecialchars($fila['mascota']) ?></strong><small><?= htmlspecialchars($fila['especie']) ?></small></td>
                                        <td><?= htmlspecialchars($fila['propietario_nombre'] . ' ' . $fila['propietario_apellido']) ?></td>
                                        <td><?= htmlspecialchars($fila['nombre_vacuna']) ?></td>
                                        <td><?= $fila['fecha_proxima'] ? date('d/m/Y', strtotime($fila['fecha_proxima'])) : '—' ?></td>
                                        <td class="text-end"><a class="estadisticas-link-tabla" href="../reportes/pdf.php?tipo=vacuna&id=<?= (int) $fila['id_vacunacion'] ?>"><i class="bi bi-file-earmark-pdf"></i> Constancia</a></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($fila['fecha_hora'])) ?></td>
                                        <td><a class="estadisticas-link-tabla" href="ficha_mascota.php?id_mascota=<?= (int) $fila['id_mascota'] ?>"><?= htmlspecialchars($fila['mascota']) ?></a></td>
                                        <td><?= htmlspecialchars($fila['especie']) ?></td>
                                        <td><?= htmlspecialchars($fila['propietario_nombre'] . ' ' . $fila['propietario_apellido']) ?></td>
                                        <td><?= htmlspecialchars($fila['motivo']) ?></td>
                                        <td><?= htmlspecialchars($fila['diagnostico'] ?: 'Sin diagnóstico registrado') ?></td>
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
const datosPacientes = <?= json_encode($seriePacientes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const datosVacunas = <?= json_encode($serieVacunas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const datosEstados = <?= json_encode($turnosEstado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const datosEspecies = <?= json_encode($especiesAtendidas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const datosDias = <?= json_encode($demandaDias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

Chart.defaults.font.family = "Plus Jakarta Sans";
Chart.defaults.color = "#6b7280";

new Chart(document.getElementById('graficoActividad'), {
    type: 'line',
    data: {
        labels: datosPacientes.labels,
        datasets: [
            {
                label: 'Pacientes atendidos',
                data: datosPacientes.values,
                borderColor: '#1a1f5e',
                backgroundColor: 'rgba(26,31,94,.08)',
                fill: true,
                tension: .32,
                pointRadius: 3
            },
            {
                label: 'Vacunaciones',
                data: datosVacunas.values,
                borderColor: '#4992b5',
                backgroundColor: 'rgba(73,146,181,.06)',
                tension: .32,
                pointRadius: 3
            }
        ]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

new Chart(document.getElementById('graficoEstados'), {
    type: 'doughnut',
    data: {
        labels: ['Realizados', 'Confirmados', 'Pendientes', 'Cancelados'],
        datasets: [{
            data: [datosEstados.realizado, datosEstados.confirmado, datosEstados.pendiente, datosEstados.cancelado],
            backgroundColor: ['#2f855a', '#4992b5', '#d69e2e', '#c05656'],
            borderWidth: 0
        }]
    },
    options: {
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: { legend: { position: 'bottom' } }
    }
});

new Chart(document.getElementById('graficoEspecies'), {
    type: 'bar',
    data: {
        labels: datosEspecies.map(item => item.etiqueta),
        datasets: [{
            label: 'Pacientes',
            data: datosEspecies.map(item => Number(item.total)),
            backgroundColor: '#7cb3fc',
            borderRadius: 7
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

new Chart(document.getElementById('graficoDias'), {
    type: 'bar',
    data: {
        labels: datosDias.labels,
        datasets: [{
            label: 'Consultas',
            data: datosDias.values,
            backgroundColor: '#4992b5',
            borderRadius: 7
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
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
<script src="../mensajeria/notificaciones.js" defer></script>
</body>
</html>
