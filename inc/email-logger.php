<?php
/**
 * Gestor de Logs de Emails.
 *
 * Registra los intentos de envío de correos y las correcciones de dominio
 * en tablas personalizadas de la base de datos para su posterior análisis.
 *
 * @package     SCP\EasySMTP\Inc
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Clase final para el registro de actividad de correos.
 *
 * @final
 */
final class Scp_Email_Logger {

	/**
	 * Nombre de la tabla para los logs de emails.
	 *
	 * @var string
	 */
	private $table_emails;

	/**
	 * Nombre de la tabla para los logs de correcciones.
	 *
	 * @var string
	 */
	private $table_corrections;

	/**
	 * Constructor de la clase.
	 *
	 * Inicializa los nombres de las tablas con el prefijo de WordPress.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_emails      = $wpdb->prefix . 'scp_smtp_emails';
		$this->table_corrections = $wpdb->prefix . 'scp_smtp_corrections';
	}

	/**
	 * Crea las tablas personalizadas en la base de datos durante la activación.
	 */
	public function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Sentencia SQL para la tabla de logs de emails.
		$sql_emails = "CREATE TABLE {$this->table_emails} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email_to VARCHAR(255) NOT NULL,
			subject VARCHAR(255) NOT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'sent',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at)
		) {$charset_collate};";

		// Sentencia SQL para la tabla de correcciones de dominio.
		$sql_corrections = "CREATE TABLE {$this->table_corrections} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			original_email VARCHAR(255) NOT NULL,
			corrected_email VARCHAR(255) NOT NULL,
			original_domain VARCHAR(255) NOT NULL,
			corrected_domain VARCHAR(255) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY original_domain (original_domain)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_emails );
		dbDelta( $sql_corrections );
	}

	/**
	 * Registra un intento de envío de correo.
	 *
	 * @param array $args Argumentos de la función `wp_mail`.
	 */
	public function log_email( $args ) {
		global $wpdb;

		$to      = isset( $args['to'] ) ? ( is_array( $args['to'] ) ? implode( ', ', $args['to'] ) : $args['to'] ) : '';
		$subject = isset( $args['subject'] ) ? $args['subject'] : '';

		$wpdb->insert(
			$this->table_emails,
			[
				'email_to'   => sanitize_text_field( $to ),
				'subject'    => sanitize_text_field( $subject ),
				'status'     => 'sent',
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Registra una corrección de dominio de email.
	 *
	 * @param string $original_email  La dirección de correo original.
	 * @param string $corrected_email La dirección de correo corregida.
	 */
	public function log_correction( $original_email, $corrected_email ) {
		global $wpdb;

		$original_parts  = explode( '@', $original_email );
		$corrected_parts = explode( '@', $corrected_email );

		$original_domain  = isset( $original_parts[1] ) ? $original_parts[1] : '';
		$corrected_domain = isset( $corrected_parts[1] ) ? $corrected_parts[1] : '';

		$wpdb->insert(
			$this->table_corrections,
			[
				'original_email'   => sanitize_email( $original_email ),
				'corrected_email'  => sanitize_email( $corrected_email ),
				'original_domain'  => sanitize_text_field( $original_domain ),
				'corrected_domain' => sanitize_text_field( $corrected_domain ),
				'created_at'       => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Obtiene los logs de emails más recientes.
	 *
	 * @param int $limit Número de registros a obtener.
	 * @return array Array de objetos con los logs.
	 */
	public function get_recent_emails( $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_emails} ORDER BY created_at DESC LIMIT %d",
				absint( $limit )
			)
		);
	}

	/**
	 * Obtiene los logs de correcciones de dominio más recientes.
	 *
	 * @param int $limit Número de registros a obtener.
	 * @return array Array de objetos con los logs.
	 */
	public function get_recent_corrections( $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_corrections} ORDER BY created_at DESC LIMIT %d",
				absint( $limit )
			)
		);
	}

	/**
	 * Obtiene estadísticas sobre las correcciones de dominio.
	 *
	 * @return array Un array con el total de correcciones y las más frecuentes.
	 */
	public function get_correction_stats() {
		global $wpdb;

		$total_corrections = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_corrections}" );

		$top_corrections = $wpdb->get_results(
			"SELECT original_domain, corrected_domain, COUNT(*) as count
			 FROM {$this->table_corrections}
			 GROUP BY original_domain, corrected_domain
			 ORDER BY count DESC
			 LIMIT 10"
		);

		return [
			'total'           => $total_corrections,
			'top_corrections' => $top_corrections,
		];
	}

	/**
	 * Limpia los logs antiguos para mantener la base de datos optimizada.
	 *
	 * @param int $days Días de logs a conservar.
	 */
	public function clear_old_logs( $days = 30 ) {
		global $wpdb;
		$days = absint( $days );

		if ( $days <= 0 ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_emails} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_corrections} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}
}
