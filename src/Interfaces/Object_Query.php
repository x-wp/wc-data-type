<?php
/**
 * Object Query interface file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC\Interfaces;

/**
 * Interface describing an object query.
 */
interface Object_Query {
    /**
     * Get Objects matching the query vars.
     *
     * @return array<int, Data|int>|object
     */
    public function get_objects(): array|object;
}
