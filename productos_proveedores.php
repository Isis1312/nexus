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

// Inicializar carrito de compras si no existe
if (!isset($_SESSION['carrito_compras'])) {
    $_SESSION['carrito_compras'] = [];
}

// --- LÓGICA DEL CARRITO (Agregar, Eliminar, Vaciar) ---
if (isset($_GET['accion']) && isset($_GET['id_producto'])) {
    $id_producto = intval($_GET['id_producto']);
    
    switch ($_GET['accion']) {
        case 'agregar_carrito':
            if (isset($_GET['cantidad_empaques']) && isset($_GET['unidades_empaque'])) {
                $cantidad_empaques = intval($_GET['cantidad_empaques']);
                $unidades_empaque = intval($_GET['unidades_empaque']);
                $precio_total = floatval($_GET['precio_total']);
                
                // Buscar si el producto ya está en el carrito
                $index = array_search($id_producto, array_column($_SESSION['carrito_compras'], 'id_producto'));
                
                if ($index !== false) {
                    // Actualizar cantidad
                    $_SESSION['carrito_compras'][$index]['cantidad_empaques'] += $cantidad_empaques;
                    $_SESSION['carrito_compras'][$index]['unidades_empaque'] = $unidades_empaque;
                    $_SESSION['carrito_compras'][$index]['precio_total'] += $precio_total;
                } else {
                    // Agregar nuevo producto al carrito
                    $_SESSION['carrito_compras'][] = [
                        'id_producto' => $id_producto,
                        'cantidad_empaques' => $cantidad_empaques,
                        'unidades_empaque' => $unidades_empaque,
                        'precio_total' => $precio_total
                    ];
                }
                
                $_SESSION['mensaje'] = "Producto agregado al carrito";
            }
            break;
            
        case 'eliminar_carrito':
            $index = array_search($id_producto, array_column($_SESSION['carrito_compras'], 'id_producto'));
            if ($index !== false) {
                array_splice($_SESSION['carrito_compras'], $index, 1);
                $_SESSION['mensaje'] = "Producto eliminado del carrito";
            }
            break;
            
        case 'vaciar_carrito':
            $_SESSION['carrito_compras'] = [];
            $_SESSION['mensaje'] = "Carrito vaciado";
            break;
    }
    
    // Redirigir para evitar reenvío del formulario
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// --- FILTROS Y BÚSQUEDA ---
$id_proveedor = isset($_GET['id_proveedor']) ? intval($_GET['id_proveedor']) : 0;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

$sql_base = "SELECT 
                pp.*,
                cp.nombre_categoria,
                s.nombre_subcategoria,
                p.nombre_comercial as proveedor,
                p.telefono as telefono_proveedor,
                p.email as email_proveedor
            FROM productos_proveedor pp
            JOIN categoria_prod cp ON pp.id_categoria = cp.id
            LEFT JOIN subcategorias s ON pp.id_subcategoria = s.id
            JOIN proveedores p ON pp.id_proveedor = p.id_proveedor";

$params = [];
$where_conditions = [];

if ($id_proveedor > 0) {
    $where_conditions[] = "pp.id_proveedor = ?";
    $params[] = $id_proveedor;
    $titulo = "Productos del Proveedor";
} else {
    $titulo = "Todos los Productos de Proveedores";
}

if (!empty($busqueda)) {
    $where_conditions[] = "(pp.nombre LIKE ? OR pp.codigo_producto LIKE ? OR pp.descripcion LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $titulo = "Búsqueda: \"$busqueda\"";
}

if (!empty($where_conditions)) {
    $sql_base .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql_base .= " ORDER BY pp.nombre";

if (!empty($params)) {
    $stmt = $pdo->prepare($sql_base);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query($sql_base);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener proveedores para filtro
$proveedores_stmt = $pdo->query("SELECT id_proveedor, nombre_comercial FROM proveedores WHERE estado = 'activo'");
$proveedores = $proveedores_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_productos = count($result);

// --- CALCULAR ESTADÍSTICAS DEL CARRITO ---
$total_carrito = 0;
$total_productos_carrito = 0;
$total_unidades_carrito = 0;
$carrito_detalles = [];

if (!empty($_SESSION['carrito_compras'])) {
    foreach ($_SESSION['carrito_compras'] as $item) {
        $stmt = $pdo->prepare("SELECT pp.*, p.nombre_comercial FROM productos_proveedor pp 
                              JOIN proveedores p ON pp.id_proveedor = p.id_proveedor 
                              WHERE pp.id_producto_proveedor = ?");
        $stmt->execute([$item['id_producto']]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            $producto['cantidad_empaques'] = $item['cantidad_empaques'];
            $producto['unidades_empaque'] = $item['unidades_empaque'];
            $producto['precio_total'] = $item['precio_total'];
            $producto['total_unidades'] = $item['cantidad_empaques'] * $item['unidades_empaque'];
            
            $carrito_detalles[] = $producto;
            $total_carrito += $item['precio_total'];
            $total_unidades_carrito += $producto['total_unidades'];
            $total_productos_carrito++;
        }
    }
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos de Proveedores - NEXUS</title>
    <link rel="stylesheet" href="css/proveedores.css">
</head>
<body>
   <main class="main-content">
        <div class="content-wrapper">
            <div class="container">
                <?php if (isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">
                        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="header">
                    <h1>🏭 <?php echo $titulo; ?></h1>
                    <p>Gestiona los productos disponibles de tus proveedores</p>
                </div>

                <div class="stats-container">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_productos; ?></span>
                        <span class="stat-label">Total Productos</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_productos_carrito; ?></span>
                        <span class="stat-label">Productos en Carrito</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number">$<?php echo number_format($total_carrito, 2); ?></span>
                        <span class="stat-label">Total Carrito</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_unidades_carrito; ?></span>
                        <span class="stat-label">Unidades en Carrito</span>
                    </div>
                </div>

                <div class="filtros-container">
                    <div class="filtro-group">
                        <form method="GET" action="" id="formBusqueda">
                            <label><strong> Buscar Producto:</strong></label>
                            <div class="search-box">
                                <input type="text" name="busqueda" class="search-input" 
                                       value="<?php echo htmlspecialchars($busqueda); ?>" 
                                       placeholder="Buscar por nombre, código o descripción...">
                            </div>
                            <?php if ($id_proveedor > 0): ?>
                                <input type="hidden" name="id_proveedor" value="<?php echo $id_proveedor; ?>">
                            <?php endif; ?>
                            <div style="margin-top: 5px;">
                                <button type="submit" class="btn btn-primary" style="padding: 8px 15px; font-size: 0.85em;">Buscar</button>
                    
                                <a href="categorias.php" class="btn btn-primary">➕ Agregar Categoría</a>
                                <a href="agregar_producto_proveedor.php" class="btn btn-primary">➕ Agregar Producto de Proveedor</a>
                                <a href="proveedores.php" class="btn btn-secondary">👥 Ver Proveedores</a>
                                <?php if (!empty($busqueda)): ?>
                                    <a href="?<?php echo $id_proveedor > 0 ? 'id_proveedor=' . $id_proveedor : ''; ?>" class="clear-search">
                                        Limpiar búsqueda
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="filtro-group compact filtro-proveedor">
                        <form method="GET" action="" id="filtroProveedor">
                            <label><strong>Filtrar por Proveedor:</strong></label>
                            <select name="id_proveedor" class="form-control" onchange="this.form.submit()">
                                <option value="0">Todos los proveedores</option>
                                <?php foreach($proveedores as $prov): ?>
                                    <option value="<?php echo $prov['id_proveedor']; ?>" 
                                        <?php echo ($id_proveedor == $prov['id_proveedor']) ? 'selected' : ''; ?>>
                                        <?php echo $prov['nombre_comercial']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($busqueda)): ?>
                                <input type="hidden" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                
<?php if (!empty($carrito_detalles)): ?>
    <div class="carrito-container" style="margin-bottom: 30px; background: #fff; border-radius: 10px; padding: 20px; border: 2px solid #28a745;">
        <h3 style="color: #28a745; margin-bottom: 15px;">🛒 Carrito de Compras</h3>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: rgba(40, 167, 69, 0.1);">
                        <th style="padding: 10px; text-align: left;">Producto</th>
                        <th style="padding: 10px; text-align: center;">Empaques</th>
                        <th style="padding: 10px; text-align: center;">Unid/Emp</th>
                        <th style="padding: 10px; text-align: center;">Total Unid</th>
                        <th style="padding: 10px; text-align: right;">Precio Total</th>
                        <th style="padding: 10px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carrito_detalles as $item): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;">
                                <strong><?php echo htmlspecialchars($item['nombre']); ?></strong><br>
                                <small>Código: <?php echo $item['codigo_producto']; ?></small><br>
                                <small>Proveedor: <?php echo $item['nombre_comercial']; ?></small>
                            </td>
                            <td style="padding: 10px; text-align: center;">
                                <?php echo $item['cantidad_empaques']; ?>
                            </td>
                            <td style="padding: 10px; text-align: center;">
                                <?php echo $item['unidades_empaque']; ?>
                            </td>
                            <td style="padding: 10px; text-align: center; font-weight: bold;">
                                <?php echo $item['total_unidades']; ?>
                            </td>
                            <td style="padding: 10px; text-align: right; font-weight: bold; color: #28a745;">
                                $<?php echo number_format($item['precio_total'], 2); ?>
                            </td>
                            <td style="padding: 10px; text-align: center;">
                                <a href="?accion=eliminar_carrito&id_producto=<?php echo $item['id_producto_proveedor']; ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('¿Eliminar este producto del carrito?')">
                                    ❌ Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: rgba(40, 167, 69, 0.05); font-weight: bold;">
                        <td colspan="3" style="padding: 10px; text-align: right;">TOTALES:</td>
                        <td style="padding: 10px; text-align: center; color: #28a745;">
                            <?php echo $total_unidades_carrito; ?> unidades
                        </td>
                        <td style="padding: 10px; text-align: right; color: #28a745; font-size: 1.1em;">
                            $<?php echo number_format($total_carrito, 2); ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <div style="display: flex; gap: 10px; justify-content: center;">
                                <form method="GET" action="" style="display: inline;">
                                    <input type="hidden" name="accion" value="vaciar_carrito">
                                    <button type="submit" class="btn btn-secondary btn-sm"
                                            onclick="return confirm('¿Está seguro de vaciar todo el carrito?')">
                                        🗑️ Vaciar
                                    </button>
                                </form>
                                <button class="btn btn-success btn-sm" onclick="abrirModalConfirmarCompra()">
                                    ✅ Confirmar Compra
                                </button>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

                <?php if ($total_productos > 0): ?>
                    <?php if ($id_proveedor > 0 && isset($result[0])): ?>
                        <div class="proveedor-info">
                            <h4>📋 Proveedor: <?php echo $result[0]['proveedor']; ?></h4>
                            <p>📞 Teléfono: <?php echo $result[0]['telefono_proveedor']; ?> | 
                            ✉️ Email: <?php echo $result[0]['email_proveedor']; ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($busqueda)): ?>
                        <div class="proveedor-info" style="background: rgba(40, 167, 69, 0.1); border-color: rgba(40, 167, 69, 0.3);">
                            <h4>🔍 Resultados de búsqueda</h4>
                            <p>Se encontraron <strong><?php echo $total_productos; ?></strong> productos que coinciden con "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"</p>
                            <a href="?<?php echo $id_proveedor > 0 ? 'id_proveedor=' . $id_proveedor : ''; ?>" class="clear-search">
                                ✕ Ver todos los productos
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Subcategoría</th>
                                    <?php if ($id_proveedor == 0): ?>
                                        <th>Proveedor</th>
                                    <?php endif; ?>
                                    <th>Precio de compra</th>
                                    <th>Unidad</th>
                                    <th>Fecha Compra</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($result as $row): ?>
                                    <?php 
                                    // Verificar si el producto está en el carrito
                                    $en_carrito = false;
                                    $carrito_index = -1;
                                    if (!empty($_SESSION['carrito_compras'])) {
                                        $carrito_index = array_search($row['id_producto_proveedor'], array_column($_SESSION['carrito_compras'], 'id_producto'));
                                        $en_carrito = ($carrito_index !== false);
                                    }
                                    ?>
                                    <tr id="producto-<?php echo $row['id_producto_proveedor']; ?>">
                                        <td><code><?php echo $row['codigo_producto']; ?></code></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['nombre']); ?></strong>
                                            <?php if ($row['es_perecedero']): ?>
                                                <br><span class="badge badge-warning">🕒 Perecedero</span>
                                            <?php endif; ?>
                                            <?php if ($en_carrito): ?>
                                                <br><span class="badge badge-success">🛒 En carrito (<?php echo $_SESSION['carrito_compras'][$carrito_index]['cantidad_empaques']; ?> empaques)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $row['nombre_categoria']; ?></td>
                                        <td><?php echo $row['nombre_subcategoria'] ?: '—'; ?></td>
                                        <?php if ($id_proveedor == 0): ?>
                                            <td>
                                                <?php echo $row['proveedor']; ?>
                                                <br><small>📞 <?php echo $row['telefono_proveedor']; ?></small>
                                            </td>
                                        <?php endif; ?>
                                        <td style="font-weight: bold; color: #28a745;">
                                            $<?php echo number_format($row['precio_compra'], 2); ?>
                                        </td>
                                        <td>
                                            <?php if ($row['fecha_compra']): ?>
                                                <span class="badge badge-info"><?php echo date('d/m/Y', strtotime($row['fecha_compra'])); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">No registrada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons-container">
                                                <a href="editar_producto_proveedor.php?id=<?php echo $row['id_producto_proveedor']; ?>" class="btn-editar">
                                                    ✎ Editar
                                                </a>
                                                <button class="btn-comprar" 
                                                        onclick="abrirModalCompra(<?php echo $row['id_producto_proveedor']; ?>, 
                                                                                 '<?php echo addslashes($row['nombre']); ?>', 
                                                                                 <?php echo $row['precio_compra']; ?>)">
                                                    🛒 Comprar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <?php if (!empty($busqueda)): ?>
                            <h3>🔍 No se encontraron productos</h3>
                            <p>No hay productos que coincidan con "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"</p>
                            <a href="?<?php echo $id_proveedor > 0 ? 'id_proveedor=' . $id_proveedor : ''; ?>" class="btn btn-primary">
                                Ver todos los productos
                            </a>
                        <?php else: ?>
                            <h3>📦 No hay productos de proveedores registrados</h3>
                            <p>Comienza agregando productos a tus proveedores.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
   </main>

    <div id="modalCompra" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Agregar Producto al Carrito</h3>
                <span class="close-modal" onclick="cerrarModalCompra()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formAgregarCarrito" method="GET" action="">
                    <input type="hidden" name="accion" value="agregar_carrito">
                    <input type="hidden" id="carrito_id_producto" name="id_producto">
                    
                    <div class="form-group">
                        <label>Producto:</label>
                        <input type="text" id="carrito_nombre_producto" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="precio_compra_total" class="required">Precio Total de la Compra ($)</label>
                        <div class="input-with-symbol">
                            <span class="currency-symbol">$</span>
                            <input type="number" id="precio_compra_total" name="precio_total" 
                                   step="0.01" class="form-control with-symbol" 
                                   required min="0" placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="cantidad_empaques" class="required">Cantidad de Empaques</label>
                            <input type="number" name="cantidad_empaques" id="cantidad_empaques" class="form-control" 
                                min="1" max="1000" required placeholder="Ej: 14 (cajas, paquetes, etc.)"
                                oninput="calcularTotal()">
                            <small style="color: #666;">Cantidad de empaques recibidos</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="unidades_empaque" class="required">Unidades por Empaque</label>
                            <input type="number" name="unidades_empaque" id="unidades_empaque" class="form-control" 
                                min="1" max="1000" required placeholder="Ej: 9 (unidades por caja)"
                                oninput="calcularTotal()">
                            <small style="color: #666;">Unidades individuales por empaque</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Total de Unidades para Inventario:</label>
                        <input type="number" id="cantidad_total" class="form-control" readonly 
                            style="background-color: #e8f5e8; font-weight: bold; color: #2e7d32;">
                        <small style="color: #666;">Se calcula automáticamente: Empaques × Unidades por empaque</small>
                    </div>

                    <div class="form-group">
                        <label>Precio de Compra por Unidad:</label>
                        <input type="text" id="precio_compra_unidad" class="form-control" readonly 
                            style="background-color: #e8f5e8; font-weight: bold; color: #2e7d32;">
                    </div>

                    <div class="form-group">
                        <label>Precio de Venta por Unidad (30%):</label>
                        <input type="text" id="precio_venta_unidad" class="form-control" readonly 
                            style="background-color: #e3f2fd; font-weight: bold; color: #1565c0;">
                    </div>
                    
                    <div class="alert-info">
                        <strong>💡 Información:</strong><br>
                        • <strong>Precio total de la compra:</strong> Total pagado al proveedor por todo el lote<br>
                        • <strong>Empaques:</strong> Cantidad de cajas/paquetes recibidos<br>
                        • <strong>Unidades por empaque:</strong> Cantidad de productos individuales por empaque<br>
                        • <strong>Precio de venta:</strong> Se calcula automáticamente (precio compra por unidad + 30%)<br>
                        • <strong>Stock máximo:</strong> 200 unidades por producto
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="cerrarModalCompra()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">🛒 Agregar al Carrito</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="modalConfirmarCompra" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✅ Confirmar Compra Completa</h3>
                <span class="close-modal" onclick="cerrarModalConfirmarCompra()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formConfirmarCompra" method="POST" action="procesar_compras_multiples.php">
                    <div id="resumen-compra">
                        <h4>Resumen de la Compra</h4>
                        <div id="detalles-resumen" style="max-height: 300px; overflow-y: auto; margin-bottom: 20px;">
                            </div>
                        
                        <div class="resumen-total" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="fecha_compra_total" class="required">Fecha de Compra</label>
                                    <input type="date" name="fecha_compra" id="fecha_compra_total" class="form-control" 
                                        value="<?php echo date('Y-m-d'); ?>" required
                                        max="<?php echo date('Y-m-d'); ?>">
                                    <small style="color: #666;">No puede ser posterior a hoy</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="fecha_vencimiento_base" class="required">Fecha Base de Vencimiento</label>
                                    <input type="date" name="fecha_vencimiento_base" id="fecha_vencimiento_base" class="form-control" 
                                        value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required
                                        min="<?php echo date('Y-m-d'); ?>">
                                    <small style="color: #666;">Fecha base para todos los productos (se puede ajustar por producto)</small>
                                </div>
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                                <h4 style="color: #28a745; margin-bottom: 10px;">TOTAL GENERAL</h4>
                                <div style="font-size: 1.5em; font-weight: bold; color: #28a745;">
                                    $<span id="total-general"><?php echo number_format($total_carrito, 2); ?></span>
                                </div>
                                <div style="color: #666; font-size: 0.9em;">
                                    <?php echo $total_unidades_carrito; ?> unidades en total
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="cerrarModalConfirmarCompra()">Cancelar</button>
                        <button type="submit" class="btn btn-success">✅ Confirmar y Procesar Todas las Compras</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

                

            </div>
        </div>
    </div>

 <script>
let productoActual = null;
let precioUnitarioActual = 0;

function calcularTotal() {
    const precioCompraTotal = parseFloat(document.getElementById('precio_compra_total').value) || 0;
    const empaques = parseInt(document.getElementById('cantidad_empaques').value) || 0;
    const unidadesPorEmpaque = parseInt(document.getElementById('unidades_empaque').value) || 0;
    
    const totalUnidades = empaques * unidadesPorEmpaque;
    
    // Calcular precio por unidad
    const precioPorUnidad = totalUnidades > 0 ? precioCompraTotal / totalUnidades : 0;
    
    // 30% de margen
    const precioVentaPorUnidad = precioPorUnidad * 1.30; 
    
    document.getElementById('cantidad_total').value = totalUnidades;
    document.getElementById('precio_compra_unidad').value = totalUnidades > 0 ? '$' + precioPorUnidad.toFixed(2) : '';
    document.getElementById('precio_venta_unidad').value = totalUnidades > 0 ? '$' + precioVentaPorUnidad.toFixed(2) : '';
    
    const totalInput = document.getElementById('cantidad_total');
    if (totalUnidades > 0) {
        totalInput.style.backgroundColor = '#e8f5e8';
        totalInput.style.color = '#2e7d32';
    } else {
        totalInput.style.backgroundColor = '#ffebee';
        totalInput.style.color = '#c62828';
    }
}

function cerrarModalCompra() {
    document.getElementById('modalCompra').style.display = 'none';
    productoActual = null;
    precioUnitarioActual = 0;
}

function abrirModalConfirmarCompra() {
    cargarDetallesCarrito();
    document.getElementById('modalConfirmarCompra').style.display = 'block';
}

function cerrarModalConfirmarCompra() {
    document.getElementById('modalConfirmarCompra').style.display = 'none';
}

function cargarDetallesCarrito() {
    // Función placeholder, se puede expandir según necesidad
}

// Validaciones de fecha
document.getElementById('fecha_compra_total')?.addEventListener('change', function() {
    const hoy = new Date().toISOString().split('T')[0];
    if (this.value > hoy) {
        alert('La fecha de compra no puede ser posterior a hoy');
        this.value = hoy;
    }
});

document.getElementById('fecha_vencimiento_base')?.addEventListener('change', function() {
    const hoy = new Date().toISOString().split('T')[0];
    if (this.value < hoy) {
        alert('La fecha de vencimiento no puede ser anterior a hoy');
        this.value = hoy;
    }
});

// Cierre de modales al hacer clic fuera
window.onclick = function(event) {
    const modalCompra = document.getElementById('modalCompra');
    const modalCategoria = document.getElementById('modalCategoria');
    const modalConfirmar = document.getElementById('modalConfirmarCompra');
    
    if (event.target === modalCompra) cerrarModalCompra();
    if (event.target === modalCategoria) cerrarModalCategoria();
    if (event.target === modalConfirmar) cerrarModalConfirmarCompra();
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) alert('✅ ' + urlParams.get('success'));
    if (urlParams.has('error')) alert('❌ ' + urlParams.get('error'));
});
</script>
</body>
</html>