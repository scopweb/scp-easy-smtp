<?php
/**
 * Email Logger Class
 * Logs email sending attempts and domain corrections
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scp_Email_Logger {

    private $table_emails;
    private $table_corrections;

    public function __construct() {
        global $wpdb;
        $this->table_emails = $wpdb->prefix . 'scp_smtp_emails';
        $this->table_corrections = $wpdb->prefix . 'scp_smtp_corrections';
    }

    /**
     * Create database tables for logging
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table for email logs
        $sql_emails = "CREATE TABLE IF NOT EXISTS {$this->table_emails} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email_to varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'sent',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Table for domain corrections
        $sql_corrections = "CREATE TABLE IF NOT EXISTS {$this->table_corrections} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            original_email varchar(255) NOT NULL,
            corrected_email varchar(255) NOT NULL,
            original_domain varchar(255) NOT NULL,
            corrected_domain varchar(255) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY original_domain (original_domain)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_emails);
        dbDelta($sql_corrections);
    }

    /**
     * Log email sending attempt
     *
     * @param array $args Email arguments
     */
    public function log_email($args) {
        global $wpdb;

        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_emails}'");
        if (!$table_exists) {
            return; // Silently skip if table doesn't exist
        }

        $to = is_array($args['to']) ? implode(', ', $args['to']) : $args['to'];
        $subject = $args['subject'] ?? '';

        $wpdb->insert(
            $this->table_emails,
            [
                'email_to' => sanitize_text_field($to),
                'subject' => sanitize_text_field($subject),
                'status' => 'sent',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Log email domain correction
     *
     * @param string $original_email Original email address
     * @param string $corrected_email Corrected email address
     */
    public function log_correction($original_email, $corrected_email) {
        global $wpdb;

        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_corrections}'");
        if (!$table_exists) {
            return; // Silently skip if table doesn't exist
        }

        $original_parts = explode('@', $original_email);
        $corrected_parts = explode('@', $corrected_email);

        $wpdb->insert(
            $this->table_corrections,
            [
                'original_email' => sanitize_email($original_email),
                'corrected_email' => sanitize_email($corrected_email),
                'original_domain' => isset($original_parts[1]) ? sanitize_text_field($original_parts[1]) : '',
                'corrected_domain' => isset($corrected_parts[1]) ? sanitize_text_field($corrected_parts[1]) : '',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Get recent email logs
     *
     * @param int $limit Number of logs to retrieve
     * @return array Array of email logs
     */
    public function get_recent_emails($limit = 50) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_emails} ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Get recent domain corrections
     *
     * @param int $limit Number of corrections to retrieve
     * @return array Array of corrections
     */
    public function get_recent_corrections($limit = 50) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_corrections} ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Get correction statistics
     *
     * @return array Statistics array
     */
    public function get_correction_stats() {
        global $wpdb;

        $total_corrections = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_corrections}");

        $top_corrections = $wpdb->get_results(
            "SELECT original_domain, corrected_domain, COUNT(*) as count
             FROM {$this->table_corrections}
             GROUP BY original_domain, corrected_domain
             ORDER BY count DESC
             LIMIT 10"
        );

        return [
            'total' => $total_corrections,
            'top_corrections' => $top_corrections
        ];
    }

    /**
     * Clear old logs
     *
     * @param int $days Days to keep logs
     */
    public function clear_old_logs($days = 30) {
        global $wpdb;

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
