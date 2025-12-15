<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../conexion.php';


$dias_lookback = isset($_GET['dias']) ? intval($_GET['dias']) : 90;
$dias_lookback = max(1, $dias_lookback);

function getRotacionInventario($pdo, $dias_lookback = 90) {
    $dias = max(1, intval($dias_lookback));

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
        $valorStockRef = "COALESCE(p.$stockCol, 0)";
    } else {
        $stockSelect = "0 AS stock";
        $valorStockRef = "0";
    }

    $sql = "SELECT
                p.id,
                p.nombre,
                {$stockSelect},
                IFNULL((SELECT SUM(dv.cantidad)
                        FROM detalle_venta dv
                        JOIN ventas v ON dv.id_venta = v.id_venta
                        WHERE dv.id_producto = p.id
                          AND v.fecha >= DATE_SUB(CURDATE(), INTERVAL $dias DAY)
                       ), 0) AS vendidos_periodo
            FROM productos p
            ORDER BY vendidos_periodo DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $vendidos = floatval($r['vendidos_periodo']);
        $stock = floatval($r['stock']);
        $promedio_diario = $vendidos > 0 ? ($vendidos / $dias) : 0;
        
        $r['rotacion'] = $stock > 0 ? round($vendidos / $stock, 2) : null;
        
        $r['dias_inventario'] = $promedio_diario > 0 ? intval(round($stock / $promedio_diario)) : null;
    }
    unset($r);
    return $rows;
}

$rotacion = getRotacionInventario($pdo, $dias_lookback);

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
                            <label>Días de análisis:</label>
                            <input type="number" name="dias" class="form-input" value="<?= $dias_lookback ?>" min="1" style="width: 100px;">
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
                <div class="periodo-info">
                    <span>Análisis basado en los últimos <?= $dias_lookback ?> días</span>
                </div>
            </div>

            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Stock Actual</th>
                                <th>Vendidos (<?= $dias_lookback ?> días)</th>
                                <th>Rotación</th>
                                <th>Días de Inventario Restantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($rotacion)): ?>
                                <tr><td colspan="5" class="empty-state">No hay datos de inventario o ventas recientes</td></tr>
                            <?php else: foreach($rotacion as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['nombre']) ?></td>
                                    <td><?= intval($r['stock']) ?></td>
                                    <td><?= intval($r['vendidos_periodo']) ?></td>
                                    <td><?= $r['rotacion'] !== null ? $r['rotacion'] . 'x' : '-' ?></td>
                                    <td>
                                        <?php
                                            if ($r['dias_inventario'] === null) {
                                                if (intval($r['vendidos_periodo']) > 0) {
                                                    echo '∞ (Stock insuficiente)';
                                                } else {
                                                    echo 'N/A (Sin ventas)';
                                                }
                                            } else {
                                                echo $r['dias_inventario'] . ' días';
                                            }
                                        ?>
                                    </td>
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