# Entrega 2 — Diseño y planificación

**Proyecto:** TallerYa · **Cliente:** Taller mecánico · **Integrantes:** Devia Lautaro, Di Prinzio Pedro y Ruesga Lautaro

> Los diagramas están en Mermaid: GitHub los muestra como imagen al abrir este archivo.

## 1. Casos de uso

```mermaid
flowchart LR
    T([Taller])
    C([Cliente])
    V([Visitante])
    B([Gmail<br/>servidor SMTP])

    subgraph TallerYa
        CU1(CU01 Iniciar sesión)
        CU2(CU02 Registrar orden)
        CU3(CU03 Cargar diagnóstico y presupuesto)
        CU4(CU04 Cambiar estado de la orden)
        CU5(CU05 Enviar aviso por email)
        CU6(CU06 Reintentar aviso)
        CU7(CU07 Ver panel y demorados)
        CU8(CU08 Buscar y filtrar órdenes)
        CU9(CU09 Ver mis autos)
        CU10(CU10 Aprobar o rechazar presupuesto)
        CU11(CU11 Consultar estado con patente y código)
    end

    T --- CU1 & CU2 & CU3 & CU4 & CU6 & CU7 & CU8
    C --- CU1 & CU9 & CU10 & CU11
    V --- CU11
    CU4 -. incluye .-> CU5
    CU10 -. incluye .-> CU4
    CU5 --- B
```

### CU04 — Cambiar estado de la orden
| | |
|---|---|
| **Actor** | Taller (o Cliente, solo para aprobar/rechazar) |
| **Precondición** | Sesión iniciada; la orden existe |
| **Flujo principal** | 1. El taller abre el detalle de la orden. 2. El sistema muestra solo los estados a los que se puede pasar. 3. El taller elige uno y opcionalmente escribe un comentario. 4. El sistema valida la transición, guarda el estado y registra el historial. 5. Si el nuevo estado es *Presupuesto listo* o *Listo para retirar*, ejecuta CU05. 6. Muestra "Estado actualizado". |
| **Alternativo 4a** | La transición no es válida (o se quiere presupuestar sin ítems): se muestra el error y no se cambia nada. |
| **Alternativo 5a** | El email falla: el estado queda cambiado, el aviso se guarda como "Falló" y se muestra un mensaje para reintentar o llamar. |
| **Postcondición** | La orden tiene el estado nuevo y queda en el historial. |

### CU10 — Aprobar o rechazar presupuesto
| | |
|---|---|
| **Actor** | Cliente |
| **Precondición** | El cliente inició sesión; la orden es suya y está en *Presupuesto listo* |
| **Flujo principal** | 1. El cliente entra a *Mis autos* y toca *Ver presupuesto*. 2. El sistema muestra el detalle y el total. 3. El cliente toca *Aprobar*. 4. El sistema ejecuta CU04 (a *Aprobado*) con el comentario "Aprobado por el cliente desde la web". 5. Muestra la confirmación. |
| **Alternativo 3a** | El cliente toca *Rechazar*: el sistema pide confirmación y pasa la orden a *Rechazado*. |
| **Excepción** | La orden es de otro cliente: el sistema responde "Orden no encontrada". |

### CU11 — Consultar estado con patente y código
| | |
|---|---|
| **Actor** | Visitante o Cliente |
| **Flujo principal** | 1. Ingresa patente y código. 2. El sistema valida el formato. 3. Busca la orden. 4. Muestra estado, recorrido y presupuesto. |
| **Alternativo** | No coincide: "No encontramos ninguna orden con esa patente y ese código". |

## 2. Arquitectura

Arquitectura **cliente-servidor** en capas, tipo MVC simplificado sin framework:

```mermaid
flowchart TB
    subgraph Navegador
        N[HTML + CSS]
    end
    subgraph "Servidor PHP (InfinityFree)"
        P["public/ — Páginas (controlador + vista)<br/>login, taller/*, cliente/*, seguimiento"]
        L["src/ — Lógica de negocio<br/>Estados · Validador · Presupuesto"]
        S["src/Ordenes.php — Acceso a datos y reglas que usan la base"]
        E["src/Email.php — Cliente SMTP de Gmail"]
    end
    DB[(MySQL)]
    BR[[Servidor SMTP de Gmail<br/>smtp.gmail.com:465]]
    M[/Casilla de email del cliente/]

    N -- "HTTP (formularios GET/POST)" --> P
    P --> L
    P --> S
    S --> DB
    S --> E
    E -- "SMTP cifrado (SSL), timeout 10 s" --> BR
    BR --> M
```

