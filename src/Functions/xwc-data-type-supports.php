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
    global $xwc_data_types;

    $xwc_data_types->add_support( $data_type, $feature, ...$args );
}

/**
 * Checks if a data type supports a feature.
 *
 * @param  string $data_type Data type.
 * @param  string $feature   Feature being checked.
 * @return bool              True if the feature is supported, false if not.
 */
function xwc_data_type_supports( string $data_type, string $feature ): bool {
    global $xwc_data_types;

    return $xwc_data_types->supports( $data_type, $feature );
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
    global $xwc_data_types;

    return $xwc_data_types->get_supports( $data_type, $feature, $def );
}
