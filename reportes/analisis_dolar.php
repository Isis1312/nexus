<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../conexion.php';

function getHistoricalRates($filePath = '../js/historial_tasas.json', $days = 30) {
    $historical_data = [];
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $historical_data = json_decode($content, true) ?? [];
    }
    
    $daily_data = [];
    $cutoff_date = strtotime("-$days days");
    
    foreach ($historical_data as $entry) {
        $timestamp = strtotime($entry['fecha']);
        if ($timestamp >= $cutoff_date) {
            $date = date('Y-m-d', $timestamp);
            $daily_data[$date] = $entry; 
        }
    }
    
    ksort($daily_data);
    return array_values($daily_data);
}

function procesarTasas($data) {
    if (empty($data)) {
        return [
            'historial' => [], 
            'variacion' => [ 'inicio' => 0, 'final' => 0, 'promedio' => 0, 'total_abs' => 0, 'total_pct' => 0 ], 
            'chart_data' => ['labels' => [], 'values' => []]
        ];
    }

    $historial = [];
    $sum_tasas = 0;
    $tasa_inicio = floatval($data[0]['dolar']); 
    $tasa_final = floatval(end($data)['dolar']); 

    $chart_labels = [];
    $chart_values = [];
    
    for ($i = 0; $i < count($data); $i++) {
        $fecha = $data[$i]['fecha'];
        $tasa = floatval($data[$i]['dolar']);
        $sum_tasas += $tasa;
        
        $variacion_abs = 0;
        $variacion_pct = 0;
        $tendencia = 'Estable';
        $clase_tasa = ''; 

        if ($i > 0) {
            $tasa_anterior = floatval($data[$i-1]['dolar']);
            $variacion_abs = $tasa - $tasa_anterior;
            
            if ($tasa_anterior > 0) {
                $variacion_pct = ($variacion_abs / $tasa_anterior) * 100;
            }
            
            if ($variacion_abs > 0) {
                $tendencia = 'Sube';
                $clase_tasa = 'up'; // Resaltado verde/accent
            } elseif ($variacion_abs < 0) {
                $tendencia = 'Baja';
                $clase_tasa = 'down'; // Resaltado rojo
            }
        }

        $historial[] = [
            'fecha' => $fecha,
            'tasa' => $tasa,
            'variacion_abs' => $variacion_abs,
            'variacion_pct' => $variacion_pct,
            'tendencia' => $tendencia,
            'clase_tasa' => $clase_tasa 
        ];

        $chart_labels[] = date('d/M', strtotime($fecha));
        $chart_values[] = $tasa;
    }
    
    $variacion_total_abs = $tasa_final - $tasa_inicio;
    $variacion_total_pct = ($tasa_inicio > 0) ? ($variacion_total_abs / $tasa_inicio) * 100 : 0;
    
    $variacion = [
        'inicio' => $tasa_inicio,
        'final' => $tasa_final,
        'promedio' => count($data) > 0 ? $sum_tasas / count($data) : 0,
        'total_abs' => $variacion_total_abs,
        'total_pct' => $variacion_total_pct,
    ];

    return [
        'historial' => $historial, 
        'variacion' => $variacion, 
        'chart_data' => ['labels' => $chart_labels, 'values' => $chart_values]
    ];
}

function getCurrentRateFromCache($filePath = '../js/tasas_cache.json') {
    $cache = [];
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $cache = json_decode($content, true) ?? [];
    }
    return [
        'dolar' => floatval($cache['dolar'] ?? 0),
        'dolar_anterior' => floatval($cache['dolar_anterior'] ?? 0),
        'porcentaje_dolar' => floatval($cache['porcentaje_dolar'] ?? 0),
        'fecha' => $cache['fecha_actualizacion'] ?? date('Y-m-d H:i:s')
    ];
}

$historialSemanaRaw = getHistoricalRates('../js/historial_tasas.json', 7);
$historialMesRaw = getHistoricalRates('../js/historial_tasas.json', 30);
$tasasActuales = getCurrentRateFromCache('../js/tasas_cache.json');

$datosMes = procesarTasas($historialMesRaw);


$tasa_actual = $tasasActuales['dolar'];
$variacion_hoy = $tasasActuales['porcentaje_dolar']; 
$tasa_anterior = $tasasActuales['dolar_anterior'];


$tasa_inicio_mes = $datosMes['variacion']['inicio'] ?? 0; 
$variacion_total_mes = $datosMes['variacion']['total_abs'] ?? 0;
$variacion_pct_mes = $datosMes['variacion']['total_pct'] ?? 0;

$tasa_actual_formateada = number_format($tasa_actual, 2, ',', '.');
$tasa_inicio_mes_formateada = number_format($tasa_inicio_mes, 2, ',', '.');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Análisis del Dólar</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../css/reportes/general_reportes.css"> 

