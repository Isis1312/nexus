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

$error = '';

// Verifica que la solicitud sea POST y que el carrito no esté vacío
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validar que haya productos en el carrito
    if (empty($_SESSION['carrito_compras'])) {
        $error = "El carrito de compras está vacío.";
        header('Location: productos_proveedores.php?error=' . urlencode($error));
        exit();
    }
    
    // Validaciones básicas de fechas
    if (empty($_POST['fecha_compra']) || empty($_POST['fecha_vencimiento_base'])) {
        $error = "Faltan datos de fecha esenciales para la compra.";
        header('Location: productos_proveedores.php?error=' . urlencode($error));
        exit();
    }
    
    $transactionActive = false;
    
    try {
        // INICIO DE LA TRANSACCIÓN
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $transactionActive = true;
        }
        
        $fecha_compra = $_POST['fecha_compra'];
        $fecha_vencimiento_base = $_POST['fecha_vencimiento_base'];
        $usuario_id = $_SESSION['id_usuario'] ?? 1;
        
        // Validar fecha de compra no sea futura
        $hoy = date('Y-m-d');
        if ($fecha_compra > $hoy) {
            throw new Exception("La fecha de compra no puede ser futura");
        }
        
        // 1. Procesar cada producto del carrito
        foreach ($_SESSION['carrito_compras'] as $id_producto => $item) {
            $id_producto_proveedor = $item['id_producto'];
            $cantidad_empaques = $item['cantidad_empaques'];
            $unidades_empaque = $item['unidades_empaque'];
            $precio_total = $item['precio_total'];
            $total_unidades = $cantidad_empaques * $unidades_empaque;
            
            // Validar datos básicos
            if ($cantidad_empaques <= 0 || $unidades_empaque <= 0 || $precio_total <= 0) {
                throw new Exception("Datos inválidos para el producto ID: $id_producto_proveedor");
            }
            
            // Cálculo del costo unitario
            $precio_costo_unitario = $precio_total / $total_unidades;

            // 2. Insertar en compras_proveedores (Registro principal de la compra)
            $stmt = $pdo->prepare("
                INSERT INTO compras_proveedores 
                (id_producto_proveedor, cantidad_empaques, unidades_empaque, 
                 fecha_compra, fecha_vencimiento, usuario_id) 
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

            // 3. Registrar en historial_compras (Detalle de la compra)
            $stmt_historial = $pdo->prepare("
                INSERT INTO historial_compras 
                (id_compra, id_producto_proveedor, cantidad_empaques, unidades_empaque, 
                 total_unidades, precio_total, fecha_compra, fecha_vencimiento, usuario_id) 
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
                $fecha_vencimiento_base,
                $usuario_id
            ]);

            // 4. LÓGICA CLAVE: Llamar al SP con 3 argumentos (INCLUYENDO FECHA VENCIMIENTO)
            $stmt_inventario = $pdo->prepare("
                CALL sp_insertar_producto_desde_proveedor(?, ?, ?)
            ");
            $stmt_inventario->execute([
                $id_producto_proveedor,     // Arg 1: id_producto_proveedor
                $total_unidades,            // Arg 2: p_cantidad_comprada
                $fecha_vencimiento_base     // Arg 3: p_fecha_vencimiento (NUEVO)
            ]);
            
            // Verificar si el procedimiento almacenado fue exitoso
            if ($stmt_inventario->errorCode() !== '00000') {
                $errorInfo = $stmt_inventario->errorInfo();
                throw new Exception("Error en SP para producto ID $id_producto_proveedor: " . $errorInfo[2]);
            }
        }
        
        // CONFIRMAR TRANSACCIÓN
        if ($pdo->inTransaction()) {
            $pdo->commit();
            $transactionActive = false;
        }
        
        // Vaciar carrito y notificar éxito
        $_SESSION['carrito_compras'] = [];
        $_SESSION['mensaje'] = "✅ Compra procesada exitosamente. El inventario ha sido actualizado.";
        
        header('Location: productos_proveedores.php?success=Compra+procesada+exitosamente');
        exit();
        
    } catch (Exception $e) {
        // MANEJO SEGURO DE TRANSACCIONES
        if ($pdo->inTransaction()) {
            try {
                $pdo->rollBack();
                $transactionActive = false;
            } catch (Exception $rollbackException) {
                error_log("Error al hacer rollback: " . $rollbackException->getMessage());
            }
        }
        
        $error = "Error al procesar la compra: " . $e->getMessage();
        error_log($error);
        
        // Opcional: mantener el carrito para que el usuario pueda corregir
        // $_SESSION['carrito_compras'] = [];
        
        header('Location: productos_proveedores.php?error=' . urlencode($error));
        exit();
    }
} else {
    // Si no hay datos de POST, redirigir
    header('Location: productos_proveedores.php');
    exit();
}
?>