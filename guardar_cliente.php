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
        
        // Insertar cliente
$stmt = $pdo->prepare("INSERT INTO clientes (nombre, cedula, telefono, direccion) VALUES (?, ?, ?, ?)");
$stmt->execute([$nombre, $cedula, $telefono, $direccion]);
$id_cliente = $pdo->lastInsertId();

// Insertar venta básica asociada
$stmtVenta = $pdo->prepare("INSERT INTO ventas (cliente, fecha, metodo_pago, total_bs, estado, id_cliente, total_usd, total_eur, tasa_usd, tasa_eur, nro_factura) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmtVenta->execute([
  $nombre,
  'Efectivo',
  0.00,
  'Pendiente',
  $id_cliente,
  0.00,
  0.00,
  10.0000,
  11.3636,
  'FAC-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT)
]);
        $_SESSION['mensaje'] = "Cliente agregado correctamente";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al guardar el cliente: " . $e->getMessage();
    }
    
    header('Location: clientes.php');
    exit();
}
?>