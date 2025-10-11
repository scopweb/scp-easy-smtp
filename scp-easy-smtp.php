<?php
/**
 * Plugin Name:       SCP Easy SMTP
 * Plugin URI:        https://scopweb.com/
 * Description:       Plugin profesional de SMTP con corrección automática de dominios de email y una interfaz de administración moderna.
 * Version:           2.1.0
 * Author:            ScopWeb
 * Author URI:        https://scopweb.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       scp-easy-smtp
 * Domain Path:       /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SCP_SMTP_VERSION', '2.1.0' );
define( 'SCP_SMTP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCP_SMTP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SCP_SMTP_REQUIRED_PHP_VERSION', '7.4' ); // Ejemplo de requisito

/**
 * Carga las dependencias del plugin.
 */
require_once SCP_SMTP_PLUGIN_DIR . 'inc/email-domain-fixer.php';
require_once SCP_SMTP_PLUGIN_DIR . 'inc/smtp-config.php';
require_once SCP_SMTP_PLUGIN_DIR . 'inc/email-logger.php';
require_once SCP_SMTP_PLUGIN_DIR . 'inc/debug-logger.php';
require_once SCP_SMTP_PLUGIN_DIR . 'inc/email-templates.php';
require_once SCP_SMTP_PLUGIN_DIR . 'admin/settings-page.php';

/**
 * Clase principal del plugin SCP Easy SMTP.
 *
 * Orquesta la inicialización de hooks, la configuración de SMTP
 * y el procesamiento de correos electrónicos.
 *
 * @since 2.0.0
 */
final class Scp_Easy_SMTP_Plugin {

    /**
     * Instancia de la clase de configuración.
     *
     * @var Scp_SMTP_Config
     */
    private $config;

    /**
     * Instancia de la clase de registro de correos.
     *
     * @var Scp_Email_Logger
     */
    private $logger;

    /**
     * Única instancia de la clase.
     *
     * @var Scp_Easy_SMTP_Plugin|null
     */
    private static $instance = null;

    /**
     * Constructor de la clase.
     *
     * Es privado para forzar el uso de `get_instance()`.
     * Inicializa las propiedades y registra los hooks de WordPress.
     */
    private function __construct() {
        $this->check_php_version();
        
        $this->config = new Scp_SMTP_Config();
        $this->logger = new Scp_Email_Logger();

        $this->add_hooks();
    }

    /**
     * Obtiene la instancia única de la clase (patrón Singleton).
     *
     * @return Scp_Easy_SMTP_Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra los hooks de WordPress.
     */
    private function add_hooks() {
        // Configuración de SMTP con alta prioridad
        add_action( 'phpmailer_init', [ $this, 'configure_smtp' ], 999 );

        // Procesamiento de emails (filtro)
        add_filter( 'wp_mail', [ $this, 'process_email' ] );

        // Menú y assets del área de administración
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_scp_smtp_send_test_email', 'scp_smtp_ajax_send_test_email' );

        // Hooks de activación y desactivación
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }
    
