<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Incluir la conexión a la base de datos
require_once 'conexion.php';

// Inicializar sistema de permisos
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

// Verificar si puede ver este módulo 
if (!$sistemaPermisos->puedeVer('proveedores')) {
    header('Location: inicio.php');
    exit();
}
// Obtener ID del proveedor a eliminar
$id_proveedor = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_proveedor <= 0) {
    $_SESSION['error'] = "ID de proveedor inválido";
    header('Location: proveedores.php');
    exit();
}

// Verificar si el proveedor existe
try {
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id_proveedor = ?");
    $stmt->execute([$id_proveedor]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proveedor) {
        $_SESSION['error'] = "Proveedor no encontrado";
        header('Location: proveedores.php');
        exit();
    }
} catch (PDOException $e) {
    die("Error al verificar proveedor: " . $e->getMessage());
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmacion = $_POST['confirmacion'] ?? '';
    
    // Debug: Ver qué está llegando
    error_log("Confirmación recibida: " . $confirmacion);
    
    if (strtoupper($confirmacion) === 'ELIMINAR') {
        try {
            // Iniciar transacción
            $pdo->beginTransaction();
            
            // 1. Desactivación del Proveedor (USA 'inactivo')
            $stmt = $pdo->prepare("UPDATE proveedores SET estado = 'inactivo' WHERE id_proveedor = ?");
            $stmt->execute([$id_proveedor]);
            
            // 2. Desactivar productos en INVENTARIO (USA 'inactive')
            $stmt = $pdo->prepare("UPDATE productos SET estado = 'inactive' WHERE proveedor_id = ?");
            $stmt->execute([$id_proveedor]);
            
            // 3. Desactivar productos en el catálogo de PROVEEDOR (USA 'inactivo')
            // ESTA ES LA LÍNEA QUE REQUIRIÓ LA MODIFICACIÓN DE LA BASE DE DATOS
            $stmt = $pdo->prepare("UPDATE productos_proveedor SET estado = 'inactivo' WHERE id_proveedor = ?");
            $stmt->execute([$id_proveedor]);
            
            $pdo->commit();
            
            $_SESSION['mensaje'] = "✅ Proveedor eliminado exitosamente";
            header('Location: proveedores.php');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al eliminar el proveedor: " . $e->getMessage();
            error_log("Error eliminando proveedor: " . $e->getMessage());
        }
    } else {
        $error = "❌ Debe escribir 'ELIMINAR' para confirmar la eliminación";
        error_log("Confirmación fallida: " . $confirmacion);
    }
}

require_once 'menu.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Proveedor - NEXUS</title>
    <link rel="stylesheet" href="css/proveedores.css">
    <link rel="stylesheet" href="css/eliminar_proveedor.css">
</head>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-content"> <div class="content-wrapper">


            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="confirm-container">
                <div class="proveedor-info">
                    <h3>📋 Información del Proveedor</h3>
                    <p><strong>Nombre Comercial:</strong> <?= htmlspecialchars($proveedor['nombre_comercial']) ?></p>
                    <p><strong>RIF:</strong> <?= htmlspecialchars($proveedor['rif']) ?></p>
                    <p><strong>Contacto:</strong> <?= htmlspecialchars($proveedor['nombres']) ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="<?= $proveedor['estado'] == 'activo' ? 'estado-activo' : 'estado-inactivo' ?>">
                            ● <?= ucfirst($proveedor['estado']) ?>
                        </span>
                    </p>
                </div>

                <div class="warning-box">
                    <h4>🚨 Advertencia Importante</h4>
                    <p>Esta acción <strong>NO se puede deshacer</strong>. El proveedor será marcado como inactivo.</p>
                </div>

                <form method="POST" action="" id="formEliminar">
                    <div class="danger-box">
                        <h4>🔒 Confirmación Requerida</h4>
                        <p>Para eliminar el proveedor, escriba <strong>ELIMINAR</strong> en el siguiente campo:</p>
                        <input type="text" name="confirmacion" class="confirm-input" 
                               placeholder="ELIMINAR" required autocomplete="off"
                               id="inputConfirmacion">
                        <small class="form-text">Debe escribir exactamente "ELIMINAR"</small>
                    </div>

                    <div class="form-actions">
                        <a href="proveedores.php" class="btn btn-secondary">
                            ← Cancelar y Volver
                        </a>
                        <button type="submit" class="btn btn-danger" id="btnEliminar">
                            🗑️ Eliminar Proveedor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="js/eliminar_proveedor.js"></script>
    
    <script>
        // Debug adicional
        console.log('Página de eliminación cargada');
        
        // Verificar que el formulario existe
        const form = document.getElementById('formEliminar');
        const input = document.getElementById('inputConfirmacion');
        const button = document.getElementById('btnEliminar');
        
        console.log('Formulario:', form);
        console.log('Input:', input);
        console.log('Botón:', button);
        
        if (!form) {
            console.error('❌ No se encontró el formulario con id "formEliminar"');
        }
        if (!input) {
            console.error('❌ No se encontró el input con id "inputConfirmacion"');
        }
        if (!button) {
            console.error('❌ No se encontró el botón con id "btnEliminar"');
        }
    </script>
</body>
</html>