<?php
/** Listado de clientes con sus vehículos, con búsqueda y paginación. */
require __DIR__ . '/../../src/app.php';
requierePersonal();

$buscar = trim($_GET['buscar'] ?? '');
$where = "WHERE u.rol = 'cliente'";
$params = [];
if ($buscar !== '') {
    // Busca por nombre, email o patente de alguno de sus autos.
    $where .= ' AND (u.nombre LIKE ? OR u.email LIKE ? OR EXISTS (SELECT 1 FROM vehiculos v2 WHERE v2.cliente_id = u.id AND v2.patente LIKE ?))';
    array_push($params, "%$buscar%", "%$buscar%", '%' . Validador::normalizarPatente($buscar) . '%');
}

$st = db()->prepare("SELECT COUNT(*) FROM usuarios u $where");
$st->execute($params);
$pag = new Paginador((int)$st->fetchColumn());

$st = db()->prepare("SELECT u.* FROM usuarios u $where ORDER BY u.nombre" . $pag->limiteSql());
$st->execute($params);
$clientes = $st->fetchAll();

// Vehículos solo de los clientes de esta página.
$porCliente = [];
if ($clientes) {
    $ids = array_column($clientes, 'id');
    $st = db()->prepare('SELECT * FROM vehiculos WHERE cliente_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY patente');
    $st->execute($ids);
    foreach ($st->fetchAll() as $v) {
        $porCliente[$v['cliente_id']][] = $v;
    }
}

encabezado('Clientes', 'clientes');
?>
<div class="tarjeta">
    <form method="get" class="fila">
        <div style="flex:3">
            <label for="buscar">Nombre, email o patente</label>
            <input id="buscar" name="buscar" value="<?= e($buscar) ?>">
        </div>
        <button class="boton boton-secundario" type="submit">Buscar</button>
    </form>
</div>
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
                        <a href="<?= url('/taller/ordenes.php?buscar=' . urlencode($v['patente'])) ?>"><strong><?= e($v['patente']) ?></strong></a>
                        <span class="chico"><?= e($v['marca'] . ' ' . $v['modelo']) ?></span><br>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$clientes): ?><tr><td colspan="3" class="chico">No hay clientes que coincidan.</td></tr><?php endif; ?>
    </table>
    <?= $pag->links() ?>
</div>
<?php pie(); ?>
