-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 05-12-2025 a las 04:50:17
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `nexus_bd`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_insertar_producto_desde_proveedor` (IN `p_id_producto_proveedor` INT, IN `p_cantidad_comprada` INT)   BEGIN
    DECLARE v_codigo VARCHAR(50);
    DECLARE v_nombre VARCHAR(255);
    DECLARE v_descripcion TEXT;
    DECLARE v_id_categoria INT;
    DECLARE v_id_proveedor INT;
    DECLARE v_precio_compra DECIMAL(10,2);
    DECLARE v_unidad_medida VARCHAR(20);
    DECLARE v_es_perecedero TINYINT(1);
    DECLARE v_existe_producto INT;
    DECLARE v_producto_id INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_existe_producto
    FROM productos_proveedor
    WHERE id_producto_proveedor = p_id_producto_proveedor;

    IF v_existe_producto = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Producto del proveedor no existe';
    END IF;

    SELECT 
        codigo_producto, nombre, NULL, id_categoria,
        id_proveedor, precio_compra, unidad_medida, es_perecedero
    INTO 
        v_codigo, v_nombre, v_descripcion, v_id_categoria,
        v_id_proveedor, v_precio_compra, v_unidad_medida, v_es_perecedero
    FROM productos_proveedor
    WHERE id_producto_proveedor = p_id_producto_proveedor;

    SELECT COUNT(*), COALESCE(id, 0) INTO v_existe_producto, v_producto_id
    FROM productos
    WHERE codigo = v_codigo; -- quitamos estado

    IF v_existe_producto > 0 THEN
        UPDATE productos
        SET cantidad = cantidad + p_cantidad_comprada,
            precio_costo = v_precio_compra,
            precio_venta = ROUND(v_precio_compra * 1.30, 2),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = v_producto_id;
    ELSE
        INSERT INTO productos (
            codigo, nombre, descripcion, categoria_id, proveedor_id,
            id_producto_proveedor, precio_costo, precio_venta,
            fecha_vencimiento, cantidad, created_at, updated_at
        ) VALUES (
            v_codigo, v_nombre, v_descripcion, v_id_categoria, v_id_proveedor,
            p_id_producto_proveedor, v_precio_compra,
            ROUND(v_precio_compra * 1.30, 2),
            NULL, p_cantidad_comprada, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        );
    END IF;

    COMMIT;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria_prod`
--

