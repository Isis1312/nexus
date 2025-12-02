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
if (!$sistemaPermisos->puedeVer('reportes')) {
    header('Location: inicio.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Métricas</title>
    <link rel="stylesheet" href="css/resportes.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-content">
        <div class="content-wrapper">
            <!-- Header de la página -->
            <div class="page-header">
                <h1 class="page-title">Reportes y Métricas</h1>
                
            </div>

            <!-- Submenú de Reportes - BOTONES HORIZONTALES -->
            <div class="submenu-reportes">
                <div class="submenu-botones">
                     <!-- AQUI VAN LOS BOTONESSS -->
                </div>
            </div>

           
                       
</body>
</html>