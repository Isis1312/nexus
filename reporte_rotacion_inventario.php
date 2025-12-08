<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'menu.php';
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

if (!$sistemaPermisos->puedeVer('reportes')) {
    header('Location: inicio.php');
    exit();
}

// Basado en tu BD, sabemos que la columna de stock es 'cantidad'
$columna_stock = 'cantidad';
$columna_stock_minimo = '0'; // No existe columna de stock mínimo en tu BD

$current_year = date('Y');
$current_month = date('m');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$tipo_analisis = isset($_GET['tipo_analisis']) ? $_GET['tipo_analisis'] : 'rotacion_ventas';
$limite_resultados = isset($_GET['limite']) ? intval($_GET['limite']) : 20;
$ordenar_por = isset($_GET['ordenar_por']) ? $_GET['ordenar_por'] : 'rotacion_promedio';

function getRotacionPorCategoriaVentas($pdo, $start_date, $end_date, $columna_stock) {
    $query = "SELECT 
                cp.id as categoria_id,
                cp.nombre_categoria as categoria,
                COUNT(DISTINCT p.id) as total_productos,
                SUM(dv.cantidad) as unidades_vendidas,
                SUM(dv.subtotal_bs) as ingresos_totales,
                SUM(CASE 
                    WHEN p.cantidad > 0 THEN p.cantidad
                    ELSE 0 
                END) as stock_actual_total,
                
                CASE 
                    WHEN AVG(p.cantidad) > 0 
                    THEN ROUND(SUM(dv.cantidad) / AVG(p.cantidad), 2)
                    ELSE 0 
                END as rotacion_promedio,
                
                CASE 
                    WHEN SUM(dv.cantidad) > 0 
                    THEN ROUND((AVG(p.cantidad) / (SUM(dv.cantidad) / GREATEST(DATEDIFF(:end_date_dias, :start_date_dias), 1))) * 30, 2)
                    ELSE 999 
                END as dias_inventario,
                
                ROUND(SUM(dv.cantidad) / GREATEST(DATEDIFF(:end_date_vel, :start_date_vel), 1), 2) as velocidad_venta,
                
                CASE 
                    WHEN (SUM(dv.cantidad) / GREATEST(AVG(p.cantidad), 1)) >= 3 THEN 'ALTA ROTACIÓN'
                    WHEN (SUM(dv.cantidad) / GREATEST(AVG(p.cantidad), 1)) >= 1.5 THEN 'ROTACIÓN MEDIA'
                    WHEN (SUM(dv.cantidad) / GREATEST(AVG(p.cantidad), 1)) > 0 THEN 'BAJA ROTACIÓN'
                    ELSE 'SIN ROTACIÓN'
                END as clasificacion_rotacion,
                
                0 as porcentaje_stock_bajo, -- No hay columna de mínimo en tu BD
                
                (SELECT p2.nombre 
                 FROM productos p2 
                 INNER JOIN detalle_venta dv_prod ON p2.id = dv_prod.id_producto 
                 INNER JOIN ventas v_prod ON dv_prod.id_venta = v_prod.id_venta
                 WHERE p2.categoria_id = cp.id 
                   AND v_prod.fecha BETWEEN :start_date2 AND :end_date2
                 GROUP BY p2.id, p2.nombre 
                 ORDER BY SUM(dv_prod.cantidad) DESC 
                 LIMIT 1) as producto_mas_vendido,
                
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
        'start_date_dias' => $start_date,
        'end_date_dias' => $end_date,
        'start_date_vel' => $start_date,
        'end_date_vel' => $end_date,
        'start_date2' => $start_date . ' 00:00:00',
        'end_date2' => $end_date . ' 23:59:59',
        'start_date3' => $start_date . ' 00:00:00',
        'end_date3' => $end_date . ' 23:59:59'
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAnalisisStockPorCategoria($pdo) {
    $query = "SELECT 
                cp.id as categoria_id,
                cp.nombre_categoria as categoria,
                COUNT(p.id) as total_productos,
                SUM(CASE WHEN p.estado = 'active' THEN 1 ELSE 0 END) as productos_activos,
                SUM(p.cantidad) as stock_total,
                AVG(p.cantidad) as stock_promedio,
                MIN(p.cantidad) as stock_minimo_cat,
                MAX(p.cantidad) as stock_maximo_cat,
                
                SUM(CASE WHEN p.cantidad = 0 THEN 1 ELSE 0 END) as productos_sin_stock,
                -- Como no hay stock mínimo, consideramos stock bajo si es menor a 10 unidades
                SUM(CASE WHEN p.cantidad > 0 AND p.cantidad <= 10 THEN 1 ELSE 0 END) as productos_stock_bajo,
                SUM(CASE WHEN p.cantidad > 10 AND p.cantidad <= 50 THEN 1 ELSE 0 END) as productos_stock_adecuado,
                SUM(CASE WHEN p.cantidad > 50 THEN 1 ELSE 0 END) as productos_stock_alto,
                
                -- Usar precio_costo para calcular valor del inventario
                SUM(COALESCE(p.precio_costo, 0) * p.cantidad) as valor_inventario_costo,
                
                -- Usar precio_venta para calcular valor de venta
                SUM(COALESCE(p.precio_venta, 0) * p.cantidad) as valor_inventario_venta,
                
                ROUND(SUM(CASE WHEN p.cantidad = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(p.id), 2) as porcentaje_sin_stock,
                ROUND(SUM(CASE WHEN p.cantidad > 0 AND p.cantidad <= 10 THEN 1 ELSE 0 END) * 100.0 / COUNT(p.id), 2) as porcentaje_stock_bajo,
                
                CASE 
                    WHEN AVG(p.cantidad) = 0 THEN 'INVENTARIO CRÍTICO'
                    WHEN AVG(p.cantidad) <= 5 THEN 'INVENTARIO BAJO'
                    WHEN AVG(p.cantidad) <= 20 THEN 'INVENTARIO ADECUADO'
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
                
                LAG(SUM(dv.cantidad), 1) OVER (PARTITION BY cp.id ORDER BY DATE_FORMAT(v.fecha, '%Y-%m')) as unidades_mes_anterior,
                LAG(SUM(dv.subtotal_bs), 1) OVER (PARTITION BY cp.id ORDER BY DATE_FORMAT(v.fecha, '%Y-%m')) as ingresos_mes_anterior,
                
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

function getCategorias($pdo) {
    $query = "SELECT id, nombre_categoria 
              FROM categoria_prod 
              WHERE estado = 'active' 
              ORDER BY nombre_categoria ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$rotacion_categorias = [];
$analisis_stock = [];
$tendencia_ventas = [];
$categorias = getCategorias($pdo);
$resumen_general = null;

if ($tipo_analisis === 'rotacion_ventas') {
    $rotacion_categorias = getRotacionPorCategoriaVentas($pdo, $start_date, $end_date, $columna_stock);
    $resumen_general = calcularResumenRotacion($rotacion_categorias);
} elseif ($tipo_analisis === 'analisis_stock') {
    $analisis_stock = getAnalisisStockPorCategoria($pdo);
    $resumen_general = calcularResumenStock($analisis_stock);
} elseif ($tipo_analisis === 'tendencia_ventas') {
    $categoria_tendencia = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : null;
    $tendencia_ventas = getTendenciaVentasCategoria($pdo, $start_date, $end_date, $categoria_tendencia);
}

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Rotación de Inventario por Categoría</title>
    <link rel="stylesheet" href="css/reportes/repo_rotacion.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .info-alert {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .info-alert strong {
            color: #1976d2;
        }
    </style>
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Reporte de Rotación de Inventario por Categoría</h1>
                   <a href="reportes.php" class="volver-button"> Volver</a>
        </div>
        
        <div class="info-rotacion">
            <h4>¿Qué es la Rotación de Inventario?</h4>
            <p>La rotación de inventario mide cuántas veces se vende y reemplaza el inventario en un período determinado.</p>
            <ul>
                <li><strong>Alta Rotación (>3):</strong> Productos que se venden rápidamente</li>
                <li><strong>Rotación Media (1.5-3):</strong> Ventas estables</li>
                <li><strong>Baja Rotación (0-1.5):</strong> Productos de movimiento lento</li>
                <li><strong>Días de Inventario:</strong> Cuántos días dura el stock actual</li>
            </ul>
            <div class="info-alert">
                <strong>Nota:</strong> El sistema está usando la columna <strong>"cantidad"</strong> para calcular stock. 
                Los valores de inventario se calculan usando <strong>precio_costo</strong> para costo y <strong>precio_venta</strong> para valor de venta.
            </div>
        </div>

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
                            <button type="submit" class="btn-rotacion">
                                <span class="btn-icon">📊</span> Generar Reporte
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if($resumen_general): ?>
        <div class="rotacion-container">
            <div class="rotacion-header">
                <h2>Resumen de Rotación de Inventario</h2>
                <div class="rotacion-periodo">
                    <span><?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></span>
                </div>
            </div>
            
            <div class="estadisticas-grid">
                <?php if($tipo_analisis === 'rotacion_ventas'): ?>
                <div class="metrica-rotacion">
                    <div class="label-rotacion">Categorías Analizadas</div>
                    <div class="valor-rotacion"><?= $resumen_general['total_categorias'] ?? 0 ?></div>
                    <div class="descripcion-rotacion">Total de categorías con ventas</div>
                </div>
                
                <div class="metrica-rotacion">
                    <div class="label-rotacion">Rotación Promedio</div>
                    <div class="valor-rotacion" style="
                        background: linear-gradient(135deg, 
                            <?= ($resumen_general['rotacion_promedio_total'] ?? 0) >= 3 ? '#28a745' : 
                               (($resumen_general['rotacion_promedio_total'] ?? 0) >= 1.5 ? '#ffc107' : '#dc3545') ?>, 
                            <?= ($resumen_general['rotacion_promedio_total'] ?? 0) >= 3 ? '#218838' : 
                               (($resumen_general['rotacion_promedio_total'] ?? 0) >= 1.5 ? '#e0a800' : '#c82333') ?>);
                        -webkit-background-clip: text;
                        -webkit-text-fill-color: transparent;
                        background-clip: text;">
                        <?= number_format($resumen_general['rotacion_promedio_total'] ?? 0, 2, ',', '.') ?>
                    </div>
                    <div class="descripcion-rotacion">Veces que se renueva el inventario</div>
                </div>
                
                <div class="metrica-rotacion">
                    <div class="label-rotacion">Días de Inventario Prom.</div>
                    <div class="valor-rotacion" style="
                        background: linear-gradient(135deg, 
                            <?= ($resumen_general['dias_inventario_promedio'] ?? 0) <= 30 ? '#28a745' : 
                               (($resumen_general['dias_inventario_promedio'] ?? 0) <= 60 ? '#ffc107' : '#dc3545') ?>, 
                            <?= ($resumen_general['dias_inventario_promedio'] ?? 0) <= 30 ? '#218838' : 
                               (($resumen_general['dias_inventario_promedio'] ?? 0) <= 60 ? '#e0a800' : '#c82333') ?>);
                        -webkit-background-clip: text;
                        -webkit-text-fill-color: transparent;
                        background-clip: text;">
                        <?= number_format($resumen_general['dias_inventario_promedio'] ?? 0, 2, ',', '.') ?>
                    </div>
                    <div class="descripcion-rotacion">Días que dura el stock actual</div>
                </div>
                
                <div class="metrica-rotacion">
                    <div class="label-rotacion">Unidades Vendidas</div>
                    <div class="valor-rotacion"><?= number_format($resumen_general['unidades_vendidas_total'] ?? 0, 0, ',', '.') ?></div>
                    <div class="descripcion-rotacion">Total unidades vendidas</div>
                </div>
                
                <?php elseif($tipo_analisis === 'analisis_stock'): ?>
                <div class="metrica-rotacion">
                    <div class="label-rotacion">Categorías Analizadas</div>
                    <div class="valor-rotacion"><?= $resumen_general['total_categorias'] ?? 0 ?></div>
                    <div class="descripcion-rotacion">Total de categorías activas</div>
                </div>
                
                <div class="metrica-rotacion">
                    <div class="label-rotacion">Productos Totales</div>
                    <div class="valor-rotacion"><?= number_format($resumen_general['total_productos'] ?? 0, 0, ',', '.') ?></div>
                    <div class="descripcion-rotacion">Total productos en inventario</div>
                </div>
                
                <div class="metrica-rotacion">
                    <div class="label-rotacion">Stock Total</div>
                    <div class="valor-rotacion"><?= number_format($resumen_general['stock_total'] ?? 0, 0, ',', '.') ?></div>
                    <div class="descripcion-rotacion">Unidades en inventario</div>
                </div>
                
                <div class="metrica-rotacion">
                    <div class="label-rotacion">Valor Inventario</div>
                    <div class="valor-rotacion">Bs. <?= number_format($resumen_general['valor_inventario_total_costo'] ?? 0, 0, ',', '.') ?></div>
                    <div class="descripcion-rotacion">Valor total al costo</div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if($tipo_analisis === 'rotacion_ventas'): ?>
            <div class="tabla-container">
                <h3>Distribución de Categorías por Nivel de Rotación</h3>
                <div class="distribucion-grid">
                    <div class="distribucion-item alta">
                        <div class="distribucion-titulo">Alta Rotación</div>
                        <div class="distribucion-cantidad"><?= $resumen_general['categorias_alta_rotacion'] ?? 0 ?></div>
                        <div class="distribucion-porcentaje">
                            <?= $resumen_general['total_categorias'] > 0 ? number_format(($resumen_general['categorias_alta_rotacion'] ?? 0) / $resumen_general['total_categorias'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                        <div class="descripcion-rotacion" style="font-size: 0.85em; margin-top: 10px;">Rotación ≥ 3</div>
                    </div>
                    
                    <div class="distribucion-item media">
                        <div class="distribucion-titulo">Rotación Media</div>
                        <div class="distribucion-cantidad"><?= $resumen_general['categorias_media_rotacion'] ?? 0 ?></div>
                        <div class="distribucion-porcentaje">
                            <?= $resumen_general['total_categorias'] > 0 ? number_format(($resumen_general['categorias_media_rotacion'] ?? 0) / $resumen_general['total_categorias'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                        <div class="descripcion-rotacion" style="font-size: 0.85em; margin-top: 10px;">Rotación 1.5-3</div>
                    </div>
                    
                    <div class="distribucion-item baja">
                        <div class="distribucion-titulo">Baja Rotación</div>
                        <div class="distribucion-cantidad"><?= $resumen_general['categorias_baja_rotacion'] ?? 0 ?></div>
                        <div class="distribucion-porcentaje">
                            <?= $resumen_general['total_categorias'] > 0 ? number_format(($resumen_general['categorias_baja_rotacion'] ?? 0) / $resumen_general['total_categorias'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                        <div class="descripcion-rotacion" style="font-size: 0.85em; margin-top: 10px;">Rotación 0-1.5</div>
                    </div>
                    
                    <div class="distribucion-item sin">
                        <div class="distribucion-titulo">Sin Rotación</div>
                        <div class="distribucion-cantidad"><?= $resumen_general['categorias_sin_rotacion'] ?? 0 ?></div>
                        <div class="distribucion-porcentaje">
                            <?= $resumen_general['total_categorias'] > 0 ? number_format(($resumen_general['categorias_sin_rotacion'] ?? 0) / $resumen_general['total_categorias'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                        <div class="descripcion-rotacion" style="font-size: 0.85em; margin-top: 10px;">Sin ventas</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if($tipo_analisis === 'rotacion_ventas'): ?>
        <div class="rotacion-container">
            <div class="rotacion-header">
                <h2>Rotación de Inventario por Categoría</h2>
                <div class="rotacion-periodo">
                    <span>Análisis basado en ventas del período</span>
                </div>
            </div>
            
            <div class="leyenda-rotacion">
                <div class="leyenda-item">
                    <div class="leyenda-color" style="background: #28a745;"></div>
                    <div class="leyenda-texto">Alta Rotación (≥3)</div>
                </div>
                <div class="leyenda-item">
                    <div class="leyenda-color" style="background: #ffc107;"></div>
                    <div class="leyenda-texto">Rotación Media (1.5-3)</div>
                </div>
                <div class="leyenda-item">
                    <div class="leyenda-color" style="background: #dc3545;"></div>
                    <div class="leyenda-texto">Baja Rotación (0-1.5)</div>
                </div>
                <div class="leyenda-item">
                    <div class="leyenda-color" style="background: #6c757d;"></div>
                    <div class="leyenda-texto">Sin Rotación</div>
                </div>
            </div>
            
            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-rotacion">
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
                                <th>Producto Más Vendido</th>
                                <th>Clasificación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($rotacion_categorias)): ?>
                                <tr>
                                    <td colspan="10" class="empty-rotacion">
                                        <h3>No hay datos de rotación</h3>
                                        <p>No hay ventas registradas en el período seleccionado.</p>
                                    </td>
                                </tr>
                            <?php else: 
                                $datos_ordenados = $rotacion_categorias;
                                usort($datos_ordenados, function($a, $b) use ($ordenar_por) {
                                    return ($b[$ordenar_por] ?? 0) <=> ($a[$ordenar_por] ?? 0);
                                });
                                
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
                                    
                                    $rotacion = $categoria['rotacion_promedio'] ?? 0;
                                    $clase_barra = $rotacion >= 3 ? 'alta' : ($rotacion >= 1.5 ? 'media' : 'baja');
                            ?>
                            <tr>
                                <td class="categoria-nombre"><?= htmlspecialchars($categoria['categoria'] ?? '') ?></td>
                                <td><?= $categoria['total_productos'] ?? 0 ?></td>
                                <td class="valor-destacado"><?= number_format($categoria['unidades_vendidas'] ?? 0, 0, ',', '.') ?></td>
                                <td>Bs. <?= number_format($categoria['ingresos_totales'] ?? 0, 2, ',', '.') ?></td>
                                <td><?= number_format($categoria['stock_actual_total'] ?? 0, 0, ',', '.') ?></td>
                                <td>
                                    <div class="valor-destacado"><?= number_format($rotacion, 2, ',', '.') ?></div>
                                    <div class="barra-rotacion <?= $clase_barra ?>">
                                        <div class="barra-rotacion-fill" style="width: <?= min($rotacion * 20, 100) ?>%; 
                                            background: linear-gradient(135deg, 
                                                <?= $rotacion >= 3 ? '#28a745' : ($rotacion >= 1.5 ? '#ffc107' : '#dc3545') ?>, 
                                                <?= $rotacion >= 3 ? '#218838' : ($rotacion >= 1.5 ? '#e0a800' : '#c82333') ?>);">
                                        </div>
                                    </div>
                                </td>
                                <td style="color: <?= $color_dias ?>; font-weight: bold;">
                                    <?= $dias_inventario < 999 ? number_format($dias_inventario, 1, ',', '.') . ' días' : 'Sin stock' ?>
                                </td>
                                <td><?= number_format($categoria['velocidad_venta'] ?? 0, 2, ',', '.') ?></td>
                                <td title="<?= htmlspecialchars($categoria['producto_mas_vendido'] ?? 'N/A') ?>">
                                    <?= htmlspecialchars(substr($categoria['producto_mas_vendido'] ?? 'N/A', 0, 25)) ?><?= strlen($categoria['producto_mas_vendido'] ?? '') > 25 ? '...' : '' ?>
                                </td>
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
            <?php endif; ?>
            
            <?php if($tipo_analisis === 'analisis_stock'): ?>
            <div class="rotacion-container">
                <div class="rotacion-header">
                    <h2>Análisis de Stock por Categoría</h2>
                    <div class="rotacion-periodo">
                        <span>Estado actual del inventario</span>
                    </div>
                </div>
                
                <div class="tabla-container">
                    <div class="table-responsive">
                        <table class="tabla-rotacion">
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
                                        <td colspan="12" class="empty-rotacion">
                                            <h3>No hay datos de stock</h3>
                                            <p>No hay productos registrados en el inventario.</p>
                                        </td>
                                    </tr>
                                <?php else: 
                                    $datos_ordenados = $analisis_stock;
                                    usort($datos_ordenados, function($a, $b) use ($ordenar_por) {
                                        return ($b[$ordenar_por] ?? 0) <=> ($a[$ordenar_por] ?? 0);
                                    });
                                    
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
                                    <td class="categoria-nombre"><?= htmlspecialchars($categoria['categoria'] ?? '') ?></td>
                                    <td><?= $categoria['total_productos'] ?? 0 ?></td>
                                    <td><?= $categoria['productos_activos'] ?? 0 ?></td>
                                    <td class="valor-destacado"><?= number_format($categoria['stock_total'] ?? 0, 0, ',', '.') ?></td>
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
            </div>
            <?php endif; ?>
            
            <?php if($tipo_analisis === 'tendencia_ventas'): ?>
            <div class="rotacion-container">
                <div class="rotacion-header">
                    <h2>Tendencia de Ventas por Categoría</h2>
                    <div class="rotacion-periodo">
                        <span>Análisis mensual de ventas</span>
                    </div>
                </div>
                
                <div class="tabla-container">
                    <div class="table-responsive">
                        <table class="tabla-rotacion">
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
                                        <td colspan="9" class="empty-rotacion">
                                            <h3>No hay datos de tendencia</h3>
                                            <p>No hay ventas registradas en el período seleccionado.</p>
                                        </td>
                                    </tr>
                                <?php else: 
                                    foreach($tendencia_ventas as $tendencia): 
                                        $crecimiento_unidades = $tendencia['crecimiento_unidades'] ?? 0;
                                        $crecimiento_ingresos = $tendencia['crecimiento_ingresos'] ?? 0;
                                        
                                        $clase_crecimiento_unidades = $crecimiento_unidades > 0 ? 'crecimiento-positivo' : 
                                                                     ($crecimiento_unidades < 0 ? 'crecimiento-negativo' : 'crecimiento-neutro');
                                        $clase_crecimiento_ingresos = $crecimiento_ingresos > 0 ? 'crecimiento-positivo' : 
                                                                      ($crecimiento_ingresos < 0 ? 'crecimiento-negativo' : 'crecimiento-neutro');
                                        
                                        $tendencia_general = ($crecimiento_unidades + $crecimiento_ingresos) / 2;
                                        $clase_tendencia = $tendencia_general > 10 ? 'crecimiento-positivo' : 
                                                          ($tendencia_general > 0 ? 'crecimiento-positivo' : 
                                                          ($tendencia_general < -10 ? 'crecimiento-negativo' : 'crecimiento-neutro'));
                                        $icono_tendencia = $tendencia_general > 10 ? '📈 Alta' : 
                                                          ($tendencia_general > 0 ? '↗️ Media' : 
                                                          ($tendencia_general < -10 ? '📉 Baja' : '➡️ Estable'));
                                ?>
                                <tr>
                                    <td><strong><?= $tendencia['mes_nombre'] ?? '' ?></strong></td>
                                    <td class="categoria-nombre"><?= htmlspecialchars($tendencia['categoria'] ?? '') ?></td>
                                    <td class="valor-destacado"><?= number_format($tendencia['unidades_vendidas'] ?? 0, 0, ',', '.') ?></td>
                                    <td>Bs. <?= number_format($tendencia['ingresos_mes'] ?? 0, 2, ',', '.') ?></td>
                                    <td><?= $tendencia['facturas_mes'] ?? 0 ?></td>
                                    <td><?= $tendencia['productos_vendidos_mes'] ?? 0 ?></td>
                                    <td>
                                        <span class="crecimiento-indicador <?= $clase_crecimiento_unidades ?>">
                                            <?= number_format($crecimiento_unidades, 1, ',', '.') ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="crecimiento-indicador <?= $clase_crecimiento_ingresos ?>">
                                            <?= number_format($crecimiento_ingresos, 1, ',', '.') ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="crecimiento-indicador <?= $clase_tendencia ?>">
                                            <?= $icono_tendencia ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
$(document).ready(function() {
    $('#tipo-analisis').change(function() {
        const tipo = $(this).val();
        
        if (tipo === 'tendencia_ventas') {
            $('#filtro-categoria').show();
        } else {
            $('#filtro-categoria').hide();
        }
        
        if (tipo === 'analisis_stock') {
            $('#filtro-fechas').hide();
            $('#filtro-fechas-fin').hide();
        } else {
            $('#filtro-fechas').show();
            $('#filtro-fechas-fin').show();
        }
    });
    
    $('#tipo-analisis').trigger('change');
});
</script>
</body>
</html>