<?php

/**
 * Funciones compartidas para estadísticas y reportes de VetClinic Pro.
 * No guarda totales: todo se calcula desde las tablas reales.
 */

function ejecutarConsultaEstadistica(
    mysqli $conn,
    string $sql,
    string $tipos = '',
    array $parametros = []
): array {
    $stmt = $conn->prepare($sql);

    if ($tipos !== '') {
        $argumentos = [$tipos];

        foreach ($parametros as $indice => $valor) {
            $parametros[$indice] = $valor;
            $argumentos[] = &$parametros[$indice];
        }

        call_user_func_array([$stmt, 'bind_param'], $argumentos);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();
    $filas = $resultado ? $resultado->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $filas;
}

function valorEstadistica(
    mysqli $conn,
    string $sql,
    string $tipos = '',
    array $parametros = [],
    string $campo = 'total'
): float {
    $filas = ejecutarConsultaEstadistica(
        $conn,
        $sql,
        $tipos,
        $parametros
    );

    if (!$filas) {
        return 0;
    }

    return (float) ($filas[0][$campo] ?? 0);
}

function fechaEstadisticaValida(?string $fecha): bool
{
    if (!is_string($fecha) || $fecha === '') {
        return false;
    }

    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    return $objeto !== false && (
        $errores === false ||
        ($errores['warning_count'] === 0 && $errores['error_count'] === 0)
    ) && $objeto->format('Y-m-d') === $fecha;
}

function rangoEstadisticas(
    string $periodo,
    ?string $desdePersonalizado = null,
    ?string $hastaPersonalizado = null
): array {
    $hoy = new DateTimeImmutable('today');
    $periodosPermitidos = [
        'mes_actual',
        'mes_anterior',
        '3m',
        '6m',
        '12m',
        'anio_actual',
        'personalizado'
    ];

    if (!in_array($periodo, $periodosPermitidos, true)) {
        $periodo = 'mes_actual';
    }

    switch ($periodo) {
        case 'mes_anterior':
            $desde = $hoy->modify('first day of last month');
            $hasta = $hoy->modify('last day of last month');
            $etiqueta = 'Mes anterior';
            break;

        case '3m':
            $desde = $hoy->modify('first day of -2 months');
            $hasta = $hoy;
            $etiqueta = 'Últimos 3 meses';
            break;

        case '6m':
            $desde = $hoy->modify('first day of -5 months');
            $hasta = $hoy;
            $etiqueta = 'Últimos 6 meses';
            break;

        case '12m':
            $desde = $hoy->modify('first day of -11 months');
            $hasta = $hoy;
            $etiqueta = 'Últimos 12 meses';
            break;

        case 'anio_actual':
            $desde = $hoy->setDate((int) $hoy->format('Y'), 1, 1);
            $hasta = $hoy;
            $etiqueta = 'Año actual';
            break;

        case 'personalizado':
            if (
                fechaEstadisticaValida($desdePersonalizado) &&
                fechaEstadisticaValida($hastaPersonalizado)
            ) {
                $desde = new DateTimeImmutable($desdePersonalizado);
                $hasta = new DateTimeImmutable($hastaPersonalizado);

                if ($desde > $hasta) {
                    [$desde, $hasta] = [$hasta, $desde];
                }

                $etiqueta = 'Período personalizado';
            } else {
                $desde = $hoy->modify('first day of this month');
                $hasta = $hoy;
                $etiqueta = 'Mes actual';
                $periodo = 'mes_actual';
            }
            break;

        case 'mes_actual':
        default:
            $desde = $hoy->modify('first day of this month');
            $hasta = $hoy;
            $etiqueta = 'Mes actual';
            $periodo = 'mes_actual';
            break;
    }

    $dias = (int) $desde->diff($hasta)->days + 1;

    if ($periodo === 'mes_actual') {
        $anteriorDesde = $hoy->modify('first day of last month');
        $ultimoDiaAnterior = (int) $anteriorDesde->modify('last day of this month')->format('j');
        $diaComparable = min((int) $hoy->format('j'), $ultimoDiaAnterior);
        $anteriorHasta = $anteriorDesde->setDate(
            (int) $anteriorDesde->format('Y'),
            (int) $anteriorDesde->format('m'),
            $diaComparable
        );
    } elseif ($periodo === 'mes_anterior') {
        $anteriorDesde = $desde->modify('first day of previous month');
        $anteriorHasta = $desde->modify('last day of previous month');
    } elseif ($periodo === 'anio_actual') {
        $anioAnterior = (int) $hoy->format('Y') - 1;
        $anteriorDesde = $hoy->setDate($anioAnterior, 1, 1);
        $mesActual = (int) $hoy->format('m');
        $diaActual = (int) $hoy->format('d');
        $ultimoDiaMismoMes = (int) (new DateTimeImmutable(
            sprintf('%04d-%02d-01', $anioAnterior, $mesActual)
        ))->modify('last day of this month')->format('d');
        $anteriorHasta = $hoy->setDate(
            $anioAnterior,
            $mesActual,
            min($diaActual, $ultimoDiaMismoMes)
        );
    } else {
        $anteriorHasta = $desde->modify('-1 day');
        $anteriorDesde = $anteriorHasta->modify('-' . ($dias - 1) . ' days');
    }

    return [
        'periodo' => $periodo,
        'desde' => $desde->format('Y-m-d'),
        'hasta' => $hasta->format('Y-m-d'),
        'anterior_desde' => $anteriorDesde->format('Y-m-d'),
        'anterior_hasta' => $anteriorHasta->format('Y-m-d'),
        'etiqueta' => $etiqueta,
        'dias' => $dias
    ];
}

function compararEstadistica(float $actual, float $anterior): array
{
    $diferencia = $actual - $anterior;

    if ($anterior == 0.0) {
        if ($actual == 0.0) {
            return [
                'actual' => $actual,
                'anterior' => $anterior,
                'diferencia' => 0,
                'porcentaje' => 0,
                'direccion' => 'igual',
                'texto' => 'Sin variación respecto al período anterior'
            ];
        }

        return [
            'actual' => $actual,
            'anterior' => $anterior,
            'diferencia' => $diferencia,
            'porcentaje' => null,
            'direccion' => 'sube',
            'texto' => 'Sin base comparable en el período anterior'
        ];
    }

    $porcentaje = ($diferencia / $anterior) * 100;
    $direccion = 'igual';

    if ($porcentaje > 0.0001) {
        $direccion = 'sube';
    } elseif ($porcentaje < -0.0001) {
        $direccion = 'baja';
    }

    $numero = number_format(abs($porcentaje), 1, ',', '.');

    if ($direccion === 'sube') {
        $texto = '↑ ' . $numero . '% respecto al período anterior';
    } elseif ($direccion === 'baja') {
        $texto = '↓ ' . $numero . '% respecto al período anterior';
    } else {
        $texto = 'Sin variación respecto al período anterior';
    }

    return [
        'actual' => $actual,
        'anterior' => $anterior,
        'diferencia' => $diferencia,
        'porcentaje' => $porcentaje,
        'direccion' => $direccion,
        'texto' => $texto
    ];
}

function mesesEspanol(): array
{
    return [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic'
    ];
}

function etiquetasMeses(int $cantidad): array
{
    $cantidad = in_array($cantidad, [3, 6, 12], true) ? $cantidad : 6;
    $actual = new DateTimeImmutable('first day of this month');
    $inicio = $actual->modify('-' . ($cantidad - 1) . ' months');
    $resultado = [];
    $meses = mesesEspanol();

    for ($i = 0; $i < $cantidad; $i++) {
        $fecha = $inicio->modify('+' . $i . ' months');
        $clave = $fecha->format('Y-m');
        $resultado[$clave] = $meses[(int) $fecha->format('n')] . ' ' . $fecha->format('y');
    }

    return $resultado;
}

function filtrosVeterinarioDesdeGet(): array
{
    $estado = trim((string) ($_GET['estado'] ?? ''));
    $estados = ['', 'pendiente', 'confirmado', 'cancelado', 'realizado'];

    if (!in_array($estado, $estados, true)) {
        $estado = '';
    }

    return [
        'id_mascota' => max(0, (int) ($_GET['id_mascota'] ?? 0)),
        'id_propietario' => max(0, (int) ($_GET['id_propietario'] ?? 0)),
        'id_especie' => max(0, (int) ($_GET['id_especie'] ?? 0)),
        'estado' => $estado
    ];
}

function condicionesTurnosVeterinario(
    int $idVeterinario,
    string $desde,
    string $hasta,
    array $filtros,
    ?string $estadoForzado = null
): array {
    $condiciones = [
        't.id_veterinario = ?',
        't.fecha_hora >= ?',
        't.fecha_hora < DATE_ADD(?, INTERVAL 1 DAY)'
    ];
    $tipos = 'iss';
    $parametros = [$idVeterinario, $desde, $hasta];

    if (($filtros['id_mascota'] ?? 0) > 0) {
        $condiciones[] = 't.id_mascota = ?';
        $tipos .= 'i';
        $parametros[] = (int) $filtros['id_mascota'];
    }

    if (($filtros['id_propietario'] ?? 0) > 0) {
        $condiciones[] = 't.id_propietario = ?';
        $tipos .= 'i';
        $parametros[] = (int) $filtros['id_propietario'];
    }

    if (($filtros['id_especie'] ?? 0) > 0) {
        $condiciones[] = 'm.id_especie = ?';
        $tipos .= 'i';
        $parametros[] = (int) $filtros['id_especie'];
    }

    $estadoSeleccionado = (string) ($filtros['estado'] ?? '');

    if ($estadoForzado !== null) {
        if ($estadoSeleccionado !== '' && $estadoSeleccionado !== $estadoForzado) {
            $condiciones[] = '1 = 0';
        }

        $condiciones[] = 't.estado = ?';
        $tipos .= 's';
        $parametros[] = $estadoForzado;
    } elseif ($estadoSeleccionado !== '') {
        $condiciones[] = 't.estado = ?';
        $tipos .= 's';
        $parametros[] = $estadoSeleccionado;
    }

    return [
        'sql' => implode(' AND ', $condiciones),
        'tipos' => $tipos,
        'parametros' => $parametros
    ];
}

function condicionesVacunasVeterinario(
    int $idVeterinario,
    string $desde,
    string $hasta,
    array $filtros
): array {
    $condiciones = [
        'va.id_veterinario = ?',
        'va.fecha_aplicacion BETWEEN ? AND ?'
    ];
    $tipos = 'iss';
    $parametros = [$idVeterinario, $desde, $hasta];

    if (($filtros['id_mascota'] ?? 0) > 0) {
        $condiciones[] = 'va.id_mascota = ?';
        $tipos .= 'i';
        $parametros[] = (int) $filtros['id_mascota'];
    }

    if (($filtros['id_propietario'] ?? 0) > 0) {
        $condiciones[] = 'm.id_propietario = ?';
        $tipos .= 'i';
        $parametros[] = (int) $filtros['id_propietario'];
    }

    if (($filtros['id_especie'] ?? 0) > 0) {
        $condiciones[] = 'm.id_especie = ?';
        $tipos .= 'i';
        $parametros[] = (int) $filtros['id_especie'];
    }

    return [
        'sql' => implode(' AND ', $condiciones),
        'tipos' => $tipos,
        'parametros' => $parametros
    ];
}

function metricasVeterinario(
    mysqli $conn,
    int $idVeterinario,
    array $rango,
    array $filtros
): array {
    $periodos = [
        'actual' => [$rango['desde'], $rango['hasta']],
        'anterior' => [$rango['anterior_desde'], $rango['anterior_hasta']]
    ];

    $resultado = [];

    foreach ($periodos as $clave => [$desde, $hasta]) {
        $realizados = condicionesTurnosVeterinario(
            $idVeterinario,
            $desde,
            $hasta,
            $filtros,
            'realizado'
        );

        $todos = condicionesTurnosVeterinario(
            $idVeterinario,
            $desde,
            $hasta,
            $filtros
        );

        $resultado[$clave]['pacientes'] = (int) valorEstadistica(
            $conn,
            "SELECT COUNT(DISTINCT t.id_mascota) AS total
             FROM turnos t
             INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
             WHERE {$realizados['sql']}",
            $realizados['tipos'],
            $realizados['parametros']
        );

        $resultado[$clave]['consultas'] = (int) valorEstadistica(
            $conn,
            "SELECT COUNT(*) AS total
             FROM turnos t
             INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
             WHERE {$realizados['sql']}",
            $realizados['tipos'],
            $realizados['parametros']
        );

        $resultado[$clave]['propietarios'] = (int) valorEstadistica(
            $conn,
            "SELECT COUNT(DISTINCT t.id_propietario) AS total
             FROM turnos t
             INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
             WHERE {$realizados['sql']}",
            $realizados['tipos'],
            $realizados['parametros']
        );

        $resultado[$clave]['turnos'] = (int) valorEstadistica(
            $conn,
            "SELECT COUNT(*) AS total
             FROM turnos t
             INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
             WHERE {$todos['sql']}",
            $todos['tipos'],
            $todos['parametros']
        );

        $condicionesHistorias = [
            'hc.id_veterinario = ?',
            'hc.fecha_consulta BETWEEN ? AND ?'
        ];
        $tiposHistoria = 'iss';
        $paramsHistoria = [$idVeterinario, $desde, $hasta];

        if (($filtros['id_mascota'] ?? 0) > 0) {
            $condicionesHistorias[] = 'hc.id_mascota = ?';
            $tiposHistoria .= 'i';
            $paramsHistoria[] = (int) $filtros['id_mascota'];
        }

        if (($filtros['id_propietario'] ?? 0) > 0) {
            $condicionesHistorias[] = 't.id_propietario = ?';
            $tiposHistoria .= 'i';
            $paramsHistoria[] = (int) $filtros['id_propietario'];
        }

        if (($filtros['id_especie'] ?? 0) > 0) {
            $condicionesHistorias[] = 'm.id_especie = ?';
            $tiposHistoria .= 'i';
            $paramsHistoria[] = (int) $filtros['id_especie'];
        }

        if (($filtros['estado'] ?? '') !== '') {
            $condicionesHistorias[] = 't.estado = ?';
            $tiposHistoria .= 's';
            $paramsHistoria[] = (string) $filtros['estado'];
        }

        $resultado[$clave]['historias'] = (int) valorEstadistica(
            $conn,
            "SELECT COUNT(*) AS total
             FROM historia_clinica hc
             INNER JOIN turnos t ON t.id_turno = hc.id_turno
             INNER JOIN mascotas m ON m.id_mascota = hc.id_mascota
             WHERE " . implode(' AND ', $condicionesHistorias),
            $tiposHistoria,
            $paramsHistoria
        );

        $vacunas = condicionesVacunasVeterinario(
            $idVeterinario,
            $desde,
            $hasta,
            $filtros
        );

        $resultado[$clave]['vacunas'] = (int) valorEstadistica(
            $conn,
            "SELECT COUNT(*) AS total
             FROM vacunaciones va
             INNER JOIN mascotas m ON m.id_mascota = va.id_mascota
             WHERE {$vacunas['sql']}",
            $vacunas['tipos'],
            $vacunas['parametros']
        );

        $condicionesNuevos = [
            't.id_veterinario = ?',
            "t.estado = 'realizado'"
        ];
        $tiposNuevos = 'i';
        $paramsNuevos = [$idVeterinario];

        if (($filtros['id_mascota'] ?? 0) > 0) {
            $condicionesNuevos[] = 't.id_mascota = ?';
            $tiposNuevos .= 'i';
            $paramsNuevos[] = (int) $filtros['id_mascota'];
        }

        if (($filtros['id_propietario'] ?? 0) > 0) {
            $condicionesNuevos[] = 't.id_propietario = ?';
            $tiposNuevos .= 'i';
            $paramsNuevos[] = (int) $filtros['id_propietario'];
        }

        if (($filtros['id_especie'] ?? 0) > 0) {
            $condicionesNuevos[] = 'm.id_especie = ?';
            $tiposNuevos .= 'i';
            $paramsNuevos[] = (int) $filtros['id_especie'];
        }

        if (
            ($filtros['estado'] ?? '') !== '' &&
            ($filtros['estado'] ?? '') !== 'realizado'
        ) {
            $condicionesNuevos[] = '1 = 0';
        }

        $tiposNuevos .= 'ss';
        $paramsNuevos[] = $desde;
        $paramsNuevos[] = $hasta;

        $resultado[$clave]['nuevos_pacientes'] = (int) valorEstadistica(
            $conn,
            "SELECT COUNT(*) AS total
             FROM (
                 SELECT t.id_mascota, MIN(t.fecha_hora) AS primera_consulta
                 FROM turnos t
                 INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
                 WHERE " . implode(' AND ', $condicionesNuevos) . "
                 GROUP BY t.id_mascota
                 HAVING DATE(primera_consulta) BETWEEN ? AND ?
             ) pacientes_nuevos",
            $tiposNuevos,
            $paramsNuevos
        );
    }

    $comparaciones = [];

    foreach (
        [
            'pacientes',
            'consultas',
            'nuevos_pacientes',
            'propietarios',
            'vacunas',
            'historias'
        ] as $metrica
    ) {
        $comparaciones[$metrica] = compararEstadistica(
            (float) $resultado['actual'][$metrica],
            (float) $resultado['anterior'][$metrica]
        );
    }

    return [
        'actual' => $resultado['actual'],
        'anterior' => $resultado['anterior'],
        'comparaciones' => $comparaciones
    ];
}

function distribucionTurnosVeterinario(
    mysqli $conn,
    int $idVeterinario,
    array $rango,
    array $filtros
): array {
    $condiciones = condicionesTurnosVeterinario(
        $idVeterinario,
        $rango['desde'],
        $rango['hasta'],
        $filtros
    );

    $filas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT t.estado, COUNT(*) AS total
         FROM turnos t
         INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
         WHERE {$condiciones['sql']}
         GROUP BY t.estado",
        $condiciones['tipos'],
        $condiciones['parametros']
    );

    $resultado = [
        'pendiente' => 0,
        'confirmado' => 0,
        'cancelado' => 0,
        'realizado' => 0
    ];

    foreach ($filas as $fila) {
        if (array_key_exists($fila['estado'], $resultado)) {
            $resultado[$fila['estado']] = (int) $fila['total'];
        }
    }

    return $resultado;
}

