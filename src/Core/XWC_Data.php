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
 */
abstract class XWC_Data extends WC_Data implements XWC_Data_Definition {
    /**
     * Prop getters trait.
     *
     * @use Prop_Getters<static>
     */
    use Prop_Getters;

    /**
     * Prop setters trait.
     *
     * @use Prop_Setters<static>
     */
    use Prop_Setters;

    /**
     * Data store object.
     *
     * @var XWC_Data_Store_XT<static>
     * @phpstan-ignore property.phpDocType
     */
    protected $data_store;

    protected bool $has_meta;

    protected bool $core_read = false;

    /**
     * Core data changes for this object.
     *
     * @var array<string,mixed>
     */
    protected $changes = array();

    /**
     * Default constructor.
     *
     * @param int|array<string,mixed>|stdClass|static $data ID to load from the DB (optional) or already queried data.
     */
    public function __construct( int|array|stdClass|XWC_Data $data = 0 ) {
        $this
            ->load_data_store()
            ->load_object_args()
            ->load_data( $data )
            ->do_actions( $data );
    }

    /**
     * Get the debug information for this object.
     *
     * @return array<string,mixed>
     */
    public function __debugInfo() {
        return array(
            'changes'   => $this->changes,
            'data'      => $this->data,
            'id'        => $this->get_id(),
            'meta_data' => wp_list_pluck(
                $this->get_meta_data(),
                'value',
                'key',
            ),
            'read'      => $this->get_object_read(),
        );
    }

    /**
     * Universal prop getter / setter
     *
     * @param  string        $name Method name.
     * @param  array<mixed>  $args Method arguments.
     * @return mixed
     *
     * @throws \BadMethodCallException If prop does not exist.
     */
    public function __call( string $name, array $args ): mixed {
        [ $name, $type, $prop ] = $this->parse_method_name( $name, $args );

        return 'get' === $type
            ? $this->get_prop( $prop, $args[0] ?? 'view' )
            : $this->set_prop( $prop, $args[0] );
    }

    public function jsonSerialize(): mixed {
        $data = $this->get_data();

        unset( $data['meta_data'], $data['stats'] );

        return $data;
    }

    /**
     * Load the data for this object from the database.
     *
     * @return static
     */
    public function load_data_store(): static {
        $this->data_store = xwc_ds( $this->object_type );

        return $this;
    }

    /**
     * Get the data store instance.
     *
     * @return XWC_Data_Store_XT<static>
     */
    public function get_data_store() {
        return $this->data_store;
    }

    /**
     * Set the core data read flag.
     *
     * @param  bool   $read
     * @return static
     */
    public function set_core_data_read( bool $read = true ): static {
        $this->core_read = $read;

        return $this;
    }

    public function get_core_data_read(): bool {
        return (bool) $this->core_read;
    }

    /**
     * Take the changes made to the meta props and apply them to the data.
     *
     * @return void
     */
    public function apply_changes() {
        $meta_changes = array_intersect(
            array_keys( $this->changes ),
            array_keys( array_diff_key( $this->data, $this->core_data, $this->extra_data, $this->tax_data ) ),
        );

        foreach ( $meta_changes as $meta_prop ) {
            $this->data[ $meta_prop ] = $this->changes[ $meta_prop ];
            unset( $this->changes[ $meta_prop ] );
        }

        parent::apply_changes();
    }

    public function save() {
        $args = $this->get_id() > 0
            ? array( 'updated', 'changes' )
            : array( 'created', null );

        return $this
            ->maybe_set_object()
            ->maybe_set_date( ...$args )
            ->save_wc_data();
    }

    /**
     * Parses the method name.
     *
     * @param  string $name Method name.
     * @param  array<mixed,mixed> $args Method arguments.
     * @return array{0: string, 1: string, 2: string}}
     */
    final protected function parse_method_name( string $name, array $args ): array {
        \preg_match( '/^([gs]et)_(.+)$/', $name, $m );

        $method = $m[0] ?? '';
        $type   = $m[1] ?? '';
        $prop   = $m[2] ?? '';

        if ( ! $method || ! $type || ! $prop || ( 'set' === $type && count( $args ) < 1 ) ) {
            $this->error( 'bmc', \sprintf( 'BMC: %s, %s', static::class, $name ) );
        }

        return array( $method, $type, $prop );
    }

