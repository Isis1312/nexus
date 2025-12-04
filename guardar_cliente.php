<?php
session_start();
require_once 'conexion.php';

$origen = $_POST['origen'] ?? 'clientes';
$esAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $cedula = trim($_POST['cedula']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    
    try {
        $sqlCheck = "SELECT id FROM clientes WHERE cedula = ?";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$cedula]);
        
        if ($stmtCheck->rowCount() > 0) {
            if ($esAjax) {
                echo json_encode(['success' => false, 'message' => 'Ya existe un cliente con esta cédula']);
            } else {
                $_SESSION['error'] = "Ya existe un cliente con esta cédula";
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO clientes (nombre, cedula, telefono, direccion) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nombre, $cedula, $telefono, $direccion]);
            $id_cliente = $pdo->lastInsertId();

            if ($origen === 'facturacion') {
                $_SESSION['cliente_reciente'] = $id_cliente;
            }
            
            if ($esAjax) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Cliente agregado correctamente',
                    'id_cliente' => $id_cliente,
                    'cedula' => $cedula,
                    'nombre' => $nombre
                ]);
            } else {
                $_SESSION['mensaje'] = "Cliente agregado correctamente";
            }
        }
        
    } catch (PDOException $e) {
        if ($esAjax) {
            echo json_encode(['success' => false, 'message' => 'Error al guardar el cliente: ' . $e->getMessage()]);
        } else {
            $_SESSION['error'] = "Error al guardar el cliente: " . $e->getMessage();
        }
    }
    
    if (!$esAjax) {
        if ($origen === 'facturacion') {
            header('Location: facturacion.php');
        } else {
            header('Location: clientes.php');
        }
        exit();
    }
}
?>