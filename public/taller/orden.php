<?php
/**
 * Detalle de una orden para el taller: diagnóstico, presupuesto,
 * cambio de estado, historial y avisos enviados.
 */
require __DIR__ . '/../../src/app.php';
$usuario = requiereRol('taller');

$id = (int)($_GET['id'] ?? 0);
$servicio = ordenes();
$orden = $servicio->buscar($id);
if (!$orden) {
    http_response_code(404);
    encabezado('Orden no encontrada');
    echo '<p><a href="' . url('/taller/ordenes.php') . '">Volver a las órdenes</a></p>';
    pie();
    exit;
}
$editable = in_array($orden['estado'], ['ingresado', 'en_diagnostico'], true);
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'datos') {
        $fechaEst = trim($_POST['fecha_estimada'] ?? '');
        $errores = Validador::errores([Validador::fechaFutura($fechaEst, date('Y-m-d'))]);
        if (!$errores) {
            db()->prepare('UPDATE ordenes SET diagnostico = ?, fecha_estimada = ? WHERE id = ?')
                ->execute([trim($_POST['diagnostico'] ?? '') ?: null, $fechaEst ?: null, $id]);
            flash('ok', 'Datos guardados.');
            redirigir("/taller/orden.php?id=$id");
        }
    } elseif ($accion === 'agregar_item' && $editable) {
        $tipo = $_POST['tipo'] ?? '';
        $errores = Validador::errores([
            in_array($tipo, ['repuesto', 'mano_obra'], true) ? null : 'Tipo de ítem inválido.',
            Validador::obligatorio($_POST['descripcion'] ?? '', 'Descripción', 150),
            Validador::numeroPositivo($_POST['cantidad'] ?? '', 'Cantidad'),
            Validador::numeroPositivo($_POST['precio_unitario'] ?? '', 'Precio'),
        ]);
        if (!$errores) {
            db()->prepare('INSERT INTO orden_items (orden_id, tipo, descripcion, cantidad, precio_unitario) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, $tipo, trim($_POST['descripcion']), $_POST['cantidad'], $_POST['precio_unitario']]);
            redirigir("/taller/orden.php?id=$id#presupuesto");
        }
    } elseif ($accion === 'borrar_item' && $editable) {
        db()->prepare('DELETE FROM orden_items WHERE id = ? AND orden_id = ?')->execute([(int)$_POST['item_id'], $id]);
        redirigir("/taller/orden.php?id=$id#presupuesto");
    } elseif ($accion === 'estado') {
        $r = $servicio->cambiarEstado($id, $_POST['nuevo'] ?? '', $usuario['id'], 'taller', trim($_POST['comentario'] ?? '') ?: null);
        if ($r['ok']) {
            flash('ok', 'Estado actualizado.');
            if ($r['aviso']) flash('aviso', $r['aviso']);
            redirigir("/taller/orden.php?id=$id");
        }
        $errores[] = $r['error'];
    } elseif ($accion === 'reintentar') {
        $res = $servicio->reintentar((int)$_POST['notificacion_id']);
        flash($res === 'enviada' ? 'ok' : 'error', $res === 'enviada' ? 'Aviso enviado.' : 'Todavía no se pudo enviar el aviso.');
        redirigir("/taller/orden.php?id=$id#avisos");
    }
}

$items = $servicio->items($id);
$historial = $servicio->historial($id);
$avisos = $servicio->notificaciones($id);
$total = Presupuesto::total($items);
$demorada = Estados::estaDemorada($orden['estado'], $orden['fecha_estimada'], date('Y-m-d'));

encabezado('Orden #' . $id, 'ordenes');
?>
<?php foreach ($errores as $err): ?><div class="alerta alerta-error"><?= e($err) ?></div><?php endforeach; ?>
<?php if ($demorada): ?><div class="alerta alerta-error">⚠ Esta orden pasó la fecha estimada (<?= fecha($orden['fecha_estimada']) ?>).</div><?php endif; ?>

