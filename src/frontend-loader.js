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
        var processed = false;
        function runOnce() {
            if (processed) return;
            processed = true;
            processBlocks();
        }

        // Bundle is up. Fire the init pageview so the API returns segments.
        // Also process now for returning sessions where segments are already
        // cached in sessionStorage and readable via argo.get('s').
        window.document.addEventListener('ao_loaded', function() {
            if (window.argo) {
                window.argo.sendInitEvent();
                if (window.argo.get('s')) runOnce();
            }
        }, false);

        // API response has populated segments — primary trigger for new sessions.
        window.document.addEventListener('ao_recognized', runOnce, false);

        // Safety net: bundle.js only dispatches ao_recognized when the response
        // carries profile data. First-time visitors with no profile would
        // otherwise stay hidden forever. Also catches the case where the bundle
        // never loads (e.g., network failure, blocked by consent).
        setTimeout(runOnce, 1000);
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
            }
            initScript();
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
        },

        /**
         * Cookiebot consent handler
         */
        cookiebot: function() {
            var categoryMap = {
                'necessary': 'necessary',
                'functional': 'preferences',
                'statistic': 'statistics',
                'marketing': 'marketing'
            };

            function hasRequiredConsent() {
                if (typeof Cookiebot !== 'undefined' && Cookiebot.consent) {
                    var categoryKey = categoryMap[config.consentCategory];
                    return Cookiebot.consent[categoryKey] === true;
                }
                return false;
            }

            // Listen for consent event
            window.addEventListener('CookiebotOnConsentReady', function() {
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
