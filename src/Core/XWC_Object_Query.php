<?php

class XWC_Object_Query {
    /**
     * Array of SQL query clauses.
     *
     * @var array<string,string>
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
     * @var array<int,stdClass|int>
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

    /**
     * The query variables used in the query.
     *
     * @var array<string,mixed>
     */
    public array $vars = array();

    /**
     * Constructor for the XWC_Object_Query class.
     *
     * @param  string $table    The table name to query.
     * @param  string $id_field The ID field of the table.
     * @param  mixed  ...$query Optional query arguments to filter the results.
     */
    public function __construct(
        protected string $table,
        protected string $id_field,
        mixed ...$query,
    ) {
        $this->parse( $query );
        $this->reset();
        $this->get_objects();
    }

    /**
     * Executes the query and returns the objects.
     *
     * @param  ?array<string,mixed> $query Optional query arguments to filter the results.
     * @return array<int,stdClass|int>
     */
    public function query( ?array $query = null ) {
        if ( $query ) {
            $this->parse( $query );
            $this->reset();
        }

        return $this->get_objects();
    }

    /**
     * Counts the number of objects in the query result.
     *
     * @param  ?array<string,mixed> $query Optional query arguments to filter the count.
     * @return int
     */
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

    /**
     * Parses the query arguments and sets the query variables.
     *
     * @param  array<mixed> $q The query arguments.
     */
    protected function parse( array $q ): void {
        $d = array(
            'count_found' => true,
            'count_only'  => false,
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

        if ( $q['count_only'] ) {
            $q['fields']      = 'ids';
            $q['count_found'] = true;
            $q['nopaging']    = true;
            $q['per_page']    = false;
        }

        $this->vars = $q;
    }

    /**
     * Resets the query state.
     *
     * This method clears the SQL clauses, resets the SQL strings, and clears the objects,
     * count, total, and pages properties.
     *
     * @return static
     */
    protected function reset(): static {
        $this->clauses   = \array_map( '__return_empty_string', $this->clauses );
        $this->sql       = '';
        $this->sql_count = '';
        $this->objects   = null;
        $this->count     = null;
        $this->total     = null;
        $this->pages     = null;

        return $this;
    }

    /**
     * Retrieves the objects based on the query clauses and variables.
     *
     * @return array<int,stdClass|int>
     */
    protected function get_objects(): array {
        $c = &$this->clauses;
        $q = &$this->vars;

        $this->init_query( $c, $q );

        return $this->run_query( $c, $q );
    }

    /**
     * Initializes the query clauses based on the query variables.
     *
     * This method sets up the fields, columns, terms, orderby, and paging for the query.
     *
     * @param  array<string,string> $c Query clauses.
     * @param  array<string,mixed>  $q Query variables.
     */
    protected function init_query( &$c, &$q ): void {
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
     * @param  array<string,string> $clauses The query clauses.
     * @param  string               $fields  The fields to be selected.
     */
    protected function init_fields( array &$clauses, string $fields ): void {
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
     * @param array<string,string> $c The query clauses.
     * @param array<string,mixed>  $q The query variables.
     */
    protected function init_columns( array &$c, array $q ): void {
        $cols = \array_filter(
            $q['col_query'] ?? array(),
            static fn( $v ) => ! \in_array(
                'all',
                \wc_string_to_array( $v ),
                true,
            ),
        );

        if ( \count( $cols ) ) {
            $c['where'] .= $this->get_sql_where_clauses( $cols, $q['relation'] ?? 'AND' );
        }

        if ( ! isset( $q['date_query'] ) ) {
            return;
        }

        $c['where'] .= ( new \WP_Date_Query( $q['date_query'] ) )->get_sql();
    }

    /**
     * Initializes query terms.
     *
     * @param array<string,string> $c Query clauses.
     * @param array<string,mixed>  $q Query variables.
     */
    protected function init_terms( array &$c, array $q ): void {
        if ( ! isset( $q['tax_query'] ) || ! $q['tax_query'] ) {
            return;
        }

        $wtq = new \WP_Tax_Query( $q['tax_query'] );
        $sql = $wtq->get_sql( $this->table, $this->id_field );

        $c['join']  .= $sql['join'];
        $c['where'] .= $sql['where'];
    }

    /**
     * Initializes query orderby.
     *
     * @param array<string,string> $c Query clauses.
     * @param array<string,mixed>  $q Query variables.
     */
    protected function init_orderby( array &$c, array $q ): void {
        $c['orderby'] = 'ORDER BY ';

        $c['orderby'] = match ( $q['orderby'] ) {
            'rand' => 'RAND()',
            $this->id_field => "{$this->table}.{$this->id_field} {$q['order']}",
            default => "{$this->table}.{$q['orderby']} {$q['order']}",
        };
    }

    /**
     * Initializes query paging.
     *
     * @param array<string,string> $c Query clauses.
     * @param array<string,mixed>  $q Query variables.
     */
    protected function init_paging( array &$c, array &$q ): void {
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
        $c['limits'] = 'LIMIT ' . $pgstrt . $q['per_page'];
    }

    /**
     * Get the SQL WHERE clauses for a query.
     *
     * @param  array<string,mixed> $args        Query arguments.
     * @param  string              $clause_join SQL join clause. Can be AND or OR.
     * @return string                           SQL WHERE clauses.
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
     * @param  string $column Column name.
     * @param  string $value  Value.
     * @return string
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

        return \sprintf( '%1$s LIKE \'%2$s\' ', $column, $escaped_like );
    }

    /**
     * Get the SQL WHERE clause value for an array.
     *
     * @param  string        $col   The column name.
     * @param  array<string> $value The values.
     * @return string
     */
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
     * @param  array<string,string> $c Query clauses.
     * @param  array<string,mixed>  $q Query variables.
     */
    protected function format_request_sql( array &$c, array &$q ): void {
        $c['groupby'] = $c['groupby'] ? 'GROUP BY ' . $c['groupby'] : '';
        $c['orderby'] = $c['orderby'] ? 'ORDER BY ' . $c['orderby'] : '';

        $req = <<<SQL
            SELECT {$c['fields']} FROM {$this->table}
            INNER JOIN (
                SELECT {$this->id_field} FROM {$this->table}
                {$c['join']}
                WHERE 1=1 {$c['where']}
                {$c['groupby']}
                {$c['orderby']}
                {$c['limits']}
            ) AS tmp USING ({$this->id_field})
            {$c['orderby']}
            SQL;

        $this->sql = $req;

        if ( ! $q['count_found'] || '' === $c['limits'] ) {
            return;
        }

        $this->sql_count = <<<SQL
            SELECT COUNT(*) FROM {$this->table}
            {$c['join']}
            WHERE 1=1 {$c['where']}
            SQL;
    }

    /**
     * Runs the query and returns the objects.
     *
     * @param  array<string,string> $c Query clauses.
     * @param  array<string,mixed>  $q Query variables.
     * @return array<int,stdClass|int>
     */
    protected function run_query( array &$c, array $q ): array {
        $this->objects ??= $this->query_database( $c, $q );
        $this->total   ??= $this->query_total_objects( $c, $q );

        return $this->objects;
    }

    /**
     * Query the database for the objects.
     *
     * @param  array<string,string> $c Query clauses.
     * @param  array<string,mixed> $q Query variables.
     * @return array<int,stdClass|int>
     */
    protected function query_database( array &$c, array $q ): array {
        global $wpdb;

        if ( $q['count_only'] ) {
            return array();
        }

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        return 'ids' === $q['fields']
            ? \array_map( 'intval', $wpdb->get_col( $this->sql ) )
            : $wpdb->get_results( $this->sql );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Sets up the amount of found posts and the number of pages (if limit clause was used)
     * for the current query.
     *
     * @since 3.5.0
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param  array<string,string> $c Query clauses.
     * @param  array<string,mixed>  $q Query variables.
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
            $q['count_only'],
            '' !== $c['limits']            => (int) $wpdb->get_var( $this->sql_count ),
            \is_array( $this->objects )    => \count( $this->objects ),
            default                        => 0,
        };
        //phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.PHP

        $this->pages = '' !== $c['limits'] ? (int) \ceil( $found / $q['per_page'] ) : 1;

        return $found;
    }
}
