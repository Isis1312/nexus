<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../conexion.php'; 

$current_date = new DateTime();
$current_year = $current_date->format('Y');
$current_month = $current_date->format('m');

$year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
$month = isset($_GET['month']) ? intval($_GET['month']) : $current_month;

$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

function getTopVendidos($pdo, $year, $month) {
    $lim = 5;
    
    $where_clause = " AND YEAR(v.fecha) = :year AND MONTH(v.fecha) = :month";
    $params = ['year' => $year, 'month' => $month];
    
    try {
        $sql = "SELECT
                    p.id,
                    p.codigo, 
                    p.nombre,
                    COALESCE(SUM(dv.cantidad), 0) AS unidades_vendidas,
                    
                    -- 1. Ingreso Total en DÓLARES (Precio de Venta Unitario * Cantidad)
                    COALESCE(SUM(dv.cantidad * dv.precio_unitario_usd), 0) AS ingreso_total_usd,
                    
                    -- 2. Ganancia Bruta Total en DÓLARES (Precio Venta USD - Precio Costo USD) * Cantidad
                    COALESCE(SUM(dv.cantidad * (dv.precio_unitario_usd - COALESCE(p.precio_costo, 0))), 0) AS ganancia_bruta_usd
                    
                FROM detalle_venta dv
                JOIN ventas v ON dv.id_venta = v.id_venta
                LEFT JOIN productos p ON dv.id_producto = p.id
                WHERE 1=1 $where_clause AND p.nombre IS NOT NULL AND p.nombre != ''
                GROUP BY p.id, p.codigo, p.nombre
                HAVING unidades_vendidas > 0
                ORDER BY unidades_vendidas DESC, ganancia_bruta_usd DESC
                LIMIT $lim"; 

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as &$r) {
            $r['unidades_vendidas'] = intval($r['unidades_vendidas']);
            $r['ingreso_total_usd'] = round(floatval($r['ingreso_total_usd']), 2);
            $r['ganancia_bruta_usd'] = round(floatval($r['ganancia_bruta_usd']), 2);
        }
        unset($r);
        return $rows;
    } catch (PDOException $e) {
        error_log("Error en getTopVendidos: " . $e->getMessage()); 
        return [];
    }
}

$top_vendidos = getTopVendidos($pdo, $year, $month);

$periodo_display = $meses_espanol[intval($month)] . " de " . $year;

$nombres_productos = [];
$unidades_vendidas = [];

foreach ($top_vendidos as $p) {
    $nombres_productos[] = $p['nombre'];
    $unidades_vendidas[] = $p['unidades_vendidas'];
}

$nombres_json = json_encode($nombres_productos);
$unidades_json = json_encode($unidades_vendidas);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Top Productos Vendidos Mensual</title>
    <link rel="stylesheet" href="../css/reportes/general_reportes.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Top 5 productos mas vendidos</h1>
            <a href="../reportes.php" class="volver-button">
            Volver
            </a>
        </div>

        <div class="filtros-container">
            <div class="filtros-card">
                <h3>Filtrar Periodo Mensual</h3>
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
                                <?php for($y = 2023; $y <= $current_year; $y++): ?>
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
                <h2>Resultados</h2>
                <div class="periodo-info">
                    <span>Periodo: **<?= $periodo_display ?>**</span>
                </div>
            </div>

            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Unidades Vendidas</th>
                                <th>Ingreso Total ($)</th>
                                <th>Ganancia Bruta ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($top_vendidos)): ?>
                                <tr><td colspan="6" class="empty-state">No hay datos de ventas para este periodo.</td></tr>
                            <?php else: 
                                $rank = 1;
                                $ganancia_total_general = 0;
                                $ingreso_total_general = 0;
                                $alerta_cero_ingreso = false; // Bandera para la alerta general

                                foreach($top_vendidos as $m): 
                                    $ganancia_total_general += $m['ganancia_bruta_usd'];
                                    $ingreso_total_general += $m['ingreso_total_usd'];
                                    
                                    $ingreso_cero_flag = ($m['ingreso_total_usd'] == 0 && $m['unidades_vendidas'] > 0);
                                    if ($ingreso_cero_flag) $alerta_cero_ingreso = true;
                                    
                                    $ganancia_negativa_flag = ($m['ganancia_bruta_usd'] < 0);
                                    
                                    // Formato de Ingreso y Ganancia
                                    $ingreso_display = number_format($m['ingreso_total_usd'], 2, ',', '.');
                                    $ganancia_display = number_format($m['ganancia_bruta_usd'], 2, ',', '.');
                                ?>
                                <tr>
                                    <td><?= $rank++ ?></td>
                                    <td><?= htmlspecialchars($m['codigo']) ?></td>
                                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                                    <td><?= $m['unidades_vendidas'] ?></td>
                                    <td class="<?= ($ingreso_cero_flag) ? 'negativo alerta-cero' : '' ?>">
                                        $ <?= $ingreso_display ?>
                                    </td>
                                    <td class="<?= ($ganancia_negativa_flag) ? 'negativo' : '' ?>">
                                        $ <?= $ganancia_display ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                        <?php if(!empty($top_vendidos)): ?>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="4"><strong>TOTALES (Top 5)</strong></td>
                                <td><strong>$ <?= number_format($ingreso_total_general, 2, ',', '.') ?></strong></td>
                                <td class="<?= ($ganancia_total_general < 0) ? 'negativo' : '' ?>">
                                    <strong>$ <?= number_format($ganancia_total_general, 2, ',', '.') ?></strong>
                                </td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                    
                    <?php if (isset($alerta_cero_ingreso) && $alerta_cero_ingreso): ?>
                    <p class="alerta-cero-msg">
                        **AVISO IMPORTANTE:** Hay productos con Ingreso Total $0 a pesar de tener unidades vendidas.
                        Esto indica que el campo **`precio_unitario_usd` en `detalle_venta` es 0 o nulo** para esas transacciones.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="reporte-container" style="margin-top: 40px;">
            <div class="reporte-header">
                <h2>Gráfico de Unidades Vendidas (Top 5)</h2>
            </div>
            <div class="chart-container">
                <canvas id="topProductosChart"></canvas>
            </div>
        </div>

    </div>
</main>
<style>
.negativo {
    color: red;
    font-weight: bold;
}
.alerta-cero {
    color: darkorange !important; 
    font-weight: bold !important;
}
.alerta-cero-msg {
    margin-top: 15px;
    padding: 10px;
    border: 1px solid darkorange;
    background-color: #fff8e1;
    color: #cc7700;
    font-size: 0.9em;
    font-weight: bold;
}
.chart-container {
    width: 100%;
    max-width: 800px; 
    margin: 20px auto; 
    padding: 10px;
}
</style>

<script>
    const nombres = <?= $nombres_json ?>;
    const unidades = <?= $unidades_json ?>;

    const ctx = document.getElementById('topProductosChart').getContext('2d');

    const topProductosChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: nombres,
            datasets: [
                {
                    label: 'Unidades Vendidas',
                    data: unidades,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)', 
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                }
            ]
        },
        options: {
            responsive: true,
            indexAxis: 'x', // Barras Verticales
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Producto'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Unidades Vendidas'
                    },
                    beginAtZero: true
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Ranking de Productos por Cantidad Vendida'
                },
                legend: {
                    position: 'top',
                }
            }
        }
    });
</script>

</body>
</html>