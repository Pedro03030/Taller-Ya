<?php
/** Listado de clientes con sus vehículos. */
require __DIR__ . '/../../src/app.php';
requiereRol('taller');

$clientes = db()->query(
    "SELECT u.*, (SELECT COUNT(*) FROM vehiculos v WHERE v.cliente_id = u.id) AS cant_vehiculos
     FROM usuarios u WHERE u.rol = 'cliente' ORDER BY u.nombre"
)->fetchAll();
$vehiculos = db()->query('SELECT * FROM vehiculos ORDER BY patente')->fetchAll();
$porCliente = [];
foreach ($vehiculos as $v) {
    $porCliente[$v['cliente_id']][] = $v;
}

encabezado('Clientes', 'clientes');
?>
<div class="tarjeta">
    <p class="chico">Los clientes nuevos se cargan al crear una orden (<a href="<?= url('/taller/orden_nueva.php') ?>">Nueva orden</a>).</p>
    <table>
        <tr><th>Nombre</th><th>Contacto</th><th>Vehículos</th></tr>
        <?php foreach ($clientes as $c): ?>
            <tr>
                <td><?= e($c['nombre']) ?></td>
                <td><?= e($c['email']) ?><br><span class="chico"><?= e($c['telefono']) ?></span></td>
                <td>
                    <?php foreach ($porCliente[$c['id']] ?? [] as $v): ?>
                        <strong><?= e($v['patente']) ?></strong> <span class="chico"><?= e($v['marca'] . ' ' . $v['modelo']) ?></span><br>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php pie(); ?>
