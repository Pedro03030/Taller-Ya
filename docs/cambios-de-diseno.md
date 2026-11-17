# Cambios de diseño

Si algo del sistema final no coincide con lo diseñado en la Entrega 2 (pantallas, menú, colores, navegación), se anota acá qué se cambió y por qué.

| Fecha | Qué se cambió | Por qué |
|---|---|---|
| 16/11/2026 | El envío de emails pasó de la **API de Brevo** al **servidor SMTP de Gmail**. En el diagrama de arquitectura cambia el servicio externo; las pantallas no cambian. | Brevo aceptaba los pedidos pero rechazaba la entrega: no admite un Gmail como remitente sin dominio propio, y no tenemos dominio. InfinityFree sí permite conectarse al SMTP de Gmail. |
