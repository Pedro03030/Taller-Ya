<?php
require __DIR__ . '/../src/app.php';

// Manda a cada usuario a su pantalla de inicio según el rol.
$u = usuarioActual();
if (!$u) {
    redirigir('/login.php');
}
redirigir(Usuarios::esPersonal($u['rol']) ? '/taller/panel.php' : '/cliente/inicio.php');
