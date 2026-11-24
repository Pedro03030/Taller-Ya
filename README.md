# TallerYa 🔧

**Publicado en:** http://tallerya.infinityfreeapp.com

Sistema web para un **taller mecánico** que resuelve el problema:

> *"Los clientes me llaman todo el día para saber si el auto está listo y cuánto sale."*

El taller carga cada auto que entra como una **orden de trabajo**, arma el presupuesto y va cambiando el estado. El cliente:
- recibe un **email** cuando el presupuesto está listo y cuando puede retirar el auto,
- **aprueba o rechaza** el presupuesto desde la web,
- puede **consultar el estado sin usuario**, con la patente y un código de seguimiento,
- puede **pedir una orden nueva** desde la web cuando quiera, aunque ese auto ya tenga otra abierta.

### Versión 2 (final)
- **Roles:** admin (dueño), empleado y cliente. Cada persona del taller tiene su usuario.
- **Filtrador de patentes** en *Nueva orden*: se escribe parte de la patente, aparecen los autos que coinciden (con cuántas órdenes abiertas tienen) y al elegir uno se completan los datos.
- **Órdenes con otra abierta:** tanto el taller como el cliente pueden abrir una orden nueva aunque el auto tenga otra en curso.
- **Dinero (solo admin):** lo cobrado al entregar, separado **por día, por mes y desde siempre**, más lo pendiente de cobro.
- **Modificar el presupuesto** después de mandarlo (hasta que el auto esté listo): el empleado se identifica con **su email y contraseña**, queda registrado quién lo cambió (en el ítem, en "Cambios del presupuesto" y en el historial de empleados), la orden vuelve a *Presupuesto listo* y se le manda un email al cliente para que lo apruebe de nuevo.
- **Historial de empleados:** todo lo que hace el personal (crear órdenes, cambiar estados, presupuestos, altas…). Lo ven solo el admin y los empleados.
- **Paginación** en todas las tablas (10 filas por página; los filtros se mantienen al cambiar de página).

## Integrantes y responsabilidades

| Integrante | Responsable de |
|---|---|
| Devia Lautaro | Módulo de órdenes y reglas de estados — Menú B (`src/Estados.php`, `src/Ordenes.php`, `src/Presupuesto.php`, pantallas del taller en `public/taller/`). Versión final: presupuesto modificable con nueva aprobación, filtrador de patentes y apartado de dinero (`src/Dinero.php`, `public/taller/dinero.php`) |
| Di Prinzio Pedro | Integración externa: envío de emails por Gmail SMTP — Menú A (`src/Email.php`, `public/taller/probar_email.php`) y publicación en InfinityFree. Versión final: base de datos v2 y migración (`database/`), documentación y manual de usuario (`docs/`) |
| Ruesga Lautaro | Login, seguridad y validaciones (`src/app.php`, `src/Validador.php`), vista del cliente (`public/cliente/`), consulta pública (`public/seguimiento.php`) y pruebas automatizadas (`tests/`). Versión final: roles admin y empleado, empleados e historial (`src/Usuarios.php`, `public/taller/empleados.php`, `public/taller/historial.php`), paginación (`src/Paginador.php`) y nueva orden del cliente |

Los tres tienen que poder explicar todo el proyecto.

## Requisitos del TP que cumple

| Requisito | Dónde está |
|---|---|
| Persistencia en base de datos | MySQL, `database/schema.sql` |
| Login con 2 tipos de usuario | `admin`, `empleado` y `cliente` (`public/login.php`, `requiereRol()` / `requierePersonal()` / `requiereAdmin()` en `src/app.php`, `src/Usuarios.php`) |
| Validación y manejo de errores | `src/Validador.php`; si Gmail no responde, el estado se guarda igual y el aviso se puede reintentar |
| Menú A: notificaciones | Emails por el servidor SMTP de Gmail (`src/Email.php`) |
| Menú B: reglas de negocio | Estados con transiciones, permisos por rol, alerta de órdenes demoradas, presupuesto modificable con reaprobación y monto cobrado (`src/Estados.php`, `src/Ordenes.php`, `src/Dinero.php`) |
| 5+ pruebas automatizadas | `tests/` (27 pruebas) |

