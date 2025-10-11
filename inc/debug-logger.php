<?php
/**
 * Logger de Depuración para SMTP.
 *
 * Proporciona un sistema de logging detallado para diagnosticar problemas
 * con la configuración y el envío de correos SMTP.
 *
 * @package     SCP\EasySMTP\Inc
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Clase final para el registro de depuración.
 *
 * Utiliza métodos estáticos para registrar información en un archivo de log
 * y en el log de errores de PHP.
 *
 * @final
 */
final class Scp_SMTP_Debug_Logger {

	/**
	 * Nombre del archivo de log.
	 *
	 * @var string
	 */
	const LOG_FILENAME = 'scp-smtp-debug.log';

	/**
	 * Ruta completa al archivo de log.
	 *
	 * @var string|null
	 */
	private static $log_file = null;

	/**
	 * Estado de activación del logger.
	 *
	 * @var bool
	 */
	private static $enabled = true;

	/**
	 * Inicializa la ruta del archivo de log.
	 *
	 * Se asegura de que la ruta se defina solo una vez.
	 */
	private static function init() {
		if ( null === self::$log_file ) {
			self::$log_file = WP_CONTENT_DIR . '/' . self::LOG_FILENAME;
		}
	}

	/**
	 * Registra un mensaje de depuración.
	 *
	 * Escribe en el archivo de log personalizado y en el log de errores de PHP.
	 *
	 * @param string $message El mensaje a registrar.
	 * @param array  $context Datos adicionales para incluir en el log.
	 */
	public static function log( $message, $context = [] ) {
		if ( ! self::$enabled ) {
			return;
		}

		self::init();

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] {$message}";

		if ( ! empty( $context ) ) {
			// `print_r` es útil para depuración, pero se debe usar con cuidado.
			$log_entry .= "\n" . print_r( $context, true );
		}

		$log_entry .= "\n" . str_repeat( '-', 80 ) . "\n";

		// Usar `error_log` con tipo 3 para escribir en un archivo específico.
		// Se suprime el error si el archivo no es escribible.
		@error_log( $log_entry, 3, self::$log_file );

		// También registrar un mensaje más corto en el log general de PHP.
		error_log( "SCP SMTP Debug: {$message}" );
	}

	/**
	 * Registra el estado de la configuración SMTP.
	 *
	 * @param array $config   La configuración SMTP.
	 * @param bool  $is_valid Si la configuración es válida o no.
	 */
	public static function log_smtp_config( $config, $is_valid ) {
		$status = $is_valid ? 'VÁLIDA' : 'INVÁLIDA';
		self::log( "Verificación de configuración SMTP: {$status}", [
			'host'     => isset( $config['host'] ) ? $config['host'] : 'N/A',
			'port'     => isset( $config['port'] ) ? $config['port'] : 'N/A',
			'secure'   => isset( $config['secure'] ) ? $config['secure'] : 'N/A',
			'from'     => isset( $config['from'] ) ? $config['from'] : 'N/A',
			'is_valid' => $is_valid,
		]);
	}

	/**
	 * Registra la inicialización de PHPMailer.
	 */
	public static function log_phpmailer_init() {
		self::log( 'Hook `phpmailer_init` ejecutado. Iniciando configuración de SMTP.' );
	}

	/**
	 * Registra la llamada al filtro `wp_mail`.
	 *
	 * @param array $args Argumentos de `wp_mail`.
	 */
	public static function log_wp_mail( $args ) {
		self::log( 'Filtro `wp_mail` ejecutado. Procesando correo.', [
			'to'              => isset( $args['to'] ) ? ( is_array( $args['to'] ) ? implode( ', ', $args['to'] ) : $args['to'] ) : 'N/A',
			'subject'         => isset( $args['subject'] ) ? $args['subject'] : 'N/A',
			'message_preview' => isset( $args['message'] ) ? substr( $args['message'], 0, 150 ) . '...' : '',
		]);
	}

	/**
	 * Obtiene la ruta del archivo de log.
	 *
	 * @return string La ruta del archivo.
	 */
	public static function get_log_file() {
		self::init();
		return self::$log_file;
	}

	/**
	 * Limpia el archivo de log.
	 */
	public static function clear_log() {
		self::init();
		if ( file_exists( self::$log_file ) && is_writable( self::$log_file ) ) {
			unlink( self::$log_file );
		}
		self::log( 'Archivo de log limpiado.' );
	}

	/**
	 * Obtiene las últimas N líneas del archivo de log.
	 *
	 * @param int $lines Número de líneas a obtener.
	 * @return string El contenido de las últimas líneas o un mensaje de estado.
	 */
	public static function get_recent_logs( $lines = 100 ) {
		self::init();

		if ( ! file_exists( self::$log_file ) ) {
			return 'El archivo de log no existe. Envía un email de prueba para generarlo.';
		}

		if ( ! is_readable( self::$log_file ) ) {
			return 'Error: El archivo de log no tiene permisos de lectura.';
		}

		// Una forma más eficiente de leer las últimas líneas de un archivo grande.
		$file_content = file_get_contents( self::$log_file );
		if ( empty( $file_content ) ) {
			return 'El archivo de log está vacío.';
		}

		$log_lines = explode( "\n", trim( $file_content ) );
		$slice     = array_slice( $log_lines, -$lines );

		return implode( "\n", $slice );
	}

	/**
	 * Activa o desactiva el logging.
	 *
	 * @param bool $enabled `true` para activar, `false` para desactivar.
	 */
	public static function set_enabled( $enabled ) {
		self::$enabled = (bool) $enabled;
	}
}
