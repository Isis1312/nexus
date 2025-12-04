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
            $_SESSION['error'] = "El stock debe estar entre 0 y 200 unidades";
        } else {
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

// Procesar eliminación de producto 
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['mensaje'] = "Producto eliminado correctamente";
    header('Location: productos.php');
    exit();
}

// Obtener productos del inventario 
try {
    $query = "SELECT 
                p.id,
                p.codigo,
                p.nombre,
                pr.nombre_comercial as marca,
                cp.nombre_categoria,
                s.nombre_subcategoria,
                p.cantidad,
                p.precio_costo,
                p.precio_venta,
                p.fecha_vencimiento
              FROM productos p
              LEFT JOIN proveedores pr ON p.proveedor_id = pr.id_proveedor
              LEFT JOIN categoria_prod cp ON p.categoria_id = cp.id
              LEFT JOIN subcategorias s ON p.subcategoria_id = s.id
              ORDER BY p.cantidad ASC, p.nombre ASC";

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

// Calcular productos con alertas
$productos_stock_bajo = [];
$productos_proximos_vencer = [];

$fecha_actual = date('Y-m-d');
$fecha_limite = date('Y-m-d', strtotime('+30 days'));

foreach ($productos as &$producto) {
    // Si no existe precio_venta en la BD, calcularlo
    if (!isset($producto['precio_venta']) || $producto['precio_venta'] === null) {
        $producto['precio_venta'] = $producto['precio_costo'] * 1.42;
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

// Mostrar mensajes
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Productos - NEXUS</title>
    <link rel="stylesheet" href="css/productos.css">
</head>
<style>
     .notificacion-lateral {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 5px;
        padding: 15px;
        max-width: 300px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
    }
    .notificacion-stock-bajo {
        background: #f8d7da;
        border-color: #f5c6cb;
    }
    .notificacion-vencimiento {
        background: #fff3cd;
        border-color: #ffeaa7;
    }
    .notificacion-lateral h4 {
        margin: 0 0 10px 0;
        color: #856404;
    }
    .notificacion-stock-bajo h4 {
        color: #721c24;
    }
    .producto-notificacion {
        padding: 5px;
        border-bottom: 1px solid #ffeaa7;
    }
    .notificacion-stock-bajo .producto-notificacion {
        border-bottom-color: #f5c6cb;
    }
    .close-notificacion {
        float: right;
        cursor: pointer;
        font-weight: bold;
    }
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
</style>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-content">
        <div class="content-wrapper">
            <!-- Header de la página -->
            <div class="page-header">
                <h1 class="page-title">Inventario de Productos</h1>
            </div>

            <!-- Notificaciones laterales -->
            <?php if (!empty($productos_stock_bajo)): ?>
            <div class="notificacion-lateral notificacion-stock-bajo" id="notificacion-stock">
                <span class="close-notificacion" onclick="cerrarNotificacion('notificacion-stock')">&times;</span>
                <h4>⚠ Stock Bajo</h4>
                <?php foreach($productos_stock_bajo as $producto): ?>
                    <div class="producto-notificacion">
                        <strong><?php echo htmlspecialchars($producto['codigo']); ?></strong><br>
                        <?php echo htmlspecialchars($producto['nombre']); ?><br>
                        Stock: <?php echo $producto['cantidad']; ?> unidades
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($productos_proximos_vencer)): ?>
            <div class="notificacion-lateral notificacion-vencimiento" id="notificacion-vencimiento">
                <span class="close-notificacion" onclick="cerrarNotificacion('notificacion-vencimiento')">&times;</span>
                <h4>📅 Productos Próximos a Vencer</h4>
                <?php foreach($productos_proximos_vencer as $producto): ?>
                    <div class="producto-notificacion">
                        <strong><?php echo htmlspecialchars($producto['codigo']); ?></strong><br>
                        <?php echo htmlspecialchars($producto['nombre']); ?><br>
                        Vence: <?php echo date('d/m/Y', strtotime($producto['fecha_vencimiento'])); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Mensajes de éxito/error -->
          <?php if (isset($mensaje)): ?>
            <div class="alert-success">✅ <?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Tabla de productos -->
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
                                <tr>
                                    <td><code><?= htmlspecialchars($producto['codigo']) ?></code></td>
                                    <td><?= htmlspecialchars($producto['nombre']) ?></td>
                                    <td><?= htmlspecialchars($producto['marca']) ?></td>
                                    <td><?= htmlspecialchars($producto['nombre_categoria']) ?></td>
                                    <td><?= htmlspecialchars($producto['nombre_subcategoria'] ?? '—') ?></td>
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
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="acciones-container">
                                            <button class="btn-action btn-editar" onclick="abrirModalStock(<?= $producto['id'] ?>, '<?= addslashes($producto['nombre']) ?>', <?= $producto['cantidad'] ?>)">
                                                ✎ Editar Stock
                                            </button>
                                            <button class="btn-action btn-eliminar" onclick="confirmarEliminar(<?= $producto['id'] ?>)">
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

    <!-- Modal Editar Stock -->
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

    // Cerrar notificaciones
    function cerrarNotificacion(id) {
        document.getElementById(id).style.display = 'none';
    }

    // Confirmar eliminación
    function confirmarEliminar(id) {
        if (confirm('¿Estás seguro de que deseas eliminar este producto del inventario?')) {
            window.location.href = 'productos.php?eliminar=' + id;
        }
    }

    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('modalStock');
        if (event.target === modal) {
            cerrarModalStock();
        }
    }

    // Auto-cerrar notificaciones después de 10 segundos
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            const notificaciones = document.querySelectorAll('.notificacion-lateral');
            notificaciones.forEach(notif => {
                notif.style.display = 'none';
            });
        }, 1000);
    });
    </script>
</body>
</html>