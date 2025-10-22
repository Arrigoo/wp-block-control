<?php

namespace Arrigoo\WpCdpBlockControl;

// Add the Arrigoo CDP script to frontend.
class EndUser {
    public static function arrigoo_cdp_custom_javascript() {
        wp_enqueue_script(
            'arrigoo_cdp',
            plugin_dir_url( __FILE__ ) . '../build/bundle.js',
          //  array(), '1.0.0', true
          );
        ?>
            <style>
                *[data-segments] {
                    display: none;
                }
            </style>
            <script type="text/javascript">
                window.arrigooHost = '<?= getenv('CDP_API_URL') ?>';
                window.document.addEventListener('ao_loaded', (evt) => {
                    const storage = window.argo;
                    const userData = storage.get('ident');
                    window.argo.sendInitEvent();
                    var user_segments = argo.get("s");
                    var blocks = document.querySelectorAll('[data-segments]');
                    var unknownUser = !user_segments || user_segments.length === 0;
                    blocks.forEach(function(block) {
                        var allSegments = block.getAttribute('data-segments').split(' ') || [];
                        var hideSegments = allSegments.filter(s => s.startsWith('!')).map(s => s.substring(1));
                        var blockSegments = allSegments.filter(s => !s.startsWith('!'));
                        var showBlock = false;
                        blockSegments.forEach(function(segment) {
                            if ((unknownUser || !user_segments) && segment === 'unknown') {
                                showBlock = true;
                                return;
                            }
                            if (user_segments && user_segments.indexOf(segment) !== -1) {
                                showBlock = true;
                            }
                        });
                        showBlock = showBlock || blockSegments.length === 0;
                        for (var i = 0; i < hideSegments.length; i++) {
                            if ((unknownUser || !user_segments) && hideSegments[i] === 'unknown') {
                                showBlock = false;
                                return;
                            }
                            if (user_segments && user_segments.indexOf(hideSegments[i]) !== -1) {
                                showBlock = false;
                                break;
                            }

                        }
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