CREATE TABLE `categoria_prod` (
  `id` int(11) NOT NULL,
  `nombre_categoria` varchar(50) NOT NULL,
  `estado` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categoria_prod`
--

INSERT INTO `categoria_prod` (`id`, `nombre_categoria`, `estado`, `created_at`) VALUES
(1, 'Lácteos', 'active', '2025-11-12 15:01:55'),
(2, 'Enlatados', 'inactive', '2025-11-12 15:01:55'),
(5, 'Salsas', 'active', '2025-11-12 15:01:55'),
(7, 'Snack', 'active', '2025-11-12 15:01:55'),
(18, 'Bebidas', 'active', '2025-12-05 03:01:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `cedula` int(8) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `direccion` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `cedula`, `telefono`, `direccion`) VALUES
(1, 'Isis Sofia', 29604083, '04160588684', 'Av. libertador con calle 57'),
(5, 'jose pernalete', 30797057, '04122201285', 'avenida españa entre calle 6 y 7'),
(11, 'Daviana', 1111111, '2147483647', 'su casa'),
(12, 'marco', 888888, '04160588684', 'su casa');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras_proveedores`
--

CREATE TABLE `compras_proveedores` (
  `id_compra` int(11) NOT NULL,
  `id_producto_proveedor` int(11) NOT NULL,
  `cantidad_empaques` int(11) NOT NULL,
  `unidades_empaque` int(11) NOT NULL,
  `fecha_compra` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `compras_proveedores`
--

INSERT INTO `compras_proveedores` (`id_compra`, `id_producto_proveedor`, `cantidad_empaques`, `unidades_empaque`, `fecha_compra`, `fecha_vencimiento`, `usuario_id`, `fecha_registro`) VALUES
(1, 5, 0, 0, '2025-11-03', '2025-12-03', 5, '2025-11-03 15:37:20'),
(2, 5, 5, 12, '2025-11-03', '2025-12-03', 5, '2025-11-03 15:55:31'),
(3, 5, 4, 3, '2025-11-09', '2025-12-09', 5, '2025-11-09 21:21:59'),
(4, 5, 12, 5, '2025-11-12', '2025-12-12', 5, '2025-11-12 04:49:39'),
(5, 5, 10, 10, '2025-11-12', '2025-12-12', 4, '2025-11-12 13:30:23'),
(6, 8, 10, 5, '2025-11-12', '2025-12-12', 4, '2025-11-12 16:30:44'),
(7, 9, 5, 2, '2025-11-12', '2025-12-12', 4, '2025-11-12 20:51:20'),
(8, 9, 7, 5, '2025-11-12', '2025-12-12', 4, '2025-11-12 20:53:33'),
(9, 8, 4, 2, '2025-11-12', '2025-12-12', 4, '2025-11-12 20:55:30'),
(10, 11, 2, 10, '2025-11-12', '2025-12-12', 4, '2025-11-12 21:38:24'),
(11, 11, 1, 2, '2025-11-12', '2025-12-12', 4, '2025-11-12 21:38:49'),
(12, 11, 1, 2, '2025-11-14', '2026-03-04', 4, '2025-11-14 11:39:49'),
(13, 10, 1, 12, '2025-11-14', '2026-01-28', 4, '2025-11-14 12:25:25'),
(14, 5, 14, 9, '2025-12-03', '2026-01-02', 5, '2025-12-03 22:45:00'),
(15, 9, 11, 10, '2025-12-04', '2026-01-03', 5, '2025-12-03 23:18:59'),
(16, 11, 6, 10, '2025-12-04', '2026-01-03', 5, '2025-12-03 23:18:59'),
(23, 8, 2, 10, '2025-12-03', '2026-01-08', 4, '2025-12-04 01:25:44'),
(29, 8, 1, 10, '2025-12-04', '2026-01-03', 4, '2025-12-04 01:36:40'),
(30, 8, 1, 100, '2025-12-04', '2026-06-04', 4, '2025-12-04 01:38:08'),
(31, 8, 25, 100, '2025-12-04', '2026-01-03', 4, '2025-12-04 01:39:08'),
(32, 9, 5, 40, '2025-12-04', '2026-04-30', 4, '2025-12-04 03:48:16'),
(33, 11, 5, 40, '2025-12-04', '2026-04-30', 4, '2025-12-04 03:48:16'),
(38, 10, 10, 20, '2025-12-04', '2026-07-29', 4, '2025-12-04 06:26:35'),
(39, 9, 8, 15, '2025-12-04', '2026-07-29', 4, '2025-12-04 06:26:35'),
(40, 11, 20, 10, '2025-12-04', '2026-07-29', 4, '2025-12-04 06:26:35'),
(41, 1, 10, 10, '2025-12-04', '2026-05-29', 4, '2025-12-04 15:29:27'),
(42, 9, 10, 50, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(43, 11, 10, 10, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(44, 1, 10, 20, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(45, 10, 10, 20, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(46, 4, 10, 10, '2025-12-04', '2026-01-03', 4, '2025-12-04 15:39:27'),
(47, 9, 10, 20, '2025-12-04', '2026-01-03', 4, '2025-12-04 15:39:27'),
(48, 3, 5, 10, '2025-12-04', '2026-01-03', 4, '2025-12-04 15:39:27'),
(49, 9, 20, 10, '2025-12-04', '2025-12-31', 4, '2025-12-04 17:59:29'),
(50, 9, 1, 2, '2025-12-04', '2025-12-26', 4, '2025-12-04 18:05:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_venta`
--

CREATE TABLE `detalle_venta` (
  `id_detalle` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `codigo_producto` varchar(50) DEFAULT NULL,
  `nombre_producto` varchar(120) DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario_bs` decimal(12,2) NOT NULL,
  `precio_unitario_usd` decimal(10,2) NOT NULL,
  `subtotal_bs` decimal(12,2) NOT NULL,
  `subtotal_usd` decimal(10,2) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_venta`
--

INSERT INTO `detalle_venta` (`id_detalle`, `id_venta`, `id_producto`, `codigo_producto`, `nombre_producto`, `cantidad`, `precio_unitario_bs`, `precio_unitario_usd`, `subtotal_bs`, `subtotal_usd`, `fecha_registro`) VALUES
(6, 9, 39, 'PROD-005', 'Yogurt Natural', 2.00, 0.00, 2.60, 0.00, 5.20, '2025-12-05 02:10:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_compras`
--

CREATE TABLE `historial_compras` (
  `id_historial` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `id_producto_proveedor` int(11) NOT NULL,
  `cantidad_empaques` int(11) NOT NULL,
  `unidades_empaque` int(11) NOT NULL,
  `total_unidades` int(11) NOT NULL,
  `precio_total` decimal(10,2) NOT NULL,
  `fecha_compra` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_compras`
--

INSERT INTO `historial_compras` (`id_historial`, `id_compra`, `id_producto_proveedor`, `cantidad_empaques`, `unidades_empaque`, `total_unidades`, `precio_total`, `fecha_compra`, `fecha_vencimiento`, `usuario_id`, `fecha_registro`) VALUES
(1, 15, 9, 11, 10, 110, 250.00, '2025-12-04', '2026-01-03', 5, '2025-12-03 23:18:59'),
(2, 16, 11, 6, 10, 60, 520.00, '2025-12-04', '2026-01-03', 5, '2025-12-03 23:18:59'),
(7, 23, 8, 2, 10, 20, 100.00, '2025-12-03', '2026-01-08', 4, '2025-12-04 01:25:44'),
(8, 29, 8, 1, 10, 10, 50.00, '2025-12-04', '2026-01-03', 4, '2025-12-04 01:36:40'),
(9, 30, 8, 1, 100, 100, 4.00, '2025-12-04', '2026-06-04', 4, '2025-12-04 01:38:08'),
(10, 31, 8, 25, 100, 2500, 10.00, '2025-12-04', '2026-01-03', 4, '2025-12-04 01:39:08'),
(11, 32, 9, 5, 40, 200, 100.00, '2025-12-04', '2026-04-30', 4, '2025-12-04 03:48:16'),
(12, 33, 11, 5, 40, 200, 80.00, '2025-12-04', '2026-04-30', 4, '2025-12-04 03:48:16'),
(13, 38, 10, 10, 20, 200, 600.00, '2025-12-04', '2026-07-29', 4, '2025-12-04 06:26:35'),
(14, 39, 9, 8, 15, 120, 500.00, '2025-12-04', '2026-07-29', 4, '2025-12-04 06:26:35'),
(15, 40, 11, 20, 10, 200, 500.00, '2025-12-04', '2026-07-29', 4, '2025-12-04 06:26:35'),
(16, 41, 1, 10, 10, 100, 600.00, '2025-12-04', '2026-05-29', 4, '2025-12-04 15:29:27'),
(17, 42, 9, 10, 50, 500, 200.00, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(18, 43, 11, 10, 10, 100, 500.00, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(19, 44, 1, 10, 20, 200, 400.00, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(20, 45, 10, 10, 20, 200, 500.00, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(21, 46, 4, 10, 10, 100, 20.00, '2025-12-04', '2026-01-03', 4, '2025-12-04 15:39:27'),
(22, 47, 9, 10, 20, 200, 200.00, '2025-12-04', '2026-01-03', 4, '2025-12-04 15:39:27'),
(23, 48, 3, 5, 10, 50, 100.00, '2025-12-04', '2026-01-03', 4, '2025-12-04 15:39:27'),
(24, 49, 9, 20, 10, 200, 500.00, '2025-12-04', '2025-12-31', 4, '2025-12-04 17:59:29'),
(25, 50, 9, 1, 2, 2, 50.00, '2025-12-04', '2025-12-26', 4, '2025-12-04 18:05:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos`
--

CREATE TABLE `modulos` (
  `id_modulo` int(11) NOT NULL,
  `nombre_modulo` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `modulos`
--

INSERT INTO `modulos` (`id_modulo`, `nombre_modulo`) VALUES
(5, 'clientes'),
(1, 'gestion_usuario'),
(3, 'Inventario'),
(2, 'proveedores'),
(4, 'reportes'),
(6, 'ventas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id_rol` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_modulo` int(11) NOT NULL,
  `agregar` tinyint(1) DEFAULT 0,
  `editar` tinyint(1) DEFAULT 0,
  `eliminar` tinyint(1) DEFAULT 0,
  `cambiar_estado` tinyint(1) DEFAULT 0,
  `ver` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id_rol`, `id_usuario`, `id_modulo`, `agregar`, `editar`, `eliminar`, `cambiar_estado`, `ver`) VALUES
(1, NULL, 1, 1, 1, 0, 1, 1),
(1, NULL, 2, 1, 1, 1, 1, 1),
(1, NULL, 3, 1, 1, 1, 0, 1),
(1, NULL, 4, 1, 1, 1, 0, 1),
(1, NULL, 5, 1, 1, 1, 0, 1),
(1, NULL, 6, 1, 1, 1, 0, 1),
(2, NULL, 1, 0, 0, 0, 0, 0),
(2, NULL, 2, 1, 1, 1, 1, 1),
(2, NULL, 3, 1, 1, 1, 1, 1),
(2, NULL, 4, 1, 1, 1, 1, 1),
(2, NULL, 5, 1, 1, 1, 0, 1),
(2, NULL, 6, 1, 1, 1, 1, 1),
(3, NULL, 1, 0, 0, 0, 0, 0),
(3, NULL, 2, 0, 0, 0, 0, 0),
(3, NULL, 3, 0, 0, 0, 0, 1),
(3, NULL, 4, 1, 1, 1, 1, 1),
(3, NULL, 5, 1, 1, 1, 0, 1),
(3, NULL, 6, 1, 1, 1, 0, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `proveedor_id` int(11) NOT NULL,
  `id_producto_proveedor` int(11) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `precio_costo` decimal(10,2) NOT NULL,
  `precio_venta` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `codigo`, `nombre`, `categoria_id`, `subcategoria_id`, `proveedor_id`, `id_producto_proveedor`, `fecha_vencimiento`, `cantidad`, `precio_costo`, `precio_venta`, `created_at`, `updated_at`, `estado`) VALUES
(34, 'PROD-009', 'Choclate con Leche', 6, NULL, 3, 9, '2025-12-26', 902, 25.00, 32.50, '2025-12-04 15:37:56', '2025-12-04 18:05:39', 'active'),
(38, 'PROD-003', 'Mantequilla con Sal', 1, NULL, 1, 4, '2026-01-03', 96, 0.20, 0.26, '2025-12-04 15:39:27', '2025-12-04 18:03:10', 'active'),
(39, 'PROD-005', 'Yogurt Natural', 3, 3, 1, 3, '2026-01-03', 44, 2.00, 2.60, '2025-12-04 15:39:27', '2025-12-05 02:10:53', 'active');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_proveedor`
--

CREATE TABLE `productos_proveedor` (
  `id_producto_proveedor` int(11) NOT NULL,
  `codigo_producto` varchar(50) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `id_subcategoria` int(11) DEFAULT NULL,
  `id_proveedor` int(11) NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `unidad_medida` varchar(20) DEFAULT 'unidad',
  `fecha_compra` date DEFAULT NULL,
  `es_perecedero` tinyint(1) DEFAULT 0,
  `registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos_proveedor`
--

INSERT INTO `productos_proveedor` (`id_producto_proveedor`, `codigo_producto`, `nombre`, `id_categoria`, `id_subcategoria`, `id_proveedor`, `precio_compra`, `unidad_medida`, `fecha_compra`, `es_perecedero`, `registro`, `actualizacion`) VALUES
(1, 'PROD-002', 'Leche Entera Pasteurizada', 1, 1, 1, 2.00, 'litro', '2025-12-04', 1, '2025-10-29 21:55:16', '2025-12-04 15:37:56'),
(2, 'PROD-004', 'Queso Blanco Fresco', 1, 2, 1, 2.50, 'kilo', NULL, 1, '2025-10-29 21:55:16', '2025-12-05 03:02:37'),
(3, 'PROD-005', 'Yogurt Natural', 3, 3, 1, 2.00, 'litro', '2025-12-04', 1, '2025-10-29 21:55:16', '2025-12-04 15:39:27'),
(4, 'PROD-003', 'Mantequilla con Sal', 1, NULL, 1, 0.20, 'unidad', '2025-12-04', 1, '2025-10-29 21:55:16', '2025-12-04 15:39:27'),
(5, 'PROD-001', 'Crema de Leche', 1, 1, 1, 0.95, 'litro', '2025-12-03', 1, '2025-10-29 21:55:16', '2025-12-03 22:45:00'),
(6, 'PROD-006', 'natilla', 1, NULL, 1, 1.50, 'paquete', NULL, 1, '2025-11-03 14:55:54', '2025-11-12 14:53:00'),
(7, 'PROD-007', 'queso manchego', 1, 2, 1, 5.00, 'kilo', NULL, 1, '2025-11-03 15:02:03', '2025-12-05 03:02:47'),
(8, 'PROD-008', 'Queso', 1, 2, 2, 0.00, 'kilo', '2025-12-04', 1, '2025-11-12 13:42:00', '2025-12-04 01:39:08'),
(9, 'PROD-009', 'Choclate con Leche', 6, NULL, 3, 25.00, 'unidad', '2025-12-04', 1, '2025-11-12 20:27:03', '2025-12-04 18:05:39'),
(10, 'PROD-010', 'Samba', 6, NULL, 3, 2.50, 'unidad', '2025-12-04', 1, '2025-11-12 21:36:37', '2025-12-04 15:37:56'),
(11, 'PROD-011', 'Cocosette', 6, NULL, 3, 5.00, 'unidad', '2025-12-04', 1, '2025-11-12 21:37:50', '2025-12-04 15:37:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL,
  `nombres` varchar(255) NOT NULL,
  `nombre_comercial` varchar(255) NOT NULL,
  `rif` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id_proveedor`, `nombres`, `nombre_comercial`, `rif`, `telefono`, `email`, `direccion`, `estado`, `registro`, `actualizacion`) VALUES
(1, 'jose andres pernalete', 'lacteos vaquita.C.A', 'J-307970578', '04122201285', 'jose00pg2@gmail.com', 'avenida españa entre calle 6 y 7', 'activo', '2022-10-19 19:39:05', '2025-11-03 03:37:45'),
(2, 'Juan Perez', 'Lacteos Los Andes', 'J-123456789', '1234-5678', 'contacto@economia.com', 'Av. Principal #123', 'activo', '2025-10-30 00:16:41', '2025-11-03 03:37:54'),
(3, 'juan jose rodriguez rivero', 'savoy', 'J-307970566', '04122201285', 'pernaletegimenezjose@gmail.com', 'avenida españa entre calle 6 y 7', 'activo', '2025-11-03 03:11:38', '2025-11-03 03:37:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(1, 'administrador'),
(3, 'asistente'),
(2, 'superusuario');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subcategorias`
--

CREATE TABLE `subcategorias` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `nombre_subcategoria` varchar(50) NOT NULL,
  `estado` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `subcategorias`
--

INSERT INTO `subcategorias` (`id`, `categoria_id`, `nombre_subcategoria`, `estado`, `created_at`) VALUES
(1, 1, 'Leche', 'active', '2025-11-12 15:01:55'),
(2, 1, 'Quesos', 'active', '2025-11-12 15:01:55'),
(3, 1, 'Yogurt', 'active', '2025-11-12 15:01:55'),
(10, 18, 'Gaseosa', 'active', '2025-12-05 03:01:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `usuario`, `nombre`, `apellido`, `contraseña`, `id_rol`, `activo`) VALUES
(4, 'isis01', 'Isis Sofia', 'Cedeño Bastidas', '1234', 1, 1),
(5, 'jose02', 'Jose', 'Pernalete', '1234', 1, 1),
(8, 'arturito01', 'Arturito', 'Riverito', '1234', 2, 1),
(9, 'daviana01', 'Daviana', 'Amaro', '1234', 3, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL,
  `cliente` varchar(100) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `total_bs` decimal(12,2) DEFAULT 0.00,
  `id_cliente` int(11) DEFAULT NULL,
  `total_usd` decimal(12,2) DEFAULT 0.00,
  `total_eur` decimal(12,2) DEFAULT NULL,
  `tasa_usd` decimal(12,4) DEFAULT 0.0000,
  `tasa_eur` decimal(12,4) DEFAULT 0.0000,
  `nro_factura` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id_venta`, `cliente`, `fecha`, `metodo_pago`, `total_bs`, `id_cliente`, `total_usd`, `total_eur`, `tasa_usd`, `tasa_eur`, `nro_factura`) VALUES
(9, 'Isis Sofia', '2025-12-05', 'Efectivo', 1349.12, 1, 5.36, NULL, 251.8900, 0.0000, 'FAC-004538');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_resumen_compras`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_resumen_compras` (
`id_historial` int(11)
,`id_compra` int(11)
,`id_producto_proveedor` int(11)
,`cantidad_empaques` int(11)
,`unidades_empaque` int(11)
,`total_unidades` int(11)
,`precio_total` decimal(10,2)
,`fecha_compra` date
,`fecha_vencimiento` date
,`usuario_id` int(11)
,`fecha_registro` timestamp
,`producto_nombre` varchar(255)
,`codigo_producto` varchar(50)
,`proveedor_nombre` varchar(255)
,`usuario_nombre` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_resumen_compras`
--
DROP TABLE IF EXISTS `vista_resumen_compras`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_resumen_compras`  AS SELECT `hc`.`id_historial` AS `id_historial`, `hc`.`id_compra` AS `id_compra`, `hc`.`id_producto_proveedor` AS `id_producto_proveedor`, `hc`.`cantidad_empaques` AS `cantidad_empaques`, `hc`.`unidades_empaque` AS `unidades_empaque`, `hc`.`total_unidades` AS `total_unidades`, `hc`.`precio_total` AS `precio_total`, `hc`.`fecha_compra` AS `fecha_compra`, `hc`.`fecha_vencimiento` AS `fecha_vencimiento`, `hc`.`usuario_id` AS `usuario_id`, `hc`.`fecha_registro` AS `fecha_registro`, `pp`.`nombre` AS `producto_nombre`, `pp`.`codigo_producto` AS `codigo_producto`, `p`.`nombre_comercial` AS `proveedor_nombre`, `u`.`nombre` AS `usuario_nombre` FROM (((`historial_compras` `hc` join `productos_proveedor` `pp` on(`hc`.`id_producto_proveedor` = `pp`.`id_producto_proveedor`)) join `proveedores` `p` on(`pp`.`id_proveedor` = `p`.`id_proveedor`)) join `usuario` `u` on(`hc`.`usuario_id` = `u`.`id_usuario`)) ORDER BY `hc`.`fecha_registro` DESC ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categoria_prod`
--
ALTER TABLE `categoria_prod`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre_categoria`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- Indices de la tabla `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `id_producto_proveedor` (`id_producto_proveedor`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_compras_fecha` (`fecha_compra`),
  ADD KEY `idx_compras_usuario` (`usuario_id`);

--
-- Indices de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `idx_detalle_venta` (`id_venta`);

--
-- Indices de la tabla `historial_compras`
--
ALTER TABLE `historial_compras`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_compra` (`id_compra`),
  ADD KEY `id_producto_proveedor` (`id_producto_proveedor`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id_modulo`),
  ADD UNIQUE KEY `nombre_modulo` (`nombre_modulo`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD UNIQUE KEY `unique_rol_modulo` (`id_rol`,`id_modulo`),
  ADD UNIQUE KEY `unique_usuario_modulo` (`id_usuario`,`id_modulo`),
  ADD KEY `id_modulo` (`id_modulo`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `subcategoria_id` (`subcategoria_id`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `id_producto_proveedor` (`id_producto_proveedor`),
  ADD KEY `idx_productos_codigo` (`codigo`);

--
-- Indices de la tabla `productos_proveedor`
--
ALTER TABLE `productos_proveedor`
  ADD PRIMARY KEY (`id_producto_proveedor`),
  ADD UNIQUE KEY `codigo_proveedor_unique` (`codigo_producto`,`id_proveedor`),
  ADD KEY `id_categoria` (`id_categoria`),
  ADD KEY `id_proveedor` (`id_proveedor`),
  ADD KEY `idx_pp_codigo` (`codigo_producto`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id_proveedor`),
  ADD UNIQUE KEY `rif` (`rif`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `subcategorias`
--
ALTER TABLE `subcategorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categoria_id` (`categoria_id`,`nombre_subcategoria`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `id_rol` (`id_rol`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`),
  ADD KEY `fk_venta_cliente` (`id_cliente`),
  ADD KEY `idx_ventas_fecha` (`fecha`),
  ADD KEY `idx_ventas_nro_factura` (`nro_factura`),
  ADD KEY `idx_ventas_cliente` (`id_cliente`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categoria_prod`
--
ALTER TABLE `categoria_prod`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  MODIFY `id_compra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `historial_compras`
--
ALTER TABLE `historial_compras`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id_modulo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT de la tabla `productos_proveedor`
--
ALTER TABLE `productos_proveedor`
  MODIFY `id_producto_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `subcategorias`
--
ALTER TABLE `subcategorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  ADD CONSTRAINT `compras_proveedores_ibfk_1` FOREIGN KEY (`id_producto_proveedor`) REFERENCES `productos_proveedor` (`id_producto_proveedor`),
  ADD CONSTRAINT `compras_proveedores_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD CONSTRAINT `detalle_venta_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`),
  ADD CONSTRAINT `detalle_venta_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `historial_compras`
--
ALTER TABLE `historial_compras`
  ADD CONSTRAINT `historial_compras_ibfk_1` FOREIGN KEY (`id_compra`) REFERENCES `compras_proveedores` (`id_compra`),
  ADD CONSTRAINT `historial_compras_ibfk_2` FOREIGN KEY (`id_producto_proveedor`) REFERENCES `productos_proveedor` (`id_producto_proveedor`),
  ADD CONSTRAINT `historial_compras_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD CONSTRAINT `permisos_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`),
  ADD CONSTRAINT `permisos_ibfk_2` FOREIGN KEY (`id_modulo`) REFERENCES `modulos` (`id_modulo`);

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_productos_producto_proveedor` FOREIGN KEY (`id_producto_proveedor`) REFERENCES `productos_proveedor` (`id_producto_proveedor`),
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`),
  ADD CONSTRAINT `productos_ibfk_4` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id_proveedor`);

--
-- Filtros para la tabla `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `usuario` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `subcategorias`
--
ALTER TABLE `subcategorias`
  ADD CONSTRAINT `subcategorias_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categoria_prod` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
