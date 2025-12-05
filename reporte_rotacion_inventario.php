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
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$tipo_analisis = isset($_GET['tipo_analisis']) ? $_GET['tipo_analisis'] : 'rotacion_ventas';
$limite_resultados = isset($_GET['limite']) ? intval($_GET['limite']) : 20;
$ordenar_por = isset($_GET['ordenar_por']) ? $_GET['ordenar_por'] : 'rotacion_promedio';

// Función para obtener rotación por categoría basada en ventas
function getRotacionPorCategoriaVentas($pdo, $start_date, $end_date) {
    $query = "SELECT 
                cp.id as categoria_id,
                cp.nombre_categoria as categoria,
                COUNT(DISTINCT p.id) as total_productos,
                SUM(dv.cantidad) as unidades_vendidas,
                SUM(dv.subtotal_bs) as ingresos_totales,
                SUM(CASE 
                    WHEN p.stock > 0 THEN p.stock 
                    ELSE 0 
                END) as stock_actual_total,
                
                -- Calcular rotación (ventas / stock promedio)
                CASE 
                    WHEN AVG(p.stock) > 0 
                    THEN ROUND(SUM(dv.cantidad) / AVG(p.stock), 2)
                    ELSE 0 
                END as rotacion_promedio,
                
                -- Calcular días de inventario
                CASE 
                    WHEN SUM(dv.cantidad) > 0 
                    THEN ROUND((AVG(p.stock) / (SUM(dv.cantidad) / DATEDIFF(:end_date, :start_date))) * 30, 2)
                    ELSE 999 
                END as dias_inventario,
                
                -- Calcular velocidad de venta (unidades por día)
                ROUND(SUM(dv.cantidad) / GREATEST(DATEDIFF(:end_date, :start_date), 1), 2) as velocidad_venta,
                
                -- Clasificación de rotación
                CASE 
                    WHEN (SUM(dv.cantidad) / GREATEST(AVG(p.stock), 1)) >= 3 THEN 'ALTA ROTACIÓN'
                    WHEN (SUM(dv.cantidad) / GREATEST(AVG(p.stock), 1)) >= 1.5 THEN 'ROTACIÓN MEDIA'
                    WHEN (SUM(dv.cantidad) / GREATEST(AVG(p.stock), 1)) > 0 THEN 'BAJA ROTACIÓN'
                    ELSE 'SIN ROTACIÓN'
                END as clasificacion_rotacion,
                
                -- Porcentaje de productos con stock bajo
                ROUND(SUM(CASE WHEN p.stock <= p.stock_minimo THEN 1 ELSE 0 END) * 100.0 / COUNT(p.id), 2) as porcentaje_stock_bajo,
                
                -- Producto más vendido de la categoría
                (SELECT p2.nombre 
                 FROM productos p2 
                 WHERE p2.categoria_id = cp.id 
                   AND p2.id IN (
                       SELECT dv2.id_producto 
                       FROM detalle_venta dv2 
                       INNER JOIN ventas v2 ON dv2.id_venta = v2.id_venta
                       WHERE v2.fecha BETWEEN :start_date2 AND :end_date2
                   )
                 GROUP BY p2.id 
                 ORDER BY SUM(dv3.cantidad) DESC 
                 LIMIT 1) as producto_mas_vendido,
                
                -- Última venta de la categoría
                MAX(v.fecha) as ultima_venta
                
              FROM categoria_prod cp
              LEFT JOIN productos p ON cp.id = p.categoria_id
              LEFT JOIN detalle_venta dv ON p.id = dv.id_producto
              LEFT JOIN ventas v ON dv.id_venta = v.id_venta
                AND v.fecha BETWEEN :start_date3 AND :end_date3
              WHERE cp.estado = 'active'
              GROUP BY cp.id, cp.nombre_categoria
              HAVING unidades_vendidas > 0
              ORDER BY rotacion_promedio DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date,
        'start_date2' => $start_date . ' 00:00:00',
        'end_date2' => $end_date . ' 23:59:59',
        'start_date3' => $start_date . ' 00:00:00',
        'end_date3' => $end_date . ' 23:59:59'
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener análisis de stock por categoría
function getAnalisisStockPorCategoria($pdo) {
    $query = "SELECT 
                cp.id as categoria_id,
                cp.nombre_categoria as categoria,
                COUNT(p.id) as total_productos,
                SUM(CASE WHEN p.estado = 'active' THEN 1 ELSE 0 END) as productos_activos,
                SUM(p.stock) as stock_total,
                AVG(p.stock) as stock_promedio,
                MIN(p.stock) as stock_minimo_cat,
                MAX(p.stock) as stock_maximo_cat,
                
                -- Análisis de niveles de stock
                SUM(CASE WHEN p.stock = 0 THEN 1 ELSE 0 END) as productos_sin_stock,
                SUM(CASE WHEN p.stock > 0 AND p.stock <= p.stock_minimo THEN 1 ELSE 0 END) as productos_stock_bajo,
                SUM(CASE WHEN p.stock > p.stock_minimo AND p.stock <= (p.stock_minimo * 2) THEN 1 ELSE 0 END) as productos_stock_adecuado,
                SUM(CASE WHEN p.stock > (p.stock_minimo * 2) THEN 1 ELSE 0 END) as productos_stock_alto,
                
                -- Valores monetarios (si existen las columnas)
                SUM(CASE 
                    WHEN p.precio_costo IS NOT NULL THEN p.precio_costo * p.stock
                    WHEN p.costo_promedio_bs IS NOT NULL THEN p.costo_promedio_bs * p.stock
                    WHEN p.costo_bs IS NOT NULL THEN p.costo_bs * p.stock
                    ELSE 0 
                END) as valor_inventario_costo,
                
                SUM(CASE 
                    WHEN p.precio_venta_bs IS NOT NULL THEN p.precio_venta_bs * p.stock
                    WHEN p.precio_bs IS NOT NULL THEN p.precio_bs * p.stock
                    WHEN p.precio IS NOT NULL THEN p.precio * p.stock
                    ELSE 0 
                END) as valor_inventario_venta,
                
                -- Porcentajes
                ROUND(SUM(CASE WHEN p.stock = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(p.id), 2) as porcentaje_sin_stock,
                ROUND(SUM(CASE WHEN p.stock > 0 AND p.stock <= p.stock_minimo THEN 1 ELSE 0 END) * 100.0 / COUNT(p.id), 2) as porcentaje_stock_bajo,
                
                -- Clasificación de inventario
                CASE 
                    WHEN AVG(p.stock) = 0 THEN 'INVENTARIO CRÍTICO'
                    WHEN AVG(p.stock) <= 5 THEN 'INVENTARIO BAJO'
                    WHEN AVG(p.stock) <= 20 THEN 'INVENTARIO ADECUADO'
                    ELSE 'INVENTARIO ALTO'
                END as clasificacion_inventario
                
              FROM categoria_prod cp
              LEFT JOIN productos p ON cp.id = p.categoria_id
              WHERE cp.estado = 'active'
                AND p.estado = 'active'
              GROUP BY cp.id, cp.nombre_categoria
              ORDER BY valor_inventario_costo DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener tendencia de ventas por categoría
function getTendenciaVentasCategoria($pdo, $start_date, $end_date, $categoria_id = null) {
    $params = [
        'start_date' => $start_date . ' 00:00:00',
        'end_date' => $end_date . ' 23:59:59'
    ];
    
    $categoria_where = '';
    if ($categoria_id) {
        $categoria_where = ' AND cp.id = :categoria_id';
        $params['categoria_id'] = $categoria_id;
    }
    
    $query = "SELECT 
                cp.nombre_categoria as categoria,
                DATE_FORMAT(v.fecha, '%Y-%m') as mes,
                DATE_FORMAT(v.fecha, '%M %Y') as mes_nombre,
                SUM(dv.cantidad) as unidades_vendidas,
                SUM(dv.subtotal_bs) as ingresos_mes,
                COUNT(DISTINCT v.id_venta) as facturas_mes,
                COUNT(DISTINCT p.id) as productos_vendidos_mes,
                
                -- Calcular variación mes a mes
                LAG(SUM(dv.cantidad), 1) OVER (PARTITION BY cp.id ORDER BY DATE_FORMAT(v.fecha, '%Y-%m')) as unidades_mes_anterior,
                LAG(SUM(dv.subtotal_bs), 1) OVER (PARTITION BY cp.id ORDER BY DATE_FORMAT(v.fecha, '%Y-%m')) as ingresos_mes_anterior,
                
                -- Calcular crecimiento
                CASE 
                    WHEN LAG(SUM(dv.cantidad), 1) OVER (PARTITION BY cp.id ORDER BY DATE_FORMAT(v.fecha, '%Y-%m')) > 0
                    THEN ROUND((SUM(dv.cantidad) - LAG(SUM(dv.cantidad), 1) OVER (PARTITION BY cp.id ORDER BY DATE_FORMAT(v.fecha, '%Y-%m'))) / 
                           LAG(SUM(dv.cantidad), 1) OVER (PARTITION BY cp.id ORDER BY DATE_FORMAT(v.fecha, '%Y-%m')) * 100, 2)
                    ELSE 0 
                END as crecimiento_unidades,
                
                CASE 
                    WHEN LAG(SUM(dv.subtotal_bs), 1) OVER (PARTITION BY cp.id ORDER BY DATE_FORMAT(v.fecha, '%Y-%m')) > 0
                    THEN ROUND((SUM(dv.subtotal_bs) - LAG(SUM(dv.subtotal_bs), 1) OVER (PARTITION BY cp.id ORDER BY DATE_FORMAT(v.fecha, '%Y-%m'))) / 
                           LAG(SUM(dv.subtotal_bs), 1) OVER (PARTITION BY cp.id ORDER BY DATE_FORMAT(v.fecha, '%Y-%m')) * 100, 2)
                    ELSE 0 
                END as crecimiento_ingresos
                
              FROM categoria_prod cp
              INNER JOIN productos p ON cp.id = p.categoria_id
              INNER JOIN detalle_venta dv ON p.id = dv.id_producto
              INNER JOIN ventas v ON dv.id_venta = v.id_venta
              WHERE v.fecha BETWEEN :start_date AND :end_date
                $categoria_where
              GROUP BY cp.id, cp.nombre_categoria, DATE_FORMAT(v.fecha, '%Y-%m'), DATE_FORMAT(v.fecha, '%M %Y')
              ORDER BY cp.nombre_categoria, DATE_FORMAT(v.fecha, '%Y-%m')";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener categorías (para select)
function getCategorias($pdo) {
    $query = "SELECT id, nombre_categoria 
              FROM categoria_prod 
              WHERE estado = 'active' 
              ORDER BY nombre_categoria ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener datos según el tipo de análisis
$rotacion_categorias = [];
$analisis_stock = [];
$tendencia_ventas = [];
$categorias = getCategorias($pdo);
$resumen_general = null;

if ($tipo_analisis === 'rotacion_ventas') {
    $rotacion_categorias = getRotacionPorCategoriaVentas($pdo, $start_date, $end_date);
    $resumen_general = calcularResumenRotacion($rotacion_categorias);
} elseif ($tipo_analisis === 'analisis_stock') {
    $analisis_stock = getAnalisisStockPorCategoria($pdo);
    $resumen_general = calcularResumenStock($analisis_stock);
} elseif ($tipo_analisis === 'tendencia_ventas') {
    $categoria_tendencia = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : null;
    $tendencia_ventas = getTendenciaVentasCategoria($pdo, $start_date, $end_date, $categoria_tendencia);
}

// Función para calcular resumen de rotación
function calcularResumenRotacion($datos) {
    if (empty($datos)) return null;
    
    $resumen = [
        'total_categorias' => count($datos),
        'categorias_alta_rotacion' => 0,
        'categorias_media_rotacion' => 0,
        'categorias_baja_rotacion' => 0,
        'categorias_sin_rotacion' => 0,
        'rotacion_promedio_total' => 0,
        'dias_inventario_promedio' => 0,
        'unidades_vendidas_total' => 0,
        'ingresos_totales' => 0
    ];
    
    $sum_rotacion = 0;
    $sum_dias = 0;
    
    foreach ($datos as $categoria) {
        $resumen['unidades_vendidas_total'] += $categoria['unidades_vendidas'] ?? 0;
        $resumen['ingresos_totales'] += $categoria['ingresos_totales'] ?? 0;
        $sum_rotacion += $categoria['rotacion_promedio'] ?? 0;
        
        if ($categoria['dias_inventario'] < 999) {
            $sum_dias += $categoria['dias_inventario'] ?? 0;
        }
        
        $clasificacion = $categoria['clasificacion_rotacion'] ?? '';
        switch ($clasificacion) {
            case 'ALTA ROTACIÓN':
                $resumen['categorias_alta_rotacion']++;
                break;
            case 'ROTACIÓN MEDIA':
                $resumen['categorias_media_rotacion']++;
                break;
            case 'BAJA ROTACIÓN':
                $resumen['categorias_baja_rotacion']++;
                break;
            case 'SIN ROTACIÓN':
                $resumen['categorias_sin_rotacion']++;
                break;
        }
    }
    
    if ($resumen['total_categorias'] > 0) {
        $resumen['rotacion_promedio_total'] = round($sum_rotacion / $resumen['total_categorias'], 2);
        
        $categorias_con_dias = array_filter($datos, function($cat) {
            return ($cat['dias_inventario'] ?? 999) < 999;
        });
        
        if (count($categorias_con_dias) > 0) {
            $resumen['dias_inventario_promedio'] = round($sum_dias / count($categorias_con_dias), 2);
        }
    }
    
    return $resumen;
}

// Función para calcular resumen de stock
function calcularResumenStock($datos) {
    if (empty($datos)) return null;
    
    $resumen = [
        'total_categorias' => count($datos),
        'total_productos' => 0,
        'productos_sin_stock' => 0,
        'productos_stock_bajo' => 0,
        'productos_stock_adecuado' => 0,
        'productos_stock_alto' => 0,
        'valor_inventario_total_costo' => 0,
        'valor_inventario_total_venta' => 0,
        'stock_total' => 0
    ];
    
    foreach ($datos as $categoria) {
        $resumen['total_productos'] += $categoria['total_productos'] ?? 0;
        $resumen['productos_sin_stock'] += $categoria['productos_sin_stock'] ?? 0;
        $resumen['productos_stock_bajo'] += $categoria['productos_stock_bajo'] ?? 0;
        $resumen['productos_stock_adecuado'] += $categoria['productos_stock_adecuado'] ?? 0;
        $resumen['productos_stock_alto'] += $categoria['productos_stock_alto'] ?? 0;
        $resumen['valor_inventario_total_costo'] += $categoria['valor_inventario_costo'] ?? 0;
        $resumen['valor_inventario_total_venta'] += $categoria['valor_inventario_venta'] ?? 0;
        $resumen['stock_total'] += $categoria['stock_total'] ?? 0;
    }
    
    return $resumen;
}

// Meses en español
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
    <title>Reporte de Rotación de Inventario por Categoría</title>
    <link rel="stylesheet" href="css/reportes.css">
    <link rel="stylesheet" href="css/reportes_rentabilidad.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Estilos específicos para rotación de inventario */
        .indicador-rotacion {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .rotacion-alta {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .rotacion-media {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .rotacion-baja {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .rotacion-sin {
            background: rgba(108, 117, 125, 0.15);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }
        
        .inventario-critico {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .inventario-bajo {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .inventario-adecuado {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .inventario-alto {
            background: rgba(0, 123, 255, 0.15);
            color: #007bff;
            border: 1px solid rgba(0, 123, 255, 0.3);
        }
        
        .barra-progreso {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        
        .barra-progreso-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #008B8B;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .info-box h4 {
            margin-top: 0;
            color: #008B8B;
        }
        
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }
        
        .crecimiento-positivo {
            color: #28a745;
            font-weight: bold;
        }
        
        .crecimiento-negativo {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Reporte de Rotación de Inventario por Categoría</h1>
        </div>
        
        <!-- Información del reporte -->
        <div class="info-box">
            <h4>¿Qué es la Rotación de Inventario?</h4>
            <p>La rotación de inventario mide cuántas veces se vende y reemplaza el inventario en un período determinado.</p>
            <ul>
                <li><strong>Alta Rotación (>3):</strong> Productos que se venden rápidamente</li>
                <li><strong>Rotación Media (1.5-3):</strong> Ventas estables</li>
                <li><strong>Baja Rotación (0-1.5):</strong> Productos de movimiento lento</li>
                <li><strong>Días de Inventario:</strong> Cuántos días dura el stock actual</li>
            </ul>
        </div>

        <!-- Filtros -->
        <div class="filtros-container">
            <div class="filtros-card">
                <h3>Filtrar Reporte de Rotación</h3>
                <form method="GET" class="filtros-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Análisis:</label>
                            <select name="tipo_analisis" class="form-select" id="tipo-analisis">
                                <option value="rotacion_ventas" <?= $tipo_analisis === 'rotacion_ventas' ? 'selected' : '' ?>>Rotación por Ventas</option>
                                <option value="analisis_stock" <?= $tipo_analisis === 'analisis_stock' ? 'selected' : '' ?>>Análisis de Stock por Categoría</option>
                                <option value="tendencia_ventas" <?= $tipo_analisis === 'tendencia_ventas' ? 'selected' : '' ?>>Tendencia de Ventas por Categoría</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="filtro-fechas">
                            <label>Fecha Inicio:</label>
                            <input type="date" name="start_date" class="form-input" value="<?= $start_date ?>">
                        </div>
                        
                        <div class="form-group" id="filtro-fechas-fin">
                            <label>Fecha Fin:</label>
                            <input type="date" name="end_date" class="form-input" value="<?= $end_date ?>">
                        </div>
                        
                        <div class="form-group" id="filtro-categoria" style="display: <?= $tipo_analisis === 'tendencia_ventas' ? 'block' : 'none' ?>;">
                            <label>Categoría (Opcional):</label>
                            <select name="categoria_id" class="form-select">
                                <option value="0">Todas las Categorías</option>
                                <?php foreach($categorias as $categoria): ?>
                                    <option value="<?= $categoria['id'] ?>" <?= (isset($_GET['categoria_id']) && $_GET['categoria_id'] == $categoria['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categoria['nombre_categoria']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Ordenar por:</label>
                            <select name="ordenar_por" class="form-select">
                                <option value="rotacion_promedio" <?= $ordenar_por === 'rotacion_promedio' ? 'selected' : '' ?>>Rotación Promedio</option>
                                <option value="unidades_vendidas" <?= $ordenar_por === 'unidades_vendidas' ? 'selected' : '' ?>>Unidades Vendidas</option>
                                <option value="ingresos_totales" <?= $ordenar_por === 'ingresos_totales' ? 'selected' : '' ?>>Ingresos Totales</option>
                                <option value="dias_inventario" <?= $ordenar_por === 'dias_inventario' ? 'selected' : '' ?>>Días de Inventario</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Límite de Resultados:</label>
                            <select name="limite" class="form-select">
                                <option value="10" <?= $limite_resultados === 10 ? 'selected' : '' ?>>Top 10</option>
                                <option value="20" <?= $limite_resultados === 20 ? 'selected' : '' ?>>Top 20</option>
                                <option value="50" <?= $limite_resultados === 50 ? 'selected' : '' ?>>Top 50</option>
                                <option value="100" <?= $limite_resultados === 100 ? 'selected' : '' ?>>Mostrar Todos</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="align-self: flex-end;">
                            <button type="submit" class="btn-generar">
                                Generar Reporte
                            </button>
                            <button type="button" class="btn-generar" onclick="exportarExcel()" style="background: #28a745; margin-left: 10px;">
                                Exportar Excel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resumen General -->
        <?php if($resumen_general): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Resumen de Rotación de Inventario</h2>
                <div class="periodo-info">
                    <span><?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></span>
                </div>
            </div>
            
            <div class="estadisticas-grid">
                <?php if($tipo_analisis === 'rotacion_ventas'): ?>
                <div class="estadistica-card">
                    <div class="estadistica-label">Categorías Analizadas</div>
                    <div class="estadistica-value"><?= $resumen_general['total_categorias'] ?? 0 ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Rotación Promedio</div>
                    <div class="estadistica-value" style="color: 
                        <?= ($resumen_general['rotacion_promedio_total'] ?? 0) >= 3 ? '#28a745' : 
                           (($resumen_general['rotacion_promedio_total'] ?? 0) >= 1.5 ? '#ffc107' : '#dc3545') ?>;">
                        <?= number_format($resumen_general['rotacion_promedio_total'] ?? 0, 2, ',', '.') ?>
                    </div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Días de Inventario Prom.</div>
                    <div class="estadistica-value" style="color: 
                        <?= ($resumen_general['dias_inventario_promedio'] ?? 0) <= 30 ? '#28a745' : 
                           (($resumen_general['dias_inventario_promedio'] ?? 0) <= 60 ? '#ffc107' : '#dc3545') ?>;">
                        <?= number_format($resumen_general['dias_inventario_promedio'] ?? 0, 2, ',', '.') ?> días
                    </div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Unidades Vendidas</div>
                    <div class="estadistica-value"><?= number_format($resumen_general['unidades_vendidas_total'] ?? 0, 0, ',', '.') ?></div>
                </div>
                
                <?php elseif($tipo_analisis === 'analisis_stock'): ?>
                <div class="estadistica-card">
                    <div class="estadistica-label">Categorías Analizadas</div>
                    <div class="estadistica-value"><?= $resumen_general['total_categorias'] ?? 0 ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Productos Totales</div>
                    <div class="estadistica-value"><?= number_format($resumen_general['total_productos'] ?? 0, 0, ',', '.') ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Stock Total</div>
                    <div class="estadistica-value"><?= number_format($resumen_general['stock_total'] ?? 0, 0, ',', '.') ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Valor Inventario (Costo)</div>
                    <div class="estadistica-value">Bs. <?= number_format($resumen_general['valor_inventario_total_costo'] ?? 0, 2, ',', '.') ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Distribución de Rotación -->
            <?php if($tipo_analisis === 'rotacion_ventas'): ?>
            <div class="tabla-container">
                <h3>Distribución de Categorías por Nivel de Rotación</h3>
                <div class="metodos-grid">
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));">
                        <div class="metodo-nombre" style="color: #28a745;">Alta Rotación</div>
                        <div class="metodo-cantidad"><?= $resumen_general['categorias_alta_rotacion'] ?? 0 ?> categorías</div>
                        <div class="metodo-total" style="color: #28a745;">
                            <?= $resumen_general['total_categorias'] > 0 ? number_format(($resumen_general['categorias_alta_rotacion'] ?? 0) / $resumen_general['total_categorias'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                    </div>
                    
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05));">
                        <div class="metodo-nombre" style="color: #ffc107;">Rotación Media</div>
                        <div class="metodo-cantidad"><?= $resumen_general['categorias_media_rotacion'] ?? 0 ?> categorías</div>
                        <div class="metodo-total" style="color: #ffc107;">
                            <?= $resumen_general['total_categorias'] > 0 ? number_format(($resumen_general['categorias_media_rotacion'] ?? 0) / $resumen_general['total_categorias'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                    </div>
                    
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));">
                        <div class="metodo-nombre" style="color: #dc3545;">Baja Rotación</div>
                        <div class="metodo-cantidad"><?= $resumen_general['categorias_baja_rotacion'] ?? 0 ?> categorías</div>
                        <div class="metodo-total" style="color: #dc3545;">
                            <?= $resumen_general['total_categorias'] > 0 ? number_format(($resumen_general['categorias_baja_rotacion'] ?? 0) / $resumen_general['total_categorias'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                    </div>
                    
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(108, 117, 125, 0.1), rgba(108, 117, 125, 0.05));">
                        <div class="metodo-nombre" style="color: #6c757d;">Sin Rotación</div>
                        <div class="metodo-cantidad"><?= $resumen_general['categorias_sin_rotacion'] ?? 0 ?> categorías</div>
                        <div class="metodo-total" style="color: #6c757d;">
                            <?= $resumen_general['total_categorias'] > 0 ? number_format(($resumen_general['categorias_sin_rotacion'] ?? 0) / $resumen_general['total_categorias'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Reporte de Rotación por Ventas -->
        <?php if($tipo_analisis === 'rotacion_ventas'): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Rotación de Inventario por Categoría</h2>
                <div class="periodo-info">
                    <span>Análisis basado en ventas del período</span>
                </div>
            </div>
            
            <!-- Tabla de Rotación -->
            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th>Productos</th>
                                <th>Unidades Vendidas</th>
                                <th>Ingresos (Bs)</th>
                                <th>Stock Actual</th>
                                <th>Rotación</th>
                                <th>Días Inventario</th>
                                <th>Velocidad Venta/día</th>
                                <th>% Stock Bajo</th>
                                <th>Producto Más Vendido</th>
                                <th>Clasificación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($rotacion_categorias)): ?>
                                <tr>
                                    <td colspan="11" class="empty-state">No hay datos de rotación para el período seleccionado</td>
                                </tr>
                            <?php else: 
                                // Ordenar datos según el filtro
                                $datos_ordenados = $rotacion_categorias;
                                usort($datos_ordenados, function($a, $b) use ($ordenar_por) {
                                    return ($b[$ordenar_por] ?? 0) <=> ($a[$ordenar_por] ?? 0);
                                });
                                
                                // Limitar resultados
                                $datos_mostrar = array_slice($datos_ordenados, 0, $limite_resultados);
                                
                                foreach($datos_mostrar as $categoria): 
                                    $clasificacion = $categoria['clasificacion_rotacion'] ?? '';
                                    $clase_rotacion = '';
                                    
                                    switch($clasificacion) {
                                        case 'ALTA ROTACIÓN':
                                            $clase_rotacion = 'rotacion-alta';
                                            break;
                                        case 'ROTACIÓN MEDIA':
                                            $clase_rotacion = 'rotacion-media';
                                            break;
                                        case 'BAJA ROTACIÓN':
                                            $clase_rotacion = 'rotacion-baja';
                                            break;
                                        case 'SIN ROTACIÓN':
                                            $clase_rotacion = 'rotacion-sin';
                                            break;
                                    }
                                    
                                    $dias_inventario = $categoria['dias_inventario'] ?? 999;
                                    $color_dias = $dias_inventario <= 30 ? '#28a745' : 
                                                ($dias_inventario <= 60 ? '#ffc107' : '#dc3545');
                                    
                                    $porcentaje_stock_bajo = $categoria['porcentaje_stock_bajo'] ?? 0;
                                    $color_stock_bajo = $porcentaje_stock_bajo > 50 ? '#dc3545' : 
                                                      ($porcentaje_stock_bajo > 20 ? '#ffc107' : '#28a745');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($categoria['categoria'] ?? '') ?></strong></td>
                                <td><?= $categoria['total_productos'] ?? 0 ?></td>
                                <td><?= number_format($categoria['unidades_vendidas'] ?? 0, 0, ',', '.') ?></td>
                                <td>Bs. <?= number_format($categoria['ingresos_totales'] ?? 0, 2, ',', '.') ?></td>
                                <td><?= number_format($categoria['stock_actual_total'] ?? 0, 0, ',', '.') ?></td>
                                <td>
                                    <strong><?= number_format($categoria['rotacion_promedio'] ?? 0, 2, ',', '.') ?></strong>
                                    <div class="barra-progreso">
                                        <div class="barra-progreso-fill" style="width: <?= min(($categoria['rotacion_promedio'] ?? 0) * 20, 100) ?>%; 
                                            background: <?= ($categoria['rotacion_promedio'] ?? 0) >= 3 ? '#28a745' : 
                                                         (($categoria['rotacion_promedio'] ?? 0) >= 1.5 ? '#ffc107' : '#dc3545') ?>;">
                                        </div>
                                    </div>
                                </td>
                                <td style="color: <?= $color_dias ?>; font-weight: bold;">
                                    <?= $dias_inventario < 999 ? number_format($dias_inventario, 1, ',', '.') . ' días' : 'Sin stock' ?>
                                </td>
                                <td><?= number_format($categoria['velocidad_venta'] ?? 0, 2, ',', '.') ?></td>
                                <td style="color: <?= $color_stock_bajo ?>; font-weight: bold;">
                                    <?= number_format($porcentaje_stock_bajo, 1, ',', '.') ?>%
                                </td>
                                <td><?= htmlspecialchars(substr($categoria['producto_mas_vendido'] ?? 'N/A', 0, 30)) ?></td>
                                <td>
                                    <span class="indicador-rotacion <?= $clase_rotacion ?>">
                                        <?= $clasificacion ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Gráfico de rotación -->
            <div class="tabla-container">
                <h3>Top 10 Categorías por Rotación de Inventario</h3>
                <div class="grafico-container">
                    <canvas id="graficoRotacionCategorias"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reporte de Análisis de Stock -->
        <?php if($tipo_analisis === 'analisis_stock'): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Análisis de Stock por Categoría</h2>
                <div class="periodo-info">
                    <span>Estado actual del inventario</span>
                </div>
            </div>
            
            <!-- Tabla de Análisis de Stock -->
            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th>Productos</th>
                                <th>Activos</th>
                                <th>Stock Total</th>
                                <th>Stock Promedio</th>
                                <th>Sin Stock</th>
                                <th>Stock Bajo</th>
                                <th>Stock Adecuado</th>
                                <th>Stock Alto</th>
                                <th>Valor Costo (Bs)</th>
                                <th>Valor Venta (Bs)</th>
                                <th>Clasificación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($analisis_stock)): ?>
                                <tr>
                                    <td colspan="12" class="empty-state">No hay datos de stock disponibles</td>
                                </tr>
                            <?php else: 
                                // Ordenar datos según el filtro
                                $datos_ordenados = $analisis_stock;
                                usort($datos_ordenados, function($a, $b) use ($ordenar_por) {
                                    return ($b[$ordenar_por] ?? 0) <=> ($a[$ordenar_por] ?? 0);
                                });
                                
                                // Limitar resultados
                                $datos_mostrar = array_slice($datos_ordenados, 0, $limite_resultados);
                                
                                foreach($datos_mostrar as $categoria): 
                                    $clasificacion = $categoria['clasificacion_inventario'] ?? '';
                                    $clase_inventario = '';
                                    
                                    switch($clasificacion) {
                                        case 'INVENTARIO CRÍTICO':
                                            $clase_inventario = 'inventario-critico';
                                            break;
                                        case 'INVENTARIO BAJO':
                                            $clase_inventario = 'inventario-bajo';
                                            break;
                                        case 'INVENTARIO ADECUADO':
                                            $clase_inventario = 'inventario-adecuado';
                                            break;
                                        case 'INVENTARIO ALTO':
                                            $clase_inventario = 'inventario-alto';
                                            break;
                                    }
                                    
                                    $porcentaje_sin_stock = $categoria['porcentaje_sin_stock'] ?? 0;
                                    $porcentaje_stock_bajo = $categoria['porcentaje_stock_bajo'] ?? 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($categoria['categoria'] ?? '') ?></strong></td>
                                <td><?= $categoria['total_productos'] ?? 0 ?></td>
                                <td><?= $categoria['productos_activos'] ?? 0 ?></td>
                                <td><?= number_format($categoria['stock_total'] ?? 0, 0, ',', '.') ?></td>
                                <td><?= number_format($categoria['stock_promedio'] ?? 0, 1, ',', '.') ?></td>
                                <td style="color: <?= $porcentaje_sin_stock > 20 ? '#dc3545' : '#6c757d' ?>;">
                                    <?= $categoria['productos_sin_stock'] ?? 0 ?>
                                    <small>(<?= number_format($porcentaje_sin_stock, 1, ',', '.') ?>%)</small>
                                </td>
                                <td style="color: <?= $porcentaje_stock_bajo > 30 ? '#ffc107' : '#6c757d' ?>;">
                                    <?= $categoria['productos_stock_bajo'] ?? 0 ?>
                                    <small>(<?= number_format($porcentaje_stock_bajo, 1, ',', '.') ?>%)</small>
                                </td>
                                <td style="color: #28a745;">
                                    <?= $categoria['productos_stock_adecuado'] ?? 0 ?>
                                </td>
                                <td style="color: #007bff;">
                                    <?= $categoria['productos_stock_alto'] ?? 0 ?>
                                </td>
                                <td>Bs. <?= number_format($categoria['valor_inventario_costo'] ?? 0, 2, ',', '.') ?></td>
                                <td>Bs. <?= number_format($categoria['valor_inventario_venta'] ?? 0, 2, ',', '.') ?></td>
                                <td>
                                    <span class="indicador-rotacion <?= $clase_inventario ?>">
                                        <?= $clasificacion ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Gráfico de distribución de stock -->
            <div class="tabla-container">
                <h3>Distribución de Stock por Categoría</h3>
                <div class="grafico-container">
                    <canvas id="graficoDistribucionStock"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reporte de Tendencia de Ventas -->
        <?php if($tipo_analisis === 'tendencia_ventas'): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Tendencia de Ventas por Categoría</h2>
                <div class="periodo-info">
                    <span>Análisis mensual de ventas</span>
                </div>
            </div>
            
            <!-- Tabla de Tendencia -->
            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th>Categoría</th>
                                <th>Unidades Vendidas</th>
                                <th>Ingresos (Bs)</th>
                                <th>Facturas</th>
                                <th>Productos Vendidos</th>
                                <th>Crecimiento Unidades</th>
                                <th>Crecimiento Ingresos</th>
                                <th>Tendencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($tendencia_ventas)): ?>
                                <tr>
                                    <td colspan="9" class="empty-state">No hay datos de tendencia para el período seleccionado</td>
                                </tr>
                            <?php else: 
                                foreach($tendencia_ventas as $tendencia): 
                                    $crecimiento_unidades = $tendencia['crecimiento_unidades'] ?? 0;
                                    $crecimiento_ingresos = $tendencia['crecimiento_ingresos'] ?? 0;
                                    
                                    $clase_crecimiento_unidades = $crecimiento_unidades > 0 ? 'crecimiento-positivo' : 
                                                                 ($crecimiento_unidades < 0 ? 'crecimiento-negativo' : '');
                                    $clase_crecimiento_ingresos = $crecimiento_ingresos > 0 ? 'crecimiento-positivo' : 
                                                                  ($crecimiento_ingresos < 0 ? 'crecimiento-negativo' : '');
                                    
                                    $tendencia_general = ($crecimiento_unidades + $crecimiento_ingresos) / 2;
                                    $color_tendencia = $tendencia_general > 10 ? '#28a745' : 
                                                      ($tendencia_general > 0 ? '#ffc107' : 
                                                      ($tendencia_general < -10 ? '#dc3545' : '#6c757d'));
                            ?>
                            <tr>
                                <td><?= $tendencia['mes_nombre'] ?? '' ?></td>
                                <td><strong><?= htmlspecialchars($tendencia['categoria'] ?? '') ?></strong></td>
                                <td><?= number_format($tendencia['unidades_vendidas'] ?? 0, 0, ',', '.') ?></td>
                                <td>Bs. <?= number_format($tendencia['ingresos_mes'] ?? 0, 2, ',', '.') ?></td>
                                <td><?= $tendencia['facturas_mes'] ?? 0 ?></td>
                                <td><?= $tendencia['productos_vendidos_mes'] ?? 0 ?></td>
                                <td class="<?= $clase_crecimiento_unidades ?>">
                                    <?= number_format($crecimiento_unidades, 1, ',', '.') ?>%
                                </td>
                                <td class="<?= $clase_crecimiento_ingresos ?>">
                                    <?= number_format($crecimiento_ingresos, 1, ',', '.') ?>%
                                </td>
                                <td style="color: <?= $color_tendencia ?>; font-weight: bold;">
                                    <?= $tendencia_general > 10 ? '📈 Alta' : 
                                        ($tendencia_general > 0 ? '↗️ Media' : 
                                        ($tendencia_general < -10 ? '📉 Baja' : '➡️ Estable')) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Gráfico de tendencia -->
            <div class="tabla-container">
                <h3>Evolución de Ventas por Categoría</h3>
                <div class="grafico-container">
                    <canvas id="graficoTendenciaVentas"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Mostrar/ocultar filtros según tipo de análisis
$(document).ready(function() {
    $('#tipo-analisis').change(function() {
        const tipo = $(this).val();
        
        // Mostrar/ocultar filtro de categoría
        if (tipo === 'tendencia_ventas') {
            $('#filtro-categoria').show();
        } else {
            $('#filtro-categoria').hide();
        }
        
        // Mostrar/ocultar filtros de fecha para análisis de stock
        if (tipo === 'analisis_stock') {
            $('#filtro-fechas').hide();
            $('#filtro-fechas-fin').hide();
        } else {
            $('#filtro-fechas').show();
            $('#filtro-fechas-fin').show();
        }
    });
    
    // Inicializar estado
    $('#tipo-analisis').trigger('change');
    
    // Generar gráficos si existen
    generarGraficosRotacion();
});

// Función para exportar a Excel
function exportarExcel() {
    const tipo = $('#tipo-analisis').val();
    const startDate = $('input[name="start_date"]').val();
    const endDate = $('input[name="end_date"]').val();
    
    let url = `exportar_rotacion_excel.php?tipo_analisis=${tipo}&start_date=${startDate}&end_date=${endDate}`;
    
    // Agregar parámetros adicionales según el tipo de análisis
    if (tipo === 'tendencia_ventas') {
        const categoriaId = $('select[name="categoria_id"]').val();
        if (categoriaId > 0) {
            url += `&categoria_id=${categoriaId}`;
        }
    }
    
    window.open(url, '_blank');
}

// Generar gráficos de rotación
function generarGraficosRotacion() {
    <?php if($tipo_analisis === 'rotacion_ventas' && !empty($rotacion_categorias)): ?>
    // Gráfico para rotación por categoría
    const ctx1 = document.getElementById('graficoRotacionCategorias');
    if (ctx1) {
        const top10Categorias = <?= json_encode(array_slice($rotacion_categorias, 0, 10)) ?>;
        
        new Chart(ctx1.getContext('2d'), {
            type: 'bar',
            data: {
                labels: top10Categorias.map(c => c.categoria.substring(0, 15) + (c.categoria.length > 15 ? '...' : '')),
                datasets: [
                    {
                        label: 'Rotación',
                        data: top10Categorias.map(c => parseFloat(c.rotacion_promedio)),
                        backgroundColor: top10Categorias.map(c => {
                            const rotacion = parseFloat(c.rotacion_promedio);
                            return rotacion >= 3 ? 'rgba(40, 167, 69, 0.7)' : 
                                   rotacion >= 1.5 ? 'rgba(255, 193, 7, 0.7)' : 
                                   'rgba(220, 53, 69, 0.7)';
                        }),
                        borderColor: top10Categorias.map(c => {
                            const rotacion = parseFloat(c.rotacion_promedio);
                            return rotacion >= 3 ? '#28a745' : 
                                   rotacion >= 1.5 ? '#ffc107' : 
                                   '#dc3545';
                        }),
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Días Inventario',
                        data: top10Categorias.map(c => {
                            const dias = parseFloat(c.dias_inventario);
                            return dias < 999 ? dias : 0;
                        }),
                        backgroundColor: 'rgba(0, 139, 139, 0.7)',
                        borderColor: '#008B8B',
                        borderWidth: 1,
                        yAxisID: 'y1',
                        type: 'line'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Top 10 Categorías por Rotación de Inventario'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Rotación'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Días de Inventario'
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        suggestedMin: 0
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    <?php if($tipo_analisis === 'analisis_stock' && !empty($analisis_stock)): ?>
    // Gráfico para distribución de stock
    const ctx2 = document.getElementById('graficoDistribucionStock');
    if (ctx2) {
        const top10Stock = <?= json_encode(array_slice($analisis_stock, 0, 10)) ?>;
        
        // Preparar datos para gráfico de barras apiladas
        const datasets = [
            {
                label: 'Sin Stock',
                data: top10Stock.map(c => c.productos_sin_stock || 0),
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                borderColor: '#dc3545',
                borderWidth: 1
            },
            {
                label: 'Stock Bajo',
                data: top10Stock.map(c => c.productos_stock_bajo || 0),
                backgroundColor: 'rgba(255, 193, 7, 0.7)',
                borderColor: '#ffc107',
                borderWidth: 1
            },
            {
                label: 'Stock Adecuado',
                data: top10Stock.map(c => c.productos_stock_adecuado || 0),
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: '#28a745',
                borderWidth: 1
            },
            {
                label: 'Stock Alto',
                data: top10Stock.map(c => c.productos_stock_alto || 0),
                backgroundColor: 'rgba(0, 123, 255, 0.7)',
                borderColor: '#007bff',
                borderWidth: 1
            }
        ];
        
        new Chart(ctx2.getContext('2d'), {
            type: 'bar',
            data: {
                labels: top10Stock.map(c => c.categoria.substring(0, 15) + (c.categoria.length > 15 ? '...' : '')),
                datasets: datasets
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribución de Niveles de Stock por Categoría'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        title: {
                            display: true,
                            text: 'Cantidad de Productos'
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    <?php if($tipo_analisis === 'tendencia_ventas' && !empty($tendencia_ventas)): ?>
    // Gráfico para tendencia de ventas
    const ctx3 = document.getElementById('graficoTendenciaVentas');
    if (ctx3) {
        // Agrupar datos por mes para múltiples categorías
        const datosPorMes = {};
        <?php 
        $categorias_unicas = [];
        $meses_unicos = [];
        foreach($tendencia_ventas as $item) {
            $categoria = $item['categoria'];
            $mes = $item['mes_nombre'];
            if(!in_array($categoria, $categorias_unicas)) {
                $categorias_unicas[] = $categoria;
            }
            if(!in_array($mes, $meses_unicos)) {
                $meses_unicos[] = $mes;
            }
        }
        ?>
        
        const categorias = <?= json_encode($categorias_unicas) ?>;
        const meses = <?= json_encode($meses_unicos) ?>;
        
        // Crear dataset para cada categoría
        const datasets = categorias.map((categoria, index) => {
            const colores = [
                'rgba(0, 139, 139, 0.7)',
                'rgba(40, 167, 69, 0.7)',
                'rgba(255, 193, 7, 0.7)',
                'rgba(220, 53, 69, 0.7)',
                'rgba(111, 66, 193, 0.7)',
                'rgba(253, 126, 20, 0.7)'
            ];
            
            const color = colores[index % colores.length];
            
            // Obtener datos para esta categoría
            const datos = meses.map(mes => {
                const item = <?= json_encode($tendencia_ventas) ?>.find(t => 
                    t.categoria === categoria && t.mes_nombre === mes
                );
                return item ? parseFloat(item.ingresos_mes) : 0;
            });
            
            return {
                label: categoria,
                data: datos,
                backgroundColor: color,
                borderColor: color.replace('0.7', '1'),
                borderWidth: 2,
                fill: false,
                tension: 0.4
            };
        });
        
        new Chart(ctx3.getContext('2d'), {
            type: 'line',
            data: {
                labels: meses,
                datasets: datasets
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Evolución de Ventas por Categoría'
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Ingresos (Bs)'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }
    <?php endif; ?>
}
</script>
</body>
</html>