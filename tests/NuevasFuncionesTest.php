<?php
// Funciones nuevas: modificar presupuesto, monto cobrado, dinero, empleados y órdenes del cliente.
// Usa baseDePrueba() de OrdenesTest.php

/** Lleva una orden nueva hasta el estado pedido, con un presupuesto de $75.000. */
function ordenEn(Ordenes $s, PDO $db, string $estadoFinal, string $decision = 'aprobado'): int
{
    $id = $s->crear(1, 'Ruido al frenar', null, 1);
    $s->cambiarEstado($id, 'en_diagnostico', 1, 'empleado');
    $db->exec("INSERT INTO orden_items (orden_id, tipo, descripcion, cantidad, precio_unitario, usuario_id) VALUES
        ($id, 'repuesto', 'Pastillas', 1, 45000, 1), ($id, 'mano_obra', 'Cambio de pastillas', 1, 30000, 1)");
    $camino = $decision === 'rechazado'
        ? [['presupuestado', 1, 'empleado'], ['rechazado', 2, 'cliente'], ['listo', 1, 'empleado'], ['entregado', 1, 'empleado']]
        : [['presupuestado', 1, 'empleado'], ['aprobado', 2, 'cliente'], ['en_reparacion', 1, 'empleado'], ['listo', 1, 'empleado'], ['entregado', 1, 'empleado']];
    foreach ($camino as [$estado, $quien, $rol]) {
        if ($s->buscar($id)['estado'] === $estadoFinal) break;
        $s->cambiarEstado($id, $estado, $quien, $rol);
    }
    return $id;
}

function itemsComoFormulario(Ordenes $s, int $id): array
{
    return array_map(fn($i) => ['id' => $i['id'], 'tipo' => $i['tipo'], 'descripcion' => $i['descripcion'],
        'cantidad' => $i['cantidad'], 'precio_unitario' => $i['precio_unitario']], $s->items($id));
}

function test_cambiar_un_presupuesto_aprobado_vuelve_a_pedir_aprobacion_y_avisa()
{
    $db = baseDePrueba();
    $enviados = [];
    $s = new Ordenes($db, new Email('t@gmail.com', 'clave', 'TallerYa', function ($h, $p, $u, $pw, $para, $msj) use (&$enviados) {
        $enviados[] = $para;
        return null;
    }));
    $id = ordenEn($s, $db, 'en_reparacion');
    $items = itemsComoFormulario($s, $id);
    $items[0]['precio_unitario'] = 50000;   // subió el repuesto
    $items[] = ['id' => null, 'tipo' => 'repuesto', 'descripcion' => 'Líquido de frenos', 'cantidad' => 1, 'precio_unitario' => 8000];

    $r = $s->modificarPresupuesto($id, $items, 4, 'Aumentó el repuesto');
    igual(true, $r['ok'], $r['error'] ?? '');
    igual('presupuestado', $s->buscar($id)['estado'], 'El cliente lo tiene que aprobar de nuevo');
    igual(88000.0, Presupuesto::total($s->items($id)));
    $cambio = $s->cambiosPresupuesto($id)[0];
    igual(4, (int)$cambio['usuario_id'], 'Queda registrado el empleado que lo cambió');
    igual('en_reparacion', $cambio['estado_anterior']);
    igual(75000.0, (float)$cambio['total_anterior']);
    comprobar(str_contains($cambio['detalle'], 'Agregó: Líquido de frenos'));
    $cargadoPor = array_column($s->items($id), 'usuario_id', 'descripcion');
    igual(4, (int)$cargadoPor['Líquido de frenos'], 'El ítem nuevo queda a nombre de quien lo cargó');
    igual(1, (int)$cargadoPor['Cambio de pastillas'], 'Un ítem que no se tocó mantiene quién lo cargó');
    igual('j@j.com', end($enviados), 'Se le avisa al cliente');
    $accion = $db->query("SELECT * FROM historial_empleados WHERE accion = 'Modificó el presupuesto'")->fetch();
    igual(4, (int)$accion['usuario_id'], 'Queda en el historial de empleados');
}

function test_no_se_puede_cambiar_el_presupuesto_de_un_auto_listo_ni_dejarlo_vacio()
{
    $db = baseDePrueba();
    $s = new Ordenes($db, new Email('', ''));
    $id = ordenEn($s, $db, 'listo');
    $r = $s->modificarPresupuesto($id, itemsComoFormulario($s, $id), 4, 'Cambio');
    igual(false, $r['ok'], 'Listo para retirar ya no se cambia');

    $id2 = ordenEn($s, $db, 'aprobado');
    igual(false, $s->modificarPresupuesto($id2, [], 4, 'Sin ítems')['ok'], 'No puede quedar vacío');
    igual(false, $s->modificarPresupuesto($id2, itemsComoFormulario($s, $id2), 4, '')['ok'], 'Hay que poner el motivo');
    igual(false, $s->modificarPresupuesto($id2, itemsComoFormulario($s, $id2), 4, 'Nada')['ok'], 'Sin cambios no se registra nada');
    igual('aprobado', $s->buscar($id2)['estado']);
}

function test_al_entregar_se_guarda_lo_cobrado_y_si_rechazo_es_cero()
{
    $db = baseDePrueba();
    $s = new Ordenes($db, new Email('', ''));
    $aprobada = ordenEn($s, $db, 'entregado');
    $rechazada = ordenEn($s, $db, 'entregado', 'rechazado');
    igual(75000.0, (float)$s->buscar($aprobada)['monto_cobrado']);
    igual(0.0, (float)$s->buscar($rechazada)['monto_cobrado'], 'Se devolvió sin reparar: no se cobra');
}

function test_dinero_separa_por_dia_mes_y_siempre()
{
    $db = baseDePrueba();
    $s = new Ordenes($db, new Email('', ''));
    $hoy = ordenEn($s, $db, 'entregado');
    $viejo = ordenEn($s, $db, 'entregado');
    $db->exec("UPDATE ordenes SET fecha_entrega = '2025-03-10 11:00:00', monto_cobrado = 20000 WHERE id = $viejo");
    ordenEn($s, $db, 'en_reparacion');   // aprobado, todavía no se cobra

    $d = new Dinero($db);
    $dia = date('Y-m-d');
    igual(['cantidad' => 1, 'total' => 75000.0], $d->resumen($dia, $dia), 'Hoy');
    igual(['cantidad' => 1, 'total' => 75000.0], $d->resumen(date('Y-m-01'), date('Y-m-t')), 'Este mes');
    igual(['cantidad' => 2, 'total' => 95000.0], $d->resumen(), 'Siempre');
    igual(2, $d->contarMeses());
    igual('2025-03', $d->porMes()[1]['mes']);
    igual(['cantidad' => 1, 'total' => 75000.0], $d->pendienteDeCobro($s), 'Lo aprobado sin entregar es plata pendiente');
}

function test_solo_un_empleado_activo_con_su_contrasena_puede_identificarse()
{
    $db = baseDePrueba();
    $u = new Usuarios($db);
    igual([], $u->crearEmpleado('Pedro', 'pedro@taller.com', null, 'secreto1'));
    comprobar($u->verificarPersonal('pedro@taller.com', 'secreto1') !== null, 'Email y contraseña correctos');
    igual(null, $u->verificarPersonal('pedro@taller.com', 'otra'), 'Contraseña incorrecta');
    $db->exec("UPDATE usuarios SET password_hash = '" . password_hash('cliente1', PASSWORD_DEFAULT) . "' WHERE id = 2");
    igual(null, $u->verificarPersonal('j@j.com', 'cliente1'), 'Un cliente no puede cambiar presupuestos');
    $pedro = $u->verificarPersonal('pedro@taller.com', 'secreto1');
    $u->cambiarActivo((int)$pedro['id'], false);
    igual(null, $u->verificarPersonal('pedro@taller.com', 'secreto1'), 'Un empleado dado de baja no puede');
    comprobar(count($u->crearEmpleado('Otro', 'pedro@taller.com', null, 'secreto1')) > 0, 'No se repiten emails');
}

function test_se_puede_crear_otra_orden_aunque_el_auto_tenga_una_abierta()
{
    $db = baseDePrueba();
    $s = new Ordenes($db, new Email('', ''));
    $primera = $s->crear(1, 'Service', null, 1);
    $segunda = $s->crear(1, 'Cambio de cubiertas', null, 2, false);   // la pide el cliente
    comprobar($segunda > $primera);
    igual(2, count($s->abiertasDelVehiculo(1)));
    igual('Pedida por el cliente desde la web', $s->historial($segunda)[0]['comentario']);
    igual(1, (int)$db->query("SELECT COUNT(*) FROM historial_empleados")->fetchColumn(), 'Solo lo del empleado va al historial de empleados');
}
