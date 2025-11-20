<?php
/**
 * Página de Configuración del Plugin SCP Easy SMTP.
 *
 * Gestiona la interfaz de administración con pestañas para configuración,
 * pruebas, logs y estadísticas.
 *
 * @package     SCP\EasySMTP\Admin
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renderiza la página principal de configuración.
 */
function scp_smtp_render_settings_page() {
	// Verificar permisos.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'scp-easy-smtp' ) );
	}

	// Sanitizar el parámetro tab.
	$allowed_tabs = [ 'dashboard', 'test', 'logs', 'stats', 'debug', 'help', 'templates' ];
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

	// Validar que el tab esté en la lista permitida.
	if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
		$active_tab = 'dashboard';
	}

	// Procesar acciones de formularios.
	scp_smtp_process_form_actions();

	?>
	<div class="wrap scp-smtp-settings">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $allowed_tabs as $tab_id ) : ?>
				<?php
				$tab_labels = [
					'dashboard' => __( 'Dashboard', 'scp-easy-smtp' ),
					'test'      => __( 'Enviar Prueba', 'scp-easy-smtp' ),
					'logs'      => __( 'Logs', 'scp-easy-smtp' ),
					'stats'     => __( 'Estadísticas', 'scp-easy-smtp' ),
					'debug'     => __( 'Debug', 'scp-easy-smtp' ),
					'help'      => __( 'Ayuda', 'scp-easy-smtp' ),
					'templates' => __( 'Plantillas', 'scp-easy-smtp' ),
				];
				$label = isset( $tab_labels[ $tab_id ] ) ? $tab_labels[ $tab_id ] : ucfirst( $tab_id );
				$active_class = ( $active_tab === $tab_id ) ? 'nav-tab-active' : '';
				$tab_url = add_query_arg( 'tab', $tab_id, admin_url( 'options-general.php?page=scp-smtp-settings' ) );
				?>
				<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo esc_attr( $active_class ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</h2>

		<div class="scp-smtp-tab-content">
			<?php
			switch ( $active_tab ) {
				case 'dashboard':
					scp_smtp_render_dashboard_tab();
					break;
				case 'test':
					scp_smtp_render_test_tab();
					break;
				case 'logs':
					scp_smtp_render_logs_tab();
					break;
				case 'stats':
					scp_smtp_render_stats_tab();
					break;
				case 'debug':
					scp_smtp_render_debug_tab();
					break;
				case 'help':
					scp_smtp_render_help_tab();
					break;
				case 'templates':
					scp_smtp_render_templates_tab();
					break;
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * Procesa las acciones de los formularios enviados.
 */
function scp_smtp_process_form_actions() {
	// Procesar envío de email de prueba.
	if ( isset( $_POST['scp_smtp_send_test'] ) ) {
		check_admin_referer( 'scp_smtp_test_email_action', 'scp_smtp_test_email_nonce' );

		$test_email = isset( $_POST['test_email'] ) ? sanitize_email( $_POST['test_email'] ) : '';

		if ( ! is_email( $test_email ) ) {
			add_settings_error( 'scp_smtp', 'invalid_email', __( 'Por favor, introduce un email válido.', 'scp-easy-smtp' ), 'error' );
		} else {
			$subject = __( 'Email de prueba - SCP Easy SMTP', 'scp-easy-smtp' );
			$message = sprintf(
				__( 'Este es un email de prueba enviado desde %s usando SCP Easy SMTP.', 'scp-easy-smtp' ),
				get_bloginfo( 'name' )
			);

			$sent = wp_mail( $test_email, $subject, $message );

			if ( $sent ) {
				add_settings_error( 'scp_smtp', 'test_sent', __( 'Email de prueba enviado correctamente.', 'scp-easy-smtp' ), 'success' );
			} else {
				add_settings_error( 'scp_smtp', 'test_failed', __( 'Error al enviar el email de prueba. Revisa la configuración SMTP y los logs de debug.', 'scp-easy-smtp' ), 'error' );
			}
		}
	}

	// Procesar limpieza de logs.
	if ( isset( $_POST['scp_smtp_clear_logs'] ) ) {
		check_admin_referer( 'scp_smtp_clear_logs_action', 'scp_smtp_clear_logs_nonce' );

		$logger = new Scp_Email_Logger();
		$days = isset( $_POST['clear_logs_days'] ) ? absint( $_POST['clear_logs_days'] ) : 30;
		$logger->clear_old_logs( $days );

		add_settings_error( 'scp_smtp', 'logs_cleared', __( 'Logs antiguos eliminados correctamente.', 'scp-easy-smtp' ), 'success' );
	}

	// Procesar limpieza del archivo de debug.
	if ( isset( $_POST['scp_smtp_clear_debug'] ) ) {
		check_admin_referer( 'scp_smtp_clear_debug_action', 'scp_smtp_clear_debug_nonce' );

		Scp_SMTP_Debug_Logger::clear_log();
		add_settings_error( 'scp_smtp', 'debug_cleared', __( 'Archivo de debug limpiado correctamente.', 'scp-easy-smtp' ), 'success' );
	}

	// Procesar guardado de plantillas.
	if ( isset( $_POST['scp_smtp_save_templates'] ) ) {
		if ( Scp_Email_Templates::save_templates_from_post( $_POST ) ) {
			add_settings_error( 'scp_smtp', 'templates_saved', __( 'Plantillas guardadas correctamente.', 'scp-easy-smtp' ), 'success' );
		} else {
			add_settings_error( 'scp_smtp', 'templates_error', __( 'Error de seguridad al guardar las plantillas.', 'scp-easy-smtp' ), 'error' );
		}
	}
}

/**
 * Renderiza la pestaña Dashboard.
 */
function scp_smtp_render_dashboard_tab() {
	settings_errors( 'scp_smtp' );

	$config = new Scp_SMTP_Config();
	$smtp_config = $config->get_smtp_config();
	$config_exists = $smtp_config && $config->validate_config( $smtp_config );

	?>
	<div class="scp-smtp-dashboard">
		<h2><?php esc_html_e( 'Estado de la Configuración SMTP', 'scp-easy-smtp' ); ?></h2>

		<?php if ( $config_exists ) : ?>
			<div class="notice notice-success inline">
				<p><strong><?php esc_html_e( '✓ Configuración SMTP válida y cargada correctamente', 'scp-easy-smtp' ); ?></strong></p>
			</div>

			<table class="widefat fixed" style="max-width: 600px; margin-top: 20px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Parámetro', 'scp-easy-smtp' ); ?></th>
						<th><?php esc_html_e( 'Valor', 'scp-easy-smtp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Host SMTP', 'scp-easy-smtp' ); ?></strong></td>
						<td><?php echo esc_html( $smtp_config['host'] ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Puerto', 'scp-easy-smtp' ); ?></strong></td>
						<td><?php echo esc_html( $smtp_config['port'] ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Seguridad', 'scp-easy-smtp' ); ?></strong></td>
						<td><?php echo esc_html( strtoupper( $smtp_config['secure'] ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Usuario', 'scp-easy-smtp' ); ?></strong></td>
						<td><?php echo esc_html( $smtp_config['username'] ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'From Email', 'scp-easy-smtp' ); ?></strong></td>
						<td><?php echo esc_html( $smtp_config['from'] ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'From Name', 'scp-easy-smtp' ); ?></strong></td>
						<td><?php echo esc_html( $smtp_config['fromname'] ); ?></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top: 20px;">
				<strong><?php esc_html_e( 'Archivo de configuración:', 'scp-easy-smtp' ); ?></strong>
				<code><?php echo esc_html( $config->get_config_path() ); ?></code>
			</p>
		<?php else : ?>
			<div class="notice notice-error inline">
				<p><strong><?php esc_html_e( '⚠ No se encontró la configuración SMTP o es inválida', 'scp-easy-smtp' ); ?></strong></p>
				<p><?php esc_html_e( 'El archivo de configuración debe estar en:', 'scp-easy-smtp' ); ?> <code><?php echo esc_html( $config->get_config_path() ); ?></code></p>
			</div>
		<?php endif; ?>

		<hr style="margin: 30px 0;">

		<h2><?php esc_html_e( 'Correcciones de Dominio Activas', 'scp-easy-smtp' ); ?></h2>

		<?php
		$corrections = Scp_Email_Domain_Fixer::get_all_corrections();
		if ( ! empty( $corrections ) ) :
			?>
			<table class="widefat fixed" style="max-width: 600px; margin-top: 20px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Dominio Incorrecto', 'scp-easy-smtp' ); ?></th>
						<th><?php esc_html_e( 'Dominio Correcto', 'scp-easy-smtp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $corrections, 0, 10 ) as $wrong => $correct ) : ?>
						<tr>
							<td><code><?php echo esc_html( $wrong ); ?></code></td>
							<td><code><?php echo esc_html( $correct ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( count( $corrections ) > 10 ) : ?>
				<p><em><?php printf( esc_html__( 'Y %d correcciones más...', 'scp-easy-smtp' ), count( $corrections ) - 10 ); ?></em></p>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No hay correcciones de dominio configuradas.', 'scp-easy-smtp' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renderiza la pestaña de Envío de Prueba.
 */
function scp_smtp_render_test_tab() {
	settings_errors( 'scp_smtp' );
	?>
	<div class="scp-smtp-test">
		<h2><?php esc_html_e( 'Enviar Email de Prueba', 'scp-easy-smtp' ); ?></h2>
		<p><?php esc_html_e( 'Envía un email de prueba para verificar que la configuración SMTP funciona correctamente.', 'scp-easy-smtp' ); ?></p>

		<form method="post" action="">
			<?php wp_nonce_field( 'scp_smtp_test_email_action', 'scp_smtp_test_email_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="test_email"><?php esc_html_e( 'Email de destino', 'scp-easy-smtp' ); ?></label>
					</th>
					<td>
						<input type="email" name="test_email" id="test_email" class="regular-text" required
							placeholder="ejemplo@gmail.com" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Introduce un email para enviar la prueba. Puedes usar un typo como "gmial.com" para probar la corrección de dominios.', 'scp-easy-smtp' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<input type="hidden" name="scp_smtp_send_test" value="1">
			<?php submit_button( __( 'Enviar Email de Prueba', 'scp-easy-smtp' ), 'primary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Renderiza la pestaña de Logs.
 */
function scp_smtp_render_logs_tab() {
	settings_errors( 'scp_smtp' );

	$logger = new Scp_Email_Logger();
	$recent_emails = $logger->get_recent_emails( 50 );
	$recent_corrections = $logger->get_recent_corrections( 50 );
	?>
	<div class="scp-smtp-logs">
		<h2><?php esc_html_e( 'Últimos Emails Enviados', 'scp-easy-smtp' ); ?></h2>

		<?php if ( ! empty( $recent_emails ) ) : ?>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Fecha', 'scp-easy-smtp' ); ?></th>
						<th><?php esc_html_e( 'Destinatario', 'scp-easy-smtp' ); ?></th>
						<th><?php esc_html_e( 'Asunto', 'scp-easy-smtp' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'scp-easy-smtp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_emails as $email ) : ?>
						<tr>
							<td><?php echo esc_html( $email->created_at ); ?></td>
							<td><?php echo esc_html( $email->email_to ); ?></td>
							<td><?php echo esc_html( $email->subject ); ?></td>
							<td><span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php echo esc_html( ucfirst( $email->status ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No hay emails registrados todavía.', 'scp-easy-smtp' ); ?></p>
		<?php endif; ?>

		<hr style="margin: 30px 0;">

		<h2><?php esc_html_e( 'Últimas Correcciones de Dominio', 'scp-easy-smtp' ); ?></h2>

		<?php if ( ! empty( $recent_corrections ) ) : ?>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Fecha', 'scp-easy-smtp' ); ?></th>
						<th><?php esc_html_e( 'Email Original', 'scp-easy-smtp' ); ?></th>
						<th><?php esc_html_e( 'Email Corregido', 'scp-easy-smtp' ); ?></th>
						<th><?php esc_html_e( 'Corrección', 'scp-easy-smtp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_corrections as $correction ) : ?>
						<tr>
							<td><?php echo esc_html( $correction->created_at ); ?></td>
							<td><?php echo esc_html( $correction->original_email ); ?></td>
							<td><?php echo esc_html( $correction->corrected_email ); ?></td>
							<td>
								<code><?php echo esc_html( $correction->original_domain ); ?></code>
								→
								<code><?php echo esc_html( $correction->corrected_domain ); ?></code>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No hay correcciones registradas todavía.', 'scp-easy-smtp' ); ?></p>
		<?php endif; ?>

		<hr style="margin: 30px 0;">

		<h2><?php esc_html_e( 'Limpiar Logs Antiguos', 'scp-easy-smtp' ); ?></h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'scp_smtp_clear_logs_action', 'scp_smtp_clear_logs_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="clear_logs_days"><?php esc_html_e( 'Eliminar logs anteriores a', 'scp-easy-smtp' ); ?></label>
					</th>
					<td>
						<input type="number" name="clear_logs_days" id="clear_logs_days" min="1" max="365" value="30" style="width: 80px;">
						<?php esc_html_e( 'días', 'scp-easy-smtp' ); ?>
					</td>
				</tr>
			</table>

			<input type="hidden" name="scp_smtp_clear_logs" value="1">
			<?php submit_button( __( 'Limpiar Logs', 'scp-easy-smtp' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Renderiza la pestaña de Estadísticas.
 */
function scp_smtp_render_stats_tab() {
	$logger = new Scp_Email_Logger();
	$stats = $logger->get_correction_stats();
	?>
	<div class="scp-smtp-stats">
		<h2><?php esc_html_e( 'Estadísticas de Correcciones de Dominio', 'scp-easy-smtp' ); ?></h2>

		<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; max-width: 300px; margin: 20px 0;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Total de Correcciones', 'scp-easy-smtp' ); ?></h3>
			<p style="font-size: 48px; font-weight: bold; color: #2271b1; margin: 0;">
				<?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?>
			</p>
		</div>

		<?php if ( ! empty( $stats['top_corrections'] ) ) : ?>
			<h3><?php esc_html_e( 'Top 10 Correcciones Más Frecuentes', 'scp-easy-smtp' ); ?></h3>
			<table class="widefat fixed striped" style="max-width: 700px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Dominio Incorrecto', 'scp-easy-smtp' ); ?></th>
						<th><?php esc_html_e( 'Dominio Correcto', 'scp-easy-smtp' ); ?></th>
						<th><?php esc_html_e( 'Veces Corregido', 'scp-easy-smtp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stats['top_corrections'] as $correction ) : ?>
						<tr>
							<td><code><?php echo esc_html( $correction->original_domain ); ?></code></td>
							<td><code><?php echo esc_html( $correction->corrected_domain ); ?></code></td>
							<td><strong><?php echo esc_html( number_format_i18n( $correction->count ) ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No hay estadísticas disponibles todavía.', 'scp-easy-smtp' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renderiza la pestaña de Debug.
 */
function scp_smtp_render_debug_tab() {
	settings_errors( 'scp_smtp' );

	$log_file = Scp_SMTP_Debug_Logger::get_log_file();
	$recent_logs = Scp_SMTP_Debug_Logger::get_recent_logs( 100 );
	?>
	<div class="scp-smtp-debug">
		<h2><?php esc_html_e( 'Logs de Debug', 'scp-easy-smtp' ); ?></h2>

		<p>
			<strong><?php esc_html_e( 'Archivo de log:', 'scp-easy-smtp' ); ?></strong>
			<code><?php echo esc_html( $log_file ); ?></code>
		</p>

		<form method="post" action="" style="margin-bottom: 20px;">
			<?php wp_nonce_field( 'scp_smtp_clear_debug_action', 'scp_smtp_clear_debug_nonce' ); ?>
			<input type="hidden" name="scp_smtp_clear_debug" value="1">
			<?php submit_button( __( 'Limpiar Archivo de Debug', 'scp-easy-smtp' ), 'secondary', 'submit', false ); ?>
		</form>

		<h3><?php esc_html_e( 'Últimas 100 líneas del log', 'scp-easy-smtp' ); ?></h3>
		<textarea readonly style="width: 100%; height: 500px; font-family: monospace; font-size: 12px; background: #f0f0f1; border: 1px solid #c3c4c7; padding: 10px;"><?php echo esc_textarea( $recent_logs ); ?></textarea>
	</div>
	<?php
}

/**
 * Renderiza la pestaña de Ayuda.
 */
function scp_smtp_render_help_tab() {
	?>
	<div class="scp-smtp-help">
		<h2><?php esc_html_e( 'Ayuda y Documentación', 'scp-easy-smtp' ); ?></h2>

		<div class="card">
			<h3><?php esc_html_e( 'Configuración SMTP', 'scp-easy-smtp' ); ?></h3>
			<p><?php esc_html_e( 'El archivo de configuración debe estar ubicado fuera del directorio web por seguridad:', 'scp-easy-smtp' ); ?></p>
			<pre style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
; scp-config.ini
[smtp]
host = smtp.ejemplo.com
port = 587
username = usuario@ejemplo.com
password = tu_contraseña
secure = tls
from = noreply@ejemplo.com
fromname = Nombre del Sitio
			</pre>
		</div>

		<div class="card">
			<h3><?php esc_html_e( 'Corrección de Dominios', 'scp-easy-smtp' ); ?></h3>
			<p><?php esc_html_e( 'El plugin corrige automáticamente errores comunes en dominios de email:', 'scp-easy-smtp' ); ?></p>
			<ul>
				<li><code>gmial.com</code> → <code>gmail.com</code></li>
				<li><code>hotmal.com</code> → <code>hotmail.com</code></li>
				<li><code>outlok.com</code> → <code>outlook.com</code></li>
				<li><?php esc_html_e( 'Y muchos más...', 'scp-easy-smtp' ); ?></li>
			</ul>
		</div>

		<div class="card">
			<h3><?php esc_html_e( 'Solución de Problemas', 'scp-easy-smtp' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Si los emails no se envían, verifica la configuración SMTP en la pestaña Dashboard.', 'scp-easy-smtp' ); ?></li>
				<li><?php esc_html_e( 'Revisa los logs de debug para ver errores detallados.', 'scp-easy-smtp' ); ?></li>
				<li><?php esc_html_e( 'Asegúrate de que el archivo scp-config.ini tenga permisos correctos (600 o 640).', 'scp-easy-smtp' ); ?></li>
				<li><?php esc_html_e( 'Envía un email de prueba desde la pestaña "Enviar Prueba".', 'scp-easy-smtp' ); ?></li>
			</ul>
		</div>

		<div class="card">
			<h3><?php esc_html_e( 'Soporte', 'scp-easy-smtp' ); ?></h3>
			<p>
				<?php esc_html_e( 'Para soporte técnico, visita:', 'scp-easy-smtp' ); ?>
				<a href="https://scopweb.com" target="_blank">scopweb.com</a>
			</p>
		</div>
	</div>
	<?php
}

/**
 * Renderiza la pestaña de Plantillas.
 */
function scp_smtp_render_templates_tab() {
	settings_errors( 'scp_smtp' );

	$languages = Scp_Email_Templates::get_registered_languages();
	$templates = Scp_Email_Templates::get_templates();
	$default_lang = get_option( 'scp_smtp_default_language', 'auto' );
	?>
	<div class="scp-smtp-templates">
		<h2><?php esc_html_e( 'Plantillas de Email Multi-idioma', 'scp-easy-smtp' ); ?></h2>

		<form method="post" action="">
			<?php wp_nonce_field( 'scp_smtp_save_templates', 'scp_smtp_templates_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="default_language"><?php esc_html_e( 'Idioma por Defecto', 'scp-easy-smtp' ); ?></label>
					</th>
					<td>
						<select name="default_language" id="default_language">
							<option value="auto" <?php selected( $default_lang, 'auto' ); ?>><?php esc_html_e( 'Detección Automática', 'scp-easy-smtp' ); ?></option>
							<?php foreach ( $languages as $code => $config ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default_lang, $code ); ?>>
									<?php echo esc_html( $config['flag'] . ' ' . $config['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Plantillas por Idioma', 'scp-easy-smtp' ); ?></h3>

			<?php foreach ( $languages as $code => $config ) : ?>
				<div class="scp-smtp-template-section" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
					<h4><?php echo esc_html( $config['flag'] . ' ' . $config['name'] ); ?></h4>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="template_subject_<?php echo esc_attr( $code ); ?>"><?php esc_html_e( 'Asunto', 'scp-easy-smtp' ); ?></label>
							</th>
							<td>
								<input type="text"
									name="template_subject_<?php echo esc_attr( $code ); ?>"
									id="template_subject_<?php echo esc_attr( $code ); ?>"
									class="large-text"
									value="<?php echo esc_attr( $templates[ $code ]['subject'] ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="template_body_<?php echo esc_attr( $code ); ?>"><?php esc_html_e( 'Cuerpo', 'scp-easy-smtp' ); ?></label>
							</th>
							<td>
								<?php
								wp_editor(
									$templates[ $code ]['body'],
									'template_body_' . $code,
									[
										'textarea_name' => 'template_body_' . $code,
										'textarea_rows' => 10,
										'media_buttons' => false,
										'teeny'         => true,
									]
								);
								?>
								<p class="description">
									<?php esc_html_e( 'Puedes usar estos placeholders: {SITE_NAME}, {SITE_URL}', 'scp-easy-smtp' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			<?php endforeach; ?>

			<hr style="margin: 30px 0;">

			<h3><?php esc_html_e( 'Añadir Nuevo Idioma', 'scp-easy-smtp' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="new_lang_code"><?php esc_html_e( 'Código del Idioma', 'scp-easy-smtp' ); ?></label>
					</th>
					<td>
						<input type="text" name="new_lang_code" id="new_lang_code" class="regular-text" placeholder="pt">
						<p class="description"><?php esc_html_e( 'Código ISO de 2 letras (ej: pt, de, it)', 'scp-easy-smtp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="new_lang_name"><?php esc_html_e( 'Nombre del Idioma', 'scp-easy-smtp' ); ?></label>
					</th>
					<td>
						<input type="text" name="new_lang_name" id="new_lang_name" class="regular-text" placeholder="Português">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="new_lang_flag"><?php esc_html_e( 'Bandera', 'scp-easy-smtp' ); ?></label>
					</th>
					<td>
						<input type="text" name="new_lang_flag" id="new_lang_flag" class="regular-text" placeholder="🇵🇹">
						<p class="description"><?php esc_html_e( 'Emoji de bandera', 'scp-easy-smtp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="new_lang_locales"><?php esc_html_e( 'Locales', 'scp-easy-smtp' ); ?></label>
					</th>
					<td>
						<input type="text" name="new_lang_locales" id="new_lang_locales" class="large-text" placeholder="pt_PT, pt_BR">
						<p class="description"><?php esc_html_e( 'Locales separados por comas', 'scp-easy-smtp' ); ?></p>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" name="scp_smtp_add_language" class="button button-secondary">
					<?php esc_html_e( 'Añadir Idioma', 'scp-easy-smtp' ); ?>
				</button>
			</p>

			<hr style="margin: 30px 0;">

			<input type="hidden" name="scp_smtp_save_templates" value="1">
			<?php submit_button( __( 'Guardar Todas las Plantillas', 'scp-easy-smtp' ), 'primary' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Maneja el envío de emails de prueba via AJAX.
 */
function scp_smtp_ajax_send_test_email() {
	// Verificar nonce.
	check_ajax_referer( 'scp_smtp_ajax_nonce', 'nonce' );

	// Verificar permisos.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'No tienes permisos suficientes.', 'scp-easy-smtp' ) ] );
	}

	// Sanitizar email.
	$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

	if ( ! is_email( $email ) ) {
		wp_send_json_error( [ 'message' => __( 'Email inválido.', 'scp-easy-smtp' ) ] );
	}

	// Enviar email.
	$subject = __( 'Email de prueba - SCP Easy SMTP', 'scp-easy-smtp' );
	$message = sprintf(
		__( 'Este es un email de prueba enviado desde %s usando SCP Easy SMTP via AJAX.', 'scp-easy-smtp' ),
		get_bloginfo( 'name' )
	);

	$sent = wp_mail( $email, $subject, $message );

	if ( $sent ) {
		wp_send_json_success( [ 'message' => __( 'Email enviado correctamente.', 'scp-easy-smtp' ) ] );
	} else {
		wp_send_json_error( [ 'message' => __( 'Error al enviar el email.', 'scp-easy-smtp' ) ] );
	}
}
