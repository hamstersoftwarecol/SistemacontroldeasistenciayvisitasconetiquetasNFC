# AsistenciaNFC

Sistema de **control de asistencia y visitas con etiquetas NFC** e **inteligencia artificial**, construido con **PHP 8.3, Laravel 13 + Breeze (Blade) y SQLite** (también funciona con MySQL).

Con solo acercar el teléfono a una etiqueta NFC el sistema registra la asistencia y monitoriza las visitas del personal en tiempo real. **No requiere Arduino ni reloj biométrico.**

> Ideal para hoteles, colegios, hospitales, oficinas, personal de limpieza, almacenes y, en general, para controlar la asistencia y ubicación de empleados.

---

## Funcionalidades

| Módulo | Qué hace |
|---|---|
| **Registro NFC de entrada/salida** | Cada ubicación tiene una etiqueta NFC con una URL única. El **primer toque del día es la entrada** y el **último toque es la salida**; los toques intermedios quedan como visitas. Se ignoran lecturas dobles (configurable). |
| **Comprobante de tiempo y comentarios** | Cada toque genera un comprobante con hora exacta, ubicación, método y **código de verificación**. El empleado puede añadir comentario y **foto de evidencia**. |
| **Equipo en tiempo real** | Panel que se actualiza solo: quién está en turno, quién llegó tarde, última ubicación y hora de cada persona. |
| **Historial completo** | Asistencias (con corrección y registro manual auditado) y visitas con filtros por fecha, empleado, ubicación y tipo. |
| **Gestión de ubicaciones** | Alta de habitaciones/oficinas/zonas, estadísticas de visitas, regeneración del enlace (invalida etiquetas antiguas). |
| **Escritor de etiquetas NFC + bloqueo** | Graba la URL en la etiqueta desde Chrome para Android (Web NFC) y la **bloquea en modo solo lectura** para evitar manipulaciones. También lee/verifica etiquetas. |
| **Mi rastreador + Mi historial** | El empleado ve su día, escanea etiquetas sin salir de la app, consulta su historial mensual, horas, tardanzas y ausencias. |
| **Modo kiosco** | Un teléfono/tablet fijo lee las **tarjetas NFC de los empleados** (Web NFC o lector USB tipo teclado) o el código de empleado. |
| **Gestión de quejas** | Quejas internas y **formulario público por ubicación** (huéspedes, pacientes, clientes), asignación, notas internas, estados y adjuntos. |
| **Notificaciones SMTP** | Nueva queja, cambios de estado, asignaciones, llegadas tarde, difusiones, resumen diario y fallos de copia. SMTP configurable desde el panel. |
| **Informes** | Asistencia, visitas y quejas; **imprimir / guardar como PDF** y **exportar a Excel (.xlsx) y CSV**. |
| **Copia de seguridad automática en Google Drive** | Copia diaria (base de datos + fotos/adjuntos) en ZIP, retención configurable y subida automática a Google Drive (OAuth). |
| **Chat y difusión** | Canal general del equipo, mensajes directos y difusiones (avisos) con prioridad y envío opcional por correo. |
| **Asistente de IA conectado a la base de datos** | Preguntas en lenguaje natural («¿quién llegó tarde hoy?», «horas por empleado esta semana») respondidas con datos reales mediante herramientas de solo lectura. Usa Claude (Anthropic). |
| **Roles y permisos por usuario** | Administrador, supervisor y empleado, con **permisos personalizables por usuario**. |
| **Interfaz móvil** | Diseño *mobile-first*, barra de navegación inferior, botón central «Marcar» e instalable como app (PWA). |

---

## Cómo funciona el NFC

```
[Etiqueta NFC en la Habitación 101]  ──contiene──▶  https://tu-dominio.com/t/Xk29...
            │
   el empleado acerca su teléfono
            ▼
 El teléfono abre la URL ─▶ el sistema identifica al empleado (sesión iniciada)
                          ─▶ registra el toque y muestra el comprobante
```

- **Android y iPhone** abren la URL automáticamente al acercar el teléfono (iPhone XS o superior).
- En **Chrome para Android** también se puede escanear desde la propia app (*Mi rastreador → Escanear etiqueta*), sin abrir pestañas.
- La sesión dura 7 días (`SESSION_LIFETIME`) y «Mantener la sesión iniciada» viene marcado, para no pedir contraseña en cada toque.
- Si alguien abre el enlace desde otro sitio web, se pide confirmación (protección contra registros falsos).
- Etiquetas recomendadas: **NTAG213/215/216**.

