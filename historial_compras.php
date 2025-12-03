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

if (!$sistemaPermisos->puedeVer('proveedores')) {
    header('Location: inicio.php');
    exit();
}

// Obtener historial de compras
try {
    $sql = "
        SELECT 
            hc.*,
            pp.nombre as producto_nombre,
            pp.codigo_producto,
            p.nombre_comercial as proveedor_nombre,
            u.nombre as usuario_nombre
        FROM historial_compras hc
        JOIN productos_proveedor pp ON hc.id_producto_proveedor = pp.id_producto_proveedor
        JOIN proveedores p ON pp.id_proveedor = p.id_proveedor
        JOIN usuario u ON hc.usuario_id = u.id_usuario
        ORDER BY hc.fecha_registro DESC
    ";
    $stmt = $pdo->query($sql);
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estadísticas
    $total_compras = count($historial);
    $total_invertido = array_sum(array_column($historial, 'precio_total'));
    $total_unidades = array_sum(array_column($historial, 'total_unidades'));
    
} catch (PDOException $e) {
    die("Error al consultar historial: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Compras - NEXUS</title>
    <link rel="stylesheet" href="css/proveedores.css">
</head>
<body>
   <main class="main-content">
        <div class="content-wrapper">
            <div class="container">
                <div class="header">
                    <h1>📋 Historial de Compras</h1>
                    <p>Registro de todas las compras realizadas a proveedores</p>
                </div>

                <!-- Estadísticas -->
                <div class="stats-container">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_compras; ?></span>
                        <span class="stat-label">Total Compras</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number">$<?php echo number_format($total_invertido, 2); ?></span>
                        <span class="stat-label">Total Invertido</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_unidades; ?></span>
                        <span class="stat-label">Total Unidades</span>
                    </div>
                </div>

                <?php if ($total_compras > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fecha Compra</th>
                                    <th>Producto</th>
                                    <th>Proveedor</th>
                                    <th>Cantidad</th>
                                    <th>Total Unidades</th>
                                    <th>Precio Total</th>
                                    <th>Vencimiento</th>
                                    <th>Usuario</th>
                                    <th>Fecha Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial as $row): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo date('d/m/Y', strtotime($row['fecha_compra'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['producto_nombre']); ?></strong><br>
                                            <small>Código: <?php echo $row['codigo_producto']; ?></small>
                                        </td>
                                        <td><?php echo $row['proveedor_nombre']; ?></td>
                                        <td>
                                            <?php echo $row['cantidad_empaques']; ?> empaques<br>
                                            <small><?php echo $row['unidades_empaque']; ?> unid/emp</small>
                                        </td>
                                        <td style="font-weight: bold;">
                                            <?php echo $row['total_unidades']; ?>
                                        </td>
                                        <td style="font-weight: bold; color: #28a745;">
                                            $<?php echo number_format($row['precio_total'], 2); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning">
                                                <?php echo date('d/m/Y', strtotime($row['fecha_vencimiento'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $row['usuario_nombre']; ?></td>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i', strtotime($row['fecha_registro'])); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>📭 No hay historial de compras</h3>
                        <p>Realiza tu primera compra para ver el historial aquí.</p>
                        <a href="productos_proveedores.php" class="btn btn-primary">
                            Ir a Productos de Proveedores
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <a href="productos_proveedores.php" class="btn btn-primary">← Volver a Productos</a>
                    <a href="reporte_compras.php" class="btn btn-secondary">📊 Generar Reporte</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>