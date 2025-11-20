# Guía de Seguridad - SCP Easy SMTP

## Resumen de Mejoras de Seguridad Implementadas

Este documento resume todas las medidas de seguridad implementadas en el plugin SCP Easy SMTP.

---

## 🔐 Mejoras Críticas Implementadas

### 1. ✅ Archivo `admin/settings-page.php` Creado
**Problema:** El archivo faltante causaba un error fatal que impedía que el plugin funcionara.

**Solución:** Se ha creado el archivo completo con todas las pestañas de administración y medidas de seguridad:
- Dashboard
- Enviar Prueba
- Logs
- Estadísticas
- Debug
- Ayuda
- Plantillas

**Seguridad implementada:**
- ✅ Verificación de permisos con `current_user_can('manage_options')`
- ✅ Nonces en todos los formularios
- ✅ Sanitización de todos los inputs (`sanitize_key`, `sanitize_email`, `sanitize_text_field`)
- ✅ Escapado de todos los outputs (`esc_html`, `esc_attr`, `esc_url`)
- ✅ Validación de tabs permitidos (whitelist)

---

### 2. ✅ Protección del Archivo de Log

**Problema:** El archivo `wp-content/scp-smtp-debug.log` era accesible públicamente.

**Solución:** Se ha creado `.htaccess` para bloquear el acceso:

```apache
<Files "scp-smtp-debug.log">
    Order allow,deny
    Deny from all
</Files>
```

**⚠️ ACCIÓN REQUERIDA:** Copiar el archivo `.htaccess` al directorio `wp-content/`:
```bash
cp /ruta-del-plugin/.htaccess /ruta-wordpress/wp-content/.htaccess
```

O agregar las reglas al `.htaccess` existente de `wp-content/`.

---

### 3. ✅ Auto-confirmación de Emails DESACTIVADA por Defecto

**Problema:** Se enviaban confirmaciones a cualquier email encontrado en el mensaje, permitiendo uso como spam relay.

**Solución:**
- Auto-confirmación **DESACTIVADA** por defecto
- Solo se activa con el filtro `scp_smtp_enable_auto_confirmation`
- Busca emails solo con patrones específicos (`Email:`, `Correo:`, `From:`)
- Valida que el email no sea del mismo dominio del sitio
- Sanitiza todos los emails antes de enviar

**Para activar (solo si es necesario):**
```php
add_filter( 'scp_smtp_enable_auto_confirmation', '__return_true' );
```

---

### 4. ✅ Sanitización de Parámetros GET

**Problema:** `$_GET['tab']` podía causar XSS.

**Solución:**
- Se sanitiza con `sanitize_key()`
- Se valida contra whitelist de tabs permitidos
- Fallback a 'dashboard' si el tab es inválido

---

### 5. ✅ Validación de Permisos del Archivo de Configuración

**Problema:** El archivo `scp-config.ini` podía tener permisos inseguros (world-readable).

**Solución:**
- Se verifica automáticamente que los permisos sean 600 o 640
- Se muestra advertencia si los permisos son inseguros
- Se loggea en el debug si WP_DEBUG está activo

**⚠️ ACCIÓN REQUERIDA:** Verificar y corregir permisos:
```bash
chmod 600 /ruta-fuera-de-web/scp-config.ini
# o
chmod 640 /ruta-fuera-de-web/scp-config.ini
```

**NUNCA usar:** 644, 664, 666, 777

---

### 6. ✅ Capability Checks en Métodos Públicos

**Problema:** Métodos públicos de `Scp_Email_Domain_Fixer` no verificaban permisos.

**Solución:**
- `add_custom_correction()`: Requiere `manage_options`
- `remove_custom_correction()`: Requiere `manage_options`
- `toggle_default_correction()`: Requiere `manage_options`
- Se loggea cualquier intento no autorizado

---

### 7. ✅ Validación de Dominios Personalizados

**Problema:** No se validaban los dominios antes de añadirlos.

**Solución:**
- Nuevo método `is_valid_domain()` valida:
  - Formato del dominio con regex
  - Longitud máxima (255 caracteres)
  - Validación con `filter_var(FILTER_VALIDATE_EMAIL)`
- Se rechazan dominios inválidos antes de guardarlos

---

### 8. ✅ Protección de Contraseña SMTP en Modo Debug

**Problema:** Con `SMTPDebug = 2`, PHPMailer podía mostrar credenciales en logs.

**Solución:**
- Se usa nivel 4 de debug (más seguro)
- Función personalizada filtra información sensible:
  - Comandos AUTH LOGIN
  - Contraseñas en texto plano
  - Tokens de autenticación
  - API keys
- Los logs muestran `[REDACTED]` en lugar de credenciales

---

### 9. ✅ Mejora de Sanitización en Logger

