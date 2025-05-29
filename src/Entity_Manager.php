<?php

namespace XWC\Data;

use Psr\Container\ContainerInterface;
use WP_Error;
use XWC\Data\Decorators\Model;
use XWC_Data;
use XWC_Data_Store_XT;
use XWC_Meta_Store;
use XWC_Object_Factory;
use XWP\Helper\Classes\Reflection;
use XWP\Helper\Traits\Singleton;

/**
 * Entity manager.
 */
final class Entity_Manager {
    use Singleton;

    /**
     * Registered entities.
     *
     * @var array<string,Entity<XWC_Data,XWC_Data_Store_XT<XWC_Data>,XWC_Object_Factory<XWC_Data>,XWC_Meta_Store<XWC_Data>>>
     */
    private array $entities = array();

    /**
     * Register a data type model.
     *
     * @template TData of XWC_Data
     * @param  class-string<TData> $classname Data type class name.
     * @param  ?ContainerInterface $container Optional. The XWP-DI container instance.
     * @return Entity<TData,XWC_Data_Store_XT<TData>,XWC_Object_Factory<TData>,XWC_Meta_Store<TData>>|WP_Error
     */
    public static function register( string $classname, ?ContainerInterface $container = null ): Entity|\WP_Error {
        return self::instance()->do_register( $classname, $container );
    }

    /**
     * Get all registered entities or a specific entity.
     *
     * @param  string|null       $name Entity name. If null, returns all registered entities.
     * @return (  $name is null ? array<Entity<XWC_Data,XWC_Data_Store_XT<XWC_Data>,XWC_Object_Factory<XWC_Data>,XWC_Meta_Store<XWC_Data>>> : Entity<XWC_Data,XWC_Data_Store_XT<XWC_Data>,XWC_Object_Factory<XWC_Data>,XWC_Meta_Store<XWC_Data>>|null )
     */
    public static function get_entity( ?string $name = null ): array|Entity|null {
        return self::instance()->do_get_entity( $name );
    }

    protected function __construct() {
        global $xwc_entities;

        $xwc_entities = $this;
    }

    /**
     * Dynamic method call handler.
     *
     * @param  string       $name Method name.
     * @param  array<mixed> $args Method arguments.
     * @return mixed
     */
    public function __call( string $name, array $args ): mixed {
        return match ( $name ) {
            'get_models' => null,
            default      => $this->$name( ...$args ),
        };
    }

    /**
     * Get all registered entities or a specific entity.
     *
     * @param  string|null $name Entity name.
     * @return null|Entity<XWC_Data,XWC_Data_Store_XT<XWC_Data>,XWC_Object_Factory<XWC_Data>,XWC_Meta_Store<XWC_Data>>|array<string,mixed>
     */
    protected function do_get_entity( ?string $name = null ): array|Entity|null {
        return $name ? ( $this->entities[ $name ] ?? null ) : $this->entities;
    }

    /**
     * Register a data type model.
     *
     * @template TData of XWC_Data
     * @param  class-string<TData> $classname Data type class name.
     * @param  ?ContainerInterface $container Optional. The XWP-DI container instance.
     * @return Entity<TData,XWC_Data_Store_XT<TData>,XWC_Object_Factory<TData>,XWC_Meta_Store<TData>>|WP_Error
     */
    protected function do_register( string $classname, ?ContainerInterface $container = null ): Entity|\WP_Error {
        try {
            if ( ! \class_exists( $classname ) ) {
                throw new \WC_Data_Exception( 'invalid_data_type', 'Invalid entity classname' );
            }

            $models = $this->get_models( $classname );
            $entity = new Entity( ...$models );

            $this->entities[ $entity->name ] = null !== $container
                ? $entity->with_container( $container )
                : $entity;

            $entity->add_hooks();

            return $entity;

        } catch ( \WC_Data_Exception $e ) {
            return new \WP_Error( $e->getCode(), $e->getMessage(), $e->getErrorData() );
        }
    }

    /**
     * Get entity definitions for a class.
     *
     * @template TData of XWC_Data
     * @param  class-string<TData> $target Target classname.
     * @return array<Model<TData,XWC_Data_Store_XT<TData>,XWC_Object_Factory<TData>,XWC_Meta_Store<TData>>>
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
