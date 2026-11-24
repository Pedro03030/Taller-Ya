<?php
require __DIR__ . '/../src/app.php';

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT * FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    $usuario = $st->fetch();

    // Mismo mensaje si falla el email o la contraseña: no damos pistas.
    if ($usuario && password_verify($password, $usuario['password_hash']) && !(int)$usuario['activo']) {
        $error = 'Tu usuario está dado de baja. Hablá con el dueño del taller.';
    } elseif ($usuario && password_verify($password, $usuario['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id' => (int)$usuario['id'],
            'nombre' => $usuario['nombre'],
            'rol' => $usuario['rol'],
        ];
        if (Usuarios::esPersonal($usuario['rol'])) {
            registrarAccion((int)$usuario['id'], 'Inició sesión');
        }
        redirigir('/index.php');
    } else {
        $error = 'Email o contraseña incorrectos.';
    }
}

/*
 * Cuentas de prueba para mostrar el sistema (demo en clase).
 * Se muestran solo si el usuario existe en la base.
 * Para ocultarlas, poner 'modo_demo' => false en config/config.php.
 */
global $CONFIG;
$cuentasDemo = [];
if ($CONFIG['modo_demo'] ?? true) {
    $candidatas = [
        ['taller@tallerya.com', 'taller123', 'Admin (dueño)', 'Todo el taller, más el dinero y los empleados'],
        ['empleado@tallerya.com', 'empleado123', 'Empleado: Carlos Mecánico', 'Órdenes, presupuestos e historial'],
        ['juan@mail.com', 'cliente123', 'Cliente: Juan Pérez', 'Tiene un presupuesto para aprobar'],
        ['martin.sosa@ejemplo.com', 'cliente123', 'Cliente: Martín Sosa', 'Presupuesto pendiente y una reparación anterior'],
        ['ana@mail.com', 'cliente123', 'Cliente: Ana Gómez', 'Tiene el auto en reparación'],
    ];
    $st = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
    foreach ($candidatas as $c) {
        $st->execute([$c[0]]);
        if ($st->fetchColumn() > 0) {
            $cuentasDemo[] = $c;
        }
    }
}

encabezado('Ingresar');
?>
<div class="tarjeta caja-centrada">
    <?php if ($error): ?><div class="alerta alerta-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrfCampo() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required>
        <p><button class="boton" type="submit">Ingresar</button></p>
    </form>
    <p class="chico">¿Solo querés saber cómo va tu auto? <a href="<?= url('/seguimiento.php') ?>">Consultalo con la patente y el código</a>.</p>
</div>

<?php if ($cuentasDemo): ?>
<div class="tarjeta caja-centrada">
    <h2>Cuentas de prueba</h2>
    <p class="chico">Entrá con un clic para ver el sistema desde cada tipo de usuario.</p>
    <?php foreach ($cuentasDemo as [$demoEmail, $demoPass, $demoNombre, $demoDetalle]): ?>
        <form method="post" class="cuenta-demo">
            <?= csrfCampo() ?>
            <input type="hidden" name="email" value="<?= e($demoEmail) ?>">
            <input type="hidden" name="password" value="<?= e($demoPass) ?>">
            <div>
                <strong><?= e($demoNombre) ?></strong><br>
                <span class="chico"><?= e($demoEmail) ?> · <?= e($demoPass) ?></span><br>
                <span class="chico"><?= e($demoDetalle) ?></span>
            </div>
            <button class="boton boton-chico boton-secundario" type="submit">Entrar</button>
        </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php pie(); ?>
