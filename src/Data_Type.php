<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
/**
 * Data_Type class file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC;

/**
 * Core class used for interaction with data types.
 *
 * @since 1.0.0
 *
 * @see xwc_register_data_type() For registering data types.
 *
 * @property-read string $name Data type key.
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
final class Data_Type {
    /**
     * Object type.
     *
     * @var string
     */
    private string $object_type;

    /**
     * Data type table.
     *
     * @var string
     */
    private string $table;

    /**
     * ID field.
     *
     * @var string
     */
    private string $id_field;

    /**
     * Column prefix.
     *
     * @var string
     */
    private string $column_prefix;

    /**
     * Data type table prefix.
     *
     * @var string
     */
    private string $table_prefix;

    /**
     * Data store class.
     *
     * @var string
     */
    private string $data_store;

    /**
     * Prefix for column queries.
     *
     * @var string
     */
    private string $query_prefix;

    /**
     * Data type label.
     *
     * @var string
     */
    private string $label;

    /**
     * Table columns.
     *
     * @var array
     */
    private array $columns;

    /**
     * Meta type.
     *
     * @var string|false
     */
    private string|false $meta_type;

    /**
     * Metadata props
     *
     * @var array<string, array>
     */
    private array $meta = array();

    /**
     * Array of taxonomies registered for the data type.
     * Format is array( 'taxonomy_key' => 'prop_name' ).
     *
     * @var array<string, string>|false
     */
    private array $taxonomies = array();

    /**
     * Query vars for the data type.
     *
     * @var array
     */
    private array $query_vars;

    /**
     * Feature supports for the data type.
     *
     * @var array<string, mixed>
     */
    private array $supports = array();

    /**
     * Metadata needed for query, data store and entity.
     *
     * @var array
     */
    private array $metadata = array();

    /**
     * Unique identifier
     *
     * @var string
     */
    private string $uniqid = '';

    /**
     * Is this data type initialized?
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Default prop values.
     *
     * @var array<string, mixed>
     */
    private static array $default_values = array(
        'array'     => array(),
        'array_raw' => array(),
        'binary'    => '',
        'bool'      => false,
        'date'      => null,
        'float'     => 0.0,
        'int'       => 0,
        'json'      => array(),
        'json_obj'  => null,
        'parent'    => 0,
        'post_id'   => 0,
        'tax_id'    => 0,
    );

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
     * @param  string $classname Data type class name.
     * @param  string $name      Data type key.
     * @param  array  $args      Data type arguments.
     */
    public function __construct(
        /**
         * Data type key.
         *
         * @var string
         */
        private string $classname,
        /**
         * Data type key.
         *
         * @var string
         */
        private string $name,
        array $args,
	) {
        $this->uniqid = \uniqid( $name );

        $this->set_props( $args );
    }

    /**
     * Sets the properties for the data type.
     *
     * @param array $args Data type arguments.
     *
     * @throws \WC_Data_Exception If invalid arguments are provided.
     */
    private function set_props( array $args ) {
        /**
		 * Filters the arguments for registering a data type.
		 *
		 * @param  array  $args Array of arguments for registering a post type.
		 * @param  string $type Post type key.
         * @return array
         *
         * @since 1.0.0
		 */
		$args = \apply_filters( 'xwc_register_data_type_args', $args, $this->name );

        $this->validate_args( $args );

        $this->table        = $this->set_table( $args['table'] );
        $this->table_prefix = $this->set_table_prefix( $args['table_prefix'] ?? $args['table'] );
        $this->data_store   = $this->set_data_store( $args['data_store'] ?? false );
        $this->object_type  = $this->set_object_type( $args['object_type'] ?? $this->name );
        $this->query_prefix = $this->set_query_prefix( $args['query_prefix'] ?? $this->name );
        $this->columns      = $this->set_columns( $args['columns'] );
        $this->meta_type    = $this->set_meta_type( $args['meta_type'] ?? $this->name );
        $this->meta         = $this->set_meta( $args['meta'] ?? false );
        $this->taxonomies   = $this->set_taxonomies( $args['taxonomies'] ?? null );
        $this->query_vars   = $this->set_query_vars( $args['query_vars'] ?? null );
        $this->supports     = $this->set_supports( $args['supports'] ?? null );

        $this->id_field      = $args['id_field'] ?? 'ID';
        $this->column_prefix = $args['column_prefix'] ?? '';
    }

    /**
     * Validates the arguments for the data type.
     *
     * @param  array $args Data type arguments.
     *
     * @throws \WC_Data_Exception If invalid arguments are provided.
     */
    private function validate_args( array $args ) {
        $needs = array(
            'table'      => static fn( $tbl ) => isset( $GLOBALS['wpdb']->$tbl ),
            'columns'    => static fn( $cols ) => \is_array( $cols ) && \count( $cols ) > 0,
            'data_store' => static fn( $ds ) => \class_exists( $ds ) && \in_array(
                Interfaces\Data_Repository::class,
                \class_implements( $ds ),
                true,
            ),
        );

		foreach ( $needs as $key => $check ) {
			if ( ! $check( $args[ $key ] ?? '' ) ) {
				throw new \WC_Data_Exception(
					\esc_html( 'invalid_' . $key ),
					\esc_html( "Invalid $key provided for data type {$this->name}" ),
				);
			}
		}
    }

    /**
     * Set the table for the data type.
     *
     * @param  string|false $table Table name.
     * @return string
     */
    private function set_table( string $table ): string {
        global $wpdb;

        return \str_starts_with( $table, $wpdb->prefix ) || \str_starts_with( $table, '{{PREFIX}}' )
            ? \str_replace( '{{PREFIX}}', $wpdb->prefix, $table )
            : $wpdb->$table;
    }

    /**
     * Set the table prefix for the data type.
     *
     * @param  string|false $prefix Table prefix.
     * @return string
     */
    private function set_table_prefix( string|false $prefix ): string {
        global $wpdb;

        if ( ! \property_exists( $wpdb, $prefix ) ) {
            return $prefix;
        }

        return \str_replace( array( $wpdb->prefix, $prefix ), '', $this->table );
    }

    /**
     * Set the data store for the data type.
     *
     * @param  string|false $data_store Data store class name.
     * @return string
     */
    private function set_data_store( string|false $data_store ): string {
        return $data_store;
    }

    /**
     * Object type for the data type.
     * We replace underscores with hyphens.
     *
     * @param  string $object_type Object type.
     * @return string
     */
    private function set_object_type( string $object_type ): string {
        return \str_replace( '_', '-', $object_type );
    }

    /**
     * Process the shortname for the data type.
     *
     * Since some column names can collide with Data_Query keys, we need a prefix for those query vars.
     *
     * @param  string $type Data type key.
     * @return string
     */
    private function set_query_prefix( string $type ): string {
        \preg_match_all( '/(?<=^|_)([a-zA-Z])/', $type, $matches );

        return \implode( '', $matches[0] );
    }

    /**
     * Set the meta type for the data type.
     *
     * @param  string $meta_type Meta type.
     * @return string|false
     */
    private function set_meta_type( string $meta_type ): string|false {
        if ( ! \_get_meta_table( $meta_type ) ) {
            return false;
        }

        return $meta_type;
    }

    /**
     * Get and process the columns for the data type.
     *
     * @param  array $columns Columns arguments.
     * @return array
     */
    private function set_columns( array $columns ): array {
        foreach ( $columns as $col => $data ) {
            $data = \wc_string_to_array( $data );
            $data = array(
                'type'    => $data[0],
                'default' => $data[1] ?? self::$default_values[ $data[0] ] ?? '',
                'search'  => $data[2] ?? false,
                'unique'  => $data[3] ?? false,
                'var'     => $data[4] ?? $this->query_prefix . '_' . $col,
                'date'    => 'date' === $data[0] ? $data[1] ?? true : false,
            );

            if ( $data['date'] ) {
                $data['default'] = null;
            }

            $columns[ $col ] = $data;
        }

        return $columns;
    }

    /**
     * Set the metadata properties for the data type.
     *
     * @param  array|false $meta Metadata arguments.
     * @return array
     */
    private function set_meta( array|false $meta ): array {
        if ( ! $meta || ! $this->meta_type ) {
			return array();
        }

        foreach ( $meta as $prop => $data ) {
            $data = \is_array( $data ) ? $data : array( $data );

            $meta[ $prop ] = array(
                'type'    => $data[0],
                'default' => $data[1] ?? self::$default_values[ $data[0] ] ?? '',
                'var'     => $data[2] ?? '_' . $prop,
                'unique'  => $data[3] ?? false,
            );
        }

        return $meta;
    }

    /**
     * Set the taxonomies for the data type.
     *
     * @param  array|false $taxonomies Taxonomy arguments.
     * @return array
     */
    private function set_taxonomies( ?array $taxonomies ): array {
        $taxonomies ??= array();

        foreach ( $taxonomies as $prop => $tax ) {
            if ( ! isset( $this->columns[ $prop ] ) && ! isset( $this->meta[ $prop ] ) ) {
                continue;
            }

            unset( $taxonomies[ $prop ] );
		}

        return $taxonomies;
    }

    /**
     * Set the query vars for the data type.
     *
     * @param  array|null $vars Query vars.
     * @return array
     */
    private function set_query_vars( ?array $vars ): array {
        $vars ??= array();
        $needs = array(
			'columns'    => 'column',
			'meta'       => 'meta',
			'taxonomies' => 'tax',
		);

        foreach ( $needs as $var => $query ) {
            foreach ( $this->$var as $prop => $data ) {
                $vars[ $prop ] ??= array(
                    'type' => $query,
                    'var'  => $data['var'] ?? $data,
                );
            }
        }

        return $vars;
    }

    /**
     * Set the feature supports for the data type.
     *
     * @param  array|null $s_args Supports arguments.
     * @return array
     */
    private function set_supports( ?array $s_args ): array {
        $supports = \array_diff_key(
            $s_args ?? array(),
            \array_flip( array( 'search', 'date', 'meta', 'tax', 'parent' ) ),
        );

        $search = \count( $this->get_search_columns() ) > 0;
        $date   = \count( $this->get_date_columns() ) > 0;
        $parent = false;
        $meta   = false !== $this->meta_type;
        $tax    = false !== $this->taxonomies;

        $supports['query'] = \array_filter( \compact( 'search', 'date', 'meta', 'tax', 'parent' ) );

        return $supports;
    }

    /**
     * Sets the feature supports for the data type.
     */
    protected function add_supports() {
        if ( ! $this->supports ) {
            return;
        }

        foreach ( $this->supports as $feat => $maybe_args ) {
            if ( \is_array( $maybe_args ) ) {
                \xwc_add_data_type_support( $this->name, $feat, $maybe_args );
            } else {
                \xwc_add_data_type_support( $this->name, $feat );
            }
        }
        unset( $this->supports );
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
            \xwc_register_taxonomy_for_data_type( $tax, $this->name );
        }
    }

    /**
     * Sets the data type object as initialized.
     *
     * @return void
     */
    protected function init() {
        $this->initialized = true;
    }

    /**
     * Get the cache group for the data type.
     *
     * @return array
     */
    public function get_cache_group(): string {
        return $this->object_type;
    }

    /**
     * Get prop types.
     *
     * Used by `Data` class
     *
     * @return array
     *
     * @see `Data`
     */
    public function get_prop_types(): array {
        return $this->metadata['prop_types'] ??= \array_merge(
            \wp_list_pluck( $this->columns, 'type' ),
            \wp_list_pluck( $this->meta, 'type' ),
        );
    }

    /**
     * Get core data
     *
     * Core data is date in columns
     * Used by `Data` class
     *
     * @return array
     */
    public function get_core_data(): array {
        return $this->metadata['core_data'] ??= \wp_list_pluck( $this->columns, 'default' );
    }

    /**
     * Get data
     *
     * Used by `Data` class
     *
     * @return array
     */
    public function get_data(): array {
        return $this->metadata['data'] ??= \wp_list_pluck( $this->meta, 'default' );
    }

    /**
     * Get meta key to props
     *
     * Used by `Data_Store` class
     *
     * @return string
     */
    public function get_meta_key_to_props(): array {
        return $this->metadata['meta_key_to_props'] ??= \array_flip( \wp_list_pluck( $this->meta, 'var' ) );
    }

    /**
     * Get internal meta keys
     *
     * Used by `Data_Store` class
     *
     * @return array
     */
    public function get_internal_meta_keys(): array {
        return $this->metadata['internal_meta_keys'] ??= \array_values( \wp_list_pluck( $this->meta, 'var' ) );
    }

    /**
     * Get must exist meta keys
     *
     * Used by `Data_Store` class
     *
     * @return array
     */
    public function get_must_exist_meta_keys(): array {
        return $this->metadata['must_exist_meta_keys'] ??= \array_values(
            \wp_list_pluck(
                \wp_list_filter( $this->meta, array( 'required' => true ) ),
                'var',
            ),
        );
    }

    /**
     * Get search columns
     *
     * Used by `Data_Query` class
     *
     * @return array
     */
    public function get_search_columns(): array {
        return $this->metadata['search_columns'] ??= \array_keys(
            \wp_list_filter( $this->columns, array( 'search' => true ) ),
        );
    }

    /**
     * Get date columns
     *
     * Used by `Data_Query` class
     *
     * @return array
     */
    public function get_date_columns(): array {
        return $this->metadata['date_columns'] ??= \array_filter( \wp_list_pluck( $this->columns, 'date' ) );
    }

    /**
     * Get column vars
     *
     * Used by `Data_Query` class
     *
     * @return array
     */
    public function get_column_vars(): array {
        return $this->metadata['column_vars'] ??= \array_flip(
            \wp_list_pluck(
                \wp_list_filter( $this->query_vars, array( 'type' => 'cols' ) ),
                'var',
            ),
        );
    }

    /**
     * Enables access to private properties.
     *
     * @param  string $prop Property.
     * @return mixed        Property value.
     */
    public function __get( string $prop ) {
        return $this->$prop;
    }

    /**
     * Checks if a property is set.
     *
     * @param  string $name Property name.
     * @return bool
     */
    public function __isset( $name ) {
        return isset( $this->$name );
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
