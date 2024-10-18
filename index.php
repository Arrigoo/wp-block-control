<?php

/*
 * Plugin Name: Arrigoo CDP Block control
 * Description: Adds a custom control to all Gutenberg blocks to hide/show the block from different segments.
 * Version: 1.0
 * Author: Arrigoo.io
 */

require_once __DIR__ . '/src_php/BlockControl.php';
require_once __DIR__ . '/src_php/EndUser.php';

add_action('wp_head', '\Arrigoo\WpCdpBlockControl\EndUser::arrigoo_cdp_custom_javascript');
add_filter( 'register_block_type_args', '\Arrigoo\WpCdpBlockControl\BlockControl::arrigoo_cdp_add_segments_attribute_to_blocks', 10, 2 );
add_action('enqueue_block_editor_assets', 'Arrigoo\WpCdpBlockControl\BlockControl::arrigoo_segment_block_control_enqueue_assets');