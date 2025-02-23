<?php

namespace XWC\Data;

use XWC\Data\Decorators\Model;
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
 * @template TDstr of XWC_Data_Store_XT
 * @template TFact of XWC_Object_Factory
 * @template TMeta of XWC_Meta_Store
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
 * @property-read TFact<TData>        $factory    Object factory.
 * @property-read TMeta<TData>|null   $meta_store Meta store class name. Optional.
 *
 * Custom data.
 *
 * @property-read array<string,mixed>  $core_data     Core data.
 * @property-read array<string,mixed>  $tax_data      Taxonomy data.
 * @property-read array<string,mixed>  $data          Data.
 * @property-read array<string,string> $prop_types    Property types.
 * @property-read array<string,bool>   $unique_data   Unique data.
 * @property-read array<string,string> $required_data Column names to property names.
 * @property-read array<string,string> $cols_to_props Column names to property names.
 * @property-read array<string,string> $meta_to_props Meta keys to property names.
 * @property-read array<string,string> $tax_to_props  Taxonomy keys to property names.
 * @property-read array<string,string> $tax_fields    Taxonomy fields.
 */
class Entity {
    private const FIELDS = array(
		'name',
        'model',
        'table',
        'data_store',
        'core_props',
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
	);

    /**
     * Object factories.
     *
     * @var array<string, TFact<TData>>
     */
    protected static array $factories = array();

    /**
     * Data stores.
     *
     * @var array<string, TDstr<TData,TMeta<TData>>>
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
     * @var class-string<TDstr<TData,TMeta<TData>>>
     */
    protected string $data_store;

    /**
     * XWP-DI Container ID.
     *
     * @var string
     */
    protected string $container;

    protected string $factory;
    protected array $core_props;
    protected string $id_field;
    protected ?string $meta_store;
    protected ?array $meta_props;
    protected ?array $tax_props;

    /**
     * Constructor.
     *
     * @param  Model<TData,TDstr,TFact,TMeta> ...$defs Model definitions.
     */
    public function __construct(
        Model ...$defs,
    ) {
        $vars = \array_keys( \get_class_vars( $this::class ) );
        $vars = \array_diff( $vars, array( 'args', 'factories', 'stores', 'hooked', 'defaults' ) );

        foreach ( $vars as $var ) {
            $this->$var = $this->set_prop( $var, $defs );
        }

        static::$stores[ $this->name ] = null;
    }

    /**
     * Get a property value.
     *
     * @param  string $name Property name.
     */
    public function __get( string $name ) {
        return match ( true ) {
            ! \in_array( $name, self::FIELDS, true ) => null,
            \method_exists( $this, "get_$name" )     => $this->{"get_$name"}(),
            default                                  => $this->$name,
        };
    }

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

    protected function get_core_data(): array {
        return \wp_list_pluck( $this->core_props, 'default' );
    }

    protected function get_data(): array {
        return \wp_list_pluck( $this->meta_props, 'default' );
    }

    protected function get_tax_data(): array {
        return \wp_list_pluck( $this->tax_props, 'default' );
    }

    protected function get_prop_types(): array {
        return \array_merge(
            \wp_list_pluck( $this->core_props, 'type' ),
            \wp_list_pluck( $this->meta_props, 'type' ),
            \wp_list_pluck( $this->tax_props, 'type' ),
        );
    }

    protected function get_unique_data(): array {
        return \array_keys(
            \wp_list_filter( $this->core_props, array( 'unique' => true ) ),
        );
    }

    protected function get_required_data(): array {
        return \wp_list_pluck(
            \wp_list_filter( $this->core_props, array( 'required' => true ) ),
            'required',
        );
    }

    protected function get_meta_to_props(): array {
        return \array_flip( \wp_list_pluck( $this->meta_props, 'name' ) );
    }

    protected function get_cols_to_props(): array {
        return \wp_list_pluck( $this->core_props, 'name' );
    }

    protected function get_tax_to_props(): array {
        return \array_flip( \wp_list_pluck( $this->tax_props, 'taxonomy' ) );
    }

    protected function get_tax_fields(): array {
        return \wp_list_pluck( $this->tax_props, 'field', 'taxonomy' );
    }

    protected function get_factory(): XWC_Object_Factory {
        return static::$factories[ $this->name ] ??= $this->factory::instance();
    }

    /**
     * Get the meta store.
     *
     * @return TMeta<TData>|null
     */
    protected function get_meta_store(): ?XWC_Meta_Store {
        if ( \is_null( $this->meta_store ) ) {
            return null;
        }

        /**
         * Variable override.
         *
         * @var class-string<TMeta<TData>> $cname
         */
        $cname = $this->meta_store;

        return $this->make( $cname );
    }

    protected function get_has_meta(): bool {
        return null !== $this->get_meta_store();
    }

    /**
     * Get the data store.
     *
     * @return TDstr<TData,TMeta<TData>>
     */
    protected function get_data_store() {
        $cname = $this->data_store;

        return static::$stores[ $this->name ] ??= $this->make( $cname )->initialize( $this );
    }

    public function add_hooks() {
        static::$hooked ??= \add_filter( 'woocommerce_data_stores', array( $this, 'prime_data_store' ), 10, 1 );
        \add_filter( "woocommerce_{$this->name}_data_store", fn() => $this->get_data_store(), 10, 1 );
    }

    public function prime_data_store( array $stores ): array {
        $to_add = \array_keys( static::$stores );
        return \array_merge(
            $stores,
            \array_combine( $to_add, \array_fill( 0, \count( $to_add ), null ) ),
        );
    }

    /**
     * Make an instance of a class.
     *
     * @template TObj of XWC_Data_Store_XT|XWC_Meta_Store
     * @param  class-string<TObj> $cname Class name.
     * @return TObj
     */
    private function make( string $cname ): object {
        return $this->container
            ? \xwp_app( $this->container )->make( $cname )
            : new $cname();
    }
}
