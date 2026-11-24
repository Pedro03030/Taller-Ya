<?php
/**
 * Apartado de dinero del admin.
 *
 * Se cuenta como plata movida lo que se cobra al ENTREGAR el auto
 * (columna ordenes.monto_cobrado, que se guarda en ese momento).
 * Si el cliente rechazó el presupuesto, se entrega sin reparar y el monto es 0.
 *
 * Las consultas usan SQL común a MySQL y SQLite (SUBSTR sobre la fecha)
 * para poder probarlas sin MySQL.
 */
class Dinero
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Total cobrado y cantidad de órdenes entregadas entre dos fechas (Y-m-d, ambas incluidas).
     * Sin fechas = desde siempre.
     */
    public function resumen(?string $desde = null, ?string $hasta = null): array
    {
        [$where, $params] = $this->rango($desde, $hasta);
        $st = $this->db->prepare(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(monto_cobrado), 0) AS total
             FROM ordenes WHERE estado = 'entregado' $where"
        );
        $st->execute($params);
        $r = $st->fetch();
        return ['cantidad' => (int)$r['cantidad'], 'total' => (float)$r['total']];
    }

    /** Totales de cada día de un mes ('2026-11'), del día más nuevo al más viejo. */
    public function porDia(string $mes, ?Paginador $pag = null): array
    {
        $st = $this->db->prepare(
            "SELECT SUBSTR(fecha_entrega, 1, 10) AS dia, COUNT(*) AS cantidad, COALESCE(SUM(monto_cobrado), 0) AS total
             FROM ordenes WHERE estado = 'entregado' AND SUBSTR(fecha_entrega, 1, 7) = ?
             GROUP BY SUBSTR(fecha_entrega, 1, 10) ORDER BY dia DESC" . ($pag ? $pag->limiteSql() : '')
        );
        $st->execute([$mes]);
        return $st->fetchAll();
    }

    public function contarDias(string $mes): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(DISTINCT SUBSTR(fecha_entrega, 1, 10)) FROM ordenes
             WHERE estado = 'entregado' AND SUBSTR(fecha_entrega, 1, 7) = ?"
        );
        $st->execute([$mes]);
        return (int)$st->fetchColumn();
    }

    /** Totales de cada mes, del más nuevo al más viejo. */
    public function porMes(?Paginador $pag = null): array
    {
        return $this->db->query(
            "SELECT SUBSTR(fecha_entrega, 1, 7) AS mes, COUNT(*) AS cantidad, COALESCE(SUM(monto_cobrado), 0) AS total
             FROM ordenes WHERE estado = 'entregado'
             GROUP BY SUBSTR(fecha_entrega, 1, 7) ORDER BY mes DESC" . ($pag ? $pag->limiteSql() : '')
        )->fetchAll();
    }

    public function contarMeses(): int
    {
        return (int)$this->db->query(
            "SELECT COUNT(DISTINCT SUBSTR(fecha_entrega, 1, 7)) FROM ordenes WHERE estado = 'entregado'"
        )->fetchColumn();
    }

    /** Órdenes entregadas en un rango, con vehículo y cliente. */
    public function entregadas(?string $desde, ?string $hasta, ?Paginador $pag = null): array
    {
        [$where, $params] = $this->rango($desde, $hasta, 'o.');
        $st = $this->db->prepare(
            "SELECT o.id, o.fecha_entrega, o.monto_cobrado, o.problema, v.patente, v.marca, v.modelo, u.nombre AS cliente_nombre
             FROM ordenes o JOIN vehiculos v ON v.id = o.vehiculo_id JOIN usuarios u ON u.id = v.cliente_id
             WHERE o.estado = 'entregado' $where ORDER BY o.fecha_entrega DESC, o.id DESC" . ($pag ? $pag->limiteSql() : '')
        );
        $st->execute($params);
        return $st->fetchAll();
    }

    /**
     * Plata que todavía no entró: órdenes con el presupuesto aprobado que aún no se entregaron
     * (aprobado, en reparación o listo para retirar, si el cliente no lo rechazó).
     */
    public function pendienteDeCobro(Ordenes $ordenes): array
    {
        $ids = $this->db->query("SELECT id FROM ordenes WHERE estado IN ('aprobado', 'en_reparacion', 'listo')")->fetchAll(PDO::FETCH_COLUMN);
        $total = 0.0;
        $cantidad = 0;
        foreach ($ids as $id) {
            $monto = $ordenes->montoACobrar((int)$id);
            if ($monto > 0) {
                $total += $monto;
                $cantidad++;
            }
        }
        return ['cantidad' => $cantidad, 'total' => $total];
    }

    /** Arma el WHERE del rango de fechas (el "hasta" incluye todo ese día). */
    private function rango(?string $desde, ?string $hasta, string $prefijo = ''): array
    {
        $where = '';
        $params = [];
        if ($desde !== null) {
            $where .= " AND {$prefijo}fecha_entrega >= ?";
            $params[] = $desde . ' 00:00:00';
        }
        if ($hasta !== null) {
            $where .= " AND {$prefijo}fecha_entrega <= ?";
            $params[] = $hasta . ' 23:59:59';
        }
        return [$where, $params];
    }

    /** "2026-11" -> "Noviembre 2026" */
    public static function nombreMes(string $mes): string
    {
        $nombres = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        [$anio, $m] = explode('-', $mes) + [null, null];
        return ($nombres[(int)$m] ?? $mes) . ' ' . $anio;
    }
}
