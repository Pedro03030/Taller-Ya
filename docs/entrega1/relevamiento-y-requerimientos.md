# Entrega 1 — Relevamiento y requerimientos

**Proyecto:** TallerYa · **Cliente:** Taller mecánico · **Integrantes:** Devia Lautaro, Di Prinzio Pedro y Ruesga Lautaro

> Los requerimientos salen de la entrevista con el cliente (ver `minuta-entrevista.md`).

## 1. Definición del problema

El dueño del taller pierde mucho tiempo atendiendo llamadas de clientes que preguntan **si el auto está listo** y **cuánto va a salir**. Esto pasa porque:

- El cliente no tiene ninguna forma de saber en qué etapa está su auto si no llama.
- El presupuesto se comunica por teléfono, y la aprobación queda "de palabra", sin registro.
- El taller no lleva un registro ordenado de los autos que tiene, en qué estado está cada uno ni cuándo prometió entregarlos: hoy los anota en un cuaderno.

**Consecuencias:** interrupciones constantes del trabajo, malentendidos con los precios, clientes molestos por no enterarse de las novedades y autos que se entregan más tarde de lo prometido sin que nadie lo note.

**Objetivo del sistema:** que el cliente se entere solo del estado y del presupuesto de su auto, sin tener que llamar, y que el taller tenga ordenado su trabajo.

## 2. Alcance

**Incluye:** registro de órdenes de trabajo, presupuesto (repuestos y mano de obra), estados de la reparación, aprobación del presupuesto por el cliente, avisos por email, consulta pública del estado, alerta de autos demorados.

**No incluye:** facturación, cobros, control de stock de repuestos, turnos.

## 3. Actores

| Actor | Descripción |
|---|---|
| **Taller** | Dueño o empleado del taller. Carga los autos, arma presupuestos y actualiza el estado. |
| **Cliente** | Dueño del auto. Consulta el estado, aprueba o rechaza el presupuesto, recibe avisos. |
| **Visitante** | Cualquier persona con la patente y el código de seguimiento; solo puede consultar el estado. |
| **Servicio de email (Gmail)** | Servidor SMTP externo que envía los emails. |

## 4. Requerimientos funcionales

| ID | Requerimiento | Prioridad |
|---|---|---|
| RF01 | El sistema debe permitir iniciar sesión con email y contraseña, con dos roles: **taller** y **cliente**. | Alta |
| RF02 | El taller debe poder registrar una orden de trabajo con la patente, los datos del vehículo, el problema que cuenta el cliente y una fecha estimada de entrega (opcional). | Alta |
| RF03 | Si la patente ya existe, el sistema debe reutilizar el vehículo y su cliente; si no, debe permitir cargar un cliente nuevo y generarle una contraseña inicial. | Media |
| RF04 | Al crear una orden, el sistema debe generar un **código de seguimiento** de 6 caracteres. | Alta |
| RF05 | El taller debe poder cargar el diagnóstico y los ítems del presupuesto (repuesto o mano de obra, descripción, cantidad y precio). El sistema calcula subtotales y total. | Alta |
| RF06 | El sistema debe manejar los estados: Ingresado → En diagnóstico → Presupuesto listo → Aprobado / Rechazado → En reparación → Listo para retirar → Entregado, sin permitir saltear pasos. | Alta |
| RF07 | No se puede enviar un presupuesto sin ítems, ni modificarlo después de enviado. | Alta |
| RF08 | El cliente debe poder **aprobar o rechazar** el presupuesto desde la web. El taller también puede registrarlo (por ejemplo, si el cliente aprueba por teléfono). | Alta |
| RF09 | Cuando la orden pasa a *Presupuesto listo* y a *Listo para retirar*, el sistema debe enviar un **email** al cliente. | Alta |
| RF10 | El sistema debe registrar cada aviso (enviado o fallido) y permitir **reintentar** los que fallaron. | Media |
| RF11 | Cualquier persona debe poder consultar el estado de una orden con la **patente y el código**, sin iniciar sesión. | Alta |
| RF12 | El sistema debe mostrar al taller una **alerta** de las órdenes que pasaron la fecha estimada y siguen en el taller. | Media |
| RF13 | El sistema debe guardar el **historial** de cambios de estado (quién, cuándo y comentario). | Media |
| RF14 | El taller debe poder listar órdenes, filtrarlas por estado y buscarlas por patente o cliente. | Media |
| RF15 | El cliente debe ver solo sus propios autos y órdenes. | Alta |

## 5. Requerimientos no funcionales

