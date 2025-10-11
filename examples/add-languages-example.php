<?php
/**
 * Ejemplos de Extensión para SCP Easy SMTP
 *
 * Este archivo demuestra cómo puedes extender y personalizar las funcionalidades
 * del plugin SCP Easy SMTP utilizando los filtros y acciones disponibles.
 *
 * Copia los fragmentos que necesites en el archivo `functions.php` de tu tema
 * o en un plugin de funcionalidades personalizado.
 *
 * @version 2.0.0
 * @package SCP\EasySMTP\Examples
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevenir acceso directo.
}

/**
 * Ejemplo 1: Añadir soporte para nuevos idiomas en las plantillas.
 *
 * Este filtro te permite registrar nuevos idiomas. Una vez registrados,
 * aparecerán automáticamente en la página de configuración de plantillas.
 */
add_filter( 'scp_smtp_register_languages', 'mi_plugin_añadir_idiomas_smtp' );
function mi_plugin_añadir_idiomas_smtp( $languages ) {

	// Añadir Alemán
	$languages['de'] = [
		'name'    => 'Deutsch',
		'flag'    => '🇩🇪',
		'markers' => [ '----DEUTSCH----', '----ALEMAN----' ], // Marcadores para forzar idioma.
		'locale'  => [ 'de_DE', 'de_CH' ], // Locales de WP para detección automática.
	];

	// Añadir Italiano
	$languages['it'] = [
		'name'    => 'Italiano',
		'flag'    => '🇮🇹',
		'markers' => [ '----ITALIANO----' ],
		'locale'  => [ 'it_IT' ],
	];

	// Añadir Portugués
	$languages['pt'] = [
		'name'    => 'Português',
		'flag'    => '🇵🇹',
		'markers' => [ '----PORTUGUES----' ],
		'locale'  => [ 'pt_PT', 'pt_BR' ],
	];

	return $languages;
}

/**
 * Ejemplo 2: Añadir nuevas reglas de corrección de dominios.
 *
 * Utiliza este filtro para añadir tus propias correcciones de dominio.
 * El formato es 'dominio_incorrecto' => 'dominio_correcto'.
 */
add_filter( 'scp_smtp_domain_corrections', 'mi_plugin_añadir_correcciones_dominio' );
function mi_plugin_añadir_correcciones_dominio( $corrections ) {

	// Corregir un error común en un dominio corporativo.
	$corrections['miempresa.co'] = 'miempresa.com';

	// Corregir un error de tipeo frecuente.
	$corrections['hotmal.es'] = 'hotmail.es';

	return $corrections;
}

/**
 * Ejemplo 3: Ejecutar una acción personalizada después de corregir un dominio.
 *
 * Este hook se dispara cada vez que un dominio es corregido con éxito.
 * Es útil para logging personalizado o para enviar notificaciones.
 */
add_action( 'scp_smtp_domain_corrected', 'mi_plugin_accion_dominio_corregido', 10, 3 );
function mi_plugin_accion_dominio_corregido( $original_email, $corrected_email, $context ) {

	// Enviar una notificación al administrador si se corrige un dominio importante.
	if ( strpos( $corrected_email, '@miempresa.com' ) !== false ) {
		$admin_email = get_option( 'admin_email' );
		$subject     = 'Notificación: Dominio corporativo corregido';
		$message     = "Se ha corregido un email de '{$original_email}' a '{$corrected_email}'.";
		wp_mail( $admin_email, $subject, $message );
	}

	// Guardar un log personalizado.
	error_log( "[SCP SMTP] Dominio corregido: de {$original_email} a {$corrected_email}" );
}

/**
 * Ejemplo 4: Ejecutar una acción después de enviar un email de confirmación.
 *
 * Este hook se dispara después de que una plantilla de confirmación ha sido enviada.
 */
add_action( 'scp_smtp_confirmation_sent', 'mi_plugin_accion_confirmacion_enviada', 10, 4 );
function mi_plugin_accion_confirmacion_enviada( $to, $subject, $language_code, $placeholders ) {

	// Si el email se envió en francés, notificar al equipo de soporte de Francia.
	if ( 'fr' === $language_code ) {
		$support_email = 'soporte.fr@miempresa.com';
		$notify_subject = "Nuevo contacto en francés de: {$to}";
		$notify_message = "Se ha enviado una confirmación a {$to}. Detalles del formulario: \n" . print_r( $placeholders, true );
		wp_mail( $support_email, $notify_subject, $notify_message );
	}
}

/**
 * Ejemplo 5: Modificar los datos de una plantilla antes de ser enviada.
 *
 * Este filtro te permite cambiar dinámicamente el asunto, cuerpo o placeholders
 * de una plantilla justo antes de que se envíe el correo.
 */
add_filter( 'scp_smtp_pre_send_template_data', 'mi_plugin_modificar_plantilla_al_vuelo', 10, 3 );
function mi_plugin_modificar_plantilla_al_vuelo( $template_data, $language_code, $placeholders ) {

	// Añadir un saludo personalizado si se conoce el nombre del usuario.
	if ( ! empty( $placeholders['{NOMBRE}'] ) ) {
		$nombre = esc_html( $placeholders['{NOMBRE}'] );
		// Añadir el saludo al principio del cuerpo del email.
		$template_data['body'] = "<p>Hola {$nombre},</p>" . $template_data['body'];
	}

	// Añadir un código de descuento especial para los emails en inglés.
	if ( 'en' === $language_code ) {
		$template_data['body'] .= '<p>As a thank you, use the code <strong>WELCOME10</strong> for a 10% discount on your next purchase!</p>';
	}

	return $template_data;
}
