<?php //phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
/**
 * Data class file.
 *
 * @package WooCommerce Utils
 */

namespace XWC;

use XWC\Interfaces\Entity;
use XWC\Traits\Data_Type_Meta;

/**
 * * Extended Data Class.
 *
 * Does the entire heavy lifting to suit all of your WC_Data needs.
 *
 * Defines an extra base data array - `core_data` - which is used to get the keys and data which should go into the main data table
 * Data and extra data can be used interchangeably for the metadata table.
 *
 * Props defined in `core_data`, `data` and `extra_data` can use the `get_` and `set_` methods to get and set the data.
 *
 * Defines a `db` context for getters which will return the data in the format it should be stored in the database.
 *
 * Defines a `prop_types` array which is used to determine how to handle the data when getting and setting props.
 * By default supported types are:
 *  - `date`      - a DateTime object
 *  - `bool`      - a boolean value
 *  - `array`     - an array which will be either imploded for a core key, or serialized for a meta key
 *  - `array_raw` - an array which will always be saved as a comma separated string
 *  - `binary`    - A hex string which will be converted to binary when saved to the database
 */
abstract class Data extends \WC_Data implements Entity {
    use Data_Type_Meta;

    /**
     * Data args. AKA Data_Type metadata
     *
     * @var array
     */
    protected static array $args = array();

    /**
     * Array holding instances of data stores.
     *
     * @var array<string, Data_Store>
     */
    protected static $stores = array();

    /**
     * Data store reference
     *
     * @var Data_Store
     */
    protected $data_store;

    /**
     * Data type
     *
     * @var string
     */
    protected string $data_type;

    /**
     * ID field
     *
     * @var string
     */
    protected string $id_field;

    /**
     * Array linking props to their types.
     *
     * @var array<string, string>
     */
    protected array $prop_types = array();

    /**
     * Array of core data keys.
     *
     * Core data keys are the keys that are stored in the main table.
     *
     * @var array<int, string>
     */
    protected array $core_data = array();

    /**
     * Keys that should be unique.
     *
     * @var array<int, string>
     */
    protected array $unique_keys = array();

    /**
     * Data source.
     *
     * @var string
     */
    public string $source;

    /**
     * Get the Data Object ID if ID is passed, otherwise Data is new and empty.
     *
     * @param  int|Data|object|array $data   Package to init.
     * @param  string                $source Data source. Can be 'db', 'cache', or 'row'.
     */
    public function __construct( int|Data|\stdClass|array $data = 0, ?string $source = null ) {
        $this->source = $source ?? 'db';

        $this->init_metadata();
        $this->load_data_store();
        $this->load_data( $data );
    }

    /**
     * Get metadata keys
     *
     * @return array
     */
    protected function get_metadata_keys(): array {
		return array(
            'id_field',
			'prop_types',
			'core_data',
			'data',
			'cache_group',
			'meta_type',
			'object_type',
		);
    }

    /**
     * Load the datastore for this object.
     */
    public function load_data_store() {
        if ( ! isset( static::$stores[ $this->object_type ] ) ) {
			static::$stores[ $this->object_type ] = &Data_Store::load( $this->object_type );
        }

        $this->data_store = &static::$stores[ $this->object_type ];
    }

    /**
     * Load the data for this object from the database.
     *
     * @param  int|Data|object $data Package to init.
     */
    protected function load_data( int|Data|\stdClass|array $data ) {
        $this->data         = \array_merge( $this->core_data, $this->data, $this->extra_data );
		$this->default_data = $this->data;

        $id_field = $this->id_field;

        match ( true ) {
            'db' !== $this->source            => $this->set_props( (array) $data ),
            \is_numeric( $data ) && $data > 0 => $this->set_id( $data ),
            $data instanceof Data             => $this->set_id( $data->get_id() ),
            ( $data?->$id_field ?? 0 ) > 0    => $this->set_id( \absint( $data->$id_field ) ),
            default                           => $this->set_object_read( true ),
        };

        $this->get_id() > 0 && $this->data_store->read( $this );
    }