    /**
     * Verifica la versión de PHP y muestra un aviso si no es compatible.
     */
    private function check_php_version() {
        if ( version_compare( PHP_VERSION, SCP_SMTP_REQUIRED_PHP_VERSION, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'php_version_notice' ] );
            // Desactiva los hooks principales si la versión no es compatible
            remove_action( 'phpmailer_init', [ $this, 'configure_smtp' ], 999 );
            remove_filter( 'wp_mail', [ $this, 'process_email' ] );
        }
    }

    /**
     * Muestra el aviso de versión de PHP no compatible.
     */
    public function php_version_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: 1: Plugin name, 2: Required PHP version, 3: Current PHP version */
                    esc_html__( '%1$s requires PHP version %2$s or higher. You are running version %3$s. Please update your PHP version.', 'scp-easy-smtp' ),
                    '<strong>' . esc_html__( 'SCP Easy SMTP', 'scp-easy-smtp' ) . '</strong>',
                    esc_html( SCP_SMTP_REQUIRED_PHP_VERSION ),
                    esc_html( PHP_VERSION )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Configura PHPMailer para usar SMTP.
     *
     * Se ejecuta en el hook `phpmailer_init`.
     *
     * @param PHPMailer $phpmailer Instancia de PHPMailer.
     */
    public function configure_smtp( $phpmailer ) {
        Scp_SMTP_Debug_Logger::log_phpmailer_init();

        $smtp_config = $this->config->get_smtp_config();
        $is_valid    = $smtp_config && $this->config->validate_config( $smtp_config );

        Scp_SMTP_Debug_Logger::log_smtp_config( $smtp_config ?: [], $is_valid );

        if ( ! $is_valid ) {
            Scp_SMTP_Debug_Logger::log( 'ERROR: Invalid or missing SMTP configuration.' );
            error_log( 'SCP SMTP: Invalid or missing SMTP configuration.' );
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $smtp_config['host'];
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Port       = $smtp_config['port'];
        $phpmailer->Username   = $smtp_config['username'];
        $phpmailer->Password   = $smtp_config['password'];
        $phpmailer->SMTPSecure = $smtp_config['secure'];
        $phpmailer->From       = $smtp_config['from'];
        $phpmailer->FromName   = $smtp_config['fromname'];

        // Activa el modo debug de SMTP si WP_DEBUG está habilitado.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $phpmailer->SMTPDebug = 2; // Muestra comunicación cliente-servidor.
        }

        Scp_SMTP_Debug_Logger::log( 'SMTP configured successfully.' );
    }

    /**
     * Procesa los argumentos del correo antes de ser enviado.
     *
     * Corrige dominios de email y gestiona las plantillas de correo.
     *
     * @param array $args Argumentos de la función `wp_mail`.
     * @return array Argumentos modificados.
     */
    public function process_email( $args ) {
        try {
            Scp_SMTP_Debug_Logger::log_wp_mail( $args );

            // 1. Corrige el email del destinatario principal.
            $args['to'] = $this->correct_recipient_email( $args['to'] );

            // 2. Corrige el email dentro del cuerpo del mensaje (si existe).
            $args['message'] = $this->correct_email_in_body( $args['message'] );

            // 3. Registra el intento de envío.
            $this->logger->log_email( $args );

            // 4. Procesa y envía confirmación automática si está habilitado.
            $this->send_auto_confirmation( $args );

        } catch ( Exception $e ) {
            Scp_SMTP_Debug_Logger::log( 'ERROR in process_email: ' . $e->getMessage() );
        }

        return $args;
    }

    /**
     * Envía una confirmación automática basada en los datos del formulario.
     *
     * Extrae los datos necesarios del cuerpo del mensaje para personalizar
     * y enviar un email de confirmación al remitente original.
     *
     * @param array $args Argumentos de la función `wp_mail`.
     */
    private function send_auto_confirmation( $args ) {
        // Asumimos que el email del remitente está en el cuerpo del mensaje.
        // Esta lógica puede necesitar ser más robusta dependiendo de los formularios.
        if ( preg_match( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $args['message'], $matches ) ) {
            $recipient_email = $matches[0];
            
            // Prepara datos extra para la plantilla.
            // Aquí se podrían extraer más datos del cuerpo del mensaje si fuera necesario.
            $extra_data = [];

            // Intenta detectar el idioma si no se pasa explícitamente.
            // Por ahora, la detección se basará en la configuración del plugin o el locale.
            
            Scp_Email_Templates::send_confirmation_email( $recipient_email, $extra_data );
        }
    }

    /**
     * Corrige el dominio del email del destinatario.
     *
     * @param string|array $to Email o array de emails.
     * @return string|array Email(s) corregido(s).
     */
    private function correct_recipient_email( $to ) {
        if ( empty( $to ) ) {
            return $to;
        }

        $original_to = is_array( $to ) ? $to[0] : $to;
        $corrected_to = Scp_Email_Domain_Fixer::fix_email_domain( $original_to );

        if ( $corrected_to !== $original_to ) {
            Scp_SMTP_Debug_Logger::log( 'Recipient email corrected', [
                'original' => $original_to,
                'corrected' => $corrected_to,
            ]);
            $this->logger->log_correction( $original_to, $corrected_to );

            if ( is_array( $to ) ) {
                $to[0] = $corrected_to;
            } else {
                $to = $corrected_to;
            }
        }
        return $to;
    }

    /**
     * Busca y corrige un email dentro del cuerpo de un mensaje.
     *
     * @param string $message Cuerpo del mensaje.
     * @return string Cuerpo del mensaje modificado.
     */
    private function correct_email_in_body( $message ) {
        // Expresión regular mejorada para encontrar un email.
        if ( preg_match( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $message, $matches ) ) {
            $email_in_body = $matches[0];
            $corrected_email = Scp_Email_Domain_Fixer::fix_email_domain( $email_in_body );

            if ( $corrected_email !== $email_in_body ) {
                Scp_SMTP_Debug_Logger::log( 'Email in message body corrected', [
                    'original' => $email_in_body,
                    'corrected' => $corrected_email,
                ]);
                $this->logger->log_correction( $email_in_body, $corrected_email );
                $message = str_replace( $email_in_body, $corrected_email, $message );
            }
        }
        return $message;
    }

    /**
     * Registra la página de ajustes en el menú de administración.
     */
    public function register_settings_page() {
        add_options_page(
            __( 'SCP SMTP Settings', 'scp-easy-smtp' ),
            __( 'SCP SMTP', 'scp-easy-smtp' ),
            'manage_options',
            'scp-smtp-settings',
            'scp_smtp_render_settings_page'
        );
    }

    /**
     * Encola los scripts y estilos para la página de ajustes.
     *
     * @param string $hook Hook de la página actual.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_scp-smtp-settings' !== $hook ) {
            return;
        }

        // Cargar los estilos básicos de admin
        wp_enqueue_style(
            'scp-smtp-admin',
            SCP_SMTP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SCP_SMTP_VERSION
        );
        
        // Cargar estilos personalizados para destacar idiomas
        wp_enqueue_style(
            'scp-smtp-admin-custom',
            SCP_SMTP_PLUGIN_URL . 'assets/css/admin-custom.css',
            ['scp-smtp-admin'],
            SCP_SMTP_VERSION
        );

        // Cargar los scripts básicos
        wp_enqueue_script(
            'scp-smtp-admin',
            SCP_SMTP_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            SCP_SMTP_VERSION,
            true
        );
        
        // Cargar el editor de WordPress si estamos en la pestaña de plantillas
        if (isset($_GET['tab']) && $_GET['tab'] === 'templates') {
            wp_enqueue_editor();
            wp_enqueue_media();
            
            // Añadir script para inicializar los editores si ya no están inicializados
            wp_add_inline_script('scp-smtp-admin', "
                jQuery(document).ready(function($) {
                    if (typeof tinymce !== 'undefined') {
                        tinymce.remove();
                        tinymce.init({
                            selector: 'textarea.wp-editor-area',
                            height: 200,
                            menubar: false,
                            plugins: 'lists link paste',
                            toolbar: 'bold italic bullist numlist link removeformat code'
                        });
                    }
                });
            ");
        }
    }

    /**
     * Acciones a realizar en la activación del plugin.
     */
    public function activate() {
        // Crea las tablas en la base de datos si no existen.
        $this->logger->create_tables();

        // Establece opciones por defecto.
        add_option( 'scp_smtp_version', SCP_SMTP_VERSION );
        
        // Limpia cualquier tarea programada anterior.
        if ( wp_next_scheduled( 'scp_smtp_cleanup_task' ) ) {
            wp_clear_scheduled_hook( 'scp_smtp_cleanup_task' );
        }
    }

    /**
     * Acciones a realizar en la desactivación del plugin.
     */
    public function deactivate() {
        // Podríamos limpiar tareas programadas si las hubiera.
        // wp_clear_scheduled_hook('my_hourly_event');
    }
}

/**
 * Función de inicialización del plugin.
 *
 * Se asegura de que el plugin se cargue solo una vez.
 */
function scp_easy_smtp_init() {
    Scp_Easy_SMTP_Plugin::get_instance();
}

// Inicia el plugin en el hook `plugins_loaded`.
add_action( 'plugins_loaded', 'scp_easy_smtp_init' );
