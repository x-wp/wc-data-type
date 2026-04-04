<?php

use XWC\Data\Entity;
use XWC\Data\Entity_Manager;
use XWC\Data\Interfaces\Object_Factory;
use XWP\Helper\Traits\Singleton_Ex;

/**
 * Data object factory class
 *
 * Provides a consistent way to get data objects.
 *
 * @template T of XWC_Data
 * @implements Object_Factory<T>
 */
class XWC_Object_Factory implements Object_Factory {
    /**
     * Entity type.
     *
     * @var string
     */
    protected string $type;

    /**
     * Data object class name.
     *
     * @var class-string<T>
     */
    protected string $classname;

    /**
     * Initialize the object factory with an entity.
     *
     * @template D of XWC_Data_Store_XT<T>
     * @template M of XWC_Meta_Store<T>
     *
     * @param  Entity<T,D,static,M> $e Entity instance.
     * @return static
     */
    public function initialize( Entity $e ): static {
        $this->type      = $e->name;
        $this->classname = $e->model;

        return $this;
    }

    public function make_object( mixed $id ): XWC_Data {
        $obj = $this->get_object( $id );

        if ( $obj ) {
            return $obj;
        }

        $classname = $this->get_classname( 0 );

        if ( ! $classname ) {
            throw new \RuntimeException(
                \esc_html( "Cannot resolve a concrete class for entity type '{$this->type}'." ),
            );
        }

        return new $classname( 0 );
    }

    public function get_object( mixed $id ): ?XWC_Data {
        $id = $this->get_id( $id );

        if ( ! $id ) {
            return null;
        }

        $classname = $this->get_classname( $id );

        try {
            return new $classname( $id );
        } catch ( \Exception ) {
            return null;
        }
    }

    public function get_id( mixed $id ): int|bool {
        $obj = $GLOBALS[ $this->type ] ?? null;

        // @phpstan-ignore return.type
        return match ( true ) {
            \is_numeric( $id )       => (int) $id,
            $id instanceof XWC_Data  => $id->get_id(),
            $obj instanceof XWC_Data => $obj->get_id(),
            default                  => false,
        };
    }

    public function get_classname( int $id ): bool|string {
        /**
         * Filters the class name of a data object.
         *
         * @var class-string<T>|false $classname
         */
        // Documented in WooCommerce.
        $classname = \apply_filters( "xwc_{$this->type}_class", $this->classname, $id );

        if ( ! $classname || ! \class_exists( $classname ) ) {
            return false;
        }

        return $classname;
    }
}
