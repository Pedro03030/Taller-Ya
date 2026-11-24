<?php
/**
 * Detalle de una orden para el taller: diagnóstico, presupuesto,
 * cambio de estado, historial y avisos enviados.
 *
 * Presupuesto:
 *  - Mientras la orden está "Ingresado" o "En diagnóstico" es un borrador: se agregan y quitan ítems.
 *  - Después de mandarlo al cliente se puede cambiar hasta que el auto esté listo, pero el empleado
 *    se tiene que identificar con su email y contraseña. Queda registrado quién lo cambió y la orden
 *    vuelve a "Presupuesto listo" para que el cliente lo apruebe de nuevo.
 */
require __DIR__ . '/../../src/app.php';
$usuario = requierePersonal();

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
$borrador = !Estados::presupuestoEnviado($orden['estado']) && Estados::presupuestoModificable($orden['estado']);
$modificable = Estados::presupuestoEnviado($orden['estado']) && Estados::presupuestoModificable($orden['estado']);
$errores = [];
$erroresModificar = [];
$filasForm = null;   // lo que escribió el empleado en "Modificar presupuesto", si hubo un error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'datos') {
        $fechaEst = trim($_POST['fecha_estimada'] ?? '');
        $errores = Validador::errores([Validador::fechaFutura($fechaEst, date('Y-m-d'))]);
        if (!$errores) {
            db()->prepare('UPDATE ordenes SET diagnostico = ?, fecha_estimada = ? WHERE id = ?')
                ->execute([trim($_POST['diagnostico'] ?? '') ?: null, $fechaEst ?: null, $id]);
            registrarAccion($usuario['id'], 'Guardó diagnóstico y fecha', $fechaEst ? 'Fecha estimada: ' . fecha($fechaEst) : null, $id);
            flash('ok', 'Datos guardados.');
            redirigir("/taller/orden.php?id=$id");
        }
    } elseif ($accion === 'agregar_item' && $borrador) {
        $tipo = $_POST['tipo'] ?? '';
        $errores = Validador::errores([
            in_array($tipo, ['repuesto', 'mano_obra'], true) ? null : 'Tipo de ítem inválido.',
            Validador::obligatorio($_POST['descripcion'] ?? '', 'Descripción', 150),
            Validador::numeroPositivo($_POST['cantidad'] ?? '', 'Cantidad'),
            Validador::numeroPositivo($_POST['precio_unitario'] ?? '', 'Precio'),
        ]);
        if (!$errores) {
            db()->prepare('INSERT INTO orden_items (orden_id, tipo, descripcion, cantidad, precio_unitario, usuario_id) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$id, $tipo, trim($_POST['descripcion']), $_POST['cantidad'], $_POST['precio_unitario'], $usuario['id']]);
            registrarAccion($usuario['id'], 'Agregó un ítem al presupuesto',
                trim($_POST['descripcion']) . ' x' . (float)$_POST['cantidad'] . ' ' . Presupuesto::formatear((float)$_POST['precio_unitario']), $id);
            redirigir("/taller/orden.php?id=$id#presupuesto");
        }
    } elseif ($accion === 'borrar_item' && $borrador) {
        $st = db()->prepare('SELECT descripcion FROM orden_items WHERE id = ? AND orden_id = ?');
        $st->execute([(int)$_POST['item_id'], $id]);
        if ($desc = $st->fetchColumn()) {
            db()->prepare('DELETE FROM orden_items WHERE id = ? AND orden_id = ?')->execute([(int)$_POST['item_id'], $id]);
            registrarAccion($usuario['id'], 'Quitó un ítem del presupuesto', $desc, $id);
        }
        redirigir("/taller/orden.php?id=$id#presupuesto");
    } elseif ($accion === 'modificar_presupuesto' && $modificable) {
        // Se arma la lista de cómo tiene que quedar el presupuesto.
        $filasForm = [];
        $items = [];
        foreach ($_POST['descripcion'] ?? [] as $i => $desc) {
            $fila = [
                'id' => ($_POST['item_id'][$i] ?? '') !== '' ? (int)$_POST['item_id'][$i] : null,
                'tipo' => $_POST['tipo'][$i] ?? '',
                'descripcion' => trim($desc),
                'cantidad' => trim($_POST['cantidad'][$i] ?? ''),
                'precio_unitario' => trim($_POST['precio_unitario'][$i] ?? ''),
                'quitar' => isset($_POST['quitar'][$i]),
            ];
            $filasForm[] = $fila;
            $vacia = $fila['id'] === null && $fila['descripcion'] === '' && $fila['precio_unitario'] === '';
            if (!$fila['quitar'] && !$vacia) {
                $items[] = $fila;
            }
        }
        // Corroborar quién es el empleado que hace el cambio.
        $empleado = usuarios()->verificarPersonal($_POST['verif_email'] ?? '', $_POST['verif_password'] ?? '');
        if (!$empleado) {
            $erroresModificar[] = 'El email o la contraseña del empleado no son correctos. El cambio no se guardó.';
        } else {
            $nota = (int)$empleado['id'] !== $usuario['id'] ? 'Desde la sesión de ' . $usuario['nombre'] : null;
            $r = $servicio->modificarPresupuesto($id, $items, (int)$empleado['id'], $_POST['motivo'] ?? '', $nota);
            if ($r['ok']) {
                flash('ok', 'Presupuesto modificado por ' . $empleado['nombre'] . '. La orden volvió a "Presupuesto listo" y se le avisó al cliente para que lo apruebe de nuevo.');
                if ($r['aviso']) flash('aviso', $r['aviso']);
                redirigir("/taller/orden.php?id=$id#presupuesto");
            }
            $erroresModificar[] = $r['error'];
        }
    } elseif ($accion === 'estado') {
        $r = $servicio->cambiarEstado($id, $_POST['nuevo'] ?? '', $usuario['id'], $usuario['rol'], trim($_POST['comentario'] ?? '') ?: null);
        if ($r['ok']) {
            flash('ok', 'Estado actualizado.');
            if ($r['aviso']) flash('aviso', $r['aviso']);
            redirigir("/taller/orden.php?id=$id");
        }
        $errores[] = $r['error'];
    } elseif ($accion === 'reintentar') {
        $res = $servicio->reintentar((int)$_POST['notificacion_id']);
        registrarAccion($usuario['id'], 'Reintentó un aviso por email', $res === 'enviada' ? 'Enviado' : 'Volvió a fallar', $id);
        flash($res === 'enviada' ? 'ok' : 'error', $res === 'enviada' ? 'Aviso enviado.' : 'Todavía no se pudo enviar el aviso.');
        redirigir("/taller/orden.php?id=$id#avisos");
    }
}

