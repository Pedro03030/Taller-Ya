<?php
/**
 * Reglas de negocio de los estados de una orden de trabajo (Menú B).
 *
 *  ingresado -> en_diagnostico -> presupuestado -> aprobado -> en_reparacion -> listo -> entregado
 *                                              \-> rechazado -> listo (se devuelve sin reparar)
 *
 * Esta clase no toca la base de datos: así se puede probar sola (ver tests/).
 */
class Estados
{
    public const NOMBRES = [
        'ingresado'      => 'Ingresado',
        'en_diagnostico' => 'En diagnóstico',
        'presupuestado'  => 'Presupuesto listo',
        'aprobado'       => 'Presupuesto aprobado',
        'rechazado'      => 'Presupuesto rechazado',
        'en_reparacion'  => 'En reparación',
        'listo'          => 'Listo para retirar',
        'entregado'      => 'Entregado',
    ];

    /** A qué estados se puede pasar desde cada estado. */
    private const TRANSICIONES = [
        'ingresado'      => ['en_diagnostico'],
        'en_diagnostico' => ['presupuestado'],
        'presupuestado'  => ['aprobado', 'rechazado'],
        'aprobado'       => ['en_reparacion'],
        'rechazado'      => ['listo'],
        'en_reparacion'  => ['listo'],
        'listo'          => ['entregado'],
        'entregado'      => [],
    ];

    /** Estados en los que se le avisa al cliente por email. */
    private const AVISAR_AL_CLIENTE = ['presupuestado', 'listo'];

    /** Estados en los que el auto todavía está "en el taller". */
    private const ABIERTOS = ['ingresado', 'en_diagnostico', 'presupuestado', 'aprobado', 'en_reparacion'];

    public static function existe(string $estado): bool
    {
        return isset(self::NOMBRES[$estado]);
    }

    public static function nombre(string $estado): string
    {
        return self::NOMBRES[$estado] ?? $estado;
    }

    public static function siguientes(string $estado): array
    {
        return self::TRANSICIONES[$estado] ?? [];
    }

    /**
     * ¿Puede este rol pasar la orden de $desde a $hacia?
     * - El cliente solo puede aprobar o rechazar un presupuesto.
     * - El taller puede hacer cualquier transición válida (por ejemplo, si el
     *   cliente aprueba por teléfono).
     * - Para presupuestar, la orden tiene que tener al menos un ítem.
     */
    public static function puedeCambiar(string $desde, string $hacia, string $rol, int $cantidadItems = 1): bool
    {
        if (!in_array($hacia, self::siguientes($desde), true)) {
            return false;
        }
        if ($hacia === 'presupuestado' && $cantidadItems === 0) {
            return false;
        }
        if ($rol === 'cliente') {
            return $desde === 'presupuestado' && in_array($hacia, ['aprobado', 'rechazado'], true);
        }
        return $rol === 'taller';
    }

    public static function hayQueAvisar(string $estado): bool
    {
        return in_array($estado, self::AVISAR_AL_CLIENTE, true);
    }

    public static function asuntoParaCliente(string $estado, string $patente): string
    {
        switch ($estado) {
            case 'presupuestado':
                return "Presupuesto listo para tu auto $patente";
            case 'listo':
                return "Tu auto $patente está listo para retirar";
            default:
                return "Novedades de tu auto $patente";
        }
    }

    public static function mensajeParaCliente(string $estado, string $patente, float $total = 0, string $codigo = ''): string
    {
        switch ($estado) {
            case 'presupuestado':
                $texto = "Hola. El presupuesto de tu auto $patente está listo: $" . number_format($total, 0, ',', '.')
                    . ".\nIngresá a TallerYa con tu usuario para aprobarlo o rechazarlo. No empezamos a trabajar sin tu aprobación.";
                break;
            case 'listo':
                $texto = "Hola. Tu auto $patente está listo para retirar.";
                break;
            default:
                $texto = "Hola. Tu auto $patente pasó a: " . self::nombre($estado) . '.';
        }
        if ($codigo !== '') {
            $texto .= "\n\nCódigo de seguimiento: $codigo (lo podés consultar sin usuario con la patente y este código).";
        }
        return $texto . "\n\nTallerYa";
    }

    /**
     * Alerta automática: una orden está demorada si sigue en el taller
     * y ya pasó la fecha estimada.
     */
    public static function estaDemorada(string $estado, ?string $fechaEstimada, string $hoy): bool
    {
        if ($fechaEstimada === null || $fechaEstimada === '') {
            return false;
        }
        return in_array($estado, self::ABIERTOS, true) && $fechaEstimada < $hoy;
    }

    public static function estaAbierta(string $estado): bool
    {
        return in_array($estado, self::ABIERTOS, true);
    }
}