### Reglas de asistencia

- Primer toque del día = **entrada**; último toque = **salida**; horas trabajadas = salida − entrada.
- **Tarde** si la entrada supera la hora de inicio + minutos de tolerancia (horario general o por empleado).
- **Turnos nocturnos**: si el turno del empleado termina después de medianoche (p. ej. 22:00–06:00), los toques de madrugada cuentan para el día anterior.
- **Ausencia**: día laborable sin ningún toque.

---

## Requisitos

- PHP 8.3+ con extensiones `pdo_sqlite` (o `pdo_mysql`), `mbstring`, `openssl`, `zip`, `intl`, `fileinfo`
- Composer 2
- Node.js 20+ (solo para compilar los estilos)
- **HTTPS en producción** (Web NFC y la cámara del teléfono lo requieren)

## Instalación

```bash
git clone https://github.com/hamstersoftwarecol/asistenciaNFC.git
cd asistenciaNFC

composer install
cp .env.example .env
php artisan key:generate

# Base de datos SQLite
touch database/database.sqlite
php artisan migrate --seed            # crea el administrador (ADMIN_EMAIL / ADMIN_PASSWORD del .env)

npm install && npm run build
php artisan serve
```

Entra en `http://localhost:8000` con **admin@example.com / password** (cámbialo en `.env` antes de sembrar o desde *Mi perfil*).

### Datos de demostración (opcional)

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
```

Crea un supervisor (`supervisor@example.com`), seis empleados (`empleado1@example.com` …), ubicaciones de hotel, dos semanas de asistencia, quejas, una difusión y mensajes. Contraseña de todos: `password`.

### Crear o promover un administrador

```bash
php artisan nfc:admin jefe@empresa.com --name="Jefe" --password="clave-segura"
```

### Variables importantes del `.env`

| Variable | Descripción |
|---|---|
| `APP_URL` | **URL pública** (con HTTPS). Es la que se graba en las etiquetas. |
| `APP_TIMEZONE` | Zona horaria de la empresa (p. ej. `America/Bogota`, `America/Mexico_City`, `Europe/Madrid`). |
| `DB_CONNECTION` | `sqlite` (por defecto) o `mysql`. |
| `SESSION_LIFETIME` | Minutos de sesión (por defecto 7 días). |
| `ANTHROPIC_API_KEY` | Opcional: clave de Claude (también se puede guardar en *Ajustes*). |
| `ADMIN_*` | Datos del administrador inicial. |

---

## Tareas programadas (cron)

Copias automáticas, envío de correos en cola y resumen diario dependen del programador de Laravel. Agrega **una sola línea** al cron del servidor:

```cron
* * * * * cd /ruta/a/asistenciaNFC && php artisan schedule:run >> /dev/null 2>&1
```

El programador también procesa la cola de correos cada minuto (`queue:work --stop-when-empty`), así que **funciona en hosting compartido** sin un *worker* permanente. En *Ajustes* y *Copias de seguridad* se muestra si el cron está funcionando.

## Puesta en marcha paso a paso

1. **Ajustes → General / Horario**: nombre de la empresa, horario, tolerancia, días laborables.
2. **Usuarios**: crea al personal (rol, departamento, turno propio, código de empleado y UID de tarjeta NFC para el kiosco). Marca «Enviar correo de bienvenida» para que cada persona defina su contraseña.
3. **Ubicaciones**: crea cada habitación, oficina o zona.
4. **Escritor NFC** (en un Android con Chrome): elige la ubicación, acerca la etiqueta, grábala y, si quieres, **bloquéala**. Desde iPhone u otros equipos usa «Copiar URL» y grábala con *NFC Tools* (registro tipo URL).
5. Pega las etiquetas y ¡listo! El personal solo tiene que acercar el teléfono.

### Modo kiosco (tarjetas de empleado)

En *Modo kiosco* elige la ubicación. Funciona con:

- **Chrome para Android** con NFC: el propio dispositivo lee las tarjetas.
- **Lector NFC USB** tipo teclado (PC o tablet): el UID se escribe y se envía solo.
- **Código de empleado** escrito a mano.

### Quejas públicas

Cada ubicación tiene un **formulario público** (*Ubicaciones → ficha → Formulario público de quejas*) con un enlace distinto al de asistencia. Puedes imprimirlo como código QR o grabarlo en otra etiqueta NFC para huéspedes o pacientes. Se puede desactivar en *Ajustes → General*.

---

## Correo SMTP

*Ajustes → Correo SMTP*: servidor, puerto, usuario, contraseña (se guarda cifrada) y remitente. Incluye **envío de prueba**.

Ejemplo con Gmail: `smtp.gmail.com`, puerto `587`, TLS y una *contraseña de aplicación* (requiere verificación en dos pasos).

## Copias de seguridad y Google Drive

1. En [Google Cloud Console](https://console.cloud.google.com/apis/credentials) habilita la **Google Drive API**.
2. Crea un **ID de cliente OAuth** de tipo *Aplicación web* y agrega como URI de redirección:
   `https://tu-dominio.com/copias/google/callback`
