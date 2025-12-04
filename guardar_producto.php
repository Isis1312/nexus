<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $codigo = trim($_POST['codigo']);
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $categoria_id = $_POST['categoria_id'];
        $subcategoria_id = $_POST['subcategoria_id'] ?? null;
        $marca_id = $_POST['marca_id'];
        $proveedor_id = $_POST['proveedor_id'];
        $fecha_vencimiento = $_POST['fecha_vencimiento'] ?: null;
        $cantidad = $_POST['cantidad'];
        $precio_costo = $_POST['precio_costo'];
        $precio_venta = $_POST['precio_venta'];

        
        // Validar código único
        $stmt = $pdo->prepare("SELECT id FROM productos WHERE codigo = ?");
        $stmt->execute([$codigo]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "El código de producto ya existe en el sistema";
            header('Location: productos.php');
            exit();
        }
        
        // Calcular margen de ganancia
        $margen_ganancia = (($precio_venta - $precio_costo) / $precio_costo) * 100;
        
        // Insertar producto
        $sql = "INSERT INTO productos (codigo, nombre, descripcion, categoria_id, subcategoria_id, marca_id, 
                proveedor_id, fecha_vencimiento, , cantidad, cantidad_minima, 
                precio_costo, precio_venta, margen_ganancia, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 5, ?, ?,  'active')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codigo, $nombre, $descripcion, $categoria_id, $subcategoria_id, $marca_id,
                       $proveedor_id, $fecha_vencimiento, $cantidad,
                       $precio_costo, $precio_venta, $margen_ganancia]);
        
        $_SESSION['mensaje'] = "Producto registrado exitosamente";
        header('Location: productos.php');
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al registrar el producto: " . $e->getMessage();
        header('Location: productos.php');
        exit();
    }
} else {
    header('Location: productos.php');
    exit();
}
?>