function seriePacientesVeterinario(
    mysqli $conn,
    int $idVeterinario,
    int $meses,
    array $filtros
): array {
    $etiquetas = etiquetasMeses($meses);
    $claves = array_keys($etiquetas);
    $desde = $claves[0] . '-01';
    $ultimoMes = new DateTimeImmutable(end($claves) . '-01');
    $hasta = $ultimoMes->modify('last day of this month')->format('Y-m-d');

    $condiciones = condicionesTurnosVeterinario(
        $idVeterinario,
        $desde,
        $hasta,
        $filtros,
        'realizado'
    );

    $filas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT DATE_FORMAT(t.fecha_hora, '%Y-%m') AS mes,
                COUNT(DISTINCT t.id_mascota) AS total
         FROM turnos t
         INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
         WHERE {$condiciones['sql']}
         GROUP BY DATE_FORMAT(t.fecha_hora, '%Y-%m')
         ORDER BY mes",
        $condiciones['tipos'],
        $condiciones['parametros']
    );

    $valores = array_fill_keys(array_keys($etiquetas), 0);

    foreach ($filas as $fila) {
        if (array_key_exists($fila['mes'], $valores)) {
            $valores[$fila['mes']] = (int) $fila['total'];
        }
    }

    return [
        'labels' => array_values($etiquetas),
        'values' => array_values($valores)
    ];
}

