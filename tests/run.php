<?php
/**
 * Corredor de pruebas mínimo (no necesita instalar nada).
 * Uso:  php tests/run.php
 * Cada archivo *Test.php define funciones que empiezan con "test_".
 */
require __DIR__ . '/../src/Estados.php';
require __DIR__ . '/../src/Validador.php';
require __DIR__ . '/../src/Presupuesto.php';
require __DIR__ . '/../src/Email.php';
require __DIR__ . '/../src/Ordenes.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

class FalloPrueba extends Exception {}

function comprobar(bool $condicion, string $mensaje = 'La condición no se cumplió'): void
{
    if (!$condicion) throw new FalloPrueba($mensaje);
}

function igual($esperado, $obtenido, string $mensaje = ''): void
{
    if ($esperado !== $obtenido) {
        throw new FalloPrueba(($mensaje ? "$mensaje: " : '') . 'se esperaba ' . var_export($esperado, true) . ' y se obtuvo ' . var_export($obtenido, true));
    }
}

$antes = get_defined_functions()['user'];
foreach (glob(__DIR__ . '/*Test.php') as $archivo) {
    require $archivo;
}
$pruebas = array_filter(array_diff(get_defined_functions()['user'], $antes), fn($f) => str_starts_with($f, 'test_'));

$ok = 0;
$fallas = 0;
foreach ($pruebas as $prueba) {
    try {
        $prueba();
        echo "  ✔ $prueba\n";
        $ok++;
    } catch (Throwable $e) {
        echo "  ✘ $prueba\n      " . $e->getMessage() . "\n";
        $fallas++;
    }
}
echo "\n$ok pruebas OK, $fallas con fallas.\n";
exit($fallas > 0 ? 1 : 0);
