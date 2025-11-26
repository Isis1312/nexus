<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}


require_once 'conexion.php';
require_once 'menu.php';
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

// Verificar si puede ver este módulo 
if (!$sistemaPermisos->puedeVer('proveedores')) {
    header('Location: inicio.php');
    exit();
}
$mensaje = '';
$error = '';
$proveedor = null;

// Obtener ID del proveedor a editar
$id_proveedor = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_proveedor <= 0) {
    header('Location: proveedores.php');
    exit();
}

// Obtener datos del proveedor
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
    die("Error al cargar proveedor: " . $e->getMessage());
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombres = trim($_POST['nombres']);
        $nombre_comercial = trim($_POST['nombre_comercial']);
        $rif = trim($_POST['rif']);
        $telefono = trim($_POST['telefono']);
        $email = trim($_POST['email']);
        $direccion = trim($_POST['direccion']);
        $estado = $_POST['estado'];
        
        // Validar campos requeridos
        if (empty($nombres) || empty($nombre_comercial) || empty($rif)) {
            $error = "Los campos marcados con * son obligatorios";
        } else {
            // Validar RIF único (excluyendo el actual)
            $stmt = $pdo->prepare("SELECT id_proveedor FROM proveedores WHERE rif = ? AND id_proveedor != ?");
            $stmt->execute([$rif, $id_proveedor]);
            if ($stmt->fetch()) {
                $error = "El RIF ya está registrado en el sistema";
            } else {
                // Validar email único si se proporciona
                if (!empty($email)) {
                    $stmt = $pdo->prepare("SELECT id_proveedor FROM proveedores WHERE email = ? AND id_proveedor != ?");
                    $stmt->execute([$email, $id_proveedor]);
                    if ($stmt->fetch()) {
                        $error = "El email ya está registrado en el sistema";
                    }
                }
                
                if (empty($error)) {
                    // Actualizar proveedor
                    $sql = "UPDATE proveedores SET nombres = ?, nombre_comercial = ?, rif = ?, telefono = ?, 
                            email = ?, direccion = ?, estado = ?, actualizacion = CURRENT_TIMESTAMP 
                            WHERE id_proveedor = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombres, $nombre_comercial, $rif, $telefono, $email, $direccion, $estado, $id_proveedor]);
                    
                    $_SESSION['mensaje'] = "Proveedor actualizado exitosamente";
                    header('Location: proveedores.php');
                    exit();
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Error al actualizar el proveedor: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Proveedor - NEXUS</title>
    <link rel="stylesheet" href="css/editar_proveedor.css">
</head>
<body>
   
    
    <main class="main-container">
        <div class="content-wrapper">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="" id="formProveedor">
                    <!-- Primera fila: Nombres y Nombre Comercial -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombres" class="required">Nombres del Proveedor *</label>
                            <input type="text" id="nombres" name="nombres" class="form-control" 
                                   value="<?= htmlspecialchars($proveedor['nombres']) ?>" required 
                                   placeholder="Ingrese los nombres completos del proveedor"
                                   maxlength="255">
                            <small class="form-text">Nombres y apellidos del representante</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre_comercial" class="required">Nombre Comercial *</label>
                            <input type="text" id="nombre_comercial" name="nombre_comercial" class="form-control" 
                                   value="<?= htmlspecialchars($proveedor['nombre_comercial']) ?>" required 
                                   placeholder="Ingrese el nombre comercial de la empresa"
                                   maxlength="255">
                            <small class="form-text">Nombre de la empresa o negocio</small>
                        </div>
                    </div>

                    <!-- Segunda fila: RIF y Teléfono -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rif" class="required">RIF *</label>
                            <input type="text" id="rif" name="rif" class="form-control" 
                                   value="<?= htmlspecialchars($proveedor['rif']) ?>" required 
                                   placeholder="Ej: J-123456789"
                                   pattern="[JVGEP]-[0-9]{9}"
                                   title="Formato: J-123456789">
                            <small class="form-text">Formato: J-123456789</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" class="form-control" 
                                   value="<?= htmlspecialchars($proveedor['telefono']) ?>" 
                                   placeholder="Ej: 0412-1234567"
                                   maxlength="20">
                            <small class="form-text">Teléfono de contacto principal</small>
                        </div>
                    </div>

                    <!-- Tercera fila: Email -->
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($proveedor['email']) ?>" 
                               placeholder="proveedor@ejemplo.com"
                               maxlength="100">
                        <small class="form-text">Correo electrónico de contacto</small>
                    </div>

                    <!-- Cuarta fila: Dirección -->
                    <div class="form-group">
                        <label for="direccion">Dirección</label>
                        <textarea id="direccion" name="direccion" class="form-control" 
                                  rows="3" placeholder="Ingrese la dirección completa del proveedor"
                                  maxlength="500"><?= htmlspecialchars($proveedor['direccion']) ?></textarea>
                        <small class="form-text">Dirección física de la empresa</small>
                    </div>

                    <!-- Quinta fila: Estado -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="estado" class="required">Estado *</label>
                            <select id="estado" name="estado" class="form-control" required>
                                <option value="activo" <?= $proveedor['estado'] == 'activo' ? 'selected' : '' ?>>Activo</option>
                                <option value="inactivo" <?= $proveedor['estado'] == 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                            <small class="form-text">Estado del proveedor en el sistema</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Información del Registro</label>
                            <div class="info-registro">
                                <small>
                                    <strong>Registrado:</strong> <?= date('d/m/Y H:i', strtotime($proveedor['registro'])) ?><br>
                                    <?php if ($proveedor['actualizacion']): ?>
                                        <strong>Última actualización:</strong> <?= date('d/m/Y H:i', strtotime($proveedor['actualizacion'])) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="proveedores.php" class="btn btn-secondary">
                            ← Cancelar y Volver
                        </a>
                        <button type="submit" class="btn btn-primary" id="btnGuardar">
                            Actualizar 
                        </button>
                    </div>
                </form>
            </div>

            <!-- Información adicional -->
            <div class="info-box">
                <h4>📋 Información</h4>
                <ul>
                    <li><strong>ID:</strong> <?= $proveedor['id_proveedor'] ?></li>
                    <li><strong>Estado actual:</strong> 
                        <span class="<?= $proveedor['estado'] == 'activo' ? 'estado-activo' : 'estado-inactivo' ?>">
                            ● <?= ucfirst($proveedor['estado']) ?>
                        </span>
                    <li>Si desactiva el proveedor, no podrá asignarle nuevos productos</li>
                </ul>
            </div>
    </div>
</main>

    <script src="js/editar_proveedor.js"></script>
</body>
</html>