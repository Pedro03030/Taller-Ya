-- TallerYa - Actividad de prueba para el empleado Carlos Mecánico (empleado@tallerya.com).
-- Importar desde phpMyAdmin DESPUÉS de migracion_v2.sql (o en una base nueva, después de schema, seed y datos_demo).
-- Se puede importar una sola vez. No borra nada.
--
-- Qué hace:
--   1. Crea el empleado si todavía no existe (contraseña: empleado123).
--   2. Pasa a nombre de Carlos la mitad de las órdenes de prueba (las de número par):
--      los cambios de estado del taller y los ítems del presupuesto que cargó.
--   3. Completa "Cargado por" en los ítems de prueba que no tenían a nadie (quedan a nombre del admin).
--   4. Llena el historial de empleados con lo que hizo cada uno (crear órdenes, cambiar estados,
--      cargar ítems) y un "Inició sesión" por cada día que trabajó.
-- Solo toca las órdenes de los datos de prueba (seed.sql y datos_demo.sql), identificadas por su código de seguimiento.

INSERT IGNORE INTO usuarios (nombre, email, password_hash, rol, telefono, activo, creado_en) VALUES
('Carlos Mecánico', 'empleado@tallerya.com', '$2y$10$/chCtHlJlovuQqlzb.taf.xpzmqKgVjL2buEQlllGLgAAysAVfy1K', 'empleado', '2246 400001', 1, '2026-10-01 09:00:00');

SET @emp   = (SELECT id FROM usuarios WHERE email = 'empleado@tallerya.com');
SET @admin = (SELECT id FROM usuarios WHERE email = 'taller@tallerya.com');

-- 2. Órdenes de prueba de número par: las trabajó Carlos
UPDATE historial_estados h
JOIN ordenes o ON o.id = h.orden_id
SET h.usuario_id = @emp
WHERE h.usuario_id = @admin
  AND MOD(o.id, 2) = 0
  AND o.codigo_seguimiento IN ('3G83DN','4ZWJD7','5DHQD3','6U2YB7','6YZFQG','77ZVRM','97EFT8','9FL63T','BEP2KS','BVFSZL','GXS8LB','H9DPUJ','HH9788','JU4KHV','LHXDGA','NZGEDP','PCF54E','QQA9MS','T4Y2QK','V9X6UE','WK3DEG','XK94CE','XQNR3Q','YBBT8S','K7P2QX','M3ZR8T','W9HD4N');

UPDATE orden_items i
JOIN ordenes o ON o.id = i.orden_id
SET i.usuario_id = @emp
WHERE (i.usuario_id IS NULL OR i.usuario_id = @admin)
  AND MOD(o.id, 2) = 0
  AND o.codigo_seguimiento IN ('3G83DN','4ZWJD7','5DHQD3','6U2YB7','6YZFQG','77ZVRM','97EFT8','9FL63T','BEP2KS','BVFSZL','GXS8LB','H9DPUJ','HH9788','JU4KHV','LHXDGA','NZGEDP','PCF54E','QQA9MS','T4Y2QK','V9X6UE','WK3DEG','XK94CE','XQNR3Q','YBBT8S','K7P2QX','M3ZR8T','W9HD4N');

-- 3. El resto de los ítems de prueba sin "Cargado por" quedan a nombre del admin
UPDATE orden_items i
JOIN ordenes o ON o.id = i.orden_id
SET i.usuario_id = @admin
WHERE i.usuario_id IS NULL
  AND o.codigo_seguimiento IN ('3G83DN','4ZWJD7','5DHQD3','6U2YB7','6YZFQG','77ZVRM','97EFT8','9FL63T','BEP2KS','BVFSZL','GXS8LB','H9DPUJ','HH9788','JU4KHV','LHXDGA','NZGEDP','PCF54E','QQA9MS','T4Y2QK','V9X6UE','WK3DEG','XK94CE','XQNR3Q','YBBT8S','K7P2QX','M3ZR8T','W9HD4N');

