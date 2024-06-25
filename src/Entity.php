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
 * @template TData of XWC_Data
 * @template TRepo of XWC_Data_Store_XT
 * @template Meta of XWC_Meta_Store
 * @template Factory of XWC_Object_Factory
 *
 * Base definition.
 *
 * @property-read string $name       Data object name.
 * @property-read string $table      Database table name.
 * @property-read string $id_field   ID field name. Default 'id'.
 * @property-read array  $core_props Core properties.
 * @property-read array  $meta_props Meta properties. Optional.
 *
 * Stores and classnames.
 *
 * @property-read class-string<TData> $model      Model class name.
 * @property-read Factory<TData>      $factory    Object factory.
 * @property-read TRepo<TData>        $data_store Data store class name.
 * @property-read Meta<TData>|null    $meta_store Meta store class name. Optional.
 *
 * Custom data.
 *
 * @property-read array<string, mixed>  $core_data          Core data.
 * @property-read array<string, mixed>  $data               Data.
 * @property-read array<string, string> $prop_types         Property types.
 * @property-read array<string, bool>   $unique_data        Unique data.
 * @property-read array<string, string> $cols_to_props      Column names to property names.
 * @property-read array<string, string> $meta_to_props      Meta keys to property names.
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
        'core_data',
        'data',
        'prop_types',
        'unique_data',
        'cols_to_props',
        'meta_to_props',
	);

    /**
     * Object factories.
     *
     * @var array<string, Factory<TData>>
     */
    protected static array $factories = array();

    /**
     * Data stores.
     *
     * @var array<string, TRepo<TData>>
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
    protected string $data_store;
    protected string $factory;
    protected array $core_props;
    protected string $id_field;
    protected ?string $meta_store;
    protected ?array $meta_props;

    protected array $args = array(
        'core' => null,
        'ctp'  => null,
        'data' => null,
        'fct'  => null,
        'imk'  => null,
        'mtp'  => null,
        'pt'   => null,
        'unq'  => null,
    );

    public function __construct(
        Model ...$defs,
    ) {
        $vars = \array_keys( \get_class_vars( $this::class ) );
        $vars = \array_diff( $vars, array( 'args', 'factories', 'stores', 'hooked' ) );

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
        return $this->args['core'] ??= \wp_list_pluck( $this->core_props, 'default' );
    }

    protected function get_data(): array {
        return $this->args['data'] ??= \wp_list_pluck( $this->meta_props, 'default' );
    }

    protected function get_prop_types(): array {
        return $this->args['pt'] ??= \array_merge(
            \wp_list_pluck( $this->core_props, 'type' ),
            \wp_list_pluck( $this->meta_props, 'type' ),
        );
    }

    protected function get_unique_data(): array {
        return $this->args['unq'] ??= \array_keys(
            \wp_list_filter( $this->core_props, array( 'unique' => true ) ),
        );
    }

    protected function get_meta_to_props(): array {
        return $this->args['mtp'] ??= \array_flip( \wp_list_pluck( $this->meta_props, 'name' ) );
    }

    protected function get_cols_to_props(): array {
        return $this->args['ctp'] ??= \wp_list_pluck( $this->core_props, 'name' );
    }

    protected function get_factory(): XWC_Object_Factory {
        return static::$factories[ $this->name ] ??= $this->factory::instance();
    }

    protected function get_meta_store(): ?XWC_Meta_Store {
        if ( ! $this->meta_store ) {
            return null;
        }

        $cname = $this->meta_store;

        return new $cname();
    }

    /**
     * Get the data store.
     *
     * @return TRepo<TData>
     *
     * @phan-return TRepo<TData>
     */
    protected function get_data_store(): XWC_Data_Store_XT {
        $cname = $this->data_store;

        return static::$stores[ $this->name ] ??= new $cname( $this );
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
}
