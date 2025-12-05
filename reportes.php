<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'menu.php';

// Inicializar sistema de permisos
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

// Verificar si puede ver este módulo 
if (!$sistemaPermisos->puedeVer('reportes')) {
    header('Location: inicio.php');
    exit();
}

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

// Obtener productos con niveles críticos de inventario
function getProductosNivelExistencia($pdo) {
    $query = "SELECT 
                p.id,
                p.nombre,
                p.codigo,
                p.categoria,
                p.existencia,
                p.minimo_inventario,
                p.maximo_inventario,
                p.precio_venta_bs,
                p.precio_venta_usd,
                (p.existencia * p.precio_venta_bs) as valor_total_bs,
                CASE 
                    WHEN p.existencia <= p.minimo_inventario THEN 'CRITICO'
                    WHEN p.existencia <= p.minimo_inventario * 1.5 THEN 'BAJO'
                    WHEN p.existencia >= p.maximo_inventario THEN 'EXCESO'
                    ELSE 'NORMAL'
                END as estado_inventario,
                ROUND((p.existencia - p.minimo_inventario), 0) as diferencia_minimo,
                ROUND((p.maximo_inventario - p.existencia), 0) as diferencia_maximo,
                ROUND((p.existencia / p.maximo_inventario * 100), 1) as porcentaje_inventario
              FROM productos p
              WHERE p.estado = 'active'
                AND (p.existencia <= p.minimo_inventario 
                     OR p.existencia >= p.maximo_inventario 
                     OR p.existencia <= p.minimo_inventario * 1.5)
              ORDER BY 
                CASE 
                    WHEN p.existencia <= p.minimo_inventario THEN 1
                    WHEN p.existencia <= p.minimo_inventario * 1.5 THEN 2
                    WHEN p.existencia >= p.maximo_inventario THEN 3
                    ELSE 4
                END,
                p.existencia ASC,
                p.nombre ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener resumen de niveles de inventario
function getResumenNivelesInventario($pdo) {
    $query = "SELECT 
                COUNT(CASE WHEN existencia <= minimo_inventario THEN 1 END) as criticos,
                COUNT(CASE WHEN existencia > minimo_inventario AND existencia <= minimo_inventario * 1.5 THEN 1 END) as bajos,
                COUNT(CASE WHEN existencia >= maximo_inventario THEN 1 END) as exceso,
                COUNT(CASE WHEN existencia > minimo_inventario * 1.5 AND existencia < maximo_inventario THEN 1 END) as normales,
                COUNT(*) as total_productos,
                SUM(CASE WHEN existencia <= minimo_inventario THEN (minimo_inventario - existencia) * precio_venta_bs ELSE 0 END) as valor_reposicion_critico,
                SUM(CASE WHEN existencia >= maximo_inventario THEN (existencia - maximo_inventario) * precio_venta_bs ELSE 0 END) as valor_exceso_inventario
              FROM productos 
              WHERE estado = 'active'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener productos próximos a mínimo (para alertas)
function getProductosProximosMinimo($pdo) {
    $query = "SELECT 
                p.nombre,
                p.codigo,
                p.existencia,
                p.minimo_inventario,
                p.maximo_inventario,
                ROUND((p.existencia / p.minimo_inventario * 100), 1) as porcentaje_minimo
              FROM productos p
              WHERE p.estado = 'active'
                AND p.existencia > p.minimo_inventario
                AND p.existencia <= p.minimo_inventario * 1.2
              ORDER BY (p.existencia / p.minimo_inventario) ASC
              LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Ejecutar consultas según tipo de reporte
$reporte_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'mensual';

// Inicializar variables para evitar errores
$reporte_mensual = [];
$productos_mas_vendidos = [];
$ventas_por_metodo = [];
$resumen_mensual = [];
$reporte_diario = [];
$productos_nivel_existencia = [];
$resumen_niveles_inventario = [];
$productos_proximos_minimo = [];

if ($reporte_tipo === 'mensual') {
    $reporte_mensual = getReporteMensual($pdo, $year, $month) ?: [];
    $resumen_mensual = getResumenMensual($pdo, $year, $month) ?: [];
    $ventas_por_metodo = getVentasPorMetodoPago($pdo, $year, $month) ?: [];
} elseif ($reporte_tipo === 'diario') {
    $reporte_diario = getReporteDiario($pdo, $day) ?: [];
} elseif ($reporte_tipo === 'inventario') {
    $productos_nivel_existencia = getProductosNivelExistencia($pdo) ?: [];
    $resumen_niveles_inventario = getResumenNivelesInventario($pdo) ?: [];
    $productos_proximos_minimo = getProductosProximosMinimo($pdo) ?: [];
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
    <link rel="stylesheet" href="css/reportes.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Reportes de Ventas</h1>
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
                                <option value="inventario" <?= $reporte_tipo === 'inventario' ? 'selected' : '' ?>>Nivel de Existencias</option>
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
        
        <!-- Reporte de Nivel de Existencias -->
        <?php if($reporte_tipo === 'inventario'): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Reporte de Nivel de Existencias</h2>
                <div class="periodo-info">
                    <span>Estado actual del inventario</span>
                </div>
            </div>
            
            <!-- Resumen Estadístico -->
            <div class="estadisticas-grid">
                <div class="estadistica-card inventario critico">
                    <div class="estadistica-label">Productos Críticos</div>
                    <div class="estadistica-value" style="color: #dc3545;"><?= $resumen_niveles_inventario['criticos'] ?? 0 ?></div>
                </div>
                
                <div class="estadistica-card inventario bajo">
                    <div class="estadistica-label">Productos Bajos</div>
                    <div class="estadistica-value" style="color: #ffc107;"><?= $resumen_niveles_inventario['bajos'] ?? 0 ?></div>
                </div>
                
                <div class="estadistica-card inventario exceso">
                    <div class="estadistica-label">Productos en Exceso</div>
                    <div class="estadistica-value" style="color: #6f42c1;"><?= $resumen_niveles_inventario['exceso'] ?? 0 ?></div>
                </div>
                
                <div class="estadistica-card inventario normal">
                    <div class="estadistica-label">Productos Normales</div>
                    <div class="estadistica-value" style="color: #28a745;"><?= $resumen_niveles_inventario['normales'] ?? 0 ?></div>
                </div>
            </div>
            
            <!-- Alertas -->
            <?php if(($resumen_niveles_inventario['criticos'] ?? 0) > 0): ?>
            <div class="alerta-inventario alerta-critica">
                <strong>⚠️ ALERTA CRÍTICA:</strong> Hay <?= $resumen_niveles_inventario['criticos'] ?? 0 ?> productos con inventario por debajo del mínimo requerido.
            </div>
            <?php endif; ?>
            
            <?php if(($resumen_niveles_inventario['bajos'] ?? 0) > 0): ?>
            <div class="alerta-inventario alerta-advertencia">
                <strong>⚠️ ADVERTENCIA:</strong> Hay <?= $resumen_niveles_inventario['bajos'] ?? 0 ?> productos con inventario cercano al mínimo.
            </div>
            <?php endif; ?>
            
            <!-- Tabla de Productos con Niveles Críticos -->
            <div class="tabla-container">
                <h3>Productos con Niveles de Inventario Críticos</h3>
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Código</th>
                                <th>Categoría</th>
                                <th>Existencia</th>
                                <th>Mínimo</th>
                                <th>Máximo</th>
                                <th>Estado</th>
                                <th>Diferencia</th>
                                <th>% Inventario</th>
                                <th>Valor (Bs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($productos_nivel_existencia)): ?>
                                <tr>
                                    <td colspan="10" class="empty-state">No hay productos con niveles críticos de inventario</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($productos_nivel_existencia as $producto): 
                                    $estado = $producto['estado_inventario'] ?? 'NORMAL';
                                    $color_estado = [
                                        'CRITICO' => '#dc3545',
                                        'BAJO' => '#ffc107',
                                        'EXCESO' => '#6f42c1',
                                        'NORMAL' => '#28a745'
                                    ][$estado] ?? '#6c757d';
                                    
                                    $diferencia = '';
                                    if ($estado === 'CRITICO' || $estado === 'BAJO') {
                                        $diferencia = 'Faltan: ' . ($producto['diferencia_minimo'] > 0 ? $producto['diferencia_minimo'] : '0');
                                    } elseif ($estado === 'EXCESO') {
                                        $diferencia = 'Exceden: ' . ($producto['diferencia_maximo'] < 0 ? abs($producto['diferencia_maximo']) : '0');
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($producto['nombre'] ?? '') ?></td>
                                    <td><?= $producto['codigo'] ?? '' ?></td>
                                    <td><?= $producto['categoria'] ?? '' ?></td>
                                    <td><strong><?= $producto['existencia'] ?? 0 ?></strong></td>
                                    <td><?= $producto['minimo_inventario'] ?? 0 ?></td>
                                    <td><?= $producto['maximo_inventario'] ?? 0 ?></td>
                                    <td>
                                        <span class="nivel-badge nivel-<?= strtolower($estado) ?>">
                                            <?= $estado ?>
                                        </span>
                                    </td>
                                    <td><?= $diferencia ?></td>
                                    <td>
                                        <?= $producto['porcentaje_inventario'] ?? 0 ?>%
                                        <div class="progreso-inventario">
                                            <div class="progreso-inventario-fill progreso-<?= strtolower($estado) ?>" 
                                                 style="width: <?= min(100, $producto['porcentaje_inventario'] ?? 0) ?>%">
                                            </div>
                                        </div>
                                    </td>
                                    <td>Bs. <?= number_format($producto['valor_total_bs'] ?? 0, 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Productos Próximos a Mínimo -->
            <?php if(!empty($productos_proximos_minimo)): ?>
            <div class="tabla-container">
                <h3>Productos Próximos al Mínimo de Inventario (Alerta Temprana)</h3>
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Código</th>
                                <th>Existencia Actual</th>
                                <th>Mínimo Requerido</th>
                                <th>% del Mínimo</th>
                                <th>Faltan para Mínimo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($productos_proximos_minimo as $producto): 
                                $porcentaje = $producto['porcentaje_minimo'] ?? 0;
                                $color = $porcentaje <= 105 ? '#dc3545' : ($porcentaje <= 120 ? '#ffc107' : '#28a745');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($producto['nombre'] ?? '') ?></td>
                                <td><?= $producto['codigo'] ?? '' ?></td>
                                <td><?= $producto['existencia'] ?? 0 ?></td>
                                <td><?= $producto['minimo_inventario'] ?? 0 ?></td>
                                <td style="color: <?= $color ?>; font-weight: bold;">
                                    <?= $porcentaje ?>%
                                </td>
                                <td>
                                    <?= max(0, ($producto['minimo_inventario'] ?? 0) - ($producto['existencia'] ?? 0)) ?>
                                    unidades
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Resumen de Valores -->
            <?php if(isset($resumen_niveles_inventario['valor_reposicion_critico']) && $resumen_niveles_inventario['valor_reposicion_critico'] > 0): ?>
            <div class="metodos-pago-container">
                <h3>Resumen Financiero de Inventario</h3>
                <div class="metodos-grid">
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));">
                        <div class="metodo-nombre">Valor Requerido para Reposición</div>
                        <div class="metodo-cantidad" style="color: #dc3545;">Productos Críticos y Bajos</div>
                        <div class="metodo-total" style="color: #dc3545;">
                            Bs. <?= number_format($resumen_niveles_inventario['valor_reposicion_critico'] ?? 0, 2, ',', '.') ?>
                        </div>
                    </div>
                    
                    <?php if(isset($resumen_niveles_inventario['valor_exceso_inventario']) && $resumen_niveles_inventario['valor_exceso_inventario'] > 0): ?>
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(111, 66, 193, 0.1), rgba(111, 66, 193, 0.05));">
                        <div class="metodo-nombre">Valor en Exceso de Inventario</div>
                        <div class="metodo-cantidad" style="color: #6f42c1;">Productos sobre Máximo</div>
                        <div class="metodo-total" style="color: #6f42c1;">
                            Bs. <?= number_format($resumen_niveles_inventario['valor_exceso_inventario'] ?? 0, 2, ',', '.') ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
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
    } else if (tipo === 'inventario') {
        // Para inventario no mostramos filtros de fecha
        $('#filtro-mes').hide();
        $('#filtro-ano').hide();
        $('#filtro-dia').hide();
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