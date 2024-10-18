<?php

namespace Arrigoo\WpCdpBlockControl;

// Add the Arrigoo CDP script to frontend.
class EndUser {
    public static function arrigoo_cdp_custom_javascript() {
        wp_enqueue_script( 
            'arrigoo_cdp', 
            plugin_dir_url( __FILE__ ) . '../build/bundle.js',
            array(), '1.0.0', true );
        ?>
            <style>
                *[data-segments] {
                    display: none;
                }
            </style>
            <script type="text/javascript">
                window.arrigooHost = '<?= getenv('CDP_URL_FRONTEND') ?>'; 
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
                            return;
                        }
                        block.remove();
                    });
                }, false);
            </script>
        <?php
    }
}