<?php

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Inicializar sistema de permisos
require_once 'conexion.php';
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);


$mensaje = '';
$error = '';
$roles = array();
// Obtener roles para el select
try {
    $sqlRoles = "SELECT * FROM roles ORDER BY id_rol";
    $stmtRoles = $pdo->query($sqlRoles);
    $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error al cargar roles: ' . $e->getMessage();
    $roles = array();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $contraseña = $_POST['contraseña'];
    $id_rol = $_POST['id_rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Validaciones
    if (empty($usuario) || empty($nombre) || empty($apellido) || empty($contraseña) || empty($id_rol)) {
        $error = 'Todos los campos son obligatorios';
    } elseif (strpos($usuario, ' ') !== false) {
        $error = 'El nombre de usuario no puede contener espacios';
    } else {
        try {
            // Verificar si el usuario ya existe
            $sqlCheck = "SELECT COUNT(*) FROM usuario WHERE usuario = :usuario";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->bindParam(':usuario', $usuario);
            $stmtCheck->execute();
            
            if ($stmtCheck->fetchColumn() > 0) {
                $error = 'El nombre de usuario ya existe';
            } else {
                // Insertar nuevo usuario
                $sqlInsert = "INSERT INTO usuario (usuario, nombre, apellido, contraseña, id_rol, activo) 
                              VALUES (:usuario, :nombre, :apellido, :contrasena, :id_rol, :activo)";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->bindParam(':usuario', $usuario);
                $stmtInsert->bindParam(':nombre', $nombre);
                $stmtInsert->bindParam(':apellido', $apellido);
                $stmtInsert->bindParam(':contrasena', $contraseña);
                $stmtInsert->bindParam(':id_rol', $id_rol);
                $stmtInsert->bindParam(':activo', $activo, PDO::PARAM_INT);
                
                if ($stmtInsert->execute()) {
                    $mensaje = 'Usuario creado exitosamente';
                    // Limpiar formulario
                    $_POST = array();
                } else {
                    $error = 'Error al crear el usuario';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error de base de datos: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Usuario - NEXUS</title>
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/agregar_usuario.css">
</head>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">Agregar Nuevo Usuario</h1>
            </div>

            <?php if ($mensaje): ?>
                <div class="mensaje mensaje-exito"><?php echo $mensaje; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mensaje mensaje-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Nombre de Usuario *</label>
                        <input type="text" name="usuario" class="form-input" value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>" required>
                        <div class="form-note">No puede contener espacios</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-input" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Apellido *</label>
                        <input type="text" name="apellido" class="form-input" value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" name="contraseña" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Rol *</label>
                        <select name="id_rol" class="form-select" required>
                            <option value="">Seleccionar rol</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id_rol']; ?>" <?php echo (isset($_POST['id_rol']) && $_POST['id_rol'] == $rol['id_rol']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-container">
                            <input type="checkbox" name="activo" id="activo" <?php echo (!isset($_POST['activo']) || $_POST['activo']) ? 'checked' : ''; ?>>
                            <label class="form-label" for="activo" style="margin: 0;">Usuario Activo</label>
                        </div>
                    </div>

                    <div class="form-buttons">
                        <a href="gestion_usuarios.php" class="btn-volver">Volver</a>
                        <button type="submit" class="btn-guardar">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>