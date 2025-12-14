<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
// require_once 'menu.php'; // COMENTADO: No se necesita el menú lateral

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

// --- LÓGICA DE CÁLCULO ALINEADA CON FACTURACIÓN (USD como base) ---
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

    // Variables de cálculo
    $tasa_usd = floatval($factura['tasa_usd']);
    $subtotal_usd_calculado = 0;
    
    // 1. ITERAR Y CALCULAR VALORES EN BS DENTRO DEL ARRAY DE DETALLES
    foreach ($detalles as &$detalle) {
        $cantidad = floatval($detalle['cantidad']);
        $precio_usd = floatval($detalle['precio_unitario_usd']);
        
        // Calcular precio unitario en Bs
        $detalle['precio_unitario_bs'] = $precio_usd * $tasa_usd;
        
        // Recalcular subtotal USD (para precisión)
        $detalle['subtotal_usd'] = $cantidad * $precio_usd;
        
        // Calcular subtotal en Bs
        $detalle['subtotal_bs'] = $detalle['subtotal_usd'] * $tasa_usd;
        
        $subtotal_usd_calculado += $detalle['subtotal_usd'];
    }
    unset($detalle); // Romper referencia

    $subtotal_bs_calculado = $subtotal_usd_calculado * $tasa_usd;
    
    // 2. Calcular IVA (16% sobre Subtotal USD)
    $iva_porcentaje = 16;
    $iva_usd = $subtotal_usd_calculado * ($iva_porcentaje / 100);
    $iva_bs = $iva_usd * $tasa_usd;

    // 3. Calcular IGTF (3% sobre Subtotal USD si el método es Efectivo)
    $igtf_porcentaje = ($factura['metodo_pago'] === 'Efectivo') ? 3 : 0;
    
    // NOTA: El IGTF se calcula sobre la base imponible de USD que se pagan en efectivo. 
    // Para simplificar, lo calculamos sobre el subtotal USD.
    $igtf_usd = $subtotal_usd_calculado * ($igtf_porcentaje / 100);
    $igtf_bs = $igtf_usd * $tasa_usd;
    
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
    <style>
        /* Ajuste de estilos para el diseño compacto y sin menú */
        body {
            /* Restablecer margen izquierdo */
            margin-left: 0 !important; 
        }
        .main-content {
            /* Asegurar que el contenido principal ocupe todo el ancho */
            margin-left: 0 !important;
            padding: 20px; 
        }
        .content-wrapper {
            /* Reducir el tamaño del contenedor para hacerlo más parecido a una factura */
            max-width: 650px; /* Ajuste para el nuevo CSS */
        }

        /* Estilos existentes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .metodo-pago-detalle {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 600;
            display: inline-block;
            background-color: #f0f0f0;
            color: #333;
        }
        .metodo-pago-detalle.efectivo {
            background-color: #d4edda;
            color: #155724;
        }
        .metodo-pago-detalle.pago-móvil, 
        .metodo-pago-detalle.transferencia {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        /* Ocultar el menú completamente para el caso de no incluir menu.php */
        @media screen {
            /* Asegura que no haya padding o margin extra del menú */
            .main-container {
                display: block;
            }
        }
        
    </style>
</head>
<body>
    
