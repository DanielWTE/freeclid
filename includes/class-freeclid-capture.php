<?php

if (!defined('ABSPATH')) {
    exit;
}

class Freeclid_Capture
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_script']);
        add_action('elementor_pro/forms/new_record', [self::class, 'handle_elementor_form'], 10, 2);
    }

    public static function enqueue_script(): void
    {
        wp_enqueue_script(
            'freeclid-capture',
            FREECLID_URL . 'assets/freeclid-capture.js',
            [],
            FREECLID_VERSION,
            false
        );

        $lookback_days = freeclid_sanitize_days(freeclid_get_option('freeclid_lookback_days'));
        $ttl = $lookback_days * DAY_IN_SECONDS;
        $ids = freeclid_enabled_click_ids();

        wp_add_inline_script(
            'freeclid-capture',
            'window.FREECLID_TTL = ' . (int) $ttl . '; window.FREECLID_IDS = ' . wp_json_encode($ids) . ';',
            'before'
        );
    }

    public static function handle_elementor_form($record, $handler): void
    {
        unset($handler);

        $all_click_ids = freeclid_collect_click_ids_from_cookies();
        $picked = freeclid_pick_click_id_from_cookies($all_click_ids);

        if ($picked === null) {
            return;
        }

        $fields = method_exists($record, 'get') ? $record->get('fields') : [];
        $form_name = method_exists($record, 'get_form_settings') ? (string) $record->get_form_settings('form_name') : '';
        $form_name = $form_name !== '' ? $form_name : 'unknown';
        $click_config = freeclid_click_ids();

        Freeclid_DB::insert([
            'click_id_type' => $picked['type'],
            'click_id' => $picked['value'],
            'platform' => $click_config[$picked['type']]['platform'],
            'form_source' => 'elementor:' . sanitize_text_field($form_name),
            'email' => freeclid_extract_field($fields, ['email', 'e-mail', 'mail']),
            'phone' => freeclid_extract_field($fields, ['phone', 'telefon', 'tel']),
            'conv_value' => freeclid_get_option('freeclid_default_value'),
            'conv_currency' => freeclid_get_option('freeclid_currency'),
            'meta' => [
                'captured_click_ids' => $all_click_ids,
                'primary_click_id_type' => $picked['type'],
            ],
        ]);
    }
}
