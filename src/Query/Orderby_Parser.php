<?php //phpcs:disable WordPress.DB.PreparedSQL
/**
 * Orderby_Query class file.
 *
 * @package WooCommerce Utils
 */

namespace XWC\Query;

use XWC\Interfaces\Clause_Parser;
use XWC\Interfaces\Query_Var_Handler;

/**
 * Handles the orderby query.
 */
class Orderby_Parser implements Clause_Parser {
        /**
         * Default table columns.
         *
         * @var array<int, string>
         */
    private array $columns;

    /**
     * Orderby clause.
     *
     * @var array
     */
    private array $orderby = array(
        'type'  => 'none',
        'value' => '',
    );

    /**
     * Order clause.
     *
     * @var string|array|null
     */
    private string|array|null $order = null;

    /**
     * Sql Queries
     *
     * @var array{orderby: string, order: string}
     */
    private array $queries = array(
        'order'   => '',
        'orderby' => '',
    );

    /**
     * Meta clauses.
     *
     * @var array<int|string, mixed>|false
     */
    private array $meta_clauses = array();

    /**
     * Primary meta query
     *
     * @var array
     */
    private array|bool $pmq = array();

    /**
     * Primary meta key
     *
     * @var string|false
     */
    private string $pmk = '';

    /**
     * Are we searching?
     *
     * @var array|false
     */
    private array|false $search = false;

    /**
     * Constructor
     *
     * @param  Query_Var_Handler $qv Query vars.
     */
    public function __construct( Query_Var_Handler &$qv ) {
        $this->search  = $qv['search_queries'];
        $this->columns = $qv->columns;

        $this->parse_meta_keys( $qv['meta_clauses'] ?? array() );
    }

    /**
     * Parses the meta keys from the meta clauses.
     *
     * @param  array $meta_clauses Meta clauses.
     */
    private function parse_meta_keys( array $meta_clauses ) {
        $this->pmq = \reset( $meta_clauses );

        if ( ! $this->pmq || ! $this->pmq['key'] ) {
            return;
        }

        $this->pmk = $this->pmq['key'];
    }

    /**
     * Does the query have orderby clauses?
     *
     * @return bool
     */
    public function has_queries(): bool {
        return \count( $this->queries ) > 0;
    }

    /**
     * Parse query vars needed for query generation.
     *
     * @param  Query_Var_Handler $qv Query vars.
     * @return static
     */
    public function parse_query_vars( $qv ): static {
        $qv = $qv->get();
        if ( \is_array( $qv['orderby'] ?? false ) ) {
            $orderby = \array_keys( $qv['orderby'] );
            $orderby = \array_values( $orderby );
        } else {
            $orderby = $qv['orderby'] ?? ( $this->search ? 'relevance' : 'ID' );
            $order   = $qv['order'] ?? '';
        }

        $type  = $this->parse_orderby_type( $orderby );
        $value = $this->parse_orderby_value( $orderby, $type );

        if ( '' === $value ) {
            return $this;
        }

        $this->order ??= $this->parse_order( $order, $type );
        $this->orderby = \compact( 'type', 'value' );

        return $this;
    }

    /**
     * Parse orderby type.
     *
     * @param  string|array|false $orderby Orderby clause.
     * @return string|array
     */
    private function parse_orderby_type( string|array|false $orderby ): string|array {
        return match ( true ) {
            false === $orderby                                  => 'none',
            \is_array( $orderby )                               => $this->parse_orderby_type( $orderby ),
            \in_array( $orderby, $this->columns, true )         => 'column',
            \str_starts_with( $orderby, 'RAND' )                => 'rand',
            $this->pmk === $orderby                             => 'meta_value',
            \array_key_exists( $orderby, $this->meta_clauses )  => 'meta_clause',
            default                                             => $orderby,
        };
    }

    /**
     * Parse orderby value.
     *
     * @param  string|array $orderby Orderby clause.
     * @param  string       $type    Orderby type.
     * @return string|array
     */
    private function parse_orderby_value( $orderby, string $type ): ?string {
        if ( \is_array( $orderby ) ) {
            return \array_map( fn( $o ) => $this->parse_orderby_value( $o, $type ), $orderby );
        }

        return match ( $type ) {
            'none'           => '',
            'column'         => "{{TABLE}}.$orderby",
            'ID'             => '{{TABLE}}.{{ID_COLUMN}}',
            'meta_value_num' => "{$this->pmq['alias']}.meta_value+0",
            'relevance'      => $this->parse_orderby_search(),
            'rand'           => $this->parse_orderby_rand( $orderby ),
            'meta_value'     => $this->parse_orderby_meta( $this->pmq ),
            default          => '',
        };
    }