function serieVacunasVeterinario(
    mysqli $conn,
    int $idVeterinario,
    int $meses,
    array $filtros
): array {
    $etiquetas = etiquetasMeses($meses);
    $claves = array_keys($etiquetas);
    $desde = $claves[0] . '-01';
    $ultimoMes = new DateTimeImmutable(end($claves) . '-01');
    $hasta = $ultimoMes->modify('last day of this month')->format('Y-m-d');

    $condiciones = condicionesVacunasVeterinario(
        $idVeterinario,
        $desde,
        $hasta,
        $filtros
    );

    $filas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT DATE_FORMAT(va.fecha_aplicacion, '%Y-%m') AS mes,
                COUNT(*) AS total
         FROM vacunaciones va
         INNER JOIN mascotas m ON m.id_mascota = va.id_mascota
         WHERE {$condiciones['sql']}
         GROUP BY DATE_FORMAT(va.fecha_aplicacion, '%Y-%m')
         ORDER BY mes",
        $condiciones['tipos'],
        $condiciones['parametros']
    );

    $valores = array_fill_keys(array_keys($etiquetas), 0);

    foreach ($filas as $fila) {
        if (array_key_exists($fila['mes'], $valores)) {
            $valores[$fila['mes']] = (int) $fila['total'];
        }
    }

    return [
        'labels' => array_values($etiquetas),
        'values' => array_values($valores)
    ];
}

