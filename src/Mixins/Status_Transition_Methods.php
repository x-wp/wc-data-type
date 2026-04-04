<?php
/**
 * Transition_Methods trait file.
 *
 * @package eXtended WooCommerce
 * @subpackage Data
 */

namespace XWC\Data\Mixins;

use XWC_Stateful_Data;

trait Status_Transition_Methods {
    use Status_Prop_Methods {
        set_status as set_status_base;
    }

    /**
     * Stores data about status changes so relevant hooks can be fired.
     *
     * @var false|array{
     *   from: string,
     *   to: string,
     *   note: string,
     *   manual: bool
     * }
     */
    protected bool|array $transition = false;

    public function save() {
        parent::save();

        return $this->status_transition()->get_id();
    }

    /**
     * Check if the object has a specific status.
     *
     * @param  string ...$status  Status.
     * @return bool
     */
    public function has_status( string ...$status ): bool {
        return \in_array( $this->get_status(), $status, true );
    }

    /**
     * Set the status of the object.
     *
     * @param  string $new_status New status to set.
     * @param  string $note       Note to add to the transition.
     * @param  bool   $manual     Whether the status change is manual or not.
     * @return array{from: string, to: string}
     */
    public function set_status( string $new_status, string $note = '', bool $manual = false ): array {
        [ 'from' => $from, 'to' => $to ] = $this->set_status_base( $new_status );

        if ( ! $this->object_read || '' === $from || $from === $to ) {
            return array(
                'from' => $from,
                'to'   => $to,
            );
        }

        $from = $this->transition['from'] ?? $from;

        $this->transition = array(
            'from'   => $from,
            'manual' => $manual,
            'note'   => $note,
            'to'     => $to,
        );

        if ( $manual ) {
            $tag = $this->get_tag_base( 'edit', 'status' );

            /**
             * Fires when the status of an object is manually changed.
             *
             * @param int    $id Object ID.
             * @param string $to New status.
             *
             * @since 1.0.0
             */
            \do_action( $tag, $this->get_id(), $to );
        }

        return array(
            'from' => $from,
            'to'   => $to,
        );
    }

    /**
     * Update the status of the object.
     *
     * Same as `set_status`, but also saves the object.
     *
     * @param  string $new_status New status to set.
     * @param  string $note       Note to add to the transition.
     * @param  bool   $manual     Whether the status change is manual or not.
     * @return bool
     */
    public function update_status( string $new_status, string $note = '', bool $manual = false ): bool {
        if ( ! $this->can_update_status() ) {
            return false;
        }

        try {
            $this->set_status( $new_status, $note, $manual );
            $this->save();
        } catch ( \Throwable ) {
            return false;
        }

        return true;
    }

    protected function can_update_status(): bool {
        return $this->get_id() > 0;
    }

    /**
     * Get the state transition for a specific property.
     *
     * @return false|array{
     *   from: string,
     *   to: string,
     *   note: string,
     *   manual: bool
     * }
     */
    protected function get_transition(): bool|array {
        $transition = $this->transition;

        $this->transition = false;

        return $transition;
    }

    protected function status_transition(): static {
        $transition = $this->get_transition();

        if ( false === $transition ) {
            return $this;
        }

        $base = $this->get_tag_base();

        try {

            [ 'from' => $from, 'to' => $to ] = $transition;

            /**
             * Fires for specific status transition.
             *
             * @param int                  $id Object ID.
             * @param XWC_Stateful_Data    $object Object instance.
             * @param array<string,mixed>  $transition Transition data.
             *
             * @since 1.0.0
             */
            \do_action( "{$base}_{$to}", $this->get_id(), $this, $transition );

            /**
             * Fires for status transition from one status to another.
             *
             * @param int                  $id Object ID.
             * @param XWC_Stateful_Data    $object Object instance.
             *
             * @since 1.0.0
             */
            \do_action( "{$base}_{$from}_to_{$to}", $this->get_id(), $this );

            /**
             * Fires for status change.
             *
             * @param int                  $id Object ID.
             * @param string               $from Previous status.
             * @param string               $to New status.
             * @param XWC_Stateful_Data    $object Object instance.
             *
             * @since 1.0.0
             */
            \do_action( "{$base}_changed", $this->get_id(), $from, $to, $this );

        } catch ( \Exception $e ) {
            if ( \function_exists( 'wc_get_logger' ) ) {
                $logger = \wc_get_logger();

                $logger->error(
                    \sprintf(
                        'Status transition of %s #%d errored!',
                        $this->object_type,
                        $this->get_id(),
                    ),
                    array(
                        $this->object_type => $this,
                        'error'            => $e,
                    ),
                );
            }
        } finally {
            return $this;
        }
    }

    protected function get_tag_base( string ...$tags ): string {
        $tags = $tags ?: array( 'status' );
        \array_unshift( $tags, 'xwc', $this->object_type );

        return \implode( '_', $tags );
    }
}
