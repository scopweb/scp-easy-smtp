<?php
/**
 * Gestor de Corrección de Dominios de Email.
 *
 * Gestiona la lógica para corregir errores comunes en los dominios de los
 * correos electrónicos, combinando reglas por defecto, reglas personalizadas
 * por el usuario y un filtro para desarrolladores.
 *
 * @package     SCP\EasySMTP\Inc
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Clase final para la corrección de dominios de email.
 *
 * @final
 */
final class Scp_Email_Domain_Fixer {

	const CUSTOM_CORRECTIONS_OPTION = 'scp_smtp_custom_corrections';
	const DISABLED_DEFAULTS_OPTION = 'scp_smtp_disabled_default_corrections';

	/**
	 * Almacena las correcciones para evitar múltiples cargas.
	 *
	 * @var array|null
	 */
	private static $all_corrections = null;

	/**
	 * Devuelve las correcciones de dominio por defecto.
	 *
	 * @return array
	 */
	public static function get_default_corrections() {
		return [
			'gmial.com'      => 'gmail.com',
			'gamil.com'      => 'gmail.com',
			'gmal.com'       => 'gmail.com',
			'gmail.con'      => 'gmail.com',
			'gmaill.com'     => 'gmail.com',
			'hotmail.con'    => 'hotmail.com',
			'hotmal.com'     => 'hotmail.com',
			'hotmial.com'    => 'hotmail.com',
			'otlook.com'     => 'outlook.com',
			'outlok.com'     => 'outlook.com',
			'yahoo.con'      => 'yahoo.com',
			'yaho.com'       => 'yahoo.com',
			'yahho.com'      => 'yahoo.com',
			'icloud.con'     => 'icloud.com',
			'icluod.com'     => 'icloud.com',
			'aol.con'        => 'aol.com',
			'protonmail.con' => 'protonmail.com',
		];
	}

	/**
	 * Obtiene todas las correcciones de dominio activas.
	 *
	 * Combina las correcciones por defecto (excluyendo las desactivadas por el usuario),
	 * las correcciones personalizadas por el usuario y las añadidas por el filtro de desarrollador.
	 *
	 * @return array Un array de correcciones `['dominio_incorrecto' => 'dominio_correcto']`.
	 */
	public static function get_all_corrections() {
		if ( null !== self::$all_corrections ) {
			return self::$all_corrections;
		}

		$default_corrections  = self::get_default_corrections();
		$custom_corrections   = get_option( self::CUSTOM_CORRECTIONS_OPTION, [] );
		$disabled_corrections = get_option( self::DISABLED_DEFAULTS_OPTION, [] );

		// Filtrar las correcciones por defecto que han sido desactivadas.
		$active_defaults = array_diff_key( $default_corrections, array_flip( $disabled_corrections ) );

		// Fusionar: las personalizadas sobreescriben a las por defecto si hay conflicto.
		$merged_corrections = array_merge( $active_defaults, $custom_corrections );

		/**
		 * Filtro para añadir o modificar correcciones de dominio.
		 * Tiene la máxima prioridad y puede sobreescribir cualquier regla.
		 *
		 * @param array $corrections Array de correcciones de dominio.
		 * @return array Array modificado.
		 */
		self::$all_corrections = apply_filters( 'scp_smtp_domain_corrections', $merged_corrections );

		return self::$all_corrections;
	}

	/**
	 * Corrige el dominio de una dirección de email si es necesario.
	 *
	 * @param string $email La dirección de email a verificar.
	 * @return string La dirección de email corregida o la original si no se encontró corrección.
	 */
	public static function fix_email_domain( $email ) {
		if ( ! is_email( $email ) ) {
			return $email;
		}

		$parts = explode( '@', $email );
		if ( count( $parts ) !== 2 ) {
			return $email;
		}

		list( $user, $domain ) = $parts;
		$domain = strtolower( $domain );

		$corrections = self::get_all_corrections();

		if ( isset( $corrections[ $domain ] ) ) {
			$corrected_domain = $corrections[ $domain ];
			$corrected_email  = $user . '@' . $corrected_domain;

			// Registrar la corrección.
			if ( class_exists( 'Scp_Email_Logger' ) ) {
				Scp_Email_Logger::log_correction( $email, $corrected_email );
			}

			return $corrected_email;
		}

		return $email;
	}

	/**
	 * Añade una nueva corrección personalizada.
	 *
	 * @param string $original El dominio incorrecto.
	 * @param string $corrected El dominio correcto.
	 * @return bool True si se añadió, false si los datos son inválidos.
	 */
	public static function add_custom_correction( $original, $corrected ) {
		$original  = strtolower( trim( $original ) );
		$corrected = strtolower( trim( $corrected ) );

		if ( empty( $original ) || empty( $corrected ) || $original === $corrected ) {
			return false;
		}

		$custom_corrections = get_option( self::CUSTOM_CORRECTIONS_OPTION, [] );
		$custom_corrections[ $original ] = $corrected;

		return update_option( self::CUSTOM_CORRECTIONS_OPTION, $custom_corrections );
	}

	/**
	 * Elimina una corrección personalizada.
	 *
	 * @param string $original El dominio incorrecto a eliminar.
	 * @return bool True si se eliminó.
	 */
	public static function remove_custom_correction( $original ) {
		$custom_corrections = get_option( self::CUSTOM_CORRECTIONS_OPTION, [] );
		if ( isset( $custom_corrections[ $original ] ) ) {
			unset( $custom_corrections[ $original ] );
			return update_option( self::CUSTOM_CORRECTIONS_OPTION, $custom_corrections );
		}
		return false;
	}

	/**
	 * Activa o desactiva una corrección por defecto.
	 *
	 * @param string $original El dominio incorrecto de la regla por defecto.
	 * @param bool   $is_disabled True para desactivar, false para activar.
	 * @return bool True si el estado cambió.
	 */
	public static function toggle_default_correction( $original, $is_disabled ) {
		$disabled_corrections = get_option( self::DISABLED_DEFAULTS_OPTION, [] );
		$key                  = array_search( $original, $disabled_corrections, true );

		if ( $is_disabled && false === $key ) {
			// Desactivar: añadir a la lista si no está.
			$disabled_corrections[] = $original;
		} elseif ( ! $is_disabled && false !== $key ) {
			// Activar: quitar de la lista si está.
			unset( $disabled_corrections[ $key ] );
		}

		return update_option( self::DISABLED_DEFAULTS_OPTION, array_values( $disabled_corrections ) );
	}
}