**Problema:**
- `sanitize_text_field()` truncaba emails largos
- No se validaba la existencia de tablas

**Solución:**
- Se usa `sanitize_email()` para emails
- Se usa `wp_kses_post()` para subjects con HTML
- Se verifica que las tablas existan antes de insertar
- Se validan emails con `is_email()` antes de loggear correcciones
- Manejo silencioso si las tablas no existen (no rompe el sitio)

---

### 10. ✅ Seguridad en Endpoint AJAX

**Implementado en `admin/settings-page.php`:**
- Verificación de nonce con `check_ajax_referer()`
- Verificación de permisos con `current_user_can('manage_options')`
- Sanitización de inputs con `sanitize_email()`
- Validación de emails con `is_email()`
- Respuestas JSON seguras con `wp_send_json_success/error()`

---

## 📋 Checklist de Instalación Segura

### Obligatorio:
- [ ] Verificar que `scp-config.ini` tiene permisos 600 o 640
- [ ] Verificar que `scp-config.ini` está FUERA del directorio web
- [ ] Copiar `.htaccess` a `wp-content/` para proteger los logs
- [ ] Revisar que WP_DEBUG esté DESACTIVADO en producción

### Recomendado:
- [ ] Mantener la auto-confirmación DESACTIVADA (es el default)
- [ ] Revisar periódicamente los logs de debug
- [ ] Limpiar logs antiguos regularmente desde la pestaña "Logs"
- [ ] Mantener WordPress y PHP actualizados

### Opcional:
- [ ] Configurar firewall para bloquear acceso a archivos .ini
- [ ] Usar autenticación de dos factores para admin de WordPress
- [ ] Configurar límites de rate limiting para emails

---

## 🔍 Auditoría de Seguridad

### Pruebas Realizadas:

✅ **XSS (Cross-Site Scripting):**
- Todos los outputs escapados con `esc_html()`, `esc_attr()`, `esc_url()`
- Inputs sanitizados con funciones específicas de WordPress

✅ **CSRF (Cross-Site Request Forgery):**
- Todos los formularios protegidos con nonces
- Verificación con `check_admin_referer()` y `wp_verify_nonce()`

✅ **SQL Injection:**
- Uso de `$wpdb->prepare()` con placeholders
- Uso de `$wpdb->insert()` con tipos de datos especificados
- Uso de `absint()` para valores numéricos

✅ **Path Traversal:**
- Validación de rutas de archivos
- No se aceptan rutas de usuario sin sanitizar

✅ **Authentication Bypass:**
- Verificación de `current_user_can('manage_options')` en todas las páginas admin
- Verificación de permisos en métodos públicos

✅ **Information Disclosure:**
- Credenciales SMTP filtradas en logs de debug
- Advertencias sobre permisos inseguros de archivos
- Logs protegidos con .htaccess

✅ **Email Injection:**
- Validación con `is_email()` y `sanitize_email()`
- Protección contra spam relay (auto-confirmación desactivada)

---

## 🚨 Advertencias de Seguridad

### NO hacer:
- ❌ NO activar auto-confirmación sin entender los riesgos
- ❌ NO poner `scp-config.ini` dentro del directorio web
- ❌ NO usar permisos 644, 666 o 777 para `scp-config.ini`
- ❌ NO desactivar las verificaciones de nonce
- ❌ NO exponer el archivo de log públicamente

### SÍ hacer:
- ✅ Mantener WP_DEBUG = false en producción
- ✅ Revisar logs regularmente
- ✅ Actualizar el plugin cuando haya nuevas versiones
- ✅ Usar contraseñas fuertes para SMTP
- ✅ Revisar permisos de archivos periódicamente

---

## 📞 Soporte

Para reportar problemas de seguridad:
- **Email:** [tu-email-de-seguridad@scopweb.com]
- **Website:** https://scopweb.com

**Por favor, NO reportes vulnerabilidades públicamente. Envía un email privado.**

---

## 📝 Changelog de Seguridad

### Versión 2.1.0 (2025-01-20)

**Crítico:**
- ✅ Creado archivo `admin/settings-page.php` faltante
- ✅ Protección de logs con .htaccess
- ✅ Auto-confirmación desactivada por defecto

**Alto:**
- ✅ Validación de permisos de archivos de configuración
- ✅ Protección de credenciales en logs de debug
- ✅ Capability checks en métodos públicos

**Medio:**
- ✅ Validación de dominios personalizados
- ✅ Sanitización mejorada en logger
- ✅ Sanitización de parámetros GET

**Bajo:**
- ✅ Documentación de seguridad

---

**Desarrollado por:** ScopWeb
**Versión:** 2.1.0
**Fecha:** 2025-01-20
**Licencia:** GPL v2 or later
