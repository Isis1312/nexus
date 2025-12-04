<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

if (!isset($_GET['id_rol'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID de rol no proporcionado']);
    exit();
}

$id_rol = intval($_GET['id_rol']);

try {
    // Verificar si el rol existe
    $sqlRol = "SELECT id_rol FROM roles WHERE id_rol = ?";
    $stmtRol = $pdo->prepare($sqlRol);
    $stmtRol->execute([$id_rol]);
    
    if ($stmtRol->rowCount() === 0) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Rol no encontrado']);
        exit();
    }

    // Obtener todos los permisos para el rol
    $sql = "SELECT m.id_modulo, m.nombre_modulo, 
                   COALESCE(p.ver, 0) as ver, 
                   COALESCE(p.agregar, 0) as agregar, 
                   COALESCE(p.editar, 0) as editar, 
                   COALESCE(p.eliminar, 0) as eliminar, 
                   COALESCE(p.cambiar_estado, 0) as cambiar_estado
            FROM modulos m 
            LEFT JOIN permisos p ON m.id_modulo = p.id_modulo AND p.id_rol = ?
            ORDER BY m.id_modulo";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_rol]);
    $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($permisos);
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>