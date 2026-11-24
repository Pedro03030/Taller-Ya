-- TallerYa - Migración a la versión 2 (para la base que YA está cargada en InfinityFree).
-- NO borra datos. Importar UNA sola vez desde phpMyAdmin (pestaña "Importar") con la base seleccionada.
-- Hacé antes una copia: pestaña "Exportar" -> "Continuar".
--
-- Qué agrega:
--   * Roles admin / empleado / cliente (la cuenta del taller pasa a ser admin) y baja de usuarios.
--   * Quién cargó cada ítem del presupuesto.
--   * Monto cobrado de cada orden entregada (apartado de Dinero).
--   * Tablas presupuesto_cambios e historial_empleados.
--   * Un empleado de prueba: empleado@tallerya.com / empleado123

-- 1. Roles: primero se agregan los nuevos valores, se pasa 'taller' a 'admin' y después se quita 'taller'.
ALTER TABLE usuarios MODIFY rol ENUM('taller', 'admin', 'empleado', 'cliente') NOT NULL;
UPDATE usuarios SET rol = 'admin' WHERE rol = 'taller';
ALTER TABLE usuarios MODIFY rol ENUM('admin', 'empleado', 'cliente') NOT NULL;
ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER telefono;

-- 2. Quién cargó cada ítem del presupuesto
ALTER TABLE orden_items ADD COLUMN usuario_id INT NULL;
ALTER TABLE orden_items ADD CONSTRAINT fk_items_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id);

-- 3. Monto cobrado al entregar
ALTER TABLE ordenes ADD COLUMN monto_cobrado DECIMAL(12,2) NULL;
UPDATE ordenes SET monto_cobrado = CASE
    WHEN (SELECT h.estado_nuevo FROM historial_estados h
          WHERE h.orden_id = ordenes.id AND h.estado_nuevo IN ('aprobado', 'rechazado')
          ORDER BY h.fecha DESC, h.id DESC LIMIT 1) = 'rechazado' THEN 0
    ELSE (SELECT COALESCE(SUM(i.cantidad * i.precio_unitario), 0) FROM orden_items i WHERE i.orden_id = ordenes.id)
END
WHERE estado = 'entregado' AND monto_cobrado IS NULL;

-- 4. Tablas nuevas
CREATE TABLE presupuesto_cambios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    usuario_id INT NOT NULL,
    estado_anterior VARCHAR(20) NOT NULL,
    total_anterior DECIMAL(12,2) NOT NULL,
    total_nuevo DECIMAL(12,2) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    detalle TEXT NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historial_empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    orden_id INT NULL,
    accion VARCHAR(50) NOT NULL,
    detalle VARCHAR(255) NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Empleado de prueba (si ya existe ese email, no hace nada)
INSERT IGNORE INTO usuarios (nombre, email, password_hash, rol, telefono, activo, creado_en) VALUES
('Carlos Mecánico', 'empleado@tallerya.com', '$2y$10$/chCtHlJlovuQqlzb.taf.xpzmqKgVjL2buEQlllGLgAAysAVfy1K', 'empleado', '2246 400001', 1, NOW());