function especiesAtendidasVeterinario(
    mysqli $conn,
    int $idVeterinario,
    array $rango,
    array $filtros
): array {
    $condiciones = condicionesTurnosVeterinario(
        $idVeterinario,
        $rango['desde'],
        $rango['hasta'],
        $filtros,
        'realizado'
    );

    return ejecutarConsultaEstadistica(
        $conn,
        "SELECT COALESCE(e.nombre_especie, 'Sin especie') AS etiqueta,
                COUNT(DISTINCT t.id_mascota) AS total
         FROM turnos t
         INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
         LEFT JOIN especies e ON e.id_especie = m.id_especie
         WHERE {$condiciones['sql']}
         GROUP BY e.id_especie, e.nombre_especie
         ORDER BY total DESC, etiqueta ASC",
        $condiciones['tipos'],
        $condiciones['parametros']
    );
}

function demandaDiasVeterinario(
    mysqli $conn,
    int $idVeterinario,
    array $rango,
    array $filtros
): array {
    $condiciones = condicionesTurnosVeterinario(
        $idVeterinario,
        $rango['desde'],
        $rango['hasta'],
        $filtros,
        'realizado'
    );

    $filas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT WEEKDAY(t.fecha_hora) AS dia, COUNT(*) AS total
         FROM turnos t
         INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
         WHERE {$condiciones['sql']}
         GROUP BY WEEKDAY(t.fecha_hora)",
        $condiciones['tipos'],
        $condiciones['parametros']
    );

    $nombres = [
        0 => 'Lun',
        1 => 'Mar',
        2 => 'Mié',
        3 => 'Jue',
        4 => 'Vie',
        5 => 'Sáb',
        6 => 'Dom'
    ];
    $valores = array_fill(0, 7, 0);

    foreach ($filas as $fila) {
        $indice = (int) $fila['dia'];

        if ($indice >= 0 && $indice <= 6) {
            $valores[$indice] = (int) $fila['total'];
        }
    }

    return [
        'labels' => array_values($nombres),
        'values' => $valores
    ];
}

function franjaMayorDemandaVeterinario(
    mysqli $conn,
    int $idVeterinario,
    array $rango,
    array $filtros
): array {
    $condiciones = condicionesTurnosVeterinario(
        $idVeterinario,
        $rango['desde'],
        $rango['hasta'],
        $filtros,
        'realizado'
    );

    $filas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT FLOOR(HOUR(t.fecha_hora) / 2) * 2 AS hora_inicio,
                COUNT(*) AS total
         FROM turnos t
         INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
         WHERE {$condiciones['sql']}
         GROUP BY FLOOR(HOUR(t.fecha_hora) / 2)
         ORDER BY total DESC, hora_inicio ASC
         LIMIT 1",
        $condiciones['tipos'],
        $condiciones['parametros']
    );

    if (!$filas) {
        return ['etiqueta' => 'Sin datos', 'total' => 0];
    }

    $inicio = (int) $filas[0]['hora_inicio'];
    $fin = min(24, $inicio + 2);

    return [
        'etiqueta' => sprintf('%02d:00–%02d:00', $inicio, $fin),
        'total' => (int) $filas[0]['total']
    ];
}

function opcionesFiltrosVeterinario(
    mysqli $conn,
    int $idVeterinario
): array {
    $mascotas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT DISTINCT m.id_mascota, m.nombre,
                p.nombre AS propietario_nombre,
                p.apellido AS propietario_apellido
         FROM turnos t
         INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
         INNER JOIN propietarios p ON p.id_propietario = t.id_propietario
         WHERE t.id_veterinario = ?
         ORDER BY m.nombre, p.apellido, p.nombre",
        'i',
        [$idVeterinario]
    );

    $propietarios = ejecutarConsultaEstadistica(
        $conn,
        "SELECT DISTINCT p.id_propietario, p.nombre, p.apellido
         FROM turnos t
         INNER JOIN propietarios p ON p.id_propietario = t.id_propietario
         WHERE t.id_veterinario = ?
         ORDER BY p.apellido, p.nombre",
        'i',
        [$idVeterinario]
    );

    $especies = ejecutarConsultaEstadistica(
        $conn,
        "SELECT DISTINCT e.id_especie, e.nombre_especie
         FROM turnos t
         INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
         INNER JOIN especies e ON e.id_especie = m.id_especie
         WHERE t.id_veterinario = ?
         ORDER BY e.nombre_especie",
        'i',
        [$idVeterinario]
    );

    return compact('mascotas', 'propietarios', 'especies');
}

function reporteTurnosVeterinario(
    mysqli $conn,
    int $idVeterinario,
    array $rango,
    array $filtros,
    ?int $limite = 100
): array {
    $condiciones = condicionesTurnosVeterinario(
        $idVeterinario,
        $rango['desde'],
        $rango['hasta'],
        $filtros
    );

    $sql = "SELECT t.id_turno, t.fecha_hora, t.motivo, t.estado,
                   m.nombre AS mascota,
                   COALESCE(e.nombre_especie, 'Sin especie') AS especie,
                   p.nombre AS propietario_nombre,
                   p.apellido AS propietario_apellido
            FROM turnos t
            INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
            INNER JOIN propietarios p ON p.id_propietario = t.id_propietario
            LEFT JOIN especies e ON e.id_especie = m.id_especie
            WHERE {$condiciones['sql']}
            ORDER BY t.fecha_hora DESC";

    if ($limite !== null) {
        $sql .= ' LIMIT ' . max(1, $limite);
    }

    return ejecutarConsultaEstadistica(
        $conn,
        $sql,
        $condiciones['tipos'],
        $condiciones['parametros']
    );
}

