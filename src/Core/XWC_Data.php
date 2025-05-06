<?php //phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
/**
 * Data class file.
 *
 * @package WooCommerce Utils
 */

use XWC\Data\Model\Prop_Getters;
use XWC\Data\Model\Prop_Setters;

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
 * @method WC_Data_Store<TDs> get_data_store() Get the data store object.
 *
 * @template TDs of XWC_Data_Store_XT
 *
 * @implements WC_Data_Definition<TDs>
 */
abstract class XWC_Data extends WC_Data implements WC_Data_Definition {
    /**
     * Prop getters trait.
     *
     * @use Prop_Getters<static,TDs>
     */
    use Prop_Getters;

    /**
     * Prop setters trait.
     *
     * @use Prop_Setters<static,TDs>
     */
    use Prop_Setters;

    /**
     * Data store object.
     *
     * @var WC_Data_Store<TDs>
     */
    protected $data_store;

    protected bool $has_meta;

    protected bool $core_read = false;

    /**
     * Parses the method name.
     *
     * @param  string $name Method name.
     * @param  array<mixed,mixed> $args Method arguments.
     * @return array{0: string, 1: string, 2: string}}
     */
    final protected function parse_method_name( string $name, array $args ): array {
        \preg_match( '/^([gs]et)_(.+)$/', $name, $m );

        if ( 3 !== \count( $m ) || ( 'set' === ( $m[1] ?? '' ) && ! isset( $args[0] ) ) ) {
            $this->error( 'bmc', \sprintf( 'BMC: %s, %s', static::class, $name ) );
        }

        return $m;
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
        [ $name, $type, $prop ] = $this->parse_method_name( $name, $args );

        return 'get' === $type
            ? $this->get_prop( $prop, $args[0] ?? 'view' )
            : $this->set_prop( $prop, $args[0] );
    }

    /**
     * Default constructor.
     *
     * @param int|array|stdClass|static $data ID to load from the DB (optional) or already queried data.
     */
    public function __construct( int|array|stdClass|XWC_Data $data = 0 ) {
        $this->load_data_store();
        $this->load_object_args();
        $this->load_data( $data );
        $this->do_actions( $data );
    }

    /**
     * Load the data for this object from the database.
     */
    public function load_data_store() {
        // @phpstan-ignore assign.propertyType
        $this->data_store = xwc_data_store( $this->object_type );
    }

    protected function load_object_args(): void {
        $def = $this->get_data_store()->get_object_args();

        foreach ( $def as $var => $data ) {
            $this->$var = $data;
        }
    }

    /**
     * Load the data for this object from the database.
     *
     * @param  int|array|stdClass|static $data Package to init.
     */
    protected function load_data( int|array|stdClass|XWC_Data $data ) {
        $this->data         = \array_merge( $this->core_data, $this->data, $this->extra_data, $this->tax_data );
        $this->default_data = $this->data;

        match ( true ) {
            \is_numeric( $data )         => $this->load_id( $data ),
            $data instanceof XWC_Data    => $this->load_id( $data->get_id() ),
            \is_array( $data )           => $this->load_row( $data ),
            \is_object( $data )          => $this->load_row( (array) $data ),
        };
    }

    protected function do_actions( $data = null ) {
        // Do nothing.
    }

    protected function load_id( int $id ) {
        if ( ! $id ) {
            return $this->set_object_read( true );
        }

        $this->set_id( $id );
        $this->get_data_store()->read( $this );
    }

    protected function load_row( array $data ) {
        $id = $data[ $this->get_data_store()->get_id_field() ] ?? 0;

        if ( ! $id ) {
            return $this->set_object_read( true );
        }

        unset( $data[ $this->get_data_store()->get_id_field() ] );

        $this->set_id( $id );
        $this->set_defaults();
        $this->set_props( $data );
        $this->set_core_data_read( true );
        $this->get_data_store()->read( $this );
    }

    public function set_core_data_read( bool $read = true ) {
        $this->core_read = $read;
    }

    public function get_core_data_read(): bool {
        return (bool) $this->core_read;
    }

    /**
         * Checks if a prop value is unique.
         *
         * @param  string $prop  Property name.
         * @param  mixed  $value Property value.
         */
    protected function check_unique_prop( string $prop, $value ) {
        // Unique propas must be scalar and not empty.
        if ( ! \in_array( $prop, $this->unique_data, true ) || ! \is_scalar( $value ) || '' === $value ) {
            return;
        }

        if ( $this->get_data_store()->is_value_unique( $value, $prop, $this->get_id() ) ) {
            return;
        }

        $this->error(
            'unique_value_exists',
            \sprintf( 'The value %s for %s is already in use.', $value, $prop ),
        );
    }

    protected function check_required_prop( string $prop, mixed $value ) {
        if ( ! isset( $this->required_data[ $prop ] ) ) {
            return;
        }

        if ( null !== $value && $value !== $this->default_data[ $prop ] ) {
            return;
        }

        $this->error(
            'required_value_missing',
            \sprintf( 'The value for %s is required.', $prop ),
        );
    }

    /**
     * Checks if validation method exists and calls it.
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
    protected function check_value_prop( string $prop, mixed $value ) {
        if ( ! method_exists( $this, "validate_{$prop}" ) ) {
            return;
        }

        $this->{"validate_{$prop}"}( $value );
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

    public function save() {
        if ( $this->get_id() ) {
            $this->maybe_set_date( 'updated', 'changes' );
        } else {
            $this->maybe_set_date( 'created' );
        }

        return parent::save();
    }

    protected function maybe_set_date( string $type, ?string $key = null ) {
        $prop = $this->get_prop_by_type( "date_{$type}" );

        if ( ! $prop ) {
            return;
        }

        $val = match ( $key ) {
            'changes' => isset( $this->changes[ $prop ] ),
            'data'    => isset( $this->data[ $prop ] ),
            default   => $this->get_prop( $prop ),
        };

        if ( $val ) {
            return;
        }

        $this->set_prop( $prop, \time() );
    }
}
