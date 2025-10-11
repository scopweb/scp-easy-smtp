<?php
/**
 * Gestor de Plantillas de Email Multi-idioma.
 *
 * Gestiona la creación, detección y envío de respuestas automáticas
 * basadas en plantillas configurables para diferentes idiomas.
 *
 * @package     SCP\EasySMTP\Inc
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Clase final para la gestión de plantillas de email.
 *
 * @final
 */
final class Scp_Email_Templates {

	/**
	 * Almacena los idiomas registrados para evitar múltiples llamadas al filtro.
	 *
	 * @var array|null
	 */
	private static $registered_languages = null;


/**
 * Obtiene los idiomas registrados para las plantillas.
 *
 * Define idiomas por defecto (ES, EN, FR), combina con idiomas personalizados
 * guardados en wp_options, y permite a otros desarrolladores añadir o modificar
 * idiomas a través del filtro `scp_smtp_register_languages`.
 *
 * @param bool $force_reload Si es true, fuerza la recarga desde la base de datos.
 * @return array Un array de configuraciones de idioma.
 */
public static function get_registered_languages( $force_reload = false ) {
	// Si se solicita forzar recarga o aún no hay idiomas cargados
	if ( $force_reload || null === self::$registered_languages ) {

		$default_languages = [
			'es' => [
				'name'   => __( 'Español', 'scp-easy-smtp' ),
				'flag'   => '🇪🇸',
				'locale' => [ 'es_ES', 'es_MX', 'es_AR' ],
			],
			'en' => [
				'name'   => __( 'English', 'scp-easy-smtp' ),
				'flag'   => '🇺🇸',
				'locale' => [ 'en_US', 'en_GB' ],
			],
			'fr' => [
				'name'   => __( 'Français', 'scp-easy-smtp' ),
				'flag'   => '🇫🇷',
				'locale' => [ 'fr_FR', 'fr_CA' ],
			],
		];

		// Obtener idiomas personalizados de wp_options
		$custom_languages = get_option( 'scp_smtp_custom_languages', [] );

		// Registrar en el log los idiomas personalizados que encontramos
		if ( class_exists( 'Scp_SMTP_Debug_Logger' ) ) {
			Scp_SMTP_Debug_Logger::log( 'Idiomas personalizados cargados:', $custom_languages );
		}

		// Combinar idiomas por defecto con los personalizados
		$merged_languages = array_merge( $default_languages, $custom_languages );

		/**
		 * Filtro para registrar o modificar idiomas para las plantillas de email.
		 *
		 * @param array $languages Array de idiomas combinados.
		 * @return array Array de idiomas modificado.
		 */
		self::$registered_languages = apply_filters( 'scp_smtp_register_languages', $merged_languages );
	}

	return self::$registered_languages;
}
	/**
	 * Registra los ajustes de las plantillas en WordPress.
	 *
	 * Crea dinámicamente opciones para el asunto y cuerpo de cada idioma registrado.
	 */
	public static function register_settings() {
		register_setting( 'scp_smtp_settings_group', 'scp_smtp_default_language' );

		foreach ( array_keys( self::get_registered_languages() ) as $code ) {
			register_setting( 'scp_smtp_settings_group', "scp_smtp_template_subject_{$code}" );
			register_setting( 'scp_smtp_settings_group', "scp_smtp_template_body_{$code}" );
		}
	}

	/**
	 * Obtiene la plantilla por defecto para un idioma específico.
	 *
	 * @param string $lang_code Código del idioma (ej. 'es').
	 * @return array Un array con 'subject' y 'body'.
	 */
	public static function get_default_template( $lang_code ) {
		$defaults = [
			'en' => [
				'subject' => 'Your inquiry has been received',
				'body'    => '<p>Thank you for contacting us. We will get back to you shortly.</p><p><strong>{SITE_NAME}</strong></p>',
			],
			'es' => [
				'subject' => 'Tu consulta ha sido recibida',
				'body'    => '<p>Gracias por contactar con nosotros. En breve nos pondremos en contacto contigo.</p><p><strong>{SITE_NAME}</strong></p>',
			],
			'fr' => [
				'subject' => 'Votre demande a été reçue',
				'body'    => '<p>Merci de nous avoir contactés. Nous vous répondrons sous peu.</p><p><strong>{SITE_NAME}</strong></p>',
			],
		];

		$template = isset( $defaults[ $lang_code ] ) ? $defaults[ $lang_code ] : $defaults['en'];

		/**
		 * Filtro para personalizar la plantilla por defecto de un idioma.
		 *
		 * @param array  $template  Array con 'subject' y 'body'.
		 * @param string $lang_code Código del idioma.
		 * @return array Plantilla modificada.
		 */
		return apply_filters( 'scp_smtp_default_template', $template, $lang_code );
	}

