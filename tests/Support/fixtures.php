<?php

declare(strict_types=1);

function xwc_test_custom_entity_name(): string {
    return 'xwc_test_item';
}

function xwc_test_custom_entity_table_name(): string {
    global $wpdb;

    return $wpdb->prefix . 'xwc_test_items';
}

function xwc_test_install_custom_entity(): void {
    global $wpdb;

    if (! function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $table   = xwc_test_custom_entity_table_name();
    $charset = $wpdb->get_charset_collate();

    dbDelta(
        "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL DEFAULT '',
            slug VARCHAR(200) NOT NULL DEFAULT '',
            score BIGINT NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY slug (slug),
            KEY score (score)
        ) {$charset};"
    );

    if (! xwc_entity_exists(xwc_test_custom_entity_name())) {
        xwc_register_entity(XWC_Test_Item::class);
    }
}

function xwc_test_reset_custom_entity_table(): void {
    global $wpdb;

    xwc_test_install_custom_entity();
    $wpdb->query('TRUNCATE TABLE ' . xwc_test_custom_entity_table_name()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * @param array<int,array{id?: int, name?: string, slug?: string, score?: int}> $rows
 */
function xwc_test_seed_custom_entity_rows(array $rows): void {
    global $wpdb;

    xwc_test_install_custom_entity();

    foreach ($rows as $row) {
        $wpdb->insert(
            xwc_test_custom_entity_table_name(),
            wp_parse_args(
                $row,
                array(
                    'name'  => '',
                    'score' => 0,
                    'slug'  => '',
                )
            )
        );
    }
}
