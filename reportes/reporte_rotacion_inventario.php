<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../conexion.php';

$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-90 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');


function getRotacionInventario($pdo, $fecha_inicio, $fecha_fin) {
    $inicio = date('Y-m-d', strtotime($fecha_inicio));
    $fin = date('Y-m-d', strtotime($fecha_fin));

    $dias_timestamp = strtotime($fin) - strtotime($inicio);
    $dias_periodo = floor($dias_timestamp / (60 * 60 * 24)) + 1;


    try {
        $colsStmt = $pdo->query("SHOW COLUMNS FROM productos");
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        $cols = [];
    }

    $stockCol = null;
    if (in_array('cantidad', $cols, true)) $stockCol = 'cantidad';
    elseif (in_array('stock', $cols, true)) $stockCol = 'stock';

    if ($stockCol) {
        $stockSelect = "COALESCE(p.$stockCol, 0) AS stock";
    } else {
        $stockSelect = "0 AS stock";
    }

    $sql = "SELECT
                p.id,
                p.nombre,
                {$stockSelect},
                IFNULL((SELECT SUM(dv.cantidad)
                        FROM detalle_venta dv
                        JOIN ventas v ON dv.id_venta = v.id_venta
                        WHERE dv.id_producto = p.id
                          AND v.fecha BETWEEN :fecha_inicio AND :fecha_fin
                       ), 0) AS vendidos_periodo
            FROM productos p
            ORDER BY vendidos_periodo DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':fecha_inicio', $inicio);
    $stmt->bindParam(':fecha_fin', $fin);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $vendidos = floatval($r['vendidos_periodo']);
        $stock = floatval($r['stock']);
        $promedio_diario = $vendidos > 0 && $dias_periodo > 0 ? ($vendidos / $dias_periodo) : 0;
        
        $r['rotacion'] = $stock > 0 ? round($vendidos / $stock, 2) : null;
    }
    unset($r);
    return $rows;
}

$rotacion = getRotacionInventario($pdo, $fecha_inicio, $fecha_fin);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rotación de inventario</title>
    <link rel="stylesheet" href="../css/reportes/general_reportes.css">
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Rotación de inventario</h1>
            <a href="../reportes.php" class="volver-button">
                        Volver
                    </a>
        </div>

        <div class="filtros-container">
            <div class="filtros-card">
                <h3>Ajustar Periodo de Ventas</h3>
                <form method="GET" class="filtros-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_inicio">Fecha Inicio:</label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-input" value="<?= $fecha_inicio ?>">
                        </div>
                        <div class="form-group">
                            <label for="fecha_fin">Fecha Fin:</label>
                            <input type="date" name="fecha_fin" id="fecha_fin" class="form-input" value="<?= $fecha_fin ?>">
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
                <h2>Métricas de Inventario</h2>
                </div>

            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Stock Actual</th>
                                <th>Vendidos (Período)</th>
                                <th>Rotación</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($rotacion)): ?>
                                <tr><td colspan="4" class="empty-state">No hay datos de inventario o ventas en el periodo seleccionado</td></tr>
                            <?php else: foreach($rotacion as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['nombre']) ?></td>
                                    <td><?= intval($r['stock']) ?></td>
                                    <td><?= intval($r['vendidos_periodo']) ?></td>
                                    <td><?= $r['rotacion'] !== null ? number_format($r['rotacion'], 2) . '%' : '-' ?></td>
                                    </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>