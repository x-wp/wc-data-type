<?php
/**
 * Entity interface file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC\Interfaces;

use XWC\Data;

/**
 * Interface describing an entity.
 */
interface Entity {
    /**
     * Get the Data Object ID if ID is passed, otherwise Data is new and empty.
     *
     * @param  int|Data|object|array $data   Package to init.
     * @param  string                $source Data source. Can be 'db', 'cache', or 'row'.
     */
    public function __construct( int|Data|\stdClass|array $data = 0, ?string $source = null );

    /**
     * Get prop types
     *
     * @return array
     */
    public function get_prop_types(): array;

    /**
     * Checks if the object has a date_created prop.
     *
     * @param  bool $gmt Whether to check for GMT or site time.
     * @return bool
     */
    public function has_created_prop( $gmt = false ): bool;

    /**
     * Checks if the object has a date_modified prop.
     *
     * @param  bool $gmt Whether to check for GMT or site time.
     * @return bool
     */
    public function has_modified_prop( $gmt = false ): bool;

    /**
     * Load the data store for this object.
     */
    public function load_data_store();

    /**
     * Get the data keys for this object. These are the columns for the main table.
     *
     * @return array<int, string>
     */
    public function get_core_data_keys(): array;

    /**
     * Get the core data for this object.
     *
     * @param  string $context The context for the data.
     * @return array<string, mixed>
     */
    public function get_core_data( string $context = 'view' ): array;
}
