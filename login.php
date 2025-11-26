<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - NEXUS</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
 

    <div class="contenedor">
        <div class="contenedor-6">
            <div class="mensaje">
                <h3>NEXUS SYSTEM</h3>
            </div>
            
            <form action="validacion_sesion.php" method="post">
                <label>Usuario:</label>
                <input type="text" name="usuario" value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
                
                <label>Contraseña:</label>
                <input type="password" name="contraseña" required placeholder="••••••••">
                
                <button type="submit">Ingresar</button>
            </form>
            
            <?php if (isset($_GET['error'])): ?>
                <div style="color: #d9534f; text-align: center; margin-top: 10px;">
                    <?php
                    switch ($_GET['error']) {
                        case 'credenciales':
                            echo 'Usuario o contraseña incorrectos';
                            break;
                        case 'campos_vacios':
                            echo 'Por favor complete todos los campos';
                            break;
                        case 'usuario_inactivo':
                            echo 'Usuario inactivo. Contacte al administrador';
                            break;
                        case 'bd':
                            echo 'Error del sistema. Intente más tarde';
                            break;
                        default:
                            echo 'Error al iniciar sesión';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="version-badge">v1.1</div>
</body>
</html>
