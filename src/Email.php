<?php
/**
 * Envío de emails por el servidor SMTP de Gmail (Menú A: envío de notificaciones).
 *
 * Por qué Gmail SMTP: InfinityFree no permite mail() de PHP en el plan gratis,
 * y Brevo rechazaba un Gmail como remitente sin dominio propio. Con Gmail SMTP
 * el email sale de la cuenta de Gmail del taller, autenticado de verdad.
 *
 * Se necesita una "contraseña de aplicación" de Google (no la contraseña normal):
 * https://myaccount.google.com/apppasswords
 *
 * El "transporte" (la función que habla con el servidor) se puede reemplazar
 * en las pruebas para simular que Gmail anda, falla o no responde.
 */
class Email
{
    private string $usuario;
    private string $password;
    private string $nombreRemitente;
    private string $host;
    private int $puerto;
    private int $timeout;
    /** @var callable(string $host, int $puerto, string $usuario, string $password, string $para, string $mensaje, int $timeout): ?string */
    private $transporte;

    public function __construct(
        string $usuario,
        string $password,
        string $nombreRemitente = 'TallerYa',
        ?callable $transporte = null,
        string $host = 'smtp.gmail.com',
        int $puerto = 465,
        int $timeout = 10
    ) {
        $this->usuario = $usuario;
        $this->password = str_replace(' ', '', $password); // Google la muestra con espacios
        $this->nombreRemitente = $nombreRemitente;
        $this->transporte = $transporte ?? [self::class, 'enviarPorSmtp'];
        $this->host = $host;
        $this->puerto = $puerto;
        $this->timeout = $timeout;
    }

    /**
     * Envía un email. Nunca tira excepción: devuelve ['ok' => bool, 'error' => ?string]
     * para que el sistema siga funcionando aunque Gmail no responda.
     */
    public function enviar(string $para, string $nombre, string $asunto, string $texto): array
    {
        if ($this->usuario === '' || $this->password === '') {
            return ['ok' => false, 'error' => 'El envío de emails no está configurado (falta el Gmail o la contraseña de aplicación).'];
        }
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => "El email del cliente no es válido: $para"];
        }

        $mensaje = $this->armarMensaje($para, $nombre, $asunto, $texto);
        $error = call_user_func($this->transporte, $this->host, $this->puerto, $this->usuario, $this->password, $para, $mensaje, $this->timeout);

        return $error === null ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => $error];
    }

    /** Arma el email en el formato que entienden los servidores de correo (RFC 5322 + MIME). */
    public function armarMensaje(string $para, string $nombre, string $asunto, string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $encabezados = [
            'Date: ' . date('r'),
            'From: ' . self::codificar($this->nombreRemitente) . " <{$this->usuario}>",
            'To: ' . self::codificar($nombre) . " <$para>",
            'Subject: ' . self::codificar($asunto),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@tallerya>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        // El cuerpo va en base64: así los acentos llegan bien y ninguna línea empieza con "."
        return implode("\r\n", $encabezados) . "\r\n\r\n" . chunk_split(base64_encode($texto), 76, "\r\n");
    }

    /** Los encabezados solo admiten ASCII: los acentos se codifican así. */
    private static function codificar(string $texto): string
    {
        return '=?UTF-8?B?' . base64_encode($texto) . '?=';
    }

    /**
     * Transporte real: conversación SMTP con el servidor.
     * Puerto 465: conexión cifrada desde el principio (SSL).
     * Puerto 587: empieza sin cifrar y pasa a TLS con STARTTLS.
     * Devuelve null si salió bien, o el texto del error.
     */
    public static function enviarPorSmtp(string $host, int $puerto, string $usuario, string $password, string $para, string $mensaje, int $timeout): ?string
    {
        $direccion = ($puerto === 465 ? 'ssl://' : 'tcp://') . "$host:$puerto";
        $socket = @stream_socket_client($direccion, $errno, $errstr, $timeout);
        if (!$socket) {
            return "No se pudo conectar con $host:$puerto ($errstr).";
        }
        stream_set_timeout($socket, $timeout);

        try {
            self::esperar($socket, [220]);
            self::comando($socket, 'EHLO tallerya', [250]);
            if ($puerto === 587) {
                self::comando($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('No se pudo cifrar la conexión (TLS).');
                }
                self::comando($socket, 'EHLO tallerya', [250]);
            }
            self::comando($socket, 'AUTH LOGIN', [334]);
            self::comando($socket, base64_encode($usuario), [334]);
            self::comando($socket, base64_encode($password), [235]);
            self::comando($socket, "MAIL FROM:<$usuario>", [250]);
            self::comando($socket, "RCPT TO:<$para>", [250, 251]);
            self::comando($socket, 'DATA', [354]);
            self::comando($socket, $mensaje . "\r\n.", [250]);
            fwrite($socket, "QUIT\r\n");
            return null;
        } catch (RuntimeException $e) {
            return $e->getMessage();
        } finally {
            fclose($socket);
        }
    }

    private static function comando($socket, string $linea, array $codigosOk): void
    {
        fwrite($socket, $linea . "\r\n");
        self::esperar($socket, $codigosOk);
    }

    /** Lee la respuesta del servidor (puede ocupar varias líneas) y controla el código. */
    private static function esperar($socket, array $codigosOk): void
    {
        $respuesta = '';
        while (($linea = fgets($socket, 1024)) !== false) {
            $respuesta .= $linea;
            if (strlen($linea) < 4 || $linea[3] === ' ') {
                break; // "250 OK" es la última línea; "250-..." indica que siguen más
            }
        }
        if ($respuesta === '') {
            $info = stream_get_meta_data($socket);
            throw new RuntimeException($info['timed_out'] ? 'El servidor de email no respondió a tiempo.' : 'El servidor de email cortó la conexión.');
        }
        $codigo = (int)substr($respuesta, 0, 3);
        if (!in_array($codigo, $codigosOk, true)) {
            $detalle = trim(preg_replace('/\s+/', ' ', $respuesta));
            if ($codigo === 535) {
                throw new RuntimeException('Gmail rechazó el usuario o la contraseña. Usá una contraseña de aplicación, no la normal. (' . $detalle . ')');
            }
            throw new RuntimeException("El servidor de email respondió con un error: $detalle");
        }
    }
}
