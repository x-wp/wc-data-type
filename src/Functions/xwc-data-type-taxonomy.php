<?php
/**
 * Functions for data type taxonomy.
 *
 * @package eXtended WooCommerce
 * @subpackage Functions
 */

use XWC\Core\Data_Type_Manager;

/**
 * Adds an already registered taxonomy to an object type.
 *
 * @since 3.0.0
 *
 * @global array<int, WP_Taxonomy> $wp_taxonomies The registered taxonomies.
 *
 * @param  string $taxonomy Name of taxonomy object.
 * @param  string $data_type Name of the object type.
 * @param  bool   $ikwid    I know what I'm doing. Allows registering a taxonomy in use by another data type.
 * @return bool             True if successful, false if not.
 */
function xwc_register_taxonomy_for_data_type( string $taxonomy, string $data_type, bool $ikwid = false ) {
	return Data_Type_Manager::instance()->register_taxonomy( $taxonomy, $data_type, $ikwid );
}
