<?php

namespace XWC\Data;

use XWC\Data\Decorators\Model;
use XWC_Data;
use XWC_Data_Store_XT;
use XWC_Meta_Store;
use XWC_Object_Factory;
use XWP\Helper\Classes\Reflection;
use XWP\Helper\Traits\Singleton;

/**
 * Entity manager.
 *
 * @template TData of XWC_Data
 * @template TDstr of XWC_Data_Store_XT
 * @template TFact of XWC_Object_Factory
 * @template TMeta of XWC_Meta_Store
 *
 * @method static Entity<TData, TDstr, TFact, TMeta>|WP_Error                                               register(string $classname)      Register a data type.
 * @method static array<string, Entity<TData, TDstr, TFact, TMeta>>|Entity<TData, TDstr, TFact, TMeta>|null get_entity(?string $name = null) Get all registered entities or a specific entity.
 */
final class Entity_Manager {
    use Singleton;

    /**
     * Registered entities.
     *
     * @var array<string, Entity<TData, TDstr, TFact, TMeta>>
     *
     * @phan-var array<string, Entity<TData, TDstr, TFact, TMeta>>
     */
    private array $entities = array();

    /**
     * Call dynamic methods on the singleton instance.
     *
     * @param  string $name     Method name.
     * @param  mixed  $arguments Method arguments.
     */
    public static function __callStatic( $name, $arguments ) {
        return self::instance()->$name( ...$arguments );
    }

    public function __call( string $name, $arguments ) {
        return match ( $name ) {
            'get_models' => null,
            default      => $this->$name( ...$arguments ),
        };
    }

    protected function __construct() {
        global $xwc_entities;

        $xwc_entities = $this;
    }

    /**
     * Get all registered entities or a specific entity.
     *
     * @param  string|null $name Entity name.
     * @return array<string, Entity<TData, TDstr, TFact, TMeta>>|Entity<TData, TDstr, TFact, TMeta>|null
     */
    protected function get_entity( ?string $name = null ): array|Entity|null {
        return $name ? ( $this->entities[ $name ] ?? null ) : $this->entities;
    }

    /**
     * Register a data type model.
     *
     * @param  class-string<TData> $classname Data type class name.
     * @return Entity<TData, TDstr, TFact, TMeta>|\WP_Error
     */
    protected function register( string $classname ): Entity|\WP_Error {
        try {
            if ( ! \class_exists( $classname ) ) {
                throw new \WC_Data_Exception( 'invalid_data_type', 'Invalid entity classname' );
            }

            $models = $this->get_models( $classname );
            $entity = new Entity( ...$models );

            $this->entities[ $entity->name ] = $entity;

            $entity->add_hooks();

            return $entity;

        } catch ( \WC_Data_Exception $e ) {
            return new \WP_Error( $e->getCode(), $e->getMessage(), $e->getErrorData() );
        }
    }

    /**
     * Get entity definitions for a class.
     *
     * @param  class-string<TData> $target Target classname.
     * @return array<Model<TData, TDstr, TFact, TMeta>>>
     */
    protected function get_models( string $target ): array {
        $defs  = array();
        $chain = Reflection::get_inheritance_chain( $target, true );

        foreach ( $chain as $classname ) {
            $defs[] = Reflection::get_decorator( $classname, Model::class )?->set_model( $classname );
        }

        return \array_values( \array_filter( \array_reverse( $defs ) ) );
    }
}
