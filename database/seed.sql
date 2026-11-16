-- Datos de prueba. Importar desde phpMyAdmin DESPUÉS de schema.sql.
-- Usuarios:  taller@tallerya.com / taller123   |   juan@mail.com / cliente123   |   ana@mail.com / cliente123
-- Para probar los avisos, cambiá el email de Juan por uno tuyo.

INSERT INTO usuarios (id, nombre, email, password_hash, rol, telefono, creado_en) VALUES
(1, 'Taller Mecánico (dueño)', 'taller@tallerya.com', '$2y$10$4ahun4NMW.gwzuf6D00.AehQr5j7Rjl2q.0gKcYeM.LWW17eYXVEi', 'taller', '2246 400000', '2026-11-01 09:00:00'),
(2, 'Juan Pérez', 'juan@mail.com', '$2y$10$kyqe05DaQIFopQuSFI4c6eWJbMXeU4aUpwAIEG40oUnwoJulOHKga', 'cliente', '2246 411111', '2026-11-01 09:00:00'),
(3, 'Ana Gómez', 'ana@mail.com', '$2y$10$kyqe05DaQIFopQuSFI4c6eWJbMXeU4aUpwAIEG40oUnwoJulOHKga', 'cliente', '2246 422222', '2026-11-01 09:00:00');

INSERT INTO vehiculos (id, cliente_id, patente, marca, modelo, anio) VALUES
(1, 2, 'AB123CD', 'Volkswagen', 'Gol Trend', 2015),
(2, 3, 'AC456EF', 'Fiat', 'Cronos', 2020),
(3, 2, 'KJH452', 'Renault', 'Kangoo', 2011);

INSERT INTO ordenes (id, vehiculo_id, problema, diagnostico, estado, codigo_seguimiento, fecha_ingreso, fecha_estimada) VALUES
(1, 1, 'Hace ruido al frenar', 'Pastillas gastadas, discos OK', 'presupuestado', 'K7P2QX', '2026-11-08 10:00:00', '2026-11-12'),
(2, 2, 'Service de los 60.000 km', NULL, 'en_reparacion', 'M3ZR8T', '2026-11-05 08:30:00', '2026-11-07'),
(3, 3, 'No arranca en frío', NULL, 'ingresado', 'W9HD4N', '2026-11-10 09:15:00', NULL);

INSERT INTO orden_items (orden_id, tipo, descripcion, cantidad, precio_unitario) VALUES
(1, 'repuesto', 'Juego de pastillas de freno delanteras', 1, 45000),
(1, 'mano_obra', 'Cambio de pastillas', 1, 30000),
(2, 'repuesto', 'Aceite 5W30 (litros)', 4, 12000),
(2, 'repuesto', 'Filtro de aceite', 1, 9500),
(2, 'mano_obra', 'Service completo', 1, 40000);

INSERT INTO historial_estados (orden_id, estado_anterior, estado_nuevo, usuario_id, comentario, fecha) VALUES
(1, NULL, 'ingresado', 1, NULL, '2026-11-08 10:00:00'),
(1, 'ingresado', 'en_diagnostico', 1, NULL, '2026-11-08 11:00:00'),
(1, 'en_diagnostico', 'presupuestado', 1, NULL, '2026-11-08 16:00:00'),
(2, NULL, 'ingresado', 1, NULL, '2026-11-05 08:30:00'),
(2, 'ingresado', 'en_diagnostico', 1, NULL, '2026-11-05 09:00:00'),
(2, 'en_diagnostico', 'presupuestado', 1, NULL, '2026-11-05 10:00:00'),
(2, 'presupuestado', 'aprobado', 3, 'Aprobado por el cliente', '2026-11-05 12:00:00'),
(2, 'aprobado', 'en_reparacion', 1, NULL, '2026-11-06 08:00:00'),
(3, NULL, 'ingresado', 1, NULL, '2026-11-10 09:15:00');