## Tecnologías

PHP 8 (sin framework), MySQL, HTML + CSS, servidor SMTP de Gmail para los emails.

## Estructura

```
config/     configuración (config.php no se sube a Git)
database/   schema.sql (tablas), seed.sql (datos de prueba), migracion_v2.sql (actualizar una base ya cargada) y datos_empleado.sql
docs/       documentación de cada entrega
public/     páginas que ve el navegador (taller/, cliente/, css/)
src/        lógica: estados, órdenes, presupuesto, usuarios y roles, dinero, paginador, validación, email
tests/      pruebas automatizadas
```

---

## Instalación en InfinityFree

### 1. Crear la cuenta y el sitio
1. Crear una cuenta en https://www.infinityfree.com y un sitio (el nuestro es `tallerya.infinityfreeapp.com`).
2. En el panel, en **MySQL Databases**, crear una base (por ejemplo `tallerya`). Anotar: nombre de la base (`if0_XXXX_tallerya`), usuario (`if0_XXXX`), host (`sqlXXX.infinityfree.com`) y la contraseña de la cuenta (está en el panel, "Account Details").

### 2. Cargar la base de datos

> **¿Ya tenés la versión anterior publicada y con datos?** No importes `schema.sql` (borra todo). Hacé una copia (phpMyAdmin → *Exportar* → *Continuar*) e importá **una sola vez** `database/migracion_v2.sql`. Pasa la cuenta del taller a admin, agrega las columnas y tablas nuevas, calcula lo cobrado de las órdenes ya entregadas y crea el empleado de prueba. Después subí de nuevo las carpetas `src/` y `public/` (el `config/config.php` queda igual). Si alguien tenía la sesión abierta, se actualiza sola.

Instalación desde cero:
1. Entrar a **phpMyAdmin** desde el panel y elegir la base.
2. Pestaña **Importar** → subir `database/schema.sql`.
3. Pestaña **Importar** → subir `database/seed.sql` (datos de prueba).
4. (Opcional) Pestaña **Importar** → subir `database/datos_demo.sql`: agrega 12 clientes, 16 autos y 24 órdenes en todos los estados, con presupuestos, historial y avisos (sirve para ver la paginación y el apartado de dinero). Todos esos clientes tienen la contraseña `cliente123`.

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
| Admin (dueño) | taller@tallerya.com | taller123 |
| Empleado | empleado@tallerya.com | empleado123 |
| Cliente | juan@mail.com | cliente123 |
| Cliente | ana@mail.com | cliente123 |

Consulta pública sin usuario: patente `AB123CD`, código `K7P2QX`.

> Cambiá la contraseña del usuario del taller antes de mostrarlo afuera del aula. Los empleados nuevos los da de alta el admin desde **Empleados**.

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

Prueban: las transiciones de estado y los permisos por rol, la alerta de demora, la validación de datos, el cálculo del presupuesto, el envío de email (bien, sin respuesta, sin configurar, acentos bien codificados), que si el email falla el estado se cambie igual y se pueda reintentar, la paginación, la modificación del presupuesto (vuelve a aprobación, registra al empleado, no deja dejarlo vacío ni cambiarlo con el auto listo), lo cobrado al entregar (0 si rechazó), el dinero por día/mes/siempre, la verificación del empleado y que se pueda abrir otra orden con una abierta.

## Uso

**Empleado:** Panel (resumen y demorados) → Nueva orden (filtrador de patentes, problema y cliente) → en el detalle: diagnóstico, fecha estimada, ítems del presupuesto y botones para cambiar de estado. Al pasar a *Presupuesto listo* y a *Listo para retirar* se manda un email al cliente. Si hay que cambiar un presupuesto ya enviado: *Modificar presupuesto* → cambiar renglones, motivo, email y contraseña del empleado. *Historial* muestra qué hizo cada uno.

**Admin:** todo lo del empleado, más *Dinero* (por día, por mes y siempre) y *Empleados* (alta, baja y reactivación).

**Cliente:** ingresa con su email → *Mis autos* → *Ver presupuesto* → Aprobar o Rechazar. Con *Nueva orden* pide una orden para uno de sus autos (o carga otro), aunque ya tenga una abierta.

