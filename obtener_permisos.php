<?php
// obtener_permisos_usuario.php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

if (!isset($_GET['id_usuario']) || !isset($_GET['id_rol'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Parámetros incompletos']);
    exit();
}

$id_usuario = intval($_GET['id_usuario']);
$id_rol = intval($_GET['id_rol']);

try {
    // Obtener permisos combinados: personalizados del usuario o por defecto del rol
    $sql = "SELECT 
                m.id_modulo, 
                m.nombre_modulo,
                COALESCE(pu.ver, p.ver, 0) as ver,
                COALESCE(pu.agregar, p.agregar, 0) as agregar,
                COALESCE(pu.editar, p.editar, 0) as editar,
                COALESCE(pu.eliminar, p.eliminar, 0) as eliminar,
                COALESCE(pu.cambiar_estado, p.cambiar_estado, 0) as cambiar_estado,
                CASE WHEN pu.id_usuario IS NOT NULL THEN 1 ELSE 0 END as es_personalizado
            FROM modulos m
            LEFT JOIN permisos p ON m.id_modulo = p.id_modulo AND p.id_rol = ?
            LEFT JOIN permisos_usuario pu ON m.id_modulo = pu.id_modulo AND pu.id_usuario = ?
            ORDER BY m.id_modulo";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_rol, $id_usuario]);
    $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($permisos);
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>