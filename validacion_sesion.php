<?php
session_start();
require_once 'conexion.php';
require_once 'permisos.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario']);
    $contraseña = $_POST['contraseña'];

    if (empty($usuario) || empty($contraseña)) {
        header('Location: login.php?error=campos_vacios');
        exit();
    }

    try {
        $sql = "SELECT u.id_usuario, u.usuario, u.nombre, u.contraseña, u.activo, r.nombre_rol, u.id_rol 
                FROM usuario u 
                INNER JOIN roles r ON u.id_rol = r.id_rol 
                WHERE u.usuario = :usuario";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':usuario', $usuario);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar si el usuario está activo
            if (!$user['activo']) {
                header('Location: login.php?error=usuario_inactivo');
                exit();
            }
            
            // Verificar contraseña 
            if ($contraseña === $user['contraseña']) {
                // OBTENER PERMISOS DESDE LA BASE DE DATOS - CONSULTA CORREGIDA
                $queryPermisos = "
                    SELECT m.nombre_modulo, p.ver, p.agregar, p.editar, p.eliminar, p.cambiar_estado 
                    FROM permisos p 
                    JOIN modulos m ON p.id_modulo = m.id_modulo 
                    WHERE p.id_rol = ?
                ";
                $stmtPermisos = $pdo->prepare($queryPermisos);
                $stmtPermisos->execute([$user['id_rol']]); // Usar id_rol en lugar de id_usuario
                $permisos_db = $stmtPermisos->fetchAll(PDO::FETCH_ASSOC);
                
                // DEBUG: Mostrar permisos obtenidos (eliminar después)
                error_log("Permisos para usuario {$user['usuario']} (rol {$user['id_rol']}): " . print_r($permisos_db, true));
                
                // Organizar permisos por módulo
                $permisosArray = [];
                foreach ($permisos_db as $permiso) {
                    $modulo = $permiso['nombre_modulo'];
                    $permisosArray[$modulo] = [
                        'ver' => (bool)$permiso['ver'],
                        'agregar' => (bool)$permiso['agregar'],
                        'editar' => (bool)$permiso['editar'],
                        'eliminar' => (bool)$permiso['eliminar'],
                        'cambiar_estado' => (bool)$permiso['cambiar_estado']
                    ];
                }
                
                // DEBUG: Mostrar array final de permisos (eliminar después)
                error_log("Array de permisos final: " . print_r($permisosArray, true));
                
                // Crear sesión con permisos
                $_SESSION['id_usuario'] = $user['id_usuario'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['nombre'] = $user['nombre'] ?? $user['usuario'];
                $_SESSION['rol'] = $user['nombre_rol'];
                $_SESSION['id_rol'] = $user['id_rol'];
                $_SESSION['permisos'] = $permisosArray;
                $_SESSION['loggedin'] = true;
                
                // Redirigir al inicio
                header('Location: inicio.php');
                exit();
            } else {
                header('Location: login.php?error=credenciales');
                exit();
            }
        } else {
            header('Location: login.php?error=credenciales');
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("Error en validacion_sesion: " . $e->getMessage());
        header('Location: login.php?error=bd');
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}
?>