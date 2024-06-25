<?php

use XWC\Data\Entity_Manager;
use XWP\Helper\Traits\Singleton_Ex;

/**
 * Data object factory class
 *
 * Provides a consistent way to get data objects.
 *
 * By default you can use the get_object methods
 *
 * @template T of XWC_Data
 *
 * @method static class-string<T>|false get_object_classname(int $id, string $type) Get the object class name.
 * @method static int|false             get_object_id(mixed $id, string $type)      Get the object ID.
 *
 * Object factory.
 */
class XWC_Object_Factory {
    use Singleton_Ex;

    /**
     * Array of entity names with their class names.
     *
     * @var array<string, class-string<T>>
     */
    protected array $models = array();

    /**
     * Handles dynamic method calls for getting object data.
     *
     * @param  string $name Method name.
     * @param  mixed  $args Method arguments.
     *
     * @return mixed
     */
    public function __call( string $name, $args ) {
        \preg_match( '/^get_(.*?)(?:_id|_classname)?$/', $name, $matches );

        $type   = $matches[1];
        $method = \str_replace( $type, 'object', $name );

        if ( ! \method_exists( $this, $method ) ) {
            return false;
        }

        $args[] = $type;

        return $this->{"$method"}( ...$args );
    }

    /**
     * Handles dynamic static method calls for getting object data
     *
     * @param  string $name Method name.
     * @param  mixed  $args Method arguments.
     *
     * @return mixed
     */
    public static function __callStatic( $name, $args ) {
        return static::instance()->__call( $name, $args );
    }

    /**
     * Constructor
     */
    protected function __construct() {
        $this->init();
    }

    /**
     *
     * Initialize the data types.
     *
     * @global Entity_Manager $xwc_entities
     */
    protected function init() {
        /**
         * Global entity manager.
         *
         * @var Entity_Manager $xwc_entities
         */
        global $xwc_entities;

        foreach ( $xwc_entities->get_entity() as $type => $dto ) {
            $this->models[ $type ] = $dto->model;
        }
    }

    /**
     * Get a data object
     *
     * @param  mixed  $id   Object ID.
     * @param  string $type Object type.
     * @return T|false
     */
    public function get_object( mixed $id, string $type = '' ): XWC_Data|bool {
        $id = $this->{"get_{$type}_id"}( $id );

        if ( ! $id ) {
            return false;
        }

        $classname = $this->{"get_{$type}_classname"}( $id );

        try {
            return new $classname( $id );
        } catch ( \Exception ) {
            return false;
        }
    }

    /**
     * Get the object ID.
     *
     * @param  mixed  $id   Object ID.
     * @param  string $type Object type.
     * @return int|false
     */
    protected function get_object_id( mixed $id, string $type ): int|false {
        $obj = $GLOBALS[ $type ] ?? null;

        return match ( true ) {
            default                  => false,
            \is_numeric( $id )       => (int) $id,
            $obj instanceof XWC_Data => $obj->get_id(),
            $id instanceof XWC_Data  => $id->get_id(),
        };
    }

    /**
     * Get the object class name.
     *
     * @param  int    $id   Object ID.
     * @param  string $type Object type.
     * @return class-string<T>|false
     */
    protected function get_object_classname( int $id, string $type ): string|false {
        // Documented in WooCommerce.
        $classname = \apply_filters( "xwc_{$type}_class", $this->models[ $type ] ?? false, $id );

        if ( ! $classname || ! \class_exists( $classname ) ) {
            return false;
        }

        return $classname;
    }
}
