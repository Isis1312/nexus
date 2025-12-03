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

$proveedores_stmt = $pdo->query("SELECT id_proveedor, nombre_comercial FROM proveedores WHERE estado = 'activo'");
$proveedores = $proveedores_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_productos = count($result);
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

             <?php if (isset($_SESSION['mensaje'])): ?>
      <div class="alert alert-success">
        <?= $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
      </div>

    <?php endif; ?>

            <div class="container">
                <div class="header">
                    <h1>🏭 <?php echo $titulo; ?></h1>
                    <p>Gestiona los productos disponibles de tus proveedores</p>
                </div>

                <!-- Estadísticas -->
                <div class="stats-container">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_productos; ?></span>
                        <span class="stat-label">Total Productos</span>
                    </div>
                </div>

                <!-- Filtros -->
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
                                    <tr>
                                        <td><code><?php echo $row['codigo_producto']; ?></code></td>
                                     <td>
                                        <strong><?php echo htmlspecialchars($row['nombre']); ?></strong>
                                        <?php if ($row['es_perecedero']): ?>
                                            <br><span class="badge badge-warning">🕒 Perecedero</span>
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
                                            <span class="badge badge-success"><?php echo $row['unidad_medida']; ?></span>
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
                                                                                 '<?php echo $row['unidad_medida']; ?>')">
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
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-primary" onclick="abrirModalCategoria()">➕ Agregar Categoría</button>
                    <a href="agregar_producto_proveedor.php" class="btn btn-primary">➕ Agregar Producto de Proveedor</a>
                    <a href="proveedores.php" class="btn btn-secondary">👥 Ver Proveedores</a>
                </div>
            </div>
    </main>

    <div id="modalCategoria" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Gestionar Categorías y Subcategorías</h3>
                <span class="close-modal" onclick="cerrarModalCategoria()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formCategoria" method="POST" action="procesar_categoria.php">
                    <div class="form-group">
                        <label for="tipo_operacion">Tipo de Operación:</label>
                        <select id="tipo_operacion" name="tipo_operacion" class="form-control" onchange="cambiarTipoOperacion()" required>
                            <option value="">Seleccionar operación</option>
                            <option value="nueva_categoria">Nueva Categoría</option>
                            <option value="nueva_subcategoria">Nueva Subcategoría</option>
                        </select>
                    </div>

                    <!-- Grupo para nueva categoría -->
                    <div id="grupo-nueva-categoria" style="display: none;">
                        <div class="form-group">
                            <label for="nombre_categoria" class="required">Nombre de Categoría</label>
                            <input type="text" id="nombre_categoria" name="nombre_categoria" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="agregar_subcategoria">¿Agregar Subcategoría?</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="agregar_subcategoria" name="agregar_subcategoria" value="1" onchange="toggleSubcategoriaNueva()">
                                <label for="agregar_subcategoria">Sí, agregar subcategoría a esta categoría</label>
                            <?php if (isset($_SESSION['error'])): ?>
      <div class="mensaje-error-modal">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

                            
                            </div>
                        </div>
                    </div>

                    <!-- Grupo para nueva subcategoría -->
                    <div id="grupo-nueva-subcategoria" style="display: none;">
                        <div class="form-group">
                            <label for="categoria_existente" class="required">Categoría Existente</label>
                            <select id="categoria_existente" name="categoria_existente" class="form-control">
                                <option value="">Seleccionar categoría</option>
                                <?php
                                $categorias_existentes = $pdo->query("SELECT id, nombre_categoria FROM categoria_prod WHERE estado = 'active' ORDER BY nombre_categoria")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($categorias_existentes as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre_subcategoria" class="required">Nombre de Subcategoría</label>
                            <input type="text" id="nombre_subcategoria" name="nombre_subcategoria" class="form-control">
                        </div>
                    </div>

                    <!-- Subcategoría para nueva categoría -->
                    <div id="subcategoria-nueva-group" style="display: none;">
                        <div class="form-group">
                            <label for="nombre_subcategoria_nueva">Nombre de Subcategoría</label>
                            <input type="text" id="nombre_subcategoria_nueva" name="nombre_subcategoria_nueva" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="cerrarModalCategoria()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>

                <!-- Lista de categorías y subcategorías existentes -->
                <div style="margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;">
                    <h4>Categorías y Subcategorías Existentes</h4>
                    <?php
                    $categorias_completas = $pdo->query("
                        SELECT c.id as categoria_id, c.nombre_categoria, 
                               s.id as subcategoria_id, s.nombre_subcategoria
                        FROM categoria_prod c
                        LEFT JOIN subcategorias s ON c.id = s.categoria_id AND s.estado = 'active'
                        WHERE c.estado = 'active'
                        ORDER BY c.nombre_categoria, s.nombre_subcategoria
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    
                    $categorias_agrupadas = [];
                    foreach ($categorias_completas as $row) {
                        $categoria_nombre = $row['nombre_categoria'];
                        if (!isset($categorias_agrupadas[$categoria_nombre])) {
                            $categorias_agrupadas[$categoria_nombre] = [];
                        }
                        if ($row['nombre_subcategoria']) {
                            $categorias_agrupadas[$categoria_nombre][] = $row['nombre_subcategoria'];
                        }
                    }
                    ?>
                    
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($categorias_agrupadas as $categoria => $subcategorias): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>🏷️ <?= htmlspecialchars($categoria) ?></strong>
                                <?php if (!empty($subcategorias)): ?>
                                    <div style="margin-left: 20px; font-size: 0.9em;">
                                        <?php foreach ($subcategorias as $subcat): ?>
                                            <div>• <?= htmlspecialchars($subcat) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-left: 20px; color: #666; font-style: italic;">
                                        Sin subcategorías
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para registrar compra -->
    <div id="modalCompra" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Registrar Compra de Producto</h3>
                <span class="close-modal" onclick="cerrarModalCompra()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formCompra" method="POST" action="procesar_compra_proveedor.php">
                    <input type="hidden" name="id_producto_proveedor" id="compra_id_producto">
                    
                    <div class="form-group">
                        <label>Producto:</label>
                        <input type="text" id="compra_nombre_producto" class="form-control" readonly>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio_compra_total" class="required">Precio Total de la Compra ($)</label>
                            <div class="input-with-symbol">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="precio_compra_total" name="precio_compra_total" 
                                       step="0.01" class="form-control with-symbol" 
                                       required min="0" placeholder="0.00"
                                       oninput="calcularCompra()">
                            </div>
                        </div>
                    </div>





                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cantidad_empaques" class="required">Cantidad de Empaques</label>
                            <input type="number" name="cantidad_empaques" id="cantidad_empaques" class="form-control" 
                                min="1" max="1000" required placeholder="Ej: 14 (cajas, paquetes, etc.)"
                                oninput="calcularCompra()">
                            <small style="color: #666;">Cantidad de empaques recibidos</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="unidades_empaque" class="required">Unidades por Empaque</label>
                            <input type="number" name="unidades_empaque" id="unidades_empaque" class="form-control" 
                                min="1" max="1000" required placeholder="Ej: 9 (unidades por caja)"
                                oninput="calcularCompra()">
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
                        <label>Precio de Venta por Unidad (42%):</label>
                        <input type="text" id="precio_venta_unidad" class="form-control" readonly 
                            style="background-color: #e3f2fd; font-weight: bold; color: #1565c0;">
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_compra" class="required">Fecha de Compra</label>
                        <input type="date" name="fecha_compra" id="fecha_compra" class="form-control" 
                            value="<?php echo date('Y-m-d'); ?>" required
                            max="<?php echo date('Y-m-d'); ?>">
                        <small style="color: #666;">No puede ser posterior a hoy</small>
                    </div>

                    <div class="form-group">
                        <label for="fecha_vencimiento" class="required">Fecha de Vencimiento</label>
                        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control" 
                            value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required
                            min="<?php echo date('Y-m-d'); ?>">
                        <small style="color: #666;">Fecha en la que vence este lote (mínimo hoy)</small>
                    </div>
                    
                    <div class="alert-info">
                        <strong>💡 Información:</strong><br>
                        • <strong>Precio total de la compra:</strong> Total pagado al proveedor por todo el lote<br>
                        • <strong>Empaques:</strong> Cantidad de cajas/paquetes recibidos<br>
                        • <strong>Unidades por empaque:</strong> Cantidad de productos individuales por empaque<br>
                        • <strong>Precio de venta:</strong> Se calcula automáticamente (precio compra por unidad + 42%)<br>
                        • <strong>Stock máximo:</strong> 200 unidades por producto
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="cerrarModalCompra()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">✅ Registrar Compra</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
      <?php if (isset($_SESSION['error'])): ?>
    document.addEventListener('DOMContentLoaded', function () {
      const modal = document.getElementById('modalAgregarCategoria');
      if (modal) {
        modal.style.display = 'block'; // o usa tu función para abrir el modal
      }
    });
  <?php endif; ?>

    function abrirModalCompra(idProducto, nombreProducto, unidadMedida) {
        document.getElementById('compra_id_producto').value = idProducto;
        document.getElementById('compra_nombre_producto').value = nombreProducto;
        
        // Resetear campos
        document.getElementById('precio_compra_total').value = '';
        document.getElementById('cantidad_empaques').value = '';
        document.getElementById('unidades_empaque').value = '';
        document.getElementById('cantidad_total').value = '';
        document.getElementById('precio_compra_unidad').value = '';
        document.getElementById('precio_venta_unidad').value = '';
        
        // Establecer fecha de vencimiento por defecto (30 días desde hoy)
        const fechaVencimiento = new Date();
        fechaVencimiento.setDate(fechaVencimiento.getDate() + 30);
        document.getElementById('fecha_vencimiento').value = fechaVencimiento.toISOString().split('T')[0];
        
        document.getElementById('modalCompra').style.display = 'block';
        document.getElementById('precio_compra_total').focus();
    }

    function calcularCompra() {
        const precioCompraTotal = parseFloat(document.getElementById('precio_compra_total').value) || 0;
        const empaques = parseInt(document.getElementById('cantidad_empaques').value) || 0;
        const unidadesPorEmpaque = parseInt(document.getElementById('unidades_empaque').value) || 0;
        
        const totalUnidades = empaques * unidadesPorEmpaque;
        
        // Calcular precio por unidad
        const precioPorUnidad = totalUnidades > 0 ? precioCompraTotal / totalUnidades : 0;
        const precioVentaPorUnidad = precioPorUnidad * 1.42;
        
        document.getElementById('cantidad_total').value = totalUnidades;
        document.getElementById('precio_compra_unidad').value = totalUnidades > 0 ? '$' + precioPorUnidad.toFixed(2) : '';
        document.getElementById('precio_venta_unidad').value = totalUnidades > 0 ? '$' + precioVentaPorUnidad.toFixed(2) : '';
        
        // Estilo del campo total
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
    }

    function abrirModalCategoria() {
        document.getElementById('modalCategoria').style.display = 'block';
    }

    function cerrarModalCategoria() {
        document.getElementById('modalCategoria').style.display = 'none';
        document.getElementById('formCategoria').reset();
        document.getElementById('grupo-nueva-categoria').style.display = 'none';
        document.getElementById('grupo-nueva-subcategoria').style.display = 'none';
        document.getElementById('subcategoria-nueva-group').style.display = 'none';
        document.getElementById('tipo_operacion').selectedIndex = 0;
    }

    function cambiarTipoOperacion() {
        const tipo = document.getElementById('tipo_operacion').value;
        const grupoCategoria = document.getElementById('grupo-nueva-categoria');
        const grupoSubcategoria = document.getElementById('grupo-nueva-subcategoria');
        const subcategoriaNuevaGroup = document.getElementById('subcategoria-nueva-group');
        
        // Ocultar todos los grupos primero
        grupoCategoria.style.display = 'none';
        grupoSubcategoria.style.display = 'none';
        subcategoriaNuevaGroup.style.display = 'none';
        
        // Mostrar el grupo correspondiente
        if (tipo === 'nueva_categoria') {
            grupoCategoria.style.display = 'block';
        } else if (tipo === 'nueva_subcategoria') {
            grupoSubcategoria.style.display = 'block';
        }
    }

    function toggleSubcategoriaNueva() {
        const subcategoriaNuevaGroup = document.getElementById('subcategoria-nueva-group');
        subcategoriaNuevaGroup.style.display = document.getElementById('agregar_subcategoria').checked ? 'block' : 'none';
    }

    // Validación de fechas en tiempo real
    document.getElementById('fecha_compra')?.addEventListener('change', function() {
        const hoy = new Date().toISOString().split('T')[0];
        if (this.value > hoy) {
            alert('La fecha de compra no puede ser posterior a hoy');
            this.value = hoy;
        }
    });

    document.getElementById('fecha_vencimiento')?.addEventListener('change', function() {
        const hoy = new Date().toISOString().split('T')[0];
        if (this.value < hoy) {
            alert('La fecha de vencimiento no puede ser anterior a hoy');
            this.value = hoy;
        }
    });

    window.onclick = function(event) {
        const modalCompra = document.getElementById('modalCompra');
        const modalCategoria = document.getElementById('modalCategoria');
        
        if (event.target === modalCompra) {
            cerrarModalCompra();
        }
        if (event.target === modalCategoria) {
            cerrarModalCategoria();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            alert('✅ ' + urlParams.get('success'));
        }
        if (urlParams.has('error')) {
            alert('❌ ' + urlParams.get('error'));
        }
    });
    </script>
</body>
</html>