<?php
/**
 * KOBA-I Audio: Safety Sentinel
 * * Performs critical environment checks before loading the AI Engine.
 */

if (!defined('ABSPATH')) exit;

class Koba_Safety_Sentinel {

    public static function scan() {
        $issues = [];

        // 1. Check for Composer Vendor Folder
        if (!file_exists(KOBA_IA_PATH . 'vendor/autoload.php')) {
            $issues[] = "<strong>Vendor Missing:</strong> Run 'composer install' in the plugin directory.";
        }

        // 2. Check for Google Credentials
        // We look for the file defined in the constant or a default location
        $key_path = defined('KOBA_GOOGLE_KEY_PATH') ? KOBA_GOOGLE_KEY_PATH : KOBA_IA_PATH . 'includes/google-creds.json';
        
        if (!file_exists($key_path)) {
            $issues[] = "<strong>Google Key Missing:</strong> Upload 'google-creds.json' to the 'includes' folder.";
        }

        // If issues exist, display admin notice and return FALSE
        if (!empty($issues)) {
            add_action('admin_notices', function() use ($issues) {
                echo '<div class="notice notice-error is-dismissible"><h3>KOBA-I Sentinel Alert</h3><ul>';
                foreach ($issues as $issue) {
                    echo "<li>$issue</li>";
                }
                echo '</ul></div>';
            });
            return false;
        }

        return true;
    }
}