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

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombres = trim($_POST['nombres']);
        $nombre_comercial = trim($_POST['nombre_comercial']);
        $rif = trim($_POST['rif']);
        $telefono = trim($_POST['telefono']);
        $email = trim($_POST['email']);
        $direccion = trim($_POST['direccion']);
        
        // Validar campos requeridos
        if (empty($nombres) || empty($nombre_comercial) || empty($rif)) {
            $error = "Los campos marcados con * son obligatorios";
        } else {
            // Validar RIF único
            $stmt = $pdo->prepare("SELECT id_proveedor FROM proveedores WHERE rif = ?");
            $stmt->execute([$rif]);
            if ($stmt->fetch()) {
                $error = "El RIF ya está registrado en el sistema";
            } else {
                // Validar email único si se proporciona
                if (!empty($email)) {
                    $stmt = $pdo->prepare("SELECT id_proveedor FROM proveedores WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = "El email ya está registrado en el sistema";
                    }
                }
                
                if (empty($error)) {
                    // Insertar proveedor
                    $sql = "INSERT INTO proveedores (nombres, nombre_comercial, rif, telefono, email, direccion, estado) 
                            VALUES (?, ?, ?, ?, ?, ?, 'activo')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombres, $nombre_comercial, $rif, $telefono, $email, $direccion]);
                    
                    $mensaje = "Proveedor agregado exitosamente";
                    
                    // Limpiar formulario después de éxito
                    $_POST = array();
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Error al agregar el proveedor: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Proveedor - NEXUS</title>
    <link rel="stylesheet" href="css/agg_proveedor.css">
</head>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-container">
        <div class="content-wrapper">
       

            <?php if ($mensaje): ?>
                <div class="alert alert-success">
                    ✅ <?= htmlspecialchars($mensaje) ?>
                    <br><small>El proveedor ha sido registrado correctamente en el sistema.</small>
                </div>
            <?php endif; ?>
            
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
                                   value="<?= htmlspecialchars($_POST['nombres'] ?? '') ?>" required 
                                   placeholder="Ingrese los nombres completos del proveedor"
                                   maxlength="255">
                            <small class="form-text">Nombres y apellidos del representante</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre_comercial" class="required">Nombre Comercial *</label>
                            <input type="text" id="nombre_comercial" name="nombre_comercial" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['nombre_comercial'] ?? '') ?>" required 
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
                                   value="<?= htmlspecialchars($_POST['rif'] ?? '') ?>" required 
                                   placeholder="Ej: J-123456789"
                                   pattern="[JVGEP]-[0-9]{9}"
                                   title="Formato: J-123456789">
                            <small class="form-text">Formato: J-123456789</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" 
                                   placeholder="Ej: 0412-1234567"
                                   maxlength="20">
                            <small class="form-text">Teléfono de contacto principal</small>
                        </div>
                    </div>

                    <!-- Tercera fila: Email -->
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               placeholder="proveedor@ejemplo.com"
                               maxlength="100">
                        <small class="form-text">Correo electrónico de contacto</small>
                    </div>

                    <!-- Cuarta fila: Dirección -->
                    <div class="form-group">
                        <label for="direccion">Dirección</label>
                        <textarea id="direccion" name="direccion" class="form-control" 
                                  rows="3" placeholder="Ingrese la dirección completa del proveedor"
                                  maxlength="500"><?= htmlspecialchars($_POST['direccion'] ?? '') ?></textarea>
                        <small class="form-text">Dirección física de la empresa</small>
                    </div>

                    <div class="form-actions">
                        <a href="proveedores.php" class="btn btn-secondary">
                            ← Volver
                        </a>
                        <button type="button" class="btn btn-secondary" onclick="limpiarFormulario()">
                             Limpiar Formulario
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnGuardar">
                            Guardar 
                        </button>
                    </div>
                </form>
            </div>

            <!-- Información adicional -->
            <div class="info-box">
                <h4>! Importante !</h4>
                <ul>
                    <li>Los campos marcados con * son obligatorios</li>
                    <li>Verifique que los datos sean correctos antes de guardar</li>
                </ul>
            </div>
        </div>
    </main>

    <script>
        // Validación de RIF en tiempo real
        document.getElementById('rif').addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase();
            // Permitir solo letras, números y guiones
            value = value.replace(/[^A-Z0-9-]/g, '');
            e.target.value = value;
            
            // Validar formato básico
            if (value.length > 0 && !/^[JVGEP]-\d{1,9}$/.test(value)) {
                this.style.borderColor = '#f44336';
            } else {
                this.style.borderColor = '';
            }
        });

        // Validación de teléfono
        document.getElementById('telefono').addEventListener('input', function(e) {
            let value = e.target.value;
            // Permitir solo números, espacios, guiones y paréntesis
            value = value.replace(/[^0-9\s\-()]/g, '');
            e.target.value = value;
        });

        // Validación de email
        document.getElementById('email').addEventListener('blur', function(e) {
            const email = e.target.value;
            if (email && !isValidEmail(email)) {
                this.style.borderColor = '#f44336';
            } else {
                this.style.borderColor = '';
            }
        });

        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Limpiar formulario
        function limpiarFormulario() {
            if (confirm('¿Está seguro de que desea limpiar el formulario? Se perderán todos los datos ingresados.')) {
                document.getElementById('formProveedor').reset();
                // Remover estilos de validación
                const inputs = document.querySelectorAll('.form-control');
                inputs.forEach(input => input.style.borderColor = '');
            }
        }

        // Validación antes de enviar
        document.getElementById('formProveedor').addEventListener('submit', function(e) {
            const rif = document.getElementById('rif').value;
            const email = document.getElementById('email').value;
            
            // Validar formato de RIF
            if (!/^[JVGEP]-\d{9}$/.test(rif)) {
                e.preventDefault();
                alert('El RIF debe tener el formato correcto: J-123456789');
                document.getElementById('rif').focus();
                return;
            }
            
            // Validar email si se proporciona
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                alert('Por favor ingrese un correo electrónico válido');
                document.getElementById('email').focus();
                return;
            }
            
            // Mostrar loading
            const btnGuardar = document.getElementById('btnGuardar');
            btnGuardar.innerHTML = '⏳ Guardando...';
            btnGuardar.disabled = true;
        });

        // Contador de caracteres para textarea
        document.getElementById('direccion').addEventListener('input', function(e) {
            const maxLength = 500;
            const currentLength = e.target.value.length;
            const counter = document.getElementById('contadorDireccion') || crearContador();
            
            counter.textContent = `${currentLength}/${maxLength} caracteres`;
            
            if (currentLength > maxLength * 0.8) {
                counter.style.color = '#ff9800';
            } else {
                counter.style.color = '#666';
            }
        });

        function crearContador() {
            const contador = document.createElement('small');
            contador.id = 'contadorDireccion';
            contador.className = 'form-text';
            contador.style.display = 'block';
            contador.style.textAlign = 'right';
            contador.style.marginTop = '5px';
            document.getElementById('direccion').parentNode.appendChild(contador);
            return contador;
        }

        // Inicializar contador
        document.addEventListener('DOMContentLoaded', function() {
            crearContador();
            document.getElementById('direccion').dispatchEvent(new Event('input'));
        });
    </script>

</body>
</html>