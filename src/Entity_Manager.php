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
 * @template T of XWC_Data
 * @template R of XWC_Data_Store_XT
 * @template M of XWC_Meta_Store
 * @template F of XWC_Object_Factory
 *
 * @phpstan-type TheEntity Entity<T,R,M,F>
 *
 * @method static Entity<T>|WP_Error                      register(string $classname)      Register a data type.
 * @method static array<string, TheEntity>|TheEntity|null get_entity(?string $name = null) Get all registered entities or a specific entity.
 */
final class Entity_Manager {
    use Singleton;

    /**
     * Registered entities.
     *
     * @var array<string, Entity<T,R,M,F>>
     *
     * @phan-var array<string, TheEntity>
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
     * @return array<string, Entity<T>>|Entity<T>|null
     *
     * @phan-return array<string, TheEntity>|TheEntity|null
     */
    protected function get_entity( ?string $name = null ): array|Entity|null {
        return $name ? ( $this->entities[ $name ] ?? null ) : $this->entities;
    }

    /**
     * Register a data type model.
     *
     * @param  class-string<T> $classname Data type class name.
     * @return Entity<T>|\WP_Error
     *
     * @phpstan-return TheEntity|\WP_Error
     */
    protected function register( string $classname ): Entity|\WP_Error {
        try {
            if ( ! \class_exists( $classname ) ) {
                throw new \WC_Data_Exception( 'invalid_data_type', 'Invalid entity classname' );
            }

            $models = $this->get_models( $classname );
            $entity = new Entity( ...$models );

            // @phpstan-ignore-next-line
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
     * @param  class-string<T> $target Target classname.
     * @return array<Model>
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
