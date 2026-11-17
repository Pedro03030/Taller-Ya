<?php
// Reglas de negocio de los estados (Menú B)

function test_el_taller_puede_seguir_el_flujo_normal()
{
    comprobar(Estados::puedeCambiar('ingresado', 'en_diagnostico', 'taller'));
    comprobar(Estados::puedeCambiar('en_reparacion', 'listo', 'taller'));
    comprobar(Estados::puedeCambiar('listo', 'entregado', 'taller'));
}

function test_no_se_puede_saltear_estados()
{
    comprobar(!Estados::puedeCambiar('ingresado', 'listo', 'taller'), 'No debería pasar de ingresado a listo');
    comprobar(!Estados::puedeCambiar('presupuestado', 'en_reparacion', 'taller'), 'No se repara sin aprobación');
    comprobar(!Estados::puedeCambiar('entregado', 'ingresado', 'taller'), 'Una orden entregada está cerrada');
}

function test_el_cliente_solo_puede_aprobar_o_rechazar()
{
    comprobar(Estados::puedeCambiar('presupuestado', 'aprobado', 'cliente'));
    comprobar(Estados::puedeCambiar('presupuestado', 'rechazado', 'cliente'));
    comprobar(!Estados::puedeCambiar('en_reparacion', 'listo', 'cliente'), 'El cliente no puede marcar su auto como listo');
}

function test_no_se_presupuesta_sin_items()
{
    comprobar(!Estados::puedeCambiar('en_diagnostico', 'presupuestado', 'taller', 0));
    comprobar(Estados::puedeCambiar('en_diagnostico', 'presupuestado', 'taller', 2));
}

function test_alerta_de_orden_demorada()
{
    comprobar(Estados::estaDemorada('en_reparacion', '2026-11-05', '2026-11-10'), 'Pasó la fecha y sigue en el taller');
    comprobar(!Estados::estaDemorada('en_reparacion', '2026-11-15', '2026-11-10'), 'Todavía está en fecha');
    comprobar(!Estados::estaDemorada('listo', '2026-11-05', '2026-11-10'), 'Si está listo no es demora del taller');
    comprobar(!Estados::estaDemorada('ingresado', null, '2026-11-10'), 'Sin fecha estimada no hay alerta');
}

function test_se_avisa_al_cliente_solo_en_presupuesto_y_listo()
{
    comprobar(Estados::hayQueAvisar('presupuestado'));
    comprobar(Estados::hayQueAvisar('listo'));
    comprobar(!Estados::hayQueAvisar('en_diagnostico'));
    $texto = Estados::mensajeParaCliente('presupuestado', 'AB123CD', 75000, 'K7P2QX');
    comprobar(str_contains($texto, '$75.000'), 'El email tiene que decir cuánto sale');
    comprobar(str_contains($texto, 'K7P2QX'), 'El email tiene que incluir el código de seguimiento');
}