| ID | Requerimiento |
|---|---|
| RNF01 | **Seguridad:** las contraseñas se guardan con hash (`password_hash`); las consultas usan sentencias preparadas (evita inyección SQL); todo lo que se muestra se escapa (evita XSS); los formularios tienen token anti-CSRF. |
| RNF02 | **Disponibilidad ante fallas externas:** si el servicio de email no responde en 10 segundos, el sistema guarda el cambio igual, avisa al taller y permite reintentar. |
| RNF03 | **Usabilidad:** el cliente debe poder aprobar un presupuesto en 3 clics como máximo desde que entra. Los estados se muestran con el mismo color en todo el sitio. |
| RNF04 | **Compatibilidad:** debe funcionar en navegadores actuales de computadora (el taller lo usa desde el mostrador) y de celular. |
| RNF05 | **Hosting:** debe funcionar en un hosting PHP + MySQL gratuito (InfinityFree). |
| RNF06 | **Rendimiento:** las pantallas deben cargar en menos de 2 segundos con hasta 1.000 órdenes. |
| RNF07 | **Mantenibilidad:** la lógica de negocio (estados, validaciones, presupuesto) está separada de las pantallas y tiene pruebas automatizadas. |

## 6. Historias de usuario

**HU01 — Registrar un auto que entra**
Como **taller**, quiero cargar el auto que me dejan con el problema que cuenta el cliente, para tener registro de todo lo que hay en el taller.
- *Dado* que la patente no existe, *cuando* cargo patente, marca, modelo, problema y un cliente, *entonces* se crea la orden en estado *Ingresado* con un código de seguimiento.
- *Dado* que la patente ya vino antes, *cuando* la ingreso, *entonces* no tengo que volver a cargar el vehículo ni el cliente.
- *Dado* que ingreso una patente inválida o dejo vacío el problema, *entonces* el sistema me muestra el error y no crea la orden.

**HU02 — Armar el presupuesto**
Como **taller**, quiero cargar los repuestos y la mano de obra, para que el cliente vea cuánto sale y en qué se gasta.
- El sistema calcula el subtotal de cada ítem y el total.
- No puedo cargar cantidades o precios menores o iguales a 0.
- No puedo enviar el presupuesto si no tiene ítems.
- Una vez enviado, el presupuesto no se puede modificar.

**HU03 — Avisar al cliente sin llamarlo**
Como **taller**, quiero que el cliente reciba un email cuando el presupuesto está listo y cuando puede retirar el auto, para no tener que llamarlo ni atender sus llamadas.
- Al pasar a *Presupuesto listo*, el cliente recibe un email con el total y su código de seguimiento.
- Al pasar a *Listo para retirar*, el cliente recibe un email.
- Si el email no se puede enviar, el estado se cambia igual, me aparece un aviso y puedo reintentar.

**HU04 — Aprobar el presupuesto**
Como **cliente**, quiero ver el detalle del presupuesto y aprobarlo o rechazarlo desde la web, para decidir sin tener que llamar.
- Solo veo los botones si la orden está en *Presupuesto listo*.
- Al aprobar, la orden pasa a *Aprobado* y queda registrado que lo aprobé yo.
- No puedo aprobar órdenes de otros clientes.

**HU05 — Consultar sin usuario**
Como **cliente**, quiero consultar en qué etapa está mi auto con la patente y el código, para saberlo aunque no tenga usuario.
- Con patente y código correctos veo el estado, el recorrido y el presupuesto.
- Con datos incorrectos veo "No encontramos ninguna orden", sin dar pistas de cuál dato está mal.

**HU06 — Ver los autos demorados**
Como **taller**, quiero ver qué autos pasaron la fecha que prometí, para avisarle al cliente antes de que llame.
- El panel muestra cuántas órdenes están demoradas y las resalta en rojo.
- Una orden lista para retirar o entregada no cuenta como demorada.

**HU07 — Seguir mi auto**
Como **cliente**, quiero ver todos mis autos y en qué estado está cada reparación, para saber qué está pasando.
- Veo primero los autos que están en el taller y después las reparaciones anteriores.

## 7. Elección de los menús

**Menú A — Envío de notificaciones (email por el servidor SMTP de Gmail).**
Justificación: el problema del cliente es que lo llaman para saber novedades. Si el sistema avisa solo en los dos momentos que más importan (presupuesto y auto listo), las llamadas deberían bajar. Se eligió email (y no WhatsApp o Telegram) porque todos los clientes tienen email, no hay que vincular nada y funciona en el hosting gratuito que usamos.

**Menú B — Reglas de negocio complejas.**
Justificación: la reparación pasa por estados con transiciones que no se pueden saltear (no se repara sin aprobación), cada rol puede hacer cambios distintos, hay avisos automáticos en estados determinados y una alerta automática cuando una orden pasa la fecha estimada.
