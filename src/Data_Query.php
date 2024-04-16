<?php //phpcs:disable Squiz.PHP.CommentedOutCode.Found
/**
 * Data query class file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC;

use XWC\Interfaces\Clause_Parser;
use XWC\Interfaces\Data_Query as Query_Interface;
use XWC\Interfaces\Query_Cache_Handler;
use XWC\Interfaces\Query_Var_Handler;
use XWC\Traits\Object_Loop;

/**
 * Data query class.
 */
class Data_Query implements Query_Interface {
    use Object_Loop;

    /**
     * The query cache.
     *
     * @var Query_Cache_Handler|null
     */
    protected ?Query_Cache_Handler $cache = null;

    /**
     * The query variables.
     *
     * @var Query_Var_Handler
     */
    public Query_Var_Handler $vars;

    /**
     * SQL Parser classes.
     *
     * @var array<string, Clause_Parser>
     */
    public array $parsers = array();

    /**
     * SQL query for the object count.
     *
     * @var string
     */
    public string $sql_count;

    /**
     * Initial SQL query for the object request.
     *
     * @var string
     */
    public string $old_sql;

    /**
     * SQL query for the object request.
     *
     * @var string
     */
    public string $sql;

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

    /**
     * The objects being iterated over.
     *
     * @var array<int, Data|int>
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
     * Constructor
     *
     * @param  array|null $query The query variables.
     * @param  bool       $init  Whether to initialize the query on construction.
     */
    public function __construct( ?array $query = null, bool $init = true ) {
        $this->vars  = $this->init_vars();
        $this->cache = $this->init_cache();

        $has_query = \is_array( $query ) && isset( $query['data_type'] );

        if ( $has_query && $init ) {
            $this->query( $query );
            return;
        }

        if ( ! $has_query ) {
            return;
        }

        $this->parse_query( $query );
    }

    /**
     * Initializes the query var handler.
     *
     * @return Query_Var_Handler
     */
    protected function init_vars(): Query_Var_Handler {
        /**
         * Filters the class used for handling query vars.
         *
         * @param  string $handler_class The class to use for handling query vars.
         * @return string
         *
         * @since 1.0.0
         */
        $handler_class = \apply_filters( 'xwc_data_query_var_handler_class', Query\Vars::class );

        return new $handler_class();
    }

    /**
     * Initializes the query cache.
     *
     * @return Query_Cache_Handler|null
     */
    protected function init_cache(): ?Query_Cache_Handler {
        /**
         * Filters the class used for caching object queries.
         *
         * @param  string $cache_class The class to use for caching object queries.
         * @return string|false
         *
         * @since 1.0.0
         */
        $cache_class = \apply_filters( 'xwc_data_query_cache_class', Query\Cache::class );

        return $cache_class ? new $cache_class( $this ) : null;
    }

    /**
     * Sets up the WordPress query by parsing query string.
     *
     * @since 1.5.0
     *
     * @see WP_Query::parse_query() for all available arguments.
     *
     * @param  array $query Array of query arguments.
     * @return array<int, Extended_Data|int> Array of post objects or post IDs.
     */
    public function query( array $query ) {
        $this->reset();
        $this->vars->set( $query );

        return $this->get_objects();
    }

    /**
     * Parses the query variables.
     *
     * @param  array|null $query The query variables.
     */
    public function parse_query( ?array $query = null ) {
        if ( \is_array( $query ) && $query ) {
            $this->reset();
            $this->vars->set( $query );
        }

        $this->vars->fill();
        $this->vars->changed = true;

        // $this->vars->hash( false );

        /**
         * Fires after the main query vars have been parsed.
         *
         * @since 1.5.0
         *
         * @param WP_Query $query The WP_Query instance (passed by reference).
         */
        \do_action_ref_array( 'xwc_parse_data_query', array( &$this ) );
    }

    /**
     * Initiates object properties and sets default values.
     *
     * @since 1.5.0
     */
    public function reset() {
        unset( $this->object );
        unset( $this->objects );
        unset( $this->sql );
        unset( $this->sql_count );
        unset( $this->old_sql );

        // $this->is_admin = false;.
        $this->count    = null;
        $this->total    = null;
        $this->pages    = null;
        $this->current  = -1;
        $this->loop     = false;
        $this->pre_loop = true;
        $this->total    = null;
        $this->pages    = null;

        $this->vars->reset();
        $this->cache->reset();
    }

