<?php
// nexus/procesar_compras_multiples.php - Versión con sintaxis corregida y flujo de compra mejorado
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
    foreach ($productos_a_procesar as $id_producto_proveedor => $data) {
        if (isset($data['seleccionar']) && $data['seleccionar'] === 'on') {
             $productos_validos[$id_producto_proveedor] = $data;
             $productos_seleccionados++;
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
        
        $id_compra_maestra = 0;
        
        foreach ($productos_validos as $id_producto_proveedor => $item) {
            
            $id_producto_proveedor = intval($id_producto_proveedor);
            $cantidad_empaques = intval($item['empaques'] ?? 0);
            $unidades_empaque = intval($item['unidades_x_empaque'] ?? 1); 
            $precio_total = floatval($item['precio_total'] ?? 0);

            $total_unidades = $cantidad_empaques * $unidades_empaque;
            
            // Re-validación estricta en el backend
            if ($cantidad_empaques <= 0 || $unidades_empaque <= 0 || $precio_total <= 0 || $total_unidades > 200) {
                 throw new Exception("Error de validación de datos para el producto ID $id_producto_proveedor: Cantidad/Precio inválido o total excede 200 unidades.");
            }
            
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
            
            // --- 1. CREAR REGISTRO MAESTRO (solo la primera vez) ---
            if ($id_compra_maestra === 0) {
                 $stmt_compra_maestra = $pdo->prepare("
                    INSERT INTO compras_proveedores 
                    (id_producto_proveedor, cantidad_empaques, unidades_empaque, fecha_compra, fecha_vencimiento, usuario_id, precio_costo_unitario, precio_total, precio_compra_total) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_compra_maestra->execute([
                    $id_producto_proveedor, 
                    $cantidad_empaques,
                    $unidades_empaque,
                    $fecha_compra,
                    $fecha_vencimiento_base,
                    $usuario_id,
                    $precio_costo_unitario,
                    $precio_total,
                    $precio_total 
                ]);
                
                $id_compra_maestra = $pdo->lastInsertId();
            }

            // --- 2. Registrar en historial_compras (Detalle de la compra - USA ID MAESTRO) ---
            $stmt_historial = $pdo->prepare("
                INSERT INTO historial_compras 
                (id_compra, id_producto_proveedor, cantidad_empaques, unidades_empaque, 
                 total_unidades, precio_total, fecha_compra, fecha_vencimiento, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_historial->execute([
                $id_compra_maestra, 
                $id_producto_proveedor,
                $cantidad_empaques,
                $unidades_empaque,
                $total_unidades,
                $precio_total,
                $fecha_compra,
                $fecha_vencimiento_base,
                $usuario_id
            ]);
            
            // --- 3. LÓGICA DE INVENTARIO: Siempre insertar un nuevo lote para este flujo simplificado ---
            
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
            
            
            // Actualizar precio de compra en la tabla de proveedores para reflejar el último costo
             $stmt_pp_update = $pdo->prepare("UPDATE productos_proveedor SET precio_compra = ?, fecha_compra = ? WHERE id_producto_proveedor = ?");
             $stmt_pp_update->execute([$precio_costo_unitario, $fecha_compra, $id_producto_proveedor]);
        } 
        
        $pdo->commit();
        $_SESSION['mensaje'] = "✅ Compra de $productos_seleccionados productos registrada exitosamente. Inventario actualizado.";
        
        // Redirigir al inventario
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