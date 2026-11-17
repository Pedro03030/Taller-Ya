<?php
/**
 * Prueba del envío por Gmail SMTP: manda un email de prueba
 * y muestra el resultado o el error exacto. Sirve para revisar
 * la configuración en el hosting y para la demo en clase.
 */
require __DIR__ . '/../../src/app.php';
requiereRol('taller');

$para = '';
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $para = trim($_POST['para'] ?? '');
    $error = Validador::email($para);
    if ($error) {
        $resultado = ['ok' => false, 'error' => $error];
    } else {
        $inicio = microtime(true);
        $resultado = email()->enviar($para, 'Prueba', 'Prueba de TallerYa',
            "Si te llegó este email, el envío por Gmail funciona.\n\nEnviado el " . date('d/m/Y H:i') . '.');
        $resultado['segundos'] = round(microtime(true) - $inicio, 2);
    }
}

encabezado('Probar envío de email', 'panel');
?>
<div class="tarjeta caja-centrada">
    <?php if ($resultado && $resultado['ok']): ?>
        <div class="alerta alerta-ok">Gmail aceptó el email (tardó <?= $resultado['segundos'] ?> s). Revisá la bandeja de entrada y la carpeta de spam.</div>
    <?php elseif ($resultado): ?>
        <div class="alerta alerta-error"><?= e($resultado['error']) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= csrfCampo() ?>
        <label for="para">Mandar un email de prueba a</label>
        <input type="email" id="para" name="para" value="<?= e($para) ?>" required>
        <p><button class="boton" type="submit">Enviar prueba</button></p>
    </form>
    <p class="chico">Usa el Gmail y la contraseña de aplicación de <code>config/config.php</code>.</p>
</div>
<?php pie(); ?>
