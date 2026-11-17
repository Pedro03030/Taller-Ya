<?php
/**
 * Bloque reutilizable: estado de una orden visto por el cliente.
 * Se usa en la consulta pública y en el detalle del cliente.
 * Espera las variables $orden e $items.
 */
$recorrido = $orden['estado'] === 'rechazado'
    ? ['ingresado', 'en_diagnostico', 'presupuestado', 'rechazado', 'listo', 'entregado']
    : ['ingresado', 'en_diagnostico', 'presupuestado', 'aprobado', 'en_reparacion', 'listo', 'entregado'];
$posicion = array_search($orden['estado'], $recorrido, true);
?>
<div class="tarjeta">
    <h2><?= e($orden['marca'] . ' ' . $orden['modelo']) ?> · <?= e($orden['patente']) ?></h2>
    <p>Estado actual: <?= etiquetaEstado($orden['estado']) ?>
        <?php if ($orden['fecha_estimada'] && Estados::estaAbierta($orden['estado'])): ?>
            · <span class="chico">Fecha estimada: <?= fecha($orden['fecha_estimada']) ?></span>
        <?php endif; ?>
    </p>
    <div class="pasos">
        <?php foreach ($recorrido as $i => $paso): ?>
            <div class="paso <?= $i <= $posicion ? 'hecho' : '' ?>"><?= e(Estados::nombre($paso)) ?></div>
        <?php endforeach; ?>
    </div>
    <p><strong>Problema informado:</strong> <?= e($orden['problema']) ?></p>
    <?php if ($orden['diagnostico']): ?>
        <p><strong>Diagnóstico del taller:</strong> <?= e($orden['diagnostico']) ?></p>
    <?php endif; ?>
</div>

<?php if ($items && !in_array($orden['estado'], ['ingresado', 'en_diagnostico'], true)): ?>
<div class="tarjeta">
    <h2>Presupuesto</h2>
    <table>
        <tr><th>Concepto</th><th>Tipo</th><th class="derecha">Cant.</th><th class="derecha">Subtotal</th></tr>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= e($it['descripcion']) ?></td>
                <td><?= $it['tipo'] === 'repuesto' ? 'Repuesto' : 'Mano de obra' ?></td>
                <td class="derecha"><?= e((float)$it['cantidad']) ?></td>
                <td class="derecha"><?= Presupuesto::formatear($it['cantidad'] * $it['precio_unitario']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p class="derecha total">Total: <?= Presupuesto::formatear(Presupuesto::total($items)) ?></p>
</div>
<?php endif; ?>
