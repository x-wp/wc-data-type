<?php

class XWC_Object_Query {
    /**
     * Array of SQL query clauses.
     *
     * @var array
     */
    public array $clauses = array(
        'distinct' => '',
        'fields'   => '',
        'groupby'  => '',
        'join'     => '',
        'limits'   => '',
        'orderby'  => '',
        'where'    => '',
    );

    public string $sql = '';

    public string $sql_count = '';

    /**
     * The objects being iterated over.
     *
     * @var array<int, \stdClass|int>
     */
    public ?array $objects = null;

    /**
     * The number of objects in the query result.
     *
     * @var int|null
     */
    public ?int $count = null;

    /**
     * The total number of objects found in the query result.
     *
     * @var int|null
     */
    public ?int $total = null;

    /**
     * The number of pages in the query result.
     *
     * @var int|null
     */
    public ?int $pages = null;

    protected array $term_props = array();


    public array $vars = array();

    public function __construct(
        protected string $table,
        protected string $id_field,
        ...$query,
    ) {
		$this->parse( $query );
		$this->reset();
        $this->get_objects();
    }

    public function query( ?array $query = null ) {
        if ( $query ) {
            $this->parse( $query );
            $this->reset();
        }

        return $this->get_objects();
    }

    public function count( ?array $query = null ): int {
        if ( $query ) {
            $query['nopaging']    = true;
            $query['count_found'] = true;
            $query['fields']      = 'ids';

            $this->query( $query );
            $this->reset();
        }

        return $this->total;
    }

    protected function parse( array $q ) {
        $d = array(
            'count_found' => true,
            'fields'      => 'ids',
            'nopaging'    => false,
            'order'       => 'DESC',
            'orderby'     => $this->id_field,
            'page'        => 1,
        );

        $q = \wp_parse_args( $q, $d );

        if ( (int) $q['page'] <= 0 ) {
            $q['page'] = 1;
        }

        if ( (int) $q['per_page'] <= 0 ) {
            $q['per_page'] = false;
            $q['nopaging'] = true;
        }

        $this->vars = $q;
    }

    protected function reset() {
        $this->clauses   = \array_map( '__return_empty_string', $this->clauses );
        $this->sql       = '';
        $this->sql_count = '';
    }

    protected function get_objects(): array {
        $c = &$this->clauses;
        $q = &$this->vars;

        $this->init_query( $c, $q );

        return $this->run_query( $c, $q );
    }

    protected function init_query( &$c, &$q ) {
        $this->init_fields( $c, $q['fields'] );
        $this->init_columns( $c, $q );
        $this->init_terms( $c, $q );
        $this->init_orderby( $c, $q );
        $this->init_paging( $c, $q );

        $this->format_request_sql( $c, $q );
    }

    /**
     * Sets the fields to be selected.
     *
     * @param  array  $clauses The query clauses.
     * @param  string $fields  The fields to be selected.
     */
    protected function init_fields( array &$clauses, string $fields ) {
        // Select the fields.
        $clauses['fields'] = match ( $fields ) {
            'ids'        => "{$this->table}.{$this->id_field}",
            'id=>parent' => "{$this->table}.{$this->id_field}, {$this->table}.parent",
            default      => "{$this->table}.*",
        };
    }

    /**
     * Initializes query columns.
     *
     * @param  array $clauses The query clauses.
     * @param  array $q The query variables.
     */
    protected function init_columns( array &$clauses, array $q ) {
        $cols = \array_filter(
            $q['col_query'] ?? array(),
            static fn( $v ) => ! \in_array(
                'all',
                \wc_string_to_array( $v ),
                true,
            )
        );

        if ( ! \count( $cols ) ) {
            return;
        }

        $clauses['where'] .= $this->get_sql_where_clauses( $cols, $q['relation'] ?? 'AND' );
    }

    protected function init_terms( array &$clauses, array $q ) {
        if ( ! $this->term_props || ! isset( $q['tax_query'] ) ) {
            return;
        }

        $wtq = new \WP_Tax_Query( $q['tax_query'] );
        $sql = $wtq->get_sql( $this->table, $this->id_field );

        $clauses['join']  .= $sql['join'];
        $clauses['where'] .= $sql['where'];
    }

    protected function init_orderby( array &$clauses, array $q ) {
        $clauses['orderby'] = 'ORDER BY ';

        $clauses['orderby'] = match ( $q['orderby'] ) {
            'rand' => 'RAND()',
            $this->id_field => "{$this->table}.{$this->id_field} {$q['order']}",
            default => "{$this->table}.{$q['orderby']} {$q['order']}",
        };
    }

    /**
     * Initializes query paging.
     *
     * @param  array $clauses The query clauses.
     * @param  array $q The query variables.
     */
    protected function init_paging( array &$clauses, array &$q ) {
        if ( $q['nopaging'] || ! $q['per_page'] ) {
            return;
        }

        // If 'offset' is provided, it takes precedence over 'page'.
        if ( isset( $q['offset'] ) && \is_numeric( $q['offset'] ) ) {
            $q['offset'] = \absint( $q['offset'] );
            $pgstrt      = $q['offset'] . ', ';
        } else {
            $pgstrt = \absint( ( $q['page'] - 1 ) * $q['per_page'] ) . ', ';
        }
        $clauses['limits'] = 'LIMIT ' . $pgstrt . $q['per_page'];
    }

