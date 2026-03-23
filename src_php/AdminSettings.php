<?php

namespace Arrigoo\WpCdpBlockControl;

class AdminSettings {
    const OPTION_GROUP = 'arrigoo_cdp_settings';
    const OPTION_NAME = 'arrigoo_cdp_config';

    /**
     * Register the admin menu.
     */
    public static function register_admin_menu() {
        add_options_page(
            'Arrigoo CDP Settings',
            'Arrigoo CDP',
            'manage_options',
            'arrigoo-cdp-settings',
            [self::class, 'render_settings_page']
        );
    }

    /**
     * Register settings.
     */
    public static function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [self::class, 'sanitize_settings'],
                'default' => [
                    'api_url' => '',
                    'api_user' => '',
                    'api_secret' => '',
                    'cookie_consent_provider' => 'none',
                    'cookie_consent_category' => 'functional',
                    'frontend_script_enabled' => true,
                ]
            ]
        );

        add_settings_section(
            'arrigoo_cdp_main_section',
            'CDP API Configuration',
            [self::class, 'render_section_description'],
            'arrigoo-cdp-settings'
        );

        add_settings_field(
            'api_url',
            'CDP API URL',
            [self::class, 'render_api_url_field'],
            'arrigoo-cdp-settings',
            'arrigoo_cdp_main_section'
        );

        add_settings_field(
            'api_user',
            'CDP API User',
            [self::class, 'render_api_user_field'],
            'arrigoo-cdp-settings',
            'arrigoo_cdp_main_section'
        );

        add_settings_field(
            'api_secret',
            'CDP API Secret',
            [self::class, 'render_api_secret_field'],
            'arrigoo-cdp-settings',
            'arrigoo_cdp_main_section'
        );

        add_settings_field(
            'cookie_consent_provider',
            'Cookie Consent Provider',
            [self::class, 'render_cookie_consent_provider_field'],
            'arrigoo-cdp-settings',
            'arrigoo_cdp_main_section'
        );

        add_settings_field(
            'cookie_consent_category',
            'Cookie Consent Category',
            [self::class, 'render_cookie_consent_category_field'],
            'arrigoo-cdp-settings',
            'arrigoo_cdp_main_section'
        );
    }

    /**
     * Sanitize settings before saving.
     */
    public static function sanitize_settings($input) {
        $sanitized = [];
        $sanitized['api_url'] = isset($input['api_url']) ? esc_url_raw(trim($input['api_url'])) : '';
        $sanitized['api_user'] = isset($input['api_user']) ? sanitize_text_field($input['api_user']) : '';
        $sanitized['api_secret'] = isset($input['api_secret']) ? sanitize_text_field($input['api_secret']) : '';

        // Cookie consent settings
        $valid_providers = array_keys(self::get_cookie_consent_providers());
        $sanitized['cookie_consent_provider'] = isset($input['cookie_consent_provider']) && in_array($input['cookie_consent_provider'], $valid_providers, true)
            ? $input['cookie_consent_provider']
            : 'none';

        $valid_categories = ['necessary', 'functional', 'statistic', 'marketing'];
        $sanitized['cookie_consent_category'] = isset($input['cookie_consent_category']) && in_array($input['cookie_consent_category'], $valid_categories, true)
            ? $input['cookie_consent_category']
            : 'functional';

        $sanitized['frontend_script_enabled'] = isset($input['frontend_script_enabled']) ? (bool) $input['frontend_script_enabled'] : false;

        // Clear the segments cache when settings are updated
        delete_option('ARRIGOO_CDP');

        return $sanitized;
    }

    /**
     * Get a specific configuration value with fallback to env vars and constants.
     */
    public static function get_config_value($key) {
        $defaults = [
            'api_url' => '',
            'api_user' => '',
            'api_secret' => '',
            'cookie_consent_provider' => 'none',
            'cookie_consent_category' => 'functional',
            'frontend_script_enabled' => true,
        ];

        $options = get_option(self::OPTION_NAME, $defaults);

        // Map keys to their env var and constant names (only for API settings)
        $mapping = [
            'api_url' => ['env' => 'CDP_API_URL', 'constant' => 'CDP_API_URL'],
            'api_user' => ['env' => 'CDP_USER', 'constant' => 'CDP_USER'],
            'api_secret' => ['env' => 'CDP_API_KEY', 'constant' => 'CDP_API_KEY'],
        ];

        // Handle boolean settings specially (empty() returns true for false)
        if ($key === 'frontend_script_enabled') {
            // array_key_exists handles the case where the value is explicitly false
            return array_key_exists($key, $options) ? (bool) $options[$key] : $defaults[$key];
        }

        // First, check if value exists in settings and is not empty
        if (isset($options[$key]) && !empty($options[$key])) {
            return $options[$key];
        }

        // Second, check environment variable
        if (isset($mapping[$key])) {
            $env_value = getenv($mapping[$key]['env']);
            if ($env_value !== false && !empty($env_value)) {
                return $env_value;
            }

            // Third, check constant
            $constant_name = $mapping[$key]['constant'];
            if (defined($constant_name)) {
                return constant($constant_name);
            }
        }

        return $defaults[$key] ?? '';
    }

    /**
     * Render the settings page.
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'arrigoo_cdp_messages',
                'arrigoo_cdp_message',
                'Settings Saved',
                'updated'
            );
        }

        settings_errors('arrigoo_cdp_messages');

        // Fetch segments from CDP
        $segments = BlockControl::arrigoo_cdp_get_segments();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections('arrigoo-cdp-settings');
                submit_button('Save Settings');
                ?>
            </form>

            <?php if (!empty($segments)): ?>
                <div style="margin-top: 40px;">
                    <h2>Available Segments</h2>
                    <p>The following segments are currently available from your CDP:</p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 20%;">System Title</th>
                                <th scope="col" style="width: 25%;">Title</th>
                                <th scope="col" style="width: 55%;">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($segments as $segment): ?>
                                <tr>
                                    <td><code><?php echo esc_html($segment['sys_title'] ?? ''); ?></code></td>
                                    <td><strong><?php echo esc_html($segment['title'] ?? ''); ?></strong></td>
                                    <td><?php echo esc_html($segment['description'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="margin-top: 40px;">
                    <h2>Available Segments</h2>
                    <div class="notice notice-warning inline">
                        <p><strong>No segments found.</strong> Please ensure your CDP API credentials are correct and that segments are configured in your CDP.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render section description.
     */
    public static function render_section_description() {
        echo '<p>Configure your Arrigoo CDP API credentials. These settings will be used to fetch segments for block control.</p>';
    }

    /**
     * Render API URL field.
     */
    public static function render_api_url_field() {
        $options = get_option(self::OPTION_NAME, ['api_url' => '']);
        $value = isset($options['api_url']) ? $options['api_url'] : '';
        ?>
        <input type="text"
               id="api_url"
               name="<?php echo esc_attr(self::OPTION_NAME); ?>[api_url]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="https://api.arrigoo.io">
        <p class="description">The base URL for your Arrigoo CDP API.</p>
        <?php
    }

    /**
     * Render API User field.
     */
    public static function render_api_user_field() {
        $options = get_option(self::OPTION_NAME, ['api_user' => '']);
        $value = isset($options['api_user']) ? $options['api_user'] : '';
        ?>
        <input type="text"
               id="api_user"
               name="<?php echo esc_attr(self::OPTION_NAME); ?>[api_user]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <p class="description">Your CDP API user identifier.</p>
        <?php
    }

    /**
     * Render API Secret field.
     */
    public static function render_api_secret_field() {
        $options = get_option(self::OPTION_NAME, ['api_secret' => '']);
        $value = isset($options['api_secret']) ? $options['api_secret'] : '';
        ?>
        <input type="password"
               id="api_secret"
               name="<?php echo esc_attr(self::OPTION_NAME); ?>[api_secret]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <p class="description">Your CDP API secret key.</p>
        <?php
    }

    /**
     * Get available cookie consent providers.
     */
    public static function get_cookie_consent_providers() {
        return [
            'none' => [
                'label' => 'None (always load script)',
                'description' => 'The frontend script will always be loaded without waiting for cookie consent.',
            ],
            'cookieinformation' => [
                'label' => 'CookieInformation.com',
                'description' => 'Wait for consent via CookieInformation cookie banner.',
            ],
            'cookiebot' => [
                'label' => 'Cookie Bot',
                'description' => 'Wait for consent via Cookie Bot banner.',
            ],
        ];
    }

    /**
     * Get available cookie consent categories.
     */
    public static function get_cookie_consent_categories() {
        return [
            'necessary' => 'Necessary',
            'functional' => 'Functional',
            'statistic' => 'Statistic',
            'marketing' => 'Marketing',
        ];
    }

    /**
     * Render Cookie Consent Provider field.
     */
    public static function render_cookie_consent_provider_field() {
        $options = get_option(self::OPTION_NAME, ['cookie_consent_provider' => 'none']);
        $current = isset($options['cookie_consent_provider']) ? $options['cookie_consent_provider'] : 'none';
        $providers = self::get_cookie_consent_providers();
        ?>
        <select id="cookie_consent_provider"
                name="<?php echo esc_attr(self::OPTION_NAME); ?>[cookie_consent_provider]"
                class="regular-text">
            <?php foreach ($providers as $value => $provider): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>>
                    <?php echo esc_html($provider['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Select your cookie consent provider. The frontend script will only load after the user has given consent for the selected category.</p>
        <?php
    }

    /**
     * Render Cookie Consent Category field.
     */
    public static function render_cookie_consent_category_field() {
        $options = get_option(self::OPTION_NAME, [
            'cookie_consent_provider' => 'none',
            'cookie_consent_category' => 'functional',
            'frontend_script_enabled' => true,
        ]);
        $current_provider = isset($options['cookie_consent_provider']) ? $options['cookie_consent_provider'] : 'none';
        $current_category = isset($options['cookie_consent_category']) ? $options['cookie_consent_category'] : 'functional';
        $frontend_enabled = isset($options['frontend_script_enabled']) ? (bool) $options['frontend_script_enabled'] : true;
        $categories = self::get_cookie_consent_categories();
        ?>
        <fieldset id="cookie_consent_category_fieldset" <?php echo $current_provider === 'none' ? 'style="display:none;"' : ''; ?>>
            <?php foreach ($categories as $value => $label): ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="radio"
                           name="<?php echo esc_attr(self::OPTION_NAME); ?>[cookie_consent_category]"
                           value="<?php echo esc_attr($value); ?>"
                           <?php checked($current_category, $value); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
            <p class="description">Select which consent category is required before loading the frontend script.</p>
        </fieldset>
        <div id="frontend_script_enabled_container" <?php echo $current_provider !== 'none' ? 'style="display:none;"' : ''; ?>>
            <label>
                <input type="checkbox"
                       id="frontend_script_enabled"
                       name="<?php echo esc_attr(self::OPTION_NAME); ?>[frontend_script_enabled]"
                       value="1"
                       <?php checked($frontend_enabled, true); ?>>
                Enable frontend script
            </label>
            <p class="description">When enabled, the plugin will automatically load the segment visibility script on the frontend. Disable this if you want to handle the script loading manually (e.g., via a tag manager).</p>
        </div>
        <script type="text/javascript">
            (function() {
                var providerSelect = document.getElementById('cookie_consent_provider');
                var categoryFieldset = document.getElementById('cookie_consent_category_fieldset');
                var frontendScriptContainer = document.getElementById('frontend_script_enabled_container');

                if (providerSelect && categoryFieldset && frontendScriptContainer) {
                    providerSelect.addEventListener('change', function() {
                        if (this.value === 'none') {
                            categoryFieldset.style.display = 'none';
                            frontendScriptContainer.style.display = 'block';
                        } else {
                            categoryFieldset.style.display = 'block';
                            frontendScriptContainer.style.display = 'none';
                        }
                    });
                }
            })();
        </script>
        <?php
    }
}
