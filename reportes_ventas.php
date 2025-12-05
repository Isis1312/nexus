<?php


session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'menu.php';



// Obtener fecha actual para valores por defecto
$current_year = date('Y');
$current_month = date('m');
$current_day = date('Y-m-d');

// Procesar filtros si se enviaron
$year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
$month = isset($_GET['month']) ? intval($_GET['month']) : $current_month;
$day = isset($_GET['day']) ? $_GET['day'] : $current_day;

// Obtener reporte mensual
function getReporteMensual($pdo, $year, $month) {
    $query = "SELECT 
                DAY(v.fecha) as dia,
                COUNT(DISTINCT v.id_venta) as total_facturas,
                SUM(v.total_bs) as total_ventas_bs,
                SUM(v.total_usd) as total_ventas_usd,
                SUM(dv.cantidad) as total_productos,
                AVG(v.total_bs) as promedio_venta_bs
              FROM ventas v
              LEFT JOIN detalle_venta dv ON v.id_venta = dv.id_venta
              WHERE YEAR(v.fecha) = :year 
                AND MONTH(v.fecha) = :month
              GROUP BY DAY(v.fecha)
              ORDER BY dia";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['year' => $year, 'month' => $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener reporte diario
function getReporteDiario($pdo, $day) {
    $query = "SELECT 
                v.id_venta,
                v.nro_factura,
                v.cliente,
                v.fecha,
                v.metodo_pago,
                v.total_bs,
                v.total_usd,
                COUNT(dv.id_detalle) as items,
                TIME(v.fecha) as hora
              FROM ventas v
              LEFT JOIN detalle_venta dv ON v.id_venta = dv.id_venta
              WHERE DATE(v.fecha) = :fecha
              GROUP BY v.id_venta
              ORDER BY v.fecha DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['fecha' => $day]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Obtener resumen general del mes
function getResumenMensual($pdo, $year, $month) {
    $query = "SELECT 
                COUNT(DISTINCT v.id_venta) as total_facturas,
                SUM(v.total_bs) as total_bs,
                SUM(v.total_usd) as total_usd,
                AVG(v.total_bs) as promedio_bs,
                COUNT(DISTINCT v.id_cliente) as clientes_unicos,
                MIN(v.total_bs) as venta_minima,
                MAX(v.total_bs) as venta_maxima
              FROM ventas v
              WHERE YEAR(v.fecha) = :year 
                AND MONTH(v.fecha) = :month";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['year' => $year, 'month' => $month]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener ventas por método de pago
function getVentasPorMetodoPago($pdo, $year, $month) {
    $query = "SELECT 
                v.metodo_pago,
                COUNT(v.id_venta) as cantidad,
                SUM(v.total_bs) as total_bs
              FROM ventas v
              WHERE YEAR(v.fecha) = :year 
                AND MONTH(v.fecha) = :month
              GROUP BY v.metodo_pago
              ORDER BY total_bs DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['year' => $year, 'month' => $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Ejecutar consultas según tipo de reporte
$reporte_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'mensual';

// Inicializar variables para evitar errores
$reporte_mensual = [];
$productos_mas_vendidos = [];
$ventas_por_metodo = [];
$resumen_mensual = [];

if ($reporte_tipo === 'mensual') {
    $reporte_mensual = getReporteMensual($pdo, $year, $month) ?: [];
    $resumen_mensual = getResumenMensual($pdo, $year, $month) ?: [];
    $ventas_por_metodo = getVentasPorMetodoPago($pdo, $year, $month) ?: [];
} else {
    $reporte_diario = getReporteDiario($pdo, $day) ?: [];
}

// Calcular totales del mes 
$total_mensual_bs = 0;
$total_mensual_facturas = 0;
$total_productos_mensual = 0;
$total_mensual_usd = 0;

if (is_array($reporte_mensual)) {
    foreach ($reporte_mensual as $dia) {
        $total_mensual_bs += floatval($dia['total_ventas_bs'] ?? 0);
        $total_mensual_facturas += intval($dia['total_facturas'] ?? 0);
        $total_productos_mensual += intval($dia['total_productos'] ?? 0);
        $total_mensual_usd += floatval($dia['total_ventas_usd'] ?? 0);
    }
}

// Meses 
$meses_espanol = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de Ventas</title>
    <link rel="stylesheet" href="css/reportes/repo_ventas.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Reportes de Ventas</h1>
            <a href="reportes.php" class="volver-button">
             Volver
            </a>
        </div>

        <!-- Filtros -->
        <div class="filtros-container">
            <div class="filtros-card">
                <h3>Filtrar Reporte</h3>
                <form method="GET" class="filtros-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Reporte:</label>
                            <select name="tipo" class="form-select" onchange="cambiarTipoReporte(this.value)">
                                <option value="mensual" <?= $reporte_tipo === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                                <option value="diario" <?= $reporte_tipo === 'diario' ? 'selected' : '' ?>>Diario</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="filtro-mes">
                            <label>Mes:</label>
                            <select name="month" class="form-select">
                                <?php foreach($meses_espanol as $num => $nombre): ?>
                                    <option value="<?= $num ?>" <?= $num == $month ? 'selected' : '' ?>>
                                        <?= $nombre ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="filtro-ano">
                            <label>Año:</label>
                            <select name="year" class="form-select">
                                <?php for($y = 2023; $y <= $current_year; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>>
                                        <?= $y ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="filtro-dia" style="display: <?= $reporte_tipo === 'diario' ? 'block' : 'none' ?>;">
                            <label>Día:</label>
                            <input type="date" name="day" class="form-input" value="<?= $day ?>">
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

        <!-- Reporte Mensual -->
        <?php if($reporte_tipo === 'mensual'): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Reporte Mensual de Ventas</h2>
                <div class="periodo-info">
                    <span><?= $meses_espanol[$month] ?> <?= $year ?></span>
                </div>
            </div>
            
            <!-- Resumen Estadístico -->
            <div class="estadisticas-grid">
                <div class="estadistica-card">
                    <div class="estadistica-label">Total Ventas (Bs)</div>
                    <div class="estadistica-value">Bs. <?= number_format($total_mensual_bs, 2, ',', '.') ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Facturas Emitidas</div>
                    <div class="estadistica-value"><?= $total_mensual_facturas ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Promedio por Factura</div>
                    <div class="estadistica-value">Bs. <?= $total_mensual_facturas > 0 ? number_format($total_mensual_bs / $total_mensual_facturas, 2, ',', '.') : '0,00' ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Productos Vendidos</div>
                    <div class="estadistica-value"><?= $total_productos_mensual ?></div>
                </div>
            </div>
            
            <!-- Tabla de Ventas por Día -->
            <div class="tabla-container">
                <h3>Ventas por Día</h3>
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Día</th>
                                <th>Facturas</th>
                                <th>Productos Vendidos</th>
                                <th>Total Bs</th>
                                <th>Total USD</th>
                                <th>Promedio Bs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($reporte_mensual)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">No hay ventas registradas para este mes</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($reporte_mensual as $dia): ?>
                                <tr>
                                    <td><?= $dia['dia'] ?? 0 ?></td>
                                    <td><?= $dia['total_facturas'] ?? 0 ?></td>
                                    <td><?= $dia['total_productos'] ?? 0 ?></td>
                                    <td>Bs. <?= number_format($dia['total_ventas_bs'] ?? 0, 2, ',', '.') ?></td>
                                    <td>$ <?= number_format($dia['total_ventas_usd'] ?? 0, 2, ',', '.') ?></td>
                                    <td>Bs. <?= number_format($dia['promedio_venta_bs'] ?? 0, 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td><strong>TOTAL</strong></td>
                                <td><strong><?= $total_mensual_facturas ?></strong></td>
                                <td><strong><?= $total_productos_mensual ?></strong></td>
                                <td><strong>Bs. <?= number_format($total_mensual_bs, 2, ',', '.') ?></strong></td>
                                <td><strong>$ <?= number_format($total_mensual_usd, 2, ',', '.') ?></strong></td>
                                <td><strong>Bs. <?= $total_mensual_facturas > 0 ? number_format($total_mensual_bs / $total_mensual_facturas, 2, ',', '.') : '0,00' ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            
            
            <!-- Métodos de Pago -->
            <?php if(!empty($ventas_por_metodo)): ?>
            <div class="metodos-pago-container">
                <h3>Ventas por Método de Pago</h3>
                <div class="metodos-grid">
                    <?php foreach($ventas_por_metodo as $metodo): ?>
                    <div class="metodo-card">
                        <div class="metodo-nombre"><?= $metodo['metodo_pago'] ?? 'No especificado' ?></div>
                        <div class="metodo-cantidad"><?= $metodo['cantidad'] ?? 0 ?> facturas</div>
                        <div class="metodo-total">Bs. <?= number_format($metodo['total_bs'] ?? 0, 2, ',', '.') ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Reporte Diario -->
        <?php if($reporte_tipo === 'diario'): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Reporte Diario de Ventas</h2>
                <div class="periodo-info">
                    <span><?= date('d/m/Y', strtotime($day)) ?></span>
                </div>
            </div>
            
            <!-- Estadísticas Diarias -->
            <?php 
            $total_diario_bs = 0;
            $total_diario_usd = 0;
            $productos_vendidos_dia = 0;
            
            if(is_array($reporte_diario)) {
                foreach($reporte_diario as $venta) {
                    $total_diario_bs += floatval($venta['total_bs'] ?? 0);
                    $total_diario_usd += floatval($venta['total_usd'] ?? 0);
                    $productos_vendidos_dia += intval($venta['items'] ?? 0);
                }
            }
            $total_diario_facturas = is_array($reporte_diario) ? count($reporte_diario) : 0;
            ?>
            
            <div class="estadisticas-grid">
                <div class="estadistica-card">
                    <div class="estadistica-label">Total del Día (Bs)</div>
                    <div class="estadistica-value">Bs. <?= number_format($total_diario_bs, 2, ',', '.') ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Facturas del Día</div>
                    <div class="estadistica-value"><?= $total_diario_facturas ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Productos Vendidos</div>
                    <div class="estadistica-value"><?= $productos_vendidos_dia ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Promedio por Factura</div>
                    <div class="estadistica-value">Bs. <?= $total_diario_facturas > 0 ? number_format($total_diario_bs / $total_diario_facturas, 2, ',', '.') : '0,00' ?></div>
                </div>
            </div>
            
            <!-- Tabla de Ventas Diarias -->
            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th>Cliente</th>
                                <th>Hora</th>
                                <th>Método de Pago</th>
                                <th>Items</th>
                                <th>Total Bs</th>
                                <th>Total USD</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($reporte_diario)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">No hay ventas registradas para este día</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($reporte_diario as $venta): ?>
                                <tr>
                                    <td><?= $venta['nro_factura'] ?? 'N/A' ?></td>
                                    <td><?= htmlspecialchars($venta['cliente'] ?? 'Sin cliente') ?></td>
                                    <td><?= isset($venta['hora']) ? substr($venta['hora'], 0, 5) : '--:--' ?></td>
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
                                <td colspan="5"><strong>TOTAL DEL DÍA</strong></td>
                                <td><strong>Bs. <?= number_format($total_diario_bs, 2, ',', '.') ?></strong></td>
                                <td><strong>$ <?= number_format($total_diario_usd, 2, ',', '.') ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function cambiarTipoReporte(tipo) {
    if (tipo === 'diario') {
        $('#filtro-mes').hide();
        $('#filtro-ano').hide();
        $('#filtro-dia').show();
    } else {
        $('#filtro-mes').show();
        $('#filtro-ano').show();
        $('#filtro-dia').hide();
    }
}

// Inicializar el estado correcto
$(document).ready(function() {
    cambiarTipoReporte('<?= $reporte_tipo ?>');
});
</script>
</body>
</html>