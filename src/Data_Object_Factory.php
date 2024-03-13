<?php
/**
 * Data_Object_Factory class file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC;

use Oblak\WP\Traits\Singleton;

/**
 * Data object factory class
 *
 * Provides a consistent way to get data objects.
 *
 * By default you can use the get_object methods
 *
 * @method static string get_object_classname(int $id, string $type) Get the object classname.
 */
class Data_Object_Factory {
    use Singleton;

    /**
     * Array of data types and default class names.
     *
     * @var array<string, class-string>
     */
    protected array $data_types = array();

    /**
     * Constructor
     */
    protected function __construct() {
        $this->init();
    }

    /**
     * Initialize the data types.
     */
    protected function init() {
        global $xwc_data_types;

        foreach ( $xwc_data_types->get_data_type() as $type => $dto ) {
            $this->data_types[ $type ] = $dto->classname;
        }
    }

    /**
     * Get a data object
     *
     * @param  mixed  $id   Object ID.
     * @param  string $type Object type.
     * @return Data|false
     */
    public function get_object( mixed $id, string $type = '' ): Data|false {
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
            default             => false,
            \is_numeric( $id )     => (int) $id,
            $obj instanceof Data => $obj->get_id(),
            $id instanceof Data => $id->get_id(),
        };
    }

    /**
     * Get the object class name.
     *
     * @param  int    $id   Object ID.
     * @param  string $type Object type.
     * @return string|false
     */
    protected function get_object_classname( int $id, string $type ): string|false {
        // Documented in WooCommerce.
        $classname = \apply_filters( "xwc_{$type}_class", $this->data_types[ $type ] ?? false, $id );

        if ( ! $classname || ! \class_exists( $classname ) ) {
            return false;
        }

        return $classname;
    }

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
}
