# CLAUDE.md - Contexto Técnico del Plugin

## Descripción General

Plugin de WordPress para configuración SMTP con corrección automática de typos en dominios de email (gmial.com → gmail.com).

## Arquitectura

**Patrón:** Modular con separación de responsabilidades

```
Main Plugin (scp-easy-smtp.php)
├── Scp_SMTP_Config (inc/smtp-config.php)
├── Scp_Email_Domain_Fixer (inc/email-domain-fixer.php)
├── Scp_Email_Logger (inc/email-logger.php)
└── Scp_SMTP_Debug_Logger (inc/debug-logger.php)
```

## Flujo de Ejecución

1. **Configuración SMTP**: Hook `phpmailer_init` con prioridad 999
2. **Procesamiento de emails**: Filter `wp_mail` con prioridad 1
3. **Corrección de dominios**: Se corrige tanto `$args['to']` como emails en el body del mensaje
4. **Logging**: Se registra en BD (si existen las tablas) y en archivo debug

## Puntos Críticos

### ⚠️ IMPORTANTE: wp_mail es un FILTER, no un action

```php
// ✅ CORRECTO
add_filter('wp_mail', [$this, 'process_email'], 1, 1);

public function process_email($args) {
    // ... hacer correcciones
    return $args; // SIEMPRE retornar $args
}

// ❌ INCORRECTO
add_action('wp_mail', ...); // Esto NO funciona
```

### Corrección de Dominios

Se deben corregir **DOS lugares**:

1. **Campo `to`** del array `$args`:
```php
if (!empty($args['to'])) {
    $original_to = is_array($args['to']) ? $args['to'][0] : $args['to'];
    $corrected_to = Scp_Email_Domain_Fixer::fix_email_domain($original_to);

    if ($corrected_to !== $original_to) {
        if (is_array($args['to'])) {
            $args['to'][0] = $corrected_to;
        } else {
            $args['to'] = $corrected_to;
        }
    }
}
```

2. **Emails en el body del mensaje** (si existen):
```php
preg_match('/Email: ([^\s]+)/', $args['message'], $matches);
if (!empty($matches[1])) {
    $corrected = Scp_Email_Domain_Fixer::fix_email_domain($matches[1]);
    $args['message'] = str_replace('Email: ' . $matches[1], 'Email: ' . $corrected, $args['message']);
}
```

### Logger - Tablas Opcionales

El logger debe verificar si existen las tablas antes de escribir:

```php
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
if (!$table_exists) {
    return; // Silently skip
}
```

### Static Variables - Lazy Initialization

Para variables estáticas en clases de debug, usar lazy initialization:

```php
private static $log_file = null;

private static function init() {
    if (self::$log_file === null) {
        self::$log_file = WP_CONTENT_DIR . '/scp-smtp-debug.log';
    }
}

public static function log($message) {
    self::init(); // Llamar antes de usar $log_file
    error_log($message, 3, self::$log_file);
}
```

## Configuración Externa

**Ubicación:** Un nivel por encima de `public_html/`

```ini
# scp-config.ini
[smtp]
host = smtp.ejemplo.com
port = 587
username = usuario@ejemplo.com
password = contraseña
secure = tls
from = noreply@ejemplo.com
fromname = Nombre Sitio
```

**Caché:** Se usa `transient` con 1 hora de duración para evitar leer el archivo constantemente.

## Interfaz Admin

**Pestañas:** Dashboard, Test, Logs, Stats, Debug, Help

**Formularios:** Todos incluyen nonces para CSRF:
```php
<?php wp_nonce_field('scp_smtp_test_email_action', 'scp_smtp_test_email_nonce'); ?>

// Verificación
if (!check_admin_referer('scp_smtp_test_email_action', 'scp_smtp_test_email_nonce')) {
    wp_die('Error de seguridad');
}
```

**Envío de formularios:** Usar `<input type="hidden" name="action_name" value="1">` en vez de `<button name="action_name">` porque el atributo `name` en botones no siempre se envía en POST.

## Estilos

**Guía:** WordPress Design Standards

- **Colores principales:** `#2271b1` (primary), `#135e96` (hover)
- **Bordes:** `#c3c4c7`, `#dcdcde`
- **Textos:** `#1d2327`, `#2c3338`, `#646970`
- **Border-radius:** 2px (mínimo)
- **Sombras:** `0 1px 1px rgba(0,0,0,0.04)` (sutil)
- **NO usar:** Gradientes, animaciones excesivas, border-radius > 4px

## Dominios Corregidos

Actualmente soporta:
- Gmail: gmial, gmeil, gmai, gmail.con
- Hotmail: hotmal, hotmial, hotmail.con
- Outlook: outlok, outlook.con
- Yahoo: yaho, yahooo, yahoo.con

**Añadir nuevos:**
```php
Scp_Email_Domain_Fixer::add_domain_correction('typo.com', 'correcto.com');
```

## Debugging

**Activar debug SMTP:**
```php
// wp-config.php
define('WP_DEBUG', true);
```

**Ver log debug:**
`wp-content/scp-smtp-debug.log`

**Tool de diagnóstico:**
Crear `test-smtp.php` en raíz del plugin:
```php
<?php
define('WP_USE_THEMES', false);
require_once('../../../../wp-load.php');

// Ver config, hooks, enviar test
```

## Problemas Comunes

### Email no se envía
- Verificar que `add_filter` (no `add_action`) esté en `wp_mail`
- Verificar que `process_email()` retorne `$args`
- Revisar que scp-config.ini exista y tenga permisos de lectura

### Parse Error en settings-page.php
- Verificar cierre de todos los `<?php if (): ?>` con `<?php endif; ?>`
- No mezclar sintaxis `if {}` con `if: endif;`

### Deprecated warnings
- No pasar `null` a funciones que esperan strings
- Inicializar variables estáticas con `init()` method

### Formulario no envía POST
- Usar `<input type="hidden">` en vez de `<button name="">`
- Verificar nonces

## Bases de Datos

**Tablas (opcionales):**
- `wp_scp_smtp_emails` - Log de emails enviados
- `wp_scp_smtp_corrections` - Historial de correcciones

Si no existen, el plugin funciona igualmente (solo no guarda logs en BD).

## Seguridad

- ✅ Nonces en todos los formularios
- ✅ `sanitize_email()`, `sanitize_text_field()`
- ✅ `esc_html()`, `esc_attr()` en outputs
- ✅ `check_admin_referer()` antes de procesar POST
- ✅ `current_user_can('manage_options')` en admin pages
- ✅ Configuración fuera de directorio web

## Testing

**Email de prueba desde admin:**
1. Ir a pestaña "Enviar Prueba"
2. Ingresar email con typo (ej: scopweb@gmial.com)
3. Verificar que llegue a gmail.com
4. Revisar pestaña "Logs" para ver la corrección

**Testing programático:**
```php
$result = wp_mail('test@gmial.com', 'Asunto', 'Mensaje');
// Debe enviar a test@gmail.com
```

## Hooks Personalizados

```php
// Cuando se corrige un dominio
do_action('scp_smtp_domain_corrected', $original_domain, $corrected_domain, $full_email);

// Uso:
add_action('scp_smtp_domain_corrected', function($orig, $corrected, $email) {
    error_log("Corrección: $orig → $corrected para $email");
}, 10, 3);
```

## Versión

**Actual:** 2.0
**WordPress mínimo:** 5.0
**PHP mínimo:** 7.2
**Licencia:** GPL v2 or later

---

**Desarrollado por:** ScopWeb
**Website:** scopweb.com
