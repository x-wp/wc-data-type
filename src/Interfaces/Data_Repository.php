<?php
/**
 * Data repository interface file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC\Interfaces;

/**
 * Interface for the extended data store classes.
 */
interface Data_Repository extends \WC_Object_Data_Store_Interface {
    /**
     * Query for objects matching the given arguments
     *
     * @param  array $query_vars Query arguments.
     * @return array
     */
    public function query( array $query_vars ): array;

    /**
     * Count entities
     *
     * @param  array $query_vars Query arguments.
     * @return int
     */
    public function count( array $query_vars ): int;

    /**
     * Get entity count
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return int                 Count.
     *
     * @deprecated 1.0.0 Use count() instead.
     */
    public function get_entity_count( $args = array(), $clause_join = 'AND' );

    /**
     * Get entities from the database
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return object[]            Array of entities.
     *
     * @deprecated 1.0.0 Use query() instead.
     */
    public function get_entities( $args = array(), $clause_join = 'AND' );

    /**
     * Get a single entity from the database
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return int|object|null     Entity ID or object. Null if not found.
     *
     * @deprecated 1.0.0 Use query() instead.
     */
    public function get_entity( $args, $clause_join = 'AND' );

    /**
     * Checks if a value is unique in the database
     *
     * @param  string $prop_or_column Property or column name.
     * @param  mixed  $value          Value to check.
     * @param  int    $current_id     Current ID.
     * @return bool
     */
    public function is_value_unique( string $prop_or_column, $value, int $current_id ): bool;
}
