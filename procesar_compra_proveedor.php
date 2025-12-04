<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_producto_proveedor = $_POST['id_producto_proveedor'];
        $precio_compra_total = $_POST['precio_compra_total'];
        $cantidad_empaques = $_POST['cantidad_empaques'];
        $unidades_empaque = $_POST['unidades_empaque'];
        $fecha_compra = $_POST['fecha_compra'];
        $fecha_vencimiento = $_POST['fecha_vencimiento'];
        
        // Validar datos
        if (empty($id_producto_proveedor) || empty($precio_compra_total) || $precio_compra_total <= 0) {
            throw new Exception("El precio total de la compra debe ser mayor a 0");
        }
        
        if (empty($cantidad_empaques) || $cantidad_empaques <= 0) {
            throw new Exception("La cantidad de empaques debe ser mayor a 0");
        }
        
        if (empty($unidades_empaque) || $unidades_empaque <= 0) {
            throw new Exception("Debe especificar la cantidad de unidades por empaque");
        }
        
        if (empty($fecha_vencimiento)) {
            throw new Exception("La fecha de vencimiento es requerida");
        }
        
        if (strtotime($fecha_vencimiento) < strtotime(date('Y-m-d'))) {
            throw new Exception("La fecha de vencimiento no puede ser anterior a la fecha actual");
        }
        
        // Calcular cantidad total
        $cantidad_total = $cantidad_empaques * $unidades_empaque;
        
        if ($cantidad_total <= 0) {
            throw new Exception("El cálculo del total de unidades es inválido");
        }
        
        // Validar stock máximo
        if ($cantidad_total > 200) {
            throw new Exception("El stock no puede ser mayor a 200 unidades. Cantidad calculada: " . $cantidad_total);
        }
        
        // Verificar que el producto proveedor existe
        $stmt_check = $pdo->prepare("
            SELECT 
                pp.*, 
                pr.nombre_comercial, 
                cp.nombre_categoria, 
                cp.id as id_categoria,
                s.nombre_subcategoria,
                s.id as id_subcategoria
            FROM productos_proveedor pp
            LEFT JOIN proveedores pr ON pp.id_proveedor = pr.id_proveedor
            LEFT JOIN categoria_prod cp ON pp.id_categoria = cp.id
            LEFT JOIN subcategorias s ON pp.id_subcategoria = s.id
            WHERE pp.id_producto_proveedor = ?
        ");
        $stmt_check->execute([$id_producto_proveedor]);
        $producto_proveedor = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto_proveedor) {
            throw new Exception("Producto de proveedor no encontrado");
        }
        
        // Calcular precios (30% de margen)
        $precio_compra_unidad = $precio_compra_total / $cantidad_total;
        $precio_venta_unidad = $precio_compra_unidad * 1.30;
        
        // Actualizar precio en productos_proveedor
        $stmt_actualizar_precio = $pdo->prepare("
            UPDATE productos_proveedor 
            SET precio_compra = ?, 
                fecha_compra = ?,
                actualizacion = NOW()
            WHERE id_producto_proveedor = ?
        ");
        $stmt_actualizar_precio->execute([
            $precio_compra_unidad,
            $fecha_compra,
            $id_producto_proveedor
        ]);
        
        // Verificar si el producto ya existe en inventario
        $stmt_existe = $pdo->prepare("SELECT id, cantidad FROM productos WHERE codigo = ?");
        $stmt_existe->execute([$producto_proveedor['codigo_producto']]);
        $producto_existente = $stmt_existe->fetch(PDO::FETCH_ASSOC);
        
        if ($producto_existente) {
            // Actualizar producto existente
            $nuevo_stock = $producto_existente['cantidad'] + $cantidad_total;
            
            if ($nuevo_stock > 200) {
                throw new Exception("No se puede agregar stock. El stock total excedería 200 unidades. Stock actual: " . $producto_existente['cantidad']);
            }
            
            // Actualizar productos
            $stmt_update = $pdo->prepare("
                UPDATE productos 
                SET cantidad = ?,
                    precio_costo = ?,
                    precio_venta = ?,
                    fecha_vencimiento = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt_update->execute([
                $nuevo_stock,
                $precio_compra_unidad,
                $precio_venta_unidad,
                $fecha_vencimiento,
                $producto_existente['id']
            ]);
            
            $mensaje_stock = "Stock actualizado: " . $producto_existente['cantidad'] . " + " . $cantidad_total . " = " . $nuevo_stock . " unidades";
        } else {
            // Insertar nuevo producto
            $stmt_insert = $pdo->prepare("
                INSERT INTO productos (
                    codigo, 
                    nombre, 
                    categoria_id, 
                    subcategoria_id,
                    proveedor_id, 
                    id_producto_proveedor,
                    fecha_vencimiento,
                    cantidad,
                    precio_costo,
                    precio_venta,
                    created_at,
                    updated_at,
                    estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 'active')
            ");
            $stmt_insert->execute([
                $producto_proveedor['codigo_producto'],
                $producto_proveedor['nombre'],
                $producto_proveedor['id_categoria'],
                $producto_proveedor['id_subcategoria'],
                $producto_proveedor['id_proveedor'],
                $id_producto_proveedor,
                $fecha_vencimiento,
                $cantidad_total,
                $precio_compra_unidad,
                $precio_venta_unidad
            ]);
            
            $mensaje_stock = "Nuevo producto agregado: " . $cantidad_total . " unidades";
        }
        
        // Registrar la compra en el historial
        $stmt_historial = $pdo->prepare("
            INSERT INTO compras_proveedores 
            (id_producto_proveedor, cantidad_empaques, unidades_empaque, fecha_compra, fecha_vencimiento, usuario_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_historial->execute([
            $id_producto_proveedor, 
            $cantidad_empaques,
            $unidades_empaque,
            $fecha_compra,
            $fecha_vencimiento,
            $_SESSION['id_usuario'] ?? 1
        ]);
        
        // Registrar en historial_compras
        $id_compra = $pdo->lastInsertId();
        $total_unidades = $cantidad_empaques * $unidades_empaque;
        
        $stmt_historial_detalle = $pdo->prepare("
            INSERT INTO historial_compras 
            (id_compra, id_producto_proveedor, cantidad_empaques, unidades_empaque, 
             total_unidades, precio_total, fecha_compra, fecha_vencimiento, usuario_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_historial_detalle->execute([
            $id_compra,
            $id_producto_proveedor,
            $cantidad_empaques,
            $unidades_empaque,
            $total_unidades,
            $precio_compra_total,
            $fecha_compra,
            $fecha_vencimiento,
            $_SESSION['id_usuario'] ?? 1
        ]);
        
        $_SESSION['mensaje'] = "✅ Compra registrada: " . $cantidad_total . 
        " unidades - Vence: " . date('d/m/Y', strtotime($fecha_vencimiento));
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ Error al procesar la compra: " . $e->getMessage();
    }
    
    header('Location: productos_proveedores.php');
    exit();
} else {
    header('Location: productos_proveedores.php');
    exit();
}
?>