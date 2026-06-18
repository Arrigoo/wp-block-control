<?php

namespace Arrigoo\WpCdpBlockControl;

/**
 * Handles frontend script loading and block visibility.
 */
class EndUser {
    const SCRIPT_HANDLE = 'arrigoo-frontend-loader';

    /**
     * Enqueue the frontend loader and its inline configuration.
     *
     * Hooked on `wp_enqueue_scripts` (not `wp_head`) so the enqueue happens
     * before `wp_print_head_scripts` runs at `wp_head` priority 9. Otherwise the
     * in-footer flag would be ignored and the loader would always land in the
     * footer regardless of the "Loader Script Position" setting.
     */
    public static function arrigoo_cdp_enqueue_assets() {
        $consent_provider = AdminSettings::get_config_value('cookie_consent_provider');
        $consent_category = AdminSettings::get_config_value('cookie_consent_category');
        $frontend_script_enabled = AdminSettings::get_config_value('frontend_script_enabled');
        $apiUrl = AdminSettings::get_config_value('api_url');
        $loader_in_footer = AdminSettings::get_config_value('loader_position') === 'footer';

        // Build URLs
        $bundleUrl = plugin_dir_url(__FILE__) . '../build/bundle.js';
        $loaderUrl = plugin_dir_url(__FILE__) . '../build/frontend-loader.js';

        // Enqueue the frontend loader script. Footer lets head-loaded
        // consent/CDP scripts initialize first; header resolves blocks earliest.
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            $loaderUrl,
            [],
            filemtime(plugin_dir_path(__FILE__) . '../build/frontend-loader.js'),
            $loader_in_footer
        );

        // Attach the config to the loader handle so it always prints immediately
        // before the loader, whether that is in the head or the footer.
        $config = sprintf(
            'window.arrigooHost = %s; window.arrigooConfig = %s;',
            wp_json_encode($apiUrl),
            wp_json_encode([
                'consentProvider' => $consent_provider,
                'consentCategory' => $consent_category,
                'frontendScriptEnabled' => (bool) $frontend_script_enabled,
                'bundleUrl' => $bundleUrl,
            ])
        );
        wp_add_inline_script(self::SCRIPT_HANDLE, $config, 'before');
    }

    /**
     * Print the hide-all CSS in the head so segmented blocks are hidden before
     * the body renders, regardless of where the loader script is positioned.
     */
    public static function arrigoo_cdp_print_styles() {
        ?>
        <style>
            *[data-segments] {
                display: none;
            }
        </style>
        <?php
    }
}
