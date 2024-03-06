<?php //phpcs:disable Squiz.Commenting
/**
 * Column_Parser class file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC\Query;

use XWC\Interfaces\Clause_Parser;
use XWC\Interfaces\Query_Var_Handler;
use XWC\Traits\Clause_Processor;

/**
 * Parses the column query.
 */
class Column_Parser implements Clause_Parser {
    use Clause_Processor;

    /**
     * Parsed queries.
     *
     * @var array
     */
    protected array $queries = array();

    /**
     * Primary table.
     *
     * @var string
     */
    protected string $primary_table;

    protected string $primary_id_column;

    /**
     * Table columns.
     *
     * @var array
     */
    protected array $table_columns;

    protected array $search_columns;

    /**
     * Column vars.
     *
     * @var array
     */
    protected array $column_vars;

    protected bool $has_search = false;

    public function __construct( Query_Var_Handler &$qv ) {
        $this->column_vars    = $qv->column_vars;
        $this->table_columns  = \array_keys( $this->column_vars );
        $this->search_columns = $qv->search_columns;
    }

    public function has_queries(): bool {
        return \count( $this->queries ) > 0;
    }

    /**
     * Parse query vars needed for query generation.
     *
     * @param  Var_Handler $qv Query vars.
     * @return static
     */
    public function parse_query_vars( $qv ): static {
        $this->has_search = ( isset( $qv['s'] ) || isset( $qv['sentence'] ) ) && $this->search_columns;

        $vars = $this->pick_and_clean( $qv->get() );

        $this->queries = \count( $vars ) > 0 ? $this->sanitize_queries( $vars ) : array();

        return $this;
    }

    public function pick_and_clean( array $qv ): array {
        $vars    = $this->normalize_vars( $qv );
        $queries = $qv['column_query'] ?? array();

        if ( $this->is_query( $queries ) ) {
            $queries = array( $queries );
        }

        foreach ( $vars as $column => $value ) {
            $queries[] = array(
                'column' => $column,
                'value'  => $value,
            );
        }

        return $queries;
    }

    public function get_sql( $table = null, $id_col = null ) {
        $this->primary_table     = $table;
        $this->primary_id_column = $id_col;

        $sql = $this->get_sql_clauses();

        if ( '' !== $sql['where'] ) {
            $sql['where'] = ' AND ' . $sql['where'];
        }

        return $sql;
    }

    protected function normalize_vars( $qv ): array {
        $ids        = \wp_parse_id_list(
            \array_merge(
                ...\array_map(
                    'wc_string_to_array',
                    \wp_array_slice_assoc( $qv, array( 'id', 'ID', 'oid', 'id__in' ) ),
                ),
            ),
        );
        $normalized = array( '{{ID_FIELD}}' => $ids );

        foreach ( $this->column_vars as $var => $column ) {
            if ( ! isset( $qv[ $var ] ) ) {
                continue;
            }

            $normalized[ $column ] = $qv[ $var ];
        }

        return $normalized;
    }
}
