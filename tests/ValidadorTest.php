<?php
// Validación de los datos que ingresa el usuario

function test_patentes_validas_e_invalidas()
{
    igual(null, Validador::patente('AB123CD'), 'Patente Mercosur');
    igual(null, Validador::patente('abc 123'), 'Patente vieja con espacio y minúsculas');
    comprobar(Validador::patente('AB12') !== null, 'Patente corta');
    comprobar(Validador::patente("AB123CD'; DROP TABLE") !== null, 'Texto raro');
    igual('AB123CD', Validador::normalizarPatente(' ab-123-cd '));
}

function test_campos_obligatorios_numeros_y_fechas()
{
    comprobar(Validador::obligatorio('   ', 'Problema') !== null);
    comprobar(Validador::numeroPositivo('-5', 'Precio') !== null);
    comprobar(Validador::numeroPositivo('abc', 'Precio') !== null);
    igual(null, Validador::numeroPositivo('1500.50', 'Precio'));
    comprobar(Validador::fechaFutura('2026-11-01', '2026-11-10') !== null, 'Fecha pasada');
    comprobar(Validador::fechaFutura('2026-02-30', '2026-01-01') !== null, 'Fecha que no existe');
    igual(null, Validador::fechaFutura('', '2026-11-10'), 'La fecha es opcional');
}
