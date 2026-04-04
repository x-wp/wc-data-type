<?php
/**
 * XWC_Stateful_Data interface file.
 *
 * @package eXtended WooCommerce
 * @subpackage Data
 */


/**
 * Defines a stateful data object.
 */
interface XWC_Stateful_Data {
    /**
     * Check if the object has a specific status.
     *
     * @param  string ...$status  Status.
     * @return bool
     */
    public function has_status( string ...$status ): bool;

    /**
     * Set the status of the object.
     *
     * @param  string $new_status New status to set.
     * @param  string $note       Note to add to the transition.
     * @param  bool   $manual     Whether the status change is manual or not.
     * @return array{from: string, to: string}
     */
    public function set_status( string $new_status, string $note = '', bool $manual = false ): array;

    /**
     * Get the current status of the object.
     *
     * @param  string $context Context in which the value is being retrieved.
     * @return string
     */
    public function get_status( string $context = 'view' ): string;

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
    public function update_status( string $new_status, string $note = '', bool $manual = false ): bool;
}