    /**
     * Parses and initializes the query.
     *
     * @param  array<string, string> $c The query clauses.
     * @param  Query_Var_Handler     $q The query variables.
     */
    protected function init_query( &$c, &$q ) {
        $this->parse_query();

        /**
         * Fires after the query variable object is created, but before the actual query is run.
         *
         * Note: If using conditional tags, use the method versions within the passed instance
         * (e.g. $this->is_main_query() instead of is_main_query()). This is because the functions
         * like is_main_query() test against the global $wp_query instance, not the passed one.
         *
         * @since 2.0.0
         *
         * @param WP_Query $query The WP_Query instance (passed by reference).
         */
        \do_action_ref_array( 'xwc_pre_get_objects', array( &$this ) );

        $q->sanity_check();
        // $q->hash( false );

        $this->init_fields( $c, $q['fields'] );
        $this->init_parsers( $c, $q );
        $this->init_paging( $c, $q );

        $this->init_filters( $c, $q, '' );
        $this->init_selection( $c );
        $this->init_filters( $c, $q, 'request' );

        $this->format_request_sql( $c, $q );
    }

    /**
     * Retrieves an array of objects based on query variables.
     *
     * There are a few filters and actions that can be used to modify the post
     * database query.
     *
     * @since 1.5.0
     *
     * @return array<int, Data|int> Array of post objects or post IDs.
     */
    public function get_objects() {
        $c = &$this->clauses;
        $q = &$this->vars;

        $this->init_query( $c, $q );

        return $this->run_query( $c, $q->get() );
    }

    /**
     * Retrieves a single object based on query variables.
     *
     * @since 1.5.0
     *
     * @return Data|int|null The object or ID.
     */
    public function get_object(): Data|int|null {
        $this->get_objects();

        return \is_array( $this->objects ) && \count( $this->objects ) > 0
            ? $this->objects[0]
            : ( 'ids' === $this->vars['fields'] ? 0 : null );
    }

