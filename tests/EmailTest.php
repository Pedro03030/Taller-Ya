<?php
// Envío de emails por Gmail SMTP (Menú A): se simula el servidor para no depender de internet.

function emailCon(callable $transporte): Email
{
    return new Email('taller@gmail.com', 'abcd efgh ijkl mnop', 'TallerYa', $transporte);
}

function test_email_se_envia_bien()
{
    $datos = null;
    $e = emailCon(function ($host, $puerto, $usuario, $password, $para, $mensaje) use (&$datos) {
        $datos = compact('host', 'puerto', 'password', 'para', 'mensaje');
        return null; // null = salió bien
    });
    $r = $e->enviar('juan@mail.com', 'Juan', 'Asunto', 'Hola');
    igual(true, $r['ok']);
    igual('smtp.gmail.com', $datos['host']);
    igual('juan@mail.com', $datos['para'], 'Se manda al cliente correcto');
    igual('abcdefghijklmnop', $datos['password'], 'Se sacan los espacios de la contraseña de aplicación');
}

function test_el_servidor_de_email_no_responde_y_el_sistema_no_se_rompe()
{
    $e = emailCon(fn() => 'El servidor de email no respondió a tiempo.');
    $r = $e->enviar('juan@mail.com', 'Juan', 'Asunto', 'Hola');
    igual(false, $r['ok']);
    comprobar(str_contains($r['error'], 'no respondió'), 'Tiene que explicar el error');
}

function test_email_sin_configurar_no_intenta_conectarse()
{
    $llamado = false;
    $e = new Email('', '', 'TallerYa', function () use (&$llamado) { $llamado = true; return null; });
    igual(false, $e->enviar('juan@mail.com', 'Juan', 'A', 'B')['ok']);
    igual(false, $llamado, 'No debería intentar conectarse sin usuario ni contraseña');
}

function test_el_email_lleva_acentos_bien_codificados()
{
    $e = emailCon(fn() => null);
    $m = $e->armarMensaje('juan@mail.com', 'Juan Pérez', 'Tu auto está listo', "Hola, ñandú.\nChau.");
    comprobar(str_contains($m, 'Subject: =?UTF-8?B?' . base64_encode('Tu auto está listo') . '?='), 'El asunto va codificado');
    comprobar(str_contains($m, 'To: =?UTF-8?B?' . base64_encode('Juan Pérez') . '?= <juan@mail.com>'));
    comprobar(str_contains($m, 'charset=UTF-8'));
    $cuerpo = substr($m, strpos($m, "\r\n\r\n") + 4);
    igual("Hola, ñandú.\nChau.", base64_decode($cuerpo), 'El cuerpo se puede decodificar igual');
}
