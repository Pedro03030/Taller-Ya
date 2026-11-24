<?php
/**
 * Historial de empleados: qué hizo cada persona del taller y cuándo.
 * Lo ven solo el admin y los empleados (los clientes no tienen acceso).
 */
require __DIR__ . '/../../src/app.php';
requierePersonal();

$filtroUsuario = (int)($_GET['usuario'] ?? 0);
$filtroDia = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia'] ?? '') ? $_GET['dia'] : '';
$soloPresupuestos = !empty($_GET['presupuestos']);

$where = 'WHERE 1=1';
$params = [];
if ($filtroUsuario) {
    $where .= ' AND h.usuario_id = ?';
    $params[] = $filtroUsuario;
}
if ($filtroDia) {
    $where .= ' AND h.fecha BETWEEN ? AND ?';
    array_push($params, "$filtroDia 00:00:00", "$filtroDia 23:59:59");
}
if ($soloPresupuestos) {
    $where .= " AND h.accion LIKE '%presupuesto%'";
}

$st = db()->prepare("SELECT COUNT(*) FROM historial_empleados h $where");
$st->execute($params);
$pag = new Paginador((int)$st->fetchColumn(), 15);

$st = db()->prepare(
    "SELECT h.*, u.nombre AS usuario_nombre, v.patente
     FROM historial_empleados h
     JOIN usuarios u ON u.id = h.usuario_id
     LEFT JOIN ordenes o ON o.id = h.orden_id
     LEFT JOIN vehiculos v ON v.id = o.vehiculo_id
     $where ORDER BY h.fecha DESC, h.id DESC" . $pag->limiteSql()
);
$st->execute($params);
$lista = $st->fetchAll();

$empleados = db()->query("SELECT id, nombre FROM usuarios WHERE rol IN ('admin', 'empleado') ORDER BY nombre")->fetchAll();

encabezado('Historial de empleados', 'historial');
?>
<div class="tarjeta">
    <form method="get" class="fila">
        <div>
            <label for="usuario">Empleado</label>
            <select id="usuario" name="usuario">
                <option value="">Todos</option>
                <?php foreach ($empleados as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $emp['id'] == $filtroUsuario ? 'selected' : '' ?>><?= e($emp['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label for="dia">Día</label><input type="date" id="dia" name="dia" value="<?= e($filtroDia) ?>"></div>
        <div class="check"><label><input type="checkbox" name="presupuestos" value="1" <?= $soloPresupuestos ? 'checked' : '' ?>> Solo presupuestos</label></div>
        <button class="boton boton-secundario" type="submit">Filtrar</button>
    </form>
</div>

<div class="tarjeta">
    <p class="chico">Todo lo que hace el personal del taller queda registrado acá. Solo lo ven el admin y los empleados.</p>
    <table>
        <tr><th>Fecha</th><th>Empleado</th><th>Acción</th><th>Orden</th><th>Detalle</th></tr>
        <?php foreach ($lista as $h): ?>
            <tr>
                <td class="chico"><?= fecha($h['fecha'], true) ?></td>
                <td><?= e($h['usuario_nombre']) ?></td>
                <td><?= e($h['accion']) ?></td>
                <td><?php if ($h['orden_id']): ?><a href="<?= url('/taller/orden.php?id=' . $h['orden_id']) ?>">#<?= $h['orden_id'] ?></a> <span class="chico"><?= e($h['patente']) ?></span><?php else: ?>—<?php endif; ?></td>
                <td class="chico"><?= e($h['detalle']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="5" class="chico">No hay acciones registradas con esos filtros.</td></tr><?php endif; ?>
    </table>
    <?= $pag->links() ?>
</div>
<?php pie(); ?>
