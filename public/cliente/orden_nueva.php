<?php
/**
 * El cliente pide una orden nueva desde la web: elige uno de sus autos (o carga uno nuevo)
 * y cuenta qué le pasa. Se puede pedir cuando quiera, aunque ese auto ya tenga otra orden abierta.
 */
require __DIR__ . '/../../src/app.php';
$usuario = requiereRol('cliente');

$st = db()->prepare(
    "SELECT v.*, (SELECT COUNT(*) FROM ordenes o WHERE o.vehiculo_id = v.id AND o.estado <> 'entregado') AS abiertas
     FROM vehiculos v WHERE v.cliente_id = ? ORDER BY v.patente"
);
$st->execute([$usuario['id']]);
$misAutos = $st->fetchAll();

$d = array_merge(['vehiculo_id' => $misAutos ? (string)$misAutos[0]['id'] : 'nuevo', 'patente' => '', 'marca' => '', 'modelo' => '', 'anio' => '', 'problema' => ''],
    array_map(fn($v) => is_string($v) ? trim($v) : '', $_POST));
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $errores = Validador::errores([Validador::obligatorio($d['problema'], '¿Qué le pasa al auto?', 2000)]);
    $vehiculoId = null;

    if ($d['vehiculo_id'] === 'nuevo') {
        $patente = Validador::normalizarPatente($d['patente']);
        $errores = array_merge($errores, Validador::errores([
            Validador::patente($patente),
            Validador::obligatorio($d['marca'], 'Marca', 50),
            Validador::obligatorio($d['modelo'], 'Modelo', 50),
            Validador::anio($d['anio']),
        ]));
        $st = db()->prepare('SELECT cliente_id FROM vehiculos WHERE patente = ?');
        $st->execute([$patente]);
        $duenio = $st->fetchColumn();
        if ($duenio !== false && (int)$duenio === $usuario['id']) {
            $errores[] = 'Ese auto ya está en tu lista: elegilo arriba.';
        } elseif ($duenio !== false) {
            $errores[] = 'Esa patente está registrada a otra persona. Comunicate con el taller.';
        }
    } else {
        // Solo puede elegir uno de sus propios autos.
        foreach ($misAutos as $a) {
            if ((string)$a['id'] === $d['vehiculo_id']) {
                $vehiculoId = (int)$a['id'];
            }
        }
        if (!$vehiculoId) {
            $errores[] = 'Elegí uno de tus autos.';
        }
    }

    if (!$errores) {
        if ($vehiculoId === null) {
            db()->prepare('INSERT INTO vehiculos (cliente_id, patente, marca, modelo, anio) VALUES (?, ?, ?, ?, ?)')
                ->execute([$usuario['id'], $patente, $d['marca'], $d['modelo'], $d['anio'] !== '' ? (int)$d['anio'] : null]);
            $vehiculoId = (int)db()->lastInsertId();
        }
        $id = ordenes()->crear($vehiculoId, $d['problema'], null, $usuario['id'], false);
        $orden = ordenes()->buscar($id);
        flash('ok', "Listo, pediste la orden #$id. Tu código de seguimiento es {$orden['codigo_seguimiento']}. El taller la va a revisar cuando lleves el auto.");
        redirigir('/cliente/orden.php?id=' . $id);
    }
}

encabezado('Pedir una orden nueva', 'nueva');
?>
<?php foreach ($errores as $err): ?><div class="alerta alerta-error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="tarjeta caja-media">
    <?= csrfCampo() ?>
    <h2>1. ¿Qué auto?</h2>
    <?php foreach ($misAutos as $a): ?>
        <label class="opcion">
            <input type="radio" name="vehiculo_id" value="<?= $a['id'] ?>" <?= (string)$a['id'] === $d['vehiculo_id'] ? 'checked' : '' ?>>
            <strong><?= e($a['patente']) ?></strong> <?= e($a['marca'] . ' ' . $a['modelo']) ?>
            <?php if ($a['abiertas'] > 0): ?><span class="chico">· ya tiene <?= $a['abiertas'] ?> orden(es) en curso, podés pedir otra igual</span><?php endif; ?>
        </label>
    <?php endforeach; ?>
    <label class="opcion">
        <input type="radio" name="vehiculo_id" value="nuevo" id="opcion-nuevo" <?= $d['vehiculo_id'] === 'nuevo' ? 'checked' : '' ?>>
        Otro auto (lo cargo ahora)
    </label>
    <div id="datos-auto-nuevo" class="fila">
        <div><label for="patente">Patente</label><input id="patente" name="patente" value="<?= e($d['patente']) ?>" placeholder="AB123CD"></div>
        <div><label for="marca">Marca</label><input id="marca" name="marca" value="<?= e($d['marca']) ?>"></div>
        <div><label for="modelo">Modelo</label><input id="modelo" name="modelo" value="<?= e($d['modelo']) ?>"></div>
        <div><label for="anio">Año</label><input id="anio" name="anio" value="<?= e($d['anio']) ?>" inputmode="numeric"></div>
    </div>

    <h2>2. ¿Qué le pasa?</h2>
    <textarea id="problema" name="problema" required placeholder="Ej.: hace ruido al frenar"><?= e($d['problema']) ?></textarea>
    <p class="chico">El taller arma el presupuesto cuando revisa el auto y te avisa por email para que lo apruebes.</p>
    <p><button class="boton" type="submit">Pedir orden</button>
       <a href="<?= url('/cliente/inicio.php') ?>">Cancelar</a></p>
</form>
<script>
// Los datos del auto nuevo se muestran solo si elige "Otro auto".
(function () {
    const caja = document.getElementById('datos-auto-nuevo');
    function actualizar() { caja.hidden = !document.getElementById('opcion-nuevo').checked; }
    document.querySelectorAll('input[name=vehiculo_id]').forEach(function (r) { r.addEventListener('change', actualizar); });
    actualizar();
})();
</script>
<?php pie(); ?>
