<?php
session_start();
require_once 'conexion.php';

// Obtener la página de origen (donde se hizo  "Agregar Cliente")
$origen = $_POST['origen'] ?? 'clientes'; // Valor por defecto: clientes.php
$cliente_id_retorno = $_POST['cliente_id_retorno'] ?? null;

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
                header('Location: clientes_factura.php');
            } else {
                header('Location: clientes.php');
            }
            exit();
        }
        
        // Insertar cliente
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre, cedula, telefono, direccion) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $cedula, $telefono, $direccion]);
        $id_cliente = $pdo->lastInsertId();

        // Si viene de facturación, también crear una venta básica asociada
        if ($origen === 'facturacion') {
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
        }
        
        $_SESSION['mensaje'] = "Cliente agregado correctamente";
        
        // Guardar el ID del cliente recién creado para facturación
        if ($origen === 'facturacion') {
            $_SESSION['cliente_reciente'] = $id_cliente;
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al guardar el cliente: " . $e->getMessage();
    }
    
    // Redirigir según el origen
    if ($origen === 'facturacion') {
        header('Location: clientes_factura.php');
    } else {
        header('Location: clientes.php');
    }
    exit();
}
?>