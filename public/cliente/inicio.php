<?php
/** Inicio del cliente: sus autos y el estado de cada reparación. */
require __DIR__ . '/../../src/app.php';
$usuario = requiereRol('cliente');

$st = db()->prepare(
    'SELECT o.*, v.patente, v.marca, v.modelo FROM ordenes o
     JOIN vehiculos v ON v.id = o.vehiculo_id
     WHERE v.cliente_id = ? ORDER BY o.fecha_ingreso DESC'
);
$st->execute([$usuario['id']]);
$ordenes = $st->fetchAll();
$activas = array_filter($ordenes, fn($o) => $o['estado'] !== 'entregado');
$anteriores = array_filter($ordenes, fn($o) => $o['estado'] === 'entregado');

encabezado('Mis autos', 'inicio');
?>
<p class="chico">Te mandamos un email cuando el presupuesto esté listo y cuando puedas retirar el auto.</p>

<div class="tarjeta">
    <h2>En el taller</h2>
    <?php if (!$activas): ?>
        <p class="chico">No tenés autos en el taller ahora.</p>
    <?php endif; ?>
    <table>
        <?php foreach ($activas as $o): ?>
            <tr>
                <td><strong><?= e($o['patente']) ?></strong><br><span class="chico"><?= e($o['marca'] . ' ' . $o['modelo']) ?></span></td>
                <td><?= e($o['problema']) ?></td>
                <td><?= etiquetaEstado($o['estado']) ?></td>
                <td>
                    <a class="boton boton-chico <?= $o['estado'] === 'presupuestado' ? '' : 'boton-secundario' ?>" href="<?= url('/cliente/orden.php?id=' . $o['id']) ?>">
                        <?= $o['estado'] === 'presupuestado' ? 'Ver presupuesto' : 'Ver detalle' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php if ($anteriores): ?>
<div class="tarjeta">
    <h2>Reparaciones anteriores</h2>
    <table>
        <tr><th>Vehículo</th><th>Trabajo</th><th>Entregado</th><th></th></tr>
        <?php foreach ($anteriores as $o): ?>
            <tr>
                <td><?= e($o['patente']) ?></td>
                <td><?= e($o['problema']) ?></td>
                <td><?= fecha($o['fecha_entrega']) ?></td>
                <td><a href="<?= url('/cliente/orden.php?id=' . $o['id']) ?>">Ver</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>
<?php pie(); ?>