</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Historial y variación de la tasa cambiaria (USD)</h1>
            
            <a href="analisis_dolar.php?accion=actualizar_tasas" class="volver-button">
                Actualizar reporte
            </a>
             <a href="../reportes.php" class="volver-button">
             Volver
            </a>
        </div>

        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Tasa actual y variación diaria</h2>
            </div>
            <div class="estadisticas-grid">
                <div class="estadistica-card current-rate-card">
                    <div class="estadistica-label">Tasa dólar actual</div>
                    <div class="estadistica-value">Bs. <?= $tasa_actual_formateada ?></div>
                </div>
                <div class="estadistica-card <?= $variacion_hoy >= 0 ? 'up' : 'down' ?>">
                    <div class="estadistica-label">Variación Diaria (%)</div>
                    <div class="estadistica-value">
                        <?= $variacion_hoy >= 0 ? '↗' : '↘' ?> 
                        <?= number_format(abs($variacion_hoy), 2, ',', '.') ?>%
                    </div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-label">Tasa anterior </div>
                    <div class="estadistica-value">Bs. <?= number_format($tasa_anterior, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Resumen de variación mensual </h2>
            </div>
            <div class="estadisticas-grid">
                <div class="estadistica-card">
                    <div class="estadistica-label">Tasa de inicio del periodo</div>
                    <div class="estadistica-value">Bs. <?= $tasa_inicio_mes_formateada ?></div>
                </div>
                <div class="estadistica-card <?= $variacion_total_mes >= 0 ? 'up' : 'down' ?>">
                    <div class="estadistica-label">Variación Total Absoluta</div>
                    <div class="estadistica-value">
                        <?= $variacion_total_mes >= 0 ? '↗' : '↘' ?> 
                        <?= ($variacion_total_mes >= 0 ? '+' : '') . number_format($variacion_total_mes, 4, ',', '.') ?> Bs
                    </div>
                </div>
                <div class="estadistica-card <?= $variacion_pct_mes >= 0 ? 'up' : 'down' ?>">
                    <div class="estadistica-label">Variación total porcentual</div>
                    <div class="estadistica-value">
                        <?= $variacion_pct_mes >= 0 ? '↗' : '↘' ?> 
                        <?= ($variacion_pct_mes >= 0 ? '+' : '') . number_format($variacion_pct_mes, 2, ',', '.') ?> %
                    </div>
                </div>
            </div>
        </div>

        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Gráfica de tendencia (Últimos 30 Días)</h2>
            </div>
            <div class="chart-container" style="width: 100%; height: 400px;">
                <canvas id="dolarChartMes"></canvas>
            </div>
            
        </div>

        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Historial detallado de la semana </h2>
            </div>
            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tasa (Bs/$)</th>
                                <th>Variación Abs. (Bs)</th>
                                <th>Variación %</th>
                                <th>Tendencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $datosSemana = procesarTasas($historialSemanaRaw);
                            if(empty($datosSemana['historial'])): ?>
                                <tr><td colspan="5">No hay datos históricos disponibles.</td></tr>
                            <?php else: 
                                $historial_reverso = array_reverse($datosSemana['historial']);
                                foreach($historial_reverso as $h): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($h['fecha'])) ?></td> <td class="<?= $h['clase_tasa'] ?>"> Bs. <?= number_format($h['tasa'], 4, ',', '.') ?>
                                    </td>
                                    <td class="<?= $h['variacion_abs'] > 0 ? 'up' : ($h['variacion_abs'] < 0 ? 'down' : '') ?>">
                                        <?= ($h['variacion_abs'] >= 0 ? '+' : '') . number_format($h['variacion_abs'], 4, ',', '.') ?>
                                    </td>
                                    <td class="<?= $h['variacion_pct'] > 0 ? 'up' : ($h['variacion_pct'] < 0 ? 'down' : '') ?>">
                                        <?= ($h['variacion_pct'] >= 0 ? '+' : '') . number_format($h['variacion_pct'], 2, ',', '.') ?> %
                                    </td>
                                    <td>
                                        <?php 
                                            if ($h['tendencia'] == 'Sube') echo '📈';
                                            elseif ($h['tendencia'] == 'Baja') echo '📉';
                                            else echo '➖';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const datosMes = <?= json_encode($datosMes['chart_data']) ?>;

        const allValues = datosMes.values;
        let suggestedMin = 0;
        let suggestedMax = 10; 

        if (allValues.length > 0) {
            const minRate = Math.min(...allValues);
            const maxRate = Math.max(...allValues);
            
            // Ajuste automático del eje Y
            if (maxRate > 0) {
                suggestedMin = minRate * 0.995;
                suggestedMax = maxRate * 1.005;
            } else if (minRate === maxRate && minRate !== 0) {
                suggestedMin = minRate * 0.999; 
                suggestedMax = maxRate * 1.001;
            }
        }
        
        const ctxMes = document.getElementById('dolarChartMes').getContext('2d');
        
        const chartMes = new Chart(ctxMes, {
            type: 'line',
            data: {
                labels: datosMes.labels,
                datasets: [{
                    label: 'Tasa de Cambio (Bs/$)',
                    data: datosMes.values,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderWidth: 2,
                    pointRadius: 3,
                    fill: false,
                    tension: 0.2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: { display: true, text: 'Fecha' }
                    },
                    y: {
                        title: { display: true, text: 'Tasa (Bs)' },
                        beginAtZero: false,
                        suggestedMin: suggestedMin, 
                        suggestedMax: suggestedMax 
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Tasa: Bs. ' + context.parsed.y.toFixed(4);
                            }
                        }
                    }
                }
            }
        });
    });
</script>
</body>
</html>