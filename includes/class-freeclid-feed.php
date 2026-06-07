<?php

if (!defined('ABSPATH')) {
    exit;
}

class Freeclid_Feed
{
    public static function init(): void
    {
        add_action('init', [self::class, 'add_rewrite_rule']);
        add_filter('query_vars', [self::class, 'add_query_vars']);
        add_action('template_redirect', [self::class, 'maybe_stream_feed']);
        add_action('rest_api_init', [self::class, 'register_rest_route']);
        add_filter('rest_authentication_errors', [self::class, 'allow_freeclid_rest_auth'], 5);
    }

    public static function add_rewrite_rule(): void
    {
        add_rewrite_rule('^freeclid-feed\.csv$', 'index.php?freeclid_feed=1', 'top');
    }

    public static function add_query_vars(array $vars): array
    {
        $vars[] = 'freeclid_feed';

        return $vars;
    }

    public static function maybe_stream_feed(): void
    {
        if (!get_query_var('freeclid_feed')) {
            return;
        }

        self::check_basic_auth();
        self::stream_csv();
        exit;
    }

    public static function register_rest_route(): void
    {
        register_rest_route(
            'freeclid/v1',
            '/feed\.csv',
            [
                'methods' => 'GET',
                'callback' => static function (): void {
                    self::check_basic_auth();
                    self::stream_csv();
                    exit;
                },
                'permission_callback' => '__return_true',
            ]
        );
    }

    public static function allow_freeclid_rest_auth($result)
    {
        if (self::is_rest_feed_request()) {
            return null;
        }

        return $result;
    }

    public static function check_basic_auth(): void
    {
        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $pass = $_SERVER['PHP_AUTH_PW'] ?? null;

        if ($user === null) {
            $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

            if (stripos($header, 'Basic ') === 0) {
                $decoded = base64_decode(substr($header, 6), true);

                if (is_string($decoded)) {
                    [$user, $pass] = array_pad(explode(':', $decoded, 2), 2, '');
                }
            }
        }

        $expected_user = (string) get_option('freeclid_feed_user');
        $expected_pass = (string) get_option('freeclid_feed_pass');

        if (
            $expected_user === ''
            || $expected_pass === ''
            || !hash_equals($expected_user, (string) $user)
            || !hash_equals($expected_pass, (string) $pass)
        ) {
            header('WWW-Authenticate: Basic realm="FreeCLID Feed"');
            status_header(401);
            exit('401 Unauthorized');
        }
    }

    public static function stream_csv(): void
    {
        if (!headers_sent()) {
            status_header(200);
            header('Content-Type: text/csv; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        $conversion_name = sanitize_text_field((string) freeclid_get_option('freeclid_conversion_name'));
        $lookback_days = freeclid_sanitize_days(freeclid_get_option('freeclid_lookback_days'));
        $output = fopen('php://output', 'w');

        if (!$output) {
            exit;
        }

        fputcsv($output, ['Google Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency']);

        foreach (Freeclid_DB::get_feed_leads($lookback_days) as $lead) {
            // TODO multi-column braid: Google supports separate GBRAID/WBRAID columns in newer upload specs.
            fputcsv($output, [
                (string) $lead['click_id'],
                $conversion_name,
                gmdate('Y-m-d H:i:s', strtotime((string) $lead['created_at'])) . '+0000',
                self::format_conversion_value($lead['conv_value']),
                freeclid_sanitize_currency($lead['conv_currency'] ?? freeclid_get_option('freeclid_currency')),
            ]);
        }

        fclose($output);
    }

    private static function format_conversion_value($value): string
    {
        $number = is_numeric($value) ? (float) $value : 0.0;

        if (floor($number) === $number) {
            return (string) (int) $number;
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private static function is_rest_feed_request(): bool
    {
        $route = '';

        if (isset($GLOBALS['wp']->query_vars['rest_route'])) {
            $route = (string) $GLOBALS['wp']->query_vars['rest_route'];
        } elseif (isset($_GET['rest_route'])) {
            $route = (string) wp_unslash($_GET['rest_route']);
        }

        if ($route !== '' && trim($route, '/') === 'freeclid/v1/feed.csv') {
            return true;
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        return is_string($path) && str_ends_with($path, '/wp-json/freeclid/v1/feed.csv');
    }
}
