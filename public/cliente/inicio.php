<?php
/** Inicio del cliente: sus autos y el estado de cada reparación. */
require __DIR__ . '/../../src/app.php';
$usuario = requiereRol('cliente');

$st = db()->prepare(
    'SELECT o.*, v.patente, v.marca, v.modelo FROM ordenes o
     JOIN vehiculos v ON v.id = o.vehiculo_id
     WHERE v.cliente_id = ? ORDER BY o.fecha_ingreso DESC, o.id DESC'
);
$st->execute([$usuario['id']]);
$ordenes = $st->fetchAll();
$activas = array_filter($ordenes, fn($o) => $o['estado'] !== 'entregado');
$anteriores = array_filter($ordenes, fn($o) => $o['estado'] === 'entregado');

// Cada tabla tiene su propia paginación.
$pagActivas = new Paginador(count($activas), 10, 'pag_activas');
$pagAnteriores = new Paginador(count($anteriores), 10, 'pag_anteriores', null, 'anteriores');

encabezado('Mis autos', 'inicio');
?>
<p class="chico">Te mandamos un email cuando el presupuesto esté listo y cuando puedas retirar el auto.</p>

<div class="tarjeta">
    <div class="titulo-con-boton">
        <h2>En el taller</h2>
        <a class="boton boton-chico" href="<?= url('/cliente/orden_nueva.php') ?>">+ Pedir una orden nueva</a>
    </div>
    <?php if (!$activas): ?>
        <p class="chico">No tenés autos en el taller ahora.</p>
    <?php else: ?>
    <table>
        <tr><th>Vehículo</th><th>Problema</th><th>Estado</th><th></th></tr>
        <?php foreach ($pagActivas->recortar($activas) as $o): ?>
            <tr>
                <td><strong><?= e($o['patente']) ?></strong><br><span class="chico"><?= e($o['marca'] . ' ' . $o['modelo']) ?> · orden #<?= $o['id'] ?></span></td>
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
    <?= $pagActivas->links() ?>
    <?php endif; ?>
</div>

<?php if ($anteriores): ?>
<div class="tarjeta" id="anteriores">
    <h2>Reparaciones anteriores</h2>
    <table>
        <tr><th>Vehículo</th><th>Trabajo</th><th>Entregado</th><th></th></tr>
        <?php foreach ($pagAnteriores->recortar($anteriores) as $o): ?>
            <tr>
                <td><?= e($o['patente']) ?></td>
                <td><?= e($o['problema']) ?></td>
                <td><?= fecha($o['fecha_entrega']) ?></td>
                <td><a href="<?= url('/cliente/orden.php?id=' . $o['id']) ?>">Ver</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?= $pagAnteriores->links() ?>
</div>
<?php endif; ?>
<?php pie(); ?>
