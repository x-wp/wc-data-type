<?php //phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag, SlevomatCodingStandard.Operators.SpreadOperatorSpacing.IncorrectSpacesAfterOperator
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
 *
 * @method Data_Store get_data_store() Get the data store.
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
     * Column prefix
     *
     * @var string|null
     */
    protected ?string $column_prefix;

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
     * Array of term data keys.
     *
     * Term data keys are the keys which are linked to taxonomies.
     *
     * @var array
     */
    protected array $term_data = array();

    /**
     * Props that should be unique.
     *
     * @var array<string, string|array<string>>
     */
    protected array $unique_props = array();

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
			'core_data',
            'column_prefix',
            'term_data',
			'data',
			'prop_types',
			'cache_group',
			'meta_type',
			'object_type',
            'unique_props',
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
        $this->data         = \array_merge(
            $this->core_data,
            $this->data,
            $this->term_data,
            $this->extra_data,
        );
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
     * Restores the column prefix to a prop name.
     *
     * @param  string $prop Property name.
     * @return string       Property name with the prefix.
     */
    protected function restore_column_prefix( string $prop ): string {
        if ( ! $this->column_prefix || \substr( $prop, 0, \strlen( $prop ) ) === $this->column_prefix ) {
            return $prop;
        }

        return \trim( $this->column_prefix, '_' ) . '_' . \trim( $prop, '_' );
    }

    /**
     * Removes the column prefix from a prop name.
     *
     * @param  string $prop Property name.
     * @return string       Property name without the prefix.
     */
    protected function strip_column_prefix( string $prop ): string {
        if ( ! $this->column_prefix || \substr( $prop, 0, \strlen( $prop ) ) !== $this->column_prefix ) {
            return $prop;
        }

        return \substr( $prop, \strlen( $this->column_prefix ) );
    }

    /**
     * Universal prop getter / setter
     *
     * @param  string $name Method name.
     * @param  array  $args Method arguments.
     * @return mixed        Void or prop value.
     *
     * @throws \BadMethodCallException If prop does not exist.
     */
    public function __call( string $name, array $args ): mixed {
        \preg_match( '/^([gs]et)_(.+)$/', $name, $m );

        if ( 3 !== \count( $m ) || ( 'set' === ( $m[1] ?? '' ) && ! isset( $args[0] ) ) ) {
            $this->error( 'bmc', \sprintf( 'BMC: %s, %s, %s', static::class, $name, \implode( ', ', $args ) ) );
        }

        [ $name, $type, $prop ] = $m;

        $prop = $this->strip_column_prefix( $prop );

        return 'get' === $type
            ? $this->get_prop( $prop, $args[0] ?? 'view' )
            : $this->set_prop( $prop, $args[0] );
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

        if ( 'db' === $context ) {
            $data = \array_combine(
                \array_map( $this->restore_column_prefix( ... ), \array_keys( $data ) ),
                \array_values( $data ),
            );
        }

		return $data;
	}

    /**
     * {@inheritDoc}
     */
	protected function set_prop( $prop, $value ) {
		$prop_type = $this->prop_types[ $prop ] ?? '';

		if ( \in_array( $prop, $this->unique_props, true ) && $this->get_object_read() ) {
			$this->check_unique_prop( $prop, $value );
		}

		match ( $prop_type ) {
			'date_created',
            'date_created_gmt',
            'date_modified',
            'date_modified_gmt',
            'date_updated',
            'date_updated_gmt',
            'date'      => $this->set_date_prop( $prop, $value ),
			'bool'      => $this->set_bool_prop( $prop, $value ),
			'bool_int'  => $this->set_bool_prop( $prop, $value ),
			'array'     => $this->set_array_prop( $prop, $value ),
			'array_raw' => $this->set_array_prop( $prop, $value ),
			'binary'    => $this->set_binary_prop( $prop, $value ),
			'json_obj'  => $this->set_json_prop( $prop, $value, false ),
			'json'      => $this->set_json_prop( $prop, $value ),
			'int'       => $this->set_int_prop( $prop, $value ),
			'float'     => $this->set_float_prop( $prop, $value ),
            'string'    => $this->set_wc_data_prop( $prop, $value ),
            'enum'      => $this->set_enum_prop( $prop, $value ),
			default     => $this->set_unknown_prop( $prop, $prop_type, $value ),
		};
	}

    /**
     * Set the value for an enum prop.
     *
     * @param  string                 $prop  Property name.
     * @param  int|string|\BackedEnum $value Property value.
     */
    protected function set_enum_prop( string $prop, int|string|\BackedEnum $value ): void {
        if ( ! \is_a( $value, \BackedEnum::class ) ) {
            $value = $this->default_data[ $prop ]::class::from( $value );
        }

        $this->set_wc_data_prop( $prop, $value );
    }

    /**
     * Bypass for the set_prop method.
     *
     * We sometimes need to access the basic `set_prop` method.
     *
     * @param  string $prop  Prop name.
     * @param  mixed  $value Prop value.
     */
    protected function set_wc_data_prop( $prop, $value ) {
        parent::set_prop( $prop, $value );
    }

    /**
     * Set an unknown prop type
     *
     * @param  string $prop  Property name.
     * @param  string $type  Property type.
     * @param  mixed  $value Property value.
     */
    protected function set_unknown_prop( string $prop, string $type, mixed $value ) {
        if ( \method_exists( $this, "set_{$type}_prop" ) ) {
            $this->{"set_{$type}_prop"}( $prop, $value );
            return;
        }

        $this->set_wc_data_prop( $prop, $value );
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
			return $this->set_wc_data_prop( $prop, $value );
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

		$this->set_wc_data_prop( $prop, \wc_string_to_bool( $value ) );
	}

    /**
     * Set an array prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
	protected function set_array_prop( string $prop, $value ) {
		$this->set_wc_data_prop( $prop, \wc_string_to_array( $value ) );
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

		$this->set_wc_data_prop( $prop, $value );
	}

    /**
     * Set a json prop
     *
     * @param  string       $prop  Property name.
     * @param  string|array $value Property value.
     * @param  bool         $assoc Whether to return an associative array or not.
     */
	protected function set_json_prop( string $prop, string|array $value, bool $assoc = true ) {
        if ( ! \is_array( $value ) ) {
            $value = \json_decode( $value, $assoc );
        }
		$this->set_wc_data_prop( $prop, $value );
	}

    /**
     * Set an int prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
	protected function set_int_prop( string $prop, $value ) {
		$this->set_wc_data_prop( $prop, \intval( $value ) );
	}

    /**
     * Set a float prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
	protected function set_float_prop( string $prop, $value ) {
		$this->set_wc_data_prop( $prop, \floatval( $value ) );
	}

    /**
     * {@inheritDoc}
     */
	protected function get_prop( $prop, $context = 'view' ) {
		$value = parent::get_prop( $prop, $context );
		$type  = $this->prop_types[ $prop ] ?? '';

		return ! \is_null( $value ) && 'db' === $context
		? $this->format_prop_for_db( $prop, $type, $value )
		: $value;
	}

    /**
     * Formats the prop for database storage.
     *
     * @param  string $prop  Property name.
     * @param  string $type  Property type.
     * @param  mixed  $value Property value.
     * @return mixed
     */
	protected function format_prop_for_db( string $prop, string $type, mixed $value ): mixed {
		$is_core_key = \in_array( $prop, $this->get_core_data_keys(), true );

		return match ( $type ) {
			'bool'              => \wc_bool_to_string( $value ),
			'bool_int'          => (int) \wc_string_to_bool( $value ),
			'array'             => $is_core_key ? \implode( ',', $value ) : $value,
			'array_raw'         => \implode( ',', $value ),
			'binary'            => 0 === \preg_match( '/[^\x20-\x7E]/', $value ) ? \hex2bin( $value ) : $value,
			'json'              => \wp_json_encode( $value ),
			'json_obj'          => \wp_json_encode( $value, \JSON_FORCE_OBJECT ),
			'date_gmt',
			'date_created_gmt',
            'date_updated_gmt',
			'date_modified_gmt' => \gmdate( 'Y-m-d H:i:s', $value->getTimestamp() ),
			'date',
            'date_created',
            'date_updated',
			'date_modified'     => \gmdate( 'Y-m-d H:i:s', $value->getOffsetTimestamp() ),
            'enum'              => $value->value,
			'string'            => $value,
			default             => $this->format_unknown_value( $prop, $type, $value ),

		};
	}

    /**
     * Formats the default value for an unknown prop type.
     */
	final protected function format_unknown_value( string $prop, string $type, mixed $value ): mixed {
        if ( \method_exists( $this, "format_{$type}_for_db" ) ) {
            return $this->{"format_{$type}_for_db"}( $prop, $value );
        }

		/**
		 * Filters the default value for an unknown prop type.
		 *
		 * @param mixed  $value The default value.
		 * @param string $prop  The prop name.
		 * @param string $type  The prop type.
		 *
		 * @return mixed
		 *
		 * @since 0.2
		 */
		return \apply_filters( 'xwc_data_unknown_value', $value, $prop, $type );
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
		$prop_type = $gmt ? 'date_created_gmt' : 'date_created';

        return (bool) \array_search( $prop_type, $this->prop_types, true );
	}

    /**
     * Find and get the date created prop.
     *
     * @param  string $context The context for the data.
     */
    public function get_created_prop( string $context = 'view' ) {
        $prop = \array_search( 'date_created', $this->prop_types, true );

        return $this->get_date_prop( $prop, $context )?->getOffsetTimestamp();
    }

    /**
     * Set the date created prop.
     *
     * @param  \WC_DateTime|string|int $value Property value.
     */
    public function set_created_prop( \WC_DateTime|string|int $value ) {
        $prop = \array_search( 'date_created', $this->prop_types, true );

        return $this->set_date_prop( $prop, $value );
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
