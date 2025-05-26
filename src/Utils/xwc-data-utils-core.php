<?php

use Psr\Container\ContainerInterface;
use XWC\Data\Entity;
use XWC\Data\Entity_Manager;

/**
 * Register an entity data model
 *
 * @template TData of XWC_Data
 *
 * @param class-string<TData> $classname Data type class name.
 * @param ?ContainerInterface $container Optional. The XWP-DI container instance.
 *
 * @return Entity<TData,XWC_Data_Store_XT<TData>,XWC_Object_Factory<TData>,XWC_Meta_Store<TData>>|WP_Error
 */
function xwc_register_entity( string $classname, ?ContainerInterface $container = null ): Entity|\WP_Error {
    return Entity_Manager::register( $classname, $container );
}

/**
 * Get an entity data model.
 *
 * @param  string      $name Entity name.
 * @return Entity<XWC_Data,XWC_Data_Store_XT<XWC_Data>,XWC_Object_Factory<XWC_Data>,XWC_Meta_Store<XWC_Data>>|null
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
 * @param  array<string,mixed>  $args     Optional. Array of arguments for filtering the list of data types.
 * @param  string               $output   Optional. The type of output to return. Accepts 'names' or 'objects'.
 * @param  string               $operator Optional. The logical operation to perform. Accepts 'or' or 'and'.
 * @return ($output is 'names' ? array<int,string> : array<Entity<XWC_Data,XWC_Data_Store_XT<XWC_Data>,XWC_Object_Factory<XWC_Data>,XWC_Meta_Store<XWC_Data>>>)
 */
function xwc_get_entities( array $args = array(), string $output = 'names', string $operator = 'and' ): array {
    $field = 'names' === $output ? 'name' : false;

    return wp_filter_object_list( Entity_Manager::get_entity(), $args, $operator, $field );
}
