<?php
/**
 * Paginación de tablas: calcula qué página mostrar y arma los links.
 *
 * Uso con SQL:
 *     $pag = new Paginador($totalDeFilas);           // lee ?pagina= de la URL
 *     $sql .= $pag->limiteSql();                      // " LIMIT 10 OFFSET 20"
 *     echo $pag->links();
 *
 * Uso con un array ya cargado:
 *     $pag = new Paginador(count($lista));
 *     foreach ($pag->recortar($lista) as $fila) ...
 *
 * Si en la misma página hay dos tablas, cada una usa su propio parámetro
 * (por ejemplo 'pag_historial' y 'pag_avisos') para que no se pisen.
 */
class Paginador
{
    public const POR_PAGINA = 10;

    public int $total;
    public int $porPagina;
    public int $pagina;
    public int $paginas;
    public int $offset;
    public string $parametro;
    private string $ancla;

    public function __construct(int $total, int $porPagina = self::POR_PAGINA, string $parametro = 'pagina', ?int $pagina = null, string $ancla = '')
    {
        $this->total = max(0, $total);
        $this->porPagina = max(1, $porPagina);
        $this->parametro = $parametro;
        $this->ancla = $ancla;
        $this->paginas = max(1, (int)ceil($this->total / $this->porPagina));

        if ($pagina === null) {
            $pagina = (int)($_GET[$parametro] ?? 1);
        }
        // Si piden una página que no existe se muestra la más cercana.
        $this->pagina = min(max(1, $pagina), $this->paginas);
        $this->offset = ($this->pagina - 1) * $this->porPagina;
    }

    /** " LIMIT x OFFSET y" para agregar al final de una consulta (son enteros, no hay inyección). */
    public function limiteSql(): string
    {
        return ' LIMIT ' . $this->porPagina . ' OFFSET ' . $this->offset;
    }

    /** Devuelve solo las filas de la página actual. */
    public function recortar(array $filas): array
    {
        return array_slice(array_values($filas), $this->offset, $this->porPagina);
    }

    public function desde(): int
    {
        return $this->total === 0 ? 0 : $this->offset + 1;
    }

    public function hasta(): int
    {
        return min($this->offset + $this->porPagina, $this->total);
    }

    /** Números de página a mostrar: siempre la primera, la última y dos alrededor de la actual. */
    public function numeros(): array
    {
        $nums = [];
        for ($i = 1; $i <= $this->paginas; $i++) {
            if ($i === 1 || $i === $this->paginas || abs($i - $this->pagina) <= 2) {
                $nums[] = $i;
            } elseif (end($nums) !== '…') {
                $nums[] = '…';
            }
        }
        return $nums;
    }

    /** URL de una página conservando los demás filtros de la URL. */
    public function urlPagina(int $n): string
    {
        $params = $_GET;
        $params[$this->parametro] = $n;
        return '?' . http_build_query($params) . ($this->ancla !== '' ? '#' . $this->ancla : '');
    }

    /** HTML de la barra de paginación. Si entra todo en una página solo muestra el total. */
    public function links(): string
    {
        $html = '<div class="paginacion"><span class="chico">';
        $html .= $this->total === 0 ? 'Sin resultados' : "Mostrando {$this->desde()}–{$this->hasta()} de {$this->total}";
        $html .= '</span>';
        if ($this->paginas > 1) {
            $html .= '<nav>';
            if ($this->pagina > 1) {
                $html .= '<a href="' . htmlspecialchars($this->urlPagina($this->pagina - 1)) . '">‹ Anterior</a>';
            }
            foreach ($this->numeros() as $n) {
                if ($n === '…') {
                    $html .= '<span class="puntos">…</span>';
                } elseif ($n === $this->pagina) {
                    $html .= '<span class="actual">' . $n . '</span>';
                } else {
                    $html .= '<a href="' . htmlspecialchars($this->urlPagina($n)) . '">' . $n . '</a>';
                }
            }
            if ($this->pagina < $this->paginas) {
                $html .= '<a href="' . htmlspecialchars($this->urlPagina($this->pagina + 1)) . '">Siguiente ›</a>';
            }
            $html .= '</nav>';
        }
        return $html . '</div>';
    }
}