    /**
     * Parse order clause.
     *
     * @param  string|array $order Order clause.
     * @param  string       $type  Orderby type.
     * @return string
     */
    private function parse_order( string|array $order, string $type ): string {
        if ( \is_array( $order ) ) {
            return \array_map( fn( $o ) => $this->parse_order( $o, $type ), $order );
        }

        if ( \in_array( $type, array( 'rand', 'oid_in', 'parent__in' ), true ) ) {
            return '';
        }

        return 'ASC' === \strtoupper( $order ) ? 'ASC' : 'DESC';
    }

    /**
     * Parse orderby search.
     *
     * @return string|null
     */
    private function parse_orderby_search(): ?string {
        global $wpdb;

        $q = $this->search;

        if ( 0 === $q['count'] ) {
            return null;
        }

        $this->order = '';

        if ( 1 === $q['count'] ) {
            return \reset( $q['orderby'] );
        }

        $count   = \count( $q['orderby'] );
        $match   = '';
        $orderby = '';
        $case    = 1;

        if ( ! \preg_match( '/(?:\s|^)\-/', $q['s'] ) ) {
            $match   = '%' . $wpdb->esc_like( $q['s'] ) . '%';
            $orderby = $wpdb->prepare( "WHEN {{TABLE}}.{$q['cols'][0]} LIKE %s THEN {$case} ", $match );
            ++$case;
        }

        if ( $count < 7 ) {
            $impl = static fn( $a ) => \implode( " {$a} ", $q['orderby'] );

            $orderby .= \sprintf( 'WHEN %s THEN %d ', $impl( 'AND' ), $case );
            ++$case;

            if ( $count > 1 ) {
                $orderby .= \sprintf( 'WHEN %s THEN %d ', $impl( 'OR' ), $case );
                ++$case;
            }
        }

        if ( $match && \count( $q['cols'] ) > 1 ) {
            \array_shift( $q['cols'] );

            foreach ( $q[ cols ] as $col ) {
                $orderby .= \sprintf( 'WHEN {{TABLE}}.%s LIKE %s THEN %d ', $col, $match, $case );
                ++$case;
            }
        }

        if ( $orderby ) {
            $orderby = "(CASE {$orderby} ELSE {$case} END)";
        }

        return $orderby;
	}

    /**
     * Parse orderby meta.
     *
     * @param  array $pmq Primary meta query.
     * @return string
     */
    private function parse_orderby_meta( array $pmq ): string {
        return $pmq['type'] ?? false
            ? "CAST({$pmq['alias']}.meta_value AS {$pmq['cast']})"
            : "{$pmq['alias']}.meta_value";
    }

    /**
     * Parse orderby rand.
     *
     * @param  string $orderby Orderby clause.
     * @return string
     */
    private function parse_orderby_rand( string $orderby ): string {
        \preg_match( '/RAND\(([0-9]+)\)/i', $orderby, $matches );

        $seed = $matches[1] ?? false ? (int) $matches[1] : '';

        return \sprintf( 'RAND(%s)', $seed );
    }

    /**
     * Get the sql queries.
     *
     * @param  string|null $primary_table    Primary table.
     * @param  string|null $primary_id_column Primary id column.
     * @return array
     */
    public function get_sql( $primary_table = null, $primary_id_column = null ) {
        $orderby = ! \is_array(
            $this->orderby['value'],
        ) ? array( $this->orderby['value'] ) : $this->orderby['value'];
        $order   = ! \is_array( $this->order ) ? array( $this->order ) : $this->order;

        $rpl = array(
            '{{ID_COLUMN}}' => $primary_id_column,
            '{{TABLE}}'     => $primary_table,
        );

        $sql = \array_map(
            static fn( $o, $i ) => \sprintf( '%s %s', $o, $order[ $i ] ),
            $orderby,
            \range( 0, \count( $orderby ) - 1 ),
        );

        return array(
            'orderby' => \strtr( \implode( ', ', $sql ), $rpl ),
        );
    }
}
