<?php
/** Empleados del taller (solo el admin): alta, baja y reactivación. */
require __DIR__ . '/../../src/app.php';
$admin = requiereAdmin();

$errores = [];
$d = array_merge(['nombre' => '', 'email' => '', 'telefono' => '', 'rol' => 'empleado'], array_map(fn($v) => is_string($v) ? trim($v) : '', $_POST));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $errores = usuarios()->crearEmpleado($d['nombre'], $d['email'], $d['telefono'], $_POST['password'] ?? '', $d['rol']);
        if (!$errores) {
            registrarAccion($admin['id'], 'Dio de alta a un empleado', $d['nombre'] . ' (' . $d['email'] . ', ' . Usuarios::nombreRol($d['rol']) . ')');
            flash('ok', "Empleado {$d['nombre']} creado. Ya puede ingresar con su email y la contraseña que cargaste.");
            redirigir('/taller/empleados.php');
        }
    } elseif ($accion === 'activo') {
        $emp = usuarios()->buscar((int)$_POST['usuario_id']);
        if ($emp && Usuarios::esPersonal($emp['rol']) && (int)$emp['id'] !== $admin['id']) {
            $activar = !(int)$emp['activo'];
            usuarios()->cambiarActivo((int)$emp['id'], $activar);
            registrarAccion($admin['id'], $activar ? 'Reactivó a un empleado' : 'Dio de baja a un empleado', $emp['nombre']);
            flash('ok', $emp['nombre'] . ($activar ? ' puede volver a ingresar.' : ' ya no puede ingresar.'));
        }
        redirigir('/taller/empleados.php');
    }
}

$total = (int)db()->query("SELECT COUNT(*) FROM usuarios WHERE rol IN ('admin', 'empleado')")->fetchColumn();
$pag = new Paginador($total);
$lista = db()->query(
    "SELECT u.*, (SELECT MAX(h.fecha) FROM historial_empleados h WHERE h.usuario_id = u.id) AS ultima_accion
     FROM usuarios u WHERE u.rol IN ('admin', 'empleado') ORDER BY u.activo DESC, u.nombre" . $pag->limiteSql()
)->fetchAll();

encabezado('Empleados', 'empleados');
?>
<?php foreach ($errores as $err): ?><div class="alerta alerta-error"><?= e($err) ?></div><?php endforeach; ?>
<div class="grilla-2">
    <div class="tarjeta">
        <h2>Personal del taller</h2>
        <table>
            <tr><th>Nombre</th><th>Rol</th><th>Última actividad</th><th></th></tr>
            <?php foreach ($lista as $u): ?>
                <tr class="<?= (int)$u['activo'] ? '' : 'inactivo' ?>">
                    <td><?= e($u['nombre']) ?><br><span class="chico"><?= e($u['email']) ?></span></td>
                    <td><?= e(Usuarios::nombreRol($u['rol'])) ?><?= (int)$u['activo'] ? '' : '<br><span class="chico">De baja</span>' ?></td>
                    <td class="chico"><?= fecha($u['ultima_accion'], true) ?><br>
                        <a href="<?= url('/taller/historial.php?usuario=' . $u['id']) ?>">Ver historial</a></td>
                    <td>
                        <?php if ((int)$u['id'] !== $admin['id']): ?>
                        <form method="post"><?= csrfCampo() ?>
                            <input type="hidden" name="accion" value="activo">
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <?php if ((int)$u['activo']): ?>
                                <button class="boton boton-chico boton-peligro" type="submit" onclick="return confirm('¿Dar de baja a <?= e($u['nombre']) ?>? No va a poder ingresar.')">Dar de baja</button>
                            <?php else: ?>
                                <button class="boton boton-chico boton-secundario" type="submit">Reactivar</button>
                            <?php endif; ?>
                        </form>
                        <?php else: ?><span class="chico">Vos</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?= $pag->links() ?>
    </div>

    <div class="tarjeta">
        <h2>Nuevo empleado</h2>
        <p class="chico">Cada persona del taller tiene su usuario: así queda registrado quién hizo cada cosa.</p>
        <form method="post">
            <?= csrfCampo() ?>
            <input type="hidden" name="accion" value="crear">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" value="<?= e($d['nombre']) ?>" required>
            <label for="email">Email (con esto ingresa)</label>
            <input type="email" id="email" name="email" value="<?= e($d['email']) ?>" required>
            <label for="telefono">Teléfono (opcional)</label>
            <input id="telefono" name="telefono" value="<?= e($d['telefono']) ?>">
            <label for="password">Contraseña inicial (mínimo 6)</label>
            <input type="password" id="password" name="password" minlength="6" autocomplete="new-password" required>
            <label for="rol">Rol</label>
            <select id="rol" name="rol">
                <option value="empleado" <?= $d['rol'] === 'empleado' ? 'selected' : '' ?>>Empleado</option>
                <option value="admin" <?= $d['rol'] === 'admin' ? 'selected' : '' ?>>Admin (ve el dinero y los empleados)</option>
            </select>
            <p><button class="boton" type="submit">Crear empleado</button></p>
        </form>
    </div>
</div>
<?php pie(); ?>