function reportePacientesVeterinario(
    mysqli $conn,
    int $idVeterinario,
    array $rango,
    array $filtros,
    ?int $limite = 100
): array {
    $condiciones = condicionesTurnosVeterinario(
        $idVeterinario,
        $rango['desde'],
        $rango['hasta'],
        $filtros,
        'realizado'
    );

    $sql = "SELECT t.id_turno, t.fecha_hora,
                   m.id_mascota, m.nombre AS mascota,
                   COALESCE(e.nombre_especie, 'Sin especie') AS especie,
                   p.nombre AS propietario_nombre,
                   p.apellido AS propietario_apellido,
                   t.motivo,
                   hc.diagnostico
            FROM turnos t
            INNER JOIN mascotas m ON m.id_mascota = t.id_mascota
            INNER JOIN propietarios p ON p.id_propietario = t.id_propietario
            LEFT JOIN especies e ON e.id_especie = m.id_especie
            LEFT JOIN historia_clinica hc ON hc.id_turno = t.id_turno
            WHERE {$condiciones['sql']}
            ORDER BY t.fecha_hora DESC";

    if ($limite !== null) {
        $sql .= ' LIMIT ' . max(1, $limite);
    }

    return ejecutarConsultaEstadistica(
        $conn,
        $sql,
        $condiciones['tipos'],
        $condiciones['parametros']
    );
}

function reporteVacunasVeterinario(
    mysqli $conn,
    int $idVeterinario,
    array $rango,
    array $filtros,
    ?int $limite = 100
): array {
    $condiciones = condicionesVacunasVeterinario(
        $idVeterinario,
        $rango['desde'],
        $rango['hasta'],
        $filtros
    );

    $sql = "SELECT va.id_vacunacion, va.nombre_vacuna,
                   va.fecha_aplicacion, va.fecha_proxima,
                   va.observaciones,
                   m.nombre AS mascota,
                   COALESCE(e.nombre_especie, 'Sin especie') AS especie,
                   p.nombre AS propietario_nombre,
                   p.apellido AS propietario_apellido
            FROM vacunaciones va
            INNER JOIN mascotas m ON m.id_mascota = va.id_mascota
            INNER JOIN propietarios p ON p.id_propietario = m.id_propietario
            LEFT JOIN especies e ON e.id_especie = m.id_especie
            WHERE {$condiciones['sql']}
            ORDER BY va.fecha_aplicacion DESC, va.id_vacunacion DESC";

    if ($limite !== null) {
        $sql .= ' LIMIT ' . max(1, $limite);
    }

    return ejecutarConsultaEstadistica(
        $conn,
        $sql,
        $condiciones['tipos'],
        $condiciones['parametros']
    );
}

function filtrosAdminDesdeGet(): array
{
    $estadoSuscripcion = trim((string) ($_GET['estado_suscripcion'] ?? ''));
    $estadosSuscripcion = [
        '',
        'pendiente_pago',
        'activa',
        'atrasada',
        'suspendida',
        'cancelada'
    ];

    if (!in_array($estadoSuscripcion, $estadosSuscripcion, true)) {
        $estadoSuscripcion = '';
    }

    $estadoPago = trim((string) ($_GET['estado_pago'] ?? ''));
    $estadosPago = ['', 'aprobado', 'pendiente', 'rechazado'];

    if (!in_array($estadoPago, $estadosPago, true)) {
        $estadoPago = '';
    }

    return [
        'id_veterinario' => max(0, (int) ($_GET['id_veterinario'] ?? 0)),
        'estado_suscripcion' => $estadoSuscripcion,
        'estado_pago' => $estadoPago
    ];
}

function condicionVeterinarioAdmin(array $filtros, string $alias = 'v'): array
{
    $condiciones = [];
    $tipos = '';
    $parametros = [];

    if (($filtros['id_veterinario'] ?? 0) > 0) {
        $condiciones[] = $alias . '.id_veterinario = ?';
        $tipos .= 'i';
        $parametros[] = (int) $filtros['id_veterinario'];
    }

    return compact('condiciones', 'tipos', 'parametros');
}

