<?php
/**
 * Dinero movido (solo el admin): lo que se cobró al entregar los autos,
 * separado por día, por mes y desde siempre.
 */
require __DIR__ . '/../../src/app.php';
requiereAdmin();

$dinero = new Dinero(db());
$hoy = date('Y-m-d');
$mesActual = date('Y-m');

$vista = in_array($_GET['vista'] ?? '', ['dia', 'mes', 'siempre'], true) ? $_GET['vista'] : 'dia';
$dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia'] ?? '') ? $_GET['dia'] : $hoy;
$mes = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : $mesActual;

$tarjetas = [
    'Hoy'        => $dinero->resumen($hoy, $hoy),
    'Este mes'   => $dinero->resumen(date('Y-m-01'), date('Y-m-t')),
    'Siempre'    => $dinero->resumen(),
];
$pendiente = $dinero->pendienteDeCobro(ordenes());

if ($vista === 'dia') {
    $resumen = $dinero->resumen($dia, $dia);
    $pag = new Paginador($resumen['cantidad']);
    $filas = $dinero->entregadas($dia, $dia, $pag);
} elseif ($vista === 'mes') {
    $resumen = $dinero->resumen($mes . '-01', date('Y-m-t', strtotime($mes . '-01')));
    $pag = new Paginador($dinero->contarDias($mes));
    $filas = $dinero->porDia($mes, $pag);
} else {
    $resumen = $tarjetas['Siempre'];
    $pag = new Paginador($dinero->contarMeses());
    $filas = $dinero->porMes($pag);
}

function linkVista(string $v, array $extra = []): string
{
    return url('/taller/dinero.php?' . http_build_query(['vista' => $v] + $extra));
}

encabezado('Dinero', 'dinero');
?>
<p class="chico">Se cuenta lo que se cobra cuando el auto se <strong>entrega</strong>. Si el cliente rechazó el presupuesto, el auto se devuelve sin reparar y no suma.</p>

<div class="grilla-4">
    <?php foreach ($tarjetas as $titulo => $r): ?>
        <div class="numero"><strong><?= Presupuesto::formatear($r['total']) ?></strong><span><?= e($titulo) ?> · <?= $r['cantidad'] ?> entrega(s)</span></div>
    <?php endforeach; ?>
    <div class="numero numero-pendiente"><strong><?= Presupuesto::formatear($pendiente['total']) ?></strong><span>Pendiente de cobro · <?= $pendiente['cantidad'] ?> orden(es) aprobada(s)</span></div>
</div>

<nav class="pestanas">
    <a href="<?= linkVista('dia', ['dia' => $dia]) ?>" class="<?= $vista === 'dia' ? 'activo' : '' ?>">Por día</a>
    <a href="<?= linkVista('mes', ['mes' => $mes]) ?>" class="<?= $vista === 'mes' ? 'activo' : '' ?>">Por mes</a>
    <a href="<?= linkVista('siempre') ?>" class="<?= $vista === 'siempre' ? 'activo' : '' ?>">Siempre</a>
</nav>

<div class="tarjeta pestana-contenido">
    <?php if ($vista === 'dia'): ?>
        <form method="get" class="fila">
            <input type="hidden" name="vista" value="dia">
            <div><label for="dia">Día</label><input type="date" id="dia" name="dia" value="<?= e($dia) ?>" max="<?= $hoy ?>"></div>
            <button class="boton boton-secundario" type="submit">Ver</button>
        </form>
        <h2><?= fecha($dia) ?>: <?= Presupuesto::formatear($resumen['total']) ?> <span class="chico">en <?= $resumen['cantidad'] ?> entrega(s)</span></h2>
        <table>
            <tr><th>Hora</th><th>Orden</th><th>Vehículo</th><th>Cliente</th><th class="derecha">Cobrado</th></tr>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td><?= date('H:i', strtotime($f['fecha_entrega'])) ?></td>
                    <td><a href="<?= url('/taller/orden.php?id=' . $f['id']) ?>">#<?= $f['id'] ?></a></td>
                    <td><strong><?= e($f['patente']) ?></strong> <span class="chico"><?= e($f['marca'] . ' ' . $f['modelo']) ?></span></td>
                    <td><?= e($f['cliente_nombre']) ?></td>
                    <td class="derecha"><?= Presupuesto::formatear((float)$f['monto_cobrado']) ?><?= (float)$f['monto_cobrado'] == 0 ? ' <span class="chico">(rechazó)</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$filas): ?><tr><td colspan="5" class="chico">Ese día no se entregaron autos.</td></tr><?php endif; ?>
        </table>

    <?php elseif ($vista === 'mes'): ?>
        <form method="get" class="fila">
            <input type="hidden" name="vista" value="mes">
            <div><label for="mes">Mes</label><input type="month" id="mes" name="mes" value="<?= e($mes) ?>" max="<?= $mesActual ?>"></div>
            <button class="boton boton-secundario" type="submit">Ver</button>
        </form>
        <h2><?= e(Dinero::nombreMes($mes)) ?>: <?= Presupuesto::formatear($resumen['total']) ?> <span class="chico">en <?= $resumen['cantidad'] ?> entrega(s)</span></h2>
        <table>
            <tr><th>Día</th><th class="derecha">Entregas</th><th class="derecha">Cobrado</th><th></th></tr>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td><?= fecha($f['dia']) ?></td>
                    <td class="derecha"><?= (int)$f['cantidad'] ?></td>
                    <td class="derecha"><?= Presupuesto::formatear((float)$f['total']) ?></td>
                    <td><a class="boton boton-chico boton-secundario" href="<?= linkVista('dia', ['dia' => $f['dia']]) ?>">Ver día</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$filas): ?><tr><td colspan="4" class="chico">Ese mes no se entregaron autos.</td></tr><?php endif; ?>
        </table>

    <?php else: ?>
        <h2>Desde siempre: <?= Presupuesto::formatear($resumen['total']) ?> <span class="chico">en <?= $resumen['cantidad'] ?> entrega(s)</span></h2>
        <table>
            <tr><th>Mes</th><th class="derecha">Entregas</th><th class="derecha">Cobrado</th><th></th></tr>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td><?= e(Dinero::nombreMes($f['mes'])) ?></td>
                    <td class="derecha"><?= (int)$f['cantidad'] ?></td>
                    <td class="derecha"><?= Presupuesto::formatear((float)$f['total']) ?></td>
                    <td><a class="boton boton-chico boton-secundario" href="<?= linkVista('mes', ['mes' => $f['mes']]) ?>">Ver mes</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$filas): ?><tr><td colspan="4" class="chico">Todavía no se entregó ningún auto.</td></tr><?php endif; ?>
        </table>
    <?php endif; ?>
    <?= $pag->links() ?>
</div>
<?php pie(); ?>
