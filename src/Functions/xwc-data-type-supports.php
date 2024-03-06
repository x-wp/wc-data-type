<?php
/**
 * Functions for data type supports.
 *
 * @package eXtended WooCommerce
 * @subpackage Functions
 */

/**
 * Add support for a feature to a data type.
 *
 * @param string $data_type Data type.
 * @param string $feature   Feature being added.
 * @param mixed  ...$args   Optional additional arguments for the feature.
 */
function xwc_add_data_type_support( string $data_type, string $feature, mixed ...$args ) {
    global $_xwc_data_type_features;

    $_xwc_data_type_features ??= array();

    $features = (array) $feature;
	foreach ( $features as $feature ) {
        $args = $args ? $args : true;

        if ( is_array( $args[0] ?? 'no' ) ) {
            $args = $args[0];
        }

		$_xwc_data_type_features[ $data_type ][ $feature ] = $args;
	}
}

/**
 * Checks if a data type supports a feature.
 *
 * @param  string $data_type Data type.
 * @param  string $feature   Feature being checked.
 * @return bool              True if the feature is supported, false if not.
 */
function xwc_data_type_supports( string $data_type, string $feature ): bool {
    global $_xwc_data_type_features;

    $_xwc_data_type_features ??= array();

    return isset( $_xwc_data_type_features[ $data_type ][ $feature ] );
}


/**
 * Get a feature from a data type.
 *
 * @param  string $data_type Data type.
 * @param  string $feature   Feature name.
 * @param  mixed  $def       Default value.
 * @return mixed
 */
function xwc_data_type_get_supports( string $data_type, string $feature, mixed $def = false ): mixed {
    global $_xwc_data_type_features;

    $_xwc_data_type_features ??= array();

    return $_xwc_data_type_features[ $data_type ][ $feature ] ?? $def;
}
