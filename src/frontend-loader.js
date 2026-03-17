/**
 * Frontend loader for Arrigoo CDP Block Control
 *
 * This script handles:
 * - Cookie consent integration (CookieInformation.com and others)
 * - Loading the main CDP bundle script
 * - Processing blocks based on user segments
 *
 * Configuration is provided via window.arrigooConfig object set by PHP.
 */

(function() {
    'use strict';

    var config = window.arrigooConfig || {};
    var arrigooScriptLoaded = false;
    var blocksProcessed = false;

    /**
     * Load the Arrigoo CDP bundle script
     */
    function loadArrigooScript() {
        if (arrigooScriptLoaded || !config.bundleUrl) return;
        arrigooScriptLoaded = true;

        var script = document.createElement('script');
        script.src = config.bundleUrl;
        script.async = true;
        document.head.appendChild(script);
    }

    /**
     * Process blocks based on user segments
     */
    function processBlocks() {
        if (blocksProcessed) return;
        blocksProcessed = true;

        var user_segments = window.argo ? window.argo.get('s') : null;
        var blocks = document.querySelectorAll('[data-segments]');
        var unknownUser = !user_segments || user_segments.length === 0;

        blocks.forEach(function(block) {
            var allSegments = block.getAttribute('data-segments').split(' ') || [];
            var hideSegments = allSegments.filter(function(s) { return s.startsWith('!'); }).map(function(s) { return s.substring(1); });
            var blockSegments = allSegments.filter(function(s) { return !s.startsWith('!'); });
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
                    break;
                }
                if (user_segments && user_segments.indexOf(hideSegments[i]) !== -1) {
                    showBlock = false;
                    break;
                }
            }

            if (showBlock) {
                block.style.display = 'block';
            } else {
                block.remove();
            }
        });
    }

    /**
     * Wait for DOM ready then execute callback
     */
    function onDomReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function initScript() {
        // Set up ao_loaded event listener for when CDP script loads
        window.document.addEventListener('ao_loaded', function() {
            if (window.argo) {
                window.argo.sendInitEvent();
            }
            processBlocks();
        }, false);
    }

    /**
     * Cookie consent handlers by provider
     */
    var consentHandlers = {
        /**
         * No consent provider - load immediately or process blocks
         */
        none: function() {
            if (config.frontendScriptEnabled) {
                loadArrigooScript();
                initScript();
            } else {
                onDomReady(processBlocks);
            }
        },

        /**
         * CookieInformation.com consent handler
         */
        cookieinformation: function() {
            var categoryMap = {
                'necessary': 'cookie_cat_necessary',
                'functional': 'cookie_cat_functional',
                'statistic': 'cookie_cat_statistic',
                'marketing': 'cookie_cat_marketing'
            };

            function hasRequiredConsent() {
                if (typeof CookieInformation !== 'undefined' && typeof CookieInformation.getStatusOfUsedConsentTypes === 'function') {
                    var consentStatus = CookieInformation.getStatusOfUsedConsentTypes();
                    var categoryKey = categoryMap[config.consentCategory];
                    return consentStatus && consentStatus[categoryKey] === true;
                }
                return false;
            }

            // Listen for consent event
            window.addEventListener('CookieInformationConsentGiven', function() {
                if (hasRequiredConsent()) {
                    loadArrigooScript();
                    initScript();
                } else {
                    onDomReady(processBlocks);
                }
            });

            // Check if consent was already given (e.g., returning visitor)
            if (hasRequiredConsent()) {
                loadArrigooScript();
                initScript();
            }
        }
    };

    // Initialize based on consent provider
    var handler = consentHandlers[config.consentProvider] || consentHandlers.none;
    handler();

})();
