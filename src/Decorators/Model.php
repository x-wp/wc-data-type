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
     * Model class name.
     *
     * @var class-string<TData>
     */
    public string $model;

    public string $table;
    public string $data_store;
    public string $factory;
    public array $core_props;
    public string $id_field;
    public ?string $meta_store;
    public array $meta_props;

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
     * Undocumented function
     *
     * @param  string      $name
     * @param  string      $table
     * @param  array       $core_props
     * @param  array       $meta_props
     * @param  string      $id_field
     * @param  class-string<TDstr>|null $data_store
     * @param  class-string<TFact>|null $factory
     * @param  class-string<TMeta>|null $meta_store
     */
    public function __construct(
        string $name,
        string $table,
        array $core_props,
        array $meta_props = array(),
        string $id_field = 'id',
        ?string $data_store = null,
        ?string $factory = null,
        ?string $meta_store = null,
    ) {
        $this->name = $name;
        $this->scaffold(
            \compact(
                'table',
                'data_store',
                'factory',
                'core_props',
                'id_field',
                'meta_store',
                'meta_props',
            ),
        );
    }

    protected function get_definers(): array {
        return array(
            'table'      => 'set_table',
            'data_store' => 'set_data_store',
            'factory'    => 'set_factory',
            'core_props' => 'set_core_props',
            'meta_props' => 'set_meta_props',
            'meta_store' => 'set_meta_store',
            'id_field'   => 'set_id_field',
        );
    }

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
     * @return class-string<TDstr>
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
     * @return class-string<TFact>
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

    protected function set_core_props( array $props ): array {
        $default = static fn( $n ) => array(
			'default' => '',
			'name'    => $n,
			'type'    => 'string',
			'unique'  => false,
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
     * Set the meta store class name.
     *
     * @param  class-string<TMeta>|null $store Meta store class name.
     * @return class-string<TMeta>|null
     */
    protected function set_meta_store( ?string $store ): ?string {
        if ( \is_null( $store ) ) {
            return null;
        }

        if ( ! \class_exists( $store ) ) {
            throw new \InvalidArgumentException( \esc_html( "Meta store class $store does not exist." ) );
        }

        return $store;
    }

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

    protected function set_id_field( string $field ): string {
        return $field;
    }
}
