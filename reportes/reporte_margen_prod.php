<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../conexion.php';


$current_year = date('Y');
$current_month = date('m');

$year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
$month = isset($_GET['month']) ? intval($_GET['month']) : $current_month;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$limit = max(1, $limit);

$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

function getMargenPorProducto($pdo, $year, $month, $limit = 20) {
    $lim = max(1, intval($limit));
    try {
        $sql = "SELECT
                    p.id,
                    p.nombre,
                    COALESCE(SUM(dv.cantidad),0) AS unidades_vendidas,
                    COALESCE(AVG(dv.precio_unitario_bs),0) AS precio_promedio_venta,
                    COALESCE(p.precio_costo, 0) AS precio_costo_unitario,
                    COALESCE(SUM(dv.cantidad * dv.precio_unitario_bs),0) AS ingreso_total,
                    COALESCE(SUM(dv.cantidad * COALESCE(p.precio_costo,0)),0) AS costo_total,
                    COALESCE(SUM(dv.cantidad * dv.precio_unitario_bs),0) - COALESCE(SUM(dv.cantidad * COALESCE(p.precio_costo,0)),0) AS utilidad_total
                FROM detalle_venta dv
                JOIN ventas v ON dv.id_venta = v.id_venta
                LEFT JOIN productos p ON dv.id_producto = p.id
                WHERE YEAR(v.fecha) = :year AND MONTH(v.fecha) = :month
                GROUP BY p.id
                ORDER BY utilidad_total DESC
                LIMIT $lim";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['year' => $year, 'month' => $month]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $venta_unit_prom = floatval($r['precio_promedio_venta'] ?? 0);
            $costo_unit = floatval($r['precio_costo_unitario'] ?? 0);
            $r['utilidad_unitaria'] = $venta_unit_prom - $costo_unit;
            $r['margen_pct'] = $venta_unit_prom > 0 ? round((($r['utilidad_unitaria']) / $venta_unit_prom) * 100, 2) : 0;
            $r['unidades_vendidas'] = intval($r['unidades_vendidas']);
            $r['ingreso_total'] = round(floatval($r['ingreso_total']), 2);
            $r['costo_total'] = round(floatval($r['costo_total']), 2);
            $r['utilidad_total'] = round(floatval($r['utilidad_total']), 2);
        }
        unset($r);
        return $rows;
    } catch (PDOException $e) {
        error_log("Error en getMargenPorProducto: " . $e->getMessage());
        return [];
    }
}

$margenes = getMargenPorProducto($pdo, $year, $month, $limit);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Margen por Producto</title>
    <link rel="stylesheet" href="../css/reportes/general_reportes.css"></head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Margen por Producto</h1>
            <a href="../reportes.php" class="volver-button">
            Volver
            </a>
        </div>

        <div class="filtros-container">
            <div class="filtros-card">
                <h3>Filtrar Periodo y Cantidad</h3>
                <form method="GET" class="filtros-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Mes:</label>
                            <select name="month" class="form-select">
                                <?php foreach($meses_espanol as $num => $nombre): ?>
                                    <option value="<?= $num ?>" <?= $num == $month ? 'selected' : '' ?>><?= $nombre ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Año:</label>
                            <select name="year" class="form-select">
                                <?php for($y = 2023; $y <= $current_year; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Límite:</label>
                            <input type="number" name="limit" class="form-input" value="<?= $limit ?>" min="1" style="width: 80px;">
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
                <h2>Top <?= $limit ?> Productos por Utilidad Total</h2>
                <div class="periodo-info">
                    <span><?= $meses_espanol[intval($month)] ?> de <?= $year ?></span>
                </div>
            </div>

            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Unidades</th>
                                <th>Precio Promedio Venta (Bs)</th>
                                <th>Precio Costo (Bs)</th>
                                <th>Utilidad Unitaria (Bs)</th>
                                <th>Margen %</th>
                                <th>Utilidad Total (Bs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($margenes)): ?>
                                <tr><td colspan="7" class="empty-state">No hay datos de margen para este periodo</td></tr>
                            <?php else: 
                                $utilidad_total_general = 0;
                                $ingreso_total_general = 0;
                                foreach($margenes as $m): 
                                    $utilidad_total_general += $m['utilidad_total'];
                                    $ingreso_total_general += $m['ingreso_total'];
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                                    <td><?= $m['unidades_vendidas'] ?></td>
                                    <td><?= number_format($m['precio_promedio_venta'], 2, ',', '.') ?></td>
                                    <td><?= number_format($m['precio_costo_unitario'], 2, ',', '.') ?></td>
                                    <td><?= number_format($m['utilidad_unitaria'], 2, ',', '.') ?></td>
                                    <td><?= $m['margen_pct'] ?> %</td>
                                    <td><?= number_format($m['utilidad_total'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                        <?php if(!empty($margenes)): ?>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="6"><strong>UTILIDAD BRUTA TOTAL (Top <?= $limit ?>)</strong></td>
                                <td><strong>Bs. <?= number_format($utilidad_total_general, 2, ',', '.') ?></strong></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>