	/**
	 * Obtiene todas las plantillas guardadas desde la base de datos.
	 *
	 * Si una plantilla para un idioma no está guardada, utiliza la de por defecto.
	 *
	 * @return array Un array de plantillas indexado por código de idioma.
	 */
	public static function get_templates() {
		$templates = [];
		foreach ( self::get_registered_languages() as $code => $config ) {
			$default_template = self::get_default_template( $code );
			$templates[ $code ] = [
				'subject' => get_option( "scp_smtp_template_subject_{$code}", $default_template['subject'] ),
				'body'    => get_option( "scp_smtp_template_body_{$code}", $default_template['body'] ),
			];
		}
		return $templates;
	}

	/**
	 * Detecta el idioma a utilizar para la respuesta automática.
	 *
	 * El orden de prioridad es:
	 * 1. Idioma explícito pasado en los datos del formulario (ej. 'lan' => 'fr').
	 * 2. Configuración de idioma por defecto del plugin ('auto' o un idioma específico).
	 * 3. Detección automática por el locale de WordPress.
	 * 4. Fallback al primer idioma registrado.
	 *
	 * @param string|null $explicit_lang Un idioma forzado externamente.
	 * @return string El código del idioma detectado.
	 */
	public static function detect_language( $explicit_lang = null ) {
		$languages      = self::get_registered_languages();
		$language_codes = array_keys( $languages );

		// 1. Idioma explícito.
		if ( ! empty( $explicit_lang ) && in_array( $explicit_lang, $language_codes, true ) ) {
			return $explicit_lang;
		}

		// 2. Configuración por defecto del plugin.
		$default_lang_option = get_option( 'scp_smtp_default_language', 'auto' );

		if ( 'auto' !== $default_lang_option && in_array( $default_lang_option, $language_codes, true ) ) {
			return $default_lang_option;
		}

		// 3. Detección por locale de WordPress.
		$current_locale = get_locale();
		foreach ( $languages as $code => $config ) {
			if ( ! empty( $config['locale'] ) ) {
				foreach ( $config['locale'] as $locale ) {
					if ( 0 === strpos( $current_locale, $locale ) ) {
						return $code;
					}
				}
			}
		}

		// 4. Fallback al primer idioma.
		return ! empty( $language_codes ) ? $language_codes[0] : 'en';
	}

	/**
	 * Envía un email de confirmación utilizando las plantillas.
	 *
	 * Esta es la función principal para ser llamada desde los formularios.
	 *
	 * @param string $email       La dirección del destinatario de la confirmación.
	 * @param array  $extra_data  Datos para reemplazar en la plantilla (ej. ['nombre' => 'Juan']).
	 *                            También puede contener 'send_confirmation' (bool) y 'lan' (string, ej. 'fr').
	 * @return bool `true` si el correo se envió, `false` en caso contrario.
	 */
	public static function send_confirmation_email( $email, $extra_data = [] ) {
		// Opción para solo corregir dominio sin enviar confirmación.
		if ( isset( $extra_data['send_confirmation'] ) && false === $extra_data['send_confirmation'] ) {
			if ( class_exists( 'Scp_Email_Domain_Fixer' ) ) {
				Scp_Email_Domain_Fixer::fix_email_domain( $email );
			}
			return true;
		}

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			Scp_SMTP_Debug_Logger::log( 'Intento de envío de confirmación a un email inválido: ' . $email );
			return false;
		}

		$explicit_lang = isset( $extra_data['lan'] ) ? $extra_data['lan'] : null;
		$lang          = self::detect_language( $explicit_lang );
		$templates     = self::get_templates();

		if ( ! isset( $templates[ $lang ] ) ) {
			$lang = 'en'; // Fallback a inglés si el idioma detectado no tiene plantilla.
		}

		$template = $templates[ $lang ];

