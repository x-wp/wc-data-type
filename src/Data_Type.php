<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
/**
 * Data_Type class file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC;

use XWC\Decorators\Data_Type_Definition;

/**
 * Core class used for interaction with data types.
 *
 * @since 1.0.0
 *
 * @see xwc_register_data_type() For registering data types.
 *
 * @property-read string $classname    Data type class name.
 * @property-read string $name         Data type key.
 * @property-read string $id_field     ID field.
 * @property-read string $table        Table name.
 * @property-read string $table_prefix Table prefix.
 * @property-read string $object_type  Object type.
 * @property-read string $meta_type    Meta type.
 * @property-read array  $query_vars   Query vars.
 *
 * @property-read array $core_props           Core props.
 * @property-read array $meta_props           Meta props.
 * @property-read array $term_props           Term props.
 * @property-read array $prop_types           Prop types.
 * @property-read array $meta_key_to_props    Meta key to props.
 * @property-read array $internal_meta_keys   Internal meta keys.
 * @property-read array $must_exist_meta_keys Must exist meta keys.
 * @property-read array $column_vars          Column vars.
 * @property-read array $search_columns       Search columns.
 * @property-read array $date_columns         Date columns.
 */
final class Data_Type {
    /**
     * Dependencies.
     *
     * @var array<int, Data_Type_Dependency>
     */
    private array $dependencies = array();

    /**
     * Composite props array.
     *
     * These are the props that are generate from the data type definition.
     * Used by `Data` and `Data_Store` classes.
     *
     * @var array
     */
    private array $props;

    /**
     * Is this data type initialized?
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Static array of data stores.
     *
     * @var array|null
     */
    private static ?array $stores = null;

    /**
     * Data store hook.
     *
     * We add the hook filters once, and then populate the array with the hooks.
     *
     * @var array|null
     */
    private static ?array $store_hook = null;

    /**
     * Constructor
     *
     * @param  string               $classname    Data type class name.
     * @param  Data_Type_Definition $def          Data type definition.
     * @param  array                $dependencies Array of dependency classes.
     */
    public function __construct(
        /**
         * Data type key.
         *
         * @var string
         */
        public string $classname,
        /**
         * Data type definition.
         *
         * @var Data_Type_Definition
         */
        private Data_Type_Definition $def,
        array $dependencies = array(),
	) {
        $this->dependencies = $this->load_dependencies( $dependencies, $this->def->name );
        $this->props        = $this->set_props();
    }

    /**
     * Initializes the dependencies for the data type.
     *
     * @param  array<int, class-string<Data_Type_Dependency>> $deps Array of dependency classes.
     * @param  string                                         $name Data type key.
     * @return array<int, Data_Type_Dependency>
     */
    private function load_dependencies( array $deps, string $name ) {
        /**
         * Filters the dependencies for a data type.
         *
         * @param  array<int, class-string<Data_Type_Dependency>> $deps Array of dependency classes.
         * @param  string                                         $type Data type key.
         * @return array<int, class-string<Data_Type_Dependency>>
         *
         * @since 1.1.0
         */
        $deps = \apply_filters( 'xwc_data_type_dependencies', $deps, $name );

        return \array_map(
            static fn( $d ) => new $d( $name ),
            $deps,
        );
    }

    /**
     * Sets the props for the data type.
     *
     * @return array
     */
    private function set_props(): array {
        return array(
            // Data specific arguments.
            'core_data'            => \wp_list_pluck( $this->columns, 'default' ),
            'data'                 => \wp_list_pluck( $this->meta, 'default' ),
            'term_data'            => \wp_list_pluck( $this->taxonomies, 'default' ),
            'prop_types'           => \array_merge(
                \wp_list_pluck( $this->def->columns, 'type' ),
                \wp_list_pluck( $this->def->meta, 'type' ),
                \wp_list_pluck( $this->def->taxonomies, 'type' ),
            ),

            // Data store speficic arguments.
            'meta_key_to_props'    => \array_flip( \wp_list_pluck( $this->def->meta, 'key' ) ),
            'internal_meta_keys'   => \array_values( \wp_list_pluck( $this->def->meta, 'key' ) ),
            'must_exist_meta_keys' => \array_values(
                \wp_list_pluck( \wp_list_filter( $this->meta, array( 'required' => true ) ), 'key' ),
            ),
            'term_props'           => \wp_list_pluck( $this->def->taxonomies, 'tax' ),

            // Query specific arguments.
            'column_vars'          => \array_flip(
                \wp_list_pluck( \wp_list_filter( $this->query_vars, array( 'type' => 'column' ) ), 'var' ),
            ),
            'search_columns'       => \array_keys(
                \wp_list_filter( $this->columns, array( 'search' => true ) ),
            ),
            'date_columns'         => \array_filter(
                $this->columns,
                static fn( $c ) => \str_starts_with( $c['type'], 'date' )
            ),
        );
    }

