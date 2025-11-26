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

$mensaje = '';
$error = '';

// Obtener categorías con sus subcategorías
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

$proveedores = $pdo->query("SELECT id_proveedor, nombre_comercial FROM proveedores WHERE estado = 'activo' ORDER BY nombre_comercial")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $codigo_producto = trim($_POST['codigo_producto']);
        $nombre = trim($_POST['nombre']);
        $id_categoria = $_POST['id_categoria'];
        $id_subcategoria = $_POST['id_subcategoria'] ?: null;
        $id_proveedor = $_POST['id_proveedor'];
        $unidad_medida = $_POST['unidad_medida'];
        $es_perecedero = isset($_POST['es_perecedero']) ? 1 : 0;
        
        $stmt = $pdo->prepare("SELECT id_producto_proveedor FROM productos_proveedor WHERE codigo_producto = ? AND id_proveedor = ?");
        $stmt->execute([$codigo_producto, $id_proveedor]);
        if ($stmt->fetch()) {
            $error = "El código de producto ya existe para este proveedor";
        } else {
            $sql = "INSERT INTO productos_proveedor (codigo_producto, nombre, id_categoria, id_subcategoria, id_proveedor, 
                     unidad_medida, es_perecedero) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo_producto, $nombre, $id_categoria, $id_subcategoria, $id_proveedor, 
                           $unidad_medida, $es_perecedero]);
            
            $mensaje = "Producto del proveedor registrado exitosamente";
            $_POST = array();
        }
    } catch (PDOException $e) {
        $error = "Error al registrar el producto: " . $e->getMessage();
    }
}

$ultimo_producto = $pdo->query("SELECT codigo_producto FROM productos_proveedor ORDER BY id_producto_proveedor DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$nuevo_codigo = 'PROD-001';
if ($ultimo_producto && isset($ultimo_producto['codigo_producto'])) {
    $matches = [];
    if (preg_match('/PROD-(\d+)/', $ultimo_producto['codigo_producto'], $matches)) {
        $numero = intval($matches[1]) + 1;
        $nuevo_codigo = 'PROD-' . str_pad($numero, 3, '0', STR_PAD_LEFT);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Producto de Proveedor - NEXUS</title>
    <link rel="stylesheet" href="css/agg_producto_proveedor.css">
</head>
<body>
    
    <main class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">Registrar Producto de Proveedor</h1>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="" id="formProducto">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="codigo_producto" class="required">Código del Producto</label>
                            <input type="text" id="codigo_producto" name="codigo_producto" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['codigo_producto'] ?? $nuevo_codigo) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre" class="required">Nombre del Producto</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_categoria" class="required">Categoría</label>
                            <select id="id_categoria" name="id_categoria" class="form-control" required onchange="actualizarSubcategorias(this.value)">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categorias as $id => $nombre): ?>
                                    <option value="<?= $id ?>" 
                                        <?= ($_POST['id_categoria'] ?? '') == $id ? 'selected' : '' ?>>
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

                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_proveedor" class="required">Proveedor</label>
                            <select id="id_proveedor" name="id_proveedor" class="form-control" required>
                                <option value="">Seleccionar proveedor</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?= $prov['id_proveedor'] ?>" 
                                        <?= ($_POST['id_proveedor'] ?? '') == $prov['id_proveedor'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov['nombre_comercial']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>


                    <div class="form-row">
                        <div class="form-group">
                            <label for="unidad_medida" class="required">Unidad de Medida</label>
                            <select id="unidad_medida" name="unidad_medida" class="form-control" required>
                                <option value="">Seleccionar unidad</option>
                                <option value="unidad" <?= ($_POST['unidad_medida'] ?? '') == 'unidad' ? 'selected' : '' ?>>Unidad</option>
                                <option value="kilo" <?= ($_POST['unidad_medida'] ?? '') == 'kilo' ? 'selected' : '' ?>>Kilo</option>
                                <option value="litro" <?= ($_POST['unidad_medida'] ?? '') == 'litro' ? 'selected' : '' ?>>Litro</option>
                                <option value="paquete" <?= ($_POST['unidad_medida'] ?? '') == 'paquete' ? 'selected' : '' ?>>Paquete</option>
                                <option value="caja" <?= ($_POST['unidad_medida'] ?? '') == 'caja' ? 'selected' : '' ?>>Caja</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="es_perecedero" name="es_perecedero" value="1" 
                                       <?= isset($_POST['es_perecedero']) ? 'checked' : '' ?>>
                                <label for="es_perecedero">Producto Perecedero</label>
                            </div>
                        </div>
                    </div>


                    <div class="form-actions">
                        <a href="productos_proveedores.php" class="btn btn-secondary">← Volver</a>
                        <button type="submit" class="btn btn-primary">Registrar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Datos de subcategorías por categoría
        const subcategoriasPorCategoria = <?= json_encode($subcategorias_por_categoria) ?>;

        function actualizarSubcategorias(categoriaId) {
            const subcategoriaGroup = document.getElementById('subcategoria-group');
            const subcategoriaSelect = document.getElementById('id_subcategoria');
            
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

        // Validación de precio
        document.getElementById('precio_compra').addEventListener('input', function(e) {
            if (e.target.value < 0) {
                e.target.value = 0;
            }
        });
    </script>
</body>
</html>