<?php

namespace Arrigoo\WpCdpBlockControl;

// Add the Arrigoo CDP script to frontend.
class EndUser {
    public static function arrigoo_cdp_custom_javascript() {
        // Get cookie consent settings
        $consent_provider = AdminSettings::get_config_value('cookie_consent_provider');
        $consent_category = AdminSettings::get_config_value('cookie_consent_category');
        $frontend_script_enabled = AdminSettings::get_config_value('frontend_script_enabled');

        // Get API URL with fallback order: settings -> env vars -> constants
        $apiUrl = AdminSettings::get_config_value('api_url');
        $bundleUrl = plugin_dir_url(__FILE__) . '../build/bundle.js';
        ?>
            <style>
                *[data-segments] {
                    display: none;
                }
            </style>
            <script type="text/javascript">
                window.arrigooHost = '<?= esc_js($apiUrl) ?>';

                (function() {
                    var arrigooScriptLoaded = false;
                    var consentProvider = '<?= esc_js($consent_provider) ?>';
                    var consentCategory = '<?= esc_js($consent_category) ?>';
                    var frontendScriptEnabled = <?= $frontend_script_enabled ? 'true' : 'false' ?>;
                    var bundleUrl = '<?= esc_js($bundleUrl) ?>';

                    function loadArrigooScript() {
                        if (arrigooScriptLoaded) return;
                        arrigooScriptLoaded = true;

                        var script = document.createElement('script');
                        script.src = bundleUrl;
                        script.async = true;
                        document.head.appendChild(script);
                    }

                    function processBlocks() {
                        var user_segments = window.argo ? argo.get("s") : null;
                        var blocks = document.querySelectorAll('[data-segments]');
                        var unknownUser = !user_segments || user_segments.length === 0;
                        console.log('processBlocks', unknownUser, blocks);

                        blocks.forEach(function(block) {
                            var allSegments = block.getAttribute('data-segments').split(' ') || [];
                            var hideSegments = allSegments.filter(s => s.startsWith('!')).map(s => s.substring(1));
                            var blockSegments = allSegments.filter(s => !s.startsWith('!'));
                            var showBlock = false;

                            console.log(unknownUser, blockSegments, hideSegments)
                            blockSegments.forEach(function(segment) {
                                console.log(unknownUser, '-'+segment+'-')
                                if ((unknownUser || !user_segments) && segment === 'unknown') {
                                    console.log(2, unknownUser, '-'+segment+'-')
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
                    }

                    // Set up ao_loaded event listener (only processes blocks if script was loaded)
                    window.document.addEventListener('ao_loaded', function(evt) {
                        if (window.argo) {
                            window.argo.sendInitEvent();
                        }
                        processBlocks();
                    }, false);

                    // Handle consent based on provider
                    // If script won't be loaded, processBlocks is called directly to handle blocks for unknown users
                    <?php echo self::get_consent_handler_js($consent_provider, $frontend_script_enabled); ?>
                })();
            </script>
        <?php
    }

    /**
     * Get the JavaScript consent handler for the specified provider.
     */
    private static function get_consent_handler_js($provider, $frontend_script_enabled = true) {
        switch ($provider) {
            case 'cookieinformation':
                return self::get_cookieinformation_handler_js();
            case 'none':
            default:
                // No consent provider - load script if enabled, otherwise just process blocks
                if ($frontend_script_enabled) {
                    return 'loadArrigooScript();';
                } else {
                    // Wait for DOM to be ready before processing blocks
                    return <<<'JS'
if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', processBlocks);
                    } else {
                        processBlocks();
                    }
JS;
                }
        }
    }

    /**
     * Get CookieInformation consent handler JavaScript.
     */
    private static function get_cookieinformation_handler_js() {
        return <<<'JS'
                    // CookieInformation.com consent handler
                    var cookieCategoryMap = {
                        'necessary': 'cookie_cat_necessary',
                        'functional': 'cookie_cat_functional',
                        'statistic': 'cookie_cat_statistic',
                        'marketing': 'cookie_cat_marketing'
                    };

                    function hasRequiredConsent() {
                        if (typeof CookieInformation !== 'undefined' && typeof CookieInformation.getStatusOfUsedConsentTypes === 'function') {
                            var consentStatus = CookieInformation.getStatusOfUsedConsentTypes();
                            var categoryKey = cookieCategoryMap[consentCategory];
                            return consentStatus && consentStatus[categoryKey] === true;
                        }
                        return false;
                    }

                    // Listen for consent event
                    window.addEventListener('CookieInformationConsentGiven', function(evt) {
                        if (hasRequiredConsent()) {
                            // User gave consent for required category - load the script
                            // ao_loaded will handle processBlocks with actual user segments
                            loadArrigooScript();
                        } else {
                            // User made a choice but didn't consent to required category
                            // Process blocks as unknown user
                            processBlocks();
                        }
                    });

                    // Check if consent was already given (e.g., returning visitor)
                    if (hasRequiredConsent()) {
                        loadArrigooScript();
                    }

JS;
    }
}
