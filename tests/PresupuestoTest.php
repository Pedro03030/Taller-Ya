<?php
// Cálculo del presupuesto

function test_total_del_presupuesto()
{
    $items = [
        ['tipo' => 'repuesto', 'cantidad' => '4', 'precio_unitario' => '12000'],
        ['tipo' => 'repuesto', 'cantidad' => '1', 'precio_unitario' => '9500'],
        ['tipo' => 'mano_obra', 'cantidad' => '1', 'precio_unitario' => '40000'],
    ];
    igual(97500.0, Presupuesto::total($items));
    igual(57500.0, Presupuesto::totalPorTipo($items, 'repuesto'));
    igual(40000.0, Presupuesto::totalPorTipo($items, 'mano_obra'));
    igual(0.0, Presupuesto::total([]));
    igual('$97.500', Presupuesto::formatear(97500));
}
