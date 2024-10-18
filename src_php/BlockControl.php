<?php

namespace Arrigoo\WpCdpBlockControl;

use Arrigoo\ArrigooCdpSdk\Client as CdpClient;


class BlockControl {
    /**
     * Add the Arrigoo Segments as block controls.
     */
    public static function arrigoo_segment_block_control_enqueue_assets() {
        wp_enqueue_script(
            'arrigoo-segment-block-control-script',
            plugin_dir_url( __FILE__ ) . '../build/index.js',
            array('wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data'),
            filemtime(plugin_dir_path(__FILE__) . 'src/index.js')
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
        if ($cached_segments) {
            return $cached_segments;
        }
        $apiUrl = getenv('CDP_API_URL');
        $apiKey = getenv('CDP_API_KEY');
        $cdpUser = getenv('CDP_USER');
        $client = CdpClient::create($apiUrl, $cdpUser, $apiKey);
        $segments = $client->getSegments();
        update_option('ARRIGOO_CDP', $segments);
        return $segments;
    }
}