function metricasAdmin(
    mysqli $conn,
    array $rango,
    array $filtros
): array {
    $filtroVet = condicionVeterinarioAdmin($filtros, 'v');
    $condicionesClientes = $filtroVet['condiciones'];
    $tiposClientes = $filtroVet['tipos'];
    $paramsClientes = $filtroVet['parametros'];

    if (($filtros['estado_suscripcion'] ?? '') !== '') {
        $condicionesClientes[] = 's.estado = ?';
        $tiposClientes .= 's';
        $paramsClientes[] = (string) $filtros['estado_suscripcion'];
    }

    $whereClientes = $condicionesClientes
        ? ' WHERE ' . implode(' AND ', $condicionesClientes)
        : '';

    $totalClientes = (int) valorEstadistica(
        $conn,
        "SELECT COUNT(DISTINCT v.id_veterinario) AS total
         FROM veterinarios v
         INNER JOIN suscripciones s ON s.id_veterinario = v.id_veterinario
         {$whereClientes}",
        $tiposClientes,
        $paramsClientes
    );

    $estadoActual = function (string $estado) use (
        $conn,
        $filtroVet,
        $filtros
    ): int {
        if (
            ($filtros['estado_suscripcion'] ?? '') !== '' &&
            $filtros['estado_suscripcion'] !== $estado
        ) {
            return 0;
        }

        $condiciones = $filtroVet['condiciones'];
        $tipos = $filtroVet['tipos'];
        $parametros = $filtroVet['parametros'];
        $condiciones[] = 's.estado = ?';
        $tipos .= 's';
        $parametros[] = $estado;

        return (int) valorEstadistica(
            $conn,
            "SELECT COUNT(DISTINCT v.id_veterinario) AS total
             FROM veterinarios v
             INNER JOIN suscripciones s ON s.id_veterinario = v.id_veterinario
             WHERE " . implode(' AND ', $condiciones),
            $tipos,
            $parametros
        );
    };

    $activos = $estadoActual('activa');
    $atrasados = $estadoActual('atrasada');
    $suspendidos = $estadoActual('suspendida');
    $pendientes = $estadoActual('pendiente_pago');
    $canceladosActuales = $estadoActual('cancelada');

    $nuevos = [];

    foreach (
        [
            'actual' => [$rango['desde'], $rango['hasta']],
            'anterior' => [$rango['anterior_desde'], $rango['anterior_hasta']]
        ] as $clave => [$desde, $hasta]
    ) {
        $condiciones = $condicionesClientes;
        $tipos = $tiposClientes;
        $parametros = $paramsClientes;
        $condiciones[] = 'DATE(v.fecha_registro) BETWEEN ? AND ?';
        $tipos .= 'ss';
        $parametros[] = $desde;
        $parametros[] = $hasta;

        $nuevos[$clave] = (int) valorEstadistica(
            $conn,
            "SELECT COUNT(DISTINCT v.id_veterinario) AS total
             FROM veterinarios v
             INNER JOIN suscripciones s ON s.id_veterinario = v.id_veterinario
             WHERE " . implode(' AND ', $condiciones),
            $tipos,
            $parametros
        );
    }

    $pagos = [];

    foreach (
        [
            'actual' => [$rango['desde'], $rango['hasta']],
            'anterior' => [$rango['anterior_desde'], $rango['anterior_hasta']]
        ] as $clave => [$desde, $hasta]
    ) {
        $condiciones = [
            'DATE(p.fecha_operacion) BETWEEN ? AND ?'
        ];
        $tipos = 'ss';
        $parametros = [$desde, $hasta];

        if (($filtros['id_veterinario'] ?? 0) > 0) {
            $condiciones[] = 'p.id_veterinario = ?';
            $tipos .= 'i';
            $parametros[] = (int) $filtros['id_veterinario'];
        }

        if (($filtros['estado_suscripcion'] ?? '') !== '') {
            $condiciones[] = 's.estado = ?';
            $tipos .= 's';
            $parametros[] = (string) $filtros['estado_suscripcion'];
        }

        if (($filtros['estado_pago'] ?? '') !== '') {
            $condiciones[] = 'p.estado = ?';
            $tipos .= 's';
            $parametros[] = (string) $filtros['estado_pago'];
        }

        $wherePago = implode(' AND ', $condiciones);

        $pagos[$clave]['total'] = (int) valorEstadistica(
            $conn,
            "SELECT COUNT(*) AS total
             FROM pagos p
             INNER JOIN suscripciones s ON s.id_suscripcion = p.id_suscripcion
             WHERE {$wherePago}",
            $tipos,
            $parametros
        );

        $condicionesAprobados = $condiciones;
        $tiposAprobados = $tipos;
        $paramsAprobados = $parametros;

        if (
            ($filtros['estado_pago'] ?? '') !== '' &&
            $filtros['estado_pago'] !== 'aprobado'
        ) {
            $condicionesAprobados[] = '1 = 0';
        } else {
            $condicionesAprobados[] = "p.estado = 'aprobado'";
        }

        $whereAprobados = implode(' AND ', $condicionesAprobados);

        $pagos[$clave]['aprobados'] = (int) valorEstadistica(
            $conn,
            "SELECT COUNT(*) AS total
             FROM pagos p
             INNER JOIN suscripciones s ON s.id_suscripcion = p.id_suscripcion
             WHERE {$whereAprobados}",
            $tiposAprobados,
            $paramsAprobados
        );

        $pagos[$clave]['ingresos'] = valorEstadistica(
            $conn,
            "SELECT COALESCE(SUM(p.importe), 0) AS total
             FROM pagos p
             INNER JOIN suscripciones s ON s.id_suscripcion = p.id_suscripcion
             WHERE {$whereAprobados}",
            $tiposAprobados,
            $paramsAprobados
        );
    }

    $condicionesCancelados = $condicionesClientes;
    $tiposCancelados = $tiposClientes;
    $paramsCancelados = $paramsClientes;
    $condicionesCancelados[] = 'DATE(s.fecha_cancelacion) BETWEEN ? AND ?';
    $tiposCancelados .= 'ss';
    $paramsCancelados[] = $rango['desde'];
    $paramsCancelados[] = $rango['hasta'];

    $bajasPeriodo = (int) valorEstadistica(
        $conn,
        "SELECT COUNT(DISTINCT v.id_veterinario) AS total
         FROM veterinarios v
         INNER JOIN suscripciones s ON s.id_veterinario = v.id_veterinario
         WHERE " . implode(' AND ', $condicionesCancelados),
        $tiposCancelados,
        $paramsCancelados
    );

    return [
        'clientes_total' => $totalClientes,
        'clientes_activos' => $activos,
        'clientes_atrasados' => $atrasados,
        'clientes_suspendidos' => $suspendidos,
        'clientes_pendientes' => $pendientes,
        'clientes_cancelados_actuales' => $canceladosActuales,
        'bajas_periodo' => $bajasPeriodo,
        'nuevos_actual' => $nuevos['actual'],
        'nuevos_anterior' => $nuevos['anterior'],
        'comparacion_nuevos' => compararEstadistica(
            $nuevos['actual'],
            $nuevos['anterior']
        ),
        'pagos_actual' => $pagos['actual']['total'],
        'pagos_aprobados_actual' => $pagos['actual']['aprobados'],
        'ingresos_actual' => $pagos['actual']['ingresos'],
        'ingresos_anterior' => $pagos['anterior']['ingresos'],
        'comparacion_ingresos' => compararEstadistica(
            $pagos['actual']['ingresos'],
            $pagos['anterior']['ingresos']
        )
    ];
}

function serieClientesAdmin(
    mysqli $conn,
    int $meses,
    array $filtros
): array {
    $etiquetas = etiquetasMeses($meses);
    $claves = array_keys($etiquetas);
    $desde = $claves[0] . '-01';
    $ultimoMes = new DateTimeImmutable(end($claves) . '-01');
    $hasta = $ultimoMes->modify('last day of this month')->format('Y-m-d');

    $condiciones = [
        'DATE(v.fecha_registro) BETWEEN ? AND ?'
    ];
    $tipos = 'ss';
    $params = [$desde, $hasta];

    if (($filtros['id_veterinario'] ?? 0) > 0) {
        $condiciones[] = 'v.id_veterinario = ?';
        $tipos .= 'i';
        $params[] = (int) $filtros['id_veterinario'];
    }

    if (($filtros['estado_suscripcion'] ?? '') !== '') {
        $condiciones[] = 's.estado = ?';
        $tipos .= 's';
        $params[] = (string) $filtros['estado_suscripcion'];
    }

    $filas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT DATE_FORMAT(v.fecha_registro, '%Y-%m') AS mes,
                COUNT(DISTINCT v.id_veterinario) AS total
         FROM veterinarios v
         INNER JOIN suscripciones s ON s.id_veterinario = v.id_veterinario
         WHERE " . implode(' AND ', $condiciones) . "
         GROUP BY DATE_FORMAT(v.fecha_registro, '%Y-%m')
         ORDER BY mes",
        $tipos,
        $params
    );

    $valores = array_fill_keys(array_keys($etiquetas), 0);

    foreach ($filas as $fila) {
        if (array_key_exists($fila['mes'], $valores)) {
            $valores[$fila['mes']] = (int) $fila['total'];
        }
    }

    return [
        'labels' => array_values($etiquetas),
        'values' => array_values($valores)
    ];
}

