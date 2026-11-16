-- TallerYa - Esquema de base de datos (MySQL / MariaDB)
-- Importar desde phpMyAdmin (pestaña "Importar") con la base ya creada y seleccionada.
-- Si las tablas ya existen, las borra y las vuelve a crear (se pierden los datos).

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notificaciones, historial_estados, orden_items, ordenes, vehiculos, usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- Usuarios del sistema. rol: 'taller' (dueño/empleados) o 'cliente'
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('taller', 'cliente') NOT NULL,
    telefono VARCHAR(30) NULL,
    creado_en DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    patente VARCHAR(10) NOT NULL UNIQUE,
    marca VARCHAR(50) NOT NULL,
    modelo VARCHAR(50) NOT NULL,
    anio INT NULL,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orden de trabajo: cada vez que un auto entra al taller
CREATE TABLE ordenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT NOT NULL,
    problema TEXT NOT NULL,                -- lo que cuenta el cliente
    diagnostico TEXT NULL,                 -- lo que encuentra el mecánico
    estado VARCHAR(20) NOT NULL DEFAULT 'ingresado',
    codigo_seguimiento VARCHAR(6) NOT NULL, -- para consultar sin usuario
    fecha_ingreso DATETIME NOT NULL,
    fecha_estimada DATE NULL,              -- cuándo debería estar listo
    fecha_entrega DATETIME NULL,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Renglones del presupuesto (repuestos y mano de obra)
CREATE TABLE orden_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    tipo ENUM('repuesto', 'mano_obra') NOT NULL,
    descripcion VARCHAR(150) NOT NULL,
    cantidad DECIMAL(8,2) NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada cambio de estado queda registrado (quién, cuándo, de qué a qué)
CREATE TABLE historial_estados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    estado_anterior VARCHAR(20) NULL,
    estado_nuevo VARCHAR(20) NOT NULL,
    usuario_id INT NOT NULL,
    comentario VARCHAR(255) NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de avisos enviados por email (y si fallaron)
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    destinatario VARCHAR(150) NOT NULL,
    asunto VARCHAR(150) NOT NULL,
    mensaje TEXT NOT NULL,
    estado ENUM('enviada', 'fallida') NOT NULL,
    error VARCHAR(255) NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
