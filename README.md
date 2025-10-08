# SCP Easy SMTP

Plugin de WordPress para configuración SMTP con corrección automática de dominios de email mal escritos.

## Características

- **Configuración SMTP externa** - Credenciales en archivo `.ini` fuera del código
- **Corrección automática de dominios** - Corrige typos comunes (gmial.com → gmail.com)
- **Logs de emails** - Registro de emails enviados y correcciones
- **Interfaz con pestañas** - Dashboard, pruebas, logs, estadísticas, debug y ayuda
- **Diseño WordPress standard** - Sigue las guías oficiales de diseño de WordPress
- **Seguridad** - Protección CSRF con nonces
- **Sistema de caché** - Optimización de configuración

## Instalación

1. Sube el plugin a `wp-content/plugins/scp-easy-smtp/`

2. Activa el plugin desde WordPress

3. Crea `scp-config.ini` un nivel por encima de `public_html`:

```ini
[smtp]
host = smtp.ejemplo.com
port = 587
username = tu_usuario@ejemplo.com
password = tu_contraseña
secure = tls
from = noreply@ejemplo.com
fromname = Nombre del Sitio
```

4. Accede a **Ajustes → SCP SMTP** y envía un email de prueba

## Pestañas del Plugin

- **Dashboard** - Estado del sistema y gestión de caché
- **Enviar Prueba** - Formulario para probar envío de emails
- **Logs** - Emails recientes y correcciones realizadas
- **Estadísticas** - Métricas de correcciones por dominio
- **Debug** - Log técnico para diagnóstico
- **Ayuda** - Guía de instalación y configuración

## Corrección de Dominios

El plugin corrige automáticamente typos en:

- **Gmail**: gmial.com, gmeil.com, gmail.con → gmail.com
- **Hotmail**: hotmal.com, hotmial.com → hotmail.com
- **Outlook**: outlok.com, outlook.con → outlook.com
- **Yahoo**: yaho.com, yahooo.com → yahoo.com

### Añadir dominios personalizados

```php
// En functions.php
Scp_Email_Domain_Fixer::add_domain_correction('midominio.cmo', 'midominio.com');
```

## Seguridad

- Archivo de configuración fuera del directorio web
- Protección CSRF con WordPress nonces
- Sanitización de inputs y validación de emails
- Verificación de permisos de usuario

## Modo Debug

Para activar debug SMTP:

```php
// En wp-config.php
define('WP_DEBUG', true);
```

## Hooks Disponibles

```php
// Cuando se corrige un dominio
add_action('scp_smtp_domain_corrected', function($original, $corrected, $email) {
    error_log("Email corregido: $email");
}, 10, 3);
```

## Estructura

```
scp-easy-smtp/
├── scp-easy-smtp.php          # Archivo principal
├── inc/
│   ├── email-domain-fixer.php # Corrección de dominios
│   ├── smtp-config.php        # Gestión de configuración
│   ├── email-logger.php       # Sistema de logs
│   └── debug-logger.php       # Debug técnico
├── admin/
│   └── settings-page.php      # Interfaz de administración
└── assets/
    ├── css/admin.css          # Estilos WordPress standard
    └── js/admin.js            # Interactividad
```

## Licencia

GPL v2 or later

---

**Desarrollado por ScopWeb** | [scopweb.com](https://scopweb.com)