function serieIngresosAdmin(
    mysqli $conn,
    int $meses,
    array $filtros
): array {
    $etiquetas = etiquetasMeses($meses);
    $claves = array_keys($etiquetas);
    $desde = $claves[0] . '-01';
    $ultimoMes = new DateTimeImmutable(end($claves) . '-01');
    $hasta = $ultimoMes->modify('last day of this month')->format('Y-m-d');

    $condiciones = [
        "p.estado = 'aprobado'",
        'DATE(p.fecha_operacion) BETWEEN ? AND ?'
    ];
    $tipos = 'ss';
    $params = [$desde, $hasta];

    if (($filtros['id_veterinario'] ?? 0) > 0) {
        $condiciones[] = 'p.id_veterinario = ?';
        $tipos .= 'i';
        $params[] = (int) $filtros['id_veterinario'];
    }

    if (($filtros['estado_suscripcion'] ?? '') !== '') {
        $condiciones[] = 's.estado = ?';
        $tipos .= 's';
        $params[] = (string) $filtros['estado_suscripcion'];
    }

    if (
        ($filtros['estado_pago'] ?? '') !== '' &&
        $filtros['estado_pago'] !== 'aprobado'
    ) {
        $condiciones[] = '1 = 0';
    }

    $filas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT DATE_FORMAT(p.fecha_operacion, '%Y-%m') AS mes,
                COALESCE(SUM(p.importe), 0) AS total
         FROM pagos p
         INNER JOIN suscripciones s ON s.id_suscripcion = p.id_suscripcion
         WHERE " . implode(' AND ', $condiciones) . "
         GROUP BY DATE_FORMAT(p.fecha_operacion, '%Y-%m')
         ORDER BY mes",
        $tipos,
        $params
    );

    $valores = array_fill_keys(array_keys($etiquetas), 0.0);

    foreach ($filas as $fila) {
        if (array_key_exists($fila['mes'], $valores)) {
            $valores[$fila['mes']] = (float) $fila['total'];
        }
    }

    return [
        'labels' => array_values($etiquetas),
        'values' => array_values($valores)
    ];
}

function estadosSuscripcionAdmin(
    mysqli $conn,
    array $filtros
): array {
    $condiciones = [];
    $tipos = '';
    $params = [];

    if (($filtros['id_veterinario'] ?? 0) > 0) {
        $condiciones[] = 's.id_veterinario = ?';
        $tipos .= 'i';
        $params[] = (int) $filtros['id_veterinario'];
    }

    if (($filtros['estado_suscripcion'] ?? '') !== '') {
        $condiciones[] = 's.estado = ?';
        $tipos .= 's';
        $params[] = (string) $filtros['estado_suscripcion'];
    }

    $where = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';

    $filas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT s.estado, COUNT(*) AS total
         FROM suscripciones s
         {$where}
         GROUP BY s.estado",
        $tipos,
        $params
    );

    $resultado = [
        'activa' => 0,
        'atrasada' => 0,
        'suspendida' => 0,
        'cancelada' => 0,
        'pendiente_pago' => 0
    ];

    foreach ($filas as $fila) {
        if (array_key_exists($fila['estado'], $resultado)) {
            $resultado[$fila['estado']] = (int) $fila['total'];
        }
    }

    return $resultado;
}

function estadosPagosAdmin(
    mysqli $conn,
    array $rango,
    array $filtros
): array {
    $condiciones = [
        'DATE(p.fecha_operacion) BETWEEN ? AND ?'
    ];
    $tipos = 'ss';
    $params = [$rango['desde'], $rango['hasta']];

    if (($filtros['id_veterinario'] ?? 0) > 0) {
        $condiciones[] = 'p.id_veterinario = ?';
        $tipos .= 'i';
        $params[] = (int) $filtros['id_veterinario'];
    }

    if (($filtros['estado_suscripcion'] ?? '') !== '') {
        $condiciones[] = 's.estado = ?';
        $tipos .= 's';
        $params[] = (string) $filtros['estado_suscripcion'];
    }

    if (($filtros['estado_pago'] ?? '') !== '') {
        $condiciones[] = 'p.estado = ?';
        $tipos .= 's';
        $params[] = (string) $filtros['estado_pago'];
    }

    $filas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT p.estado, COUNT(*) AS total
         FROM pagos p
         INNER JOIN suscripciones s ON s.id_suscripcion = p.id_suscripcion
         WHERE " . implode(' AND ', $condiciones) . "
         GROUP BY p.estado",
        $tipos,
        $params
    );

    $resultado = [
        'aprobado' => 0,
        'pendiente' => 0,
        'rechazado' => 0
    ];

    foreach ($filas as $fila) {
        if (array_key_exists($fila['estado'], $resultado)) {
            $resultado[$fila['estado']] = (int) $fila['total'];
        }
    }

    return $resultado;
}

function serieAltasBajasAdmin(
    mysqli $conn,
    int $meses,
    array $filtros
): array {
    $etiquetas = etiquetasMeses($meses);
    $claves = array_keys($etiquetas);
    $desde = $claves[0] . '-01';
    $ultimoMes = new DateTimeImmutable(end($claves) . '-01');
    $hasta = $ultimoMes->modify('last day of this month')->format('Y-m-d');

    $whereVet = '';
    $tiposVet = 'ss';
    $paramsVet = [$desde, $hasta];

    if (($filtros['id_veterinario'] ?? 0) > 0) {
        $whereVet .= ' AND v.id_veterinario = ?';
        $tiposVet .= 'i';
        $paramsVet[] = (int) $filtros['id_veterinario'];
    }

    if (($filtros['estado_suscripcion'] ?? '') !== '') {
        $whereVet .= ' AND s.estado = ?';
        $tiposVet .= 's';
        $paramsVet[] = (string) $filtros['estado_suscripcion'];
    }

    $altas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT DATE_FORMAT(v.fecha_registro, '%Y-%m') AS mes,
                COUNT(*) AS total
         FROM veterinarios v
         INNER JOIN suscripciones s ON s.id_veterinario = v.id_veterinario
         WHERE DATE(v.fecha_registro) BETWEEN ? AND ? {$whereVet}
         GROUP BY DATE_FORMAT(v.fecha_registro, '%Y-%m')",
        $tiposVet,
        $paramsVet
    );

    $tiposBajas = 'ss';
    $paramsBajas = [$desde, $hasta];
    $whereBajas = '';

    if (($filtros['id_veterinario'] ?? 0) > 0) {
        $whereBajas .= ' AND s.id_veterinario = ?';
        $tiposBajas .= 'i';
        $paramsBajas[] = (int) $filtros['id_veterinario'];
    }

    if (($filtros['estado_suscripcion'] ?? '') !== '') {
        $whereBajas .= ' AND s.estado = ?';
        $tiposBajas .= 's';
        $paramsBajas[] = (string) $filtros['estado_suscripcion'];
    }

    $bajas = ejecutarConsultaEstadistica(
        $conn,
        "SELECT DATE_FORMAT(s.fecha_cancelacion, '%Y-%m') AS mes,
                COUNT(*) AS total
         FROM suscripciones s
         WHERE s.fecha_cancelacion IS NOT NULL
           AND DATE(s.fecha_cancelacion) BETWEEN ? AND ? {$whereBajas}
         GROUP BY DATE_FORMAT(s.fecha_cancelacion, '%Y-%m')",
        $tiposBajas,
        $paramsBajas
    );

    $valoresAltas = array_fill_keys(array_keys($etiquetas), 0);
    $valoresBajas = array_fill_keys(array_keys($etiquetas), 0);

    foreach ($altas as $fila) {
        if (array_key_exists($fila['mes'], $valoresAltas)) {
            $valoresAltas[$fila['mes']] = (int) $fila['total'];
        }
    }

    foreach ($bajas as $fila) {
        if (array_key_exists($fila['mes'], $valoresBajas)) {
            $valoresBajas[$fila['mes']] = (int) $fila['total'];
        }
    }

    return [
        'labels' => array_values($etiquetas),
        'altas' => array_values($valoresAltas),
        'bajas' => array_values($valoresBajas)
    ];
}

