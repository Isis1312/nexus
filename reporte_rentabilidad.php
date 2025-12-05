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

// Primero, verificar la estructura de la tabla productos
function verificarEstructuraProductos($pdo) {
    $query = "SHOW COLUMNS FROM productos";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $columnas_costo = [];
    $columnas_precio = [];
    
    foreach ($columnas as $columna) {
        $nombre = strtolower($columna['Field']);
        
        if (strpos($nombre, 'costo') !== false) {
            $columnas_costo[] = $columna['Field'];
        }
        
        if (strpos($nombre, 'precio') !== false) {
            $columnas_precio[] = $columna['Field'];
        }
    }
    
    return [
        'costo' => $columnas_costo,
        'precio' => $columnas_precio,
        'todas' => array_column($columnas, 'Field')
    ];
}

$estructura = verificarEstructuraProductos($pdo);

// Determinar qué columnas usar para costos
$columna_costo = 'precio_costo'; // Valor por defecto
if (in_array('costo_promedio_bs', $estructura['costo'])) {
    $columna_costo = 'costo_promedio_bs';
} elseif (in_array('costo_bs', $estructura['costo'])) {
    $columna_costo = 'costo_bs';
} elseif (in_array('costo', $estructura['costo'])) {
    $columna_costo = 'costo';
} elseif (in_array('precio_costo', $estructura['precio'])) {
    $columna_costo = 'precio_costo';
} elseif (in_array('precio_compra_bs', $estructura['precio'])) {
    $columna_costo = 'precio_compra_bs';
}

