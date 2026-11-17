# Entrega 3 — Avance funcional

**Proyecto:** TallerYa · **Integrantes:** Devia Lautaro, Di Prinzio Pedro y Ruesga Lautaro
**URL pública:** http://tallerya.infinityfreeapp.com

## Qué funciona

| Funcionalidad | Historia | Estado |
|---|---|---|
| Login con dos roles (taller y cliente) | — | ✅ |
| Alta de orden con vehículo y cliente | HU01 | ✅ |
| Presupuesto con repuestos y mano de obra, con totales | HU02 | ✅ |
| Estados con transiciones controladas e historial | HU03, HU04 | ✅ |
| **Email al cliente por Gmail SMTP** cuando el presupuesto está listo y cuando el auto está listo | HU03 | ✅ conectado |
| Registro de avisos y reintento si el email falla | HU03 | ✅ |
| Aprobación o rechazo del presupuesto por el cliente | HU04 | ✅ |
| Consulta pública con patente y código | HU05 | ✅ |
| Panel con alerta de autos demorados | HU06 | ✅ |
| Pantalla para probar el envío de emails | — | ✅ |
| Sistema publicado en InfinityFree | — | ✅ |

## Qué falta para la entrega final
- Plan de pruebas y manual de usuario.
- Retrospectiva del grupo.
- Ajustes según la devolución de esta entrega.

## Integración externa: cómo está conectada
- `src/Email.php` se conecta a `smtp.gmail.com` por el puerto 465 (cifrado SSL), se autentica con una **contraseña de aplicación** de Google y manda el email con los comandos del protocolo SMTP (`EHLO`, `AUTH LOGIN`, `MAIL FROM`, `RCPT TO`, `DATA`).
- Si Gmail tarda más de 10 segundos o devuelve un error, **el estado de la orden se cambia igual**, el aviso queda como "Falló" con el motivo, y el taller lo puede reintentar desde el detalle de la orden.
- La contraseña de aplicación está en `config/config.php`, que **no se sube a GitHub**.
- **Por qué Gmail SMTP:** InfinityFree bloquea la API de Telegram y la función `mail()` de PHP. Probamos la API de Brevo: aceptaba el pedido, pero después rechazaba la entrega porque el remitente era un Gmail sin dominio propio (se ve en su registro de envíos). Con Gmail SMTP el email sale de la cuenta del taller, autenticado.

## Guion de la demo (5 minutos)

1. **Consulta sin usuario:** en *Consultar mi auto* poner la patente `AB123CD` y el código `K7P2QX`. Se ve el estado y el presupuesto.
2. **Taller** (`taller@tallerya.com`): mostrar el *Panel* con los autos demorados en rojo.
3. *Nueva orden*: cargar un auto con un cliente nuevo que tenga **un email nuestro**. El sistema muestra el código de seguimiento.
4. En el detalle de la orden: pasarla a *En diagnóstico*, intentar presupuestar sin ítems (aparece el error), cargar 2 ítems y pasarla a *Presupuesto listo*.
5. **Abrir el celular y mostrar el email que llegó** con el total y el código.
6. **Cliente**: entrar con el usuario nuevo, ver el presupuesto y aprobarlo.
7. Volver al taller: la orden figura como *Aprobado* y el historial dice que la aprobó el cliente.
8. (Opcional) Mostrar el caso de error: en el detalle de una orden, un aviso que falló con el botón *Reintentar*.

> Antes de la clase, probar el email desde *Panel → Probar el envío de emails* y tener abiertos el sitio y la casilla de correo.
