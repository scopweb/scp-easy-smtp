<?php
/**
 * SMTP Configuration Handler
 * Manages SMTP configuration from external ini file
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scp_SMTP_Config {

    private $config_path;
    private $config = null;
    private $config_cache_key = 'scp_smtp_config_cache';

    public function __construct() {
        $this->config_path = ABSPATH . '../scp-config.ini';
    }

    /**
     * Get SMTP configuration
     *
     * @return array|false SMTP configuration array or false if not found
     */
    public function get_smtp_config() {
        // Check cache first
        $cached = get_transient($this->config_cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Load from file
        if (file_exists($this->config_path)) {
            $config = parse_ini_file($this->config_path, true);

            if (isset($config['smtp'])) {
                // Cache for 1 hour
                set_transient($this->config_cache_key, $config['smtp'], HOUR_IN_SECONDS);
                return $config['smtp'];
            }
        }

        error_log('SCP SMTP: Archivo de configuración no encontrado en: ' . $this->config_path);
        return false;
    }

    /**
     * Validate SMTP configuration
     *
     * @param array $config Configuration array to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_config($config) {
        $required_fields = ['host', 'port', 'username', 'password', 'secure', 'from', 'fromname'];

        foreach ($required_fields as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                error_log("SCP SMTP: Campo requerido '{$field}' no encontrado o vacío en la configuración");
                return false;
            }
        }

        // Validate port
        if (!is_numeric($config['port']) || $config['port'] < 1 || $config['port'] > 65535) {
            error_log('SCP SMTP: Puerto inválido');
            return false;
        }

        // Validate secure method
        if (!in_array(strtolower($config['secure']), ['tls', 'ssl', 'none'])) {
            error_log('SCP SMTP: Método de seguridad inválido (debe ser tls, ssl o none)');
            return false;
        }

        // Validate from email
        if (!filter_var($config['from'], FILTER_VALIDATE_EMAIL)) {
            error_log('SCP SMTP: Email remitente inválido');
            return false;
        }

        return true;
    }

    /**
     * Clear configuration cache
     */
    public function clear_cache() {
        delete_transient($this->config_cache_key);
    }

    /**
     * Get configuration file path
     *
     * @return string Configuration file path
     */
    public function get_config_path() {
        return $this->config_path;
    }

    /**
     * Check if configuration file exists
     *
     * @return bool True if exists, false otherwise
     */
    public function config_exists() {
        return file_exists($this->config_path);
    }
}