-- 4a. Historial de empleados: crear órdenes y cambiar estados (lo que hizo el personal, no el cliente)
INSERT INTO historial_empleados (usuario_id, orden_id, accion, detalle, fecha)
SELECT h.usuario_id, h.orden_id,
       CASE WHEN h.estado_anterior IS NULL THEN 'Creó una orden' ELSE 'Cambió el estado' END,
       CASE WHEN h.estado_anterior IS NULL THEN LEFT(o.problema, 200)
            ELSE CONCAT(CASE h.estado_anterior WHEN 'ingresado' THEN 'Ingresado' WHEN 'en_diagnostico' THEN 'En diagnóstico' WHEN 'presupuestado' THEN 'Presupuesto listo' WHEN 'aprobado' THEN 'Presupuesto aprobado' WHEN 'rechazado' THEN 'Presupuesto rechazado' WHEN 'en_reparacion' THEN 'En reparación' WHEN 'listo' THEN 'Listo para retirar' WHEN 'entregado' THEN 'Entregado' ELSE h.estado_anterior END, ' → ', CASE h.estado_nuevo WHEN 'ingresado' THEN 'Ingresado' WHEN 'en_diagnostico' THEN 'En diagnóstico' WHEN 'presupuestado' THEN 'Presupuesto listo' WHEN 'aprobado' THEN 'Presupuesto aprobado' WHEN 'rechazado' THEN 'Presupuesto rechazado' WHEN 'en_reparacion' THEN 'En reparación' WHEN 'listo' THEN 'Listo para retirar' WHEN 'entregado' THEN 'Entregado' ELSE h.estado_nuevo END,
                        IF(h.comentario IS NULL, '', CONCAT(' (', h.comentario, ')'))) END,
       h.fecha
FROM historial_estados h
JOIN ordenes o ON o.id = h.orden_id
JOIN usuarios u ON u.id = h.usuario_id
WHERE u.rol IN ('admin', 'empleado')
  AND o.codigo_seguimiento IN ('3G83DN','4ZWJD7','5DHQD3','6U2YB7','6YZFQG','77ZVRM','97EFT8','9FL63T','BEP2KS','BVFSZL','GXS8LB','H9DPUJ','HH9788','JU4KHV','LHXDGA','NZGEDP','PCF54E','QQA9MS','T4Y2QK','V9X6UE','WK3DEG','XK94CE','XQNR3Q','YBBT8S','K7P2QX','M3ZR8T','W9HD4N')
  AND NOT EXISTS (SELECT 1 FROM historial_empleados e
                  WHERE e.orden_id = h.orden_id AND e.fecha = h.fecha
                    AND e.accion IN ('Creó una orden', 'Cambió el estado'));

-- 4b. Ítems del presupuesto: se cargaron media hora antes de mandar el presupuesto
INSERT INTO historial_empleados (usuario_id, orden_id, accion, detalle, fecha)
SELECT i.usuario_id, i.orden_id, 'Agregó un ítem al presupuesto',
       CONCAT(i.descripcion, ' x', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM i.cantidad)), ' $', FORMAT(i.precio_unitario, 0, 'de_DE')),
       DATE_SUB(p.fecha, INTERVAL 30 MINUTE)
FROM orden_items i
JOIN ordenes o ON o.id = i.orden_id
JOIN (SELECT orden_id, MIN(fecha) AS fecha FROM historial_estados
      WHERE estado_nuevo = 'presupuestado' GROUP BY orden_id) p ON p.orden_id = i.orden_id
WHERE i.usuario_id IN (@emp, @admin)
  AND o.codigo_seguimiento IN ('3G83DN','4ZWJD7','5DHQD3','6U2YB7','6YZFQG','77ZVRM','97EFT8','9FL63T','BEP2KS','BVFSZL','GXS8LB','H9DPUJ','HH9788','JU4KHV','LHXDGA','NZGEDP','PCF54E','QQA9MS','T4Y2QK','V9X6UE','WK3DEG','XK94CE','XQNR3Q','YBBT8S','K7P2QX','M3ZR8T','W9HD4N')
  AND NOT EXISTS (SELECT 1 FROM historial_empleados e
                  WHERE e.orden_id = i.orden_id AND e.accion = 'Agregó un ítem al presupuesto'
                    AND e.detalle LIKE CONCAT(i.descripcion, ' x%'));

-- 4c. Un "Inició sesión" por cada día que trabajó cada uno, 10 minutos antes de su primera acción
INSERT INTO historial_empleados (usuario_id, orden_id, accion, detalle, fecha)
SELECT d.usuario_id, NULL, 'Inició sesión', NULL, DATE_SUB(d.primera, INTERVAL 10 MINUTE)
FROM (SELECT usuario_id, MIN(fecha) AS primera FROM historial_empleados
      WHERE usuario_id IN (@emp, @admin) AND accion <> 'Inició sesión'
      GROUP BY usuario_id, DATE(fecha)) d
WHERE NOT EXISTS (SELECT 1 FROM historial_empleados e
                  WHERE e.usuario_id = d.usuario_id AND e.accion = 'Inició sesión'
                    AND DATE(e.fecha) = DATE(d.primera));
