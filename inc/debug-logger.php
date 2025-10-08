<?php
/**
 * Debug Logger Class
 * Comprehensive debugging and logging for SMTP operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scp_SMTP_Debug_Logger {

    private static $log_file = null;
    private static $enabled = true;

    /**
     * Initialize log file path
     */
    private static function init() {
        if (self::$log_file === null) {
            self::$log_file = WP_CONTENT_DIR . '/scp-smtp-debug.log';
        }
    }

    /**
     * Log debug message
     */
    public static function log($message, $context = []) {
        self::init();

        if (!self::$enabled) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}";

        if (!empty($context)) {
            $log_entry .= "\n" . print_r($context, true);
        }

        $log_entry .= "\n" . str_repeat('-', 80) . "\n";

        error_log($log_entry, 3, self::$log_file);

        // Also log to PHP error log
        error_log("SCP SMTP: {$message}");
    }

    /**
     * Log SMTP configuration attempt
     */
    public static function log_smtp_config($config, $is_valid) {
        $status = $is_valid ? 'VÁLIDA' : 'INVÁLIDA';
        self::log("Configuración SMTP {$status}", [
            'host' => $config['host'] ?? 'N/A',
            'port' => $config['port'] ?? 'N/A',
            'secure' => $config['secure'] ?? 'N/A',
            'from' => $config['from'] ?? 'N/A',
            'is_valid' => $is_valid
        ]);
    }

    /**
     * Log email processing
     */
    public static function log_email_process($original_email, $corrected_email) {
        if ($original_email !== $corrected_email) {
            self::log("Email CORREGIDO", [
                'original' => $original_email,
                'corregido' => $corrected_email
            ]);
        } else {
            self::log("Email procesado (sin corrección)", [
                'email' => $original_email
            ]);
        }
    }

    /**
     * Log PHPMailer init
     */
    public static function log_phpmailer_init() {
        self::log("PHPMailer inicializado - Hook phpmailer_init ejecutado");
    }

    /**
     * Log wp_mail call
     */
    public static function log_wp_mail($args) {
        self::log("wp_mail llamado", [
            'to' => $args['to'] ?? 'N/A',
            'subject' => $args['subject'] ?? 'N/A',
            'message_preview' => substr($args['message'] ?? '', 0, 100) . '...'
        ]);
    }

    /**
     * Get log file path
     */
    public static function get_log_file() {
        self::init();
        return self::$log_file;
    }

    /**
     * Clear log file
     */
    public static function clear_log() {
        self::init();
        if (file_exists(self::$log_file)) {
            unlink(self::$log_file);
        }
        self::log("Log limpiado");
    }

    /**
     * Get last N lines from log
     */
    public static function get_recent_logs($lines = 50) {
        self::init();

        if (!file_exists(self::$log_file)) {
            return "No hay logs disponibles aún.\n\nEnvía un email de prueba desde arriba para generar logs.";
        }

        $file = file(self::$log_file);
        if (empty($file)) {
            return "El archivo de log está vacío.\n\nEnvía un email de prueba para ver los logs.";
        }

        $total_lines = count($file);
        $start = max(0, $total_lines - $lines);

        return implode('', array_slice($file, $start));
    }

    /**
     * Enable/disable logging
     */
    public static function set_enabled($enabled) {
        self::$enabled = $enabled;
    }
}
