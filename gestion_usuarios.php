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
if (!$sistemaPermisos->puedeVer('gestion_usuario')) {
    header('Location: inicio.php');
    exit();
}

// Procesar cambio de estado de usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    try {
        $id_usuario = $_POST['id_usuario'];
        $nuevo_estado = isset($_POST['nuevo_estado']) ? 1 : 0;
        
        // Verificar que el usuario no esté desactivándose a sí mismo
        if ($id_usuario == $_SESSION['id_usuario'] && $nuevo_estado == 0) {
            $_SESSION['error'] = "No puedes desactivar tu propio usuario";
            header('Location: gestion_usuarios.php');
            exit();
        }
        
        $sql = "UPDATE usuario SET activo = ? WHERE id_usuario = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nuevo_estado, $id_usuario]);
        
        $_SESSION['mensaje'] = "Estado del usuario actualizado correctamente";
        header('Location: gestion_usuarios.php');
        exit();
        
    } catch (PDOException $e) {
        $error_estado = "Error al cambiar el estado: " . $e->getMessage();
    }
}

// Procesar actualización de permisos desde el modal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_permisos'])) {
    try {
        $id_usuario = $_POST['id_usuario'];
        $id_rol = $_POST['id_rol'];
        
        // Verificar que no esté cambiando permisos de su propio usuario
        if ($id_usuario == $_SESSION['id_usuario']) {
            $_SESSION['error'] = "No puedes cambiar los permisos de tu propio usuario";
            header('Location: gestion_usuarios.php');
            exit();
        }
        
        // Obtener todos los módulos
        $sqlModulos = "SELECT id_modulo, nombre_modulo FROM modulos ORDER BY id_modulo";
        $stmtModulos = $pdo->query($sqlModulos);
        $modulos = $stmtModulos->fetchAll(PDO::FETCH_ASSOC);
        
        // Actualizar cada permiso en la tabla permisos (por rol)
        foreach ($modulos as $modulo) {
            $id_modulo = $modulo['id_modulo'];
            
            // Para cada módulo, obtener los permisos específicos que aplican
            $ver = isset($_POST['permisos'][$id_modulo]['ver']) ? 1 : 0;
            $agregar = isset($_POST['permisos'][$id_modulo]['agregar']) ? 1 : 0;
            $editar = isset($_POST['permisos'][$id_modulo]['editar']) ? 1 : 0;
            $eliminar = isset($_POST['permisos'][$id_modulo]['eliminar']) ? 1 : 0;
            $cambiar_estado = isset($_POST['permisos'][$id_modulo]['cambiar_estado']) ? 1 : 0;
            
            // Verificar si ya existe el permiso para este rol
            $sqlCheck = "SELECT COUNT(*) as count FROM permisos WHERE id_rol = ? AND id_modulo = ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$id_rol, $id_modulo]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existe['count'] > 0) {
                // Actualizar permiso existente
                $sqlUpdate = "UPDATE permisos SET 
                             ver = ?, agregar = ?, editar = ?, eliminar = ?, cambiar_estado = ?
                             WHERE id_rol = ? AND id_modulo = ?";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([$ver, $agregar, $editar, $eliminar, $cambiar_estado, $id_rol, $id_modulo]);
            } else {
                // Insertar nuevo permiso
                $sqlInsert = "INSERT INTO permisos (id_rol, id_modulo, ver, agregar, editar, eliminar, cambiar_estado)
                             VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([$id_rol, $id_modulo, $ver, $agregar, $editar, $eliminar, $cambiar_estado]);
            }
        }
        
        $_SESSION['mensaje'] = "Permisos actualizados correctamente para el usuario";
        header('Location: gestion_usuarios.php');
        exit();
        
    } catch (PDOException $e) {
        $error_permisos = "Error al actualizar permisos: " . $e->getMessage();
    }
}

