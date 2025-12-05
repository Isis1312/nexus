<?php
session_start();
require_once 'conexion.php';

// Asegurarnos de que la respuesta sea JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tipo_operacion = $_POST['tipo_operacion'] ?? '';
        
        if (empty($tipo_operacion)) {
            throw new Exception("Debe seleccionar el tipo de operación");
        }
        
        if ($tipo_operacion === 'nueva_categoria') {
            // --- Lógica para Nueva Categoría ---
            $nombre_categoria = trim($_POST['nombre_categoria']);
            
            if (empty($nombre_categoria)) {
                throw new Exception("El nombre de la categoría es requerido");
            }
            
            // 1. Verificar si la categoría ya existe
            $stmt = $pdo->prepare("SELECT id FROM categoria_prod WHERE nombre_categoria = ?");
            $stmt->execute([$nombre_categoria]);
            if ($stmt->fetch()) {
                throw new Exception("La categoría '$nombre_categoria' ya existe.");
            }
            
            // 2. Insertar categoría
            $stmt = $pdo->prepare("INSERT INTO categoria_prod (nombre_categoria, estado) VALUES (?, 'active')");
            $stmt->execute([$nombre_categoria]);
            $id_categoria = $pdo->lastInsertId();
            
            echo json_encode(['status' => 'success', 'message' => "Categoría '$nombre_categoria' creada exitosamente."]);
            
        } elseif ($tipo_operacion === 'nueva_subcategoria') {
            // --- Lógica para Nueva Subcategoría ---
            $categoria_existente = $_POST['categoria_existente'];
            $nombre_subcategoria = trim($_POST['nombre_subcategoria']);
            
            if (empty($categoria_existente)) {
                throw new Exception("Debe seleccionar una categoría padre.");
            }
            
            if (empty($nombre_subcategoria)) {
                throw new Exception("El nombre de la subcategoría es requerido.");
            }
            
            // 1. Verificar si la subcategoría ya existe DENTRO de esa categoría
            $stmt = $pdo->prepare("SELECT id FROM subcategorias WHERE categoria_id = ? AND nombre_subcategoria = ?");
            $stmt->execute([$categoria_existente, $nombre_subcategoria]);
            if ($stmt->fetch()) {
                throw new Exception("La subcategoría '$nombre_subcategoria' ya existe dentro de esta categoría.");
            }
            
            // 2. Insertar subcategoría
            $stmt = $pdo->prepare("INSERT INTO subcategorias (categoria_id, nombre_subcategoria, estado) VALUES (?, ?, 'active')");
            $stmt->execute([$categoria_existente, $nombre_subcategoria]);
            
            echo json_encode(['status' => 'success', 'message' => "Subcategoría '$nombre_subcategoria' agregada exitosamente."]);
        }
        
    } catch (Exception $e) {
        // Enviar el error al modal
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
?>