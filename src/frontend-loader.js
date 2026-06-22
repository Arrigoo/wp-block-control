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
                // Drop the attribute so the hide-all `*[data-segments]` rule no
                // longer matches and the block reverts to its CSS-defined display.
                block.removeAttribute('data-segments');
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
            // ao_loaded is only dispatched by a method on window.argo, so argo
            // is guaranteed to be set here — no need to guard.
            var cached = window.argo.get('s');
            if (cached && cached.length) runOnce();
        }, false);

        // API response carried profile/segments. The bundle sets its segment
        // store immediately before dispatching this, so argo.get('s') is
        // populated here — primary trigger for a freshly recognized visitor.
        window.document.addEventListener('ao_recognized', runOnce, false);

        // The init pageview response has returned. bundle.js dispatches this on
        // every event response, immediately AFTER ao_recognized in the same
        // callback. So if the visitor was recognized, runOnce already executed
        // above; if they genuinely have no profile, this is the precise moment
        // to process them as unknown — with no race against network latency.
        window.document.addEventListener('ao_event_sent', runOnce, false);

        // Last-resort net for when the bundle never loads or no response ever
        // arrives (network failure, blocked by consent, loaded externally and
        // absent). Long enough not to beat a slow-but-successful recognition;
        // when it does fire, no segments will arrive and unknown is correct.
        setTimeout(runOnce, 2000);
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
                window.document.addEventListener('ao_loaded', function() {
                    window.argo.sendInitEvent();
                });
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
