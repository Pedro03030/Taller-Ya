<?php
/**
 * Operaciones sobre órdenes que tocan la base de datos.
 */
class Ordenes
{
    private PDO $db;
    private Email $email;

    public function __construct(PDO $db, Email $email)
    {
        $this->db = $db;
        $this->email = $email;
    }

    public function buscar(int $id): ?array
    {
        $st = $this->db->prepare(
            'SELECT o.*, v.patente, v.marca, v.modelo, v.anio, v.cliente_id,
                    u.nombre AS cliente_nombre, u.email AS cliente_email, u.telefono AS cliente_telefono
             FROM ordenes o
             JOIN vehiculos v ON v.id = o.vehiculo_id
             JOIN usuarios u ON u.id = v.cliente_id
             WHERE o.id = ?'
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function items(int $ordenId): array
    {
        $st = $this->db->prepare('SELECT * FROM orden_items WHERE orden_id = ? ORDER BY tipo DESC, id');
        $st->execute([$ordenId]);
        return $st->fetchAll();
    }

    public function historial(int $ordenId): array
    {
        $st = $this->db->prepare(
            'SELECT h.*, u.nombre AS usuario_nombre FROM historial_estados h
             JOIN usuarios u ON u.id = h.usuario_id WHERE h.orden_id = ? ORDER BY h.fecha, h.id'
        );
        $st->execute([$ordenId]);
        return $st->fetchAll();
    }

    public function notificaciones(int $ordenId): array
    {
        $st = $this->db->prepare('SELECT * FROM notificaciones WHERE orden_id = ? ORDER BY fecha DESC, id DESC');
        $st->execute([$ordenId]);
        return $st->fetchAll();
    }

    /** Genera un código corto sin letras que se confunden (0/O, 1/I). */
    public static function generarCodigo(): string
    {
        $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = '';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= $letras[random_int(0, strlen($letras) - 1)];
        }
        return $codigo;
    }

    public function crear(int $vehiculoId, string $problema, ?string $fechaEstimada, int $usuarioId): int
    {
        $ahora = date('Y-m-d H:i:s');
        $this->db->beginTransaction();
        $st = $this->db->prepare(
            'INSERT INTO ordenes (vehiculo_id, problema, estado, codigo_seguimiento, fecha_ingreso, fecha_estimada)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$vehiculoId, $problema, 'ingresado', self::generarCodigo(), $ahora, $fechaEstimada ?: null]);
        $id = (int)$this->db->lastInsertId();
        $this->registrarHistorial($id, null, 'ingresado', $usuarioId, null);
        $this->db->commit();
        return $id;
    }

    /**
     * Cambia el estado respetando las reglas de Estados.
     * Devuelve ['ok' => bool, 'error' => ?string, 'aviso' => ?string]
     * 'aviso' informa si la notificación no se pudo mandar (pero el cambio sí se hizo).
     */
    public function cambiarEstado(int $ordenId, string $nuevo, int $usuarioId, string $rol, ?string $comentario = null): array
    {
        $orden = $this->buscar($ordenId);
        if (!$orden) {
            return ['ok' => false, 'error' => 'La orden no existe.', 'aviso' => null];
        }
        if ($rol === 'cliente' && (int)$orden['cliente_id'] !== $usuarioId) {
            return ['ok' => false, 'error' => 'No tenés permiso sobre esta orden.', 'aviso' => null];
        }
        $items = $this->items($ordenId);
        if (!Estados::puedeCambiar($orden['estado'], $nuevo, $rol, count($items))) {
            $error = ($nuevo === 'presupuestado' && count($items) === 0)
                ? 'Cargá al menos un ítem en el presupuesto antes de enviarlo.'
                : 'No se puede pasar de "' . Estados::nombre($orden['estado']) . '" a "' . Estados::nombre($nuevo) . '".';
            return ['ok' => false, 'error' => $error, 'aviso' => null];
        }

        $this->db->beginTransaction();
        $sql = 'UPDATE ordenes SET estado = ?' . ($nuevo === 'entregado' ? ', fecha_entrega = ?' : '') . ' WHERE id = ?';
        $params = $nuevo === 'entregado' ? [$nuevo, date('Y-m-d H:i:s'), $ordenId] : [$nuevo, $ordenId];
        $this->db->prepare($sql)->execute($params);
        $this->registrarHistorial($ordenId, $orden['estado'], $nuevo, $usuarioId, $comentario);
        $this->db->commit();

        $aviso = null;
        if (Estados::hayQueAvisar($nuevo)) {
            $asunto = Estados::asuntoParaCliente($nuevo, $orden['patente']);
            $texto = Estados::mensajeParaCliente($nuevo, $orden['patente'], Presupuesto::total($items), $orden['codigo_seguimiento']);
            if ($this->notificar($orden, $asunto, $texto) !== 'enviada') {
                $aviso = 'El estado se cambió, pero no se pudo mandar el email al cliente. Podés reintentar desde el detalle o avisarle por teléfono.';
            }
        }
        return ['ok' => true, 'error' => null, 'aviso' => $aviso];
    }

    /** Envía el email y registra el resultado. Devuelve 'enviada' o 'fallida'. */
    public function notificar(array $orden, string $asunto, string $texto, ?int $notificacionId = null): string
    {
        $r = $this->email->enviar($orden['cliente_email'], $orden['cliente_nombre'], $asunto, $texto);
        $estado = $r['ok'] ? 'enviada' : 'fallida';
        $this->guardarNotificacion((int)$orden['id'], $orden['cliente_email'], $asunto, $texto, $estado, $r['error'], $notificacionId);
        return $estado;
    }

    /** Reintenta una notificación que falló. */
    public function reintentar(int $notificacionId): string
    {
        $st = $this->db->prepare('SELECT * FROM notificaciones WHERE id = ?');
        $st->execute([$notificacionId]);
        $n = $st->fetch();
        if (!$n) {
            return 'fallida';
        }
        $orden = $this->buscar((int)$n['orden_id']);
        // Se actualiza el mismo registro en vez de crear otro.
        return $this->notificar($orden, $n['asunto'], $n['mensaje'], (int)$n['id']);
    }

    private function guardarNotificacion(int $ordenId, string $para, string $asunto, string $mensaje, string $estado, ?string $error, ?int $id = null): void
    {
        $error = $error !== null ? mb_substr($error, 0, 255) : null;
        if ($id !== null) {
            $st = $this->db->prepare('UPDATE notificaciones SET destinatario = ?, estado = ?, error = ?, fecha = ? WHERE id = ?');
            $st->execute([$para, $estado, $error, date('Y-m-d H:i:s'), $id]);
            return;
        }
        $st = $this->db->prepare('INSERT INTO notificaciones (orden_id, destinatario, asunto, mensaje, estado, error, fecha) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$ordenId, $para, $asunto, $mensaje, $estado, $error, date('Y-m-d H:i:s')]);
    }

    private function registrarHistorial(int $ordenId, ?string $anterior, string $nuevo, int $usuarioId, ?string $comentario): void
    {
        $st = $this->db->prepare(
            'INSERT INTO historial_estados (orden_id, estado_anterior, estado_nuevo, usuario_id, comentario, fecha)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$ordenId, $anterior, $nuevo, $usuarioId, $comentario, date('Y-m-d H:i:s')]);
    }
}
