<?php

if (!defined('ABSPATH')) {
    exit;
}

class Freeclid_Settings
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function add_default_options(): void
    {
        foreach (freeclid_default_options() as $option => $default) {
            add_option($option, $default);
        }
    }

    public static function add_menu(): void
    {
        add_options_page(
            __('FreeCLID', 'freeclid'),
            __('FreeCLID', 'freeclid'),
            'manage_options',
            'freeclid',
            [self::class, 'render_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting('freeclid_settings', 'freeclid_feed_user', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('freeclid_settings', 'freeclid_feed_pass', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('freeclid_settings', 'freeclid_conversion_name', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Lead',
        ]);
        register_setting('freeclid_settings', 'freeclid_default_value', [
            'sanitize_callback' => 'freeclid_sanitize_decimal',
            'default' => '0',
        ]);
        register_setting('freeclid_settings', 'freeclid_currency', [
            'sanitize_callback' => 'freeclid_sanitize_currency',
            'default' => 'EUR',
        ]);
        register_setting('freeclid_settings', 'freeclid_lookback_days', [
            'sanitize_callback' => 'freeclid_sanitize_days',
            'default' => 90,
        ]);
        register_setting('freeclid_settings', 'freeclid_capture_click_ids', [
            'sanitize_callback' => 'freeclid_sanitize_capture_click_ids',
            'default' => array_keys(freeclid_click_ids()),
        ]);
        register_setting('freeclid_settings', 'freeclid_delete_data_on_uninstall', [
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);

        add_settings_section(
            'freeclid_feed_section',
            __('Feed Settings', 'freeclid'),
            '__return_false',
            'freeclid'
        );

        self::add_field('freeclid_feed_user', __('Feed username', 'freeclid'), 'render_text_field');
        self::add_field('freeclid_feed_pass', __('Feed password', 'freeclid'), 'render_password_field');
        self::add_field('freeclid_conversion_name', __('Conversion name', 'freeclid'), 'render_conversion_name_field');
        self::add_field('freeclid_default_value', __('Default conversion value', 'freeclid'), 'render_value_field');
        self::add_field('freeclid_currency', __('Currency', 'freeclid'), 'render_currency_field');
        self::add_field('freeclid_lookback_days', __('Lookback window in days', 'freeclid'), 'render_days_field');
        self::add_field('freeclid_capture_click_ids', __('Click IDs to capture', 'freeclid'), 'render_click_ids_field');
        self::add_field('freeclid_feed_url', __('Feed URL', 'freeclid'), 'render_feed_url_field');
        self::add_field('freeclid_delete_data_on_uninstall', __('Delete data on uninstall', 'freeclid'), 'render_delete_data_field');
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('FreeCLID', 'freeclid'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('freeclid_settings');
                do_settings_sections('freeclid');
                submit_button();
                ?>
            </form>
            <?php self::render_recent_leads(); ?>
        </div>
        <style>
            .freeclid-wide-field { width: 100%; max-width: 620px; }
            .freeclid-inline-button { margin-left: 8px; }
            .freeclid-help { max-width: 680px; }
        </style>
        <script>
        (function () {
            function randomSecret(length) {
                var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                var out = '';
                var bytes = new Uint8Array(length);

                if (window.crypto && window.crypto.getRandomValues) {
                    window.crypto.getRandomValues(bytes);
                    for (var i = 0; i < bytes.length; i++) {
                        out += chars[bytes[i] % chars.length];
                    }
                    return out;
                }

                for (var j = 0; j < length; j++) {
                    out += chars[Math.floor(Math.random() * chars.length)];
                }

                return out;
            }

            var generate = document.getElementById('freeclid-generate-pass');
            var password = document.getElementById('freeclid_feed_pass');

            if (generate && password) {
                generate.addEventListener('click', function () {
                    password.value = randomSecret(32);
                });
            }

            var copy = document.getElementById('freeclid-copy-feed-url');
            var feedUrl = document.getElementById('freeclid_feed_url');

            if (copy && feedUrl && navigator.clipboard) {
                copy.addEventListener('click', function () {
                    navigator.clipboard.writeText(feedUrl.value);
                });
            }
        })();
        </script>
        <?php
    }

    public static function render_text_field(array $args): void
    {
        $option = $args['option'];
        ?>
        <input class="regular-text" type="text" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr((string) freeclid_get_option($option)); ?>" autocomplete="off">
        <?php
    }

    public static function render_password_field(array $args): void
    {
        $option = $args['option'];
        ?>
        <input class="regular-text" type="text" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr((string) freeclid_get_option($option)); ?>" autocomplete="off">
        <button type="button" class="button freeclid-inline-button" id="freeclid-generate-pass"><?php echo esc_html__('Generate', 'freeclid'); ?></button>
        <p class="description freeclid-help"><?php echo esc_html__('Stored as a shared secret for the machine feed endpoint. Use a strong random value and HTTPS.', 'freeclid'); ?></p>
        <?php
    }

    public static function render_conversion_name_field(array $args): void
    {
        self::render_text_field($args);
        ?>
        <p class="description freeclid-help"><?php echo esc_html__('Must exactly match the Import conversion action name in Google Ads.', 'freeclid'); ?></p>
        <?php
    }

    public static function render_value_field(array $args): void
    {
        $option = $args['option'];
        ?>
        <input class="small-text" type="number" min="0" step="0.01" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr((string) freeclid_get_option($option)); ?>">
        <?php
    }

    public static function render_currency_field(array $args): void
    {
        $option = $args['option'];
        ?>
        <input class="small-text" type="text" maxlength="3" pattern="[A-Za-z]{3}" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr((string) freeclid_get_option($option)); ?>">
        <?php
    }

    public static function render_days_field(array $args): void
    {
        $option = $args['option'];
        ?>
        <input class="small-text" type="number" min="1" step="1" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr((string) freeclid_get_option($option)); ?>">
        <p class="description freeclid-help"><?php echo esc_html__('Used for both cookie lifetime and feed lookback window.', 'freeclid'); ?></p>
        <?php
    }

    public static function render_click_ids_field(): void
    {
        $enabled = freeclid_enabled_click_ids();

        foreach (freeclid_click_ids() as $id => $config) {
            $label = $config['feed'] ? __('Captured and emitted in the Google CSV', 'freeclid') : __('Captured for future feed integrations only', 'freeclid');
            ?>
            <label>
                <input type="checkbox" name="freeclid_capture_click_ids[]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $enabled, true)); ?>>
                <code><?php echo esc_html($id); ?></code>
                <?php echo esc_html($label); ?>
            </label><br>
            <?php
        }
        ?>
        <p class="description freeclid-help"><?php echo esc_html__('Priority on submit: gclid, gbraid, wbraid, fbclid, msclkid. One lead row is inserted for the highest-priority present ID; additional valid IDs are kept in row meta.', 'freeclid'); ?></p>
        <?php
    }

    public static function render_feed_url_field(): void
    {
        ?>
        <input class="freeclid-wide-field" type="text" id="freeclid_feed_url" readonly value="<?php echo esc_attr(freeclid_feed_url()); ?>">
        <button type="button" class="button freeclid-inline-button" id="freeclid-copy-feed-url"><?php echo esc_html__('Copy', 'freeclid'); ?></button>
        <p class="description freeclid-help">
            <?php echo esc_html__('REST fallback:', 'freeclid'); ?>
            <code><?php echo esc_html(freeclid_rest_feed_url()); ?></code>
        </p>
        <?php
    }

    public static function render_delete_data_field(): void
    {
        ?>
        <label>
            <input type="checkbox" name="freeclid_delete_data_on_uninstall" value="1" <?php checked((int) freeclid_get_option('freeclid_delete_data_on_uninstall'), 1); ?>>
            <?php echo esc_html__('Drop the FreeCLID table and delete settings when uninstalling the plugin.', 'freeclid'); ?>
        </label>
        <?php
    }

    private static function add_field(string $id, string $title, string $callback): void
    {
        add_settings_field(
            $id,
            $title,
            [self::class, $callback],
            'freeclid',
            'freeclid_feed_section',
            ['option' => $id]
        );
    }

    private static function render_recent_leads(): void
    {
        $leads = Freeclid_DB::get_recent_leads(10);
        ?>
        <h2><?php echo esc_html__('Last 10 captured leads', 'freeclid'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Created UTC', 'freeclid'); ?></th>
                    <th><?php echo esc_html__('Type', 'freeclid'); ?></th>
                    <th><?php echo esc_html__('Click ID', 'freeclid'); ?></th>
                    <th><?php echo esc_html__('Platform', 'freeclid'); ?></th>
                    <th><?php echo esc_html__('Form source', 'freeclid'); ?></th>
                    <th><?php echo esc_html__('Email', 'freeclid'); ?></th>
                    <th><?php echo esc_html__('Phone', 'freeclid'); ?></th>
                    <th><?php echo esc_html__('Value', 'freeclid'); ?></th>
                    <th><?php echo esc_html__('Currency', 'freeclid'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($leads === []) : ?>
                    <tr>
                        <td colspan="9"><?php echo esc_html__('No leads captured yet.', 'freeclid'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($leads as $lead) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $lead['created_at']); ?></td>
                            <td><?php echo esc_html((string) $lead['click_id_type']); ?></td>
                            <td><code><?php echo esc_html((string) $lead['click_id']); ?></code></td>
                            <td><?php echo esc_html((string) $lead['platform']); ?></td>
                            <td><?php echo esc_html((string) $lead['form_source']); ?></td>
                            <td><?php echo esc_html((string) $lead['email']); ?></td>
                            <td><?php echo esc_html((string) $lead['phone']); ?></td>
                            <td><?php echo esc_html((string) $lead['conv_value']); ?></td>
                            <td><?php echo esc_html((string) $lead['conv_currency']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
}