    /**
     * Get the supported query features for the data type.
     *
     * @return array
     */
    private function get_query_supports(): array {
        $supports = \array_diff_key(
            $s_args ?? array(),
            \array_flip( array( 'search', 'date', 'meta', 'tax', 'parent' ) ),
        );

        $search = \count( $this->search_columns ) > 0;
        $date   = \count( $this->date_columns ) > 0;
        $meta   = \count( $this->data ) > 0;
        $tax    = \count( $this->term_data ) > 0;
        $parent = false;

        return \array_filter( \compact( 'search', 'date', 'meta', 'tax', 'parent' ) );
    }

    /**
     * Sets the feature supports for the data type.
     */
    protected function add_supports() {
        $supports = $this->supports;

        $supports['query'] = $this->get_query_supports();

        foreach ( $supports as $feat => $maybe_args ) {
            $args = \is_array( $maybe_args )
                ? array( $this->name, $feat, $maybe_args )
                : array( $this->name, $feat );

            \xwc_add_data_type_support( ...$args );
        }
    }

    /**
     * Adds hooks for the data type.
     */
    protected function add_hooks() {
        self::$store_hook ??= array(
            'woo' => \add_filter( 'woocommerce_data_stores', array( self::class, 'enable_data_store' ) ),
            'xwc' => \add_filter( 'xwc_data_stores', array( self::class, 'enable_data_store' ) ),
        );
    }

    /**
     * Registers the data store to WC_Data_Store and XWC_Data_Store.
     */
    protected function register_data_store() {
        self::$stores[ $this->object_type ] = $this->data_store;
    }

    /**
     * Enables the data store for the data type.
     *
     * @param  array $stores Array of data stores.
     * @return array
     */
    public static function enable_data_store( array $stores ): array {
        return \array_merge( $stores, self::$stores );
    }

    /**
     * Registers taxonomies for the data type.
     */
    protected function register_taxonomies() {
        if ( ! $this->taxonomies ) {
            return;
        }

        foreach ( $this->taxonomies as $tax ) {
            \xwc_register_taxonomy_for_data_type( $tax['tax'], $this->name, $tax['force'] );
        }
    }

    /**
     * Sets the data type object as initialized.
     *
     * @return void
     */
    protected function initialize() {
        foreach ( $this->dependencies as $dep ) {
            $dep->initialize();
            $dep->add_hooks();
        }

        $this->initialized = true;
    }

    /**
     * Get the data object factory.
     *
     * @return Data_Object_Factory
     */
    public function get_factory(): Data_Object_Factory {
        return $this->def->factory::instance();
    }

    /**
     * Enables access to private properties.
     *
     * @param  string $prop Property.
     * @return mixed        Property value.
     */
    public function __get( string $prop ) {
        if ( isset( $this->props[ $prop ] ) ) {
            return $this->props[ $prop ];
        }

        return $this->def->$prop;
    }

    /**
     * Checks if a property is set.
     *
     * @param  string $name Property name.
     * @return bool
     */
    public function __isset( $name ) {
        return isset( $this->props[ $name ] ) || isset( $this->def->$name );
    }

    /**
     * Enables access to private methods before the data type is initialized.
     *
     * @param  string $name      Method name.
     * @param  array  $arguments Method arguments.
     * @return mixed
     *
     * @throws \BadMethodCallException If the method does not exist.
     */
    public function __call( $name, $arguments ) {
        if ( ! \method_exists( $this, $name ) && ! \str_starts_with( $name, 'get_' ) ) {
            throw new \BadMethodCallException( 'Method ' . \esc_html( $name ) . ' does not exist' );
        }

        if ( $this->initialized ) {
            return;
        }

        return $this->$name( ...$arguments );
    }
}
