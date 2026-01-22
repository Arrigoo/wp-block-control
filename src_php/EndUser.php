<?php

namespace Arrigoo\WpCdpBlockControl;

/**
 * Handles frontend script loading and block visibility.
 */
class EndUser {
    /**
     * Enqueue frontend scripts and output configuration.
     */
    public static function arrigoo_cdp_custom_javascript() {
        // Get settings
        $consent_provider = AdminSettings::get_config_value('cookie_consent_provider');
        $consent_category = AdminSettings::get_config_value('cookie_consent_category');
        $frontend_script_enabled = AdminSettings::get_config_value('frontend_script_enabled');
        $apiUrl = AdminSettings::get_config_value('api_url');

        // Build URLs
        $bundleUrl = plugin_dir_url(__FILE__) . '../build/bundle.js';
        $loaderUrl = plugin_dir_url(__FILE__) . '../build/frontend-loader.js';

        // Enqueue the frontend loader script
        wp_enqueue_script(
            'arrigoo-frontend-loader',
            $loaderUrl,
            [],
            filemtime(plugin_dir_path(__FILE__) . '../build/frontend-loader.js'),
            false // Load in head, not footer
        );

        // Output inline configuration and styles
        ?>
        <style>
            *[data-segments] {
                display: none;
            }
        </style>
        <script type="text/javascript">
            window.arrigooHost = '<?= esc_js($apiUrl) ?>';
            window.arrigooConfig = {
                consentProvider: '<?= esc_js($consent_provider) ?>',
                consentCategory: '<?= esc_js($consent_category) ?>',
                frontendScriptEnabled: <?= $frontend_script_enabled ? 'true' : 'false' ?>,
                bundleUrl: '<?= esc_js($bundleUrl) ?>'
            };
        </script>
        <?php
    }
}
