<?php
/** Listado de todas las órdenes con filtro por estado y búsqueda por patente o cliente. */
require __DIR__ . '/../../src/app.php';
requierePersonal();

$estado = $_GET['estado'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');

$desde = 'FROM ordenes o JOIN vehiculos v ON v.id = o.vehiculo_id JOIN usuarios u ON u.id = v.cliente_id WHERE 1=1';
$sql = '';
$params = [];
if (Estados::existe($estado)) {
    $sql .= ' AND o.estado = ?';
    $params[] = $estado;
}
if ($buscar !== '') {
    $sql .= ' AND (v.patente LIKE ? OR u.nombre LIKE ?)';
    $params[] = '%' . Validador::normalizarPatente($buscar) . '%';
    $params[] = '%' . $buscar . '%';
}
// Primero se cuentan los resultados para paginar, después se trae solo la página actual.
$st = db()->prepare("SELECT COUNT(*) $desde $sql");
$st->execute($params);
$pag = new Paginador((int)$st->fetchColumn());

$st = db()->prepare("SELECT o.*, v.patente, v.marca, v.modelo, u.nombre AS cliente_nombre $desde $sql ORDER BY o.fecha_ingreso DESC, o.id DESC" . $pag->limiteSql());
$st->execute($params);
$lista = $st->fetchAll();
$hoy = date('Y-m-d');

encabezado('Órdenes de trabajo', 'ordenes');
?>
<div class="tarjeta">
    <form method="get" class="fila">
        <div>
            <label for="buscar">Patente o cliente</label>
            <input id="buscar" name="buscar" value="<?= e($buscar) ?>">
        </div>
        <div>
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach (Estados::NOMBRES as $clave => $nombre): ?>
                    <option value="<?= $clave ?>" <?= $clave === $estado ? 'selected' : '' ?>><?= e($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="boton boton-secundario" type="submit">Filtrar</button>
    </form>
</div>

<div class="tarjeta">
    <?php if (!$lista): ?>
        <p class="chico">No hay órdenes que coincidan.</p>
    <?php else: ?>
    <table>
        <tr><th>#</th><th>Ingreso</th><th>Vehículo</th><th>Cliente</th><th>Estado</th><th>Fecha estimada</th><th></th></tr>
        <?php foreach ($lista as $o): ?>
            <tr class="<?= Estados::estaDemorada($o['estado'], $o['fecha_estimada'], $hoy) ? 'demorada' : '' ?>">
                <td><?= $o['id'] ?></td>
                <td><?= fecha($o['fecha_ingreso']) ?></td>
                <td><strong><?= e($o['patente']) ?></strong> <span class="chico"><?= e($o['marca'] . ' ' . $o['modelo']) ?></span></td>
                <td><?= e($o['cliente_nombre']) ?></td>
                <td><?= etiquetaEstado($o['estado']) ?></td>
                <td><?= fecha($o['fecha_estimada']) ?></td>
                <td><a class="boton boton-chico boton-secundario" href="<?= url('/taller/orden.php?id=' . $o['id']) ?>">Ver</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?= $pag->links() ?>
</div>
<?php pie(); ?>