// Determinar qué columnas usar para precios de venta
$columna_precio_venta = 'precio_venta_bs'; // Valor por defecto
if (in_array('precio_venta_bs', $estructura['precio'])) {
    $columna_precio_venta = 'precio_venta_bs';
} elseif (in_array('precio_bs', $estructura['precio'])) {
    $columna_precio_venta = 'precio_bs';
} elseif (in_array('precio', $estructura['precio'])) {
    $columna_precio_venta = 'precio';
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
$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
$tipo_analisis = isset($_GET['tipo_analisis']) ? $_GET['tipo_analisis'] : 'por_factura';

// Función para obtener el costo promedio de productos
function getCostoPromedioProductos($pdo, $columna_costo, $columna_precio_venta) {
    // Primero verificar si las columnas existen
    $query_check = "SHOW COLUMNS FROM productos WHERE Field = ? OR Field = ?";
    $stmt_check = $pdo->prepare($query_check);
    $stmt_check->execute([$columna_costo, $columna_precio_venta]);
    $existen = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($existen) < 2) {
        // Si no existen ambas columnas, usar valores por defecto
        $query = "SELECT 
                    id,
                    codigo,
                    nombre,
                    0 as costo_promedio_bs,
                    0 as precio_venta_bs,
                    0 as margen_porcentaje,
                    0 as margen_bs
                  FROM productos 
                  WHERE estado = 'active' 
                  LIMIT 0";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $query = "SELECT 
                id,
                codigo,
                nombre,
                $columna_costo as costo_promedio_bs,
                $columna_precio_venta as precio_venta_bs,
                ROUND((($columna_precio_venta - $columna_costo) / $columna_precio_venta * 100), 2) as margen_porcentaje,
                ROUND(($columna_precio_venta - $columna_costo), 2) as margen_bs
              FROM productos 
              WHERE estado = 'active' 
                AND $columna_costo > 0
                AND $columna_precio_venta > 0
              ORDER BY margen_porcentaje DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener rentabilidad por factura
function getRentabilidadPorFactura($pdo, $start_date, $end_date, $cliente_id = 0, $columna_costo) {
    $params = ['start_date' => $start_date . ' 00:00:00', 'end_date' => $end_date . ' 23:59:59'];
    $cliente_where = '';
    
    if ($cliente_id > 0) {
        $cliente_where = ' AND v.id_cliente = :cliente_id';
        $params['cliente_id'] = $cliente_id;
    }
    
    // Verificar si la columna de costo existe
    $query_check = "SHOW COLUMNS FROM productos WHERE Field = ?";
    $stmt_check = $pdo->prepare($query_check);
    $stmt_check->execute([$columna_costo]);
    $columna_existe = $stmt_check->fetch();
    
    if (!$columna_existe) {
        // Si no existe la columna, usar valor por defecto
        $columna_costo_sql = "dv.precio_unitario_bs * 0.7";
    } else {
        $columna_costo_sql = "COALESCE(p.$columna_costo, dv.precio_unitario_bs * 0.7)";
    }
    
    $query = "SELECT 
                v.id_venta,
                v.nro_factura,
                v.fecha,
                v.cliente,
                v.metodo_pago,
                v.total_bs as ingresos_totales,
                v.total_usd,
                COUNT(DISTINCT dv.id_detalle) as cantidad_productos,
                SUM(dv.cantidad) as total_unidades,
                
                -- Calcular costos totales
                ROUND(SUM(dv.cantidad * $columna_costo_sql), 2) as costos_totales,
                
                -- Calcular ganancia
                ROUND(v.total_bs - SUM(dv.cantidad * $columna_costo_sql), 2) as ganancia_bruta,
                
                -- Calcular márgenes
                ROUND(((v.total_bs - SUM(dv.cantidad * $columna_costo_sql)) / v.total_bs * 100), 2) as margen_porcentaje,
                
                -- Calcular rentabilidad por unidad
                ROUND(((v.total_bs - SUM(dv.cantidad * $columna_costo_sql)) / SUM(dv.cantidad)), 2) as ganancia_por_unidad,
                
                -- Clasificación de rentabilidad
                CASE 
                    WHEN ((v.total_bs - SUM(dv.cantidad * $columna_costo_sql)) / v.total_bs * 100) >= 40 THEN 'ALTA'
                    WHEN ((v.total_bs - SUM(dv.cantidad * $columna_costo_sql)) / v.total_bs * 100) >= 20 THEN 'MEDIA'
                    ELSE 'BAJA'
                END as nivel_rentabilidad
                
              FROM ventas v
              INNER JOIN detalle_venta dv ON v.id_venta = dv.id_venta
              LEFT JOIN productos p ON dv.id_producto = p.id
              WHERE v.fecha BETWEEN :start_date AND :end_date
                $cliente_where
              GROUP BY v.id_venta
              ORDER BY margen_porcentaje DESC, v.fecha DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener rentabilidad por producto
function getRentabilidadPorProducto($pdo, $start_date, $end_date, $columna_costo, $columna_precio_venta) {
    // Verificar si la columna de costo existe
    $query_check = "SHOW COLUMNS FROM productos WHERE Field = ?";
    $stmt_check = $pdo->prepare($query_check);
    $stmt_check->execute([$columna_costo]);
    $columna_existe = $stmt_check->fetch();
    
    if (!$columna_existe) {
        // Si no existe la columna, devolver array vacío
        return [];
    }
    
    $query = "SELECT 
                p.id,
                p.codigo,
                p.nombre,
                cp.nombre_categoria as categoria,
                p.$columna_costo as costo_promedio_bs,
                ROUND(AVG(dv.precio_unitario_bs), 2) as precio_venta_promedio,
                SUM(dv.cantidad) as total_vendido,
                SUM(dv.subtotal_bs) as ingresos_totales,
                ROUND(SUM(dv.cantidad * p.$columna_costo), 2) as costos_totales,
                ROUND(SUM(dv.subtotal_bs) - SUM(dv.cantidad * p.$columna_costo), 2) as ganancia_total,
                ROUND(((SUM(dv.subtotal_bs) - SUM(dv.cantidad * p.$columna_costo)) / SUM(dv.subtotal_bs) * 100), 2) as margen_porcentaje,
                ROUND(((SUM(dv.subtotal_bs) - SUM(dv.cantidad * p.$columna_costo)) / SUM(dv.cantidad)), 2) as ganancia_por_unidad,
                COUNT(DISTINCT v.id_venta) as facturas_con_producto,
                MAX(v.fecha) as ultima_venta,
                
                -- Clasificación
                CASE 
                    WHEN ((SUM(dv.subtotal_bs) - SUM(dv.cantidad * p.$columna_costo)) / SUM(dv.subtotal_bs) * 100) >= 40 THEN 'ALTA'
                    WHEN ((SUM(dv.subtotal_bs) - SUM(dv.cantidad * p.$columna_costo)) / SUM(dv.subtotal_bs) * 100) >= 20 THEN 'MEDIA'
                    ELSE 'BAJA'
                END as nivel_rentabilidad
                
              FROM productos p
              LEFT JOIN categoria_prod cp ON p.categoria_id = cp.id
              INNER JOIN detalle_venta dv ON p.id = dv.id_producto
              INNER JOIN ventas v ON dv.id_venta = v.id_venta
              WHERE v.fecha BETWEEN :start_date AND :end_date
                AND p.estado = 'active'
                AND p.$columna_costo > 0
              GROUP BY p.id
              HAVING total_vendido > 0
              ORDER BY ganancia_total DESC, margen_porcentaje DESC
              LIMIT 50";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'start_date' => $start_date . ' 00:00:00',
        'end_date' => $end_date . ' 23:59:59'
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener resumen general de rentabilidad - VERSIÓN SIMPLIFICADA Y CORREGIDA
function getResumenRentabilidad($pdo, $start_date, $end_date, $columna_costo) {
    // Primero, obtener datos básicos de ventas sin costos
    $query = "SELECT 
                COUNT(DISTINCT v.id_venta) as total_facturas,
                SUM(v.total_bs) as ingresos_totales,
                SUM(dv.cantidad) as total_unidades,
                ROUND(AVG(v.total_bs), 2) as ticket_promedio,
                COUNT(DISTINCT v.id_cliente) as clientes_activos
              FROM ventas v
              INNER JOIN detalle_venta dv ON v.id_venta = dv.id_venta
              WHERE v.fecha BETWEEN :start_date AND :end_date";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'start_date' => $start_date . ' 00:00:00',
        'end_date' => $end_date . ' 23:59:59'
    ]);
    
    $resumen = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ahora calcular costos y ganancias usando subconsulta segura
    $query_costos = "SELECT 
                        SUM(costos.costo_total) as costos_totales,
                        SUM(costos.ganancia) as ganancia_total,
                        CASE 
                            WHEN SUM(costos.ingresos) > 0 
                            THEN ROUND((SUM(costos.ganancia) / SUM(costos.ingresos) * 100), 2)
                            ELSE 0 
                        END as margen_promedio
                    FROM (
                        SELECT 
                            dv.id_detalle,
                            dv.subtotal_bs as ingresos,
                            CASE 
                                WHEN p.precio_costo IS NOT NULL AND p.precio_costo > 0
                                THEN ROUND(dv.cantidad * p.precio_costo, 2)
                                ELSE ROUND(dv.cantidad * dv.precio_unitario_bs * 0.7, 2)
                            END as costo_total,
                            CASE 
                                WHEN p.precio_costo IS NOT NULL AND p.precio_costo > 0
                                THEN ROUND(dv.subtotal_bs - (dv.cantidad * p.precio_costo), 2)
                                ELSE ROUND(dv.subtotal_bs - (dv.cantidad * dv.precio_unitario_bs * 0.7), 2)
                            END as ganancia
                        FROM ventas v
                        INNER JOIN detalle_venta dv ON v.id_venta = dv.id_venta
                        LEFT JOIN productos p ON dv.id_producto = p.id
                        WHERE v.fecha BETWEEN :start_date AND :end_date
                    ) as costos";
    
    $stmt_costos = $pdo->prepare($query_costos);
    $stmt_costos->execute([
        'start_date' => $start_date . ' 00:00:00',
        'end_date' => $end_date . ' 23:59:59'
    ]);
    
    $costos_data = $stmt_costos->fetch(PDO::FETCH_ASSOC);
    
    // Combinar resultados
    if ($resumen && $costos_data) {
        $resumen_completo = array_merge($resumen, $costos_data);
        
        // Calcular ganancia por unidad
        if ($resumen_completo['total_unidades'] > 0) {
            $resumen_completo['ganancia_por_unidad'] = 
                round($resumen_completo['ganancia_total'] / $resumen_completo['total_unidades'], 2);
        } else {
            $resumen_completo['ganancia_por_unidad'] = 0;
        }
        
        // Obtener distribución de facturas por nivel de rentabilidad
        $query_distribucion = "SELECT 
                    SUM(CASE WHEN rentabilidad.margen >= 40 THEN 1 ELSE 0 END) as facturas_alta_rentabilidad,
                    SUM(CASE WHEN rentabilidad.margen BETWEEN 20 AND 39.99 THEN 1 ELSE 0 END) as facturas_media_rentabilidad,
                    SUM(CASE WHEN rentabilidad.margen < 20 THEN 1 ELSE 0 END) as facturas_baja_rentabilidad
                  FROM (
                      SELECT 
                          v.id_venta,
                          CASE 
                              WHEN SUM(CASE 
                                        WHEN p.precio_costo IS NOT NULL AND p.precio_costo > 0
                                        THEN dv.cantidad * p.precio_costo
                                        ELSE dv.cantidad * dv.precio_unitario_bs * 0.7
                                       END) > 0
                              THEN ROUND(((v.total_bs - SUM(CASE 
                                                          WHEN p.precio_costo IS NOT NULL AND p.precio_costo > 0
                                                          THEN dv.cantidad * p.precio_costo
                                                          ELSE dv.cantidad * dv.precio_unitario_bs * 0.7
                                                         END)) / v.total_bs * 100), 2)
                              ELSE 0
                          END as margen
                      FROM ventas v
                      INNER JOIN detalle_venta dv ON v.id_venta = dv.id_venta
                      LEFT JOIN productos p ON dv.id_producto = p.id
                      WHERE v.fecha BETWEEN :start_date AND :end_date
                      GROUP BY v.id_venta
                  ) as rentabilidad";
        
        $stmt_distribucion = $pdo->prepare($query_distribucion);
        $stmt_distribucion->execute([
            'start_date' => $start_date . ' 00:00:00',
            'end_date' => $end_date . ' 23:59:59'
        ]);
        
        $distribucion = $stmt_distribucion->fetch(PDO::FETCH_ASSOC);
        
        if ($distribucion) {
            $resumen_completo = array_merge($resumen_completo, $distribucion);
        } else {
            $resumen_completo['facturas_alta_rentabilidad'] = 0;
            $resumen_completo['facturas_media_rentabilidad'] = 0;
            $resumen_completo['facturas_baja_rentabilidad'] = 0;
        }
        
        return $resumen_completo;
    }
    
    return null;
}

// Función para obtener rentabilidad por cliente
function getRentabilidadPorCliente($pdo, $start_date, $end_date, $columna_costo) {
    // Verificar si la columna de costo existe
    $query_check = "SHOW COLUMNS FROM productos WHERE Field = ?";
    $stmt_check = $pdo->prepare($query_check);
    $stmt_check->execute([$columna_costo]);
    $columna_existe = $stmt_check->fetch();
    
    if (!$columna_existe) {
        // Si no existe la columna, usar valor por defecto
        $columna_costo_sql = "dv.precio_unitario_bs * 0.7";
    } else {
        $columna_costo_sql = "COALESCE(p.$columna_costo, dv.precio_unitario_bs * 0.7)";
    }
    
    $query = "SELECT 
                v.id_cliente,
                v.cliente,
                COUNT(DISTINCT v.id_venta) as total_facturas,
                SUM(v.total_bs) as ingresos_totales,
                SUM(dv.cantidad) as total_productos,
                ROUND(SUM(dv.cantidad * $columna_costo_sql), 2) as costos_totales,
                ROUND(SUM(v.total_bs) - SUM(dv.cantidad * $columna_costo_sql), 2) as ganancia_total,
                ROUND(((SUM(v.total_bs) - SUM(dv.cantidad * $columna_costo_sql)) / SUM(v.total_bs) * 100), 2) as margen_promedio,
                ROUND(AVG(v.total_bs), 2) as ticket_promedio,
                MAX(v.fecha) as ultima_compra,
                
                -- Clasificación de cliente
                CASE 
                    WHEN ((SUM(v.total_bs) - SUM(dv.cantidad * $columna_costo_sql)) / SUM(v.total_bs) * 100) >= 35 THEN 'ALTA RENTABILIDAD'
                    WHEN ((SUM(v.total_bs) - SUM(dv.cantidad * $columna_costo_sql)) / SUM(v.total_bs) * 100) >= 25 THEN 'MEDIA RENTABILIDAD'
                    ELSE 'BAJA RENTABILIDAD'
                END as clasificacion_cliente,
                
                -- Valor del cliente
                CASE 
                    WHEN SUM(v.total_bs) > 10000 THEN 'CLIENTE PREMIUM'
                    WHEN SUM(v.total_bs) > 5000 THEN 'CLIENTE MEDIO'
                    ELSE 'CLIENTE BASICO'
                END as valor_cliente
                
              FROM ventas v
              INNER JOIN detalle_venta dv ON v.id_venta = dv.id_venta
              LEFT JOIN productos p ON dv.id_producto = p.id
              WHERE v.fecha BETWEEN :start_date AND :end_date
                AND v.cliente IS NOT NULL
                AND v.cliente != ''
              GROUP BY v.id_cliente, v.cliente
              HAVING ingresos_totales > 0
              ORDER BY ganancia_total DESC, margen_promedio DESC
              LIMIT 30";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'start_date' => $start_date . ' 00:00:00',
        'end_date' => $end_date . ' 23:59:59'
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener clientes (para el select)
function getClientes($pdo) {
    $query = "SELECT DISTINCT id_cliente, cliente 
              FROM ventas 
              WHERE cliente IS NOT NULL 
                AND cliente != ''
              ORDER BY cliente ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener datos según el tipo de análisis
$rentabilidad_por_factura = [];
$rentabilidad_por_producto = [];
$rentabilidad_por_cliente = [];
$resumen_rentabilidad = [];
$costo_promedio_productos = [];
$clientes = getClientes($pdo);

// Información de depuración (puedes eliminar esto después)
$debug_info = [
    'columna_costo_detectada' => $columna_costo,
    'columna_precio_venta_detectada' => $columna_precio_venta,
    'estructura_productos' => $estructura
];

if ($tipo_analisis === 'por_factura') {
    $rentabilidad_por_factura = getRentabilidadPorFactura($pdo, $start_date, $end_date, $cliente_id, $columna_costo);
    $resumen_rentabilidad = getResumenRentabilidad($pdo, $start_date, $end_date, $columna_costo);
} elseif ($tipo_analisis === 'por_producto') {
    $rentabilidad_por_producto = getRentabilidadPorProducto($pdo, $start_date, $end_date, $columna_costo, $columna_precio_venta);
    $resumen_rentabilidad = getResumenRentabilidad($pdo, $start_date, $end_date, $columna_costo);
} elseif ($tipo_analisis === 'por_cliente') {
    $rentabilidad_por_cliente = getRentabilidadPorCliente($pdo, $start_date, $end_date, $columna_costo);
    $resumen_rentabilidad = getResumenRentabilidad($pdo, $start_date, $end_date, $columna_costo);
} elseif ($tipo_analisis === 'costos_productos') {
    $costo_promedio_productos = getCostoPromedioProductos($pdo, $columna_costo, $columna_precio_venta);
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
    <title>Reporte de Rentabilidad</title>
    <!-- CONEXIÓN CORRECTA DE CSS -->
    <link rel="stylesheet" href="css/reportes.css">
    <link rel="stylesheet" href="css/reportes_rentabilidad.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Estilos inline adicionales */
        .debug-info {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 10px;
            margin: 10px 0;
            font-size: 12px;
            color: #666;
        }
        .debug-info h4 {
            margin-top: 0;
            color: #007bff;
        }
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
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Reporte de Rentabilidad</h1>
        </div>

        <!-- Información de depuración (opcional, puede eliminar) -->
        <?php if(isset($_GET['debug'])): ?>
        <div class="debug-info">
            <h4>Información de depuración:</h4>
            <p><strong>Columna de costo detectada:</strong> <?= $columna_costo ?></p>
            <p><strong>Columna de precio de venta detectada:</strong> <?= $columna_precio_venta ?></p>
            <p><strong>Columnas de costo encontradas:</strong> <?= implode(', ', $estructura['costo']) ?></p>
            <p><strong>Columnas de precio encontradas:</strong> <?= implode(', ', $estructura['precio']) ?></p>
            <p><strong>Todas las columnas de productos:</strong> <?= implode(', ', $estructura['todas']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Información sobre costos -->
        <div class="info-alert">
            <strong>Información:</strong> El sistema está usando la columna <strong>"<?= $columna_costo ?>"</strong> para calcular costos. 
            Si necesitas usar otra columna, verifica la estructura de tu tabla productos.
        </div>

        <!-- Filtros -->
        <div class="filtros-container">
            <div class="filtros-card">
                <h3>Filtrar Reporte de Rentabilidad</h3>
                <form method="GET" class="filtros-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Análisis:</label>
                            <select name="tipo_analisis" class="form-select" id="tipo-analisis">
                                <option value="por_factura" <?= $tipo_analisis === 'por_factura' ? 'selected' : '' ?>>Por Factura/Transacción</option>
                                <option value="por_producto" <?= $tipo_analisis === 'por_producto' ? 'selected' : '' ?>>Por Producto</option>
                                <option value="por_cliente" <?= $tipo_analisis === 'por_cliente' ? 'selected' : '' ?>>Por Cliente</option>
                                <option value="costos_productos" <?= $tipo_analisis === 'costos_productos' ? 'selected' : '' ?>>Costos y Márgenes de Productos</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Fecha Inicio:</label>
                            <input type="date" name="start_date" class="form-input" value="<?= $start_date ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Fecha Fin:</label>
                            <input type="date" name="end_date" class="form-input" value="<?= $end_date ?>">
                        </div>
                        
                        <div class="form-group" id="filtro-cliente" style="display: <?= $tipo_analisis === 'por_factura' ? 'block' : 'none' ?>;">
                            <label>Cliente (Opcional):</label>
                            <select name="cliente_id" class="form-select">
                                <option value="0">Todos los Clientes</option>
                                <?php foreach($clientes as $cliente): ?>
                                    <option value="<?= $cliente['id_cliente'] ?>" <?= $cliente_id == $cliente['id_cliente'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cliente['cliente']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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

        <!-- Resumen General -->
        <?php if($resumen_rentabilidad && in_array($tipo_analisis, ['por_factura', 'por_producto', 'por_cliente'])): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Resumen de Rentabilidad</h2>
                <div class="periodo-info">
                    <span><?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></span>
                </div>
            </div>
            
            <div class="estadisticas-grid">
                <div class="estadistica-card">
                    <div class="estadistica-label">Ingresos Totales</div>
                    <div class="estadistica-value">Bs. <?= number_format($resumen_rentabilidad['ingresos_totales'] ?? 0, 2, ',', '.') ?></div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Ganancia Bruta</div>
                    <div class="estadistica-value" style="color: #28a745;">
                        Bs. <?= number_format($resumen_rentabilidad['ganancia_total'] ?? 0, 2, ',', '.') ?>
                    </div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Margen Promedio</div>
                    <div class="estadistica-value" style="color: <?= ($resumen_rentabilidad['margen_promedio'] ?? 0) >= 30 ? '#28a745' : (($resumen_rentabilidad['margen_promedio'] ?? 0) >= 20 ? '#ffc107' : '#dc3545') ?>;">
                        <?= number_format($resumen_rentabilidad['margen_promedio'] ?? 0, 2, ',', '.') ?>%
                    </div>
                </div>
                
                <div class="estadistica-card">
                    <div class="estadistica-label">Facturas Analizadas</div>
                    <div class="estadistica-value"><?= $resumen_rentabilidad['total_facturas'] ?? 0 ?></div>
                </div>
            </div>
            
            <!-- Distribución de Rentabilidad -->
            <?php if(isset($resumen_rentabilidad['facturas_alta_rentabilidad'])): ?>
            <div class="tabla-container">
                <h3>Distribución de Facturas por Nivel de Rentabilidad</h3>
                <div class="metodos-grid">
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));">
                        <div class="metodo-nombre" style="color: #28a745;">Alta Rentabilidad (≥40%)</div>
                        <div class="metodo-cantidad"><?= $resumen_rentabilidad['facturas_alta_rentabilidad'] ?? 0 ?> facturas</div>
                        <div class="metodo-total" style="color: #28a745;">
                            <?= $resumen_rentabilidad['total_facturas'] > 0 ? number_format(($resumen_rentabilidad['facturas_alta_rentabilidad'] ?? 0) / $resumen_rentabilidad['total_facturas'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                    </div>
                    
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05));">
                        <div class="metodo-nombre" style="color: #ffc107;">Media Rentabilidad (20-39%)</div>
                        <div class="metodo-cantidad"><?= $resumen_rentabilidad['facturas_media_rentabilidad'] ?? 0 ?> facturas</div>
                        <div class="metodo-total" style="color: #ffc107;">
                            <?= $resumen_rentabilidad['total_facturas'] > 0 ? number_format(($resumen_rentabilidad['facturas_media_rentabilidad'] ?? 0) / $resumen_rentabilidad['total_facturas'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                    </div>
                    
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));">
                        <div class="metodo-nombre" style="color: #dc3545;">Baja Rentabilidad (<20%)</div>
                        <div class="metodo-cantidad"><?= $resumen_rentabilidad['facturas_baja_rentabilidad'] ?? 0 ?> facturas</div>
                        <div class="metodo-total" style="color: #dc3545;">
                            <?= $resumen_rentabilidad['total_facturas'] > 0 ? number_format(($resumen_rentabilidad['facturas_baja_rentabilidad'] ?? 0) / $resumen_rentabilidad['total_facturas'] * 100, 1, ',', '.') : '0' ?>%
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Reporte por Factura -->
        <?php if($tipo_analisis === 'por_factura'): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Rentabilidad por Factura/Transacción</h2>
                <div class="periodo-info">
                    <span><?= $cliente_id > 0 ? 'Cliente específico' : 'Todos los clientes' ?></span>
                </div>
            </div>
            
            <!-- Tabla de Facturas -->
            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Método Pago</th>
                                <th>Ingresos (Bs)</th>
                                <th>Costos (Bs)</th>
                                <th>Ganancia (Bs)</th>
                                <th>Margen %</th>
                                <th>Unidades</th>
                                <th>Ganancia/Unidad</th>
                                <th>Nivel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($rentabilidad_por_factura)): ?>
                                <tr>
                                    <td colspan="11" class="empty-state">No hay facturas en el período seleccionado</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $total_ingresos = 0;
                                $total_costos = 0;
                                $total_ganancia = 0;
                                ?>
                                <?php foreach($rentabilidad_por_factura as $factura): 
                                    $total_ingresos += $factura['ingresos_totales'];
                                    $total_costos += $factura['costos_totales'];
                                    $total_ganancia += $factura['ganancia_bruta'];
                                    
                                    $color_nivel = [
                                        'ALTA' => '#28a745',
                                        'MEDIA' => '#ffc107',
                                        'BAJA' => '#dc3545'
                                    ][$factura['nivel_rentabilidad']] ?? '#6c757d';
                                ?>
                                <tr>
                                    <td><?= $factura['nro_factura'] ?? 'N/A' ?></td>
                                    <td><?= date('d/m/Y', strtotime($factura['fecha'])) ?></td>
                                    <td><?= htmlspecialchars($factura['cliente'] ?? 'Sin cliente') ?></td>
                                    <td><?= $factura['metodo_pago'] ?? 'N/A' ?></td>
                                    <td>Bs. <?= number_format($factura['ingresos_totales'] ?? 0, 2, ',', '.') ?></td>
                                    <td>Bs. <?= number_format($factura['costos_totales'] ?? 0, 2, ',', '.') ?></td>
                                    <td style="color: <?= ($factura['ganancia_bruta'] ?? 0) >= 0 ? '#28a745' : '#dc3545' ?>;">
                                        Bs. <?= number_format($factura['ganancia_bruta'] ?? 0, 2, ',', '.') ?>
                                    </td>
                                    <td style="color: <?= $color_nivel ?>; font-weight: bold;">
                                        <?= number_format($factura['margen_porcentaje'] ?? 0, 2, ',', '.') ?>%
                                    </td>
                                    <td><?= $factura['total_unidades'] ?? 0 ?></td>
                                    <td>Bs. <?= number_format($factura['ganancia_por_unidad'] ?? 0, 2, ',', '.') ?></td>
                                    <td>
                                        <span class="nivel-badge" style="background: <?= $color_nivel ?>20; color: <?= $color_nivel ?>; border-color: <?= $color_nivel ?>;">
                                            <?= $factura['nivel_rentabilidad'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if(!empty($rentabilidad_por_factura)): ?>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="4"><strong>TOTALES</strong></td>
                                <td><strong>Bs. <?= number_format($total_ingresos, 2, ',', '.') ?></strong></td>
                                <td><strong>Bs. <?= number_format($total_costos, 2, ',', '.') ?></strong></td>
                                <td><strong>Bs. <?= number_format($total_ganancia, 2, ',', '.') ?></strong></td>
                                <td><strong><?= $total_ingresos > 0 ? number_format(($total_ganancia / $total_ingresos * 100), 2, ',', '.') : '0,00' ?>%</strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <!-- Gráfico de distribución -->
            <div class="tabla-container">
                <h3>Distribución de Rentabilidad por Factura</h3>
                <div class="grafico-container">
                    <canvas id="graficoRentabilidadFacturas"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reporte por Producto -->
        <?php if($tipo_analisis === 'por_producto'): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Rentabilidad por Producto</h2>
                <div class="periodo-info">
                    <span>Top 50 productos más rentables</span>
                </div>
            </div>
            
            <!-- Tabla de Productos -->
            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Código</th>
                                <th>Categoría</th>
                                <th>Costo Prom. (Bs)</th>
                                <th>Precio Prom. (Bs)</th>
                                <th>Unidades Vend.</th>
                                <th>Ingresos (Bs)</th>
                                <th>Costos (Bs)</th>
                                <th>Ganancia (Bs)</th>
                                <th>Margen %</th>
                                <th>Ganancia/Unidad</th>
                                <th>Nivel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($rentabilidad_por_producto)): ?>
                                <tr>
                                    <td colspan="12" class="empty-state">No hay productos vendidos en el período seleccionado o no se encontró la columna de costos</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $total_ingresos = 0;
                                $total_costos = 0;
                                $total_ganancia = 0;
                                $total_unidades = 0;
                                ?>
                                <?php foreach($rentabilidad_por_producto as $producto): 
                                    $total_ingresos += $producto['ingresos_totales'];
                                    $total_costos += $producto['costos_totales'];
                                    $total_ganancia += $producto['ganancia_total'];
                                    $total_unidades += $producto['total_vendido'];
                                    
                                    $color_nivel = [
                                        'ALTA' => '#28a745',
                                        'MEDIA' => '#ffc107',
                                        'BAJA' => '#dc3545'
                                    ][$producto['nivel_rentabilidad']] ?? '#6c757d';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($producto['nombre'] ?? '') ?></td>
                                    <td><?= $producto['codigo'] ?? '' ?></td>
                                    <td><?= $producto['categoria'] ?? '' ?></td>
                                    <td>Bs. <?= number_format($producto['costo_promedio_bs'] ?? 0, 2, ',', '.') ?></td>
                                    <td>Bs. <?= number_format($producto['precio_venta_promedio'] ?? 0, 2, ',', '.') ?></td>
                                    <td><?= $producto['total_vendido'] ?? 0 ?></td>
                                    <td>Bs. <?= number_format($producto['ingresos_totales'] ?? 0, 2, ',', '.') ?></td>
                                    <td>Bs. <?= number_format($producto['costos_totales'] ?? 0, 2, ',', '.') ?></td>
                                    <td style="color: <?= ($producto['ganancia_total'] ?? 0) >= 0 ? '#28a745' : '#dc3545' ?>;">
                                        Bs. <?= number_format($producto['ganancia_total'] ?? 0, 2, ',', '.') ?>
                                    </td>
                                    <td style="color: <?= $color_nivel ?>; font-weight: bold;">
                                        <?= number_format($producto['margen_porcentaje'] ?? 0, 2, ',', '.') ?>%
                                    </td>
                                    <td>Bs. <?= number_format($producto['ganancia_por_unidad'] ?? 0, 2, ',', '.') ?></td>
                                    <td>
                                        <span class="nivel-badge" style="background: <?= $color_nivel ?>20; color: <?= $color_nivel ?>; border-color: <?= $color_nivel ?>;">
                                            <?= $producto['nivel_rentabilidad'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if(!empty($rentabilidad_por_producto)): ?>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="5"><strong>TOTALES</strong></td>
                                <td><strong><?= $total_unidades ?></strong></td>
                                <td><strong>Bs. <?= number_format($total_ingresos, 2, ',', '.') ?></strong></td>
                                <td><strong>Bs. <?= number_format($total_costos, 2, ',', '.') ?></strong></td>
                                <td><strong>Bs. <?= number_format($total_ganancia, 2, ',', '.') ?></strong></td>
                                <td><strong><?= $total_ingresos > 0 ? number_format(($total_ganancia / $total_ingresos * 100), 2, ',', '.') : '0,00' ?>%</strong></td>
                                <td><strong>Bs. <?= $total_unidades > 0 ? number_format(($total_ganancia / $total_unidades), 2, ',', '.') : '0,00' ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <!-- Gráfico de productos más rentables -->
            <div class="tabla-container">
                <h3>Top 10 Productos Más Rentables</h3>
                <div class="grafico-container">
                    <canvas id="graficoTopProductos"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reporte por Cliente -->
        <?php if($tipo_analisis === 'por_cliente'): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Rentabilidad por Cliente</h2>
                <div class="periodo-info">
                    <span>Top 30 clientes más rentables</span>
                </div>
            </div>
            
            <!-- Tabla de Clientes -->
            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Facturas</th>
                                <th>Ingresos (Bs)</th>
                                <th>Costos (Bs)</th>
                                <th>Ganancia (Bs)</th>
                                <th>Margen %</th>
                                <th>Ticket Promedio</th>
                                <th>Última Compra</th>
                                <th>Clasificación</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($rentabilidad_por_cliente)): ?>
                                <tr>
                                    <td colspan="10" class="empty-state">No hay clientes con compras en el período seleccionado</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $total_ingresos = 0;
                                $total_costos = 0;
                                $total_ganancia = 0;
                                $total_facturas = 0;
                                ?>
                                <?php foreach($rentabilidad_por_cliente as $cliente): 
                                    $total_ingresos += $cliente['ingresos_totales'];
                                    $total_costos += $cliente['costos_totales'];
                                    $total_ganancia += $cliente['ganancia_total'];
                                    $total_facturas += $cliente['total_facturas'];
                                    
                                    $color_clasificacion = [
                                        'ALTA RENTABILIDAD' => '#28a745',
                                        'MEDIA RENTABILIDAD' => '#ffc107',
                                        'BAJA RENTABILIDAD' => '#dc3545'
                                    ][$cliente['clasificacion_cliente']] ?? '#6c757d';
                                    
                                    $color_valor = [
                                        'CLIENTE PREMIUM' => '#6f42c1',
                                        'CLIENTE MEDIO' => '#007bff',
                                        'CLIENTE BASICO' => '#6c757d'
                                    ][$cliente['valor_cliente']] ?? '#6c757d';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($cliente['cliente'] ?? 'Cliente no identificado') ?></td>
                                    <td><?= $cliente['total_facturas'] ?? 0 ?></td>
                                    <td>Bs. <?= number_format($cliente['ingresos_totales'] ?? 0, 2, ',', '.') ?></td>
                                    <td>Bs. <?= number_format($cliente['costos_totales'] ?? 0, 2, ',', '.') ?></td>
                                    <td style="color: <?= ($cliente['ganancia_total'] ?? 0) >= 0 ? '#28a745' : '#dc3545' ?>;">
                                        Bs. <?= number_format($cliente['ganancia_total'] ?? 0, 2, ',', '.') ?>
                                    </td>
                                    <td style="color: <?= $color_clasificacion ?>; font-weight: bold;">
                                        <?= number_format($cliente['margen_promedio'] ?? 0, 2, ',', '.') ?>%
                                    </td>
                                    <td>Bs. <?= number_format($cliente['ticket_promedio'] ?? 0, 2, ',', '.') ?></td>
                                    <td><?= date('d/m/Y', strtotime($cliente['ultima_compra'])) ?></td>
                                    <td>
                                        <span class="nivel-badge" style="background: <?= $color_clasificacion ?>20; color: <?= $color_clasificacion ?>; border-color: <?= $color_clasificacion ?>;">
                                            <?= $cliente['clasificacion_cliente'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="nivel-badge" style="background: <?= $color_valor ?>20; color: <?= $color_valor ?>; border-color: <?= $color_valor ?>;">
                                            <?= $cliente['valor_cliente'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if(!empty($rentabilidad_por_cliente)): ?>
                        <tfoot>
                            <tr class="total-row">
                                <td><strong>TOTALES</strong></td>
                                <td><strong><?= $total_facturas ?></strong></td>
                                <td><strong>Bs. <?= number_format($total_ingresos, 2, ',', '.') ?></strong></td>
                                <td><strong>Bs. <?= number_format($total_costos, 2, ',', '.') ?></strong></td>
                                <td><strong>Bs. <?= number_format($total_ganancia, 2, ',', '.') ?></strong></td>
                                <td><strong><?= $total_ingresos > 0 ? number_format(($total_ganancia / $total_ingresos * 100), 2, ',', '.') : '0,00' ?>%</strong></td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <!-- Gráfico de clientes -->
            <div class="tabla-container">
                <h3>Distribución de Clientes por Rentabilidad</h3>
                <div class="grafico-container">
                    <canvas id="graficoClientes"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Costos y Margenes de Productos -->
        <?php if($tipo_analisis === 'costos_productos'): ?>
        <div class="reporte-container">
            <div class="reporte-header">
                <h2>Costos y Márgenes de Productos</h2>
                <div class="periodo-info">
                    <span>Análisis de estructura de costos</span>
                </div>
            </div>
            
            <!-- Tabla de Costos -->
            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Código</th>
                                <th>Costo Prom. (Bs)</th>
                                <th>Precio Venta (Bs)</th>
                                <th>Margen Bs</th>
                                <th>Margen %</th>
                                <th>Relación Precio/Costo</th>
                                <th>Recomendación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($costo_promedio_productos)): ?>
                                <tr>
                                    <td colspan="8" class="empty-state">No hay productos con costos registrados o no se encontraron las columnas de costo y precio</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $productos_alto_margen = 0;
                                $productos_medio_margen = 0;
                                $productos_bajo_margen = 0;
                                $productos_sin_costo = 0;
                                ?>
                                <?php foreach($costo_promedio_productos as $producto): 
                                    $margen_porcentaje = $producto['margen_porcentaje'] ?? 0;
                                    $costo = $producto['costo_promedio_bs'] ?? 0;
                                    $precio = $producto['precio_venta_bs'] ?? 0;
                                    
                                    if ($costo <= 0 || $precio <= 0) {
                                        $productos_sin_costo++;
                                        continue;
                                    }
                                    
                                    $relacion_precio_costo = $precio > 0 && $costo > 0 
                                        ? round($precio / $costo, 2) 
                                        : 0;
                                    
                                    if ($margen_porcentaje >= 40) {
                                        $productos_alto_margen++;
                                        $color_margen = '#28a745';
                                        $recomendacion = 'Excelente margen';
                                    } elseif ($margen_porcentaje >= 25) {
                                        $productos_medio_margen++;
                                        $color_margen = '#ffc107';
                                        $recomendacion = 'Margen adecuado';
                                    } else {
                                        $productos_bajo_margen++;
                                        $color_margen = '#dc3545';
                                        $recomendacion = 'Revisar precio/costo';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($producto['nombre'] ?? '') ?></td>
                                    <td><?= $producto['codigo'] ?? '' ?></td>
                                    <td>Bs. <?= number_format($costo, 2, ',', '.') ?></td>
                                    <td>Bs. <?= number_format($precio, 2, ',', '.') ?></td>
                                    <td style="color: <?= $color_margen ?>;">
                                        Bs. <?= number_format($producto['margen_bs'] ?? 0, 2, ',', '.') ?>
                                    </td>
                                    <td style="color: <?= $color_margen ?>; font-weight: bold;">
                                        <?= number_format($margen_porcentaje, 2, ',', '.') ?>%
                                    </td>
                                    <td><?= $relacion_precio_costo ?>x</td>
                                    <td>
                                        <span class="nivel-badge" style="background: <?= $color_margen ?>20; color: <?= $color_margen ?>; border-color: <?= $color_margen ?>;">
                                            <?= $recomendacion ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Estadísticas de márgenes -->
            <?php if($productos_alto_margen + $productos_medio_margen + $productos_bajo_margen > 0): ?>
            <div class="tabla-container">
                <h3>Distribución de Márgenes por Producto</h3>
                <div class="metodos-grid">
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));">
                        <div class="metodo-nombre" style="color: #28a745;">Alto Margen (≥40%)</div>
                        <div class="metodo-cantidad"><?= $productos_alto_margen ?> productos</div>
                        <div class="metodo-total" style="color: #28a745;">
                            <?= ($productos_alto_margen + $productos_medio_margen + $productos_bajo_margen) > 0 ? number_format(($productos_alto_margen / ($productos_alto_margen + $productos_medio_margen + $productos_bajo_margen) * 100), 1, ',', '.') : '0' ?>%
                        </div>
                    </div>
                    
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05));">
                        <div class="metodo-nombre" style="color: #ffc107;">Margen Medio (25-39%)</div>
                        <div class="metodo-cantidad"><?= $productos_medio_margen ?> productos</div>
                        <div class="metodo-total" style="color: #ffc107;">
                            <?= ($productos_alto_margen + $productos_medio_margen + $productos_bajo_margen) > 0 ? number_format(($productos_medio_margen / ($productos_alto_margen + $productos_medio_margen + $productos_bajo_margen) * 100), 1, ',', '.') : '0' ?>%
                        </div>
                    </div>
                    
                    <div class="metodo-card" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));">
                        <div class="metodo-nombre" style="color: #dc3545;">Bajo Margen (<25%)</div>
                        <div class="metodo-cantidad"><?= $productos_bajo_margen ?> productos</div>
                        <div class="metodo-total" style="color: #dc3545;">
                            <?= ($productos_alto_margen + $productos_medio_margen + $productos_bajo_margen) > 0 ? number_format(($productos_bajo_margen / ($productos_alto_margen + $productos_medio_margen + $productos_bajo_margen) * 100), 1, ',', '.') : '0' ?>%
                        </div>
                    </div>
                </div>
                <?php if($productos_sin_costo > 0): ?>
                <div class="info-alert" style="margin-top: 15px;">
                    <strong>Nota:</strong> Hay <?= $productos_sin_costo ?> productos sin costos registrados o con costo/precio igual a cero. Estos no se incluyen en el análisis.
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Mostrar/ocultar filtro de cliente según tipo de análisis
$(document).ready(function() {
    $('#tipo-analisis').change(function() {
        if ($(this).val() === 'por_factura') {
            $('#filtro-cliente').show();
        } else {
            $('#filtro-cliente').hide();
        }
    });
    
    // Inicializar estado
    if ($('#tipo-analisis').val() !== 'por_factura') {
        $('#filtro-cliente').hide();
    }
    
    // Generar gráficos si existen
    generarGraficos();
});

