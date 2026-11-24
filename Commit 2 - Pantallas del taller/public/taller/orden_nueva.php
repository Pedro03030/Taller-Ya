<?php
/**
 * Alta de una orden cuando el cliente deja el auto.
 * Si la patente ya existe se usa ese vehículo; si no, se carga el vehículo
 * y se elige un cliente existente o se crea uno nuevo.
 */
require __DIR__ . '/../../src/app.php';
$usuario = requierePersonal();

$clientes = db()->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'cliente' ORDER BY nombre")->fetchAll();
$d = array_merge([
    'patente' => '', 'marca' => '', 'modelo' => '', 'anio' => '', 'cliente_id' => '',
    'nombre' => '', 'email' => '', 'telefono' => '', 'problema' => '', 'fecha_estimada' => '',
], array_map('trim', $_POST));
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $hoy = date('Y-m-d');
    $patente = Validador::normalizarPatente($d['patente']);

    $st = db()->prepare('SELECT * FROM vehiculos WHERE patente = ?');
    $st->execute([$patente]);
    $vehiculo = $st->fetch();

    $errores = Validador::errores([
        Validador::patente($patente),
        Validador::obligatorio($d['problema'], 'Problema que cuenta el cliente', 2000),
        Validador::fechaFutura($d['fecha_estimada'], $hoy),
    ]);

    if (!$vehiculo) {
        $errores = array_merge($errores, Validador::errores([
            Validador::obligatorio($d['marca'], 'Marca', 50),
            Validador::obligatorio($d['modelo'], 'Modelo', 50),
            Validador::anio($d['anio']),
        ]));
        if ($d['cliente_id'] === '') {
            $errores = array_merge($errores, Validador::errores([
                Validador::obligatorio($d['nombre'], 'Nombre del cliente', 100),
                Validador::email($d['email']),
            ]));
            $st = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
            $st->execute([$d['email']]);
            if ($st->fetchColumn() > 0) {
                $errores[] = 'Ya existe un usuario con ese email: elegilo en la lista de clientes.';
            }
        }
    }

    if (!$errores) {
        $passwordInicial = null;
        if (!$vehiculo) {
            $clienteId = (int)$d['cliente_id'];
            if ($clienteId === 0) {
                // Cliente nuevo: se le genera una contraseña que el taller le pasa.
                $passwordInicial = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
                db()->prepare(
                    'INSERT INTO usuarios (nombre, email, password_hash, rol, telefono, creado_en) VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$d['nombre'], $d['email'], password_hash($passwordInicial, PASSWORD_DEFAULT), 'cliente', $d['telefono'] ?: null, date('Y-m-d H:i:s')]);
                $clienteId = (int)db()->lastInsertId();
            }
            db()->prepare('INSERT INTO vehiculos (cliente_id, patente, marca, modelo, anio) VALUES (?, ?, ?, ?, ?)')
                ->execute([$clienteId, $patente, $d['marca'], $d['modelo'], $d['anio'] !== '' ? (int)$d['anio'] : null]);
            $vehiculoId = (int)db()->lastInsertId();
        } else {
            $vehiculoId = (int)$vehiculo['id'];
        }

        // Se permite crear la orden aunque el auto ya tenga otra abierta; solo se avisa.
        $yaAbiertas = ordenes()->abiertasDelVehiculo($vehiculoId);
        $id = ordenes()->crear($vehiculoId, $d['problema'], $d['fecha_estimada'] ?: null, $usuario['id']);
        $orden = ordenes()->buscar($id);
        flash('ok', "Orden #$id creada. Código de seguimiento para el cliente: {$orden['codigo_seguimiento']}");
        if ($yaAbiertas) {
            flash('aviso', 'Este auto ya tenía ' . count($yaAbiertas) . ' orden(es) abierta(s): #' . implode(', #', array_column($yaAbiertas, 'id')) . '.');
        }
        if ($passwordInicial) {
            flash('aviso', "Usuario creado para {$d['email']} con contraseña inicial: $passwordInicial (pasásela al cliente).");
        }
        redirigir('/taller/orden.php?id=' . $id);
    }
}

