<?php
/**
 * Clause_Parser interface file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC\Interfaces;

/**
 * Interface for clause parsers.
 */
interface Clause_Parser {
    /**
     * Constructor.
     *
     * @param Query_Var_Handler $query Data query object.
     */
    public function __construct( Query_Var_Handler &$query );

    /**
     * Checks if the query has any queries.
     *
     * @return bool
     */
    public function has_queries(): bool;

    /**
     * Parse query vars needed for query generation.
     *
     * @param  array<string, mixed> $query_vars Query vars.
     */
    public function parse_query_vars( $query_vars ): static;

    /**
	 * Generates SQL clauses to be appended to a main query.
	 *
	 * @param  string $primary_table     Database table where the object being filtered is stored (eg wp_users).
	 * @param  string $primary_id_column ID column for the filtered object in $primary_table.
	 * @return array{join: string, where: string}
	 */
    public function get_sql( $primary_table = null, $primary_id_column = null );
}
