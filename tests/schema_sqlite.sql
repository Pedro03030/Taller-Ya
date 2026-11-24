-- Copia del esquema adaptada a SQLite, solo para las pruebas automáticas.
-- (También sirve para probar el sistema en local sin MySQL.)

-- Usuarios del sistema.
-- rol: 'admin' (dueño: ve todo, incluido el dinero y el alta de empleados),
--      'empleado' (personal del taller) o 'cliente'
CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL,
    telefono VARCHAR(30) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,  -- 0 = dado de baja, no puede ingresar
    creado_en DATETIME NOT NULL
);

CREATE TABLE vehiculos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id INT NOT NULL,
    patente VARCHAR(10) NOT NULL UNIQUE,
    marca VARCHAR(50) NOT NULL,
    modelo VARCHAR(50) NOT NULL,
    anio INT NULL,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id)
);

-- Orden de trabajo: cada vez que un auto entra al taller.
-- Un mismo vehículo puede tener varias órdenes abiertas a la vez.
CREATE TABLE ordenes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehiculo_id INT NOT NULL,
    problema TEXT NOT NULL,                -- lo que cuenta el cliente
    diagnostico TEXT NULL,                 -- lo que encuentra el mecánico
    estado VARCHAR(20) NOT NULL DEFAULT 'ingresado',
    codigo_seguimiento VARCHAR(6) NOT NULL, -- para consultar sin usuario
    fecha_ingreso DATETIME NOT NULL,
    fecha_estimada DATE NULL,              -- cuándo debería estar listo
    fecha_entrega DATETIME NULL,
    monto_cobrado DECIMAL(12,2) NULL,      -- se guarda al entregar (0 si rechazó el presupuesto)
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
);

-- Renglones del presupuesto (repuestos y mano de obra)
CREATE TABLE orden_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    orden_id INT NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    descripcion VARCHAR(150) NOT NULL,
    cantidad DECIMAL(8,2) NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    usuario_id INT NULL,                   -- empleado que cargó o modificó el ítem
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Cada cambio de estado queda registrado (quién, cuándo, de qué a qué)
CREATE TABLE historial_estados (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    orden_id INT NOT NULL,
    estado_anterior VARCHAR(20) NULL,
    estado_nuevo VARCHAR(20) NOT NULL,
    usuario_id INT NOT NULL,
    comentario VARCHAR(255) NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Registro de avisos enviados por email (y si fallaron)
CREATE TABLE notificaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    orden_id INT NOT NULL,
    destinatario VARCHAR(150) NOT NULL,
    asunto VARCHAR(150) NOT NULL,
    mensaje TEXT NOT NULL,
    estado VARCHAR(20) NOT NULL,
    error VARCHAR(255) NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE
);

-- Cambios de un presupuesto que ya se le había mandado al cliente.
-- usuario_id es el empleado que se identificó con email y contraseña al hacer el cambio.
CREATE TABLE presupuesto_cambios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    orden_id INT NOT NULL,
    usuario_id INT NOT NULL,
    estado_anterior VARCHAR(20) NOT NULL,
    total_anterior DECIMAL(12,2) NOT NULL,
    total_nuevo DECIMAL(12,2) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    detalle TEXT NULL,                     -- qué renglones se agregaron, cambiaron o quitaron
    fecha DATETIME NOT NULL,
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Historial de empleados: todo lo que hace el personal del taller.
-- Solo lo ven el admin y los empleados.
CREATE TABLE historial_empleados (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INT NOT NULL,
    orden_id INT NULL,
    accion VARCHAR(50) NOT NULL,
    detalle VARCHAR(255) NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE SET NULL
);
