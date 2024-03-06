<?php
/**
 * Functions for managing data types.
 *
 * @package Data Types
 */

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
    global $xwc_data_types;

    $field = 'names' === $output ? 'name' : false;

    return wp_filter_object_list( $xwc_data_types, $args, $operator, $field );
}

/**
 * Get a data type object.
 *
 * @param  string $type Data type.
 * @return Data_Type|null
 */
function xwc_get_data_type_object( string $type ): ?Data_Type {
    global $xwc_data_types;

    return $xwc_data_types[ $type ] ?? null;
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
 *
 * @return Data_Type|\WP_Error
 */
function xwc_register_data_type( string $type_or_class ) {
    global $xwc_data_types;

    $xwc_data_types ??= array();

	if ( ! class_exists( $type_or_class ) ) {
		return new \WP_Error();
	}

    try {
        $args = current(
            ( new ReflectionClass( $type_or_class ) )->getAttributes(
                Data_Type::class,
                ReflectionAttribute::IS_INSTANCEOF,
            ),
        )->getArguments();

        $dto = new \XWC\Data_Type( $type_or_class, ...$args );

        $dt = $dto->name;

        $dto->register_data_store();
        $dto->add_supports();

        $xwc_data_types[ $dt ] = $dto;

        $dto->add_hooks();
        $dto->register_taxonomies();
        $dto->init();

        /**
         * Fires after a data type is registered.
         *
         * @param string    $type Data type.
         * @param Data_Type $dto  Arguments used to register the data type.
         *
         * @since 1.0.0
         */
        do_action( 'registered_data_type', $dt, $dto );
    } catch ( \Error $e ) {
		$dto = new \WP_Error(
            'invalid_data_type',
            'Missing Data_Type attribute on ' . $type_or_class,
            $e->getMessage(),
		);
	} catch ( \WC_Data_Exception $e ) {
		$dto = new \WP_Error( $e->getCode(), $e->getMessage(), $e->getErrorData() );
	}

    return $dto;
}

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
	global $wp_taxonomies;

	if ( ! isset( $wp_taxonomies[ $taxonomy ] ) || is_null( xwc_get_data_type_object( $data_type ) ) ) {
		return false;
	}

    if ( count( $wp_taxonomies[ $taxonomy ]->object_type ) > 0 && ! $ikwid ) {
        _doing_it_wrong(
            __FUNCTION__,
            sprintf(
                "\n" . 'Taxonomy %s already registered for post or object types [%s]. ID collisons are possible.' . "\n",
                esc_html( $taxonomy ),
                esc_html( implode( ', ', $wp_taxonomies[ $taxonomy ]->object_type ) ),
            ),
            '6.5.0',
        );
    }

	if ( ! in_array( $data_type, $wp_taxonomies[ $taxonomy ]->object_type, true ) ) {
		$wp_taxonomies[ $taxonomy ]->object_type[] = $data_type;
	}

	// Filter out empties.
	$wp_taxonomies[ $taxonomy ]->object_type = array_filter( $wp_taxonomies[ $taxonomy ]->object_type );

	/**
	 * Fires after a taxonomy is registered for an object type.
	 *
	 * @since 5.1.0
	 *
	 * @param string $taxonomy    Taxonomy name.
	 * @param string $object_type Name of the object type.
	 */
	do_action( 'xwc_registered_taxonomy_for_data_type', $taxonomy, $data_type );

	return true;
}
