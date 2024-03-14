<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
/**
 * Data_Type_Definition class file.
 *
 * @package eXtended WooCommerce
 * @subpackage Decorators
 */

namespace XWC\Decorators;

use XWC\Data_Object_Factory;
use XWC\Data_Store;

/**
 * Complete data type definition.
 *
 * @property-read string $id_field      ID field.
 * @property-read string $column_prefix Column prefix.
 * @property-read string $table         Data type table.
 * @property-read string $table_prefix  Table prefix.
 * @property-read string $data_store    Data store class.
 * @property-read string $object_type   Object type.
 * @property-read string $cache_group  Cache prefix.
 * @property-read string $query_prefix  Prefix for column queries.
 * @property-read array  $columns       Columns for the data type.
 * @property-read string $meta_type     Meta type.
 * @property-read array  $meta          Meta columns.
 * @property-read array  $taxonomies    Taxonomies.
 * @property-read array  $query_vars    Query vars.
 * @property-read string $name          Data type name.
 * @property-read array  $supports      Array of supported features.
 *
 * @property-read class-string<Data_Object_Factory> $factory Factory class.
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Data_Type_Definition {
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
     * Data type table.
     *
     * @var string
     */
    private string $table;

    /**
     * Table prefix.
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
     * Factory class.
     *
     * @var string
     */
    private string $factory;

    /**
     * Object type.
     *
     * @var string
     */
    private string $object_type;

    /**
     * Cache prefix.
     *
     * @var string
     */
    private string $cache_group;

    /**
     * Prefix for column queries.
     *
     * @var string
     */
    private string $query_prefix;

    /**
     * Columns for the data type.
     *
     * @var array
     */
    private array $columns;

    /**
     * Meta type.
     *
     * @var string
     */
    private string $meta_type;

    /**
     * Meta columns.
     *
     * @var array
     */
    private array $meta;

    /**
     * Taxonomies.
     *
     * @var array
     */
    private array $taxonomies;

    /**
     * Array of supported features.
     *
     * @var array
     */
    private array $supports;

    /**
     * Query vars.
     *
     * @var array
     */
    private array $query_vars;

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
        'string'    => '',
    );

    /**
     * Private props accessible via magic method.
     *
     * @var array<int, string>
     */
    private static array $accessible_props = array(
        'id_field',
        'factory',
        'column_prefix',
        'table',
        'table_prefix',
        'data_store',
        'object_type',
        'cache_group',
        'query_prefix',
        'columns',
        'meta_type',
        'meta',
        'taxonomies',
        'query_vars',
        'name',
        'supports',
    );

    /**
     * Definition arguments.
     *
     * @var array
     */
    private array $args;

    /**
     * Constructor.
     *
     * @param string $name       Data type name.
     * @param array  $config     Data type configuration.
     * @param array  $structure  Data type structure.
     */
    public function __construct(
        /**
         * Data type name.
         *
         * @var string
         */
        private string $name,
        array $config,
        array $structure,
	) {
        $this->scaffold( \array_merge( $config, $structure ) );
    }

    /**
     * Set up the data type definition.
     *
     * @param  array $args Data type definition.
     *
     * @uses Data_Type_Definition::set_table()
     */
    private function scaffold( array $args ): void {
        /**
		 * Filters the arguments for registering a data type.
		 *
		 * @param  array  $def  Array of arguments for registering a post type.
		 * @param  string $type Post type key.
         * @return array
         *
         * @since 1.1.0
		 */
		$args  = \apply_filters( 'xwc_data_type_definition', $args, $this->name );
        $props = array(
			'table'        => 'set_table',
            'table_prefix' => 'set_table_prefix',
			'data_store'   => 'set_data_store',
            'factory'      => 'set_factory',
			'object_type'  => 'set_object_type',
            'cache_group'  => 'set_cache_group',
			'query_prefix' => 'set_query_prefix',
			'columns'      => 'set_columns',
			'meta_type'    => 'set_meta_type',
			'meta'         => 'set_meta',
			'taxonomies'   => 'set_taxonomies',
			'query_vars'   => 'set_query_vars',
            'supports'     => 'set_supports',
		);

        $this->validate_definition( $args );

        $this->id_field      = $args['id_field'] ?? 'ID';
        $this->column_prefix = $args['column_prefix'] ?? '';

        foreach ( $props as $prop => $setter ) {
            $this->$prop = $this->{"$setter"}( $args[ $prop ] ?? null );
        }
    }

    /**
     * Validates the arguments for the data type.
     *
     * @param  array $def Data type arguments.
     *
     * @throws \WC_Data_Exception If invalid arguments are provided.
     */
    private function validate_definition( array $def ) {
        global $wpdb;

        $needs = array(
            'table'      => static fn( $tbl ) => isset( $wpdb->$tbl ) || \str_starts_with( $tbl, '{{PREFIX}}' ),
            'columns'    => static fn( $cols ) => \is_array( $cols ) && \count( $cols ) > 0,
            'data_store' => static fn( $ds ) => Data_Store::is_valid( $ds ),
        );

		foreach ( $needs as $key => $check ) {
			if ( ! $check( $def[ $key ] ?? '' ) ) {
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
     *
     * @used-by Data_Type_Definition::setup_definition()
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
    private function set_table_prefix( ?string $prefix ): string {
        global $wpdb;

        if ( ! \is_null( $prefix ) && ! \property_exists( $wpdb, $prefix ) ) {
            return \rtrim( $prefix, '_' ) . '_';
        }

        return \explode( '_', \str_replace( $wpdb->prefix, '', $this->table ) )[0];
    }

    /**
     * Set the data store for the data type.
     *
     * @param  string|false $data_store Data store class name.
     * @return string
     */
    private function set_data_store( string $data_store ): string {
        return $data_store;
    }

    /**
     * Set the factory class for the data type.
     *
     * @param  string|false $factory Factory class name.
     * @return string
     */
    private function set_factory( ?string $factory ): string {
        return $factory ?? Data_Object_Factory::class;
    }

    /**
     * Object type for the data type.
     * We replace underscores with hyphens.
     *
     * @param  string $object_type Object type.
     * @return string
     */
    private function set_object_type( ?string $object_type ): string {
        $object_type ??= \str_replace( '_', '-', $this->name );
        return \sanitize_key( $object_type );
    }

    /**
     * Set the cache prefix for the data type.
     *
     * @param  string|false $cache_group Query prefix.
     * @return string
     */
    private function set_cache_group( ?string $cache_group ): string {
        return $cache_group ?? $this->object_type;
    }

    /**
     * Process the shortname for the data type.
     *
     * Since some column names can collide with Data_Query keys, we need a prefix for those query vars.
     *
     * @param  string $prefix Data type key.
     * @return string
     */
    private function set_query_prefix( ?string $prefix ): string {
        if ( ! \is_null( $prefix ) ) {
            return \rtrim( $prefix, '_' ) . '_';
        }

        $prefix = $this->name;

        \preg_match_all( '/(?<=^|_)([a-zA-Z])/', $prefix, $matches );

        return \implode( '', $matches[0] ) . '_';
    }

    /**
     * Get and process the columns for the data type.
     *
     * @param  array $columns Columns arguments.
     * @return array
     */
    private function set_columns( array $columns ): array {
        foreach ( $columns  as $col => $data ) {
            $data = \wp_parse_args(
                $data,
                array(
					'search' => false,
					'type'   => 'string',
					'unique' => false,
					'var'    => $this->query_prefix . $col,
                ),
            );

            $data['default'] ??= static::$default_values[ $data['type'] ];
            $columns[ $col ]   = $data;
        }

        return $columns;
    }

    /**
     * Set the meta type for the data type.
     *
     * @param  string $meta_type Meta type.
     * @return string|false
     */
    private function set_meta_type( ?string $meta_type ): string|false {
        if ( false === $meta_type ) {
            return '';
        }

        $meta_type ??= $this->name;

        return \sanitize_key( $meta_type );
    }

    /**
     * Set the metadata properties for the data type.
     *
     * @param  ?array $meta Metadata arguments.
     * @return array
     */
    private function set_meta( ?array $meta ): array {
        foreach ( $meta  as $key => $data ) {
            $data = \wp_parse_args(
                $data,
                array(
                    'key'      => '_' . \ltrim( $key, '_' ),
                    'required' => false,
                    'type'     => 'string',
                    'unique'   => false,
                    'var'      => $key,
                ),
            );

            $data['default'] ??= static::$default_values[ $data['type'] ];
            $meta[ $key ]      = $data;
        }

        return $meta;
    }

    /**
     * Set the taxonomies for the data type.
     *
     * @param  array $taxonomies Taxonomy arguments.
     * @return array
     */
    private function set_taxonomies( ?array $taxonomies ): array {
        $taxonomies ??= array();
        $tax_data     = array();

        foreach ( $taxonomies as $prop => $data ) {
            $data = \wp_parse_args(
                $data,
                array(
                    'force'    => false,
                    'multiple' => true,
                    'tax'      => null,
                    'type'     => 'tax',
                    'var'      => $prop,
                ),
            );
            $tax  = \get_taxonomy( $data['tax'] ?? '' );

            if ( ! $tax ) {
                continue;
            }

            $data['default'] = $this->get_default_terms( $data['default'] ?? $tax->default_term, $tax->name );

            $tax_data[ $prop ] = $data;
        }

        return $tax_data;
    }

    /**
     * Get default terms for a taxonomy.
     *
     * @param  array|string|null $term_data Term data.
     * @param  string            $tax       Taxonomy name.
     * @return array<int, int>
     */
    private function get_default_terms( array|string|null $term_data, string $tax ): array {
        if ( \is_null( $term_data ) ) {
            return array();
        }

        $default = \is_array( $term_data ) ? $term_data['name'] : $term_data;
        $default = \get_term_by( 'name', $term_data, $tax );

        if ( ! $default ) {
            return array();
        }

        return array( $default->term_id );
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
                    'var'  => $data['var'],
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
        return $s_args ?? array();
    }

    /**
     * Get a property from the definition.
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get( string $name ) {
        if ( \in_array( $name, static::$accessible_props, true ) ) {
            return $this->$name;
        }

        return null;
    }
}