3. Copia *Client ID* y *Client Secret* en **Ajustes → Copias de seguridad**.
4. En **Copias de seguridad** pulsa **Conectar con Google Drive** y autoriza la cuenta.

El sistema crea su propia carpeta en Drive (alcance `drive.file`: solo ve lo que él mismo sube). Cada ZIP contiene la base de datos (`database.sqlite`, o `database.sql` con MySQL) y la carpeta `archivos/` con fotos y adjuntos.

**Restaurar**: detén la app, reemplaza `database/database.sqlite` por el de la copia y copia `archivos/*` en `storage/app/private/`.

Comando manual: `php artisan backup:run`.

## Asistente de IA

Usa **Claude** (Anthropic) a través del SDK oficial de PHP. Configura la clave en *Ajustes → Asistente IA* (se guarda cifrada) o con `ANTHROPIC_API_KEY`. El modelo por defecto es `claude-opus-5-5`; se puede elegir otro y el nivel de esfuerzo.

- Consulta la base de datos **solo con herramientas de lectura**: estado del equipo, registros y resumen de asistencia, visitas, estadísticas por ubicación, quejas, personal y ubicaciones.
- **Respeta los permisos**: un empleado solo puede preguntar por sus propios datos; quien tiene el permiso «IA con datos de todo el personal» puede consultar a todos.
- Las conversaciones se guardan por usuario.

---

## Roles y permisos

| Permiso | Admin | Supervisor | Empleado |
|---|:-:|:-:|:-:|
| Panel del equipo en tiempo real | ✅ | ✅ | |
| Ver asistencia y visitas de todos | ✅ | ✅ | |
| Corregir / registrar asistencia manual | ✅ | | |
| Modo kiosco | ✅ | ✅ | |
| Gestionar ubicaciones | ✅ | | |
| Escribir y bloquear etiquetas NFC | ✅ | | |
| Chat del equipo | ✅ | ✅ | ✅ |
| Enviar difusiones | ✅ | ✅ | |
| Gestionar quejas | ✅ | ✅ | |
| Informes (imprimir / exportar) | ✅ | ✅ | |
| Asistente de IA | ✅ | ✅ | ✅ (solo sus datos) |
| Usuarios, ajustes y copias de seguridad | ✅ | | |

Todos pueden marcar asistencia, ver su rastreador e historial y reportar quejas. En la ficha de cada usuario se pueden **personalizar los permisos** individualmente.

---

## Usar MySQL en lugar de SQLite

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=asistencia_nfc
DB_USERNAME=usuario
DB_PASSWORD=clave
```

Luego `php artisan migrate --seed`. Las copias usan `mysqldump` si está instalado (si no, exportan las tablas a JSON).

## Desarrollo y pruebas

```bash
composer run dev        # servidor + cola + Vite
php artisan test        # pruebas automatizadas
vendor/bin/pint         # estilo de código
```

## Estructura principal

```
app/
  Enums/                 Roles y catálogo de permisos
  Services/
    AttendanceService    Reglas de toques (entrada, visitas, salida, tardanza, turnos nocturnos)
    TeamStatusService    Estado del equipo en tiempo real
    ReportService        Datos de informes
    BackupService        Copias de seguridad
    GoogleDriveService   Subida a Google Drive (OAuth)
    Ai/                  Asistente IA (SDK de Claude + herramientas de solo lectura)
  Support/SpreadsheetExporter   Exportación CSV / XLSX sin dependencias
resources/js/components/  Escáner y escritor Web NFC, kiosco, panel en vivo, chat, asistente IA
```

## Licencia

MIT
