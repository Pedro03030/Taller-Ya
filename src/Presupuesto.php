<?php
/** Cálculos del presupuesto de una orden. */
class Presupuesto
{
    /** @param array $items lista de ['cantidad' => .., 'precio_unitario' => .., 'tipo' => ..] */
    public static function total(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $total += (float)$item['cantidad'] * (float)$item['precio_unitario'];
        }
        return round($total, 2);
    }

    public static function totalPorTipo(array $items, string $tipo): float
    {
        return self::total(array_filter($items, fn($i) => $i['tipo'] === $tipo));
    }

    public static function formatear(float $monto): string
    {
        return '$' . number_format($monto, 0, ',', '.');
    }
}