    /**
     * Load the object args from the data store.
     *
     * @return static
     */
    protected function load_object_args(): static {
        $def = $this->get_data_store()->get_object_args();

        foreach ( $def as $var => $data ) {
            $this->$var = $data;
        }

        return $this;
    }

    /**
     * Load the data for this object from the database.
     *
     * @param  int|array<string,mixed>|stdClass|static $data Package to init.
     * @return static
     */
    protected function load_data( int|array|stdClass|XWC_Data $data ): static {
        $this->data         = \array_merge( $this->core_data, $this->data, $this->extra_data, $this->tax_data );
        $this->default_data = $this->data;

        match ( true ) {
            \is_numeric( $data )         => $this->load_id( $data ),
            $data instanceof XWC_Data    => $this->load_id( $data->get_id() ),
            \is_array( $data )           => $this->load_row( $data ),
            \is_object( $data )          => $this->load_row( (array) $data ),
        };

        return $this;
    }

    /**
     * Do any actions after the object is loaded.
     *
     * @param  mixed $data Data to use for actions.
     */
    protected function do_actions( $data = null ): void {
        // Do nothing.
    }

    /**
     * Load the data for this object from the database by ID.
     *
     * @param  int $id ID to load.
     */
    protected function load_id( int $id ): void {
        if ( ! $id ) {
            $this->set_object_read( true );
            return;
        }

        $this->set_id( $id );
        $this->get_data_store()->read( $this );
    }

    /**
     * Load the data for this object from the database by row data.
     *
     * @param  array<string,mixed> $data Row data to load.
     */
    protected function load_row( array $data ): void {
        $id = $data[ $this->get_data_store()->get_id_field() ] ?? 0;

        if ( ! $id ) {
            $this->set_object_read( true );
            return;
        }

        unset( $data[ $this->get_data_store()->get_id_field() ] );

        $this->set_id( $id );
        $this->set_defaults();
        $this->set_props( $data );
        $this
            ->set_core_data_read( true )
            ->get_data_store()->read( $this );
    }

    /**
     * Checks if a prop value is unique.
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     *
     * @throws \WC_Data_Exception If the value is not unique.
     */
    protected function check_unique_prop( string $prop, $value ): void {
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

    /**
     * Checks if a prop value is required.
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     *
     * @throws \WC_Data_Exception If the value is required but not set.
     */
    protected function check_required_prop( string $prop, mixed $value ): void {
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
     *
     * @throws \WC_Data_Exception If the value is invalid.
     */
    protected function check_value_prop( string $prop, mixed $value ): void {
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

    protected function maybe_set_object(): static {
        if ( ! $this->has_prop_type( 'object' ) ) {
            return $this;
        }

        $changed = array_diff(
            (array) $this->get_prop_by_type( 'object' ),
            array_keys( parent::get_changes() ),
        );

        foreach ( $changed as $prop ) {
            $obj = $this->{"get_{$prop}"}();

            if ( ! ( $obj?->changed() ?? false ) ) {
                continue;
            }

            $this->changes[ $prop ] = $obj;
        }

        return $this;
    }

    protected function has_prop_type( string $type ): bool {
        foreach ( $this->get_prop_types() as $t ) {
            if ( $t === $type || str_starts_with( $t, $type . '|' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Maybe set the created or updated date on save.
     *
     * @param  'created'|'updated'   $type Type of date to set.
     * @param  null|'changes'|'data' $key  Optional. Key to check for changes or data.
     * @return static
     */
    protected function maybe_set_date( string $type, ?string $key = null ): static {
        $prop = $this->get_prop_by_type( "date_{$type}" );

        if ( ! $prop ) {
            return $this;
        }

        $val = match ( $key ) {
            'changes' => isset( $this->changes[ $prop ] ),
            'data'    => isset( $this->data[ $prop ] ),
            default   => $this->get_prop( $prop ),
        };

        if ( ! $val ) {
            $this->set_prop( $prop, \time() );
        }

        return $this;
    }

    /**
     * Call the parent save method.
     *
     * @return int
     */
    protected function save_wc_data(): int {
        return parent::save();
    }
}
