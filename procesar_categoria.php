<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tipo_operacion = $_POST['tipo_operacion'];
        
        if (empty($tipo_operacion)) {
            throw new Exception("Debe seleccionar el tipo de operación");
        }
        
        if ($tipo_operacion === 'nueva_categoria') {
            // Procesar nueva categoría
            $nombre_categoria = trim($_POST['nombre_categoria']);
            $agregar_subcategoria = isset($_POST['agregar_subcategoria']) ? 1 : 0;
            $nombre_subcategoria_nueva = trim($_POST['nombre_subcategoria_nueva'] ?? '');
            
            if (empty($nombre_categoria)) {
                throw new Exception("El nombre de la categoría es requerido");
            }
            
            // Verificar si la categoría ya existe
            $stmt = $pdo->prepare("SELECT id FROM categoria_prod WHERE nombre_categoria = ?");
            $stmt->execute([$nombre_categoria]);
            if ($stmt->fetch()) {
                throw new Exception("La categoría '$nombre_categoria' ya existe");
            }
            
            // Insertar categoría
            $stmt = $pdo->prepare("INSERT INTO categoria_prod (nombre_categoria, estado) VALUES (?, 'active')");
            $stmt->execute([$nombre_categoria]);
            $id_categoria = $pdo->lastInsertId();
            
            // Insertar subcategoría si se especificó
            if ($agregar_subcategoria && !empty($nombre_subcategoria_nueva)) {
                $stmt = $pdo->prepare("INSERT INTO subcategorias (categoria_id, nombre_subcategoria, estado) VALUES (?, ?, 'active')");
                $stmt->execute([$id_categoria, $nombre_subcategoria_nueva]);
            }
            
            $_SESSION['mensaje'] = "Categoría '$nombre_categoria' registrada exitosamente" . 
                                  ($agregar_subcategoria && !empty($nombre_subcategoria_nueva) ? " con subcategoría '$nombre_subcategoria_nueva'" : "");
            
        } elseif ($tipo_operacion === 'nueva_subcategoria') {
            // Procesar nueva subcategoría
            $categoria_existente = $_POST['categoria_existente'];
            $nombre_subcategoria = trim($_POST['nombre_subcategoria']);
            
            if (empty($categoria_existente)) {
                throw new Exception("Debe seleccionar una categoría existente");
            }
            
            if (empty($nombre_subcategoria)) {
                throw new Exception("El nombre de la subcategoría es requerido");
            }
            
            // Verificar si la subcategoría ya existe en esta categoría
            $stmt = $pdo->prepare("SELECT id FROM subcategorias WHERE categoria_id = ? AND nombre_subcategoria = ?");
            $stmt->execute([$categoria_existente, $nombre_subcategoria]);
            if ($stmt->fetch()) {
                throw new Exception("La subcategoría '$nombre_subcategoria' ya existe en esta categoría");
            }
            
            // Obtener nombre de la categoría para el mensaje
            $stmt = $pdo->prepare("SELECT nombre_categoria FROM categoria_prod WHERE id = ?");
            $stmt->execute([$categoria_existente]);
            $categoria_nombre = $stmt->fetchColumn();
            
            // Insertar subcategoría
            $stmt = $pdo->prepare("INSERT INTO subcategorias (categoria_id, nombre_subcategoria, estado) VALUES (?, ?, 'active')");
            $stmt->execute([$categoria_existente, $nombre_subcategoria]);
            
            $_SESSION['mensaje'] = "Subcategoría '$nombre_subcategoria' agregada exitosamente a la categoría '$categoria_nombre'";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error al procesar la operación: " . $e->getMessage();
    }
    
    header('Location: productos_proveedores.php');
    exit();
} else {
    header("Location: productos_proveedores.php?error_categoria=La categoría 'alcohol' ya existe");
exit();
}
?>