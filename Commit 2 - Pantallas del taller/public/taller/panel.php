<?php
/** Pantalla de inicio del taller: resumen del día y alertas de demora. */
require __DIR__ . '/../../src/app.php';
requierePersonal();

$hoy = date('Y-m-d');
$abiertas = db()->query(
    "SELECT o.*, v.patente, v.marca, v.modelo, u.nombre AS cliente_nombre, u.telefono AS cliente_telefono
     FROM ordenes o JOIN vehiculos v ON v.id = o.vehiculo_id JOIN usuarios u ON u.id = v.cliente_id
     WHERE o.estado NOT IN ('entregado')
     ORDER BY o.fecha_estimada IS NULL, o.fecha_estimada, o.fecha_ingreso"
)->fetchAll();

$cuenta = ['abiertas' => 0, 'esperando' => 0, 'listos' => 0, 'demoradas' => 0];
$demoradas = [];
foreach ($abiertas as $o) {
    if (Estados::estaAbierta($o['estado'])) $cuenta['abiertas']++;
    if ($o['estado'] === 'presupuestado') $cuenta['esperando']++;
    if ($o['estado'] === 'listo') $cuenta['listos']++;
    if (Estados::estaDemorada($o['estado'], $o['fecha_estimada'], $hoy)) {
        $cuenta['demoradas']++;
        $demoradas[] = $o;
    }
}

$pag = new Paginador(count($abiertas));

encabezado('Panel del taller', 'panel');
?>
<div class="grilla-4">
    <div class="numero"><strong><?= $cuenta['abiertas'] ?></strong><span>Autos en el taller</span></div>
    <div class="numero"><strong><?= $cuenta['esperando'] ?></strong><span>Presupuestos sin respuesta</span></div>
    <div class="numero"><strong><?= $cuenta['listos'] ?></strong><span>Listos para retirar</span></div>
    <div class="numero <?= $cuenta['demoradas'] ? 'alerta-borde' : '' ?>"><strong><?= $cuenta['demoradas'] ?></strong><span>Demorados</span></div>
</div>

<?php if ($demoradas): ?>
<div class="alerta alerta-error">
    ⚠ Hay <?= count($demoradas) ?> auto(s) que pasaron la fecha estimada. Revisalos o avisale al cliente.
</div>
<?php endif; ?>

<div class="tarjeta">
    <h2>Trabajo pendiente</h2>
    <?php if (!$abiertas): ?>
        <p class="chico">No hay autos en el taller.</p>
    <?php else: ?>
    <table>
        <tr><th>#</th><th>Vehículo</th><th>Cliente</th><th>Estado</th><th>Fecha estimada</th><th></th></tr>
        <?php foreach ($pag->recortar($abiertas) as $o): ?>
            <tr class="<?= Estados::estaDemorada($o['estado'], $o['fecha_estimada'], $hoy) ? 'demorada' : '' ?>">
                <td><?= $o['id'] ?></td>
                <td><strong><?= e($o['patente']) ?></strong><br><span class="chico"><?= e($o['marca'] . ' ' . $o['modelo']) ?></span></td>
                <td><?= e($o['cliente_nombre']) ?><br><span class="chico"><?= e($o['cliente_telefono']) ?></span></td>
                <td><?= etiquetaEstado($o['estado']) ?></td>
                <td><?= fecha($o['fecha_estimada']) ?></td>
                <td><a class="boton boton-chico boton-secundario" href="<?= url('/taller/orden.php?id=' . $o['id']) ?>">Ver</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?= $pag->links() ?>
    <?php endif; ?>
</div>
<p class="chico"><a href="<?= url('/taller/probar_email.php') ?>">Probar el envío de emails</a></p>
<?php pie(); ?>
