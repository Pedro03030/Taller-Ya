# TallerYa 🔧

**Publicado en:** http://tallerya.infinityfreeapp.com

Sistema web para un **taller mecánico** que resuelve el problema:

> *"Los clientes me llaman todo el día para saber si el auto está listo y cuánto sale."*

El taller carga cada auto que entra como una **orden de trabajo**, arma el presupuesto y va cambiando el estado. El cliente:
- recibe un **email** cuando el presupuesto está listo y cuando puede retirar el auto,
- **aprueba o rechaza** el presupuesto desde la web,
- puede **consultar el estado sin usuario**, con la patente y un código de seguimiento.

## Integrantes y responsabilidades

| Integrante | Responsable de |
|---|---|
| Devia Lautaro | Módulo de órdenes y reglas de estados — Menú B (`src/Estados.php`, `src/Ordenes.php`, `src/Presupuesto.php`, pantallas del taller en `public/taller/`) |
| Di Prinzio Pedro | Integración externa: envío de emails por Gmail SMTP — Menú A (`src/Email.php`, `public/taller/probar_email.php`) y publicación en InfinityFree |
| Ruesga Lautaro | Login, seguridad y validaciones (`src/app.php`, `src/Validador.php`), vista del cliente (`public/cliente/`), consulta pública (`public/seguimiento.php`) y pruebas automatizadas (`tests/`) |

Los tres tienen que poder explicar todo el proyecto.

## Requisitos del TP que cumple

| Requisito | Dónde está |
|---|---|
| Persistencia en base de datos | MySQL, `database/schema.sql` |
| Login con 2 tipos de usuario | `taller` y `cliente` (`public/login.php`, `requiereRol()` en `src/app.php`) |
| Validación y manejo de errores | `src/Validador.php`; si Gmail no responde, el estado se guarda igual y el aviso se puede reintentar |
| Menú A: notificaciones | Emails por el servidor SMTP de Gmail (`src/Email.php`) |
| Menú B: reglas de negocio | Estados con transiciones, permisos por rol y alerta de órdenes demoradas (`src/Estados.php`) |
| 5+ pruebas automatizadas | `tests/` (16 pruebas) |

## Tecnologías

PHP 8 (sin framework), MySQL, HTML + CSS, servidor SMTP de Gmail para los emails.

## Estructura

```
config/     configuración (config.php no se sube a Git)
database/   schema.sql (tablas) y seed.sql (datos de prueba)
docs/       documentación de cada entrega
public/     páginas que ve el navegador (taller/, cliente/, css/)
src/        lógica: estados, validación, presupuesto, email, base de datos
tests/      pruebas automatizadas
```

---

## Instalación en InfinityFree

### 1. Crear la cuenta y el sitio
1. Crear una cuenta en https://www.infinityfree.com y un sitio (el nuestro es `tallerya.infinityfreeapp.com`).
2. En el panel, en **MySQL Databases**, crear una base (por ejemplo `tallerya`). Anotar: nombre de la base (`if0_XXXX_tallerya`), usuario (`if0_XXXX`), host (`sqlXXX.infinityfree.com`) y la contraseña de la cuenta (está en el panel, "Account Details").

### 2. Cargar la base de datos
1. Entrar a **phpMyAdmin** desde el panel y elegir la base.
2. Pestaña **Importar** → subir `database/schema.sql`.
3. Pestaña **Importar** → subir `database/seed.sql` (datos de prueba).

> Para probar los avisos, cambiá el email de Juan Pérez en la tabla `usuarios` por uno tuyo.

### 3. Configurar el envío de emails (Gmail)
1. En la cuenta de Gmail que va a mandar los avisos, activar la **verificación en 2 pasos** (https://myaccount.google.com/security).
2. Entrar a https://myaccount.google.com/apppasswords, crear una **contraseña de aplicación** llamada `TallerYa` y copiar las 16 letras.

> No uses `mail()` de PHP ni Telegram: InfinityFree no los permite en el plan gratis. Probamos la API de Brevo, pero rechaza un Gmail como remitente si no se tiene un dominio propio. Por eso usamos el SMTP de Gmail, que InfinityFree sí permite.

### 4. Configurar el proyecto
Copiar `config/config.example.php` como `config/config.php` y completar los datos de la base, el Gmail y la contraseña de aplicación. Dejar `'url_base' => '/public'`.

### 5. Subir los archivos
Con el **File Manager** del panel o con WinSCP / FileZilla (protocolo **FTP**, servidor `ftpupload.net`, puerto 21; los datos están en *FTP Details*), subir **todo el contenido** del proyecto dentro de la carpeta `htdocs`:

```
htdocs/
├── index.php        (redirige a public/)
├── config/
├── public/
├── src/
└── database/ ...
```

Las carpetas `config`, `src`, `database`, `tests` y `docs` tienen un `.htaccess` que impide verlas desde el navegador.

### 6. Probar
Entrar a http://tallerya.infinityfreeapp.com. Usuarios de prueba:

| Rol | Email | Contraseña |
|---|---|---|
| Taller | taller@tallerya.com | taller123 |
| Cliente | juan@mail.com | cliente123 |
| Cliente | ana@mail.com | cliente123 |

Consulta pública sin usuario: patente `AB123CD`, código `K7P2QX`.

> Cambiá la contraseña del usuario del taller antes de mostrarlo afuera del aula.

---

## Instalación local (XAMPP)

1. Copiar la carpeta del proyecto a `C:\xampp\htdocs\tallerya`.
2. En phpMyAdmin (`http://localhost/phpmyadmin`) crear la base `tallerya` (utf8mb4) e importar `schema.sql` y después `seed.sql`.
3. Copiar `config/config.example.php` como `config/config.php`, con `mysql:host=localhost;dbname=tallerya;charset=utf8mb4`, usuario `root`, contraseña vacía y `'url_base' => '/tallerya/public'`.
4. Entrar a `http://localhost/tallerya`.

## Pruebas automatizadas

No hace falta instalar nada ni tener MySQL (usan una base SQLite en memoria y simulan el servidor de email):

```
php tests/run.php
```

Prueban: las transiciones de estado y los permisos, la alerta de demora, la validación de datos, el cálculo del presupuesto, el envío de email (bien, sin respuesta, sin configurar, acentos bien codificados) y que si el email falla el estado se cambie igual y se pueda reintentar.

## Uso

**Taller:** Panel (resumen y demorados) → Nueva orden (patente, problema y cliente) → en el detalle: diagnóstico, fecha estimada, ítems del presupuesto y botones para cambiar de estado. Al pasar a *Presupuesto listo* y a *Listo para retirar* se manda un email al cliente.

**Cliente:** ingresa con su email → *Mis autos* → *Ver presupuesto* → Aprobar o Rechazar.
