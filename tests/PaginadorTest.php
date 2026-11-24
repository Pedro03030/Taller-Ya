<?php
// Paginación de las tablas

function test_paginador_calcula_paginas_y_offset()
{
    $p = new Paginador(25, 10, 'pagina', 2);
    igual(3, $p->paginas);
    igual(10, $p->offset);
    igual(' LIMIT 10 OFFSET 10', $p->limiteSql());
    igual(11, $p->desde());
    igual(20, $p->hasta());
}

function test_paginador_corrige_paginas_que_no_existen()
{
    igual(3, (new Paginador(25, 10, 'pagina', 99))->pagina, 'Una página de más muestra la última');
    igual(1, (new Paginador(25, 10, 'pagina', -4))->pagina, 'Una página negativa muestra la primera');
    $vacio = new Paginador(0, 10, 'pagina', 1);
    igual(1, $vacio->paginas, 'Sin filas igual hay una página');
    igual(0, $vacio->desde());
}

function test_paginador_recorta_un_array_y_conserva_los_filtros()
{
    $p = new Paginador(23, 10, 'pagina', 3);
    igual([20, 21, 22], $p->recortar(range(0, 22)));
    $_GET = ['estado' => 'listo', 'pagina' => 3];
    igual('?estado=listo&pagina=2', $p->urlPagina(2), 'Al cambiar de página no se pierde el filtro');
    $_GET = [];
}
