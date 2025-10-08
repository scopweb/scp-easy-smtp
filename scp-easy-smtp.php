<?php
/**
 * Plugin Name: SCP Easy SMTP
 * Plugin URI: https://scopweb.com/
 * Description: Plugin profesional de SMTP con corrección automática de dominios de email y interfaz moderna
 * Version: 2.0.0
 * Author: ScopWeb
 * Author URI: https://scopweb.com/
 * License: GPL v2 or later
 * Text Domain: scp-easy-smtp
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SCP_SMTP_VERSION', '2.0.0');
define('SCP_SMTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCP_SMTP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load dependencies
require_once SCP_SMTP_PLUGIN_DIR . 'inc/email-domain-fixer.php';
require_once SCP_SMTP_PLUGIN_DIR . 'inc/smtp-config.php';
require_once SCP_SMTP_PLUGIN_DIR . 'inc/email-logger.php';
require_once SCP_SMTP_PLUGIN_DIR . 'inc/debug-logger.php';
require_once SCP_SMTP_PLUGIN_DIR . 'admin/settings-page.php';

class Scp_Easy_SMTP_Plugin {

    private $config;
    private $logger;

    public function __construct() {
        $this->config = new Scp_SMTP_Config();
        $this->logger = new Scp_Email_Logger();

        // SMTP Configuration - PRIORIDAD ALTA
        add_action('phpmailer_init', [$this, 'configure_smtp'], 999);

        // Email processing - wp_mail es un FILTER, no action
        add_filter('wp_mail', [$this, 'process_email'], 1, 1);

        // Admin menu
        add_action('admin_menu', [$this, 'register_settings_page']);

        // Admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function configure_smtp($phpmailer) {
        Scp_SMTP_Debug_Logger::log_phpmailer_init();

        $smtp_config = $this->config->get_smtp_config();
        $is_valid = $smtp_config && $this->config->validate_config($smtp_config);

        Scp_SMTP_Debug_Logger::log_smtp_config($smtp_config ?: [], $is_valid);

        if ($is_valid) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $smtp_config['host'];
            $phpmailer->SMTPAuth = true;
            $phpmailer->Port = $smtp_config['port'];
            $phpmailer->Username = $smtp_config['username'];
            $phpmailer->Password = $smtp_config['password'];
            $phpmailer->SMTPSecure = $smtp_config['secure'];
            $phpmailer->From = $smtp_config['from'];
            $phpmailer->FromName = $smtp_config['fromname'];

            // Debug mode if enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $phpmailer->SMTPDebug = 2;
            }

            Scp_SMTP_Debug_Logger::log("SMTP configurado correctamente");
        } else {
            Scp_SMTP_Debug_Logger::log("ERROR: Configuración SMTP no válida o no encontrada");
            error_log('SCP SMTP: Configuración SMTP no válida o no encontrada');
        }
    }

    public function process_email($args) {
        try {
            Scp_SMTP_Debug_Logger::log_wp_mail($args);

            // 1. Fix recipient email (to:)
            if (!empty($args['to'])) {
                $original_to = is_array($args['to']) ? $args['to'][0] : $args['to'];
                $corrected_to = Scp_Email_Domain_Fixer::fix_email_domain($original_to);

                if ($corrected_to !== $original_to) {
                    Scp_SMTP_Debug_Logger::log("Email destinatario CORREGIDO", [
                        'original' => $original_to,
                        'corregido' => $corrected_to
                    ]);

                    // Update the 'to' field
                    if (is_array($args['to'])) {
                        $args['to'][0] = $corrected_to;
                    } else {
                        $args['to'] = $corrected_to;
                    }

                    // Log the correction
                    $this->logger->log_correction($original_to, $corrected_to);
                }
            }

            // 2. Fix email in message body (for contact forms that include email in body)
            preg_match('/Email: ([^\s]+)/', $args['message'], $matches);
            $email = isset($matches[1]) ? $matches[1] : '';

            if (!empty($email)) {
                $corrected_email = Scp_Email_Domain_Fixer::fix_email_domain($email);

                if ($corrected_email !== $email) {
                    Scp_SMTP_Debug_Logger::log("Email en mensaje CORREGIDO", [
                        'original' => $email,
                        'corregido' => $corrected_email
                    ]);

                    // Update message
                    $args['message'] = str_replace(
                        'Email: ' . $email,
                        'Email: ' . $corrected_email,
                        $args['message']
                    );

                    // Log the correction
                    $this->logger->log_correction($email, $corrected_email);
                }
            }

            // Log email attempt
            $this->logger->log_email($args);
        } catch (Exception $e) {
            Scp_SMTP_Debug_Logger::log("ERROR en process_email: " . $e->getMessage());
        }

        // IMPORTANTE: Siempre retornar $args para que el email continúe
        return $args;
    }

    public function register_settings_page() {
        add_options_page(
            __('SCP SMTP Settings', 'scp-easy-smtp'),
            __('SCP SMTP', 'scp-easy-smtp'),
            'manage_options',
            'scp-smtp-settings',
            'scp_smtp_render_settings_page'
        );
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_scp-smtp-settings') {
            return;
        }

        wp_enqueue_style(
            'scp-smtp-admin',
            SCP_SMTP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SCP_SMTP_VERSION
        );

        wp_enqueue_script(
            'scp-smtp-admin',
            SCP_SMTP_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            SCP_SMTP_VERSION,
            true
        );
    }

    public function activate() {
        // Create database tables for logs
        $this->logger->create_tables();

        // Set default options
        add_option('scp_smtp_version', SCP_SMTP_VERSION);
    }

    public function deactivate() {
        // Cleanup if needed
    }
}

// Initialize plugin
new Scp_Easy_SMTP_Plugin();
