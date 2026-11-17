<?php
/**
 * Arranque común: se incluye al principio de cada página.
 * Carga la configuración, la conexión a la base, la sesión y funciones de ayuda.
 */
require_once __DIR__ . '/Estados.php';
require_once __DIR__ . '/Validador.php';
require_once __DIR__ . '/Presupuesto.php';
require_once __DIR__ . '/Email.php';
require_once __DIR__ . '/Ordenes.php';

$rutaConfig = __DIR__ . '/../config/config.php';
if (!file_exists($rutaConfig)) {
    http_response_code(500);
    die('Falta config/config.php. Copiá config/config.example.php y completalo (ver README).');
}
$CONFIG = require $rutaConfig;
date_default_timezone_set($CONFIG['zona_horaria'] ?? 'America/Argentina/Buenos_Aires');

session_start();

function db(): PDO
{
    global $CONFIG;
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO($CONFIG['db']['dsn'], $CONFIG['db']['usuario'], $CONFIG['db']['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('No se pudo conectar a la base de datos. Revisá config/config.php.');
        }
    }
    return $pdo;
}

function email(): Email
{
    global $CONFIG;
    $c = $CONFIG['email'] ?? [];
    return new Email(
        $c['gmail_usuario'] ?? '',
        $c['gmail_password_app'] ?? '',
        $c['nombre_remitente'] ?? 'TallerYa',
        null,
        'smtp.gmail.com',
        (int)($c['puerto'] ?? 465)
    );
}

function ordenes(): Ordenes
{
    return new Ordenes(db(), email());
}

/* ---------- Sesión y permisos ---------- */

function usuarioActual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/** Corta la página si no hay sesión o el rol no corresponde. */
function requiereRol(string $rol): array
{
    $u = usuarioActual();
    if (!$u) {
        redirigir('/login.php');
    }
    if ($u['rol'] !== $rol) {
        http_response_code(403);
        die('No tenés permiso para ver esta página.');
    }
    return $u;
}

/** Arma una URL interna teniendo en cuenta dónde está instalado el sitio. */
function url(string $ruta): string
{
    global $CONFIG;
    return rtrim($CONFIG['url_base'] ?? '', '/') . $ruta;
}

function redirigir(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

/* ---------- Seguridad ---------- */

/** Escapa texto para mostrarlo en HTML (evita XSS). */
function e($texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/** Token anti-CSRF para los formularios. */
function csrfCampo(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function verificarCsrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('El formulario venció. Volvé atrás y recargá la página.');
    }
}

/* ---------- Mensajes "flash" (se muestran una vez) ---------- */

function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'texto' => $texto];
}

function mostrarFlash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= '<div class="alerta alerta-' . e($f['tipo']) . '">' . e($f['texto']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------- Vista ---------- */

function etiquetaEstado(string $estado): string
{
    return '<span class="estado estado-' . e($estado) . '">' . e(Estados::nombre($estado)) . '</span>';
}

function fecha(?string $f, bool $conHora = false): string
{
    if (!$f) {
        return '—';
    }
    return date($conHora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($f));
}

require_once __DIR__ . '/layout.php';
