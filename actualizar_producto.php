<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = intval($_POST['id']);
        $cantidad = intval($_POST['cantidad']);
        $precio_costo = floatval($_POST['precio_costo']);
        $precio_venta = floatval($_POST['precio_venta']);
        
        // Validaciones
        if ($cantidad < 1 || $cantidad > 200) {
            throw new Exception("La cantidad debe estar entre 1 y 200");
        }
        
        if ($precio_costo <= 0 || $precio_venta <= 0) {
            throw new Exception("Los precios deben ser mayores a 0");
        }
        
        $stmt = $pdo->prepare("UPDATE productos SET 
            cantidad = ?, 
            precio_costo = ?, 
            precio_venta = ?,
            margen_ganancia = ?,
            updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?");
        
        $margen_ganancia = (($precio_venta - $precio_costo) / $precio_costo) * 100;
        
        $stmt->execute([$cantidad, $precio_costo, $precio_venta, $margen_ganancia, $id]);
        
        $_SESSION['mensaje'] = "Producto actualizado con éxito";
        header('Location: productos.php?success=1');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error al actualizar el producto: " . $e->getMessage();
        header('Location: productos.php');
        exit();
    }
} else {
    header('Location: productos.php');
    exit();
}
?>