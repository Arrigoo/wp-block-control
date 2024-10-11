<?php
/*
 * Plugin Name: Arrigoo CDP Block control
 * Description: Adds a custom control to all Gutenberg blocks to hide/show the block from different segments.
 * Version: 1.0
 * Author: Arrigoo.io
 */

// Add the Arrigoo CDP script to frontend.
function arrigoo_cdp_custom_javascript() {
    wp_enqueue_script( 'arrigoo_cdp', 'http://localhost:3030/frontend-test/bundle.js', array(), '1.0.0', true );
      ?>
            <style>
                *[data-segments] {
                    display: none;
                }
            </style>
          <script type="text/javascript">
                window.document.addEventListener('ao_loaded', (evt) => {
                    const storage = window.argo;
                    console.log('loaded', storage)
                    const userData = storage.get('ident');
                    subscriberToken = window.argo.getSearchValue('st');
                    if (subscriberToken) {
                        window.argo.send('pageview_nl', subscriberToken, { intval: 1, topics: ['pony', 'fisk'] });
                        window.argo.set('ident', { id_type: 'foreignid1', id_value: subscriberToken });
                    }
                    window.argo.sendInitEvent();
                
                    var segments = argo.get("s");
                    var blocks = document.querySelectorAll('[data-segments]');
                    blocks.forEach(function(block) {
                        var blockSegments = block.getAttribute('data-segments').split(' ');
                        var showBlock = false;
                        blockSegments.forEach(function(segment) {
                            if (segments.indexOf(segment) !== -1) {
                                showBlock = true;
                            }
                        });
                        if (showBlock) {
                            block.style.display = 'block';
                        }
                    });
                }, false);
          </script>
      <?php
  }
  add_action('wp_head', 'arrigoo_cdp_custom_javascript');


function arrigoo_segment_block_control_enqueue_assets() {
    wp_enqueue_script(
        'arrigoo-segment-block-control-script',
        plugin_dir_url( __FILE__ ) . 'build/index.js',
        array('wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data'),
        filemtime(plugin_dir_path(__FILE__) . 'src/arrigoo-cdp-admin.js')
    );
    
    ?>
        <script>

        </script>
    <?php
}

function arrigoo_cdp_add_segments_attribute_to_blocks( $args, $block_type ) {

    $args['attributes'] = $args['attributes'] ?? [];
    
    $args['attributes']['selectedSegments'] = [
        'type'    => 'array',
        'default' => [],
    ];
    

    return $args;
}
add_filter( 'register_block_type_args', 'arrigoo_cdp_add_segments_attribute_to_blocks', 10, 2 );

//arrigoo-cdp
add_action('enqueue_block_editor_assets', 'arrigoo_segment_block_control_enqueue_assets');