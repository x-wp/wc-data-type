<?php
/**
 * WC_Data_Interface interface file.
 *
 * @package WooCommerce Utils
 */

/**
 * Abstract WC Data Class
 *
 * Implemented by classes using the same CRUD(s) pattern.
 */
interface WC_Data_Definition {
    /**
     * Generate cache key from id and group.
     *
     * @since 4.7.0
     *
     * @param int|string $id          Object ID.
     * @param string     $cache_group Group name use to store cache. Whole group cache can be invalidated in one go.
     *
     * @return string Meta cache key.
     */
    public static function generate_meta_cache_key( $id, $cache_group );

    /**
     * Prime caches for raw meta data. This includes meta_id column as well, which is not included by default in WP meta data.
     *
     * @since 4.7.0
     *
     * @param array  $raw_meta_data_collection Array of objects of { object_id => array( meta_row_1, meta_row_2, ... }.
     * @param string $cache_group              Name of cache group.
     */
    public static function prime_raw_meta_data_cache( $raw_meta_data_collection, $cache_group );

    /**
     * Only store the object ID to avoid serializing the data object instance.
     *
     * @return array
     */
    public function __sleep();

    /**
     * Re-run the constructor with the object ID.
     *
     * If the object no longer exists, remove the ID.
     */
    public function __wakeup();

    /**
     * When the object is cloned, make sure meta is duplicated correctly.
     *
     * @since 3.0.2
     */
    public function __clone();

    /**
     * Change data to JSON format.
     *
     * @since  2.6.0
     * @return string Data in JSON format.
     */
    public function __toString();

    /**
     * Get the data store.
     *
     * @since  3.0.0
     * @return WC_Data_Store
     */
    public function get_data_store();

    /**
     * Returns the unique ID for this object.
     *
     * @since  2.6.0
     * @return int
     */
    public function get_id();

    /**
     * Delete an object, set the ID to 0, and return result.
     *
     * @since  2.6.0
     * @param  bool $force_delete Should the date be deleted permanently.
     * @return bool result
     */
    public function delete( $force_delete = false );

    /**
     * Save should create or update based on object existence.
     *
     * @since  2.6.0
     * @return int
     */
    public function save();

    /**
     * Returns all data for this object.
     *
     * @since  2.6.0
     * @return array
     */
    public function get_data();

    /**
     * Returns array of expected data keys for this object.
     *
     * @since   3.0.0
     * @return array
     */
    public function get_data_keys();

    /**
     * Returns all "extra" data keys for an object (for sub objects like product types).
     *
     * @since  3.0.0
     * @return array
     */
    public function get_extra_data_keys();

    /**
     * Get All Meta Data.
     *
     * @since 2.6.0
     * @return array of objects.
     */
    public function get_meta_data();

    /**
     * Get Meta Data by Key.
     *
     * @since  2.6.0
     * @param  string $key Meta Key.
     * @param  bool   $single return first found meta with key, or all with $key.
     * @param  string $context What the value is for. Valid values are view and edit.
     * @return mixed
     */
    public function get_meta( $key = '', $single = true, $context = 'view' );

    /**
     * See if meta data exists, since get_meta always returns a '' or array().
     *
     * @since  3.0.0
     * @param  string $key Meta Key.
     * @return boolean
     */
    public function meta_exists( $key = '' );

    /**
     * Set all meta data from array.
     *
     * @since 2.6.0
     * @param array $data Key/Value pairs.
     */
    public function set_meta_data( $data );

    /**
     * Add meta data.
     *
     * @since 2.6.0
     *
     * @param string       $key Meta key.
     * @param string|array $value Meta value.
     * @param bool         $unique Should this be a unique key?.
     */
    public function add_meta_data( $key, $value, $unique = false );

    /**
     * Update meta data by key or ID, if provided.
     *
     * @since  2.6.0
     *
     * @param  string       $key Meta key.
     * @param  string|array $value Meta value.
     * @param  int          $meta_id Meta ID.
     */
    public function update_meta_data( $key, $value, $meta_id = 0 );

    /**
     * Delete meta data.
     *
     * @since 2.6.0
     * @param string $key Meta key.
     */
    public function delete_meta_data( $key );

    /**
     * Delete meta data.
     *
     * @since 2.6.0
     * @param int $mid Meta ID.
     */
    public function delete_meta_data_by_mid( $mid );

    /**
     * Helper method to compute meta cache key. Different from WP Meta cache key in that meta data cached using this key also contains meta_id column.
     *
     * @since 4.7.0
     *
     * @return string
     */
    public function get_meta_cache_key();

    /**
     * Read Meta Data from the database. Ignore any internal properties.
     * Uses it's own caches because get_metadata does not provide meta_ids.
     *
     * @since 2.6.0
     * @param bool $force_read True to force a new DB read (and update cache).
     */
    public function read_meta_data( $force_read = false );

    /**
     * Helper function to initialize metadata entries from filtered raw meta data.
     *
     * @param array $filtered_meta_data Filtered metadata fetched from DB.
     */
    public function init_meta_data( array $filtered_meta_data = array() );

    /**
     * Update Meta Data in the database.
     *
     * @since 2.6.0
     */
    public function save_meta_data();

    /**
     * Set ID.
     *
     * @since 3.0.0
     * @param int $id ID.
     */
    public function set_id( $id );

    /**
     * Set all props to default values.
     *
     * @since 3.0.0
     */
    public function set_defaults();

    /**
     * Set object read property.
     *
     * @since 3.0.0
     * @param boolean $read Should read?.
     */
    public function set_object_read( $read = true );

    /**
     * Get object read property.
     *
     * @since  3.0.0
     * @return boolean
     */
    public function get_object_read();

    /**
     * Set a collection of props in one go, collect any errors, and return the result.
     * Only sets using public methods.
     *
     * @since  3.0.0
     *
     * @param array  $props Key value pairs to set. Key is the prop and should map to a setter function name.
     * @param string $context In what context to run this.
     *
     * @return bool|WP_Error
     */
    public function set_props( $props, $context = 'set' );

    /**
     * Return data changes only.
     *
     * @since 3.0.0
     * @return array
     */
    public function get_changes();

    /**
     * Merge changes with data and clear.
     *
     * @since 3.0.0
     */
    public function apply_changes();
}
