<?php // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys, WordPress.PHP.DiscouragedPHPFunctions, WordPress.PHP.NoSilencedErrors.Discouraged, Universal.Operators.DisallowShortTernary.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

namespace XWC\Data\Decorators;

use XWC_Data;
use XWC_Data_Store_XT;
use XWC_Meta_Store;
use XWC_Object_Factory;

/**
 * Data model definition.
 *
 * @template TData of XWC_Data
 * @template TDstr of XWC_Data_Store_XT
 * @template TFact of XWC_Object_Factory
 * @template TMeta of XWC_Meta_Store
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Model {
    /**
     * Data object name.
     *
     * @var string | null
     */
    public ?string $name;

    /**
     * Table ID.
     *
     * @var string
     */
    private string $table_id;

    /**
     * Model class name.
     *
     * @var class-string<TData>
     */
    public string $model;

    public string $table;
    public string $data_store;
    public string $factory;

    /**
     * Core properties.
     *
     * @var array<string,string|array{
     *   name?: string,
     *   type: 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array_set'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'other'|string|class-string,
     *   default: mixed,
     *   unique?: bool,
     *   def_cb?: callable(): mixed,
     * }>
     */
    public array $core_props;
    public string $id_field;
    public ?string $meta_store;

    /**
     * Meta properties.
     *
     * @var array<string,array{
     *   name?: string,
     *   type: 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array_set'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'other'|string|class-string,
     *   default: mixed,
     *   unique?: bool,
     *   required?: bool,
     * }>
     */
    public array $meta_props;

    public string $meta_table;
    public string $meta_id_field;
    public string $meta_obj_field;

    /**
     * Taxonomy properties.
     *
     * @var array<string,array{
     *   tax: string,
     *   default: string,
     *   single: bool,
     *   return: string,
     * }>
     */
    public array $tax_props;

    /**
     * Constructor.
     *
     * @param  string                   $name       Data object name.
     * @param  string                   $table      Database table name.
     * @param  array<string,string|array{
     *   name?: string,
     *   type: 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array_set'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'other'|string|class-string,
     *   default?: mixed,
     *   unique?: bool,
     *   def_cb?: callable(): mixed,
     * }>                               $core_props Array of core properties.
     * @param  array<string,array{
     *   name?: string,
     *   type: 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array_set'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'other'|string|class-string,
     *   default?: mixed,
     *   unique?: bool,
     *   required?: bool,
     * }>                               $meta_props Array of meta properties.
     * @param  array<string,array{
     *   taxonomy: string,
     *   field?: 'term_id'|'slug'|'name'|'parent',
     *   default?: string|array<string>|array<int>,
     *   return?: 'single'|'array',
     *   required?: bool
     * }>                               $tax_props  Array of taxonomy properties.
     * @param  string                   $id_field   ID field column name.
     * @param  class-string<TDstr>|null $data_store Data store class name.
     * @param  class-string<TFact>|null $factory    Object factory class name.
     * @param  class-string<TMeta>|null $meta_store Meta store class name.
     * @param  string|null              $container  XWP-DI Container ID.
     * @param  string|null              $meta_table Meta table name.
     * @param  string|null              $meta_id_field Meta ID field name.
     * @param  string|null              $meta_obj_field Meta object field name.
     */
    public function __construct(
        string $name,
        string $table,
        array $core_props,
        array $meta_props = array(),
        array $tax_props = array(),
        string $id_field = 'id',
        ?string $data_store = null,
        ?string $factory = null,
        ?string $meta_store = null,
        ?string $container = null,
        ?string $meta_table = null,
        ?string $meta_id_field = null,
        ?string $meta_obj_field = null,
    ) {
        $this->name     = $name;
        $this->table_id = $table;
        $this->scaffold(
            \compact(
                'table',
                'data_store',
                'factory',
                'core_props',
                'tax_props',
                'id_field',
                'meta_store',
                'meta_props',
                'container',
                'meta_table',
                'meta_id_field',
                'meta_obj_field',
            ),
        );
    }

    /**
     * Set the model class name.
     *
     * @param  class-string<TData> $model Model class name.
     * @return static
     */
    public function set_model( string $model ): static {
        $this->model ??= $model;

        return $this;
    }

    /**
     * Get the definers for the model properties.
     *
     * @return array<string,string>
     */
    protected function get_definers(): array {
        return array(
            'table'          => 'set_table',
            'data_store'     => 'set_data_store',
            'factory'        => 'set_factory',
            'core_props'     => 'set_core_props',
            'meta_props'     => 'set_meta_props',
            'tax_props'      => 'set_tax_props',
            'meta_store'     => 'set_meta_store',
            'id_field'       => 'set_id_field',
            'container'      => 'set_container',
            'meta_table'     => 'set_meta_table',
            'meta_id_field'  => 'set_meta_id_field',
            'meta_obj_field' => 'set_meta_obj_field',
        );
    }

    /**
     * Get the arguments for registering a data type.
     *
     * @param  array<string,mixed> $args Arguments for registering a data type.
     * @return array<string,mixed>
     */
    protected function get_entity_args( array $args ): array {
        /**
         * Filters the arguments for registering a data type.
         *
         * @param  array  $def  Array of arguments for registering a data type.
         * @param  string $type Data type key.
         * @return array
         *
         * @since 1.0.0
         */
        return \apply_filters( 'xwc_data_model_definition', $args, $this->name );
    }

    /**
     * Scaffold the model properties.
     *
     * @param  array<string,mixed> $args Arguments for registering a data type.
     */
    protected function scaffold( array $args ): void {
        $args = $this->get_entity_args( $args );

        foreach ( $this->get_definers() as $prop => $setter ) {
            $this->$prop = $this->$setter( $args[ $prop ] );
        }
    }

    protected function set_table( string $table ): string {
        global $wpdb;

        if ( isset( $wpdb->$table ) ) {
            return $wpdb->$table;
        }

        return \str_replace( '{{PREFIX}}', $wpdb->prefix, $table );
    }

    /**
     * Set the data store class name.
     *
     * @param  class-string<TDstr>|null $store Data store class name.
     * @return ($store is null ? class-string<XWC_Data_Store_XT<TData>> : class-string<TDstr>)
     *
     * @throws \InvalidArgumentException If the store class does not exist.
     */
    protected function set_data_store( ?string $store ): string {
        if ( \is_null( $store ) ) {
            return XWC_Data_Store_XT::class;
        }

        if ( ! \class_exists( $store ) ) {
            throw new \InvalidArgumentException( \esc_html( "Data store class $store does not exist." ) );
        }

        return $store;
    }

    /**
     * Set the object factory class name.
     *
     * @param  class-string<TFact>|null $factory Object factory class name.
     * @return ($factory is null ? class-string<XWC_Object_Factory<TData>> : class-string<TFact>)
     */
    protected function set_factory( ?string $factory ): string {
        if ( \is_null( $factory ) ) {
            return XWC_Object_Factory::class;
        }

        if ( ! \class_exists( $factory ) ) {
            throw new \InvalidArgumentException( \esc_html( "Factory class $factory does not exist." ) );
        }

        return $factory;
    }

    /**
     * Set the core properties.
     *
     * @param  array<string,string|array<string,mixed>> $props Array of core properties.
     * @return array<string,array<string,mixed>>
     */
    protected function set_core_props( array $props ): array {
        $default = static fn( $n ) => array(
            'default' => '',
            'name'    => $n,
            'type'    => 'string',
            'unique'  => false,
        );
        $parsed  = array();

        foreach ( $props as $prop => $args ) {
            if ( ! \is_array( $args ) ) {
                $args = array( 'default' => $args );
            }

            if ( isset( $args['def_cb'] ) ) {
                $args['default'] = $args['def_cb']();
                unset( $args['def_cb'] );
            }

            $parsed[ $prop ] = \wp_parse_args( $args, $default( $prop ) );
        }

        return $parsed;
    }

    /**
     * Set the meta store class name.
     *
     * @param  class-string<TMeta>|null $store Meta store class name.
     * @return class-string<TMeta>|null
     */
    protected function set_meta_store( ?string $store ): ?string {
        if ( array() === $this->meta_props ) {
            return null;
        }

        $store ??= XWC_Meta_Store::class;

        if ( ! \class_exists( $store ) ) {
            throw new \InvalidArgumentException( \esc_html( "Meta store class $store does not exist." ) );
        }

        return $store;
    }

    /**
     * Set the meta properties.
     *
     * @param  array<string,array<string,mixed>> $props Array of meta properties.
     * @return array<string,array<string,mixed>>
     */
    protected function set_meta_props( array $props ): array {
        $default = static fn( $n ) => array(
            'default'  => '',
            'name'     => '_' . \ltrim( $n, '_' ),
            'required' => false,
            'type'     => 'string',
            'unique'   => false,
        );

        foreach ( $props as $prop => $args ) {
            if ( ! \is_array( $args ) ) {
                $args = array( 'default' => $args );
            }

            $props[ $prop ] = \wp_parse_args( $args, $default( $prop ) );
        }

        return $props;
    }

    /**
     * Set the meta table name.
     *
     * @param  string|null $table Meta table name.
     * @return string
     */
    protected function set_meta_table( ?string $table ): string {
        if ( null === $this->meta_store || array() === $this->meta_props ) {
            return '';
        }

        $table ??= \rtrim( $this->table_id, 's' ) . 'meta';

        return $this->set_table( $table );
    }

    protected function set_meta_id_field( ?string $field ): string {
        return $field ?? 'id';
    }

    protected function set_meta_obj_field( ?string $field ): string {
        return $field ?? 'object_id';
    }

    /**
     * Set the taxonomy properties.
     *
     * @param  array<string,array<string,mixed>> $props Array of taxonomy properties.
     * @return array<string,array<string,mixed>>
     */
    protected function set_tax_props( array $props ): array {
        foreach ( $props as &$args ) {
            $args = $this->parse_tax_arg( $args );
        }

        return \array_filter( $props );
    }

    protected function set_id_field( string $field ): string {
        return $field;
    }

    protected function set_container( ?string $container ): string {
        return $container ?? '';
    }

    /**
     * Parse the taxonomy argument for a property.
     *
     * @param  array<string,mixed> $args Taxonomy arguments.
     * @return array<string,mixed>
     */
    private function parse_tax_arg( array $args ): array {
        $args = \wp_parse_args(
            $args,
            array(
                'field'    => 'term_id',
                'default'  => '',
                'required' => false,
                'return'   => 'single',
            ),
        );

        $args['field']   = \preg_replace( '/^id$/', 'term_id', \ltrim( $args['field'], 'term_' ) );
        $args['default'] = 'array' === $args['return'] ? (array) $args['default'] : $args['default'];
        $args['type']    = \sprintf( 'term_%s|%s|%s', $args['return'], $args['field'], $args['taxonomy'] );

        return $args;
    }
}
