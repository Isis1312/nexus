<?php
session_start();
require_once 'conexion.php';

// Obtener la página de origen (donde se hizo "Agregar Cliente")
$origen = $_POST['origen'] ?? 'clientes'; // Valor por defecto: clientes.php

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
            
            // Redirigir según el origen
            if ($origen === 'facturacion') {
                header('Location: facturacion.php');
            } else {
                header('Location: clientes.php');
            }
            exit();
        }
        
        // Insertar cliente (solo cliente, sin crear venta)
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre, cedula, telefono, direccion) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $cedula, $telefono, $direccion]);
        $id_cliente = $pdo->lastInsertId();

        // Guardar el ID del cliente recién creado para facturación
        // Solo si viene de facturación, para poder resaltarlo en la tabla
        if ($origen === 'facturacion') {
            $_SESSION['cliente_reciente'] = $id_cliente;
        }
        
        $_SESSION['mensaje'] = "Cliente agregado correctamente";
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al guardar el cliente: " . $e->getMessage();
    }
    
    // Redirigir según el origen
    if ($origen === 'facturacion') {
        header('Location: facturacion.php');
    } else {
        header('Location: clientes.php');
    }
    exit();
}
?>