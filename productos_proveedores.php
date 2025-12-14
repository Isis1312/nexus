<?php
// nexus/productos_proveedores.php - Flujo de Compra Mejorado
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

// --- CONSULTA PARA ESTADÍSTICAS (Resuelve N/A) ---
if ($id_proveedor > 0) {
    $stats_sql = "
        SELECT 
            COALESCE(SUM(p.cantidad), 0) AS total_stock,
            COALESCE(SUM(hc.precio_total), 0) AS total_invertido,
            COALESCE(SUM(hc.total_unidades), 0) AS total_unidades_compradas
        FROM productos_proveedor pp_stats
        LEFT JOIN productos p ON pp_stats.id_producto_proveedor = p.id_producto_proveedor
        LEFT JOIN historial_compras hc ON pp_stats.id_producto_proveedor = hc.id_producto_proveedor
        WHERE pp_stats.id_proveedor = :id_proveedor
    ";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute(['id_proveedor' => $id_proveedor]);
} else {
    $stats_sql = "
        SELECT 
            COALESCE((SELECT SUM(cantidad) FROM productos WHERE estado = 'active'), 0) AS total_stock,
            COALESCE((SELECT SUM(precio_total) FROM historial_compras), 0) AS total_invertido,
            COALESCE((SELECT SUM(total_unidades) FROM historial_compras), 0) AS total_unidades_compradas
    ";
    $stats_stmt = $pdo->query($stats_sql);
}
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Obtener proveedores para filtro
$proveedores_stmt = $pdo->query("SELECT id_proveedor, nombre_comercial FROM proveedores WHERE estado = 'activo'");
$proveedores = $proveedores_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_productos = count($result);

// Mensajes de sesión
$mensaje = $_SESSION['mensaje'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['error']);

