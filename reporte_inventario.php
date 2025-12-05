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
        p.estado = 'active'; -- Solo contamos productos activos según su BD
";

try {
    $stmt = $pdo->query($sql);
    $resumen = $stmt->fetch();
} catch (\PDOException $e) {
    die("Error al ejecutar la consulta del inventario: " . $e->getMessage());
}
?>

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Valorización de Inventario</title>

    <link rel="stylesheet" href="css/reportes/repo_inventario.css">
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


            </div>
        </div>
    </main>
</body>
</html>