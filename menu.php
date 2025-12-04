<?php

// 1. Verificar si una sesión ya está activa antes de iniciarla
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

//  Redirigir 
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'permisos.php';

$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/menu.css">
</head>
<body>
    <div class="main-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>NEXUS SYSTEM</h2>
                <div class="user-info">
                    <small><?php echo $_SESSION['nombre']; ?> (<?php echo $_SESSION['rol']; ?>)</small>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="inicio.php" class="menu-item <?php echo $current_page == 'inicio.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">🏠</span>
                        <span class="menu-text">Inicio</span>
                    </a>
                </li>
                
                <!-- Gestión de Usuarios - Solo si puede ver -->
                <?php if ($sistemaPermisos->puedeVer('gestion_usuario')): ?>
                <li>
                    <a href="gestion_usuarios.php" class="menu-item <?php echo $current_page == 'gestion_usuarios.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">👥</span>
                        <span class="menu-text">Gestión de Usuarios</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Proveedores - Solo si puede ver -->
                <?php if ($sistemaPermisos->puedeVer('proveedores')): ?>
                <li>
                    <a href="proveedores.php" class="menu-item <?php echo $current_page == 'proveedores.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">📦</span>
                        <span class="menu-text">Proveedores</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Productos - Solo si puede ver -->
                <?php if ($sistemaPermisos->puedeVer('Inventario')): ?>
                <li>
                    <a href="productos.php" class="menu-item <?php echo $current_page == 'productos.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">📊</span>
                        <span class="menu-text">Inventario</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Reportes - Solo si puede ver -->
                <?php if ($sistemaPermisos->puedeVer('reportes')): ?>
                <li>
                    <a href="reportes.php" class="menu-item <?php echo $current_page == 'reportes.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">📈</span>
                        <span class="menu-text">Reportes</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Clientes - Solo si puede ver -->
                <?php if ($sistemaPermisos->puedeVer('clientes')): ?>
                <li>
                    <a href="clientes.php" class="menu-item <?php echo $current_page == 'clientes.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">👤</span>
                        <span class="menu-text">Clientes</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Ventas - Solo si puede ver -->
                <!--<?php if ($sistemaPermisos->puedeVer('ventas')): ?>
                <li>
                    <a href="venta.php" class="menu-item <?php echo $current_page == 'venta.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">🛒</span>
                        <span class="menu-text">Ventas</span>
                    </a>
                </li>
                <?php endif; ?>-->


                <li>
                    <a href="facturacion.php" class="menu-item <?php echo $current_page == 'facturacion.php' ? 'active' : ''; ?>">
                         <span class="menu-icon">⇄</span>
                        <span class="menu-text">Facturación</span>
                    </a>
                </li>
              


                <div class="menu-separator"></div>
                
                <li class="logout-item">
                    <a href="cerrar_sesion.php" class="menu-item">
                        <span class="menu-icon">🚪</span>
                        <span class="menu-text">Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </nav>