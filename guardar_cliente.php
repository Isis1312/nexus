<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $cedula = trim($_POST['cedula']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    
    try {
        // Verificar si la cédula ya existe
        $sqlCheck = "SELECT id FROM clientes WHERE cedula = ?";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$cedula]);
        
        if ($stmtCheck->rowCount() > 0) {
            $_SESSION['error'] = "Ya existe un cliente con esta cédula";
            header('Location: clientes.php');
            exit();
        }
        
        // Insertar nuevo cliente
        $sql = "INSERT INTO clientes (nombre, cedula, telefono, direccion) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $cedula, $telefono, $direccion]);
        
        $_SESSION['mensaje'] = "Cliente agregado correctamente";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al guardar el cliente: " . $e->getMessage();
    }
    
    header('Location: clientes.php');
    exit();
}
?>