encabezado('Nueva orden de trabajo', 'nueva');
?>
<?php foreach ($errores as $err): ?><div class="alerta alerta-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post">
    <?= csrfCampo() ?>
    <div class="grilla-2">
        <div>
            <div class="tarjeta">
                <h2>1. Vehículo</h2>
                <label for="patente">Patente</label>
                <input id="patente" name="patente" value="<?= e($d['patente']) ?>" placeholder="Escribí la patente para buscar (ej.: AB123CD)" autocomplete="off" required>
                <div id="resultados-patente" class="resultados-patente" hidden></div>
                <div id="patente-elegida" class="alerta alerta-aviso" hidden></div>
                <p class="chico">Escribí parte de la patente y elegí el auto si ya vino antes: los demás datos se completan solos. Se puede abrir una orden nueva aunque el auto tenga otra abierta.</p>
                <div class="fila">
                    <div><label for="marca">Marca</label><input id="marca" name="marca" value="<?= e($d['marca']) ?>"></div>
                    <div><label for="modelo">Modelo</label><input id="modelo" name="modelo" value="<?= e($d['modelo']) ?>"></div>
                    <div><label for="anio">Año</label><input id="anio" name="anio" value="<?= e($d['anio']) ?>" inputmode="numeric"></div>
                </div>
            </div>
            <div class="tarjeta">
                <h2>2. Trabajo</h2>
                <label for="problema">¿Qué le pasa al auto? (lo que cuenta el cliente)</label>
                <textarea id="problema" name="problema" required><?= e($d['problema']) ?></textarea>
                <label for="fecha_estimada">Fecha estimada de entrega (opcional)</label>
                <input type="date" id="fecha_estimada" name="fecha_estimada" value="<?= e($d['fecha_estimada']) ?>">
            </div>
        </div>
        <div class="tarjeta">
            <h2>3. Cliente</h2>
            <p class="chico">Solo hace falta si el auto es nuevo en el taller.</p>
            <label for="cliente_id">Cliente existente</label>
            <select id="cliente_id" name="cliente_id">
                <option value="">— Cliente nuevo —</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (string)$c['id'] === $d['cliente_id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?> (<?= e($c['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <label for="nombre">Nombre (cliente nuevo)</label>
            <input id="nombre" name="nombre" value="<?= e($d['nombre']) ?>">
            <label for="email">Email (cliente nuevo)</label>
            <input type="email" id="email" name="email" value="<?= e($d['email']) ?>">
            <label for="telefono">Teléfono</label>
            <input id="telefono" name="telefono" value="<?= e($d['telefono']) ?>">
            <p><button class="boton" type="submit">Crear orden</button></p>
        </div>
    </div>
</form>

<script>
// Filtrador de patentes: busca mientras se escribe y completa los datos del auto elegido.
(function () {
    const campo = document.getElementById('patente');
    const lista = document.getElementById('resultados-patente');
    const elegida = document.getElementById('patente-elegida');
    const urlBuscar = <?= json_encode(url('/taller/buscar_patente.php')) ?>;
    let espera = null;

    function texto(t) { const s = document.createElement('span'); s.textContent = t; return s; }

    function mostrar(autos) {
        lista.innerHTML = '';
        if (!autos.length) {
            lista.appendChild(texto('No hay autos con esa patente: se carga como auto nuevo.'));
            lista.className = 'resultados-patente vacio';
            lista.hidden = false;
            return;
        }
        lista.className = 'resultados-patente';
        autos.forEach(function (a) {
            const b = document.createElement('button');
            b.type = 'button';
            b.appendChild(Object.assign(document.createElement('strong'), { textContent: a.patente }));
            b.appendChild(texto(' ' + a.marca + ' ' + a.modelo + (a.anio ? ' ' + a.anio : '') + ' · ' + a.cliente_nombre));
            if (Number(a.abiertas) > 0) {
                b.appendChild(Object.assign(document.createElement('span'), { className: 'estado estado-presupuestado', textContent: a.abiertas + ' abierta(s)' }));
            }
            b.addEventListener('click', function () { elegir(a); });
            lista.appendChild(b);
        });
        lista.hidden = false;
    }

    function elegir(a) {
        campo.value = a.patente;
        document.getElementById('marca').value = a.marca;
        document.getElementById('modelo').value = a.modelo;
        document.getElementById('anio').value = a.anio || '';
        document.getElementById('cliente_id').value = a.cliente_id;
        lista.hidden = true;
        elegida.textContent = 'Auto ya registrado de ' + a.cliente_nombre + '.'
            + (Number(a.abiertas) > 0 ? ' Tiene ' + a.abiertas + ' orden(es) abierta(s): igual podés crear otra.' : '');
        elegida.hidden = false;
        document.getElementById('problema').focus();
    }

    campo.addEventListener('input', function () {
        elegida.hidden = true;
        clearTimeout(espera);
        const q = campo.value.trim();
        if (q.length < 2) { lista.hidden = true; return; }
        espera = setTimeout(function () {
            fetch(urlBuscar + '?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(mostrar)
                .catch(function () { lista.hidden = true; });
        }, 200);
    });
})();
</script>
<?php pie(); ?>
