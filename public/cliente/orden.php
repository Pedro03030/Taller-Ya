<?php
/** Detalle de una orden para el cliente; desde acá aprueba o rechaza el presupuesto. */
require __DIR__ . '/../../src/app.php';
$usuario = requiereRol('cliente');

$id = (int)($_GET['id'] ?? 0);
$servicio = ordenes();
$orden = $servicio->buscar($id);

// Un cliente solo puede ver sus propias órdenes.
if (!$orden || (int)$orden['cliente_id'] !== $usuario['id']) {
    http_response_code(404);
    encabezado('Orden no encontrada');
    echo '<p><a href="' . url('/cliente/inicio.php') . '">Volver a mis autos</a></p>';
    pie();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $decision = $_POST['decision'] ?? '';
    $r = $servicio->cambiarEstado($id, $decision, $usuario['id'], 'cliente',
        $decision === 'aprobado' ? 'Aprobado por el cliente desde la web' : 'Rechazado por el cliente desde la web');
    flash($r['ok'] ? 'ok' : 'error', $r['ok']
        ? ($decision === 'aprobado' ? '¡Listo! El taller ya puede empezar la reparación.' : 'Registramos que no querés hacer la reparación. Podés pasar a retirar el auto.')
        : $r['error']);
    redirigir("/cliente/orden.php?id=$id");
}

$items = $servicio->items($id);
$cambios = $servicio->cambiosPresupuesto($id);
encabezado('Mi auto ' . $orden['patente'], 'inicio');
?>
<?php if ($cambios && $orden['estado'] === 'presupuestado'): ?>
<div class="alerta alerta-aviso">
    El taller modificó el presupuesto el <?= fecha($cambios[0]['fecha'], true) ?>:
    antes <?= Presupuesto::formatear($cambios[0]['total_anterior']) ?>, ahora <strong><?= Presupuesto::formatear($cambios[0]['total_nuevo']) ?></strong>.
    Motivo: <?= e($cambios[0]['motivo']) ?>. Revisalo y aprobalo de nuevo.
</div>
<?php endif; ?>
<?php include __DIR__ . '/../../src/vista_estado_cliente.php'; ?>
<?php if ($orden['estado'] === 'presupuestado'): ?>
<div class="tarjeta">
    <h2>¿Aprobás el presupuesto?</h2>
    <form method="post">
        <?= csrfCampo() ?>
        <button class="boton" name="decision" value="aprobado" type="submit">Sí, aprobar por <?= Presupuesto::formatear(Presupuesto::total($items)) ?></button>
        <button class="boton boton-peligro" name="decision" value="rechazado" type="submit"
                onclick="return confirm('¿Seguro que no querés hacer la reparación?')">No, rechazar</button>
    </form>
</div>
<?php endif; ?>
<p class="chico">Código de seguimiento: <span class="codigo"><?= e($orden['codigo_seguimiento']) ?></span></p>
<?php pie(); ?>
