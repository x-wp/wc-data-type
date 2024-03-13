<?php
/**
 * Functions for managing data types.
 *
 * @package Data Types
 */

use XWC\Core\Data_Type_Manager;
use XWC\Data;
use XWC\Data_Object_Factory;
use XWC\Data_Type;

/**
 * Get a list of registered data types.
 *
 * @param  array  $args     Optional. Array of arguments for filtering the list of data types.
 * @param  string $output   Optional. The type of output to return. Accepts 'names' or 'objects'.
 * @param  string $operator Optional. The logical operation to perform. Accepts 'or' or 'and'.
 * @return array<int, Data_Type|string> An array of data type names or objects.
 */
function xwc_get_data_types( array $args = array(), string $output = 'names', string $operator = 'and' ): array {
    $field = 'names' === $output ? 'name' : false;

    return wp_filter_object_list( Data_Type_Manager::instance()->get_data_type(), $args, $operator, $field );
}

/**
 * Get a data type object factory.
 *
 * @param  string $type Data type.
 * @return Data_Object_Factory
 */
function xwc_get_data_object_factory( string $type ): Data_Object_Factory {
    return xwc_get_data_type_object( $type )->get_factory();
}

/**
 * Get a data type object.
 *
 * @param  string $type Data type.
 * @return Data_Type|null
 */
function xwc_get_data_type_object( string $type ): ?Data_Type {
    return Data_Type_Manager::instance()->get_data_type( $type );
}

/**
 * Determine if a data type is registered.
 *
 * @param  string $type Data type.
 * @return bool
 */
function xwc_data_type_exists( string $type ): bool {
    return (bool) xwc_get_data_type_object( $type );
}
/**
 * Register a data type.
 *
 * @param string $type_or_class Data type or class name.
 * @param string ...$deps       Optional. Data type dependencies.
 *
 * @return Data_Type|\WP_Error
 */
function xwc_register_data_type( string $type_or_class, string ...$deps ): Data_Type|\WP_Error {
	global $xwc_data_types;

	$xwc_data_types ??= Data_Type_Manager::instance();

    return $xwc_data_types->register_class( $type_or_class, ...$deps );
}

/**
 * Get a data object
 *
 * @param  int|WC_Product_Attribute|string|false $id Object ID.
 * @param  string                                $type Object type.
 * @param  bool|int|null                         $def Optional. Default value to return if the object does not exist. Default false.
 * @return Attribute_Taxonomy|false|null
 */
function xwc_get_data( mixed $id, string $type, int|false|null $def = false ): Data|int|false|null {
	if (
        ! did_action( 'woocommerce_init' ) ||
        ! did_action( 'woocommerce_after_register_taxonomy' ) ||
        ! did_action( 'woocommerce_after_register_post_type' )
	) {

		wc_doing_it_wrong(
			__FUNCTION__,
			sprintf(
			/* translators: 1: wc_get_product 2: woocommerce_init 3: woocommerce_after_register_taxonomy 4: woocommerce_after_register_post_type */
				__(
					'%1$s should not be called before the %2$s, %3$s and %4$s actions have finished.',
					'woocommerce',
				),
				'wc_get_attribute_taxonomy',
				'woocommerce_init',
				'woocommerce_after_register_taxonomy',
				'woocommerce_after_register_post_type',
			),
			'3.9',
		);
		return $def;
	}

	// phpcs:ignore Universal.Operators
	return xwc_get_data_object_factory( $type )->{"get_$type"}( $id ) ?: $def;
}

/**
 * Get a data object instance by ID and type.
 *
 * @param  int    $id   Object ID.
 * @param  string $type Object type.
 * @return Data
 */
function xwc_get_data_instance( int $id, string $type ): Data {
	$classname = xwc_get_data_object_factory( $type )->{"get_{$type}_classname"}( $id );

	return new $classname( $id );
}