<div class="grilla-2">
    <div>
        <div class="tarjeta">
            <h2><?= e($orden['patente']) ?> · <?= e($orden['marca'] . ' ' . $orden['modelo'] . ($orden['anio'] ? ' ' . $orden['anio'] : '')) ?></h2>
            <p>Estado: <?= etiquetaEstado($orden['estado']) ?> &nbsp; Código: <span class="codigo"><?= e($orden['codigo_seguimiento']) ?></span></p>
            <p><strong>Problema informado:</strong> <?= e($orden['problema']) ?></p>
            <form method="post">
                <?= csrfCampo() ?>
                <input type="hidden" name="accion" value="datos">
                <label for="diagnostico">Diagnóstico</label>
                <textarea id="diagnostico" name="diagnostico"><?= e($orden['diagnostico']) ?></textarea>
                <div class="fila">
                    <div>
                        <label for="fecha_estimada">Fecha estimada</label>
                        <input type="date" id="fecha_estimada" name="fecha_estimada" value="<?= e($orden['fecha_estimada']) ?>">
                    </div>
                    <button class="boton boton-secundario" type="submit">Guardar</button>
                </div>
            </form>
        </div>

        <div class="tarjeta" id="presupuesto">
            <h2>Presupuesto</h2>
            <table>
                <tr><th>Concepto</th><th>Tipo</th><th class="derecha">Cant.</th><th class="derecha">Precio unit.</th><th class="derecha">Subtotal</th><?php if ($editable): ?><th></th><?php endif; ?></tr>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['descripcion']) ?></td>
                        <td><?= $it['tipo'] === 'repuesto' ? 'Repuesto' : 'Mano de obra' ?></td>
                        <td class="derecha"><?= e((float)$it['cantidad']) ?></td>
                        <td class="derecha"><?= Presupuesto::formatear($it['precio_unitario']) ?></td>
                        <td class="derecha"><?= Presupuesto::formatear($it['cantidad'] * $it['precio_unitario']) ?></td>
                        <?php if ($editable): ?>
                        <td>
                            <form method="post"><?= csrfCampo() ?>
                                <input type="hidden" name="accion" value="borrar_item">
                                <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                                <button class="boton boton-chico boton-peligro" type="submit">Quitar</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?><tr><td colspan="6" class="chico">Todavía no hay ítems.</td></tr><?php endif; ?>
            </table>
            <p class="chico">Repuestos: <?= Presupuesto::formatear(Presupuesto::totalPorTipo($items, 'repuesto')) ?> ·
                Mano de obra: <?= Presupuesto::formatear(Presupuesto::totalPorTipo($items, 'mano_obra')) ?></p>
            <p class="derecha total">Total: <?= Presupuesto::formatear($total) ?></p>

            <?php if ($editable): ?>
            <form method="post" class="fila">
                <?= csrfCampo() ?>
                <input type="hidden" name="accion" value="agregar_item">
                <div><label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo"><option value="repuesto">Repuesto</option><option value="mano_obra">Mano de obra</option></select></div>
                <div style="flex:3"><label for="descripcion">Descripción</label><input id="descripcion" name="descripcion" required></div>
                <div><label for="cantidad">Cant.</label><input id="cantidad" name="cantidad" value="1" inputmode="decimal" required></div>
                <div><label for="precio_unitario">Precio</label><input id="precio_unitario" name="precio_unitario" inputmode="decimal" required></div>
                <button class="boton" type="submit">Agregar</button>
            </form>
            <?php else: ?>
                <p class="chico">El presupuesto ya se envió al cliente y no se puede modificar.</p>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="tarjeta">
            <h2>Cliente</h2>
            <p><?= e($orden['cliente_nombre']) ?><br>
               <span class="chico"><?= e($orden['cliente_telefono']) ?> · <?= e($orden['cliente_email']) ?></span></p>
        </div>

        <div class="tarjeta">
            <h2>Cambiar estado</h2>
            <?php $siguientes = Estados::siguientes($orden['estado']); ?>
            <?php if (!$siguientes): ?>
                <p class="chico">La orden está cerrada.</p>
            <?php else: ?>
                <form method="post">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="estado">
                    <label for="comentario">Comentario (opcional)</label>
                    <input id="comentario" name="comentario" placeholder="Ej.: aprobó por teléfono">
                    <p>
                    <?php foreach ($siguientes as $s): ?>
                        <button class="boton <?= $s === 'rechazado' ? 'boton-peligro' : '' ?>" name="nuevo" value="<?= $s ?>" type="submit">→ <?= e(Estados::nombre($s)) ?></button>
                    <?php endforeach; ?>
                    </p>
                </form>
                <?php if (Estados::hayQueAvisar($siguientes[0])): ?>
                    <p class="chico">Al pasar a "<?= e(Estados::nombre($siguientes[0])) ?>" se le manda un email al cliente.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="tarjeta">
            <h2>Historial</h2>
            <?php foreach ($historial as $h): ?>
                <p><?= etiquetaEstado($h['estado_nuevo']) ?><br>
                   <span class="chico"><?= fecha($h['fecha'], true) ?> · <?= e($h['usuario_nombre']) ?><?= $h['comentario'] ? ' · ' . e($h['comentario']) : '' ?></span></p>
            <?php endforeach; ?>
        </div>

        <div class="tarjeta" id="avisos">
            <h2>Avisos por email</h2>
            <?php if (!$avisos): ?><p class="chico">Todavía no se mandaron avisos.</p><?php endif; ?>
            <?php foreach ($avisos as $a): ?>
                <p>
                    <?php if ($a['estado'] === 'enviada'): ?><span class="estado estado-listo">Enviado</span>
                    <?php else: ?><span class="estado estado-rechazado">Falló</span><?php endif; ?>
                    <span class="chico"><?= fecha($a['fecha'], true) ?> · a <?= e($a['destinatario']) ?></span><br>
                    <span class="chico"><strong><?= e($a['asunto']) ?></strong></span>
                    <?php if ($a['error']): ?><br><span class="chico">Error: <?= e($a['error']) ?></span><?php endif; ?>
                </p>
                <?php if ($a['estado'] !== 'enviada'): ?>
                    <form method="post"><?= csrfCampo() ?>
                        <input type="hidden" name="accion" value="reintentar">
                        <input type="hidden" name="notificacion_id" value="<?= $a['id'] ?>">
                        <button class="boton boton-chico boton-secundario" type="submit">Reintentar</button>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php pie(); ?>
