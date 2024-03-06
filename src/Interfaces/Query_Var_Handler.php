<?php
/**
 * Query_Var_Handler interface file.
 *
 * @package eXtended WooCommerce
 * @subpackage Interfaces
 */

namespace XWC\Interfaces;

/**
 * Interface
 */
interface Query_Var_Handler extends \ArrayAccess, \Iterator, \Countable, \JsonSerializable {
    /**
     * Get the query vars
     *
     * @return array
     */
    public function get(): array;

    /**
     * Set the query vars.
     *
     * @param  array $qv Query vars.
     */
    public function set( array $qv ): void;

    /**
     * Reset the query vars.
     */
    public function reset(): void;

    /**
     * Fill in the query vars with default values.
     */
    public function fill(): void;

    /**
     * Perform a sanity check on the query vars.
     *
     * Sets required query vars to the default values.
     *
     * @return void
     */
    public function sanity_check(): void;

    /**
     * Get supported features for the entity.
     *
     * @return array<int, string>
     */
    public function get_features(): array;

    /**
     * Check if the feature is supported.
     *
     * @param  string $what Feature name.
     * @return bool
     */
    public function supports( string $what ): bool;

    /**
     * Checks if the current query needs a parser.
     *
     * @param  string $which Feature name.
     * @return bool
     */
    public function needs_parser( string $which ): bool;

    /**
     * Get default query vars.
     *
     * @return array<string, mixed>
     */
    public function get_defaults(): array;

    /**
     * Get the variables for a specific feature
     *
     * @param  string $feature Feature name.
     * @param  bool   $real    If true, will return the "sane" defaults, if not - will not include standard keys.
     * @return array
     */
    public function get_feature_vars( string $feature, bool $real = false ): array;
}
