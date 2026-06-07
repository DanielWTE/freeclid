<?php

if (!defined('ABSPATH')) {
    exit;
}

function freeclid_click_ids(): array
{
    return [
        'gclid'   => ['platform' => 'google', 'feed' => true],
        'gbraid'  => ['platform' => 'google', 'feed' => true],
        'wbraid'  => ['platform' => 'google', 'feed' => true],
        'fbclid'  => ['platform' => 'meta', 'feed' => false],
        'msclkid' => ['platform' => 'microsoft', 'feed' => false],
    ];
}

function freeclid_default_options(): array
{
    return [
        'freeclid_feed_user' => '',
        'freeclid_feed_pass' => '',
        'freeclid_conversion_name' => 'Lead',
        'freeclid_default_value' => '0',
        'freeclid_currency' => 'EUR',
        'freeclid_lookback_days' => 90,
        'freeclid_capture_click_ids' => array_keys(freeclid_click_ids()),
        'freeclid_delete_data_on_uninstall' => 0,
    ];
}

function freeclid_get_option(string $option)
{
    $defaults = freeclid_default_options();

    return get_option($option, $defaults[$option] ?? null);
}

function freeclid_feed_url(): string
{
    return home_url('/freeclid-feed.csv');
}

function freeclid_rest_feed_url(): string
{
    return rest_url('freeclid/v1/feed.csv');
}

function freeclid_enabled_click_ids(): array
{
    $configured = get_option('freeclid_capture_click_ids', array_keys(freeclid_click_ids()));
    $supported = array_keys(freeclid_click_ids());

    if (!is_array($configured)) {
        return $supported;
    }

    return array_values(array_intersect($supported, array_map('sanitize_key', $configured)));
}

function freeclid_feed_click_id_types(): array
{
    return array_keys(array_filter(
        freeclid_click_ids(),
        static fn (array $config): bool => !empty($config['feed'])
    ));
}

function freeclid_sanitize_click_id_value($value): ?string
{
    $value = trim(rawurldecode((string) $value));

    if (!preg_match('/^[A-Za-z0-9_\-.]{1,512}$/', $value)) {
        return null;
    }

    return $value;
}

function freeclid_collect_click_ids_from_cookies(): array
{
    $ids = [];

    foreach (freeclid_enabled_click_ids() as $type) {
        $cookie_name = 'fcl_' . $type;

        if (!isset($_COOKIE[$cookie_name])) {
            continue;
        }

        $value = freeclid_sanitize_click_id_value(wp_unslash($_COOKIE[$cookie_name]));

        if ($value === null) {
            continue;
        }

        $ids[$type] = $value;
    }

    return $ids;
}

function freeclid_pick_click_id_from_cookies(?array $cookies = null): ?array
{
    $cookies = $cookies ?? freeclid_collect_click_ids_from_cookies();

    foreach (array_keys(freeclid_click_ids()) as $type) {
        if (!isset($cookies[$type])) {
            continue;
        }

        return [
            'type' => $type,
            'value' => $cookies[$type],
        ];
    }

    return null;
}

function freeclid_sanitize_currency($value): string
{
    $currency = strtoupper(sanitize_text_field((string) $value));

    return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'EUR';
}

function freeclid_sanitize_decimal($value): string
{
    $number = is_numeric($value) ? (float) $value : 0.0;
    $number = max(0, $number);

    return number_format($number, 2, '.', '');
}

function freeclid_sanitize_days($value): int
{
    $days = absint($value);

    return max(1, $days);
}

function freeclid_sanitize_capture_click_ids($value): array
{
    if (!is_array($value)) {
        return [];
    }

    return array_values(array_intersect(array_keys(freeclid_click_ids()), array_map('sanitize_key', $value)));
}

function freeclid_extract_field($fields, array $needles): string
{
    if (!is_array($fields)) {
        return '';
    }

    $normalized_needles = array_map('freeclid_normalize_field_label', $needles);

    foreach ($fields as $key => $field) {
        $labels = [(string) $key];

        if (is_array($field)) {
            foreach (['id', 'type', 'title', 'name', 'label'] as $label_key) {
                if (isset($field[$label_key])) {
                    $labels[] = (string) $field[$label_key];
                }
            }
        }

        foreach ($labels as $label) {
            $normalized_label = freeclid_normalize_field_label($label);

            foreach ($normalized_needles as $needle) {
                if ($needle !== '' && str_contains($normalized_label, $needle)) {
                    return freeclid_sanitize_field_value($field);
                }
            }
        }
    }

    return '';
}

function freeclid_normalize_field_label(string $label): string
{
    $label = remove_accents($label);
    $label = strtolower($label);

    return preg_replace('/[^a-z0-9]+/', '', $label) ?: '';
}

function freeclid_sanitize_field_value($field): string
{
    $value = $field;

    if (is_array($field)) {
        $value = $field['value'] ?? $field['raw_value'] ?? '';
    }

    if (is_array($value)) {
        $value = implode(', ', array_map('sanitize_text_field', array_map('strval', $value)));
    }

    return sanitize_text_field((string) $value);
}