// Obtener lista de usuarios
try {
    $sql = "SELECT u.*, r.nombre_rol, r.id_rol 
            FROM usuario u 
            INNER JOIN roles r ON u.id_rol = r.id_rol 
            ORDER BY u.id_usuario";
    $stmt = $pdo->query($sql);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_usuarios = "Error al cargar los usuarios: " . $e->getMessage();
    $usuarios = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - NEXUS</title>
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/g_usuarios.css">
    <style>
        /* Estilos adicionales específicos para el modal */
        .usuario-seleccionado {
            background: linear-gradient(135deg, rgba(0, 139, 139, 0.08), rgba(0, 102, 102, 0.08));
            padding: 18px 22px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 5px solid #008B8B;
            border: 2px solid rgba(0, 139, 139, 0.2);
        }
        
        .usuario-seleccionado strong {
            color: #008B8B;
            font-weight: 700;
        }
        
        .rol-info h4 {
            color: #008B8B;
            margin: 15px 0 8px 0;
            font-size: 1.3em;
            font-weight: 700;
        }
        
        .rol-description {
            color: rgba(51, 65, 85, 0.8);
            font-size: 1em;
            margin: 0 0 20px 0;
            line-height: 1.5;
        }
        
        /* Mejoras para checkboxes en modal */
        .permiso-opciones input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #008B8B;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        .permiso-opciones input[type="checkbox"]:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Toast de notificación */
        #toastNotificacion {
            position: fixed;
            top: 25px;
            right: 25px;
            background: linear-gradient(135deg, #23ca3f, #1ea832);
            color: white;
            padding: 18px 24px;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(35, 202, 63, 0.4);
            transform: translateX(150%);
            transition: transform 0.4s ease;
            z-index: 1001;
            border-left: 5px solid #1ea832;
            display: none;
        }
        
        #toastNotificacion.show {
            transform: translateX(0);
            display: block;
        }
        
        .toast-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .toast-icon {
            font-weight: bold;
            font-size: 1.3em;
        }
    </style>
