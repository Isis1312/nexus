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

// --- CONFIGURACIÓN DE PÁGINA ---
$titulo = "Gestión de Productos de Proveedores";
$mensaje = $_SESSION['mensaje'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['error']);


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
    $where_conditions[] = "pp.id_proveedor = :id_proveedor";
    $params[':id_proveedor'] = $id_proveedor;
    
    // Obtener el nombre del proveedor para el título (USO DE $pdo)
    try {
        $stmt_prov = $pdo->prepare("SELECT nombre_comercial FROM proveedores WHERE id_proveedor = :id");
        $stmt_prov->execute([':id' => $id_proveedor]);
        $prov_nombre = $stmt_prov->fetchColumn();
        if ($prov_nombre) {
            $titulo = "Productos de: " . htmlspecialchars($prov_nombre);
        }
    } catch (PDOException $e) {
        $error .= " Error al obtener el nombre del proveedor: " . $e->getMessage();
    }
}

if (!empty($busqueda)) {
    $where_conditions[] = "(pp.nombre LIKE :busqueda OR pp.codigo_producto LIKE :busqueda OR pp.descripcion LIKE :busqueda)";
    $params[':busqueda'] = '%' . $busqueda . '%';
}

if (count($where_conditions) > 0) {
    $sql_base .= " WHERE " . implode(' AND ', $where_conditions);
}

$sql_base .= " ORDER BY p.nombre_comercial, pp.nombre";

// --- CONSULTA DE DATOS ---
try {
    $stmt = $pdo->prepare($sql_base); // CORRECCIÓN: usar $pdo
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_productos = count($result);
} catch (PDOException $e) {
    $error = "Error al obtener los productos: " . $e->getMessage();
    $result = [];
    $total_productos = 0;
}

// --- ESTADÍSTICAS GLOBALES/PROVEEDOR ---
// CORRECCIÓN: La función ahora recibe $pdo
function getStats($pdo, $id_proveedor) {
    $stats = [
        'total_stock' => 0,
        'total_invertido' => 0.0,
        'total_unidades_compradas' => 0
    ];
    $params = [];
    $where = '';
    
    if ($id_proveedor > 0) {
        $where = "WHERE pp.id_proveedor = :id_proveedor";
        $params[':id_proveedor'] = $id_proveedor;
    }

    // Stock total actual (usando p.cantidad en lugar de p.stock)
    $sql_stock = "SELECT SUM(p.cantidad) FROM productos p 
                  JOIN productos_proveedor pp ON p.id_producto_proveedor = pp.id_producto_proveedor $where";
    $stmt_stock = $pdo->prepare($sql_stock); // CORRECCIÓN: usar $pdo
    $stmt_stock->execute($params);
    $stats['total_stock'] = (int)$stmt_stock->fetchColumn() ?: 0;
    
    // Total invertido histórico (total_precio_total de historial_compras)
    $sql_invertido = "SELECT SUM(hc.precio_total) FROM historial_compras hc 
                      JOIN productos_proveedor pp ON hc.id_producto_proveedor = pp.id_producto_proveedor $where";
    $stmt_invertido = $pdo->prepare($sql_invertido); // CORRECCIÓN: usar $pdo
    $stmt_invertido->execute($params);
    $stats['total_invertido'] = (float)$stmt_invertido->fetchColumn() ?: 0.0;

    // Total unidades compradas histórico (total_unidades de historial_compras)
    $sql_unidades = "SELECT SUM(hc.total_unidades) FROM historial_compras hc
                     JOIN productos_proveedor pp ON hc.id_producto_proveedor = pp.id_producto_proveedor $where";
    $stmt_unidades = $pdo->prepare($sql_unidades); // CORRECCIÓN: usar $pdo
    $stmt_unidades->execute($params);
    $stats['total_unidades_compradas'] = (int)$stmt_unidades->fetchColumn() ?: 0;

    return $stats;
}

$stats = getStats($pdo, $id_proveedor); // CORRECCIÓN: pasar $pdo

