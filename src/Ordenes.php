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

    /** Ítems del presupuesto, con el nombre del empleado que cargó cada uno. */
    public function items(int $ordenId): array
    {
        $st = $this->db->prepare(
            'SELECT i.*, u.nombre AS usuario_nombre FROM orden_items i
             LEFT JOIN usuarios u ON u.id = i.usuario_id
             WHERE i.orden_id = ? ORDER BY i.tipo DESC, i.id'
        );
        $st->execute([$ordenId]);
        return $st->fetchAll();
    }

    public function historial(int $ordenId, ?Paginador $pag = null): array
    {
        $st = $this->db->prepare(
            'SELECT h.*, u.nombre AS usuario_nombre FROM historial_estados h
             JOIN usuarios u ON u.id = h.usuario_id WHERE h.orden_id = ? ORDER BY h.fecha, h.id'
            . ($pag ? $pag->limiteSql() : '')
        );
        $st->execute([$ordenId]);
        return $st->fetchAll();
    }

    public function notificaciones(int $ordenId, ?Paginador $pag = null): array
    {
        $st = $this->db->prepare('SELECT * FROM notificaciones WHERE orden_id = ? ORDER BY fecha DESC, id DESC'
            . ($pag ? $pag->limiteSql() : ''));
        $st->execute([$ordenId]);
        return $st->fetchAll();
    }

    /** Cambios hechos al presupuesto después de mandarlo, del más nuevo al más viejo. */
    public function cambiosPresupuesto(int $ordenId, ?Paginador $pag = null): array
    {
        $st = $this->db->prepare(
            'SELECT c.*, u.nombre AS usuario_nombre FROM presupuesto_cambios c
             JOIN usuarios u ON u.id = c.usuario_id WHERE c.orden_id = ? ORDER BY c.fecha DESC, c.id DESC'
            . ($pag ? $pag->limiteSql() : '')
        );
        $st->execute([$ordenId]);
        return $st->fetchAll();
    }

    /** Cuenta filas de una tabla relacionada a la orden (para paginar). */
    public function contar(string $tabla, int $ordenId): int
    {
        if (!in_array($tabla, ['historial_estados', 'notificaciones', 'presupuesto_cambios'], true)) {
            throw new InvalidArgumentException('Tabla no permitida');
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM $tabla WHERE orden_id = ?");
        $st->execute([$ordenId]);
        return (int)$st->fetchColumn();
    }

    /** Órdenes del vehículo que siguen abiertas (no entregadas). */
    public function abiertasDelVehiculo(int $vehiculoId): array
    {
        $st = $this->db->prepare("SELECT id, estado FROM ordenes WHERE vehiculo_id = ? AND estado <> 'entregado' ORDER BY id");
        $st->execute([$vehiculoId]);
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

    /**
     * Crea una orden. Se puede crear aunque el vehículo ya tenga otra abierta
     * (por ejemplo, el cliente trae el auto por un problema distinto).
     * $porPersonal = false cuando la pide el cliente desde la web (no va al historial de empleados).
     */
    public function crear(int $vehiculoId, string $problema, ?string $fechaEstimada, int $usuarioId, bool $porPersonal = true): int
    {
        $ahora = date('Y-m-d H:i:s');
        $this->db->beginTransaction();
        $st = $this->db->prepare(
            'INSERT INTO ordenes (vehiculo_id, problema, estado, codigo_seguimiento, fecha_ingreso, fecha_estimada)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$vehiculoId, $problema, 'ingresado', self::generarCodigo(), $ahora, $fechaEstimada ?: null]);
        $id = (int)$this->db->lastInsertId();
        $this->registrarHistorial($id, null, 'ingresado', $usuarioId, $porPersonal ? null : 'Pedida por el cliente desde la web');
        if ($porPersonal) {
            $this->registrarAccion($usuarioId, 'Creó una orden', mb_substr($problema, 0, 200), $id);
        }
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
        if ($nuevo === 'entregado') {
            // Al entregar queda guardado cuánto se cobró (lo usa el apartado de Dinero).
            $this->db->prepare('UPDATE ordenes SET estado = ?, fecha_entrega = ?, monto_cobrado = ? WHERE id = ?')
                ->execute([$nuevo, date('Y-m-d H:i:s'), $this->montoACobrar($ordenId), $ordenId]);
        } else {
            $this->db->prepare('UPDATE ordenes SET estado = ? WHERE id = ?')->execute([$nuevo, $ordenId]);
        }
        $this->registrarHistorial($ordenId, $orden['estado'], $nuevo, $usuarioId, $comentario);
        if (Usuarios::esPersonal($rol)) {
            $this->registrarAccion($usuarioId, 'Cambió el estado',
                Estados::nombre($orden['estado']) . ' → ' . Estados::nombre($nuevo) . ($comentario ? " ($comentario)" : ''), $ordenId);
        }
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

    /**
     * Cuánto se le cobra al cliente al entregar: el total del presupuesto,
     * o 0 si la última respuesta del cliente fue rechazarlo (se devuelve sin reparar).
     */
    public function montoACobrar(int $ordenId): float
    {
        $st = $this->db->prepare(
            "SELECT estado_nuevo FROM historial_estados
             WHERE orden_id = ? AND estado_nuevo IN ('aprobado', 'rechazado')
             ORDER BY fecha DESC, id DESC LIMIT 1"
        );
        $st->execute([$ordenId]);
        if ($st->fetchColumn() === 'rechazado') {
            return 0.0;
        }
        return Presupuesto::total($this->items($ordenId));
    }

    /**
     * Cambia el presupuesto de una orden de una sola vez.
     *
     * $items: cómo tiene que quedar el presupuesto. Cada ítem: ['id' => ?int (null si es nuevo),
     *         'tipo', 'descripcion', 'cantidad', 'precio_unitario']. Los ítems que no vienen se quitan.
     * $empleadoId: el empleado que se identificó (con email y contraseña) para hacer el cambio.
     * $nota: texto extra para el historial de empleados (por ejemplo, de quién era la sesión abierta).
     *
     * Si el presupuesto ya se le había mandado al cliente, la orden vuelve a "Presupuesto listo",
     * queda registrado el cambio y se le manda un email para que lo apruebe de nuevo.
     *
     * Devuelve ['ok' => bool, 'error' => ?string, 'aviso' => ?string].
     */
    public function modificarPresupuesto(int $ordenId, array $items, int $empleadoId, string $motivo, ?string $nota = null): array
    {
        $fallo = fn(string $e) => ['ok' => false, 'error' => $e, 'aviso' => null];
        $orden = $this->buscar($ordenId);
        if (!$orden) {
            return $fallo('La orden no existe.');
        }
        if (!Estados::presupuestoModificable($orden['estado'])) {
            return $fallo('El presupuesto ya no se puede cambiar: la orden está "' . Estados::nombre($orden['estado']) . '".');
        }
        $enviado = Estados::presupuestoEnviado($orden['estado']);
        $motivo = trim($motivo);
        if ($enviado && ($error = Validador::obligatorio($motivo, 'Motivo del cambio', 255))) {
            return $fallo($error);
        }
        if (!$items) {
            return $fallo('El presupuesto tiene que tener al menos un ítem.');
        }
        foreach ($items as $n => $it) {
            $errores = Validador::errores([
                in_array($it['tipo'] ?? '', ['repuesto', 'mano_obra'], true) ? null : 'Tipo de ítem inválido.',
                Validador::obligatorio((string)($it['descripcion'] ?? ''), 'Descripción', 150),
                Validador::numeroPositivo($it['cantidad'] ?? '', 'Cantidad'),
                Validador::numeroPositivo($it['precio_unitario'] ?? '', 'Precio'),
            ]);
            if ($errores) {
                return $fallo('Renglón ' . ($n + 1) . ': ' . $errores[0]);
            }
        }

        // Comparar lo que había con lo nuevo para saber qué cambió.
        $actuales = [];
        foreach ($this->items($ordenId) as $it) {
            $actuales[(int)$it['id']] = $it;
        }
        $totalAnterior = Presupuesto::total($actuales);
        $totalNuevo = Presupuesto::total($items);
        $detalle = [];
        $quedan = [];
        $cambiados = [];
        foreach ($items as $it) {
            $idItem = isset($it['id']) && $it['id'] !== '' ? (int)$it['id'] : null;
            if ($idItem !== null && !isset($actuales[$idItem])) {
                return $fallo('Uno de los ítems no pertenece a esta orden.');
            }
            if ($idItem === null) {
                $detalle[] = 'Agregó: ' . $this->describirItem($it);
                continue;
            }
            $quedan[] = $idItem;
            $antes = $actuales[$idItem];
            if ($antes['tipo'] !== $it['tipo'] || trim($antes['descripcion']) !== trim($it['descripcion'])
                || (float)$antes['cantidad'] !== (float)$it['cantidad'] || (float)$antes['precio_unitario'] !== (float)$it['precio_unitario']) {
                $detalle[] = 'Cambió: ' . $this->describirItem($antes) . ' → ' . $this->describirItem($it);
                $cambiados[] = $idItem;
            }
        }
        foreach ($actuales as $idItem => $antes) {
            if (!in_array($idItem, $quedan, true)) {
                $detalle[] = 'Quitó: ' . $this->describirItem($antes);
            }
        }
        if (!$detalle) {
            return $fallo('No hiciste ningún cambio en el presupuesto.');
        }

        $this->db->beginTransaction();
        foreach ($actuales as $idItem => $antes) {
            if (!in_array($idItem, $quedan, true)) {
                $this->db->prepare('DELETE FROM orden_items WHERE id = ? AND orden_id = ?')->execute([$idItem, $ordenId]);
            }
        }
        $actualizar = $this->db->prepare('UPDATE orden_items SET tipo = ?, descripcion = ?, cantidad = ?, precio_unitario = ?, usuario_id = ? WHERE id = ? AND orden_id = ?');
        $insertar = $this->db->prepare('INSERT INTO orden_items (orden_id, tipo, descripcion, cantidad, precio_unitario, usuario_id) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($items as $it) {
            $idItem = isset($it['id']) && $it['id'] !== '' ? (int)$it['id'] : null;
            $valores = [$it['tipo'], trim($it['descripcion']), $it['cantidad'], $it['precio_unitario']];
            if ($idItem === null) {
                $insertar->execute(array_merge([$ordenId], $valores, [$empleadoId]));
            } elseif (in_array($idItem, $cambiados, true)) {
                // Solo se cambia el "cargado por" de los renglones que este empleado tocó.
                $actualizar->execute(array_merge($valores, [$empleadoId, $idItem, $ordenId]));
            }
        }

        $textoDetalle = implode("\n", $detalle);
        if ($enviado) {
            $this->db->prepare(
                'INSERT INTO presupuesto_cambios (orden_id, usuario_id, estado_anterior, total_anterior, total_nuevo, motivo, detalle, fecha)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$ordenId, $empleadoId, $orden['estado'], $totalAnterior, $totalNuevo, $motivo, $textoDetalle, date('Y-m-d H:i:s')]);

            if ($orden['estado'] !== 'presupuestado') {
                // Vuelve a esperar la aprobación del cliente.
                $this->db->prepare('UPDATE ordenes SET estado = ? WHERE id = ?')->execute(['presupuestado', $ordenId]);
                $this->registrarHistorial($ordenId, $orden['estado'], 'presupuestado', $empleadoId,
                    mb_substr('Presupuesto modificado: ' . $motivo, 0, 255));
            }
        }
        $this->registrarAccion($empleadoId, 'Modificó el presupuesto',
            Presupuesto::formatear($totalAnterior) . ' → ' . Presupuesto::formatear($totalNuevo)
            . ($motivo !== '' ? ". Motivo: $motivo" : '') . ($nota ? ". $nota" : ''), $ordenId);
        $this->db->commit();

        $aviso = null;
        if ($enviado) {
            $asunto = Estados::asuntoParaCliente('presupuesto_modificado', $orden['patente']);
            $texto = Estados::mensajeParaCliente('presupuesto_modificado', $orden['patente'], $totalNuevo, $orden['codigo_seguimiento']);
            if ($this->notificar($orden, $asunto, $texto) !== 'enviada') {
                $aviso = 'El presupuesto se cambió, pero no se pudo mandar el email al cliente. Podés reintentar desde el detalle o avisarle por teléfono.';
            }
        }
        return ['ok' => true, 'error' => null, 'aviso' => $aviso];
    }

    private function describirItem(array $it): string
    {
        return trim($it['descripcion']) . ' x' . (float)$it['cantidad'] . ' ' . Presupuesto::formatear((float)$it['precio_unitario']);
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

    /**
     * Historial de empleados: guarda qué hizo cada persona del taller.
     * Lo ven solo el admin y los empleados (pantalla Historial).
     */
    public function registrarAccion(int $usuarioId, string $accion, ?string $detalle = null, ?int $ordenId = null): void
    {
        $this->db->prepare('INSERT INTO historial_empleados (usuario_id, orden_id, accion, detalle, fecha) VALUES (?, ?, ?, ?, ?)')
            ->execute([$usuarioId, $ordenId, mb_substr($accion, 0, 50), $detalle !== null ? mb_substr($detalle, 0, 255) : null, date('Y-m-d H:i:s')]);
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
