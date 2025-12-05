<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'menu.php';

$sql = "
    SELECT
        -- 1. Total de Unidades de Productos en Stock
        SUM(p.cantidad) AS total_productos_en_stock,

        -- 2. Valor Total del Inventario al Precio de Costo (Inversión)
        SUM(p.cantidad * p.precio_costo) AS valor_total_inventario_costo,

        -- 3. Valor Total de Venta del Stock (Valor Potencial)
        SUM(p.cantidad * p.precio_venta) AS valor_total_venta_stock,

        -- 4. Ganancia Bruta Potencial (Valor Venta - Valor Costo)
        SUM(p.cantidad * (p.precio_venta - p.precio_costo)) AS ganancia_bruta_potencial
    FROM
        productos p
    WHERE
        p.estado = 'active';
";

try {
    $stmt = $pdo->query($sql);
    $resumen = $stmt->fetch();
} catch (\PDOException $e) {
    die("Error al ejecutar la consulta del inventario: " . $e->getMessage());
}

// Datos para el gráfico
$valor_costo = floatval($resumen['valor_total_inventario_costo']);
$ganancia_bruta = floatval($resumen['ganancia_bruta_potencial']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Valorización de Inventario</title>

    <link rel="stylesheet" href="css/reportes/repo_inventario.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
   <main class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                    <h1 class="page-title">Resumen de valorización del inventario actual</h1>
                    <a href="reportes.php" class="volver-button">
                    Volver
                    </a>
                </div>


                <div class="report-summary">
                    
                    <div class="metric-card units">
                        <h3 class="metric-label">Total de Unidades en Stock</h3>
                        <p class="metric-value"><?php echo number_format($resumen['total_productos_en_stock'], 0, ',', '.'); ?></p>
                    </div>

                    <div class="metric-card cost">
                        <h3 class="metric-label">Valor Total de Inventario (Costo / Inversión)</h3>
                        <p class="metric-value">$ <?php echo number_format($resumen['valor_total_inventario_costo'], 2, ',', '.'); ?></p>
                    </div>

                    <div class="metric-card sales">
                        <h3 class="metric-label">Valor Total de Venta del Stock</h3>
                        <p class="metric-value">$ <?php echo number_format($resumen['valor_total_venta_stock'], 2, ',', '.'); ?></p>
                    </div>

                    <div class="metric-card profit">
                        <h3 class="metric-label">Ganancia Bruta Potencial del Stock</h3>
                        <p class="metric-value">$ <?php echo number_format($resumen['ganancia_bruta_potencial'], 2, ',', '.'); ?></p>
                    </div>
                </div>

                <div class="chart-container-wrapper">
                    <div class="chart-card">
                        <h2 class="chart-title">Distribución de Valor Potencial (Costo vs. Ganancia Bruta)</h2>
                        <canvas id="inventoryValueChart"></canvas>
                    </div>
                </div>


            </div>
        </div>
    </main>

    <script>
    const ctx = document.getElementById('inventoryValueChart');
    
    // Datos PHP pasados a JS
    const valorCosto = <?php echo json_encode($valor_costo); ?>;
    const gananciaBruta = <?php echo json_encode($ganancia_bruta); ?>;

    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Valor Costo (Inversión)', 'Ganancia Bruta Potencial'],
            datasets: [{
                data: [valorCosto, gananciaBruta],
                backgroundColor: [
                    '#e74c3c', // Rojo/Naranja para Costo
                    '#2ecc71'  // Verde para Ganancia
                ],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            size: 14
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            // Formatear el valor a moneda
                            const value = context.parsed;
                            label += new Intl.NumberFormat('es-ES', { 
                                style: 'currency', 
                                currency: 'USD',
                                minimumFractionDigits: 2 
                            }).format(value);
                            return label;
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>