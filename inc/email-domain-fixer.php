<?php
/**
 * Email Domain Fixer Class
 * Corrects common typos in email domains
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scp_Email_Domain_Fixer {

    /**
     * Array of common email domain typos and their corrections
     */
    private static $domain_corrections = [
        // Gmail corrections
        'gaiml.com' => 'gmail.com',
        'gamail.com' => 'gmail.com',
        'gemail.com' => 'gmail.com',
        'gemail.es' => 'gmail.es',
        'gemeil.com' => 'gmail.com',
        'gimail.com' => 'gmail.com',
        'gmail.comm' => 'gmail.com',
        'gmail.con' => 'gmail.com',
        'gmeil.com' => 'gmail.com',
        'gmil.com' => 'gmail.com',
        'gmila.com' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'gnail.com' => 'gmail.com',
        'gmial.com' => 'gmail.com',

        // Hotmail corrections
        'hemail.com' => 'hotmail.com',
        'hoail.com' => 'hotmail.com',
        'homail.com' => 'hotmail.com',
        'hormail.com' => 'hotmail.com',
        'hotmaol.com' => 'hotmail.com',
        'hotmal.com' => 'hotmail.com',
        'hotmial.com' => 'hotmail.com',
        'hotmail.cm' => 'hotmail.com',
        'hotmail.co' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',
        'hotmail.coma' => 'hotmail.com',
        'hotmail.om' => 'hotmail.com',
        'jotamail.com' => 'hotmail.com',
        'otmil.com' => 'hotmail.com',

        // Hotmail variants .es
        'hitmail.es' => 'hotmail.es',
        'hotmeil.es' => 'hotmail.es',

        // Outlook corrections
        'outlook.con' => 'outlook.com',
        'outlok.com' => 'outlook.com',

        // Yahoo corrections
        'yaho.com' => 'yahoo.com',
        'yahoo.con' => 'yahoo.com',
        'yahooo.com' => 'yahoo.com',
        'ymail.con' => 'yahoo.com',

        // Test domain
        'test.com' => 'jotajotape.com',
    ];

    /**
     * Fix email domain typos
     *
     * @param string $email Email address to fix
     * @return string Corrected email address
     */
    public static function fix_email_domain($email) {
        if (empty($email)) {
            return '';
        }

        // Split email into local part and domain
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $local = $parts[0];
        $domain = strtolower($parts[1]);

        // Check if domain needs correction
        if (isset(self::$domain_corrections[$domain])) {
            $original_domain = $domain;
            $domain = self::$domain_corrections[$domain];

            // Log the correction
            do_action('scp_smtp_domain_corrected', $original_domain, $domain, $email);
        }

        return $local . '@' . $domain;
    }

    /**
     * Get all domain corrections
     *
     * @return array Array of domain corrections
     */
    public static function get_domain_corrections() {
        return self::$domain_corrections;
    }

    /**
     * Add custom domain correction
     *
     * @param string $wrong_domain Incorrect domain
     * @param string $correct_domain Correct domain
     */
    public static function add_domain_correction($wrong_domain, $correct_domain) {
        self::$domain_corrections[strtolower($wrong_domain)] = strtolower($correct_domain);
    }
}