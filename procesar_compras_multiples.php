<?php
// nexus/procesar_compras_multiples.php - Versión con sintaxis corregida y flujo de compra Maestro/Detalle
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

$error = '';
$usuario_id = $_SESSION['id_usuario'] ?? 4; // Usar un ID de usuario por defecto
$productos_a_procesar = $_POST['productos'] ?? [];
$productos_seleccionados = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $fecha_compra = $_POST['fecha_compra'] ?? date('Y-m-d');
    $fecha_vencimiento_base = $_POST['fecha_vencimiento_base'] ?? date('Y-m-d', strtotime('+30 days'));
    
    // Contar cuántos productos fueron realmente seleccionados (usando el flag oculto 'seleccionar' activado por JS)
    $productos_validos = [];
    $total_monto_compra = 0;
    
    foreach ($productos_a_procesar as $id_producto_proveedor => $data) {
        if (isset($data['seleccionar']) && $data['seleccionar'] === 'on') {
             $id_producto_proveedor = intval($id_producto_proveedor);
             $cantidad_empaques = intval($data['empaques'] ?? 0);
             $unidades_empaque = intval($data['unidades_x_empaque'] ?? 1); 
             $precio_total = floatval($data['precio_total'] ?? 0);
             $total_unidades = $cantidad_empaques * $unidades_empaque;

             // Re-validación estricta en el backend
             if ($cantidad_empaques > 0 && $precio_total > 0 && $total_unidades > 0 && $total_unidades <= 200) {
                 $productos_validos[$id_producto_proveedor] = $data;
                 $productos_seleccionados++;
                 $total_monto_compra += $precio_total;
             }
        }
    }
    
    if ($productos_seleccionados === 0) {
        $error = "Debe ingresar cantidades y precios válidos (mayores a cero) en al menos un producto para procesar la compra.";
        $_SESSION['error'] = $error;
        header('Location: productos_proveedores.php');
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        // --- 1. INSERCIÓN DEL REGISTRO MAESTRO (compras_proveedores) ---
        $stmt_compra_maestra = $pdo->prepare("
            INSERT INTO compras_proveedores 
            (fecha_compra, usuario_id, precio_compra_total) 
            VALUES (?, ?, ?)
        ");
        
        $stmt_compra_maestra->execute([
            $fecha_compra,
            $usuario_id,
            $total_monto_compra // Monto total de la compra
        ]);
        
        $id_compra_maestra = $pdo->lastInsertId();

        // --- 2. PROCESAMIENTO DE DETALLES (historial_compras e Inventario) ---
        foreach ($productos_validos as $id_producto_proveedor => $item) {
            
            $id_producto_proveedor = intval($id_producto_proveedor);
            $cantidad_empaques = intval($item['empaques'] ?? 0);
            $unidades_empaque = intval($item['unidades_x_empaque'] ?? 1); 
            $precio_total = floatval($item['precio_total'] ?? 0); // Costo de esta línea/lote

            $total_unidades = $cantidad_empaques * $unidades_empaque;
            $precio_costo_unitario = $precio_total / $total_unidades;
            $precio_venta_calculado = round($precio_costo_unitario * 1.30, 2); 

            // Obtener datos detallados del producto del proveedor
            $stmt_pp = $pdo->prepare("SELECT * FROM productos_proveedor WHERE id_producto_proveedor = ?");
            $stmt_pp->execute([$id_producto_proveedor]);
            $pp_data = $stmt_pp->fetch(PDO::FETCH_ASSOC);
            
            if (!$pp_data) continue;
            $codigo_producto = $pp_data['codigo_producto'];

            // --- 2.1 Insertar en historial_compras (Detalle/Puntual) ---
            $stmt_historial = $pdo->prepare("
                INSERT INTO historial_compras 
                (id_compra, id_producto_proveedor, cantidad_empaques, unidades_empaque, 
                 total_unidades, precio_total, fecha_vencimiento) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt_historial->execute([
                $id_compra_maestra, 
                $id_producto_proveedor,
                $cantidad_empaques,
                $unidades_empaque,
                $total_unidades,
                $precio_total,
                $fecha_vencimiento_base,
            ]);
            
            // --- 2.2 Lógica de Inventario (UPSERT en productos) ---
            
            // 1. Buscar si ya existe el producto en la tabla `productos` usando el CÓDIGO
            $stmt_check = $pdo->prepare("SELECT id, cantidad FROM productos WHERE codigo = ?");
            $stmt_check->execute([$codigo_producto]);
            $producto_inventario = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($producto_inventario) {
                // Producto existe: ACTUALIZAR STOCK y Precios/Vencimiento
                $nueva_cantidad = $producto_inventario['cantidad'] + $total_unidades;

                $stmt_update = $pdo->prepare("
                    UPDATE productos SET 
                        cantidad = ?, 
                        -- Se actualiza el costo y precio de venta con el último lote comprado (promedio real sería mejor, pero se usa simple para mantener la unicidad)
                        precio_costo = ?, 
                        precio_venta = ?,
                        -- La fecha de vencimiento se actualiza al ser un lote único para este código
                        fecha_vencimiento = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt_update->execute([
                    $nueva_cantidad,
                    $precio_costo_unitario,
                    $precio_venta_calculado,
                    $fecha_vencimiento_base,
                    $producto_inventario['id']
                ]);
            } else {
                // Producto no existe: INSERTAR nuevo producto
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
                    $pp_data['descripcion'] ?? '', 
                    $pp_data['id_categoria'], 
                    $pp_data['id_subcategoria'],
                    $pp_data['id_proveedor'], 
                    $id_producto_proveedor, 
                    $precio_costo_unitario,
                    $precio_venta_calculado,
                    $fecha_vencimiento_base,
                    $total_unidades
                ]);
            }
            
            
            // Actualizar precio de compra en la tabla de proveedores para reflejar el último costo
             $stmt_pp_update = $pdo->prepare("UPDATE productos_proveedor SET precio_compra = ?, fecha_compra = ? WHERE id_producto_proveedor = ?");
             $stmt_pp_update->execute([$precio_costo_unitario, $fecha_compra, $id_producto_proveedor]);
        } 
        
        $pdo->commit();
        $_SESSION['mensaje'] = "✅ Compra de $productos_seleccionados productos registrada exitosamente. Inventario actualizado.";
        
        // Redirigir a la página de inventario consolidado
        header('Location: productos.php'); 
        exit();
        
    } catch (Exception $e) { 
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $error = "Error al procesar la compra: " . $e->getMessage();
        error_log("Error en procesar_compras_multiples: " . $error);
        
        $_SESSION['error'] = $error;
        header('Location: productos_proveedores.php');
        exit();
    }
} else { 
    header('Location: productos_proveedores.php');
    exit();
}
?>