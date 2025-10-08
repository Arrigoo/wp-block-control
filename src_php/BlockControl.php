<?php

namespace Arrigoo\WpCdpBlockControl;

use Arrigoo\ArrigooCdpSdk\Client as CdpClient;


class BlockControl {
    const CACHE_EXPIRE = 300;
    /**
     * Add the Arrigoo Segments as block controls.
     */
    public static function arrigoo_segment_block_control_enqueue_assets() {
        wp_enqueue_script(
            'arrigoo-segment-block-control-script',
            plugin_dir_url( __FILE__ ) . '../build/index.js',
            array('wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data'),
            @filemtime(plugin_dir_path(__FILE__) . 'src/index.js')
        );
        $segments = self::arrigoo_cdp_get_segments();
        ?>
            <script>
                window.arrigooCdpSegments = <?= json_encode($segments); ?>;
            </script>
        <?php
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
     * Instanitate the CDP client and request segments.
     */
    public static function arrigoo_cdp_get_segments() {
        $cached_segments = get_option('ARRIGOO_CDP');
        $now = time();
        if ($cached_segments && isset($cached_segments['expire']) && ($now > $cached_segments['expire'])) {
            return $cached_segments['segments'];
        }
        $apiUrl = getenv('CDP_API_URL');
        $apiKey = getenv('CDP_API_KEY');
        $cdpUser = getenv('CDP_USER');
        $apiUrl = $apiUrl ?: CDP_API_URL;
        $apiKey = $apiKey ?: CDP_API_KEY;
        $cdpUser = $cdpUser ?: CDP_USER;
        if (!$apiUrl || !$apiKey || !$cdpUser) {
            return [];
        }
        $client = CdpClient::create($apiUrl, $cdpUser, $apiKey);
        $segments = $client->getSegments();
        $segment_cache = [
            'segments' => $segments,
            'expire' => $now + self::CACHE_EXPIRE,
        ];
        update_option('ARRIGOO_CDP', $segments);
        return $segments;
    }
}
