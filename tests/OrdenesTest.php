<?php
// Prueba de integración: cambio de estado + base de datos + aviso.
// Usa una base SQLite en memoria para no necesitar MySQL.

function baseDePrueba(): PDO
{
    $db = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec(file_get_contents(__DIR__ . '/schema_sqlite.sql'));
    $db->exec("INSERT INTO usuarios (id, nombre, email, password_hash, rol, creado_en) VALUES
        (1, 'Carlos', 't@t.com', 'x', 'empleado', '2026-11-01'),
        (2, 'Juan', 'j@j.com', 'x', 'cliente', '2026-11-01'),
        (3, 'Ana', 'a@a.com', 'x', 'cliente', '2026-11-01'),
        (4, 'Pedro', 'p@t.com', 'x', 'empleado', '2026-11-01')");
    $db->exec("INSERT INTO vehiculos (id, cliente_id, patente, marca, modelo) VALUES (1, 2, 'AB123CD', 'VW', 'Gol')");
    return $db;
}

function test_si_el_email_falla_el_estado_cambia_igual_y_queda_registrado()
{
    $db = baseDePrueba();
    $emailCaido = new Email('taller@gmail.com', 'clave', 'TallerYa', fn() => 'El servidor de email no respondió a tiempo.');
    $servicio = new Ordenes($db, $emailCaido);

    $id = $servicio->crear(1, 'Ruido al frenar', null, 1);
    $servicio->cambiarEstado($id, 'en_diagnostico', 1, 'empleado');
    $db->exec("INSERT INTO orden_items (orden_id, tipo, descripcion, cantidad, precio_unitario) VALUES ($id, 'repuesto', 'Pastillas', 1, 45000)");
    $r = $servicio->cambiarEstado($id, 'presupuestado', 1, 'empleado');

    igual(true, $r['ok'], 'El cambio de estado no depende del email');
    comprobar($r['aviso'] !== null, 'Hay que avisarle al taller que el mensaje no salió');
    igual('presupuestado', $servicio->buscar($id)['estado']);
    $aviso = $servicio->notificaciones($id)[0];
    igual('fallida', $aviso['estado']);
    igual('j@j.com', $aviso['destinatario']);
    igual(3, count($servicio->historial($id)), 'Ingresado, en diagnóstico y presupuestado');
}

function test_un_cliente_no_puede_aprobar_la_orden_de_otro()
{
    $db = baseDePrueba();
    $servicio = new Ordenes($db, new Email('', ''));
    $id = $servicio->crear(1, 'Service', null, 1);
    $db->exec("UPDATE ordenes SET estado = 'presupuestado' WHERE id = $id");

    $r = $servicio->cambiarEstado($id, 'aprobado', 3, 'cliente'); // Ana intenta aprobar el auto de Juan
    igual(false, $r['ok']);
    igual('presupuestado', $servicio->buscar($id)['estado']);

    $r = $servicio->cambiarEstado($id, 'aprobado', 2, 'cliente'); // Juan sí puede
    igual(true, $r['ok']);
}

function test_reintentar_un_email_que_fallo()
{
    $db = baseDePrueba();
    $anda = false; // el servicio de email empieza caído
    $email = new Email('taller@gmail.com', 'clave', 'TallerYa', function () use (&$anda) {
        return $anda ? null : 'El servidor de email no respondió a tiempo.';
    });
    $servicio = new Ordenes($db, $email);
    $id = $servicio->crear(1, 'Service', null, 1);
    $db->exec("UPDATE ordenes SET estado = 'en_reparacion' WHERE id = $id");
    $servicio->cambiarEstado($id, 'listo', 1, 'empleado');
    $aviso = $servicio->notificaciones($id)[0];
    igual('fallida', $aviso['estado']);

    $anda = true; // vuelve el servicio
    igual('enviada', $servicio->reintentar((int)$aviso['id']));
    igual(1, count($servicio->notificaciones($id)), 'Se actualiza el mismo aviso, no se duplica');
}