</head>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">Gestión de Usuarios</h1>
            </div>

            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="mensaje-exito"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mensaje-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if (isset($error_estado)): ?>
                <div class="mensaje-error"><?php echo $error_estado; ?></div>
            <?php endif; ?>

            <?php if (isset($error_permisos)): ?>
                <div class="mensaje-error"><?php echo $error_permisos; ?></div>
            <?php endif; ?>

            <!-- Controles superiores -->
            <div class="controls-container">
                <?php if ($sistemaPermisos->puedeAgregar('gestion_usuario')): ?>
                    <a href="agregar_usuario.php" class="btn-agregar">
                        <span>+</span>
                        Agregar nuevo usuario
                    </a>
                <?php endif; ?>
                
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Buscar un usuario...">
                </div>
            </div>

            <!-- Tabla de usuarios -->
            <div class="users-table">
                <div class="table-header">
                    <h3>Usuarios de NEXUS</h3>
                </div>
                <div class="table-container">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>Nº</th>
                                <th>Usuario</th>
                                <th>Nombre completo</th>
                                <th>Contraseña</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($usuarios) > 0): ?>
                                <?php foreach ($usuarios as $index => $usuario): ?>
                                    <?php
                                    $nombreCompleto = $usuario['nombre'] . ' ' . ($usuario['apellido'] ?? '');
                                    $estado_texto = $usuario['activo'] ? 'Activo' : 'Inactivo';
                                    $esUsuarioActual = ($usuario['id_usuario'] == $_SESSION['id_usuario']);
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($nombreCompleto); ?></td>
                                        <td>
                                            <div class="password-container">
                                                <span class="password-field" data-password="<?php echo htmlspecialchars($usuario['contraseña']); ?>">
                                                    ••••••••
                                                </span>
                                                <button class="btn-toggle-password" onmousedown="mostrarPassword(this)" onmouseup="ocultarPassword(this)" ontouchstart="mostrarPassword(this)" ontouchend="ocultarPassword(this)">
                                                    👁
                                                </button>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($usuario['nombre_rol']); ?></td>
                                        <td>
                                            <?php if ($sistemaPermisos->puedeCambiarEstado('gestion_usuario')): ?>
                                                <form method="POST" action="" class="toggle-form" id="formEstado<?php echo $usuario['id_usuario']; ?>">
                                                    <input type="hidden" name="cambiar_estado" value="1">
                                                    <input type="hidden" name="id_usuario" value="<?php echo $usuario['id_usuario']; ?>">
                                                    <div class="toggle-container">
                                                        <label class="toggle-switch">
                                                            <input type="checkbox" name="nuevo_estado" value="1" 
                                                                   <?php echo $usuario['activo'] ? 'checked' : ''; ?>
                                                                   <?php echo $esUsuarioActual ? 'onclick="return false;"' : ''; ?>
                                                                   onchange="confirmarCambioEstado(<?php echo $usuario['id_usuario']; ?>, <?php echo $usuario['activo'] ? 'true' : 'false'; ?>, <?php echo $esUsuarioActual ? 'true' : 'false'; ?>)">
                                                            <span class="toggle-slider"></span>
                                                        </label>
                                                        <span class="toggle-label <?php echo $esUsuarioActual ? 'current-user' : ''; ?>">
                                                            <?php echo $estado_texto; ?>
                                                            <?php if ($esUsuarioActual): ?>
                                                                <br><small>(Tú)</small>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </form>
                                            <?php else: ?>
                                                <div class="toggle-container">
                                                    <span class="toggle-label-only <?php echo $usuario['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $estado_texto; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="acciones-container">
                                                <?php if ($sistemaPermisos->puedeEditar('gestion_usuario')): ?>
                                                    <a href="editar_usuario.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn-action btn-editar">
                                                        <span>✎</span>
                                                        Editar
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($sistemaPermisos->puedeEditar('gestion_usuario') && !$esUsuarioActual): ?>
                                                    <button class="btn-action btn-detalles" onclick="mostrarModalPermisos(
                                                        <?php echo $usuario['id_usuario']; ?>, 
                                                        '<?php echo htmlspecialchars(addslashes($usuario['usuario'])); ?>',
                                                        <?php echo $usuario['id_rol']; ?>, 
                                                        '<?php echo htmlspecialchars(addslashes($usuario['nombre_rol'])); ?>'
                                                    )">
                                                        <span>🔒</span>
                                                        Permisos
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="no-users">
                                        No hay usuarios registrados en el sistema.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal de permisos -->
            <div id="modalPermisos" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Configurar Permisos del Usuario</h3>
                        <span class="close-modal" onclick="cerrarModal()">&times;</span>
                    </div>
                    <form method="POST" action="" id="formPermisos">
                        <input type="hidden" name="actualizar_permisos" value="1">
                        <input type="hidden" name="id_usuario" id="id_usuario_modal">
                        <input type="hidden" name="id_rol" id="id_rol_modal">
                        
                        <div class="modal-body">
                            <div class="modal-scroll-content">
                                <div id="detallesContenido" class="rol-info">
                                    <!-- Información del usuario y rol se cargará aquí -->
                                </div>
                                
                                <div id="permisosGrid" class="permisos-grid">
                                    <!-- Los permisos se cargarán aquí dinámicamente -->
                                    <div class="cargando">Cargando permisos...</div>
                                </div>
                                
                                <div class="modal-actions">
                                    <button type="submit" class="btn-guardar">
                                        <span>💾</span>
                                        Guardar Cambios
                                    </button>
                                    <button type="button" class="btn-cancelar" onclick="cerrarModal()">
                                        <span>✕</span>
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Toast de notificación -->
            <div id="toastNotificacion">
                <div class="toast-content">
                    <span class="toast-icon">✓</span>
                    <span id="toastMensaje">Permisos actualizados correctamente</span>
                </div>
            </div>
        </div>

        <script>
            // Función para mostrar/ocultar contraseña
            function mostrarPassword(boton) {
                const container = boton.parentElement;
                const passwordField = container.querySelector('.password-field');
                const passwordReal = passwordField.getAttribute('data-password');
                passwordField.textContent = passwordReal;
            }

            function ocultarPassword(boton) {
                const container = boton.parentElement;
                const passwordField = container.querySelector('.password-field');
                passwordField.textContent = '••••••••';
            }

            // Función para buscar en la tabla
            document.getElementById('searchInput').addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('#usersTable tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // Función para confirmar cambio de estado
            function confirmarCambioEstado(idUsuario, estadoActual, esUsuarioActual) {
                if (esUsuarioActual) {
                    mostrarToast('No puedes cambiar el estado de tu propio usuario', 'error');
                    return false;
                }
                
                const nuevoEstado = !estadoActual;
                const accion = nuevoEstado ? 'activar' : 'desactivar';
                const mensaje = `¿Estás seguro de que deseas ${accion} este usuario?`;
                
                if (confirm(mensaje)) {
                    document.getElementById(`formEstado${idUsuario}`).submit();
                } else {
                    // Revertir el cambio visual del toggle
                    const checkbox = document.querySelector(`#formEstado${idUsuario} input[type="checkbox"]`);
                    checkbox.checked = estadoActual;
                    return false;
                }
            }

            // Funciones para el modal de permisos
            async function mostrarModalPermisos(idUsuario, usuarioNombre, idRol, rolNombre) {
                console.log("Mostrando modal para usuario:", idUsuario, usuarioNombre, idRol, rolNombre);
                const modal = document.getElementById('modalPermisos');
                const contenido = document.getElementById('detallesContenido');
                const permisosGrid = document.getElementById('permisosGrid');
                const idUsuarioInput = document.getElementById('id_usuario_modal');
                const idRolInput = document.getElementById('id_rol_modal');
                
                // Mostrar información del usuario
                contenido.innerHTML = `
                    <div class="usuario-seleccionado">
                        <strong>Usuario:</strong> ${usuarioNombre}<br>
                        <strong>Rol:</strong> ${rolNombre}
                    </div>
                    <h4>${rolNombre}</h4>
                    <p class="rol-description">Configura los permisos para el rol de ${rolNombre}. Los cambios afectarán a todos los usuarios con este rol.</p>
                `;
                
                idUsuarioInput.value = idUsuario;
                idRolInput.value = idRol;
                
                // Limpiar grid mientras carga
                permisosGrid.innerHTML = '<div class="cargando">Cargando permisos...</div>';
                
                // Cargar permisos desde el servidor
                await cargarPermisosRol(idRol);
                
                modal.style.display = 'block';
            }

            async function cargarPermisosRol(idRol) {
                try {
                    console.log("Cargando permisos para rol:", idRol);
                    const response = await fetch(`obtener_permisos.php?id_rol=${idRol}`);
                    
                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }
                    
                    const permisos = await response.json();
                    console.log("Permisos recibidos:", permisos);
                    
                    const permisosGrid = document.getElementById('permisosGrid');
                    permisosGrid.innerHTML = '';
                    
                    if (permisos.error) {
                        permisosGrid.innerHTML = '<div class="error-carga">Error: ' + permisos.error + '</div>';
                        return;
                    }
                    
                    if (permisos.length === 0) {
                        permisosGrid.innerHTML = '<div class="error-carga">No se encontraron permisos para este rol</div>';
                        return;
                    }
                    
                    permisos.forEach(permiso => {
                        const moduloHTML = crearHTMLModulo(permiso);
                        permisosGrid.innerHTML += moduloHTML;
                    });
                    
                    // Inicializar el estado de todos los checkboxes después de cargar
                    permisos.forEach(permiso => {
                        inicializarEstadoCheckboxes(permiso.id_modulo);
                    });
                    
                } catch (error) {
                    console.error('Error al cargar permisos:', error);
                    const permisosGrid = document.getElementById('permisosGrid');
                    permisosGrid.innerHTML = '<div class="error-carga">Error al cargar los permisos: ' + error.message + '</div>';
                }
            }

            function crearHTMLModulo(permiso) {
                const id_modulo = permiso.id_modulo;
                const nombreModulo = obtenerNombreModulo(permiso.nombre_modulo);
                const permisosHTML = generarCheckboxesPermisos(permiso);
                
                return `
                    <div class="permiso-modulo" data-modulo="${id_modulo}">
                        <h4>${nombreModulo}</h4>
                        <div class="permiso-opciones">
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][ver]" ${permiso.ver ? 'checked' : ''} 
                                       onchange="manejarCambioVer(${id_modulo})">
                                Ver
                            </label>
                            ${permisosHTML}
                        </div>
                    </div>
                `;
            }

            function generarCheckboxesPermisos(permiso) {
                const id_modulo = permiso.id_modulo;
                let html = '';
                
                // Según el módulo, mostrar solo los permisos relevantes
                switch(permiso.nombre_modulo) {
                    case 'gestion_usuario':
                        html = `
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][agregar]" ${permiso.agregar ? 'checked' : ''}>
                                Agregar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][editar]" ${permiso.editar ? 'checked' : ''}>
                                Editar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][cambiar_estado]" ${permiso.cambiar_estado ? 'checked' : ''}>
                                Cambiar Estado
                            </label>
                        `;
                        break;
                        
                    case 'clientes':
                    case 'inventario':
                        html = `
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][agregar]" ${permiso.agregar ? 'checked' : ''}>
                                Agregar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][editar]" ${permiso.editar ? 'checked' : ''}>
                                Editar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][eliminar]" ${permiso.eliminar ? 'checked' : ''}>
                                Eliminar
                            </label>
                        `;
                        break;
                        
                    case 'proveedores':
                    case 'reportes':
                    case 'ventas':
                        html = `
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][agregar]" ${permiso.agregar ? 'checked' : ''}>
                                Agregar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][editar]" ${permiso.editar ? 'checked' : ''}>
                                Editar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][eliminar]" ${permiso.eliminar ? 'checked' : ''}>
                                Eliminar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][cambiar_estado]" ${permiso.cambiar_estado ? 'checked' : ''}>
                                Cambiar Estado
                            </label>
                        `;
                        break;
                        
                    default:
                        html = `
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][agregar]" ${permiso.agregar ? 'checked' : ''}>
                                Agregar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][editar]" ${permiso.editar ? 'checked' : ''}>
                                Editar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][eliminar]" ${permiso.eliminar ? 'checked' : ''}>
                                Eliminar
                            </label>
                        `;
                }
                
                return html;
            }

            function inicializarEstadoCheckboxes(idModulo) {
                const verCheckbox = document.querySelector(`input[name="permisos[${idModulo}][ver]"]`);
                if (verCheckbox) {
                    manejarCambioVer(idModulo);
                }
            }

            // Función para manejar cambios en el checkbox "Ver"
            function manejarCambioVer(idModulo) {
                const verCheckbox = document.querySelector(`input[name="permisos[${idModulo}][ver]"]`);
                const moduloDiv = document.querySelector(`.permiso-modulo[data-modulo="${idModulo}"]`);
                
                if (!verCheckbox || !moduloDiv) {
                    console.error(`No se encontró el módulo ${idModulo}`);
                    return;
                }
                
                const estaActivo = verCheckbox.checked;
                const otrosCheckboxes = moduloDiv.querySelectorAll('input[type="checkbox"]:not([name*="[ver]"])');
                
                // Habilitar/deshabilitar otros checkboxes basado en "Ver"
                otrosCheckboxes.forEach(checkbox => {
                    checkbox.disabled = !estaActivo;
                    if (!estaActivo) {
                        checkbox.checked = false;
                    }
                });
            }

            function obtenerNombreModulo(nombre) {
                const nombres = {
                    'gestion_usuario': 'Gestión de Usuarios',
                    'clientes': 'Clientes',
                    'inventario': 'Inventario',
                    'proveedores': 'Proveedores',
                    'reportes': 'Reportes',
                    'ventas': 'Ventas'
                };
                return nombres[nombre] || nombre;
            }

            function cerrarModal() {
                const modal = document.getElementById('modalPermisos');
                modal.style.display = 'none';
            }

            // Cerrar modal al hacer click fuera
            window.onclick = function(event) {
                const modal = document.getElementById('modalPermisos');
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }

            // Manejar envío del formulario de permisos
            document.getElementById('formPermisos').addEventListener('submit', function(e) {
                if (!confirm('¿Estás seguro de actualizar los permisos de este rol? Esto afectará a todos los usuarios con este rol.')) {
                    e.preventDefault();
                } else {
                    console.log("Enviando formulario de permisos...");
                }
            });

            // Función para mostrar toast de notificación
            function mostrarToast(mensaje, tipo = 'exito') {
                const toast = document.getElementById('toastNotificacion');
                const toastMensaje = document.getElementById('toastMensaje');
                
                // Cambiar color según tipo
                if (tipo === 'error') {
                    toast.style.background = 'linear-gradient(135deg, #ff6b6b, #ff5252)';
                    toast.style.borderLeft = '5px solid #ff5252';
                } else {
                    toast.style.background = 'linear-gradient(135deg, #23ca3f, #1ea832)';
                    toast.style.borderLeft = '5px solid #1ea832';
                }
                
                toastMensaje.textContent = mensaje;
                toast.classList.add('show');
                
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        </script>
    </main>
</body>
</html>