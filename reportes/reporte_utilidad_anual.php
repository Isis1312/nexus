<?php
// reportes/estado_resultados_anual.php

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../conexion.php'; 

function obtenerTasaDolar() {
    $cache_file = '../js/tasas_cache.json'; 
    
    if (file_exists($cache_file)) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data && isset($cached_data['dolar'])) {
            return floatval($cached_data['dolar']);
        }
    }
    
    return 36.50; 
}
$tasa_dolar = obtenerTasaDolar();

$current_year = date('Y');

$year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

function getTotalVentasAnual($pdo, $year) {
    $query = "SELECT 
               IFNULL(SUM(total_usd), 0) AS ingresos_anual 
             FROM ventas 
             WHERE YEAR(fecha) = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$year]);
    return floatval($stmt->fetchColumn() ?: 0);
}

$ingresos_anual = getTotalVentasAnual($pdo, $year);

$costos_anual   = $ingresos_anual * 0.60;
$gastos_anual   = $ingresos_anual * 0.10;
$util_bruta_anual = $ingresos_anual - $costos_anual;
$util_neta_anual   = $util_bruta_anual - $gastos_anual;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Utilidad anual</title>
     <link rel="stylesheet" href="../css/reportes/general_reportes.css">
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Utilidad anual</h1>
            <a href="../reportes.php" class="volver-button">
                        Volver
                    </a>
        </div>

        <div class="filtros-container">
            <div class="filtros-card">
                <h3>Filtrar Periodo</h3>
                <form method="GET" class="filtros-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Año:</label>
                            <select name="year" class="form-select">
                                <?php for($y = 2025; $y <= $current_year; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn-generar">Generar Reporte</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Estado de Resultados Simplificado (Anual)</h2>
                <div class="periodo-info">
                    <span>Año <?= $year ?></span>
                </div>
            </div>

            <div class="tabla-container">
                <table class="tabla-reporte">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Monto ($)</th>
                            <th>Monto (Bs) <br>(Tasa: Bs. <?= number_format($tasa_dolar, 2, ',', '.') ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Ingresos</td>
                            <td>$. <?= number_format($ingresos_anual, 2, ',', '.') ?></td>
                            <td>Bs. <?= number_format($ingresos_anual * $tasa_dolar, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td>Costos (60% de Ingresos)</td>
                            <td>$. <?= number_format($costos_anual, 2, ',', '.') ?></td>
                            <td>Bs. <?= number_format($costos_anual * $tasa_dolar, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Utilidad Bruta</strong></td>
                            <td><strong>$. <?= number_format($util_bruta_anual, 2, ',', '.') ?></strong></td>
                            <td><strong>Bs. <?= number_format($util_bruta_anual * $tasa_dolar, 2, ',', '.') ?></strong></td>
                        </tr>
                        <tr>
                            <td>Gastos (10% de Ingresos)</td>
                            <td>$. <?= number_format($gastos_anual, 2, ',', '.') ?></td>
                            <td>Bs. <?= number_format($gastos_anual * $tasa_dolar, 2, ',', '.') ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>UTILIDAD NETA</strong></td>
                            <td><strong>$. <?= number_format($util_neta_anual, 2, ',', '.') ?></strong></td>
                            <td><strong>Bs. <?= number_format($util_neta_anual * $tasa_dolar, 2, ',', '.') ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>