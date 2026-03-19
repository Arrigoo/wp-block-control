<?php

namespace Arrigoo\WpCdpBlockControl;

use Arrigoo\ArrigooCdpSdk\Client as CdpClient;


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
	 * Add segment data attributes to dynamic blocks during render.
	 */
	public static function arrigoo_add_segment_attributes_to_dynamic_blocks( $block_content, $block ) {
		if ( empty( $block['attrs']['selectedSegments'] ) ) {
			return $block_content;
		}

		$segments  = esc_attr( implode( ' ', $block['attrs']['selectedSegments'] ) );
		$processor = new \WP_HTML_Tag_Processor( $block_content );

		if ( $processor->next_tag() ) {
			$processor->set_attribute( 'data-segments', $segments );
			$processor->add_class( 'arrigoo-segment-block' );
			return $processor->get_updated_html();
		}

		return $block_content;
	}

    /**
     * Instantiate the CDP client and request segments for use in admin.
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

        if (!$apiUrl || !$apiKey || !$cdpUser) {
            return [];
        }

        $client = CdpClient::create($apiUrl, $cdpUser, $apiKey);
        $segments = $client->getSegments();
        $segment_cache = [
            'segments' => $segments,
            'expire' => $now + self::CACHE_EXPIRE,
        ];
        update_option('ARRIGOO_CDP', $segment_cache);
        return $segments;
    }
}
