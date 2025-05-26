<?php

namespace XWC\Data;

use Psr\Container\ContainerInterface;
use XWC\Data\Decorators\Model;
use XWC\Data\Decorators\Model_Modifier;
use XWC_Data;
use XWC_Data_Store_XT;
use XWC_Meta_Store;
use XWC_Object_Factory;

/**
 * Entity object.
 *
 * Base definition.
 *
 * @template TData of XWC_Data
 * @template TDstr of XWC_Data_Store_XT<TData>
 * @template TFact of XWC_Object_Factory<TData>
 * @template TMeta of XWC_Meta_Store<TData>
 *
 * @property-read string $name       Data object name.
 * @property-read string $table      Database table name.
 * @property-read string $id_field   ID field name. Default 'id'.
 * @property-read array  $core_props Core properties.
 * @property-read array  $meta_props Meta properties. Optional.
 * @property-read array<string,array{
 *   type: string,
 *   tax: string,
 *   default: string,
 *   single: bool,
 *   return: string,
 * }>                    $tax_props  Taxonomy properties.
 * @property-read bool   $has_meta   Has meta data.
 *
 * Stores and classnames.
 *
 * @property-read class-string<TData>              $model      Model class name.
 * @property-read TFact        $factory    Object factory.
 * @property-read TMeta|null   $meta_store Meta store class name. Optional.
 * @property-read TDstr        $repo       Data store instance.
 *
 * Custom data.
 *
 * @property-read array<string,mixed>  $core_data     Core data.
 * @property-read array<string,mixed>  $tax_data      Taxonomy data.
 * @property-read array<string,mixed>  $data          Data.
 * @property-read array<string,string> $prop_types    Property types.
 * @property-read array<string>        $unique_data   Unique data.
 * @property-read array<string,string> $required_data Column names to property names.
 * @property-read array<string,string> $cols_to_props Column names to property names.
 * @property-read array<string,string> $meta_to_props Meta keys to property names.
 * @property-read array<string,string> $tax_to_props  Taxonomy keys to property names.
 * @property-read array<string,string> $tax_fields    Taxonomy fields.
 *
 * @property-read string $meta_table      Meta table name.
 * @property-read string $meta_id_field   Meta ID field name. Default 'meta_id'.
 * @property-read string $meta_obj_field  Meta object field name. Default 'object_id'.
 */
class Entity {
    private const FIELDS = array(
        'name',
        'model',
        'table',
        'data_store',
        'core_props',
        'repo',
        'factory',
        'id_field',
        'meta_store',
        'meta_props',
        'tax_props',
        'core_data',
        'data',
        'tax_data',
        'prop_types',
        'unique_data',
        'required_data',
        'cols_to_props',
        'meta_to_props',
        'tax_to_props',
        'tax_fields',
        'has_meta',
        'meta_table',
        'meta_id_field',
        'meta_obj_field',
    );

    /**
     * Object factories.
     *
     * @var array<string,TFact>
     */
    protected static array $factories = array();

    /**
     * Data stores.
     *
     * @var array<string,TDstr>
     */
    protected static array $stores = array();

    protected static bool $hooked;

    /**
     * Data object name.
     *
     * @var string
     */
    protected string $name;

    /**
     * Model class name.
     *
     * @var class-string<TData>
     */
    protected string $model;

    protected string $table;

    /**
     * Data store class name.
     *
     * @var class-string<TDstr>
     */
    protected string $data_store;

    /**
     * XWP-DI Container ID.
     *
     * @var string
     */
    protected string $container;

    protected string $factory;

    /**
     * Core properties.
     *
     * @var array<string,string|array{
     *   name?: string,
     *   type: 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'string'|'other',
     *   default: mixed,
     *   unique?: bool,
     *   def_cb?: callable(): mixed,
     * }>
     */
    protected array $core_props;
    protected string $id_field;
    protected ?string $meta_store;

    /**
     * Meta properties.
     *
     * @var array<string,array{
     *   name?: string,
     *   type: 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'string'|'other',
     *   default: mixed,
     *   unique?: bool,
     *   required?: bool,
     * }>
     */
    protected ?array $meta_props;

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
    protected ?array $tax_props;

    public string $meta_table;
    public string $meta_id_field;
    public string $meta_obj_field;
    /**
     * Container instance.
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $ctr;

    /**
     * Constructor.
     *
     * @param  Model<TData,TDstr,TFact,TMeta> ...$defs Model definitions.
     */
    public function __construct(
        Model ...$defs,
    ) {
        $vars = \array_keys( \get_class_vars( $this::class ) );
        $vars = \array_diff(
            $vars,
            array( 'args', 'factories', 'stores', 'hooked', 'defaults', 'ctr', 'container' ),
        );

        foreach ( $vars as $var ) {

            $this->$var = $this->set_prop( $var, $defs );
        }

        static::$stores[ $this->name ] = null;
    }

    /**
     * Get a property value.
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get( string $name ): mixed {
        return match ( true ) {
            ! \in_array( $name, self::FIELDS, true ) => null,
            \method_exists( $this, "get_$name" )     => $this->{"get_$name"}(),
            default                                  => $this->$name,
        };
    }

    /**
     * Set a container instance.
     *
     * @param  ContainerInterface $container Container instance.
     * @return static
     */
    public function with_container( ContainerInterface $container ): static {
        $this->ctr = $container;

        return $this;
    }

