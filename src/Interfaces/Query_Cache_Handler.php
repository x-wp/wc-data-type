<?php
/**
 * Query_Cache interface file.
 *
 * @package WooCommerce Utils
 */

namespace XWC\Interfaces;

/**
 * Shared interface describing query cache classes.
 */
interface Query_Cache_Handler {
    /**
     * Is the query cacheable?
     *
     * @return bool
     */
    public function is_cacheable(): bool;

    /**
     * Is the query found in the cache?
     *
     * @return bool
     */
    public function found(): bool;

    /**
     * Get the query from the cache.
     *
     * @return array|null
     */
    public function get(): ?array;

    /**
     * Set the query in the cache.
     *
     * @return void
     */
    public function set();

    /**
     * Shutdown the cache.
     *
     * @return void
     */
    public function shutdown(): void;

    /**
     * Reset the cache.
     *
     * @return void
     */
    public function reset(): void;
}
