<?php

if (!defined('ABSPATH')) {
    exit;
}

class Freeclid_DB
{
    public static function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'freeclid_leads';
    }

    public static function create_table(): void
    {
        global $wpdb;

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            click_id_type VARCHAR(16) NOT NULL,
            click_id VARCHAR(512) NOT NULL,
            platform VARCHAR(16) NOT NULL,
            form_source VARCHAR(191) DEFAULT NULL,
            email VARCHAR(191) DEFAULT NULL,
            phone VARCHAR(64) DEFAULT NULL,
            conv_value DECIMAL(10,2) DEFAULT 0,
            conv_currency VARCHAR(3) DEFAULT 'EUR',
            meta LONGTEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY click_id_type (click_id_type)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function insert(array $data): int
    {
        global $wpdb;

        $click_ids = freeclid_click_ids();
        $type = sanitize_key((string) ($data['click_id_type'] ?? ''));
        $click_id = freeclid_sanitize_click_id_value($data['click_id'] ?? '');

        if (!isset($click_ids[$type]) || $click_id === null) {
            return 0;
        }

        $meta = $data['meta'] ?? null;

        if (is_array($meta)) {
            $meta = wp_json_encode($meta);
        }

        $row = [
            'created_at' => sanitize_text_field((string) ($data['created_at'] ?? gmdate('Y-m-d H:i:s'))),
            'click_id_type' => $type,
            'click_id' => $click_id,
            'platform' => sanitize_key((string) ($data['platform'] ?? $click_ids[$type]['platform'])),
            'form_source' => self::truncate(sanitize_text_field((string) ($data['form_source'] ?? '')), 191),
            'email' => self::truncate(sanitize_email((string) ($data['email'] ?? '')), 191),
            'phone' => self::truncate(sanitize_text_field((string) ($data['phone'] ?? '')), 64),
            'conv_value' => freeclid_sanitize_decimal($data['conv_value'] ?? 0),
            'conv_currency' => freeclid_sanitize_currency($data['conv_currency'] ?? 'EUR'),
            'meta' => is_string($meta) ? $meta : null,
        ];

        $inserted = $wpdb->insert(
            self::table_name(),
            $row,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s']
        );

        if (!$inserted) {
            return 0;
        }

        $row_id = (int) $wpdb->insert_id;
        do_action('freeclid_lead_captured', $row_id, $row);

        return $row_id;
    }

    public static function get_feed_leads(int $lookback_days): array
    {
        global $wpdb;

        $types = freeclid_feed_click_id_types();

        if ($types === []) {
            return [];
        }

        $lookback_days = freeclid_sanitize_days($lookback_days);
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($lookback_days * DAY_IN_SECONDS));
        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $sql = "SELECT id, created_at, click_id_type, click_id, conv_value, conv_currency
            FROM " . self::table_name() . "
            WHERE created_at >= %s
            AND click_id_type IN ({$placeholders})
            ORDER BY created_at ASC, id ASC";

        return $wpdb->get_results($wpdb->prepare($sql, array_merge([$cutoff], $types)), ARRAY_A) ?: [];
    }

    public static function get_recent_leads(int $limit = 10): array
    {
        global $wpdb;

        $limit = min(50, max(1, absint($limit)));
        $sql = "SELECT id, created_at, click_id_type, click_id, platform, form_source, email, phone, conv_value, conv_currency
            FROM " . self::table_name() . "
            ORDER BY created_at DESC, id DESC
            LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A) ?: [];
    }

    public static function uninstall(): void
    {
        global $wpdb;

        if (!get_option('freeclid_delete_data_on_uninstall')) {
            return;
        }

        $wpdb->query('DROP TABLE IF EXISTS ' . self::table_name());

        foreach (array_keys(freeclid_default_options()) as $option) {
            delete_option($option);
        }
    }

    private static function truncate(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length);
        }

        return substr($value, 0, $length);
    }
}
