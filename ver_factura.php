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
if (!$sistemaPermisos->puedeVer('ventas')) {
    header('Location: inicio.php');
    exit();
}

// Obtener ID de la factura
$id_venta = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_venta <= 0) {
    header('Location: facturas.php');
    exit();
}

// Obtener datos de la factura
try {
    // Obtener venta
    $stmt = $pdo->prepare("SELECT v.*, c.cedula, c.telefono, c.direccion  
                          FROM ventas v 
                          LEFT JOIN clientes c ON v.id_cliente = c.id 
                          WHERE v.id_venta = ?");
    $stmt->execute([$id_venta]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        header('Location: facturas.php');
        exit();
    }
    
    // Obtener detalles de la venta
    $stmt_detalles = $pdo->prepare("SELECT * FROM detalle_venta WHERE id_venta = ?");
    $stmt_detalles->execute([$id_venta]);
    $detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular impuestos (como se hizo originalmente)
    $subtotal_bs = 0;
    foreach ($detalles as $detalle) {
        $subtotal_bs += $detalle['subtotal_bs'];
    }
    
    $iva_porcentaje = 16;
    $iva_bs = $subtotal_bs * ($iva_porcentaje / 100);
    
    $igtf_porcentaje = ($factura['metodo_pago'] === 'Efectivo') ? 3 : 0;
    $igtf_bs = $subtotal_bs * ($igtf_porcentaje / 100);
    
} catch (PDOException $e) {
    die("Error al cargar la factura: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura <?= $factura['nro_factura'] ?></title>
    <link rel="stylesheet" href="css/ver_factura.css">
</head>
<body>

<main class="main-content">
    <div class="content-wrapper">
        <div class="factura-detalle">
            <!-- Encabezado -->
            <div class="encabezado-factura">
                
                <div class="info-factura-detalle">
                    <div class="numero-factura"><?= $factura['nro_factura'] ?></div>
                    <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($factura['fecha'])) ?></p>
                    <p><strong>Registrado por:</strong> <?= htmlspecialchars($factura['usuario_nombre'] ?? 'Sistema') ?></p>
                    <p><strong>Tasa $:</strong> Bs. <?= number_format($factura['tasa_usd'], 2, ',', '.') ?></p>
                </div>
            </div>
            
            <!-- Datos del cliente -->
            <div class="datos-cliente-detalle">
                <h3>Datos del Cliente</h3>
                <div class="cliente-info-detalle">
                    <div class="info-cliente-grid">
                        <div class="info-cliente-item">
                            <span class="info-cliente-label">Nombre:</span>
                            <span class="info-cliente-value"><?= htmlspecialchars($factura['cliente']) ?></span>
                        </div>
                        <div class="info-cliente-item">
                            <span class="info-cliente-label">Cédula:</span>
                            <span class="info-cliente-value"><?= htmlspecialchars($factura['cedula'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-cliente-item">
                            <span class="info-cliente-label">Teléfono:</span>
                            <span class="info-cliente-value"><?= htmlspecialchars($factura['telefono'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-cliente-item">
                            <span class="info-cliente-label">Dirección:</span>
                            <span class="info-cliente-value"><?= htmlspecialchars($factura['direccion'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detalles de productos -->
            <div class="seccion-detalles">
                <h3>Detalles de la Factura</h3>
                <div class="tabla-detalles-container">
                    <table class="tabla-detalles">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario (Bs)</th>
                                <th>Subtotal (Bs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $detalle): ?>
                            <tr>
                                <td><?= htmlspecialchars($detalle['codigo_producto']) ?></td>
                                <td><?= htmlspecialchars($detalle['nombre_producto']) ?></td>
                                <td class="text-center"><?= number_format($detalle['cantidad'], 2, ',', '.') ?></td>
                                <td class="text-right">Bs. <?= number_format($detalle['precio_unitario_bs'], 2, ',', '.') ?></td>
                                <td class="text-right">Bs. <?= number_format($detalle['subtotal_bs'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Totales -->
            <div class="seccion-totales-detalle">
                <div class="totales-detalle-container">
                    <div class="total-fila">
                        <span class="total-label-detalle">Subtotal:</span>
                        <span class="total-value-detalle">Bs. <?= number_format($subtotal_bs, 2, ',', '.') ?></span>
                    </div>
                    <div class="total-fila">
                        <span class="total-label-detalle">IVA (16%):</span>
                        <span class="total-value-detalle">Bs. <?= number_format($iva_bs, 2, ',', '.') ?></span>
                    </div>
                    <?php if ($factura['metodo_pago'] === 'Efectivo'): ?>
                    <div class="total-fila">
                        <span class="total-label-detalle">IGTF (3%):</span>
                        <span class="total-value-detalle">Bs. <?= number_format($igtf_bs, 2, ',', '.') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="total-fila total-final-detalle">
                        <span class="total-label-detalle">TOTAL:</span>
                        <span class="total-value-detalle">Bs. <?= number_format($factura['total_bs'], 2, ',', '.') ?></span>
                    </div>
                    <div class="total-fila">
                        <span class="total-label-detalle">Método de Pago:</span>
                        <span class="total-value-detalle metodo-pago-detalle <?= strtolower(str_replace(' ', '-', $factura['metodo_pago'])) ?>">
                            <?= htmlspecialchars($factura['metodo_pago']) ?>
                        </span>
                    </div>
                    <div class="total-fila total-final-detalle">
                        <span class="total-label-detalle">TOTAL EN DÓLARES:</span>
                        <span class="total-value-detalle">$ <?= number_format($factura['total_usd'], 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="acciones-factura no-print">
                <button class="btn-imprimir" onclick="window.print()">
                    🖨️ Imprimir Factura
                </button>
                <a href="facturas.php" class="btn-volver-factura">
                    ↩️ Volver a Facturas
                </a>
            </div>
            
            <!-- Pie de página -->
            <div class="pie-factura">
                <p><strong>Gracias por su compra</strong></p>
                <p>Esta factura es un documento legal. Conserve una copia.</p>
                <p>Sistema generado automáticamente - <?= date('d/m/Y H:i:s') ?></p>
            </div>
        </div>
    </div>
</main>

<script>
// Configurar impresión
document.addEventListener('DOMContentLoaded', function() {
    // Agregar funcionalidad de impresión
    document.querySelector('.btn-imprimir').addEventListener('click', function() {
        window.print();
    });
});
</script>

</body>
</html>