$items = $servicio->items($id);
$total = Presupuesto::total($items);
$demorada = Estados::estaDemorada($orden['estado'], $orden['fecha_estimada'], date('Y-m-d'));

$pagHistorial = new Paginador($servicio->contar('historial_estados', $id), 8, 'pag_historial', null, 'historial');
$historial = $servicio->historial($id, $pagHistorial);
$pagAvisos = new Paginador($servicio->contar('notificaciones', $id), 5, 'pag_avisos', null, 'avisos');
$avisos = $servicio->notificaciones($id, $pagAvisos);
$pagCambios = new Paginador($servicio->contar('presupuesto_cambios', $id), 5, 'pag_cambios', null, 'cambios');
$cambios = $servicio->cambiosPresupuesto($id, $pagCambios);

// Filas del formulario "Modificar presupuesto": los ítems actuales más dos renglones vacíos.
if ($filasForm === null) {
    $filasForm = array_map(fn($it) => $it + ['quitar' => false], $items);
    for ($i = 0; $i < 2; $i++) {
        $filasForm[] = ['id' => null, 'tipo' => 'repuesto', 'descripcion' => '', 'cantidad' => '1', 'precio_unitario' => '', 'quitar' => false];
    }
}

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
                <tr><th>Concepto</th><th>Tipo</th><th class="derecha">Cant.</th><th class="derecha">Precio unit.</th><th class="derecha">Subtotal</th><th>Cargado por</th><?php if ($borrador): ?><th></th><?php endif; ?></tr>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['descripcion']) ?></td>
                        <td><?= $it['tipo'] === 'repuesto' ? 'Repuesto' : 'Mano de obra' ?></td>
                        <td class="derecha"><?= e((float)$it['cantidad']) ?></td>
                        <td class="derecha"><?= Presupuesto::formatear($it['precio_unitario']) ?></td>
                        <td class="derecha"><?= Presupuesto::formatear($it['cantidad'] * $it['precio_unitario']) ?></td>
                        <td class="chico"><?= e($it['usuario_nombre'] ?? '—') ?></td>
                        <?php if ($borrador): ?>
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
                <?php if (!$items): ?><tr><td colspan="7" class="chico">Todavía no hay ítems.</td></tr><?php endif; ?>
            </table>
            <p class="chico">Repuestos: <?= Presupuesto::formatear(Presupuesto::totalPorTipo($items, 'repuesto')) ?> ·
                Mano de obra: <?= Presupuesto::formatear(Presupuesto::totalPorTipo($items, 'mano_obra')) ?></p>
            <p class="derecha total">Total: <?= Presupuesto::formatear($total) ?></p>
            <?php if ($cambios): ?>
                <p class="chico">Última modificación: <?= fecha($cambios[0]['fecha'], true) ?> por <strong><?= e($cambios[0]['usuario_nombre']) ?></strong> (<a href="#cambios">ver cambios</a>).</p>
            <?php endif; ?>

            <?php if ($borrador): ?>
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
            <?php elseif ($modificable): ?>
            <details class="modificar" <?= $erroresModificar ? 'open' : '' ?>>
                <summary class="boton boton-secundario">Modificar presupuesto</summary>
                <?php foreach ($erroresModificar as $err): ?><div class="alerta alerta-error"><?= e($err) ?></div><?php endforeach; ?>
                <p class="chico">El presupuesto ya se le mandó al cliente. Si lo cambiás, la orden vuelve a <strong>Presupuesto listo</strong>,
                    se le manda un email al cliente y lo tiene que aprobar de nuevo. Queda registrado quién lo cambió.</p>
                <form method="post">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="modificar_presupuesto">
                    <table class="tabla-edicion" id="tabla-modificar">
                        <tr><th>Tipo</th><th>Descripción</th><th>Cant.</th><th>Precio unit.</th><th>Quitar</th></tr>
                        <?php foreach ($filasForm as $i => $f): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="item_id[<?= $i ?>]" value="<?= e($f['id']) ?>">
                                <select name="tipo[<?= $i ?>]">
                                    <option value="repuesto" <?= $f['tipo'] === 'repuesto' ? 'selected' : '' ?>>Repuesto</option>
                                    <option value="mano_obra" <?= $f['tipo'] === 'mano_obra' ? 'selected' : '' ?>>Mano de obra</option>
                                </select>
                            </td>
                            <td><input name="descripcion[<?= $i ?>]" value="<?= e($f['descripcion']) ?>" placeholder="<?= $f['id'] ? '' : 'Renglón nuevo (opcional)' ?>"></td>
                            <td><input name="cantidad[<?= $i ?>]" value="<?= e(is_numeric($f['cantidad']) ? (float)$f['cantidad'] : $f['cantidad']) ?>" inputmode="decimal" class="corto"></td>
                            <td><input name="precio_unitario[<?= $i ?>]" value="<?= e(is_numeric($f['precio_unitario']) ? (float)$f['precio_unitario'] : $f['precio_unitario']) ?>" inputmode="decimal" class="corto"></td>
                            <td class="centro"><?php if ($f['id']): ?><input type="checkbox" name="quitar[<?= $i ?>]" value="1" <?= $f['quitar'] ? 'checked' : '' ?> aria-label="Quitar este ítem"><?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <p><button type="button" class="boton boton-chico boton-claro-oscuro" id="otro-renglon">+ Otro renglón</button></p>
                    <label for="motivo">Motivo del cambio</label>
                    <input id="motivo" name="motivo" maxlength="255" value="<?= e($_POST['motivo'] ?? '') ?>" placeholder="Ej.: al desarmar apareció otro repuesto gastado" required>
                    <div class="verificacion">
                        <strong>¿Quién hace el cambio?</strong>
                        <p class="chico">Ingresá tu email y contraseña de empleado. Se pide aunque haya una sesión abierta, para saber quién lo cambió.</p>
                        <div class="fila">
                            <div><label for="verif_email">Email del empleado</label><input type="email" id="verif_email" name="verif_email" value="<?= e($_POST['verif_email'] ?? '') ?>" autocomplete="off" required></div>
                            <div><label for="verif_password">Contraseña</label><input type="password" id="verif_password" name="verif_password" autocomplete="off" required></div>
                        </div>
                    </div>
                    <p><button class="boton" type="submit">Guardar cambios y avisar al cliente</button></p>
                </form>
            </details>
            <?php else: ?>
                <p class="chico">El auto ya está listo para retirar: el presupuesto no se puede modificar.</p>
            <?php endif; ?>
        </div>

        <?php if ($pagCambios->total > 0): ?>
        <div class="tarjeta" id="cambios">
            <h2>Cambios del presupuesto</h2>
            <?php foreach ($cambios as $c): ?>
                <div class="cambio">
                    <p><strong><?= e($c['usuario_nombre']) ?></strong> <span class="chico"><?= fecha($c['fecha'], true) ?> · estaba en "<?= e(Estados::nombre($c['estado_anterior'])) ?>"</span><br>
                        <?= Presupuesto::formatear($c['total_anterior']) ?> → <strong><?= Presupuesto::formatear($c['total_nuevo']) ?></strong>
                        · <span class="chico">Motivo: <?= e($c['motivo']) ?></span></p>
                    <p class="chico"><?= nl2br(e($c['detalle'])) ?></p>
                </div>
            <?php endforeach; ?>
            <?= $pagCambios->links() ?>
        </div>
        <?php endif; ?>
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
                <p class="chico">La orden está cerrada.<?= $orden['monto_cobrado'] !== null ? ' Se cobró ' . Presupuesto::formatear((float)$orden['monto_cobrado']) . '.' : '' ?></p>
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

        <div class="tarjeta" id="historial">
            <h2>Historial</h2>
            <?php foreach ($historial as $h): ?>
                <p><?= etiquetaEstado($h['estado_nuevo']) ?><br>
                   <span class="chico"><?= fecha($h['fecha'], true) ?> · <?= e($h['usuario_nombre']) ?><?= $h['comentario'] ? ' · ' . e($h['comentario']) : '' ?></span></p>
            <?php endforeach; ?>
            <?= $pagHistorial->links() ?>
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
            <?php if ($avisos): ?><?= $pagAvisos->links() ?><?php endif; ?>
        </div>
    </div>
</div>

<?php if ($modificable): ?>
<script>
// "+ Otro renglón": copia el último renglón vacío con el número siguiente.
document.getElementById('otro-renglon').addEventListener('click', function () {
    const tabla = document.getElementById('tabla-modificar');
    const ultima = tabla.rows[tabla.rows.length - 1];
    const nueva = ultima.cloneNode(true);
    const n = tabla.rows.length - 1;
    nueva.querySelectorAll('input, select').forEach(function (c) {
        c.name = c.name.replace(/\[\d+\]/, '[' + n + ']');
        if (c.type === 'hidden') c.value = '';
        else if (c.tagName === 'INPUT') c.value = c.name.startsWith('cantidad') ? '1' : '';
    });
    nueva.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.remove(); });
    tabla.tBodies[0].appendChild(nueva);
});
</script>
<?php endif; ?>
<?php pie(); ?>
