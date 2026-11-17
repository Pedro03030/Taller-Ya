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
    if ($usuario && password_verify($password, $usuario['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id' => (int)$usuario['id'],
            'nombre' => $usuario['nombre'],
            'rol' => $usuario['rol'],
        ];
        redirigir('/index.php');
    }
    $error = 'Email o contraseña incorrectos.';
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
<?php pie(); ?>
