<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

if (!$sistemaPermisos->puedeVer('proveedores')) {
    header('Location: inicio.php');
    exit();
}

$mensaje = '';
$error = '';
$producto = null;

$id_producto_proveedor = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_producto_proveedor <= 0) {
    header('Location: productos_proveedores.php');
    exit();
}

// Obtener categorías con sus subcategorías (igual que en agregar)
$categorias_con_sub = $pdo->query("
    SELECT c.id as categoria_id, c.nombre_categoria, 
           s.id as subcategoria_id, s.nombre_subcategoria
    FROM categoria_prod c
    LEFT JOIN subcategorias s ON c.id = s.categoria_id AND s.estado = 'active'
    WHERE c.estado = 'active'
    ORDER BY c.nombre_categoria, s.nombre_subcategoria
")->fetchAll(PDO::FETCH_ASSOC);

// Organizar categorías y subcategorías
$categorias = [];
$subcategorias_por_categoria = [];

foreach ($categorias_con_sub as $row) {
    $categoria_id = $row['categoria_id'];
    $categoria_nombre = $row['nombre_categoria'];
    
    // Agregar categoría si no existe
    if (!isset($categorias[$categoria_id])) {
        $categorias[$categoria_id] = $categoria_nombre;
    }
    
    // Agregar subcategoría si existe
    if ($row['subcategoria_id']) {
        if (!isset($subcategorias_por_categoria[$categoria_id])) {
            $subcategorias_por_categoria[$categoria_id] = [];
        }
        $subcategorias_por_categoria[$categoria_id][] = [
            'id' => $row['subcategoria_id'],
            'nombre' => $row['nombre_subcategoria']
        ];
    }
}

$stmt = $pdo->prepare("SELECT * FROM productos_proveedor WHERE id_producto_proveedor = ?");
$stmt->execute([$id_producto_proveedor]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    $error = "Producto no encontrado";
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $nombre = trim($_POST['nombre']);
            $id_categoria = $_POST['id_categoria'];
            $id_subcategoria = $_POST['id_subcategoria'] ?: null;
            
            if (empty($nombre) || empty($id_categoria)) {
                $error = "El nombre y la categoría son obligatorios";
            } else {
                $sql = "UPDATE productos_proveedor SET 
                        nombre = ?, 
                        id_categoria = ?, 
                        id_subcategoria = ?,
                        actualizacion = CURRENT_TIMESTAMP
                        WHERE id_producto_proveedor = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nombre, 
                    $id_categoria, 
                    $id_subcategoria,
                    $id_producto_proveedor
                ]);
                
                $mensaje = "Producto actualizado exitosamente";
                
                // Actualizar los datos del producto en la variable
                $producto = array_merge($producto, [
                    'nombre' => $nombre,
                    'id_categoria' => $id_categoria,
                    'id_subcategoria' => $id_subcategoria
                ]);
            }
        } catch (PDOException $e) {
            $error = "Error al actualizar el producto: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto de Proveedor - NEXUS</title>
    <link rel="stylesheet" href="css/agg_producto_proveedor.css">
</head>
<body>
    <?php require_once 'menu.php'; ?>
    <main class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">Editar Producto de Proveedor</h1>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($producto): ?>
            <div class="form-container">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="codigo_producto">Código del Producto</label>
                        <input type="text" id="codigo_producto" class="form-control" 
                               value="<?= htmlspecialchars($producto['codigo_producto']) ?>" readonly 
                               style="background-color: #f8f9fa;">
                        <small class="form-text">El código no se puede modificar</small>
                    </div>

                    <div class="form-group">
                        <label for="nombre" class="required">Nombre del Producto</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" 
                               value="<?= htmlspecialchars($producto['nombre']) ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_categoria" class="required">Categoría</label>
                            <select id="id_categoria" name="id_categoria" class="form-control" required onchange="actualizarSubcategorias(this.value)">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categorias as $id => $nombre): ?>
                                    <option value="<?= $id ?>" 
                                        <?= $producto['id_categoria'] == $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($nombre) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="subcategoria-group" style="display: none;">
                            <label for="id_subcategoria">Subcategoría</label>
                            <select id="id_subcategoria" name="id_subcategoria" class="form-control">
                                <option value="">Seleccionar subcategoría</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="proveedor">Proveedor</label>
                        <input type="text" id="proveedor" class="form-control" 
                               value="<?= htmlspecialchars($producto['id_proveedor']) ?>" readonly 
                               style="background-color: #f8f9fa;">
                        <small class="form-text">El proveedor no se puede modificar</small>
                    </div>

                    <div class="form-group">
                        <label for="precio_compra">Precio de Compra</label>
                        <div class="input-with-symbol">
                            <span class="currency-symbol">$</span>
                            <input type="text" id="precio_compra" class="form-control with-symbol" 
                                   value="$<?= number_format($producto['precio_compra'], 2) ?>" readonly 
                                   style="background-color: #f8f9fa;">
                        </div>
                        <small class="form-text">El precio se actualiza al realizar compras</small>
                    </div>

                    <div class="form-group">
                        <label for="unidad_medida">Unidad de Medida</label>
                        <input type="text" id="unidad_medida" class="form-control" 
                               value="<?= htmlspecialchars($producto['unidad_medida']) ?>" readonly 
                               style="background-color: #f8f9fa;">
                    </div>

                    <div class="form-actions">
                        <a href="productos_proveedores.php" class="btn btn-secondary">
                            ← Volver
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Actualizar Producto
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <div class="form-actions">
                    <a href="productos_proveedores.php" class="btn btn-secondary">
                        ← Volver a Productos de Proveedores
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Datos de subcategorías por categoría
        const subcategoriasPorCategoria = <?= json_encode($subcategorias_por_categoria) ?>;

        function actualizarSubcategorias(categoriaId) {
            const subcategoriaGroup = document.getElementById('subcategoria-group');
            const subcategoriaSelect = document.getElementById('id_subcategoria');
            const subcategoriaActual = <?= $producto['id_subcategoria'] ?? 'null' ?>;
            
            // Limpiar subcategorías
            subcategoriaSelect.innerHTML = '<option value="">Seleccionar subcategoría</option>';
            
            // Mostrar/ocultar grupo de subcategorías
            if (categoriaId && subcategoriasPorCategoria[categoriaId] && subcategoriasPorCategoria[categoriaId].length > 0) {
                subcategoriaGroup.style.display = 'block';
                
                // Llenar subcategorías
                subcategoriasPorCategoria[categoriaId].forEach(subcat => {
                    const option = document.createElement('option');
                    option.value = subcat.id;
                    option.textContent = subcat.nombre;
                    // Seleccionar la subcategoría actual si coincide
                    if (subcat.id == subcategoriaActual) {
                        option.selected = true;
                    }
                    subcategoriaSelect.appendChild(option);
                });
            } else {
                subcategoriaGroup.style.display = 'none';
            }
        }

        // Actualizar subcategorías al cargar la página si hay una categoría seleccionada
        document.addEventListener('DOMContentLoaded', function() {
            const categoriaSelect = document.getElementById('id_categoria');
            if (categoriaSelect.value) {
                actualizarSubcategorias(categoriaSelect.value);
            }
        });
    </script>
</body>
</html>