    public function add_hooks(): void {
        static::$hooked ??= \add_filter( 'woocommerce_data_stores', array( $this, 'prime_data_store' ), 10, 1 );
        \add_filter( "woocommerce_{$this->name}_data_store", fn() => $this->get_data_store(), 10, 1 );
    }

    /**
     * Prime the data store with the entity name.
     *
     * @param  array<string,mixed> $stores Data stores.
     * @return array<string,null>
     */
    public function prime_data_store( array $stores ): array {
        $to_add = \array_keys( static::$stores );
        return \array_merge(
            $stores,
            \array_combine( $to_add, \array_fill( 0, \count( $to_add ), null ) ),
        );
    }

    /**
     * Set a property value based on definitions.
     *
     * @param  string                                                                        $prop Property name.
     * @param  array<Model<TData,TDstr,TFact,TMeta>|Model_Modifier<TData,TDstr,TFact,TMeta>> $defs Model definitions.
     * @return mixed
     */
    protected function set_prop( string $prop, array $defs ): mixed {
        $defined = \wp_list_pluck( \wp_list_filter( $defs, array( $prop => null ), 'NOT' ), $prop );

        if ( 1 === \count( $defined ) ) {
            return \current( $defined );
        }

        $base = \array_shift( $defined );

        if ( \is_array( $base ) ) {
            return \array_merge( $base, ...$defined );
        }

        $final = \end( $defined );

        return $final ? $final : $base;
    }

    /**
     * Get the default core data properties.
     *
     * @return array<string,mixed>
     */
    protected function get_core_data(): array {
        return \wp_list_pluck( $this->core_props, 'default' );
    }

    /**
     * Get the default meta data properties.
     *
     * @return array<string,mixed>
     */
    protected function get_data(): array {
        return \wp_list_pluck( $this->meta_props, 'default' );
    }

    /**
     * Get the default taxonomy data properties.
     *
     * @return array<string,mixed>
     */
    protected function get_tax_data(): array {
        return \wp_list_pluck( $this->tax_props, 'default' );
    }

    /**
     * Get the property types.
     *
     * @return array<string,string>
     */
    protected function get_prop_types(): array {
        return \array_merge(
            \wp_list_pluck( $this->core_props, 'type' ),
            \wp_list_pluck( $this->meta_props, 'type' ),
            \wp_list_pluck( $this->tax_props, 'type' ),
        );
    }

    /**
     * Get the unique data properties.
     *
     * @return array<string>
     */
    protected function get_unique_data(): array {
        return \array_keys(
            \wp_list_filter( $this->core_props, array( 'unique' => true ) ),
        );
    }

    /**
     * Get the required data properties.
     *
     * @return array<string,bool>
     */
    protected function get_required_data(): array {
        return \wp_list_pluck(
            \wp_list_filter( $this->core_props, array( 'required' => true ) ),
            'required',
        );
    }

    /**
     * Get the meta keys to properties mapping.
     *
     * @return array<string,string>
     */
    protected function get_meta_to_props(): array {
        return \array_flip( \wp_list_pluck( $this->meta_props, 'name' ) );
    }

    /**
     * Get the columns to properties mapping.
     *
     * @return array<string,string>
     */
    protected function get_cols_to_props(): array {
        return \wp_list_pluck( $this->core_props, 'name' );
    }

    /**
     * Get the taxonomy keys to properties mapping.
     *
     * @return array<string,string>
     */
    protected function get_tax_to_props(): array {
        return \array_flip( \wp_list_pluck( $this->tax_props, 'taxonomy' ) );
    }

    /**
     * Get the taxonomy fields.
     *
     * @return array<string,string>
     */
    protected function get_tax_fields(): array {
        return \wp_list_pluck( $this->tax_props, 'field', 'taxonomy' );
    }

    /**
     * Get the factory instance.
     *
     * @return TFact
     */
    protected function get_factory(): XWC_Object_Factory {
        return static::$factories[ $this->name ] ??= $this->factory::instance();
    }

    /**
     * Get the meta store.
     *
     * @return ?TMeta
     */
    protected function get_meta_store(): ?XWC_Meta_Store {
        if ( \is_null( $this->meta_store ) ) {
            return null;
        }

        /**
         * Variable override.
         *
         * @var class-string<TMeta> $cname
         */
        $cname = $this->meta_store;

        return $this->make( $cname )->initialize( $this );
    }

    protected function get_has_meta(): bool {
        return '' !== $this->meta_table && array() !== $this->meta_props;
    }

    /**
     * Get the data store.
     *
     * @return TDstr
     */
    protected function get_data_store(): XWC_Data_Store_XT {
        $cname = $this->data_store;

        return static::$stores[ $this->name ] ??= $this->make( $cname )->initialize( $this );
    }

    /**
     * Get the repository instance.
     *
     * @return TDstr
     */
    protected function get_repo(): XWC_Data_Store_XT {
        return $this->get_data_store();
    }

    /**
     * Make an instance of a class.
     *
     * @template TObj of TDstr|TMeta
     * @param  class-string<TObj> $cname Class name.
     * @return TObj
     */
    private function make( string $cname ): object {
        return $this->get_ctr()?->get( $cname ) ?? new $cname();
    }

    /**
     * Get the container instance.
     *
     * @return ContainerInterface|null
     */
    private function get_ctr(): ?ContainerInterface {
        if ( isset( $this->ctr ) ) {
            return $this->ctr;
        }

        if ( ! isset( $this->container ) || ! $this->container ) {
            return null;
        }

        return $this->ctr ??= \xwp_app( $this->container );
    }
}