$fecha_hoy = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos de Proveedores - NEXUS</title>
    <link rel="stylesheet" href="css/proveedores.css">
    <style>
        /* Estilos CSS para el nuevo formulario de compra múltiple */
        .tabla-compras td {
            vertical-align: middle;
            text-align: center;
        }
        .tabla-compras input[type="number"] {
            width: 80px;
            text-align: center;
            padding: 5px;
            box-sizing: border-box;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        .tabla-compras .costo-calculado {
            font-size: 0.95em;
            font-weight: bold;
        }
        .total-row-compra {
            font-size: 1.2em;
            font-weight: bold;
            color: #008B8B;
            background-color: #e0f7fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .form-global-controls {
            background: #f8fdff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid rgba(0, 139, 139, 0.2);
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .form-global-controls .form-group {
            flex-grow: 1;
            min-width: 250px;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-success:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-success:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
    </style>
</head>
<body>
   <main class="main-content">
        <div class="content-wrapper">
            <div class="container">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px;">
                        <?php echo htmlspecialchars($error); ?>
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
                        <span class="stat-number"><?php echo number_format($stats['total_stock'], 0, ',', '.'); ?></span>
                        <span class="stat-label">Stock en Inventario</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number">$<?php echo number_format($stats['total_invertido'], 2, ',', '.'); ?></span>
                        <span class="stat-label">Total en Compras</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo number_format($stats['total_unidades_compradas'], 0, ',', '.'); ?></span>
                        <span class="stat-label">Unidades Compradas</span>
                    </div>
                </div>

                <div class="filtros-container">
                    <div class="filtro-group">
                        <form method="GET" action="" id="formBusqueda" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <label><strong> Buscar Producto:</strong></label>
                            <input type="text" name="busqueda" class="search-input" style="width: 250px;"
                                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                                   placeholder="Buscar por nombre, código o descripción...">
                            
                            <?php if ($id_proveedor > 0): ?>
                                <input type="hidden" name="id_proveedor" value="<?php echo $id_proveedor; ?>">
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary" style="padding: 8px 15px; font-size: 0.85em;">Buscar</button>
                            <a href="categorias.php" class="btn btn-secondary" style="padding: 8px 15px; font-size: 0.85em;">➕ Categoría</a>
                            <a href="agregar_producto_proveedor.php" class="btn btn-secondary" style="padding: 8px 15px; font-size: 0.85em;">➕ Producto de Proveedor</a>
                            <a href="proveedores.php" class="btn btn-secondary" style="padding: 8px 15px; font-size: 0.85em;">👥 Proveedores</a>
                            <?php if (!empty($busqueda)): ?>
                                <a href="?<?php echo $id_proveedor > 0 ? 'id_proveedor=' . $id_proveedor : ''; ?>" class="clear-search">
                                    Limpiar búsqueda
                                </a>
                            <?php endif; ?>
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

                <?php if ($total_productos > 0): ?>
                    <?php if ($id_proveedor > 0 && isset($result[0])): ?>
                        <div class="proveedor-info">
                            <h4>📋 Proveedor: <?php echo $result[0]['proveedor']; ?></h4>
                            <p>📞 Teléfono: <?php echo $result[0]['telefono_proveedor']; ?> | 
                            ✉️ Email: <?php echo $result[0]['email_proveedor']; ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="procesar_compras_multiples.php" onsubmit="return validarCompraMultiple(this)">
                    
                        <div class="form-global-controls">
                            <div class="form-group">
                                <label for="fecha_compra">Fecha de Compra</label>
                                <input type="date" name="fecha_compra" id="fecha_compra" class="form-control" 
                                    value="<?= $fecha_hoy ?>" required max="<?= $fecha_hoy ?>">
                                <small style="color: #6c757d;">Fecha de la transacción de compra.</small>
                            </div>
                            <div class="form-group">
                                <label for="fecha_vencimiento_base">Fecha de Vencimiento (Base)</label>
                                <input type="date" name="fecha_vencimiento_base" id="fecha_vencimiento_base" class="form-control" 
                                    value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required min="<?= $fecha_hoy ?>">
                                <small style="color: #6c757d;">Fecha de vencimiento para todos los productos.</small>
                            </div>
                            <div class="form-group" style="min-width: 150px; flex-grow: 0;">
                                <label>&nbsp;</label>
                                <button type="submit" id="btnProcesarCompra" class="btn btn-success" disabled>
                                    🛒 Procesar Compra Seleccionada
                                </button>
                            </div>
                        </div>

                        <div class="table-container">
                            <table class="table tabla-compras">
                                <thead>
                                    <tr>
                                        <th style="text-align: left;">Cód. Producto</th>
                                        <th style="text-align: left;">Nombre Producto</th>
                                        <?php if ($id_proveedor == 0): ?>
                                            <th>Proveedor</th>
                                        <?php endif; ?>
                                        <th>Unidad</th>
                                        <th>Cant. Empaques</th>
                                        <th>Unid. x Empaque</th>
                                        <th>Precio Total ($)</th>
                                        <th>P. Costo Unit. ($)</th>
                                        <th>P. Venta Calc. ($)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($result as $row): ?>
                                        <?php
                                        $id = $row['id_producto_proveedor'];
                                        $nombre = htmlspecialchars($row['nombre']);
                                        ?>
                                        <tr id="producto-row-<?= $id ?>">
                                            <td style="text-align: left;"><code><?php echo $row['codigo_producto']; ?></code></td>
                                            <td style="text-align: left;">
                                                <strong><?php echo $nombre; ?></strong>
                                                <small>(Categoría: <?= $row['nombre_categoria'] ?>)</small>
                                            </td>
                                            <?php if ($id_proveedor == 0): ?>
                                                <td><?php echo $row['proveedor']; ?></td>
                                            <?php endif; ?>
                                            <td><?php echo $row['unidad_medida']; ?></td>
                                            
                                            <td>
                                                <input type="number" name="productos[<?= $id ?>][empaques]" id="empaques_<?= $id ?>" 
                                                       min="0" max="100" value="0" oninput="calcularFila(<?= $id ?>)" data-id="<?= $id ?>" data-campo="empaques" required>
                                            </td>
                                            <td>
                                                <input type="number" name="productos[<?= $id ?>][unidades_x_empaque]" id="unidades_<?= $id ?>" 
                                                       min="1" max="1000" value="1" oninput="calcularFila(<?= $id ?>)" data-id="<?= $id ?>" data-campo="unidades" required>
                                            </td>
                                            <td>
                                                <input type="number" name="productos[<?= $id ?>][precio_total]" id="precio_total_<?= $id ?>" 
                                                       step="0.01" min="0" value="0.00" oninput="calcularFila(<?= $id ?>)" data-id="<?= $id ?>" data-campo="precio" required>
                                            </td>
                                            
                                            <td>
                                                <span id="costo_unitario_<?= $id ?>" class="costo-calculado" style="color: #e74c3c;">$0.00</span>
                                            </td>
                                            <td>
                                                <span id="precio_venta_<?= $id ?>" class="costo-calculado" style="color: #28a745;">$0.00</span>
                                            </td>
                                            
                                            <input type="hidden" name="productos[<?= $id ?>][id_producto_proveedor]" value="<?= $id ?>">
                                            <input type="hidden" name="productos[<?= $id ?>][seleccionar]" id="select_flag_<?= $id ?>" value="off">
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                    
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

<script>
    // Variables globales para rastrear los productos listos para la compra
    let productosListosParaComprar = new Set();
    const btnProcesarCompra = document.getElementById('btnProcesarCompra');
    
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar cálculos para cada fila al cargar 
        document.querySelectorAll('.tabla-compras input[type="number"]').forEach(input => {
            const id = input.getAttribute('data-id');
            if (id) {
                calcularFila(parseInt(id));
            }
        });
        actualizarBotonProcesar();
    });

    function calcularFila(id) {
        const empaquesInput = document.getElementById(`empaques_${id}`);
        const unidadesInput = document.getElementById(`unidades_${id}`);
        const precioTotalInput = document.getElementById(`precio_total_${id}`);
        const costoUnitarioSpan = document.getElementById(`costo_unitario_${id}`);
        const precioVentaSpan = document.getElementById(`precio_venta_${id}`);
        const selectFlagInput = document.getElementById(`select_flag_${id}`);
        
        const empaques = parseInt(empaquesInput.value) || 0;
        const unidades = parseInt(unidadesInput.value) || 1; 
        const precioTotal = parseFloat(precioTotalInput.value) || 0;
        
        const totalUnidades = empaques * unidades;
        
        let costoUnitario = 0;
        let precioVenta = 0;

        if (totalUnidades > 0 && precioTotal > 0) {
            costoUnitario = precioTotal / totalUnidades;
            // Margen del 30% para el precio de venta
            precioVenta = costoUnitario * 1.30; 
        }

        costoUnitarioSpan.textContent = `$${costoUnitario.toFixed(2)}`;
        precioVentaSpan.textContent = `$${precioVenta.toFixed(2)}`;
        
        // Determinar si el producto es válido para la compra
        const esValido = empaques > 0 && precioTotal > 0 && totalUnidades > 0 && totalUnidades <= 200;
        
        // Manejar el límite de stock de lote (200 unidades)
        if (totalUnidades > 200) {
             costoUnitarioSpan.style.color = '#dc3545';
             precioVentaSpan.style.color = '#dc3545';
        } else {
             costoUnitarioSpan.style.color = '#e74c3c';
             precioVentaSpan.style.color = '#28a745';
        }


        // Registrar el estado para el envío
        if (esValido) {
            productosListosParaComprar.add(id);
            selectFlagInput.value = 'on';
        } else {
            productosListosParaComprar.delete(id);
            selectFlagInput.value = 'off';
        }
        
        actualizarBotonProcesar();
    }
    
    function actualizarBotonProcesar() {
        const totalProductosValidos = productosListosParaComprar.size;
        
        if (totalProductosValidos > 0) {
            btnProcesarCompra.disabled = false;
            btnProcesarCompra.innerHTML = `🛒 Procesar Compra (${totalProductosValidos} Prod.)`;
        } else {
            btnProcesarCompra.disabled = true;
            btnProcesarCompra.innerHTML = '🛒 Procesar Compra Seleccionada';
        }
    }

    function validarCompraMultiple(form) {
        if (productosListosParaComprar.size === 0) {
            alert('Debe ingresar cantidades y precios válidos (mayores a cero) en al menos un producto para procesar la compra.');
            return false;
        }
        
        const fechaCompra = document.getElementById('fecha_compra').value;
        const fechaVencimiento = document.getElementById('fecha_vencimiento_base').value;

        if (!fechaCompra || !fechaVencimiento) {
            alert('Debe seleccionar la Fecha de Compra y la Fecha de Vencimiento.');
            return false;
        }

        if (!confirm(`¿Está seguro de registrar la compra para los ${productosListosParaComprar.size} productos seleccionados?`)) {
            return false;
        }

        // Antes de enviar, deshabilitar los campos de los productos que NO van a ser comprados
        document.querySelectorAll('.tabla-compras input[data-campo]').forEach(input => {
            const id = parseInt(input.getAttribute('data-id'));
            if (!productosListosParaComprar.has(id)) {
                 input.name = ''; // Anula el envío de los inputs numéricos no seleccionados
            }
        });
        
        // Solo enviar los flags de selección 'on'
        document.querySelectorAll('input[id^="select_flag_"]').forEach(flagInput => {
             const id = parseInt(flagInput.id.replace('select_flag_', ''));
             if (flagInput.value === 'off') {
                 flagInput.name = '';
             }
        });

        btnProcesarCompra.disabled = true;
        btnProcesarCompra.innerHTML = '⏳ Procesando...';
        return true;
    }
</script>

</body>
</html>