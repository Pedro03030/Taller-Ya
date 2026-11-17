<?php
/**
 * Validación de datos que ingresa el usuario.
 * Cada método devuelve null si está bien, o un mensaje de error.
 */
class Validador
{
    /** Patentes argentinas: vieja (ABC123) o Mercosur (AB123CD). */
    public static function patente(string $patente): ?string
    {
        $p = self::normalizarPatente($patente);
        if (preg_match('/^[A-Z]{3}[0-9]{3}$/', $p) || preg_match('/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/', $p)) {
            return null;
        }
        return 'La patente no es válida (ejemplos: ABC123 o AB123CD).';
    }

    public static function normalizarPatente(string $patente): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($patente)));
    }

    public static function email(string $email): ?string
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) ? null : 'El email no es válido.';
    }

    public static function obligatorio(string $valor, string $campo, int $max = 255): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return "El campo \"$campo\" es obligatorio.";
        }
        if (mb_strlen($valor) > $max) {
            return "El campo \"$campo\" no puede tener más de $max caracteres.";
        }
        return null;
    }

    public static function numeroPositivo($valor, string $campo): ?string
    {
        if (!is_numeric($valor) || (float)$valor <= 0) {
            return "El campo \"$campo\" tiene que ser un número mayor a 0.";
        }
        return null;
    }

    public static function anio($valor): ?string
    {
        if ($valor === '' || $valor === null) {
            return null; // es opcional
        }
        $anioActual = (int)date('Y');
        if (!ctype_digit((string)$valor) || (int)$valor < 1950 || (int)$valor > $anioActual + 1) {
            return 'El año del vehículo no es válido.';
        }
        return null;
    }

    public static function fechaFutura(string $fecha, string $hoy): ?string
    {
        if ($fecha === '') {
            return null; // es opcional
        }
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            return 'La fecha no es válida.';
        }
        if ($fecha < $hoy) {
            return 'La fecha estimada no puede ser anterior a hoy.';
        }
        return null;
    }

    /** Junta los errores (saca los null). */
    public static function errores(array $resultados): array
    {
        return array_values(array_filter($resultados, fn($e) => $e !== null));
    }
}