function generarGraficos() {
    <?php if($tipo_analisis === 'por_factura' && !empty($rentabilidad_por_factura)): ?>
    // Gráfico para rentabilidad por factura
    const ctx1 = document.getElementById('graficoRentabilidadFacturas').getContext('2d');
    const facturasData = <?= json_encode(array_slice($rentabilidad_por_factura, 0, 20)) ?>;
    
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: facturasData.map(f => f.nro_factura),
            datasets: [{
                label: 'Margen %',
                data: facturasData.map(f => parseFloat(f.margen_porcentaje)),
                backgroundColor: facturasData.map(f => {
                    const margen = parseFloat(f.margen_porcentaje);
                    return margen >= 40 ? 'rgba(40, 167, 69, 0.7)' : 
                           margen >= 20 ? 'rgba(255, 193, 7, 0.7)' : 
                           'rgba(220, 53, 69, 0.7)';
                }),
                borderColor: facturasData.map(f => {
                    const margen = parseFloat(f.margen_porcentaje);
                    return margen >= 40 ? '#28a745' : 
                           margen >= 20 ? '#ffc107' : 
                           '#dc3545';
                }),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Margen de Rentabilidad por Factura (Top 20)'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const factura = facturasData[context.dataIndex];
                            return [
                                `Cliente: ${factura.cliente}`,
                                `Ingresos: Bs. ${parseFloat(factura.ingresos_totales).toLocaleString('es-ES', {minimumFractionDigits: 2})}`,
                                `Ganancia: Bs. ${parseFloat(factura.ganancia_bruta).toLocaleString('es-ES', {minimumFractionDigits: 2})}`,
                                `Margen: ${parseFloat(factura.margen_porcentaje).toLocaleString('es-ES', {minimumFractionDigits: 2})}%`
                            ];
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Margen (%)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Número de Factura'
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if($tipo_analisis === 'por_producto' && !empty($rentabilidad_por_producto)): ?>
    // Gráfico para rentabilidad por producto
    const ctx2 = document.getElementById('graficoTopProductos').getContext('2d');
    const productosData = <?= json_encode(array_slice($rentabilidad_por_producto, 0, 10)) ?>;
    
    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: productosData.map(p => p.nombre.substring(0, 20) + (p.nombre.length > 20 ? '...' : '')),
            datasets: [
                {
                    label: 'Ingresos (Bs)',
                    data: productosData.map(p => parseFloat(p.ingresos_totales)),
                    backgroundColor: 'rgba(0, 139, 139, 0.7)',
                    borderColor: '#008B8B',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Margen %',
                    data: productosData.map(p => parseFloat(p.margen_porcentaje)),
                    backgroundColor: productosData.map(p => {
                        const margen = parseFloat(p.margen_porcentaje);
                        return margen >= 40 ? 'rgba(40, 167, 69, 0.7)' : 
                               margen >= 20 ? 'rgba(255, 193, 7, 0.7)' : 
                               'rgba(220, 53, 69, 0.7)';
                    }),
                    borderColor: productosData.map(p => {
                        const margen = parseFloat(p.margen_porcentaje);
                        return margen >= 40 ? '#28a745' : 
                               margen >= 20 ? '#ffc107' : 
                               '#dc3545';
                    }),
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
                    text: 'Top 10 Productos Más Rentables'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Ingresos (Bs)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Margen (%)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if($tipo_analisis === 'por_cliente' && !empty($rentabilidad_por_cliente)): ?>
    // Gráfico para rentabilidad por cliente
    const ctx3 = document.getElementById('graficoClientes').getContext('2d');
    
    // Contar clientes por clasificación
    const clasificaciones = {};
    <?php foreach($rentabilidad_por_cliente as $cliente): ?>
        const clasif = '<?= $cliente["clasificacion_cliente"] ?>';
        clasificaciones[clasif] = (clasificaciones[clasif] || 0) + 1;
    <?php endforeach; ?>
    
    new Chart(ctx3, {
        type: 'doughnut',
        data: {
            labels: Object.keys(clasificaciones),
            datasets: [{
                data: Object.values(clasificaciones),
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(255, 193, 7, 0.7)',
                    'rgba(220, 53, 69, 0.7)'
                ],
                borderColor: [
                    '#28a745',
                    '#ffc107',
                    '#dc3545'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Distribución de Clientes por Nivel de Rentabilidad'
                },
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    <?php endif; ?>
}
</script>
</body>
</html>