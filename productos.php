<?php

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

if (!$sistemaPermisos->puedeVer('Inventario')) {
    header('Location: inicio.php');
    exit();
}

// Procesar actualización de stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_stock'])) {
    try {
        $id_producto = $_POST['id_producto'];
        $nuevo_stock = intval($_POST['stock']);
        
        if ($nuevo_stock < 0 || $nuevo_stock > 200) {
            $_SESSION['error'] = "El stock debe estar entre 0 y 200";
        } else {
            // Se actualiza el stock del registro con el ID utilizado (id_unico del grupo)
            $stmt = $pdo->prepare("UPDATE productos SET cantidad = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$nuevo_stock, $id_producto]);
            $_SESSION['mensaje'] = "Stock actualizado exitosamente";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al actualizar el stock: " . $e->getMessage();
    }
    header('Location: productos.php');
    exit();
}

// Procesar eliminación de producto (LÓGICA CORREGIDA: Elimina por CÓDIGO)
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    try {
        // 1. Obtener el código del producto a partir del id_unico (que es MIN(p.id))
        $stmt_codigo = $pdo->prepare("SELECT codigo FROM productos WHERE id = ?");
        $stmt_codigo->execute([$id]);
        $codigo = $stmt_codigo->fetchColumn();
        
        if ($codigo) {
            // 2. Eliminar TODOS los registros que comparten ese código
            $stmt = $pdo->prepare("DELETE FROM productos WHERE codigo = ?");
            $stmt->execute([$codigo]);
            $_SESSION['mensaje'] = "✅ Producto (código $codigo) y todos sus lotes asociados eliminados correctamente";
        } else {
            $_SESSION['error'] = "❌ No se encontró el producto para eliminar (ID: $id)";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Error al eliminar el producto: " . $e->getMessage();
    }

    header('Location: productos.php');
    exit();
}

// --- Lógica para Notificaciones (La lógica de tasa de dólar ha sido ELIMINADA) ---


// Obtener productos del inventario (Consulta CONSOLIDADA por CÓDIGO)
try {
    $query = "
        SELECT 
            MIN(p.id) AS id_unico, 
            p.codigo,
            MAX(p.nombre) AS nombre,
            SUM(p.cantidad) AS cantidad,
            MAX(p.precio_venta) AS precio_venta,
            MAX(p.fecha_vencimiento) AS fecha_vencimiento,
            MAX(pr.nombre_comercial) AS marca,
            MAX(cp.nombre_categoria) AS nombre_categoria,
            MAX(s.nombre_subcategoria) AS nombre_subcategoria
        FROM productos p
        LEFT JOIN proveedores pr ON p.proveedor_id = pr.id_proveedor
        LEFT JOIN categoria_prod cp ON p.categoria_id = cp.id
        LEFT JOIN subcategorias s ON p.subcategoria_id = s.id
        WHERE p.estado = 'active'
        GROUP BY p.codigo -- Agrupar por el código para consolidar el stock
        ORDER BY p.codigo ASC
    "; 

    $stmt = $pdo->query($query);
    if ($stmt) {
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $productos = [];
        error_log("Error en la consulta SQL: no se pudo ejecutar la consulta");
    }
} catch (PDOException $e) {
    error_log("Error de base de datos: " . $e->getMessage());
    $productos = [];
    $_SESSION['error'] = "Error al cargar los productos: " . $e->getMessage();
}

// Si no hay productos o hay error, inicializar como array vacío
if (!isset($productos) || $productos === false) {
    $productos = [];
}


// Calcular productos con alertas (usa la data AGRUPADA)
$productos_stock_bajo = [];
$productos_proximos_vencer = [];

$fecha_actual = date('Y-m-d');
$fecha_limite = date('Y-m-d', strtotime('+30 days'));

foreach ($productos as &$producto) {
    // Fallback de precio de venta si es nulo 
    if (!isset($producto['precio_venta']) || $producto['precio_venta'] === null) {
        $producto['precio_venta'] = 0; 
    }
    
    // Stock bajo (menos de 20 unidades)
    if ($producto['cantidad'] < 20) {
        $productos_stock_bajo[] = $producto;
    }
    
    // Próximos a vencer (en los próximos 30 días)
    if ($producto['fecha_vencimiento'] && $producto['fecha_vencimiento'] >= $fecha_actual && $producto['fecha_vencimiento'] <= $fecha_limite) {
        $productos_proximos_vencer[] = $producto;
    }
}

// Contar notificaciones totales para el badge (SOLO STOCK Y VENCIMIENTO)
$total_notificaciones = count($productos_stock_bajo) + count($productos_proximos_vencer);


// Mostrar mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// NUEVO: Array para rastrear códigos ya mostrados
$productos_vistos = [];

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Productos - NEXUS</title>
    <link rel="stylesheet" href="css/productos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> </head>
<style>
     /* Estilos para Notificaciones de Stock/Vencimiento */
     .cantidad-baja {
        color: #dc3545;
        font-weight: bold;
    }
    .vencimiento-proximo {
        color: #856404;
        font-weight: bold;
    }
    .vencimiento-caducado {
        color: #dc3545;
        font-weight: bold;
    }

    /* --- Estilos para la Campana de Notificación --- */
    .notification-container {
        position: absolute;
        top: 20px;
        right: 20px;
        z-index: 1000;
      
    }

    .notification-bell {
        font-size: 1.5rem;
        cursor: pointer;
        color: #008B8B; /* Color del sistema Nexus para el icono */
        position: relative;
        padding: 10px;
        border-radius: 50%;
        transition: background-color 0.2s;
    }

    .notification-bell:hover {
        background-color: rgba(0, 139, 139, 0.1);
    }

    .notification-count {
        position: absolute;
        top: 0;
        right: 0;
        background-color: #dc3545;
        color: white;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 0.7rem;
        line-height: 1;
        font-weight: bold;
    }

    .notification-dropdown {
        display: none;
        position: absolute;
        right: 0;
        top: 50px;
        width: 300px;
        background-color: white;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #ddd;
    }

    .dropdown-header {
        background-color: #f8f9fa;
        padding: 10px;
        font-weight: bold;
        border-bottom: 1px solid #eee;
    }
    .dropdown-header.item-stock-bajo {
        background-color: #f8d7da;
        color: #721c24;
    }
    .dropdown-header.item-vencimiento {
        background-color: #fff3cd;
        color: #856404;
    }

    .dropdown-item {
        padding: 10px;
        border-bottom: 1px solid #f0f0f0;
        cursor: default;
    }

    .dropdown-item:last-child {
        border-bottom: none;
    }

    .item-stock-bajo {
        color: #dc3545;
    }
    .item-vencimiento {
        color: #ffc107;
    }
</style>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">Inventario de Productos</h1>

                <div class="notification-container">
                    <div class="notification-bell" onclick="toggleDropdown()">
                        <i class="fas fa-bell"></i> <?php if ($total_notificaciones > 0): ?>
                            <span class="notification-count"><?= $total_notificaciones ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="dropdown-header">
                            Notificaciones Importantes (<?= $total_notificaciones ?>)
                        </div>
                        
                        <?php 
                        // La notificación de Tasa USD ha sido ELIMINADA de aquí.
                        ?>

                        <?php 
                        // 1. Notificación de Stock Bajo
                        if (!empty($productos_stock_bajo)): ?>
                            <div class="dropdown-header item-stock-bajo">
                                ⚠ Stock Bajo (<?= count($productos_stock_bajo) ?> productos)
                            </div>
                            <?php foreach($productos_stock_bajo as $producto): ?>
                                <div class="dropdown-item">
                                    <strong><?= htmlspecialchars($producto['codigo']) ?></strong> - <?= htmlspecialchars($producto['nombre']) ?><br>
                                    Stock: <span class="item-stock-bajo"><?= $producto['cantidad'] ?> unidades</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php 
                        // 2. Notificación de Vencimiento
                        if (!empty($productos_proximos_vencer)): ?>
                            <div class="dropdown-header item-vencimiento">
                                📅 Próximos a Vencer (<?= count($productos_proximos_vencer) ?> productos)
                            </div>
                            <?php foreach($productos_proximos_vencer as $producto): ?>
                                <div class="dropdown-item">
                                    <strong><?= htmlspecialchars($producto['codigo']) ?></strong> - <?= htmlspecialchars($producto['nombre']) ?><br>
                                    Vence: <span class="item-vencimiento"><?= date('d/m/Y', strtotime($producto['fecha_vencimiento'])) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($total_notificaciones == 0): ?>
                             <div class="dropdown-item" style="text-align: center; color: #6c757d;">
                                 No hay notificaciones pendientes.
                             </div>
                        <?php endif; ?>
                    </div>
                </div>
                </div>

            <?php if (isset($mensaje)): ?>
            <div class="alert-success">✅ <?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="users-table">
                <div class="table-header">
                    <h3>Lista de Productos (<?= count($productos) ?> en inventario)</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Marca</th>
                                <th>Categoría</th>
                                <th>Subcategoría</th>
                                <th>Stock</th>
                                <th>Precio Venta ($)</th>
                                <th>Vencimiento</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productos)): ?>
                                <tr>
                                    <td colspan="9" class="no-users">No se encontraron productos en inventario</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): ?>
                                    <?php 
                                    $codigo = $producto['codigo']; 
                                    
                                    // FILTRO FINAL VISUAL: Si ya se mostró este código, salta la fila
                                    if (isset($productos_vistos[$codigo])) {
                                        continue;
                                    }
                                    $productos_vistos[$codigo] = true;
                                    ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($producto['codigo']) ?></code></td>
                                    <td><?= htmlspecialchars($producto['nombre']) ?></td>
                                    <td><?= htmlspecialchars($producto['marca']) ?></td>
                                    <td><?= htmlspecialchars($producto['nombre_categoria']) ?></td>
                                    <td>
                                        <?php if (!empty($producto['nombre_subcategoria'])): ?>
                                            <?= htmlspecialchars($producto['nombre_subcategoria']) ?>
                                        <?php else: ?>
                                            <span style="color: #999; font-style: italic;">— Sin subcategoría —</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?= $producto['cantidad'] < 20 ? 'cantidad-baja' : '' ?>">
                                            <?= $producto['cantidad'] ?> unidades
                                            <?php if ($producto['cantidad'] < 20): ?>
                                                <br><small style="color: #dc3545;">⚠ Stock bajo</small>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: bold; color: #28a745;">
                                        $<?= number_format($producto['precio_venta'], 2) ?>
                                    </td>
                                    <td>
                                        <?php if ($producto['fecha_vencimiento']): ?>
                                            <?php
                                            $hoy = date('Y-m-d');
                                            $fecha_vencimiento = $producto['fecha_vencimiento'];
                                            $dias_restantes = floor((strtotime($fecha_vencimiento) - strtotime($hoy)) / (60 * 60 * 24));
                                            
                                            if ($dias_restantes < 0) {
                                                $clase = 'vencimiento-caducado';
                                                $mensaje_vencimiento = '❌ Caducado';
                                            } elseif ($dias_restantes <= 30) {
                                                $clase = 'vencimiento-proximo';
                                                $mensaje_vencimiento = '⚠ Próximo a vencer';
                                            } else {
                                                $clase = '';
                                                $mensaje_vencimiento = '';
                                            }
                                            ?>
                                            <span class="<?= $clase ?>">
                                                <?= date('d/m/Y', strtotime($producto['fecha_vencimiento'])) ?>
                                                <?php if ($mensaje_vencimiento): ?>
                                                    <br><small style="color: inherit;"><?= $mensaje_vencimiento ?></small>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999; font-style: italic;">— Sin fecha —</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="acciones-container">
                                            <button class="btn-action btn-editar" onclick="abrirModalStock(<?= $producto['id_unico'] ?>, '<?= addslashes($producto['nombre']) ?>', <?= $producto['cantidad'] ?>)">
                                                ✎ Editar Stock
                                            </button>
                                            <button class="btn-action btn-eliminar" onclick="confirmarEliminar(<?= $producto['id_unico'] ?>)">
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div id="modalStock" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Stock del Producto</h3>
                <span class="close-modal" onclick="cerrarModalStock()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="id_producto" id="edit_id">
                    <input type="hidden" name="actualizar_stock" value="1">
                    
                    <div class="form-group">
                        <label>Producto:</label>
                        <input type="text" id="edit_nombre_producto" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_stock" class="required">Stock Actual (0-200 unidades)</label>
                        <input type="number" name="stock" id="edit_stock" class="form-control" 
                               min="0" max="200" required>
                        <small class="form-text">El stock debe estar entre 0 y 200 unidades</small>
                    </div>

                    <div class="alert-info">
                        <strong>💡 Información:</strong><br>
                        • Stock mínimo recomendado: 20 unidades<br>
                        • Stock máximo permitido: 200 unidades<br>
                        • Se mostrará alerta cuando el stock esté por debajo de 20 unidades
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-volver" onclick="cerrarModalStock()">Cancelar</button>
                        <button type="submit" class="btn-guardar">Actualizar Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Función para alternar el dropdown de la campana
    function toggleDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        } else {
            dropdown.style.display = 'block';
        }
    }

    // Funciones para modal de stock
    function abrirModalStock(id, nombre, stock) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nombre_producto').value = nombre;
        document.getElementById('edit_stock').value = stock;
        document.getElementById('modalStock').style.display = 'block';
    }

    function cerrarModalStock() {
        document.getElementById('modalStock').style.display = 'none';
    }

    // Validación de stock en tiempo real
    document.getElementById('edit_stock')?.addEventListener('input', function(e) {
        const valor = parseInt(e.target.value);
        if (valor < 0) {
            e.target.value = 0;
        } else if (valor > 200) {
            e.target.value = 200;
        }
    });

    // Confirmar eliminación
    function confirmarEliminar(id) {
        // Alerta al usuario sobre la nueva lógica
        if (confirm('¿Estás seguro de que deseas eliminar este producto (y todos sus lotes) del inventario?')) {
            // Se envía el ID del registro "maestro" (MIN(p.id)) y la lógica en PHP se encarga de buscar el código y eliminar todos los lotes.
            window.location.href = 'productos.php?eliminar=' + id; 
        }
    }

    // Cerrar modal y dropdown al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('modalStock');
        const bell = document.querySelector('.notification-bell');
        const dropdown = document.getElementById('notificationDropdown');

        if (event.target === modal) {
            cerrarModalStock();
        }
        
        // Si el clic no es en la campana ni dentro del dropdown, cerrarlo
        if (dropdown && dropdown.style.display === 'block' && event.target !== bell && !bell.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    }
    </script>
</body>
</html>