    /**
     * Universal prop getter / setter
     *
     * @param  string $name      Method name.
     * @param  array  $arguments Method arguments.
     *
     * @throws \BadMethodCallException If prop does not exist.
     */
    public function __call( $name, $arguments ) {
        \preg_match( '/^([gs]et)_(.+)$/', $name, $matches );

        $type = $matches[1] ?? false;
        $prop = $matches[2] ?? false;
        $arg  = 'set' === $type
            ? $arguments[0] ?? $this->error( 'bmc', 'No value provided for prop setter.' )
            : $arguments[0] ?? 'view';

        return match ( true ) {
            'get' === $type && $prop => $this->get_prop( $prop, $arg ),
            'set' === $type && $prop => $this->set_prop( $prop, $arg ),
            default => $this->error( 'bmc', \sprintf( 'BMC: %s, %s, %s', static::class, $type, $prop ) ),
        };
    }

    /**
     * Get the data keys for this object. These are the columns for the main table.
     *
     * @return array<int, string>
     */
    public function get_core_data_keys(): array {
        return \array_keys( $this->core_data );
    }

    /**
     * Get the core data for this object.
     *
     * @param  string $context The context for the data.
     * @return array<string, mixed>
     */
    public function get_core_data( string $context = 'view' ): array {
        $data = array();

        foreach ( $this->get_core_data_keys() as $prop ) {
            $data[ $prop ] = $this->get_prop( $prop, $context );
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    protected function set_prop( $prop, $value ) {
        $prop_type = $this->prop_types[ $prop ] ?? '';

        if ( \in_array( $prop, $this->unique_keys, true ) && $this->get_object_read() ) {
            $this->check_unique_prop( $prop, $value );
        }

        match ( $prop_type ) {
            'date'      => $this->set_date_prop( $prop, $value ),
            'bool'      => $this->set_bool_prop( $prop, $value ),
            'array'     => $this->set_array_prop( $prop, $value ),
            'array_raw' => $this->set_array_prop( $prop, $value ),
            'binary'    => $this->set_binary_prop( $prop, $value ),
            'json_obj'  => $this->set_json_prop( $prop, $value, false ),
            'json'      => $this->set_json_prop( $prop, $value ),
            'int'       => $this->set_int_prop( $prop, $value ),
            'float'     => $this->set_float_prop( $prop, $value ),
            default     => parent::set_prop( $prop, $value ),
        };
    }

    /**
     * Checks if a prop value is unique.
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
    protected function check_unique_prop( string $prop, $value ) {
        // Unique propas must be scalar and not empty.
        if ( ! \is_scalar( $value ) || \is_bool( $value ) || '' === $value ) {
            return;
        }

        if ( $this->data_store->is_value_unique( $prop, $value, $this->get_id() ) ) {
            return;
        }

        $this->error(
            'unique_value_exists',
            \sprintf( 'The value %s for %s is already in use.', $value, $prop ),
        );
    }

    /**
     * Set a date prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
    protected function set_date_prop( $prop, $value ) {
        static $loop = false;

        if ( $loop ) {
            $loop = false;
            return parent::set_prop( $prop, $value );
        }

        $loop = true;

        parent::set_date_prop( $prop, $value );
    }

    /**
     * Set a boolean prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
    protected function set_bool_prop( string $prop, $value ) {
        if ( '' === $value ) {
            return;
        }

        parent::set_prop( $prop, \wc_string_to_bool( $value ) );
    }

    /**
     * Set an array prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
    protected function set_array_prop( string $prop, $value ) {
        parent::set_prop( $prop, \wc_string_to_array( $value ) );
    }

    /**
     * Set a binary prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
    protected function set_binary_prop( string $prop, $value ) {
        if ( \preg_match( '/[^\x20-\x7E]/', $value ) > 0 ) {
            $value = \bin2hex( $value );
        }

        parent::set_prop( $prop, $value );
    }

    /**
     * Set a json prop
     *
     * @param  string $prop  Property name.
     * @param  string $value Property value.
     * @param  bool   $assoc Whether to return an associative array or not.
     */
    protected function set_json_prop( string $prop, string $value, bool $assoc = true ) {
        parent::set_prop( $prop, \json_decode( $value, $assoc ) );
    }

    /**
     * Set an int prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
    protected function set_int_prop( string $prop, $value ) {
        parent::set_prop( $prop, \intval( $value ) );
    }

    /**
     * Set a float prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
    protected function set_float_prop( string $prop, $value ) {
        parent::set_prop( $prop, \floatval( $value ) );
    }

    /**
     * {@inheritDoc}
     */
    protected function get_prop( $prop, $context = 'view' ) {
        $value = parent::get_prop( $prop, $context );
        $type  = $this->prop_types[ $prop ] ?? '';

        if ( \is_null( $value ) || 'db' !== $context ) {
            return $value;
        }

        $is_core_key = \in_array( $prop, $this->get_core_data_keys(), true );
        $date_cb     = \str_ends_with( $prop, '_gmt' ) ? 'getTimestamp' : 'getOffsetTimestamp';

        return match ( $type ) {
            'date'      => \gmdate( 'Y-m-d H:i:s', $value->{"$date_cb"}() ),
            'bool'      => \wc_bool_to_string( $value ),
            'array'     => $is_core_key ? \implode( ',', $value ) : $value,
            'array_raw' => \implode( ',', $value ),
            'binary'    => 0 === \preg_match( '/[^\x20-\x7E]/', $value ) ? \hex2bin( $value ) : $value,
            'json'      => \wp_json_encode( $value ),
            'json_obj'  => \wp_json_encode( $value, \JSON_FORCE_OBJECT ),
            default     => $value,
        };
    }

    /**
     * Get prop types
     *
     * @return array
     */
    public function get_prop_types(): array {
        return $this->prop_types;
    }

    /**
     * Checks if the object has a date_created prop.
     *
     * @param  bool $gmt Whether to check for GMT or site time.
     * @return bool
     */
    public function has_created_prop( $gmt = false ): bool {
        $prop_name = $gmt ? 'date_created_gmt' : 'date_created';
        return \in_array( $prop_name, $this->get_core_data_keys(), true );
    }

    /**
     * Checks if the object has a date_modified prop.
     *
     * @param  bool $gmt Whether to check for GMT or site time.
     * @return bool
     */
    public function has_modified_prop( $gmt = false ): bool {
        $prop_name = $gmt ? 'date_modified_gmt' : 'date_modified';
        return \in_array( $prop_name, $this->get_core_data_keys(), true );
    }

    /**
     * {@inheritDoc}
     */
    protected function is_internal_meta_key( $key ) {
        $parent_check = parent::is_internal_meta_key( $key );

        if ( ! $parent_check && \in_array( $key, $this->get_data_keys(), true ) ) {
            \wc_doing_it_wrong(
                __FUNCTION__,
                \sprintf(
                    // Translators: %s: $key Key to check.
                    \__(
                        'Generic add/update/get meta methods should not be used for internal meta data, including "%s". Use getters and setters.',
                        'woocommerce',
                    ),
                    $key,
                ),
                'WooCommerce Utils - 3.2.0',
            );
            return true;
        }

        return $parent_check;
    }

    /**
     * Save changes to database and return core data keys
     */
    public function __serialize(): array {
        return \array_merge(
            array( 'id' => $this->get_id() ),
            $this->get_core_data( 'db' ),
        );
    }

    /**
     * Load the data from cache, skipping DB read
     *
     * @param  array $data Data to load.
     */
    public function __unserialize( array $data ): void {
		$this->__construct( $data, 'cache' );
    }
}
