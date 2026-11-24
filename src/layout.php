<?php
/**
 * Encabezado y pie comunes a todas las pantallas.
 * El menú depende del rol: así todas las páginas se ven y navegan igual
 * (requisito obligatorio 4: respetar el diseño de la Entrega 2).
 */
function encabezado(string $titulo, string $activo = ''): void
{
    $u = usuarioActual();
    $menu = [];
    if ($u && Usuarios::esPersonal($u['rol'])) {
        $menu = [
            'panel'     => ['Panel', '/taller/panel.php'],
            'ordenes'   => ['Órdenes', '/taller/ordenes.php'],
            'nueva'     => ['Nueva orden', '/taller/orden_nueva.php'],
            'clientes'  => ['Clientes', '/taller/clientes.php'],
            'historial' => ['Historial', '/taller/historial.php'],
        ];
        if ($u['rol'] === 'admin') {
            $menu['dinero'] = ['Dinero', '/taller/dinero.php'];
            $menu['empleados'] = ['Empleados', '/taller/empleados.php'];
        }
    } elseif ($u && $u['rol'] === 'cliente') {
        $menu = [
            'inicio'   => ['Mis autos', '/cliente/inicio.php'],
            'nueva'    => ['Nueva orden', '/cliente/orden_nueva.php'],
        ];
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · TallerYa</title>
    <link rel="stylesheet" href="<?= url('/css/estilos.css') ?>">
</head>
<body>
<header class="barra">
    <a class="marca" href="<?= url('/index.php') ?>"><span class="marca-icono">🔧</span> TallerYa</a>
    <nav>
        <?php foreach ($menu as $clave => [$texto, $ruta]): ?>
            <a href="<?= url($ruta) ?>" class="<?= $clave === $activo ? 'activo' : '' ?>"><?= e($texto) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="usuario">
        <?php if ($u): ?>
            <span><?= e($u['nombre']) ?> <span class="rol"><?= e(Usuarios::nombreRol($u['rol'])) ?></span></span>
            <a href="<?= url('/logout.php') ?>" class="boton boton-chico boton-claro">Salir</a>
        <?php else: ?>
            <a href="<?= url('/seguimiento.php') ?>">Consultar mi auto</a>
            <a href="<?= url('/login.php') ?>" class="boton boton-chico boton-claro">Ingresar</a>
        <?php endif; ?>
    </div>
</header>
<main class="contenido">
    <h1><?= e($titulo) ?></h1>
    <?= mostrarFlash() ?>
<?php
}

function pie(): void
{
    ?>
</main>
<footer class="pie">TallerYa · Sistema de seguimiento de reparaciones</footer>
</body>
</html>
<?php
}
