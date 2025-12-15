<?php


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
$current_month = date('m');

$year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
$month = isset($_GET['month']) ? intval($_GET['month']) : $current_month;

$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

function getTotalVentasMensual($pdo, $year, $month) {
    $query = "SELECT 
               IFNULL(SUM(v.total_usd), 0) as total_mensual_usd
             FROM ventas v
             WHERE YEAR(v.fecha) = :year 
               AND MONTH(v.fecha) = :month";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['year' => $year, 'month' => $month]);
    return floatval($stmt->fetchColumn());
}

$ingresos_mes = getTotalVentasMensual($pdo, $year, $month);

$costos_mes   = $ingresos_mes * 0.60;
$gastos_mes   = $ingresos_mes * 0.10;
$util_bruta_mes = $ingresos_mes - $costos_mes;

$util_neta_mes = $util_bruta_mes - $gastos_mes; 

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Utilidad Mensual</title>
     <link rel="stylesheet" href="../css/reportes/general_reportes.css">
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Utilidad mensual</h1>
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
                            <label>Mes:</label>
                            <select name="month" class="form-select">
                                <?php foreach($meses_espanol as $num => $nombre): ?>
                                    <option value="<?= $num ?>" <?= $num == $month ? 'selected' : '' ?>><?= $nombre ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                <h2>Estado de Resultados Simplificado</h2>
                <div class="periodo-info">
                    <span><?= $meses_espanol[intval($month)] ?> de <?= $year ?></span>
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
                            <td>$. <?= number_format($ingresos_mes, 2, ',', '.') ?></td>
                            <td>Bs. <?= number_format($ingresos_mes * $tasa_dolar, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td>Costos (60% de Ingresos)</td>
                            <td>$. <?= number_format($costos_mes, 2, ',', '.') ?></td>
                            <td>Bs. <?= number_format($costos_mes * $tasa_dolar, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Utilidad Bruta</strong></td>
                            <td><strong>$. <?= number_format($util_bruta_mes, 2, ',', '.') ?></strong></td>
                            <td><strong>Bs. <?= number_format($util_bruta_mes * $tasa_dolar, 2, ',', '.') ?></strong></td>
                        </tr>
                        <tr>
                            <td>Gastos (10% de Ingresos)</td>
                            <td>$. <?= number_format($gastos_mes, 2, ',', '.') ?></td>
                            <td>Bs. <?= number_format($gastos_mes * $tasa_dolar, 2, ',', '.') ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>UTILIDAD NETA</strong></td>
                            <td><strong>$. <?= number_format($util_neta_mes, 2, ',', '.') ?></strong></td>
                            <td><strong>Bs. <?= number_format($util_neta_mes * $tasa_dolar, 2, ',', '.') ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>