// --- LISTA DE PROVEEDORES (para el filtro) ---
$stmt_proveedores = $pdo->query("SELECT id_proveedor, nombre_comercial FROM proveedores WHERE estado = 'activo'"); // CORRECCIÓN: usar $pdo
$proveedores = $stmt_proveedores->fetchAll(PDO::FETCH_ASSOC);

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
        
        /* Nuevo estilo para la fila de totales en el pie de la tabla (TFOOT) */
        .tabla-compras tfoot td {
            font-size: 1.1em;
            background-color: #f0f8ff; 
            border-top: 3px solid #008B8B; 
        }
        /* Resaltar los valores totales de inversión y unidades */
        .tabla-compras tfoot #total_precio_compra,
        .tabla-compras tfoot #total_unidades_compra {
            color: #008B8B;
            font-weight: bold;
            font-size: 1.2em;
            background-color: #e0f7fa; /* Fondo más claro para los totales clave */
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
                        <span class="stat-label">Total Histórico en Compras</span> 
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo number_format($stats['total_unidades_compradas'], 0, ',', '.'); ?></span>
                        <span class="stat-label">Unidades Históricas Compradas</span> 
                    </div>
                </div>

                <div class="filtros-container">
                    <div class="filtro-group">
                        <form method="GET" action="" id="formBusqueda" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <label><strong> Buscar Producto:</strong></label>
                            <input type="text" name="busqueda" class="search-input" style="width: 250px;"
                                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                                   placeholder="Buscar por nombre">
                            
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
                                <tfoot>
                                    <tr>
                                        <?php $colspan_total_label = ($id_proveedor == 0) ? 4 : 3; ?>
                                        <td colspan="<?= $colspan_total_label ?>" style="text-align: right;"><strong>TOTALES DE ESTA COMPRA:</strong></td>
                                        
                                        <td id="total_empaques_compra">0</td>
                                        
                                        <td id="total_unidades_compra" style="color: #008B8B; background-color: #e0f7fa;">0</td>
                                        
                                        <td id="total_precio_compra" style="color: #008B8B;">$0.00</td>
                                        
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
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
        calcularTotalCompra(); // Calcular totales iniciales
    });

    /**
     * Calcula los costos unitarios y el precio de venta sugerido para una fila.
     * Marca la fila como seleccionada si tiene cantidades y precios válidos.
     */
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
            // Margen del 30% para el precio de venta sugerido
            precioVenta = costoUnitario * 1.30; 
        }

        costoUnitarioSpan.textContent = `$${costoUnitario.toFixed(2)}`;
        precioVentaSpan.textContent = `$${precioVenta.toFixed(2)}`;
        
        // Determinar si el producto es válido para la compra
        const esValido = empaques > 0 && precioTotal > 0 && totalUnidades > 0;
        
        // Manejo de colores de costo/venta (opcional, para visual)
        costoUnitarioSpan.style.color = esValido ? '#e74c3c' : '#bdc3c7';
        precioVentaSpan.style.color = esValido ? '#28a745' : '#bdc3c7';

        // Registrar el estado para el envío
        if (esValido) {
            productosListosParaComprar.add(id);
            selectFlagInput.value = 'on';
        } else {
            productosListosParaComprar.delete(id);
            selectFlagInput.value = 'off';
        }
        
        actualizarBotonProcesar();
        calcularTotalCompra(); // Llama a la función de totales para actualizar el TFOOT
    }
    
    /**
     * Calcula y actualiza los totales de la compra actual en el pie de la tabla.
     */
    function calcularTotalCompra() {
        let totalPrecioCompra = 0;
        let totalEmpaques = 0;
        let totalUnidades = 0;

        // Recorrer todos los inputs de precio_total y empaques
        document.querySelectorAll('input[id^="precio_total_"]').forEach(inputPrecio => {
            const id = parseInt(inputPrecio.id.replace('precio_total_', ''));
            const empaquesInput = document.getElementById(`empaques_${id}`);
            const unidadesXEmpaqueInput = document.getElementById(`unidades_${id}`);

            const precioTotal = parseFloat(inputPrecio.value) || 0;
            const empaques = parseInt(empaquesInput.value) || 0;
            const unidadesXEmpaque = parseInt(unidadesXEmpaqueInput.value) || 1;

            // Solo sumar si el producto está marcado como válido para la compra
            if (productosListosParaComprar.has(id)) {
                 totalPrecioCompra += precioTotal;
                 totalEmpaques += empaques;
                 totalUnidades += (empaques * unidadesXEmpaque);
            }
        });
        
        // Actualizar los elementos en el pie de la tabla (tfoot)
        document.getElementById('total_empaques_compra').textContent = totalEmpaques.toString();
        document.getElementById('total_unidades_compra').textContent = totalUnidades.toString(); 
        document.getElementById('total_precio_compra').textContent = `$${totalPrecioCompra.toFixed(2)}`;
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