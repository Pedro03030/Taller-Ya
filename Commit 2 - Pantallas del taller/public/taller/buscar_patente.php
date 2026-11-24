<?php
/**
 * Filtrador de patentes para "Nueva orden": devuelve en JSON los vehículos
 * cuya patente contiene lo que se va escribiendo. Lo usa el JavaScript de orden_nueva.php.
 */
require __DIR__ . '/../../src/app.php';
requierePersonal();

$q = Validador::normalizarPatente($_GET['q'] ?? '');
$resultado = [];
if (strlen($q) >= 2) {
    $st = db()->prepare(
        "SELECT v.id, v.patente, v.marca, v.modelo, v.anio, v.cliente_id, u.nombre AS cliente_nombre,
                (SELECT COUNT(*) FROM ordenes o WHERE o.vehiculo_id = v.id AND o.estado <> 'entregado') AS abiertas
         FROM vehiculos v JOIN usuarios u ON u.id = v.cliente_id
         WHERE v.patente LIKE ? ORDER BY v.patente LIMIT 8"
    );
    $st->execute(['%' . $q . '%']);
    $resultado = $st->fetchAll();
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resultado);
