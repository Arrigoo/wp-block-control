<?php

namespace Arrigoo\WpCdpBlockControl;

use Arrigoo\ArrigooCdpSdk\Client as CdpClient;
use GuzzleHttp\Exception\GuzzleException;

class BlockControl {
    const CACHE_EXPIRE = 300;
    /**
     * Add the Arrigoo Segments as block controls.
     */
    public static function arrigoo_segment_block_control_enqueue_assets() {
        $script_path = plugin_dir_path(__FILE__) . '../build/index.js';
        $script_url = plugin_dir_url( __FILE__ ) . '../build/index.js';

        wp_enqueue_script(
            'arrigoo-segment-block-control-script',
            $script_url,
            array('wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-hooks', 'wp-i18n', 'wp-block-editor'),
            file_exists($script_path) ? filemtime($script_path) : '1.0.0',
            false
        );

        $segments = self::arrigoo_cdp_get_segments();

        // Use wp_add_inline_script to add data in a WordPress-aligned way
        wp_add_inline_script(
            'arrigoo-segment-block-control-script',
            sprintf(
                'window.arrigooCdpSegments = %s;',
                wp_json_encode($segments)
            ),
            'before'
        );
    }

    /**
     * Add the selectedSegments attribute to all blocks.
     */
    public static function arrigoo_cdp_add_segments_attribute_to_blocks( $args, $block_type ) {

        $args['attributes'] = $args['attributes'] ?? [];

        $args['attributes']['selectedSegments'] = [
            'type'    => 'array',
            'default' => [],
        ];
        return $args;
    }

    /**
     * Instanitate the CDP client and request segments for use in admin.
     */
    public static function arrigoo_cdp_get_segments() {
        $cached_segments = get_option('ARRIGOO_CDP');
        $now = time();
        if ($cached_segments && isset($cached_segments['expire']) && ($now < $cached_segments['expire'])) {
            return $cached_segments['segments'];
        }

        // Get configuration values with fallback order: settings -> env vars -> constants
        $apiUrl = AdminSettings::get_config_value('api_url');
        $cdpUser = AdminSettings::get_config_value('api_user');
        $apiKey = AdminSettings::get_config_value('api_secret');

        // TEMPORARY DEBUG: log the settings used to fetch segments.
        error_log(sprintf(
            '[Arrigoo CDP DEBUG] Fetching segments with api_url=%s, api_user=%s, api_secret=%s',
            var_export($apiUrl, true),
            var_export($cdpUser, true),
            $apiKey ? '***set (len ' . strlen($apiKey) . ')***' : var_export($apiKey, true)
        ));

        if (!$apiUrl || !$apiKey || !$cdpUser) {
            error_log('[Arrigoo CDP DEBUG] Aborting: one or more credentials are empty.');
            return [];
        }
        try {
            $client = CdpClient::create($apiUrl, $cdpUser, $apiKey);
            $segments = $client->getSegments();

            // TEMPORARY DEBUG: log the complete response from the CDP.
            error_log('[Arrigoo CDP DEBUG] getSegments() response: ' . var_export($segments, true));

            $segment_cache = [
                'segments' => $segments,
                'expire' => $now + self::CACHE_EXPIRE,
            ];
            update_option('ARRIGOO_CDP', $segment_cache);
            return $segments;
        } catch (GuzzleException $e) {
            // TEMPORARY DEBUG: log the exception thrown while fetching segments.
            error_log('[Arrigoo CDP DEBUG] getSegments() threw GuzzleException: ' . $e->getMessage());
            return $cached_segments['segments'];
        }
    }
}
