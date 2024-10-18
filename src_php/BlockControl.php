<?php

namespace Arrigoo\WpCdpBlockControl;

use Arrigoo\ArrigooCdpSdk\Client as CdpClient;

/**
 * Add the Arrigoo Segments as block controls.
 */
function arrigoo_segment_block_control_enqueue_assets() {
    wp_enqueue_script(
        'arrigoo-segment-block-control-script',
        plugin_dir_url( __FILE__ ) . 'build/index.js',
        array('wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data'),
        filemtime(plugin_dir_path(__FILE__) . 'src/arrigoo-cdp-admin.js')
    );
    $segments = arrigoo_cdp_get_segments();
    ?>
        <script>
            window.arrigooCdpSegments = <?= json_encode($segments); ?>;

        </script>
    <?php
}

/**
 * Add the selectedSegments attribute to all blocks.
 */
function arrigoo_cdp_add_segments_attribute_to_blocks( $args, $block_type ) {

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
function arrigoo_cdp_get_segments() {
    $apiUrl = getenv('CDP_API_URL');
    $apiKey = getenv('CDP_API_KEY');
    $cdpUser = getenv('CDP_USER');
    $client = CdpClient::create($apiUrl, $cdpUser, $apiKey);
    $segments = $client->getSegments();
    return $segments;
}