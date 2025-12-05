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



$mensaje = '';
$error = '';

// Verificar que se haya proporcionado un ID de usuario
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: gestion_usuarios.php');
    exit();
}

$id_usuario = $_GET['id'];

// Obtener datos del usuario
try {
    $sql = "SELECT u.*, r.nombre_rol 
            FROM usuario u 
            INNER JOIN roles r ON u.id_rol = r.id_rol 
            WHERE u.id_usuario = :id_usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        header('Location: gestion_usuarios.php');
        exit();
    }
} catch (PDOException $e) {
    $error = 'Error al cargar los datos del usuario: ' . $e->getMessage();
}

// Obtener roles para el select
try {
    $sqlRoles = "SELECT * FROM roles ORDER BY id_rol";
    $stmtRoles = $pdo->query($sqlRoles);
    $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error al cargar roles: ' . $e->getMessage();
    $roles = array();
}

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['editar_usuario'])) {
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $id_rol = $_POST['id_rol'];
        
        // Validaciones
        if (empty($nombre) || empty($apellido) || empty($id_rol)) {
            $error = 'Todos los campos son obligatorios';
        } else {
            try {
                // Actualizar usuario
                $sqlUpdate = "UPDATE usuario 
                            SET nombre = :nombre, apellido = :apellido, id_rol = :id_rol 
                            WHERE id_usuario = :id_usuario";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':nombre', $nombre);
                $stmtUpdate->bindParam(':apellido', $apellido);
                $stmtUpdate->bindParam(':id_rol', $id_rol, PDO::PARAM_INT);
                $stmtUpdate->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
                
                if ($stmtUpdate->execute()) {
                    $mensaje = 'Usuario actualizado exitosamente';
                    // Recargar datos del usuario
                    $stmt->execute();
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Error al actualizar el usuario';
                }
            } catch (PDOException $e) {
                $error = 'Error de base de datos: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - NEXUS</title>
    <link rel="stylesheet" href="css/editar_usuario.css">
</head>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">Editar Usuario</h1>
            </div>

            <?php if ($mensaje): ?>
                <div class="mensaje mensaje-exito"><?php echo $mensaje; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mensaje mensaje-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-container">
                <!-- Sección de información (solo lectura) -->
                <div class="info-section">
                    <h3>ℹ  Información del usuario</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">ID de Usuario:</span>
                            <span class="info-value"><?php echo htmlspecialchars($usuario['id_usuario']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nombre de Usuario:</span>
                            <span class="info-value"><?php echo htmlspecialchars($usuario['usuario']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Contraseña:</span>
                            <div class="password-container">
                                <span class="password-field" id="passwordField">••••••••</span>
                                <button type="button" class="btn-toggle-password" onclick="togglePassword()">👁</button>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Estado:</span>
                            <span class="status-badge <?php echo $usuario['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Sección editable -->
                <div class="editable-section">
                    <h3>✎ Datos Editables</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="editar_usuario" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">Nombre </label>
                            <input type="text" name="nombre" class="form-input" 
                                   value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Apellido </label>
                            <input type="text" name="apellido" class="form-input" 
                                   value="<?php echo htmlspecialchars($usuario['apellido']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Rol</label>
                            <select name="id_rol" class="form-select" required>
                                <option value="">Seleccionar rol</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?php echo $rol['id_rol']; ?>" 
                                        <?php echo ($usuario['id_rol'] == $rol['id_rol']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-buttons">
                            <a href="gestion_usuarios.php" class="btn-volver">
                                Volver
                            </a>
                            <button type="submit" class="btn-guardar">
                                Guardar 
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // Función para mostrar/ocultar contraseña
            function togglePassword() {
                const passwordField = document.getElementById('passwordField');
                const realPassword = '<?php echo htmlspecialchars($usuario['contraseña']); ?>';
                
                if (passwordField.textContent === '••••••••') {
                    passwordField.textContent = realPassword;
                } else {
                    passwordField.textContent = '••••••••';
                }
            }

            // Confirmación antes de salir si hay cambios sin guardar
            let formChanged = false;
            const formInputs = document.querySelectorAll('input, select');
            
            formInputs.forEach(input => {
                input.addEventListener('change', () => {
                    formChanged = true;
                });
            });

            document.querySelector('.btn-volver').addEventListener('click', (e) => {
                if (formChanged) {
                    if (!confirm('⚠  Tienes cambios sin guardar. ¿Estás seguro de que quieres volver?')) {
                        e.preventDefault();
                    }
                }
            });

            // Prevenir envío duplicado del formulario
            let formSubmitting = false;
            document.querySelector('form').addEventListener('submit', (e) => {
                if (formSubmitting) {
                    e.preventDefault();
                } else {
                    formSubmitting = true;
                    document.querySelector('.btn-guardar').disabled = true;
                    document.querySelector('.btn-guardar').innerHTML = '<span>⏳</span> Guardando...';
                }
            });
        </script>
    </main>
</body>
</html>