function opcionesFiltrosAdmin(mysqli $conn): array
{
    return ejecutarConsultaEstadistica(
        $conn,
        "SELECT id_veterinario, nombre, apellido, matricula
         FROM veterinarios
         ORDER BY apellido, nombre"
    );
}

function reportePagosAdmin(
    mysqli $conn,
    array $rango,
    array $filtros,
    ?int $limite = 100
): array {
    $condiciones = [
        'DATE(p.fecha_operacion) BETWEEN ? AND ?'
    ];
    $tipos = 'ss';
    $params = [$rango['desde'], $rango['hasta']];

    if (($filtros['id_veterinario'] ?? 0) > 0) {
        $condiciones[] = 'p.id_veterinario = ?';
        $tipos .= 'i';
        $params[] = (int) $filtros['id_veterinario'];
    }

    if (($filtros['estado_suscripcion'] ?? '') !== '') {
        $condiciones[] = 's.estado = ?';
        $tipos .= 's';
        $params[] = (string) $filtros['estado_suscripcion'];
    }

    if (($filtros['estado_pago'] ?? '') !== '') {
        $condiciones[] = 'p.estado = ?';
        $tipos .= 's';
        $params[] = (string) $filtros['estado_pago'];
    }

    $sql = "SELECT p.id_pago, p.fecha_operacion, p.numero_operacion,
                   p.periodo_desde, p.periodo_hasta, p.importe,
                   p.medio_pago, p.estado,
                   v.nombre, v.apellido, v.matricula
            FROM pagos p
            INNER JOIN veterinarios v ON v.id_veterinario = p.id_veterinario
            INNER JOIN suscripciones s ON s.id_suscripcion = p.id_suscripcion
            WHERE " . implode(' AND ', $condiciones) . "
            ORDER BY p.fecha_operacion DESC";

    if ($limite !== null) {
        $sql .= ' LIMIT ' . max(1, $limite);
    }

    return ejecutarConsultaEstadistica(
        $conn,
        $sql,
        $tipos,
        $params
    );
}

function reporteSuscripcionesAdmin(
    mysqli $conn,
    array $filtros,
    ?int $limite = 100
): array {
    $condiciones = [];
    $tipos = '';
    $params = [];

    if (($filtros['id_veterinario'] ?? 0) > 0) {
        $condiciones[] = 's.id_veterinario = ?';
        $tipos .= 'i';
        $params[] = (int) $filtros['id_veterinario'];
    }

    if (($filtros['estado_suscripcion'] ?? '') !== '') {
        $condiciones[] = 's.estado = ?';
        $tipos .= 's';
        $params[] = (string) $filtros['estado_suscripcion'];
    }

    $where = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';

    $sql = "SELECT s.id_suscripcion, s.estado, s.fecha_inicio,
                   s.fecha_vencimiento, s.fecha_fin_tolerancia,
                   s.fecha_cancelacion, s.suspension_manual,
                   v.id_veterinario, v.nombre, v.apellido,
                   v.matricula, v.email,
                   pl.nombre AS plan_nombre,
                   pl.precio_mensual
            FROM suscripciones s
            INNER JOIN veterinarios v ON v.id_veterinario = s.id_veterinario
            INNER JOIN planes pl ON pl.id_plan = s.id_plan
            {$where}
            ORDER BY FIELD(s.estado, 'suspendida', 'atrasada', 'pendiente_pago', 'activa', 'cancelada'),
                     s.fecha_vencimiento ASC";

    if ($limite !== null) {
        $sql .= ' LIMIT ' . max(1, $limite);
    }

    return ejecutarConsultaEstadistica(
        $conn,
        $sql,
        $tipos,
        $params
    );
}

function reporteClientesAdmin(
    mysqli $conn,
    array $rango,
    array $filtros,
    ?int $limite = 100
): array {
    $condiciones = [
        'DATE(v.fecha_registro) <= ?'
    ];
    $tipos = 's';
    $params = [$rango['hasta']];

    if (($filtros['id_veterinario'] ?? 0) > 0) {
        $condiciones[] = 'v.id_veterinario = ?';
        $tipos .= 'i';
        $params[] = (int) $filtros['id_veterinario'];
    }

    if (($filtros['estado_suscripcion'] ?? '') !== '') {
        $condiciones[] = 's.estado = ?';
        $tipos .= 's';
        $params[] = (string) $filtros['estado_suscripcion'];
    }

    $sql = "SELECT v.id_veterinario, v.nombre, v.apellido,
                   v.matricula, v.especialidad, v.email,
                   v.localidad, v.fecha_registro,
                   s.estado AS estado_suscripcion,
                   s.fecha_vencimiento,
                   pl.nombre AS plan_nombre
            FROM veterinarios v
            INNER JOIN suscripciones s ON s.id_veterinario = v.id_veterinario
            INNER JOIN planes pl ON pl.id_plan = s.id_plan
            WHERE " . implode(' AND ', $condiciones) . "
            ORDER BY v.fecha_registro DESC, v.apellido, v.nombre";

    if ($limite !== null) {
        $sql .= ' LIMIT ' . max(1, $limite);
    }

    return ejecutarConsultaEstadistica(
        $conn,
        $sql,
        $tipos,
        $params
    );
}

function formatoDineroEstadistica(float $importe): string
{
    return '$' . number_format($importe, 0, ',', '.');
}

function queryStringEstadisticas(array $extra = []): string
{
    $permitidos = [
        'periodo',
        'desde',
        'hasta',
        'id_mascota',
        'id_propietario',
        'id_especie',
        'estado',
        'id_veterinario',
        'estado_suscripcion',
        'estado_pago',
        'grafico_meses',
        'reporte'
    ];
    $datos = [];

    foreach ($permitidos as $clave) {
        if (isset($_GET[$clave]) && $_GET[$clave] !== '') {
            $datos[$clave] = (string) $_GET[$clave];
        }
    }

    foreach ($extra as $clave => $valor) {
        if ($valor === null || $valor === '') {
            unset($datos[$clave]);
        } else {
            $datos[$clave] = (string) $valor;
        }
    }

    return http_build_query($datos);
}
