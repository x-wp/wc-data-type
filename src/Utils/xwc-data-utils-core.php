<?php

use XWC\Data\Entity;
use XWC\Data\Entity_Manager;

/**
 * Register an entity data model
 *
 * @template TData of XWC_Data
 *
 * @param class-string<TData> $classname Data type class name.
 *
 * @return Entity|WP_Error
 */
function xwc_register_entity( string $classname ): Entity|\WP_Error {
    return Entity_Manager::register( $classname );
}

/**
 * Get an entity data model.
 *
 * @param  string      $name Entity name.
 * @return Entity|null
 */
function xwc_get_entity( string $name ): ?Entity {
    return Entity_Manager::get_entity( $name );
}

/**
 * Check if an entity data model exists
 *
 * @param  string $name Entity name.
 * @return bool
 */
function xwc_entity_exists( string $name ): bool {
    return null !== Entity_Manager::get_entity( $name );
}

/**
 * Get a list of registered data types.
 *
 * @param  array  $args     Optional. Array of arguments for filtering the list of data types.
 * @param  string $output   Optional. The type of output to return. Accepts 'names' or 'objects'.
 * @param  string $operator Optional. The logical operation to perform. Accepts 'or' or 'and'.
 * @return array<int, Entity|string> An array of data type names or objects.
 */
function xwc_get_entities( array $args = array(), string $output = 'names', string $operator = 'and' ): array {
    $field = 'names' === $output ? 'name' : false;

    return wp_filter_object_list( Entity_Manager::get_entity(), $args, $operator, $field );
}
