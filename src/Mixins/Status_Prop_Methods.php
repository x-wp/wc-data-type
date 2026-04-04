<?php
/**
 * Status_Prop_Methods trait file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC\Data\Mixins;

trait Status_Prop_Methods {
    /**
     * Get the valid statuses for the object.
     *
     * @return array<int,string>
     */
    abstract protected function get_valid_statuses(): array;

    abstract protected function get_default_status(): string;

    abstract protected function get_status_prefix(): string;

    /**
     * Set the object status.
     *
     * @param  string $to New status.
     * @return array{from: string, to: string}
     */
    public function set_status( string $to ): array {
        $from = $this->get_status();
        $to   = $this->strip_object_status( $to );

        if ( ! $this->object_read ) {
            $this->set_prop( 'status', $to );

            return \compact( 'from', 'to' );
        }

        if ( ! $this->is_valid_status( $to ) ) {
            $to = $this->get_default_status();
        }

        if ( 'draft' !== $from && ! $this->is_valid_status( $from ) ) {
            $from = $this->get_default_status();
        }

        $this->set_prop( 'status', $to );

        return \compact( 'from', 'to' );
    }

    /**
     * Get the invoice status.
     *
     * @param  string $context Context.
     * @return string
     */
    public function get_status( string $context = 'view' ): string {
        $status = $this->get_prop( 'status', $context );

        if ( '' === $status && 'view' === $context ) {
            $status = $this->get_default_status();
        }

        return $status;
    }

    /**
     * Get invoice status prop for the database.
     *
     * @param  string $status Status to format.
     * @return string
     */
    protected function get_object_status_prop( string $status ) {
        if ( '' === $status ) {
            $status = $this->get_default_status();
        }

        $status = $this->format_object_status( $status );

        return $status;
    }

    protected function strip_object_status( string $status ): string {
        $prefix = $this->get_status_prefix();

        return \str_starts_with( $status, $prefix )
            ? \substr( $status, \strlen( $prefix ) )
            : $status;
    }

    /**
     * Format the object status with a prefix.
     *
     * @param  string $status Status to format.
     * @return string
     */
    protected function format_object_status( string $status ): string {
        $prefix = $this->get_status_prefix();

        return ! \str_starts_with( $status, $prefix )
            ? $prefix . $status
            : $status;
    }

    /**
     * Check if a status is valid.
     *
     * @param  string $status Status to check.
     * @return bool
     */
    protected function is_valid_status( string $status ): bool {
        return \in_array( $this->format_object_status( $status ), $this->get_valid_statuses(), true );
    }
}
