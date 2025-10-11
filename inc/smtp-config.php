<?php
/**
 * Manejador de configuración SMTP.
 *
 * Gestiona la carga, validación y cacheo de la configuración SMTP
 * desde un archivo .ini externo para mayor seguridad.
 *
 * @package     SCP\EasySMTP\Inc
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Clase final para la gestión de la configuración SMTP.
 *
 * No está pensada para ser extendida.
 *
 * @final
 */
final class Scp_SMTP_Config {

	/**
	 * Ruta al archivo de configuración.
	 *
	 * @var string
	 */
	private $config_path;

	/**
	 * Clave para el caché de la configuración en transitorios.
	 *
	 * @var string
	 */
	private $config_cache_key = 'scp_smtp_config_cache';

	/**
	 * Constructor de la clase.
	 *
	 * Define la ruta del archivo de configuración, que por seguridad
	 * se espera que esté un nivel por encima del directorio raíz de WordPress.
	 */
	public function __construct() {
		// Define la ruta del archivo .ini de forma dinámica y segura.
		// Busca el directorio raíz de la instalación de WordPress y sube un nivel.
		// Esto funciona tanto si WordPress está en el directorio raíz del servidor web
		// como si está en un subdirectorio.
		$base_path = $this->find_wp_config_path();
		$this->config_path = $base_path . '/scp-config.ini';
	}

	/**
	 * Encuentra la ruta del directorio que contiene wp-config.php.
	 *
	 * WordPress puede tener su archivo wp-config.php en el directorio raíz de la instalación
	 * o en el directorio padre. Esta función busca en ambos lugares para determinar
	 * la ruta base correcta, que generalmente es la raíz del sitio web.
	 *
	 * @return string La ruta del directorio donde se espera que esté el archivo de configuración.
	 */
	private function find_wp_config_path() {
		$abspath_parent = dirname( ABSPATH );

		// Si wp-config.php está en el directorio padre de ABSPATH, esa es la raíz.
		if ( file_exists( $abspath_parent . '/wp-config.php' ) ) {
			return dirname( $abspath_parent ); // Subir un nivel desde la raíz del sitio.
		}

		// Si wp-config.php está en ABSPATH, esa es la raíz.
		if ( file_exists( ABSPATH . '/wp-config.php' ) ) {
			return $abspath_parent; // Subir un nivel desde la raíz del sitio.
		}

		// Fallback por si la estructura es muy inusual.
		// Asume que la raíz es el directorio padre de ABSPATH.
		return $abspath_parent;
	}

	/**
	 * Obtiene la configuración SMTP.
	 *
	 * Primero intenta obtener la configuración desde el caché de transitorios de WordPress.
	 * Si no está en caché, la lee desde el archivo .ini y la guarda en el caché.
	 *
	 * @return array|false Un array con la configuración SMTP o `false` si no se encuentra o es inválida.
	 */
	public function get_smtp_config() {
		// Intentar obtener desde el caché.
		$cached_config = get_transient( $this->config_cache_key );
		if ( false !== $cached_config ) {
			return $cached_config;
		}

		// Si no está en caché, leer desde el archivo.
		if ( ! $this->config_exists() ) {
			error_log( 'SCP SMTP: Archivo de configuración no encontrado en: ' . $this->config_path );
			return false;
		}

		// `true` para procesar secciones.
		$config = parse_ini_file( $this->config_path, true );

		if ( ! isset( $config['smtp'] ) || ! is_array( $config['smtp'] ) ) {
			error_log( 'SCP SMTP: La sección [smtp] no fue encontrada o es inválida en el archivo de configuración.' );
			return false;
		}

		$smtp_config = $config['smtp'];

		// Validar la configuración antes de cachearla.
		if ( ! $this->validate_config( $smtp_config ) ) {
			error_log( 'SCP SMTP: La configuración SMTP leída no es válida.' );
			return false;
		}

		// Guardar en caché por 1 hora.
		set_transient( $this->config_cache_key, $smtp_config, HOUR_IN_SECONDS );

		return $smtp_config;
	}

	/**
	 * Valida la configuración SMTP.
	 *
	 * Comprueba que todos los campos requeridos existan y tengan formatos válidos.
	 *
	 * @param array $config Array de configuración a validar.
	 * @return bool `true` si la configuración es válida, `false` en caso contrario.
	 */
	public function validate_config( $config ) {
		$required_fields = [ 'host', 'port', 'username', 'password', 'secure', 'from', 'fromname' ];

		foreach ( $required_fields as $field ) {
			if ( ! isset( $config[ $field ] ) || '' === $config[ $field ] ) {
				error_log( "SCP SMTP: El campo requerido '{$field}' no está definido o está vacío en la configuración." );
				return false;
			}
		}

		// Validación del puerto.
		if ( ! filter_var( $config['port'], FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1, 'max_range' => 65535 ] ] ) ) {
			error_log( 'SCP SMTP: Puerto SMTP inválido: ' . $config['port'] );
			return false;
		}

		// Validación del método de seguridad.
		if ( ! in_array( strtolower( $config['secure'] ), [ 'tls', 'ssl', '' ], true ) ) {
			error_log( 'SCP SMTP: Método de seguridad inválido. Debe ser "tls", "ssl" o estar vacío.' );
			return false;
		}

		// Validación del email 'from'.
		if ( ! filter_var( $config['from'], FILTER_VALIDATE_EMAIL ) ) {
			error_log( 'SCP SMTP: La dirección de correo "from" no es válida: ' . $config['from'] );
			return false;
		}

		return true;
	}

	/**
	 * Limpia el caché de la configuración.
	 *
	 * Útil cuando se actualiza el archivo .ini y se necesita forzar la recarga.
	 */
	public function clear_cache() {
		delete_transient( $this->config_cache_key );
	}

	/**
	 * Devuelve la ruta del archivo de configuración.
	 *
	 * @return string Ruta absoluta al archivo de configuración.
	 */
	public function get_config_path() {
		return $this->config_path;
	}

	/**
	 * Comprueba si el archivo de configuración existe y es legible.
	 *
	 * @return bool `true` si existe y se puede leer, `false` en caso contrario.
	 */
	public function config_exists() {
		return is_readable( $this->config_path );
	}
}