		// Reemplazo de placeholders.
		$placeholders = [
			'{SITE_NAME}' => get_bloginfo( 'name' ),
			'{SITE_URL}'  => home_url(),
		];
		foreach ( $extra_data as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$placeholders[ '{' . strtoupper( $key ) . '}' ] = $value;
			}
		}

		$subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template['subject'] );
		$body    = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template['body'] );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$sent = wp_mail( $email, $subject, $body, $headers );

		if ( $sent ) {
			Scp_SMTP_Debug_Logger::log( "Email de confirmación enviado a: {$email} (Idioma: {$lang})" );
		} else {
			Scp_SMTP_Debug_Logger::log( "Error al enviar email de confirmación a: {$email}" );
		}

		return $sent;
	}

	/**
	 * Guarda las plantillas desde el formulario de administración.
	 *
	 * @param array $post_data Los datos recibidos por POST.
	 * @return bool `true` si se guardó correctamente, `false` si falló la verificación de seguridad.
	 */
	public static function save_templates_from_post( $post_data ) {
		if ( ! isset( $post_data['scp_smtp_templates_nonce'] ) || ! wp_verify_nonce( $post_data['scp_smtp_templates_nonce'], 'scp_smtp_save_templates' ) ) {
			return false;
		}

		// Procesar la adición de un nuevo idioma
		if ( isset( $post_data['scp_smtp_add_language'] ) ) {
			if ( 
				isset( $post_data['new_lang_code'] ) && 
				isset( $post_data['new_lang_name'] ) && 
				isset( $post_data['new_lang_flag'] )
			) {
				$code = sanitize_key( $post_data['new_lang_code'] );
				$name = sanitize_text_field( $post_data['new_lang_name'] );
				$flag = sanitize_text_field( $post_data['new_lang_flag'] );
				
				// Procesar locales, asegurando que tenemos un array incluso si está vacío
				$locales = [];
				if ( isset( $post_data['new_lang_locales'] ) && !empty( $post_data['new_lang_locales'] ) ) {
					$locales = array_map( 'trim', explode( ',', sanitize_text_field( $post_data['new_lang_locales'] ) ) );
				}
				
				// Registrar en el log lo que estamos añadiendo
				if (class_exists('Scp_SMTP_Debug_Logger')) {
					Scp_SMTP_Debug_Logger::log( 'Añadiendo nuevo idioma:', [
						'code' => $code,
						'name' => $name,
						'flag' => $flag,
						'locales' => $locales,
					]);
				}
				
				// Obtener idiomas personalizados existentes y añadir el nuevo
				$custom_languages = get_option( 'scp_smtp_custom_languages', [] );
				$custom_languages[$code] = [
					'name'   => $name,
					'flag'   => $flag,
					'locale' => $locales,
				];
				
				// Guardar los idiomas personalizados actualizados
				$updated = update_option( 'scp_smtp_custom_languages', $custom_languages );

				if ( class_exists( 'Scp_SMTP_Debug_Logger' ) ) {
					Scp_SMTP_Debug_Logger::log( 'Resultado de guardar idiomas personalizados:', [
						'updated'    => $updated,
						'saved_data' => $custom_languages,
					]);
				}

				// Agregar también plantillas por defecto para el nuevo idioma
				$default_template = self::get_default_template( 'en' ); // Usar inglés como base
				update_option( "scp_smtp_template_subject_{$code}", $default_template['subject'] );
				update_option( "scp_smtp_template_body_{$code}", $default_template['body'] );

				// Registrar también el idioma en WordPress settings
				register_setting( 'scp_smtp_settings_group', "scp_smtp_template_subject_{$code}" );
				register_setting( 'scp_smtp_settings_group', "scp_smtp_template_body_{$code}" );

				// Forzar recarga de idiomas registrados
				self::$registered_languages = null;
			}
		}
		
		// Procesar la eliminación de un idioma
		if ( isset( $post_data['scp_smtp_remove_language'] ) && isset( $post_data['remove_lang_code'] ) ) {
			$code = sanitize_key( $post_data['remove_lang_code'] );
			
			if (class_exists('Scp_SMTP_Debug_Logger')) {
				Scp_SMTP_Debug_Logger::log( 'Eliminando idioma:', $code );
			}
			
			$custom_languages = get_option( 'scp_smtp_custom_languages', [] );
			if ( isset( $custom_languages[$code] ) ) {
				// Registrar el idioma que se va a eliminar
				if (class_exists('Scp_SMTP_Debug_Logger')) {
					Scp_SMTP_Debug_Logger::log( 'Datos del idioma a eliminar:', $custom_languages[$code] );
				}
				
				unset( $custom_languages[$code] );
				$updated = update_option( 'scp_smtp_custom_languages', $custom_languages );
				
				// Registrar el resultado
				if (class_exists('Scp_SMTP_Debug_Logger')) {
					Scp_SMTP_Debug_Logger::log( 'Resultado de eliminar idioma:', [
						'updated' => $updated,
						'remaining_languages' => $custom_languages,
					]);
				}
				
				// También eliminar sus plantillas
				delete_option( "scp_smtp_template_subject_{$code}" );
				delete_option( "scp_smtp_template_body_{$code}" );
				
				// Forzar recarga de idiomas registrados
				self::$registered_languages = null;
			}
		}

		// Guardar el idioma por defecto
		if ( isset( $post_data['default_language'] ) ) {
			update_option( 'scp_smtp_default_language', sanitize_text_field( $post_data['default_language'] ) );
		}

		// Obtener los idiomas registrados actualizados tras posibles cambios
		$registered_languages = self::get_registered_languages();
		
		// Guardar las plantillas para todos los idiomas
		foreach ( array_keys( $registered_languages ) as $code ) {
			if ( isset( $post_data[ "template_subject_{$code}" ] ) ) {
				update_option( "scp_smtp_template_subject_{$code}", sanitize_text_field( $post_data[ "template_subject_{$code}" ] ) );
			}
			if ( isset( $post_data[ "template_body_{$code}" ] ) ) {
				update_option( "scp_smtp_template_body_{$code}", wp_kses_post( $post_data[ "template_body_{$code}" ] ) );
			}
		}

		return true;
	}
}