- **public/**: cada página recibe el pedido, valida los datos, llama a la lógica y arma el HTML con `layout.php` (mismo encabezado y menú en todo el sitio).
- **src/Estados.php, Validador.php, Presupuesto.php**: reglas puras, sin base de datos, para poder probarlas solas.
- **src/Ordenes.php**: operaciones sobre la base con PDO y sentencias preparadas; usa transacciones al cambiar de estado.
- **src/Email.php**: encapsula la API externa. Nunca tira excepción: devuelve `ok` o el error, así una caída de Gmail no rompe el sistema.

## 3. Modelo de datos (DER)

```mermaid
erDiagram
    USUARIOS ||--o{ VEHICULOS : "es dueño de"
    VEHICULOS ||--o{ ORDENES : "tiene"
    ORDENES ||--o{ ORDEN_ITEMS : "presupuesto"
    ORDENES ||--o{ HISTORIAL_ESTADOS : "registra"
    USUARIOS ||--o{ HISTORIAL_ESTADOS : "hizo el cambio"
    ORDENES ||--o{ NOTIFICACIONES : "genera"

    USUARIOS {
        int id PK
        varchar nombre
        varchar email UK
        varchar password_hash
        enum rol "taller | cliente"
        varchar telefono
        datetime creado_en
    }
    VEHICULOS {
        int id PK
        int cliente_id FK
        varchar patente UK
        varchar marca
        varchar modelo
        int anio
    }
    ORDENES {
        int id PK
        int vehiculo_id FK
        text problema
        text diagnostico
        varchar estado
        varchar codigo_seguimiento
        datetime fecha_ingreso
        date fecha_estimada
        datetime fecha_entrega
    }
    ORDEN_ITEMS {
        int id PK
        int orden_id FK
        enum tipo "repuesto | mano_obra"
        varchar descripcion
        decimal cantidad
        decimal precio_unitario
    }
    HISTORIAL_ESTADOS {
        int id PK
        int orden_id FK
        varchar estado_anterior
        varchar estado_nuevo
        int usuario_id FK
        varchar comentario
        datetime fecha
    }
    NOTIFICACIONES {
        int id PK
        int orden_id FK
        varchar destinatario
        varchar asunto
        text mensaje
        enum estado "enviada | fallida"
        varchar error
        datetime fecha
    }
```

Decisiones:
- El **total del presupuesto no se guarda**: se calcula sumando los ítems, así nunca queda desactualizado (evita datos redundantes, 3FN).
- El vehículo está separado de la orden porque el mismo auto vuelve varias veces.
- El historial guarda quién hizo cada cambio: sirve para saber si el presupuesto lo aprobó el cliente o el taller.

### Diagrama de estados de una orden (Menú B)

```mermaid
stateDiagram-v2
    [*] --> Ingresado
    Ingresado --> EnDiagnostico: taller
    EnDiagnostico --> PresupuestoListo: taller (con 1+ ítems) ✉️
    PresupuestoListo --> Aprobado: cliente o taller
    PresupuestoListo --> Rechazado: cliente o taller
    Aprobado --> EnReparacion: taller
    EnReparacion --> ListoParaRetirar: taller ✉️
    Rechazado --> ListoParaRetirar: taller ✉️
    ListoParaRetirar --> Entregado: taller
    Entregado --> [*]
```
✉️ = se manda email al cliente. Alerta de **demora**: la orden está en Ingresado, En diagnóstico, Presupuesto listo, Aprobado o En reparación y ya pasó la fecha estimada.

## 4. Diseño de pantallas

**Estilo común a todo el sitio:** barra superior azul oscuro (`#1f3a5f`) con el logo y el menú según el rol; botones de acción naranja (`#e8772e`); tarjetas blancas sobre fondo gris claro; cada estado tiene siempre el mismo color de etiqueta. Una sola hoja de estilos: `public/css/estilos.css`.

| # | Pantalla | Rol | Imagen |
|---|---|---|---|
| 1 | Ingresar | Todos | ![](pantallas/01-login.png) |
| 2 | Consultar mi auto (patente + código) | Visitante | ![](pantallas/02-seguimiento.png) |
| 3 | Panel del taller | Taller | ![](pantallas/03-taller-panel.png) |
| 4 | Órdenes (filtro y búsqueda) | Taller | ![](pantallas/04-taller-ordenes.png) |
| 5 | Nueva orden | Taller | ![](pantallas/05-taller-nueva-orden.png) |
| 6 | Detalle de la orden | Taller | ![](pantallas/06-taller-detalle-orden.png) |
| 7 | Clientes | Taller | ![](pantallas/07-taller-clientes.png) |
| 8 | Mis autos | Cliente | ![](pantallas/08-cliente-inicio.png) |
| 9 | Presupuesto y aprobación | Cliente | ![](pantallas/09-cliente-presupuesto.png) |

## 5. Mapa de navegación

```mermaid
flowchart TD
    I[index] -->|sin sesión| L[Ingresar]
    L <--> S[Consultar mi auto]
    L -->|rol taller| P
    L -->|rol cliente| MA

    subgraph "Menú del taller"
        P[Panel] --- O[Órdenes] --- N[Nueva orden] --- CL[Clientes]
    end
    P -->|Ver| D[Detalle de la orden]
    O -->|Ver| D
    N -->|Crear orden| D

    subgraph "Menú del cliente"
        MA[Mis autos]
    end
    MA -->|Ver presupuesto / detalle| PR[Presupuesto y aprobación]

    P & O & N & CL & D & MA & PR -->|Salir| L
```

## 6. Tecnologías elegidas y por qué

| Tecnología | Por qué |
|---|---|
| **PHP 8 sin framework** | Es lo que ya sabemos usar, así podemos explicar cada línea en la defensa. Un framework (Laravel) agregaría muchas cosas que no necesitamos y que no podríamos justificar en 4 semanas. |
| **MySQL** | Los datos son relacionales (cliente → vehículo → orden → ítems) y necesitamos claves foráneas y transacciones. Lo da gratis el hosting. |
| **HTML + CSS propio** | Pocas pantallas; con una sola hoja de estilos nos aseguramos de que todo el sitio se vea igual. |
| **SMTP de Gmail** | Gratis, y el email sale autenticado desde la cuenta del taller. InfinityFree no permite `mail()` de PHP ni la API de Telegram, y Brevo rechaza un Gmail como remitente sin dominio propio. |
| **InfinityFree** | Hosting gratis con PHP y MySQL para tener una URL pública (extra del TP). |
| **Git + GitHub** | Obligatorio en el TP; cada integrante trabaja en su módulo y se ven los commits de los tres. |
| **Pruebas con un corredor propio en PHP** | Corren con `php tests/run.php` sin instalar nada (PHPUnit necesita Composer). |

## 7. Metodología

Usamos **Kanban** con iteraciones de una semana que coinciden con las entregas:
- Tablero con columnas *Por hacer / Haciendo / Revisión / Hecho* (GitHub Projects).
- Límite de 2 tareas en *Haciendo* por persona.
- Cada integrante sube su parte y otro integrante la revisa.
- Al inicio de cada clase revisamos la devolución del docente y agregamos tareas al backlog.

Elegimos Kanban y no Scrum completo porque somos 3 personas: no tiene sentido tener roles de Scrum Master y Product Owner, pero sí nos sirve ver el flujo de tareas y no tener demasiadas abiertas.

## 8. Backlog

| ID | Tarea | HU | Responsable | Semana | Estado |
|---|---|---|---|---|---|
| T01 | Crear repositorio, estructura de carpetas y `.gitignore` | — | Di Prinzio Pedro | 2 | Hecho |
| T02 | Base de datos: `schema.sql` y `seed.sql` | — | Devia Lautaro | 2 | Hecho |
| T03 | Login, sesión y permisos por rol | — | Ruesga Lautaro | 3 | Hecho |
| T04 | Layout común y hoja de estilos | — | Ruesga Lautaro | 3 | Hecho |
| T05 | Alta de orden con vehículo y cliente | HU01 | Devia Lautaro | 3 | Hecho |
| T06 | Presupuesto: agregar/quitar ítems y totales | HU02 | Devia Lautaro | 3 | Hecho |
| T07 | Reglas de estados e historial | HU03, HU04 | Devia Lautaro | 3 | Hecho |
| T08 | Envío de emails por Gmail SMTP + manejo de errores | HU03 | Di Prinzio Pedro | 3 | Hecho |
| T09 | Registro de avisos y reintento | HU03 | Di Prinzio Pedro | 3 | Hecho |
| T10 | Vista del cliente y aprobación | HU04, HU07 | Ruesga Lautaro | 3 | Hecho |
| T11 | Consulta pública con patente y código | HU05 | Ruesga Lautaro | 3 | Hecho |
| T12 | Panel con alerta de demorados | HU06 | Devia Lautaro | 3 | Hecho |
| T13 | Listado con filtro y búsqueda | — | Devia Lautaro | 3 | Hecho |
| T14 | Publicar en InfinityFree y configurar Gmail | — | Di Prinzio Pedro | 3 | Hecho |
| T15 | Informe teórico y exposición | — | Los tres | 3 | Por hacer |
| T16 | Pruebas automatizadas (mínimo 5) | — | Los tres | 4 | Hecho |
| T17 | Plan de pruebas y manual de usuario | — | Los tres | 4 | Por hacer |
| T18 | Retrospectiva | — | Los tres | 4 | Por hacer |
| T19 | Preparar la defensa (cada uno estudia los módulos de los demás) | — | Los tres | 4 | Por hacer |

> Actualizar los estados y los nombres de los responsables con lo que hagan de verdad. El historial de commits tiene que coincidir con esta asignación.
