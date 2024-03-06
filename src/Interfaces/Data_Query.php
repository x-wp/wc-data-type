<?php
/**
 * Data_Query interface file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC\Interfaces;

/**
 * Interface for data queries.
 */
interface Data_Query {
    /**
     * Do we have objects for the loop?
     *
     * @return bool
     */
    public function have_objects(): bool;
}
