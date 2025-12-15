<?php

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}


require_once '../conexion.php'; 


// --- 1. Definición de Fechas 
$current_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-30 days'));

// Procesar filtros de rango de fechas si se enviaron
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $current_date;

// --- 2. Funciones de Consultas SQL ---
/**
 * Obtiene el detalle de ventas en el rango de fechas.
 * @param PDO $pdo Objeto de conexión a la base de datos.
 * @param string $start_date Fecha de inicio (Y-m-d).
 * @param string $end_date Fecha de fin (Y-m-d).
 * @return array
 */
function getReporteRangoFechas($pdo, $start_date, $end_date) {
    $query = "SELECT 
                v.id_venta,
                v.nro_factura,
                v.cliente,
                DATE(v.fecha) as fecha_venta,
                v.metodo_pago,
                v.total_bs,
                v.total_usd,
                COUNT(dv.id_detalle) as items
              FROM ventas v
              LEFT JOIN detalle_venta dv ON v.id_venta = dv.id_venta
              WHERE DATE(v.fecha) BETWEEN :start_date AND :end_date
              GROUP BY v.id_venta, v.nro_factura, v.cliente, DATE(v.fecha), v.metodo_pago, v.total_bs, v.total_usd
              ORDER BY v.fecha DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el resumen consolidado de todas las ventas dentro del rango.
 * @param PDO $pdo Objeto de conexión a la base de datos.
 * @param string $start_date Fecha de inicio (Y-m-d).
 * @param string $end_date Fecha de fin (Y-m-d).
 * @return array
 */
function getResumenRangoFechas($pdo, $start_date, $end_date) {
    $query = "SELECT 
                COUNT(DISTINCT v.id_venta) as total_facturas,
                SUM(v.total_bs) as total_bs,
                SUM(v.total_usd) as total_usd,
                COUNT(DISTINCT v.id_cliente) as clientes_unicos,
                SUM(dv.cantidad) as total_productos
              FROM ventas v
              LEFT JOIN detalle_venta dv ON v.id_venta = dv.id_venta
              WHERE DATE(v.fecha) BETWEEN :start_date AND :end_date";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


// --- 3. Ejecución de Consultas y Cálculo de Totales ---

$reporte_rango = getReporteRangoFechas($pdo, $start_date, $end_date) ?: [];
$resumen_rango = getResumenRangoFechas($pdo, $start_date, $end_date) ?: [];

// Variables para el resumen general (usando el resumen consolidado)
$total_rango_bs = floatval($resumen_rango['total_bs'] ?? 0);
$total_rango_usd = floatval($resumen_rango['total_usd'] ?? 0);
$total_rango_facturas = intval($resumen_rango['total_facturas'] ?? 0);
$total_productos_rango = intval($resumen_rango['total_productos'] ?? 0);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de Ventas por Rango</title>
    <link rel="stylesheet" href="../css/reportes/repo_ventas.css"> 
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Reporte de ventas</h1>
            <a href="../reportes.php" class="volver-button"> Volver</a>
        </div>

        <div class="filtros-container">
            <div class="filtros-card">
                <h3>Filtrar por Rango</h3>
                <form method="GET" class="filtros-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de Inicio:</label>
                            <input type="date" name="start_date" class="form-input" value="<?= htmlspecialchars($start_date) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Fecha de Fin:</label>
                            <input type="date" name="end_date" class="form-input" value="<?= htmlspecialchars($end_date) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-generar">
                                Generar Reporte
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Resumen de Ventas</h2>
                <div class="periodo-info">
                    <span>Desde: <?= date('d/m/Y', strtotime($start_date)) ?> | Hasta: <?= date('d/m/Y', strtotime($end_date)) ?></span>
                </div>
            </div>
            
            <div class="estadisticas-grid">
                <div class="estadistica-card">
                    <div class="estadistica-label">Total Ventas (Bs)</div>
                    <div class="estadistica-value">Bs. <?= number_format($total_rango_bs, 2, ',', '.') ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Total Ventas ($)</div>
                    <div class="estadistica-value">$ <?= number_format($total_rango_usd, 2, ',', '.') ?></div>
                </div>

                <div class="estadistica-card">
                    <div class="estadistica-label">Facturas Emitidas</div>
                    <div class="estadistica-value"><?= $total_rango_facturas ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Productos Vendidos</div>
                    <div class="estadistica-value"><?= $total_productos_rango ?></div>
                </div>
            </div>
            
            <div class="tabla-container">
                <h3>Detalle de Facturas</h3>
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Método de Pago</th>
                                <th>Items</th>
                                <th>Total Bs</th>
                                <th>Total USD</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($reporte_rango)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">No hay ventas registradas en el rango seleccionado</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($reporte_rango as $venta): ?>
                                <tr>
                                    <td><?= $venta['nro_factura'] ?? 'N/A' ?></td>
                                    <td><?= date('d/m/Y', strtotime($venta['fecha_venta'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($venta['cliente'] ?? 'Sin cliente') ?></td>
                                    <td><?= $venta['metodo_pago'] ?? 'No especificado' ?></td>
                                    <td><?= $venta['items'] ?? 0 ?></td>
                                    <td>Bs. <?= number_format($venta['total_bs'] ?? 0, 2, ',', '.') ?></td>
                                    <td>$ <?= number_format($venta['total_usd'] ?? 0, 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="4"><strong>TOTAL DEL RANGO</strong></td>
                                <td><strong><?= $total_productos_rango ?></strong></td>
                                <td><strong>Bs. <?= number_format($total_rango_bs, 2, ',', '.') ?></strong></td>
                                <td><strong>$ <?= number_format($total_rango_usd, 2, ',', '.') ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</main>


</body>
</html>