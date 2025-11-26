<?php
session_start();
require_once 'conexion.php';

header('Content-Type: application/json');

// Verificar que sea una petición GET y tenga el parámetro necesario
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['categoria_id'])) {
    $categoria_id = intval($_GET['categoria_id']);
    
    try {
        $stmt = $pdo->prepare("SELECT id, nombre FROM subcategorias WHERE categoria_id = ? AND estado = 'active' ORDER BY nombre");
        $stmt->execute([$categoria_id]);
        $subcategorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($subcategorias);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error al cargar subcategorías']);
    }
} else {
    echo json_encode([]);
}
?>