<?php
session_start();
require_once 'conexion.php';

//tasas desde el archivo json
$tasas_file = 'js/tasas_cache.json';
if (file_exists($tasas_file)) {
    $tasas_data = json_decode(file_get_contents($tasas_file), true);
    $tasa_usd = isset($tasas_data['dolar']) ? floatval($tasas_data['dolar']) : 0;
    $tasa_eur = isset($tasas_data['euro']) ? floatval($tasas_data['euro']) : 0;
} else {
    $tasa_usd = 250.00;
    $tasa_eur = 270.00;
}

$total_usd = $tasa_usd > 0 ? ($total_bs / $tasa_usd) : 0;
$total_eur = $tasa_eur > 0 ? ($total_bs / $tasa_eur) : 0;




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cliente = trim($_POST['cliente']);
        $fecha = $_POST['fecha'];
        $metodo_pago = $_POST['metodo'];
        $total_bs = $_POST['total_bs'];
        $estado = $_POST['estado'];
        $id_cliente = $_POST['id_cliente'] ?? null; // si seleccionas cliente de la tabla clientes
        $tasa_usd = 10.0000; 
        $tasa_eur = 11.3636;

        // Calcular totales en otras monedas
        $total_usd = $total_bs / $tasa_usd;
        $total_eur = $total_bs / $tasa_eur;

        // Generar número de factura simple
        $nro_factura = 'FAC-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);

        // Insertar venta
        // Obtener el último número de factura
$stmt = $pdo->query("SELECT nro_factura FROM ventas ORDER BY id DESC LIMIT 1");
$ultima = $stmt->fetchColumn();

if ($ultima && preg_match('/FAC-(\d+)/', $ultima, $match)) {
    $numero = intval($match[1]) + 1;
} else {
    $numero = 1;
}

$nro_factura = 'FAC-' . str_pad($numero, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("INSERT INTO ventas 
            (cliente, fecha, metodo_pago, total_bs, estado, id_cliente, total_usd, total_eur, tasa_usd, tasa_eur, nro_factura) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cliente, $fecha, $metodo_pago, $total_bs, $estado, $id_cliente, $total_usd, $total_eur, $tasa_usd, $tasa_eur, $nro_factura]);

        $_SESSION['mensaje'] = "✅ Venta registrada correctamente.";
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ Error al registrar la venta: " . $e->getMessage();
    }

    header('Location: venta.php');
    exit();
}
?>