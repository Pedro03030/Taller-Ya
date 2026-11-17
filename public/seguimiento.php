<?php
/**
 * Consulta pública: el cliente ve el estado con la patente y el código
 * que le dieron al dejar el auto. No necesita usuario.
 * Resuelve directamente "me llaman todo el día para saber si está listo".
 */
require __DIR__ . '/../src/app.php';

$orden = null;
$items = [];
$error = null;
$patente = '';

if (isset($_GET['patente'])) {
    $patente = Validador::normalizarPatente($_GET['patente']);
    $codigo = strtoupper(trim($_GET['codigo'] ?? ''));
    $error = Validador::patente($patente) ?? (strlen($codigo) !== 6 ? 'El código tiene 6 caracteres.' : null);

    if (!$error) {
        $st = db()->prepare(
            'SELECT o.id FROM ordenes o JOIN vehiculos v ON v.id = o.vehiculo_id
             WHERE v.patente = ? AND o.codigo_seguimiento = ?'
        );
        $st->execute([$patente, $codigo]);
        $id = $st->fetchColumn();
        if ($id) {
            $orden = ordenes()->buscar((int)$id);
            $items = ordenes()->items((int)$id);
        } else {
            $error = 'No encontramos ninguna orden con esa patente y ese código.';
        }
    }
}

encabezado('Consultar el estado de mi auto');
?>
<div class="tarjeta caja-centrada">
    <?php if ($error): ?><div class="alerta alerta-error"><?= e($error) ?></div><?php endif; ?>
    <form method="get">
        <label for="patente">Patente</label>
        <input id="patente" name="patente" value="<?= e($patente) ?>" placeholder="AB123CD" required>
        <label for="codigo">Código de seguimiento</label>
        <input id="codigo" name="codigo" placeholder="Está en el comprobante" maxlength="6" required>
        <p><button class="boton" type="submit">Consultar</button></p>
    </form>
</div>

<?php if ($orden): ?>
    <?php include __DIR__ . '/../src/vista_estado_cliente.php'; ?>
    <p class="chico">Para aprobar el presupuesto desde acá, <a href="<?= url('/login.php') ?>">ingresá con tu usuario</a> o llamá al taller.</p>
<?php endif; ?>
<?php pie(); ?>
