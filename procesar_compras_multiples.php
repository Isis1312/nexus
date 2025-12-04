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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['carrito_compras'])) {
    $pdo->beginTransaction();
    
    try {
        $fecha_compra = $_POST['fecha_compra'];
        $fecha_vencimiento_base = $_POST['fecha_vencimiento_base'];
        $usuario_id = $_SESSION['id_usuario'];
        
        // Procesar cada producto del carrito
        foreach ($_SESSION['carrito_compras'] as $item) {
            $id_producto_proveedor = $item['id_producto'];
            $cantidad_empaques = $item['cantidad_empaques'];
            $unidades_empaque = $item['unidades_empaque'];
            $precio_total = $item['precio_total'];
            
            // Insertar en compras_proveedores
            $stmt = $pdo->prepare("
                INSERT INTO compras_proveedores 
                (id_producto_proveedor, cantidad_empaques, unidades_empaque, fecha_compra, fecha_vencimiento, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_producto_proveedor,
                $cantidad_empaques,
                $unidades_empaque,
                $fecha_compra,
                $fecha_vencimiento_base,
                $usuario_id
            ]);
            
            $id_compra = $pdo->lastInsertId();
            
            // Calcular precio por unidad
            $total_unidades = $cantidad_empaques * $unidades_empaque;
            $precio_por_unidad = $precio_total / $total_unidades;
            
            // Actualizar producto proveedor con precio y fecha
            $stmt = $pdo->prepare("
                UPDATE productos_proveedor 
                SET precio_compra = ?, fecha_compra = ?, actualizacion = NOW() 
                WHERE id_producto_proveedor = ?
            ");
            $stmt->execute([$precio_por_unidad, $fecha_compra, $id_producto_proveedor]);
            
            // Verificar si el producto ya existe en inventario
            $stmt = $pdo->prepare("
                SELECT id, cantidad FROM productos 
                WHERE id_producto_proveedor = ? AND estado = 'active'
            ");
            $stmt->execute([$id_producto_proveedor]);
            $producto_existente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($producto_existente) {
                // Actualizar producto existente
                $nueva_cantidad = $producto_existente['cantidad'] + $total_unidades;
                $stmt = $pdo->prepare("
                    UPDATE productos 
                    SET cantidad = ?, precio_costo = ?, precio_venta = ROUND(? * 1.30, 2),
                        fecha_vencimiento = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nueva_cantidad,
                    $precio_por_unidad,
                    $precio_por_unidad,
                    $fecha_vencimiento_base,
                    $producto_existente['id']
                ]);
            } else {
                // Insertar nuevo producto en inventario
                $stmt = $pdo->prepare("
                    SELECT pp.*, p.nombre_comercial 
                    FROM productos_proveedor pp
                    JOIN proveedores p ON pp.id_proveedor = p.id_proveedor
                    WHERE pp.id_producto_proveedor = ?
                ");
                $stmt->execute([$id_producto_proveedor]);
                $producto_proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($producto_proveedor) {
                    $stmt = $pdo->prepare("
                        INSERT INTO productos 
                        (codigo, nombre, categoria_id, subcategoria_id, proveedor_id, 
                         id_producto_proveedor, fecha_vencimiento, cantidad, precio_costo, 
                         precio_venta, unidad_medida, es_perecedero, estado,
                         created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                    ");
                    $stmt->execute([
                        $producto_proveedor['codigo_producto'],
                        $producto_proveedor['nombre'],
                        $producto_proveedor['id_categoria'],
                        $producto_proveedor['id_subcategoria'],
                        $producto_proveedor['id_proveedor'],
                        $id_producto_proveedor,
                        $fecha_vencimiento_base,
                        $total_unidades,
                        $precio_por_unidad,
                        round($precio_por_unidad * 1.30, 2),
                        $producto_proveedor['unidad_medida'],
                        $producto_proveedor['es_perecedero']
                    ]);
                }
            }
            
            // Registrar en historial
            $stmt = $pdo->prepare("
                INSERT INTO historial_compras 
                (id_compra, id_producto_proveedor, cantidad_empaques, unidades_empaque, 
                 total_unidades, precio_total, fecha_compra, fecha_vencimiento, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_compra,
                $id_producto_proveedor,
                $cantidad_empaques,
                $unidades_empaque,
                $total_unidades,
                $precio_total,
                $fecha_compra,
                $fecha_vencimiento_base,
                $usuario_id
            ]);
        }
        
        $pdo->commit();
        
        // Vaciar carrito
        $_SESSION['carrito_compras'] = [];
        $_SESSION['mensaje'] = "✅ Compra procesada exitosamente. Todos los productos han sido agregados al inventario.";
        
        header('Location: productos_proveedores.php?success=Compra+procesada+exitosamente');
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al procesar la compra: " . $e->getMessage();
        header('Location: productos_proveedores.php?error=' . urlencode($error));
        exit();
    }
} else {
    header('Location: productos_proveedores.php?error=Carrito+vacio+o+solicitud+invalida');
    exit();
}
?>