    /**
     * Get the SQL WHERE clauses for a query.
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return string              SQL WHERE clauses.
     */
    protected function get_sql_where_clauses( $args, $clause_join ) {
        $clauses = array();

        foreach ( $args as $column => $value ) {
            $clauses[] = \is_array( $value )
                ? $this->get_array_clause_value( $column, $value )
                : $this->get_scalar_clause_value( $column, $value );
        }

        $clauses = \implode( $clause_join . ' ', $clauses );

        return $clauses ? " AND ( $clauses ) " : '';
    }

    /**
     * Get the SQL WHERE clause value depending on the type
     *
     * @param string|array $value Value.
     */
    protected function get_scalar_clause_value( $column, $value ) {
        global $wpdb;

        // Value is a string, let's handle wildcards.
        $left_wildcard  = 0 === \strpos( $value, '%' ) ? '%' : '';
        $right_wildcard = \strrpos( $value, '%' ) === \strlen( $value ) - 1 ? '%' : '';

        $value = \trim( \esc_sql( $value ), '%' );

        if ( $left_wildcard || $right_wildcard ) {
            $value = $wpdb->esc_like( $value );
        }

        $escaped_like = $left_wildcard . $value . $right_wildcard;

        return \sprintf( '%1$s LIKE \'%2$s\'', $column, $escaped_like );
    }

    protected function get_array_clause_value( string $col, array $value ): string {
        $in  = array();
        $not = array();
        $cls = array();

        foreach ( $value as $v ) {
            if ( \str_starts_with( $v, '!' ) ) {
                $not[] = \substr( $v, 1 );
                continue;
            }
            $in[] = $v;
        }

        if ( $in ) {
            $cls[] = $this->implode_array( $col, $in, 'IN' );
        }

        if ( $not ) {
            $cls[] = $this->implode_array( $col, $not, 'NOT IN' );
        }

        return \sprintf( '(%s)', \implode( ' AND ', $cls ) );
    }

    /**
     * Implode an array of values for use in a SQL query.
     *
     * @param  string         $col  The column name.
     * @param  array<string>  $val  The values.
     * @param  string         $glue The glue.
     * @return string
     */
    protected function implode_array( string $col, array $val, string $glue ): string {
        $val = \implode( "','", \esc_sql( $val ) );

        return \sprintf( '%s %s (\'%s\')', $col, $glue, $val );
    }

    /**
     * Formats the object request SQL based on query variables.
     *
     * @param  array $clauses The query clauses.
     * @param  array $q       The query variables.
     */
    protected function format_request_sql( array &$clauses, array &$q ) {
        $clauses['groupby'] = $clauses['groupby'] ? 'GROUP BY ' . $clauses['groupby'] : '';
        $clauses['orderby'] = $clauses['orderby'] ? 'ORDER BY ' . $clauses['orderby'] : '';

        $req = <<<SQL
            SELECT {$clauses['fields']} FROM {$this->table}
            INNER JOIN (
                SELECT {$this->id_field} FROM {$this->table}
                {$clauses['join']}
                WHERE 1=1 {$clauses['where']}
                {$clauses['groupby']}
                {$clauses['orderby']}
                {$clauses['limits']}
            ) AS tmp USING ({$this->id_field})
            SQL;

        $this->sql = $req;

        if ( ! $q['count_found'] || '' === $clauses['limits'] ) {
            return;
        }

        $this->sql_count = <<<SQL
            SELECT COUNT(*) FROM {$this->table}
            {$clauses['join']}
            WHERE 1=1 {$clauses['where']}
            SQL;
    }

    protected function run_query( array &$c, array $q ) {
        $this->objects ??= $this->query_database( $c, $q );
        $this->total   ??= $this->query_total_objects( $c, $q );

        return $this->objects;
    }

    /**
     * Query the database for the objects.
     *
     * @param  array $c The query clauses.
     * @param  array $q The query variables.
     * @return array    The objects.
     */
    protected function query_database( array &$c, array $q ): array {
        global $wpdb;

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        return 'ids' === $q['fields']
            ? \array_map( 'intval', $wpdb->get_col( $this->sql ) )
            : $wpdb->get_results( $this->sql );
        // phpcs:enable
    }

    /**
     * Sets up the amount of found posts and the number of pages (if limit clause was used)
     * for the current query.
     *
     * @since 3.5.0
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param array $c     The query clauses.
     * @param array $q     The query variables.
     * @return int
     */
    private function query_total_objects( array $c, array $q ) {
        global $wpdb;

        if ( ! $q['count_found'] ) {
            $this->pages = 0;
            return 0;
        }

        //phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.PHP
        $found = match ( true ) {
            '' !== $c['limits']            => (int) $wpdb->get_var( $this->sql_count ),
            \is_array( $this->objects )    => \count( $this->objects ),
            default                        => 0,
        };
        // phpcs:enable

        $this->pages = '' !== $c['limits'] ? \ceil( $found / $q['per_page'] ) : 1;

        return $found;
    }
}
