<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found
/**
 * Object utilities.
 *
 * @package eXtended WooCommerce
 */

/**
 * Get an entity data store
 *
 * @template T of XWC_Data_Store_XT
 * @param  string          $name Entity name.
 * @param  class-string<T> $cn   Data store class name.
 * @return WC_Data_Store<T>
 */
function xwc_data_store( string $name, string $cn = XWC_Data_Store_XT::class ) {
    // @phpstan-ignore return.type, arguments.count
    return WC_Data_Store::load( $name, $cn );
}

/**
 * Get an object factory
 *
 * @param  string $name Object type.
 * @return XWC_Object_Factory
 */
function xwc_get_object_factory( string $name ): XWC_Object_Factory {
    /**
     * Override var type
     *
     * @var XWC_Object_Factory $f
     */
    $f = xwc_get_entity( $name )->factory;

    return $f;
}

/**
 * Get a data object
 *
 * @template D of false|int|null
 * @template O of XWC_Data
 *
 * @param  int|O|string|false $id Object ID.
 * @param  string                 $name Object type.
 * @param  D                      $def  Optional. Default value to return if the object does not exist. Default false.
 * @return O|D
 */
function xwc_get_object( mixed $id, string $name, int|bool|null $def = false ): XWC_Data|int|bool|null {
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

    return xwc_get_object_factory( $name )->{"get_$name"}( $id ) ?: $def;
}

function xwc_get_object_classname( int $id, string $name ): string {
    return xwc_get_object_factory( $name )->{"get_{$name}_classname"}( $id );
}

/**
 * Get a data object instance by ID and type.
 *
 * @param  int    $id   Object ID.
 * @param  string $type Object type.
 * @return XWC_Data
 */
function xwc_get_object_instance( int $id, string $type ): XWC_Data {
	$classname = xwc_get_object_classname( $id, $type );

	return new $classname( $id );
}

/**
 * Standard way of retrieving data objects based on certain parameters.
 *
 * @param  string $name Entity name.
 * @param  array  $args Query args.
 * @return array<XWC_Data|int>|array{objects: array<XWC_Data|int>, pages: int, total: int}
 */
function xwc_get_objects( string $name, array $args = array() ): array {
    return xwc_data_store( $name )->query( $args );
}
