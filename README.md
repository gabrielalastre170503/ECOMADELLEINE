<div align="center">

# 🩺 EcoMadelleine

### Sistema de gestión integral para clínicas y consultorios de **ecografía**

Agenda de citas · Informes ecográficos con firma electrónica · Facturación · Portal del paciente · Notificaciones · Cumplimiento médico

<br>

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-MySQL-003545?style=for-the-badge&logo=mariadb&logoColor=white)
![JavaScript](https://img.shields.io/badge/JS-Vanilla-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Apache](https://img.shields.io/badge/Apache-XAMPP-D22128?style=for-the-badge&logo=apache&logoColor=white)
![Sin frameworks](https://img.shields.io/badge/Dependencias-0%20(vanilla)-2ea44f?style=for-the-badge)

</div>

---

## 📋 Tabla de contenidos

- [¿Qué es?](#-qué-es)
- [Características](#-características)
- [Stack tecnológico](#️-stack-tecnológico)
- [Arquitectura](#️-arquitectura)
- [Estructura del proyecto](#️-estructura-del-proyecto)
- [Seguridad y cumplimiento](#-seguridad-y-cumplimiento)
- [Instalación](#-instalación)
- [Configuración (.env)](#️-configuración-env)
- [Roles y navegación](#-roles-y-navegación)
- [Modelo de datos](#️-modelo-de-datos)
- [Tareas programadas (cron)](#-tareas-programadas-cron)
- [Autor](#-autor)

---

## 🔎 ¿Qué es?

**EcoMadelleine** es una aplicación web completa para administrar el día a día de un centro de diagnóstico por **ultrasonido / ecografía**: pacientes, agenda, estudios e informes, facturación y comunicación con el paciente — todo en un solo lugar, con **cuatro roles** diferenciados (administrador, ecografista, recepcionista y paciente).

Está construida **sin frameworks ni dependencias externas** (PHP + JavaScript/CSS *vanilla*), sobre un **front controller con URLs limpias**, organizada por **dominios**, y con un enfoque serio en **seguridad y cumplimiento médico** (firma electrónica de informes, consentimiento informado, retención de datos, auditoría).

---

## ✨ Características

### 🗓️ Agenda y citas
- Solicitud de cita por el paciente y programación por recepción/ecografista.
- Calendario interactivo (FullCalendar), disponibilidad por horarios recurrentes y excepciones.
- Propuesta y aceptación de nuevas fechas, reprogramación, cancelación.
- **Recordatorios automáticos** por correo (cron) y notificaciones in-app.
- Detección de **conflictos de horario** y control de no-asistencia.

### 🧾 Informes ecográficos (el producto)
- Creación de informes por tipo de estudio (catálogo de ecografías) con plantillas dinámicas.
- Adjuntos (imágenes del estudio, PDFs).
- **Firma electrónica trazable**: huella **SHA-256** del contenido (integridad) + **sello HMAC-SHA256** del servidor (autenticidad y no-repudio del servidor).
- **Compartir resultados por enlace con token** (acceso de solo lectura, sin login) para el paciente.
- Generación de PDF e impresión.

### 👥 Gestión clínica
- Ficha de paciente, historia clínica, notas de sesión y notas clínicas.
- Directorio de ecografistas y especialidades.
- Encuestas de satisfacción.

### 💳 Facturación y reportes
- Registro de pagos y precios por tipo de estudio.
- Panel de **reportes y estadísticas** con gráficos (Chart.js): crecimiento, carga de trabajo, tipos de estudio, tiempos de respuesta, distribución por edad, etc.

### 🔐 Identidad y acceso
- Registro con **verificación de correo**, login, **recuperación de contraseña** por token.
- **Autenticación de dos factores (2FA)** por código.
- **Consentimiento informado** obligatorio antes de usar el sistema (gate).
- **Bitácora de auditoría** de acciones sensibles.

### 🌐 Portal del paciente
- Mis citas, solicitar cita, mis informes, ecografistas activos, preparación de estudios, precios, FAQ y centro de ayuda.

---

## 🛠️ Stack tecnológico

| Capa | Tecnología |
|---|---|
| **Backend** | PHP 8.2 (procedural, `mysqli` en modo excepción) |
| **Base de datos** | MariaDB / MySQL |
| **Frontend** | JavaScript + CSS *vanilla* (sin build, sin npm) |
| **Servidor** | Apache (XAMPP) con `mod_rewrite` |
| **Enrutado** | Front controller propio con **URLs limpias** |
| **Librerías (CDN)** | FullCalendar, Chart.js, Flatpickr, Font Awesome |
| **Dependencias** | **Ninguna** (sin Composer ni node_modules) |

---

## 🏗️ Arquitectura

El sistema usa un **front controller** con **URLs limpias**. Apache reescribe toda ruta que no sea un archivo/carpeta real hacia `router.php`, que la resuelve contra la tabla de rutas y ejecuta el handler correspondiente.

```
Petición  ─►  .htaccess (mod_rewrite)  ─►  router.php  ─►  core/routes.php
                  │ (si NO es -f / -d)                         │
                  ▼                                            ▼
            archivos reales (assets, api, ...)        handler del dominio (admin/, paciente/, ...)
                                                              │
                                            bootstrap.php (auto_prepend: sesión, CSP, CSRF, eco_url)
```

- **`bootstrap.php`** se auto-carga en cada petición (`auto_prepend_file`): endurece la sesión, fija cabeceras de seguridad (CSP), expone helpers **CSRF** y **`eco_url()`** (base robusta derivada de la raíz del proyecto).
- El código está organizado **por dominio/feature**, no por tipo de archivo (*screaming architecture*).

---

## 🗂️ Estructura del proyecto

```
Sistema_EcoMadelleineV1/
├── index.php              # Landing público (DirectoryIndex)
├── router.php             # Front controller
├── bootstrap.php          # Arranque global (sesión, CSP, CSRF, eco_url)
├── .htaccess              # mod_rewrite + cabeceras + protección de archivos
│
├── core/                  # conexión a BD (conexion.php) + tabla de rutas (routes.php)
├── config/                # env_loader + config_correo (carga de .env / secretos)
├── lib/                   # Lógica de negocio (por dominio)
│   ├── citas/  comunicaciones/  facturacion/  informes/
│   ├── personal/  reportes/  seguridad/  core/  (Router, tokens, pdf, paginación)
│
├── layouts/               # shell.php + partials (sidebar, modales, vistas)
├── assets/                # CSS y JS (por feature)
├── api/                   # ~80 endpoints AJAX (get_*, buscar_*, guardar_*, ...)
│
├── admin/                 # Páginas del administrador
├── ecografista/           # Páginas del ecografista
├── recepcion/             # Páginas de recepción
├── paciente/              # Portal del paciente
├── common/                # Páginas cross-rol (dashboard, perfil, reportes, agenda...)
├── auth/                  # login, registro, recuperar, 2FA, logout
├── informes/              # Ver / crear / gestionar informes
├── publico/               # Páginas sin login (verificación de correo, resultados por token)
│
├── cli/                   # Scripts de cron (recordatorios, purga de retención)
├── database/              # Migraciones y seeds (+ backups, ignorados)
├── uploads/               # Imágenes médicas de pacientes (datos sensibles)
├── documentos/            # Repositorio de documentos
└── docs/                  # Documentación (SEGURIDAD_OPS.md)
```

---

## 🔐 Seguridad y cumplimiento

EcoMadelleine maneja **datos clínicos sensibles**, así que la seguridad es parte del diseño:

- **Secretos fuera del código** — credenciales en `.env` (gitignored); usuario MySQL **no-root** (`eco_app`) con privilegios mínimos.
- **Content Security Policy** + cabeceras de protección (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`) globales vía `bootstrap.php`.
- **Protección CSRF** en todas las acciones que cambian estado (token por sesión, validación en tiempo constante).
- **2FA** por código y endurecimiento de sesión (`HttpOnly`, `SameSite`, `Secure` en HTTPS, *strict mode*).
- **Firma electrónica de informes** — SHA-256 (integridad) + HMAC-SHA256 con clave de servidor (autenticidad). *Honesto sobre su alcance:* es un **sello de servidor verificable**, no una firma cualificada con PKI/TSA externa.
- **Consentimiento informado** obligatorio + **política de retención** de datos efímeros (purga programada).
- **Bitácora de auditoría** de acciones sensibles.
- Archivos sensibles (`.env`, `bootstrap.php`, `config_correo.php`, etc.) **denegados** por `.htaccess`; `uploads/` con su propio `.htaccess`.

> 📖 Más detalle operativo en [`docs/SEGURIDAD_OPS.md`](docs/SEGURIDAD_OPS.md).

---

## 🚀 Instalación

### Requisitos
- **XAMPP** (Apache + PHP **8.2** + MariaDB/MySQL) con `mod_rewrite` y `mod_headers` activos.

### Pasos

```bash
# 1. Clonar en el htdocs de XAMPP
cd C:/xampp/htdocs
git clone https://github.com/gabrielalastre170503/ECOMADELLEINE.git Sistema_EcoMadelleineV1

# 2. Crear la base de datos e importar el esquema/seeds
#    (crea db_clinica_ecografias y ejecuta los scripts de database/)

# 3. Variables de entorno
cp .env.example .env
#    Edita .env: credenciales de BD, SMTP, etc.
```

4. **Usuario de BD** (recomendado, no usar root):
```sql
CREATE USER 'eco_app'@'localhost' IDENTIFIED BY 'TU_CLAVE_SEGURA';
GRANT SELECT, INSERT, UPDATE, DELETE ON db_clinica_ecografias.* TO 'eco_app'@'localhost';
FLUSH PRIVILEGES;
```

5. **`.htaccess`** — verifica que la ruta de `auto_prepend_file` apunte a tu `bootstrap.php` y que `RewriteBase` coincida con la subcarpeta de instalación.

6. **Cron** — programa los scripts de `cli/` (ver [Tareas programadas](#-tareas-programadas-cron)).

7. Abre **`http://localhost/Sistema_EcoMadelleineV1/`** 🎉

---

## ⚙️ Configuración (.env)

| Variable | Descripción |
|---|---|
| `DB_HOST` / `DB_NAME` | Host y nombre de la base de datos |
| `DB_USER` / `DB_PASS` | Usuario (no-root) y contraseña de BD |
| `APP_ENV` | `development` \| `production` |
| `SMTP_HOST` / `SMTP_PORT` | Servidor SMTP saliente (ej. Gmail `smtp.gmail.com:587`) |
| `SMTP_USER` / `SMTP_PASS` | Cuenta y **contraseña de aplicación** (16 car.) |
| `MAIL_FROM_EMAIL` / `MAIL_FROM_NAME` | Remitente de los correos |
| `MAIL_TO_EMAIL` | Destino del formulario de ayuda |

> ⚠️ El `.env` y `config/config_correo.php` contienen secretos reales — **nunca** se suben a Git.

---

## 🧭 Roles y navegación

Acceso por **URLs limpias**. Resumen por rol:

| Rol | Rutas principales |
|---|---|
| 🛡️ **Administrador** | `/dashboard` · `/personal` · `/usuarios` · `/especialidades` · `/repositorio` · `/contenido` · `/auditoria` · `/reportes` |
| 🩻 **Ecografista** | `/dashboard` · `/mis-pacientes` · `/mi-agenda` · `/proximas-citas` · `/solicitudes` · `/disponibilidad` · `/notas-sesion` · `/nuevo-informe` |
| 🧑‍💼 **Recepcionista** | `/dashboard` · `/citas-pendientes` · `/gestion-pacientes` · `/agenda` · `/directorio` · `/facturacion` |
| 🧑‍🦰 **Paciente** | `/mis-citas` · `/solicitar-cita` · `/mis-informes` · `/ecografistas` · `/preparacion` · `/precios` · `/faq` · `/ayuda` |
| 🌍 **Público** | `/login` · `/registro` · `/recuperar` · `/privacidad` |

---

## 🗄️ Modelo de datos

Principales tablas de `db_clinica_ecografias`:

- **`usuarios`** · **`especialidades`** · `usuario_especialidades` — personas y roles.
- **`citas`** · `cita_eventos` · `cita_recordatorios` · `horarios_recurrentes` · `disponibilidad_excepciones` — agenda.
- **`informes_estudios`** · `informe_archivos` · `tipos_ecografias` · `notas_clinicas` — estudios e informes.
- `notificaciones` · `consentimientos` · `descarga_tokens` · `encuestas` — flujo y cumplimiento.
- `auditoria` · `intentos_login` · `contenido_web` · `faqs` — seguridad y contenido.

---

## ⏰ Tareas programadas (cron)

| Tarea | Script | Frecuencia sugerida |
|---|---|---|
| **Recordatorios de cita** | `cli/cron_recordatorios.php` | cada 30 min |
| **Purga de retención** | `cli/mantenimiento_retencion.php --apply` | semanal (dom. 03:00) |

**Windows (Task Scheduler):**
```bat
schtasks /create /tn "EcoRecordatorios" /tr "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\Sistema_EcoMadelleineV1\cli\cron_recordatorios.php\"" /sc minute /mo 30
```

`cron_recordatorios.php` también se puede disparar por **token** (`?key=ECO_CRON_KEY`) o desde el panel (botón "ejecutar ahora", con CSRF).

---

## 👤 Autor

**Gabriel Alastre** — [@gabrielalastre170503](https://github.com/gabrielalastre170503)

<div align="center">

---

Hecho con ☕ y PHP *vanilla*. **EcoMadelleine** · Centro de Diagnóstico por Ultrasonido.

</div>
