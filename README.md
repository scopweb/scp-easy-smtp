# SCP Easy SMTP: Configuración SMTP Avanzada para WordPress

**Versión:** 2.1.0  
**Autor:** ScopWeb  
**Sitio Web:** [scopweb.com](https://scopweb.com)

Un plugin de WordPress robusto y seguro para gestionar el envío de correos a través de un servidor SMTP externo. Incluye un gestor visual de corrección de dominios, plantillas multi-idioma, logs detallados y una interfaz de administración intuitiva.

---

## ✨ Características Principales

- **Configuración SMTP Segura:** Almacena tus credenciales SMTP en un archivo `.ini` fuera del directorio web público para máxima seguridad.
- **Gestor de Corrección de Dominios:** Corrige automáticamente errores de escritura comunes (ej. `gmial.com` → `gmail.com`). Permite añadir, eliminar o desactivar reglas de corrección desde una interfaz visual.
- **Plantillas Multi-idioma:** Crea y gestiona respuestas automáticas en varios idiomas. El sistema detecta el idioma o permite forzar uno específico desde el formulario.
- **Logs y Estadísticas:** Registra cada correo enviado y cada corrección de dominio. Visualiza estadísticas para entender los errores más comunes.
- **Interfaz de Administración Completa:** Gestiona toda la configuración desde una única página con pestañas: Dashboard, Correcciones, Pruebas, Logs, Estadísticas, Plantillas, Debug y Ayuda.
- **Modo Debug:** Un log de depuración detallado para diagnosticar problemas de conexión y envío de correos.
- **Optimización y Caché:** Un sistema de caché para la configuración SMTP que reduce la carga en el servidor.
- **Extensible:** Añade nuevos idiomas o dominios para corregir fácilmente a través de filtros de WordPress para desarrolladores.

## 🚀 Instalación

1.  **Sube el Plugin:** Copia la carpeta `scp-easy-smtp` a tu directorio `wp-content/plugins/`.
2.  **Activa el Plugin:** Ve al panel de administración de WordPress, a la sección "Plugins", y activa "SCP Easy SMTP".
3.  **Crea el Archivo de Configuración:**
    -   Crea un archivo llamado `scp-config.ini` en el directorio que está **un nivel por encima** de tu instalación de WordPress (el mismo directorio donde normalmente se encuentra `wp-config.php`).
    -   Añade tus credenciales SMTP al archivo:

    ```ini
    [smtp]
    host = "smtp.ejemplo.com"
    port = 587
    username = "tu_usuario@ejemplo.com"
    password = "tu_contraseña_secreta"
    secure = "tls" ; Opciones: 'ssl', 'tls', o '' (sin encriptación)
    from = "noreply@ejemplo.com"
    fromname = "Nombre de tu Sitio Web"
    ```

4.  **Verifica y Prueba:**
    -   Ve a **Ajustes → SCP Easy SMTP** en tu panel de WordPress.
    -   El "Dashboard" debería mostrar el estado "Configurado".
    -   Ve a la pestaña **"Enviar Prueba"**, introduce un email y comprueba que el envío funciona.

## 🛠️ Uso y Guía de Integración

### Gestión de Corrección de Dominios

Ve a la pestaña **Ajustes → SCP Easy SMTP → Correcciones**. Desde aquí puedes:
- **Activar o desactivar** las reglas de corrección que vienen por defecto.
- **Añadir** tus propias reglas personalizadas (ej. `miempresa.co` → `miempresa.com`).
- **Eliminar** las reglas personalizadas que hayas creado.

Para desarrolladores, el filtro `scp_smtp_domain_corrections` sigue estando disponible y tiene la máxima prioridad:

```php
add_filter( 'scp_smtp_domain_corrections', function( $corrections ) {
    // Esta regla sobreescribirá cualquier configuración de la interfaz.
    $corrections['dominio-custom.com'] = 'dominio-final.com';
    return $corrections;
} );
```

### Envío de Confirmaciones desde Formularios

Para integrar el plugin con tus formularios de contacto, usa la clase `Scp_Email_Templates`.

#### Escenario 1: Solo Corregir Dominio (sin enviar email de confirmación)

Si solo quieres que el email del destinatario se corrija antes de que tu formulario lo procese:

```php
if ( class_exists( 'Scp_Email_Domain_Fixer' ) ) {
    $user_email = 'usuario@gmial.com'; // Email del formulario
    $corrected_email = Scp_Email_Domain_Fixer::fix_email_domain( $user_email );
    // Ahora $corrected_email es 'usuario@gmail.com'
    // Procede a enviar tu email principal a $corrected_email
}
```

#### Escenario 2: Corregir Dominio Y Enviar Email de Confirmación

Si quieres corregir el dominio y además enviar una respuesta automática al usuario:

```php
if ( class_exists( 'Scp_Email_Templates' ) ) {
    $user_email = 'usuario@ejemplo.com'; // Email del formulario
    $form_data = [
        'nombre' => 'Juan Pérez', // Placeholder {NOMBRE} para la plantilla
        'lan'    => 'fr',         // Opcional: Forzar el envío de la plantilla en francés
    ];

    Scp_Email_Templates::send_confirmation_email( $user_email, $form_data );
}
```

La función `send_confirmation_email` se encarga de:
1.  Corregir el dominio del `$user_email`.
2.  Detectar el idioma (basado en el parámetro `lan`, la config de WP o el idioma por defecto).
3.  Enviar la plantilla de email correspondiente.
4.  Registrar el envío en los logs.

### Añadir Nuevos Idiomas para Plantillas

Puedes añadir soporte para más idiomas usando el filtro `scp_smtp_register_languages`:

```php
add_filter( 'scp_smtp_register_languages', function( $languages ) {
    $languages['de'] = [
        'name'   => 'Deutsch',
        'flag'   => '🇩🇪',
        'locale' => [ 'de_DE', 'de_CH' ],
    ];
    return $languages;
} );
```
El nuevo idioma aparecerá automáticamente en la pestaña "Templates".

## 🗂️ Estructura del Plugin

```
scp-easy-smtp/
├── scp-easy-smtp.php          # Archivo principal del plugin
├── README.md                  # Este archivo
├── inc/
│   ├── smtp-config.php        # Carga y cachea la configuración SMTP
│   ├── email-domain-fixer.php # Lógica para la corrección de dominios
│   ├── email-logger.php       # Registra emails y correcciones en la BD
│   ├── email-templates.php    # Gestiona las plantillas multi-idioma
│   └── debug-logger.php       # Sistema de logging para depuración
├── admin/
│   └── settings-page.php      # Renderiza la página de ajustes y procesa los formularios
└── assets/
    ├── css/admin.css          # Estilos para la interfaz de administración
    └── js/admin.js            # Interactividad y mejoras de UX en el admin
```

## 📄 Licencia

Este plugin es liberado bajo la licencia **GPL v2 o posterior**.
