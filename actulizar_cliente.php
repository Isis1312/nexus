<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nombre = trim($_POST['nombre']);
    $telefono = trim($_POST['telefono']);
    
    try {
        $sql = "UPDATE clientes SET nombre = ?, telefono = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $telefono, $id]);
        
        $_SESSION['mensaje'] = "Cliente actualizado correctamente";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al actualizar el cliente: " . $e->getMessage();
    }
    
    header('Location: clientes.php');
    exit();
}
?>