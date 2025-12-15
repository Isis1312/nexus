<?php
// reportes/top_clientes.php

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../conexion.php'; // Asegúrate de que este archivo contiene la conexión $pdo

// --- 1. Variables de Filtro y Fecha ---
$current_year = date('Y');
$current_month = date('m');

// Procesar filtros
$year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
$month = isset($_GET['month']) ? intval($_GET['month']) : $current_month;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$limit = max(1, $limit); // Asegurar límite mínimo de 1

// Meses en español
$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// --- 2. Función de Base de Datos (getTopClientes) ---
function getTopClientes($pdo, $year, $month, $limit = 10) {
    $lim = max(1, intval($limit));
    $sql = "SELECT
                c.id AS cliente_id,
                COALESCE(c.nombre, v.cliente) AS cliente,
                COUNT(v.id_venta) AS cantidad_compras,
                SUM(v.total_usd) AS total_usd
            FROM ventas v
            LEFT JOIN clientes c ON v.id_cliente = c.id
            WHERE YEAR(v.fecha) = :year AND MONTH(v.fecha) = :month
            GROUP BY cliente_id
            ORDER BY total_bs DESC
            LIMIT $lim"; // El LIMIT se inyecta directamente porque $lim ya está saneado
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['year' => $year, 'month' => $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- 3. Ejecutar Consulta ---
$topClientes = getTopClientes($pdo, $year, $month, $limit);

// --- 4. HTML de Presentación ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte top cleintes</title>
     <link rel="stylesheet" href="../css/reportes/general_reportes.css">
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Top clientes</h1>
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
                                <?php for($y = 2025; $y <= $current_year; $y++): ?>
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
                <h2>Top <?= $limit ?> Clientes por Monto</h2>
                <div class="periodo-info">
                    <span><?= $meses_espanol[intval($month)] ?> de <?= $year ?></span>
                </div>
            </div>

            <div class="tabla-container">
                <div class="table-responsive">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Compras</th>
                                <th>Total $</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($topClientes)): ?>
                                <tr><td colspan="4" class="empty-state">No hay datos de clientes para este periodo</td></tr>
                            <?php else: 
                                $rank = 1;
                                foreach($topClientes as $c): ?>
                                <tr>
                                    <td><?= $rank++ ?></td>
                                    <td><?= htmlspecialchars($c['cliente'] ?? 'Sin nombre') ?></td>
                                    <td><?= intval($c['cantidad_compras']) ?></td>
                                    <td>$. <?= number_format($c['total_usd'] ?? 0, 2, ',', '.') ?></td>
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