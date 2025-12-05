<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'menu.php'; // Asumimos que tienes un menú lateral

// --- LÓGICA DE BASE DE DATOS (PROCESAR FORMULARIOS) ---

$mensaje = '';
$tipo_mensaje = '';
$categoria_a_editar = null;

// 1. ELIMINAR CATEGORÍA
if (isset($_GET['accion']) && $_GET['accion'] == 'eliminar_cat' && isset($_GET['id'])) {
    try {
        // CÓDIGO DE ELIMINACIÓN PERMANENTE
        $stmt = $pdo->prepare("DELETE FROM categoria_prod WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        
        // Opcional: Eliminar subcategorías relacionadas
        $stmt_sub = $pdo->prepare("DELETE FROM subcategorias WHERE categoria_id = ?");
        $stmt_sub->execute([$_GET['id']]);
        
        $mensaje = "Categoría y sus subcategorías eliminadas permanentemente.";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// 2. ELIMINAR SUBCATEGORÍA (CAMBIO A ELIMINACIÓN PERMANENTE)
if (isset($_GET['accion']) && $_GET['accion'] == 'eliminar_sub' && isset($_GET['id'])) {
    try {
        // CÓDIGO DE ELIMINACIÓN PERMANENTE
        $stmt = $pdo->prepare("DELETE FROM subcategorias WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        
        $mensaje = "Subcategoría eliminada permanentemente.";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}



// 3. CARGAR DATOS PARA EDICIÓN
if (isset($_GET['accion']) && $_GET['accion'] == 'editar_cat' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM categoria_prod WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $categoria_a_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. PROCESAR POST (CREAR O ACTUALIZAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operacion = $_POST['operacion'];
    
    try {
        if ($operacion === 'guardar_categoria') {
            $nombre = trim($_POST['nombre_categoria']);
            $id_edit = isset($_POST['id_categoria']) ? intval($_POST['id_categoria']) : 0;

            if (empty($nombre)) throw new Exception("El nombre es obligatorio.");

            if ($id_edit > 0) {
                // Actualizar
                $stmt = $pdo->prepare("UPDATE categoria_prod SET nombre_categoria = ? WHERE id = ?");
                $stmt->execute([$nombre, $id_edit]);
                $mensaje = "Categoría actualizada exitosamente.";
            } else {
                // Crear Nueva
                // Verificar duplicado
                $check = $pdo->prepare("SELECT id FROM categoria_prod WHERE nombre_categoria = ? AND estado = 'active'");
                $check->execute([$nombre]);
                if($check->fetch()) throw new Exception("Ya existe una categoría con ese nombre.");

                $stmt = $pdo->prepare("INSERT INTO categoria_prod (nombre_categoria, estado) VALUES (?, 'active')");
                $stmt->execute([$nombre]);
                $mensaje = "Categoría creada exitosamente.";
            }
            $tipo_mensaje = "success";

        } elseif ($operacion === 'guardar_subcategoria') {
            $id_padre = $_POST['categoria_padre'];
            $nombre_sub = trim($_POST['nombre_subcategoria']);

            if (empty($id_padre) || empty($nombre_sub)) throw new Exception("Todos los campos son obligatorios.");

            // Verificar duplicado
            $check = $pdo->prepare("SELECT id FROM subcategorias WHERE categoria_id = ? AND nombre_subcategoria = ? AND estado = 'active'");
            $check->execute([$id_padre, $nombre_sub]);
            if($check->fetch()) throw new Exception("Esta subcategoría ya existe en la categoría seleccionada.");

            $stmt = $pdo->prepare("INSERT INTO subcategorias (categoria_id, nombre_subcategoria, estado) VALUES (?, ?, 'active')");
            $stmt->execute([$id_padre, $nombre_sub]);
            
            $mensaje = "Subcategoría agregada exitosamente.";
            $tipo_mensaje = "success";
        }

    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// --- OBTENER DATOS PARA LA VISTA ---

// Obtener todas las categorías para el select y la tabla
$stmt_cats = $pdo->query("SELECT * FROM categoria_prod WHERE estado = 'active' ORDER BY nombre_categoria");
$categorias_lista = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías con sus subcategorías agrupadas para la tabla
$sql_tabla = "
    SELECT c.id, c.nombre_categoria, 
           GROUP_CONCAT(CONCAT(s.id, ':', s.nombre_subcategoria) ORDER BY s.nombre_subcategoria SEPARATOR '||') as subcategorias_raw
    FROM categoria_prod c
    LEFT JOIN subcategorias s ON c.id = s.categoria_id AND s.estado = 'active'
    WHERE c.estado = 'active'
    GROUP BY c.id
    ORDER BY c.nombre_categoria";
$tabla_result = $pdo->query($sql_tabla)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Categorías - NEXUS</title>
    <link rel="stylesheet" href="css/categorias.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <main class="main-content">
        <div class="content-wrapper">
            
            <div class="page-header">
                <div class="page-title">📂 Gestión de Categorías</div>
                <a href="productos_proveedores.php" class="btn-action btn-editar" style="font-weight: bold;">
                    🔙 Volver a Productos
                </a>
            </div>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <div class="forms-container">
                <div class="form-box">
                    <h3>
                        <?php echo $categoria_a_editar ? '✏️ Editar Categoría' : '➕ Nueva Categoría'; ?>
                    </h3>
                    <form method="POST" action="categorias.php">
                        <input type="hidden" name="operacion" value="guardar_categoria">
                        <?php if($categoria_a_editar): ?>
                            <input type="hidden" name="id_categoria" value="<?php echo $categoria_a_editar['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label>Nombre de la Categoría:</label>
                            <input type="text" name="nombre_categoria" class="form-control" required 
                                   placeholder="Ej: Bebidas, Limpieza..."
                                   value="<?php echo $categoria_a_editar ? htmlspecialchars($categoria_a_editar['nombre_categoria']) : ''; ?>">
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn-guardar">
                                <?php echo $categoria_a_editar ? 'Actualizar' : 'Guardar Categoría'; ?>
                            </button>
                            <?php if($categoria_a_editar): ?>
                                <a href="categorias.php" class="btn-guardar" style="background: #6c757d; text-align:center; text-decoration:none;">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="form-box">
                    <h3>➕ Agregar Subcategoría</h3>
                    <form method="POST" action="categorias.php">
                        <input type="hidden" name="operacion" value="guardar_subcategoria">

                        <div class="form-group">
                            <label>Pertenece a la Categoría:</label>
                            <select name="categoria_padre" class="form-control" required>
                                <option value="">-- Seleccione --</option>
                                <?php foreach($categorias_lista as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Nombre Subcategoría:</label>
                            <input type="text" name="nombre_subcategoria" class="form-control" required placeholder="Ej: Gaseosas, Detergentes...">
                        </div>

                        <button type="submit" class="btn-guardar">Agregar Subcategoría</button>
                    </form>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th width="30%">Categoría Principal</th>
                            <th width="50%">Subcategorías Asociadas</th>
                            <th width="20%">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tabla_result) > 0): ?>
                            <?php foreach($tabla_result as $row): ?>
                                <tr>
                                    <td>
                                        <strong style="color: #008B8B; font-size: 1.1em;">
                                            <?php echo htmlspecialchars($row['nombre_categoria']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($row['subcategorias_raw'])) {
                                            $subs = explode('||', $row['subcategorias_raw']);
                                            foreach($subs as $sub_string) {
                                                list($sub_id, $sub_nombre) = explode(':', $sub_string);
                                                echo '<span class="subcat-badge">';
                                                echo htmlspecialchars($sub_nombre);
                                                // Link para borrar subcategoría
                                                echo '<a href="categorias.php?accion=eliminar_sub&id='.$sub_id.'" class="btn-del-sub" onclick="return confirm(\'¿Eliminar subcategoría?\')" title="Eliminar">×</a>';
                                                echo '</span>';
                                            }
                                        } else {
                                            echo '<span style="color: #999; font-style: italic;">Sin subcategorías</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="categorias.php?accion=editar_cat&id=<?php echo $row['id']; ?>" class="btn-action btn-editar">
                                            ✏️ Editar
                                        </a>
                                        <a href="categorias.php?accion=eliminar_cat&id=<?php echo $row['id']; ?>" 
                                           class="btn-action btn-eliminar"
                                           onclick="return confirm('¿Seguro que deseas eliminar la categoría <?php echo $row['nombre_categoria']; ?>? Esto podría ocultar sus subcategorías.')">
                                            🗑️ Eliminar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 30px;">No hay categorías registradas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</body>
</html>