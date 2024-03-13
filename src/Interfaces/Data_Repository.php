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
     * Checks if a value is unique in the database
     *
     * @param  string $prop_or_column Property or column name.
     * @param  mixed  $value          Value to check.
     * @param  int    $current_id     Current ID.
     * @return bool
     */
    public function is_value_unique( string $prop_or_column, $value, int $current_id ): bool;
}
