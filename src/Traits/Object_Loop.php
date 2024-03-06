<?php
/**
 * Object_Loop trait file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC\Traits;

use XWC\Data;

/**
 * Loop functionality for `Data_Query`
 */
trait Object_Loop {
    /**
     * The objects being iterated over.
     *
     * @var object|int|null
     */
    protected object|int|null $object = null;

    /**
     * Current object index in the loop.
     *
     * @var int
     */
    protected int $current = -1;

    /**
     * Are we in the loop?
     *
     * @var bool
     */
    protected bool $loop = false;

    /**
     * Are we before the loop?
     *
     * @var bool
     */
    protected bool $pre_loop = true;

    /**
     * Do we have objects for the loop?
     *
     * @return bool
     */
    public function have_objects(): bool {
        if ( $this->current + 1 < $this->count ) {
            return true;
        }

        if ( $this->current + 1 === $this->count && $this->count > 0 ) {
            // Documented in WP_Query.
            \do_action_ref_array( 'object_loop_end', array( &$this ) );

            $this->rewind_objects();
        } elseif ( 0 === $this->count ) {
            $this->pre_loop = false;
            // Documented in WP_Query.
            \do_action( 'object_loop_empty', $this );
        }

        $this->loop = false;

        return false;
    }

    /**
     * Setup the next object and iterate the current object index.
     *
     * @param  bool $to_object Should the object be setup.
     */
    public function the_object( bool $to_object = false ) {
        global $xwc_object;

        if ( ! $this->loop ) {
            $xwc_object;
            // phpcs:ignore
            // TODO - setup caching and stuff.
        }

        $this->loop     = true;
        $this->pre_loop = false;

        if ( -1 === $this->current ) {
            // Documented in WP_Query.
            \do_action_ref_array( 'object_loop_start', array( &$this ) );
        }

        $xwc_object = $this->next_object( $to_object );

        $this->setup_object( $xwc_object );
    }

    /**
     * Sets up the next object and iterates current object index.
     *
     * @param  bool $to_object Should the object be setup.
     */
    public function next_object( bool $to_object ) {
        ++$this->current;

        $this->object = $to_object
            ? $this->setup_object( $this->objects[ $this->current ] )
            : $this->objects[ $this->current ];

        return $this->object;
    }

    /**
     * Rewind the objects and reset the current object index.
     */
    public function rewind_objects() {
        $this->current = -1;

        if ( $this->count <= 0 ) {
            return;
        }

        $this->object = $this->objects[0];
    }

    /**
     * Sets up the object data.
     *
     * @param  Data|stdClass|int $data_obj Object to setup.
     * @return Data|false
     */
    public function setup_object( object|int $data_obj ): Data|false {
        if ( $data_obj instanceof Data ) {
            return $data_obj;
        }

        try {
            $classname = $this->vars->classname;

            $type = \is_object( $data_obj ) ? 'row' : 'db';

            return new $classname( $data_obj, $type );
        } catch ( \Throwable ) {
            return false;
        }
    }

    /**
     * Resets the object data.
     */
    public function reset_object_data() {
        if ( ! $this->object ) {
            return;
        }

        $GLOBALS['xwc_object'] = $this->object;

        $this->setup_object( $this->object );
    }
}
