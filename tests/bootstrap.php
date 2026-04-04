<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$_tests_dir = getenv('WP_TESTS_DIR');

if (false === $_tests_dir || '' === $_tests_dir) {
    $_tests_dir = dirname(__DIR__) . '/.cache/wp-tests/lib';
}

$wp_core_dir = getenv('WP_CORE_DIR');

if (false === $wp_core_dir || '' === $wp_core_dir) {
    $wp_core_dir = dirname(__DIR__) . '/.cache/wp-tests/wordpress';
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    static function () use ($wp_core_dir): void {
        $woocommerce = $wp_core_dir . '/wp-content/plugins/woocommerce/woocommerce.php';

        if (file_exists($woocommerce)) {
            require_once $woocommerce;
        }

        require_once dirname(__DIR__) . '/tests/wp-plugin/xwc-data-type-tests/xwc-data-type-tests.php';

        add_action(
            'plugins_loaded',
            static function (): void {
                if (class_exists('WC_Install', false)) {
                    WC()->wpdb_table_fix();
                    WC_Install::create_tables();
                    update_option('woocommerce_version', WC_VERSION);
                    update_option('woocommerce_db_version', WC()->db_version);
                }
            },
            0
        );
    }
);

require_once $_tests_dir . '/includes/bootstrap.php';