<main class="main-content">
    <div class="content-wrapper">
        <div class="factura-detalle">
            <div class="encabezado-factura">
                <div class="info-empresa">
                    <h2>NEXUS SYSTEM</h2>
                    <p>Rif: J-XXXXXXXXX</p>
                    <p>Av. Principal, Ciudad</p>
                    <p>Tel: (0000) 000-0000</p>
                </div>
                
                <div class="info-factura-detalle">
                    <div class="numero-factura">FACTURA #<?= $factura['nro_factura'] ?></div>
                    <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($factura['fecha'])) ?></p>
                    <p><strong>Método de Pago:</strong> 
                        <span class="metodo-pago-detalle <?= strtolower(str_replace(' ', '-', $factura['metodo_pago'])) ?>">
                            <?= htmlspecialchars($factura['metodo_pago']) ?>
                        </span>
                    </p>
                    <p><strong>Tasa USD:</strong> Bs. <?= number_format($tasa_usd, 2, ',', '.') ?></p>
                </div>
            </div>
            
            <div class="datos-cliente-detalle">
                <h3>Datos del Cliente</h3>
                <div class="cliente-info-detalle">
                    <div class="info-cliente-grid">
                        <div class="info-cliente-item">
                            <span class="info-cliente-label">Nombre:</span>
                            <span class="info-cliente-value"><?= htmlspecialchars($factura['cliente']) ?></span>
                        </div>
                        <div class="info-cliente-item">
                            <span class="info-cliente-label">Cédula/RIF:</span>
                            <span class="info-cliente-value"><?= htmlspecialchars($factura['cedula'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-cliente-item">
                            <span class="info-cliente-label">Teléfono:</span>
                            <span class="info-cliente-value"><?= htmlspecialchars($factura['telefono'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-cliente-item" style="grid-column: span 2;">
                            <span class="info-cliente-label">Dirección:</span>
                            <span class="info-cliente-value"><?= htmlspecialchars($factura['direccion'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="seccion-detalles">
                <h3>Detalles de la Compra</h3>
                <div class="tabla-detalles-container">
                    <table class="tabla-detalles">
                        <thead>
                            <tr>
                                <th>Cód.</th>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-right">P. Unit. ($)</th>
                                <th class="text-right">Subtotal (Bs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $detalle): ?>
                            <tr>
                                <td><?= htmlspecialchars($detalle['codigo_producto']) ?></td>
                                <td><?= htmlspecialchars($detalle['nombre_producto']) ?></td>
                                <td class="text-center"><?= number_format($detalle['cantidad'], 0, ',', '.') ?></td> 
                                <td class="text-right">$ <?= number_format($detalle['precio_unitario_usd'], 2, ',', '.') ?></td>
                                <td class="text-right">Bs. <?= number_format($detalle['subtotal_bs'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="seccion-totales-detalle">
                <div class="totales-detalle-container">
                    <div class="total-fila">
                        <span class="total-label-detalle">Subtotal (Base Imponible):</span>
                        <span class="total-value-detalle">Bs. <?= number_format($subtotal_bs_calculado, 2, ',', '.') ?></span> 
                    </div>
                    <div class="total-fila">
                        <span class="total-label-detalle">IVA (<?= $iva_porcentaje ?>%):</span>
                        <span class="total-value-detalle">Bs. <?= number_format($iva_bs, 2, ',', '.') ?></span>
                    </div>
                    <?php if ($factura['metodo_pago'] === 'Efectivo'): ?>
                    <div class="total-fila">
                        <span class="total-label-detalle">IGTF (3% Efectivo):</span>
                        <span class="total-value-detalle">Bs. <?= number_format($igtf_bs, 2, ',', '.') ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="total-fila total-final-detalle">
                        <span class="total-label-detalle">TOTAL (Bs):</span>
                        <span class="total-value-detalle">Bs. <?= number_format($factura['total_bs'], 2, ',', '.') ?></span>
                    </div>
                    <div class="total-fila total-final-detalle">
                        <span class="total-label-detalle">TOTAL (USD):</span>
                        <span class="total-value-detalle">$ <?= number_format($factura['total_usd'], 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>
            
            <div class="acciones-factura no-print">
                <button class="btn-imprimir" onclick="window.print()">
                    🖨 Imprimir / Generar PDF
                </button>
                <a href="facturas.php" class="btn-volver-factura">
                    ↩ Volver a Facturas
                </a>
            </div>
            
            <div class="pie-factura">
                <p><strong>¡Gracias por su compra!</strong></p>
                <p>Documento no fiscal. Conserve una copia.</p>
                <p>Sistema generado automáticamente - <?= date('d/m/Y H:i:s') ?></p>
            </div>
        </div>
    </div>
</main>
<script>
    // La función window.print() abre el diálogo de impresión del navegador.
    // Esto permite al usuario seleccionar "Guardar como PDF".
</script>
</body>
</html>