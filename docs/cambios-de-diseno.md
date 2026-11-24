# Cambios de diseño

Si algo del sistema final no coincide con lo diseñado en la Entrega 2 (pantallas, menú, colores, navegación), se anota acá qué se cambió y por qué.

| Fecha | Qué se cambió | Por qué |
|---|---|---|
| 16/11/2026 | El envío de emails pasó de la **API de Brevo** al **servidor SMTP de Gmail**. En el diagrama de arquitectura cambia el servicio externo; las pantallas no cambian. | Brevo aceptaba los pedidos pero rechazaba la entrega: no admite un Gmail como remitente sin dominio propio, y no tenemos dominio. InfinityFree sí permite conectarse al SMTP de Gmail. |
| 23/11/2026 | **Roles:** el usuario único "taller" se separa en **admin** (dueño) y **empleado**. El menú suma *Historial* (admin y empleados) y *Dinero* y *Empleados* (solo admin). | Con una sola cuenta compartida no se sabía quién hacía cada cosa, y el dinero no tiene que verlo todo el personal. |
| 23/11/2026 | **Presupuesto modificable** después de enviarlo, hasta que el auto está listo. Pide email y contraseña del empleado y vuelve a *Presupuesto listo*. Tablas nuevas `presupuesto_cambios` e `historial_empleados`; columna `usuario_id` en `orden_items`. | Al desarmar el auto a veces aparecen más fallas. Antes había que cerrar la orden y abrir otra; ahora queda registrado quién lo cambió y el cliente lo vuelve a aprobar. |
| 23/11/2026 | **Dinero:** columna `monto_cobrado` en `ordenes`, que se guarda al entregar. | Para separar lo cobrado por día, mes y siempre sin recalcular presupuestos que después se podrían tocar. |
| 23/11/2026 | **Nueva orden:** filtrador de patentes en vivo; el cliente también puede pedir una orden desde la web, aunque tenga otra abierta. | Agiliza la carga en el mostrador y el mismo auto puede tener dos trabajos distintos a la vez. |
| 23/11/2026 | **Paginación** en todas las tablas. | Con muchos clientes y órdenes las listas se hacían muy largas. |
