<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'permisos.php';

$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registrar_compra') {
    
    $id_producto_proveedor = intval($_POST['id_producto_proveedor'] ?? 0);
    $cantidad_empaques = intval($_POST['cantidad_empaques'] ?? 0);
    $unidades_empaque = intval($_POST['unidades_empaque'] ?? 0);
    $precio_total = floatval($_POST['precio_total'] ?? 0);
    $fecha_compra = $_POST['fecha_compra'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    
    if ($id_producto_proveedor <= 0 || $cantidad_empaques <= 0 || $unidades_empaque <= 0 || $precio_total <= 0) {
        $error = "Datos de compra incompletos o inválidos.";
        header('Location: productos_proveedores.php?error=' . urlencode($error));
        exit();
    }
    
    $total_unidades = $cantidad_empaques * $unidades_empaque;
    
    if ($total_unidades > 200) {
         $error = "El total de unidades ($total_unidades) excede el límite permitido de 200 unidades.";
         header('Location: productos_proveedores.php?error=' . urlencode($error));
         exit();
    }
    
    if ($total_unidades <= 0) {
         $error = "La cantidad total de unidades es cero. No se puede procesar la compra.";
         header('Location: productos_proveedores.php?error=' . urlencode($error));
         exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        $precio_costo_unitario = $precio_total / $total_unidades;
        $precio_venta_calculado = round($precio_costo_unitario * 1.30, 2);

        // Obtener datos detallados del producto del proveedor
        $stmt_pp = $pdo->prepare("SELECT * FROM productos_proveedor WHERE id_producto_proveedor = ?");
        $stmt_pp->execute([$id_producto_proveedor]);
        $pp_data = $stmt_pp->fetch(PDO::FETCH_ASSOC);
        
        if (!$pp_data) {
            throw new Exception("Producto del proveedor (ID: $id_producto_proveedor) no encontrado.");
        }
        
        $codigo_producto = $pp_data['codigo_producto'];
        
        // --- 1. CREAR REGISTRO EN compras_proveedores (Registro Maestro) ---
        $stmt_compra = $pdo->prepare("
            INSERT INTO compras_proveedores 
            (id_producto_proveedor, cantidad_empaques, unidades_empaque, fecha_compra, fecha_vencimiento, precio_costo_unitario, precio_total, precio_compra_total) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_compra->execute([
            $id_producto_proveedor,
            $cantidad_empaques,
            $unidades_empaque,
            $fecha_compra,
            $fecha_vencimiento,
            $precio_costo_unitario,
            $precio_total,
            $precio_total 
        ]);
        
        $id_compra = $pdo->lastInsertId();
        
        // --- 2. Registrar en historial_compras (Detalle de la compra) ---
        $stmt_historial = $pdo->prepare("
            INSERT INTO historial_compras 
            (id_compra, id_producto_proveedor, cantidad_empaques, unidades_empaque, 
             total_unidades, precio_total, fecha_compra, fecha_vencimiento) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_historial->execute([
            $id_compra,
            $id_producto_proveedor,
            $cantidad_empaques,
            $unidades_empaque,
            $total_unidades,
            $precio_total,
            $fecha_compra,
            $fecha_vencimiento
        ]);
        
        // --- 3. LÓGICA DE INVENTARIO (Anti-duplicación por id_producto_proveedor) ---
        
        // Buscar si ya existe el producto en la tabla `productos` usando el FK id_producto_proveedor
        $stmt_check = $pdo->prepare("SELECT id, cantidad FROM productos WHERE id_producto_proveedor = ?");
        $stmt_check->execute([$id_producto_proveedor]);
        $producto_inventario = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($producto_inventario) {
            // Existe: ACTUALIZAR STOCK
            $nueva_cantidad = $producto_inventario['cantidad'] + $total_unidades;
            
            if ($nueva_cantidad > 200) {
                 // Este chequeo ya se hizo arriba, pero lo mantenemos por seguridad.
                 throw new Exception("Stock de $pp_data[nombre] excede el límite de 200 unidades.");
            }
            
            $stmt_update = $pdo->prepare("
                UPDATE productos SET 
                    cantidad = ?, 
                    precio_costo = ?, 
                    precio_venta = ?,
                    fecha_vencimiento = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt_update->execute([
                $nueva_cantidad,
                $precio_costo_unitario,
                $precio_venta_calculado,
                $fecha_vencimiento,
                $producto_inventario['id']
            ]);
        } else {
            // No existe: INSERTAR nuevo producto
            $stmt_insert = $pdo->prepare("
                INSERT INTO productos (
                    codigo, nombre, descripcion, categoria_id, subcategoria_id,
                    proveedor_id, id_producto_proveedor, precio_costo, precio_venta,
                    fecha_vencimiento, cantidad, estado
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active'
                )
            ");
            
            $stmt_insert->execute([
                $codigo_producto, 
                $pp_data['nombre'], 
                $pp_data['descripcion'], 
                $pp_data['id_categoria'], 
                $pp_data['id_subcategoria'],
                $pp_data['id_proveedor'], 
                $id_producto_proveedor, 
                $precio_costo_unitario,
                $precio_venta_calculado,
                $fecha_vencimiento,
                $total_unidades
            ]);
        }
        
        $pdo->commit();
        $_SESSION['mensaje'] = "✅ Compra de '$pp_data[nombre]' registrada exitosamente. Inventario actualizado.";
        
        header('Location: productos_proveedores.php?success=Compra+procesada+exitosamente');
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $error = "Error al procesar la compra: " . $e->getMessage();
        error_log($error);
        
        header('Location: productos_proveedores.php?error=' . urlencode($error));
        exit();
    }
} else {
    header('Location: productos_proveedores.php');
    exit();
}
?>