    /**
     * Count the number of objects in the query.
     *
     * @return int
     */
    public function total_objects(): int {
        if ( null !== $this->total ) {
            return $this->total;
        }

        $c = &$this->clauses;
        $q = &$this->vars;

        $this->init_query( $c, $q );

        $q['force_count'] = true;

        return $this->total ??= (int) $GLOBALS['wpdb']->get_var( $this->sql_count );
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
            'ids'        => "{$this->vars->table}.{$this->vars->id_field}",
            'id=>parent' => "{$this->vars->table}.{$this->vars->id_field}, {$this->vars->table}.parent",
            default      => "{$this->vars->table}.*",
        };
    }

    /**
     * Run the parsers.
     *
     * @param  array             $clauses The query clauses.
     * @param  Query_Var_Handler $q       The query variables.
     */
    protected function init_parsers( array &$clauses, Query_Var_Handler &$q ) {
        foreach ( $this->get_parsers( $q ) as $query => $data ) {
            if ( ! $q->needs_parser( $query ) ) {
                continue;
            }

            $class = $data['class'];

            $this->parsers[ $query ] ??= ( new $class( $q ) )->parse_query_vars( $q );

            $clauses = $this->merge_clauses( $clauses, $this->parsers[ $query ], $data['action'] );
        }
    }

    /**
     * Get the parsers.
     *
     * @param  Query_Var_Handler $q The query variables.
     * @return array<string, array{action: string, class: class-string<Clause_Parser>}>
     */
    protected function get_parsers( Query_Var_Handler $q ) {
        // phpcs:ignore SlevomatCodingStandard.Arrays
        $parsers = array(
            'cols'    => array(
                'action' => 'append',
                'class'  => Query\Column_Parser::class,
            ),
            'date'    => array(
                'action' => 'append',
                'class'  => Query\Date_Parser::class,
            ),
            'meta'    => array(
                'action' => 'append',
                'class'  => Query\Meta_Parser::class,
            ),
            'tax'     => array(
                'action' => 'append',
                'class'  => Query\Tax_Parser::class,
            ),
            'search'  => array(
                'action' => 'append',
                'class'  => Query\Search_Parser::class,
            ),
            'orderby' => array(
                'action' => 'overwrite',
                'class'  => Query\Orderby_Parser::class,
            ),
        );

        /**
         * Filters the query parsers.
         *
         * @param  array             $parsers The query parsers.
         * @param  Query_Var_Handler $qv      The query variables.
         *
         * @return array
         *
         * @since 1.1.0
         */
        return \apply_filters( 'wcx_data_query_parsers', $parsers, $q );
    }

    /**
     * Merge the subquery into the main query.
     *
     * @param  array         $existing The existing query.
     * @param  Clause_Parser $parser   The Query parser.
     * @param  string        $how      How to merge the queries.
     *
     * @return array
     */
    protected function merge_clauses( array $existing, Clause_Parser &$parser, string $how = 'append' ): array {
        $sql = \array_filter( $parser->get_sql( $this->vars->table, $this->vars->id_field ) );

        foreach ( $sql as $clause => $value ) {
            if ( ! isset( $existing[ $clause ] ) ) {
                $existing[ $clause ] = $value;
                continue;
            }

            $value = 'append' === $how
                ? $existing[ $clause ] . $value
                : $value . $existing[ $clause ];

            $existing[ $clause ] = $value;
        }

        return $existing;
    }

    /**
     * Initializes query paging.
     *
     * @param  array             $clauses The query clauses.
     * @param  Query_Var_Handler $q       The query variables.
     */
    protected function init_paging( array &$clauses, Query_Var_Handler &$q ) {
        if ( $q['nopaging'] || ! $q['per_page'] ) {
            return;
        }

        $page = \absint( $q['page'] );
        if ( ! $page ) {
            $page = 1;
        }

        // If 'offset' is provided, it takes precedence over 'page'.
        if ( isset( $q['offset'] ) && \is_numeric( $q['offset'] ) ) {
            $q['offset'] = \absint( $q['offset'] );
            $pgstrt      = $q['offset'] . ', ';
        } else {
            $pgstrt = \absint( ( $page - 1 ) * $q['per_page'] ) . ', ';
        }
        $clauses['limits'] = 'LIMIT ' . $pgstrt . $q['per_page'];
    }

    /**
     * Initializes clause filters
     *
     * @param  array             $clauses The query clauses.
     * @param  Query_Var_Handler $q       The query variables.
     * @param  string|false      $suffix  Optional. An optional suffix for the clause filter.
     *                                              If false, the joint filter won't be used.
     */
    protected function init_filters( array &$clauses, Query_Var_Handler &$q, string|false $suffix ) {
        if ( ! $q['filters'] ) {
            return;
        }

        $keys   = \array_keys( $this->clauses );
        $suffix = $suffix ? '_' . \ltrim( $suffix, '_' ) : '';

        foreach ( $keys as $clause ) {
            $filter = "xwc_objects_{$clause}{$suffix}";

            /**
             * Filters the dynamic clause of the query.
             *
             * For use by caching plugins.
             *
             * @since 2.5.0
             *
             * @param string   $clause Clause of the query.
             * @param WP_Query $query  The WP_Query instance (passed by reference).
             *
             * @return string
             */
            $clauses[ $clause ] = \apply_filters_ref_array( $filter, array( $clauses[ $clause ], &$this ) );
        }

        if ( false === $suffix ) {
            return;
        }

        /**
         * Filters all query clauses at once, for convenience.
         *
         * For use by caching plugins.
         *
         * Covers the WHERE, GROUP BY, JOIN, ORDER BY, DISTINCT,
         * fields (SELECT), and LIMIT clauses.
         *
         * @since 3.1.0
         *
         * @param string[] $clauses {
         *     Associative array of the clauses for the query.
         *
         *     @type string $qwhere    The WHERE clause of the query.
         *     @type string $qgroupby  The GROUP BY clause of the query.
         *     @type string $qjoin     The JOIN clause of the query.
         *     @type string $qorderby  The ORDER BY clause of the query.
         *     @type string $qdistinct The DISTINCT clause of the query.
         *     @type string $qfields   The SELECT clause of the query.
         *     @type string $qlimits   The LIMIT clause of the query.
         * }
         * @param WP_Query $query  The WP_Query instance (passed by reference).
         */
        $clauses = (array) \apply_filters_ref_array( 'object_clauses' . $suffix, array( $clauses, &$this ) );

        $clauses = \wp_parse_args( $clauses, \array_combine( $keys, \array_fill( 0, \count( $keys ), '' ) ) );
    }

    /**
     * Fires to announce the query's current selection parameters.
     *
     * For use by caching plugins.
     *
     * @param  array $c The query clauses.
     */
    protected function init_selection( array &$c ) {
        // phpcs:ignore WooCommerce.Commenting
        \do_action(
            'object_selection',
            $c['where'] . $c['groupby'] . $c['orderby'] . $c['limits'] . $c['join'],
        );
    }

    /**
     * Formats the object request SQL based on query variables.
     *
     * @param  array             $clauses The query clauses.
     * @param  Query_Var_Handler $q       The query variables.
     */
    protected function format_request_sql( array &$clauses, Query_Var_Handler &$q ) {
        $clauses['groupby'] = $clauses['groupby'] ? 'GROUP BY ' . $clauses['groupby'] : '';
        $clauses['orderby'] = $clauses['orderby'] ? 'ORDER BY ' . $clauses['orderby'] : '';

        $req = <<<SQL
            SELECT {$clauses['fields']} FROM {$q->table}
            INNER JOIN (
                SELECT {$q->id_field} FROM {$q->table}
                {$clauses['join']}
                WHERE 1=1 {$clauses['where']}
                {$clauses['groupby']}
                {$clauses['orderby']}
                {$clauses['limits']}
            ) AS tmp USING ({$q->id_field})
            SQL;

        $this->old_sql = $req;
        $this->sql     = $req;

        if ( ! $q['count_found'] || '' === $clauses['limits'] ) {
            return;
        }

        $this->sql_count = <<<SQL
            SELECT COUNT(*) FROM {$q->table}
            {$clauses['join']}
            WHERE 1=1 {$clauses['where']}
            SQL;
    }

    /**
     * Runs the query, while checking cache and respecting pre-filter
     *
     * @param  array $c The query clauses.
     * @param  array $q The query variables.
     * @return Extended_Data[]|int[]
     */
    protected function run_query( array &$c, array $q ) {
        $this->objects ??= $this->cache?->is_cacheable() ?? false
            ? $this->cache?->get()
            : $this->pre_get_objects();

        $this->objects ??= $this->query_database( $c, $q );
        $this->total   ??= $this->query_total_objects( $c, $q );
        $this->count   ??= \count( $this->objects );

        $this->remap_objects( $q );

        if ( ! $this->cache?->found() ) {
            $this->cache?->set();
        }

        return $this->objects;
    }

    /**
     * Pre query filters
     */
    protected function pre_get_objects(): ?array {
        /**
         * Filters the posts array before the query takes place.
         *
         * Return a non-null value to bypass WordPress' default post queries.
         *
         * Filtering functions that require pagination information are encouraged to set
         * the `found_objects` and `max_num_pages` properties of the WP_Query object,
         * passed to the filter by reference. If WP_Query does not perform a database
         * query, it will not have enough information to generate these values itself.
         *
         * @since 4.6.0
         *
         * @param Extended_Data[]|int[]|null $posts Return an array of post data to short-circuit WP's query,
         *                                    or null to allow WP to run its normal queries.
         * @param Data_Query                 $query The WP_Query instance (passed by reference).
         */
        return \apply_filters_ref_array( 'xwc_pre_objects', array( null, &$this ) );
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

        $split = \wp_using_ext_object_cache() && ( '' !== $this->clauses['limits'] && $q['per_page'] < 100 );

        if ( $split ) {
            $this->init_fields( $c, 'ids' );
        }

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        return 'ids' === $q['fields']
            ? $wpdb->get_col( $this->sql )
            : $wpdb->get_results( $this->sql );
        // phpcs:enable
    }

    /**
     * Remap the objects to the requested type.
     *
     * @param  array $q The query variables.
     */
    protected function remap_objects( array $q ) {
        $id = $this->vars->id_field;

        $cb = match ( $q['fields'] ) {
            'ids'    => static fn( $v ) => \intval( \is_object( $v ) ? $v->$id : $v ),
            default  => array( $this, 'setup_object' ),
        };

        $this->objects = \array_map( $cb, $this->objects );
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
            null === $this->objects        => 0,
            0 === \count( $this->objects ) => 0,
            default                        => 1,
        };
        // phpcs:enable

        $this->pages = '' !== $c['limits'] ? \ceil( $found / $q['per_page'] ) : 1;

        return $found;
    }
}
