<?php
/**
 * Clause Processor trait file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC\Traits;

/**
 * Trait for processing query clauses.
 */
trait Clause_Processor {
    /**
     * Valid query operations.
     *
     * @var array
     */
    protected static $valid_ops = array(
        '>',
		'>=',
		'<',
		'<=',
        '=',
		'!=',
		'LIKE',
		'NOT LIKE',
		'IN',
		'NOT IN',
		'IS NULL',
		'IS NOT NULL',
		'RLIKE',
		'REGEXP',
		'NOT REGEXP',
        'IN',
        'NOT IN',
        'BETWEEN',
        'NOT BETWEEN',
    );

    /**
     * Valid array operations.
     *
     * @var array
     */
    protected static $array_ops = array(
        'IN',
        'NOT IN',
        'BETWEEN',
        'NOT BETWEEN',
    );

    /**
     * Performs query sanitization.
     *
     * @param  array $qv Query vars to sanitize.
     * @return array
     */
    protected function sanitize_queries( array $qv ): array {
        return \array_filter(
            \array_map(
                fn( $v ) => match ( true ) {
                    \is_null( $v )           => false,
                    $this->is_relation( $v ) => $v,
                    $this->is_query( $v )    => $this->sanitize_value( $v ),
                    default                  => $this->sanitize_queries( $v ),
                },
                $qv,
            ),
        );
    }

    /**
     * Sanitize a single query value, and set sane defaults
     *
     * @param  mixed $value Query value to sanitize.
     * @return array{
     *   compare: string|bool,
     *   exact : bool,
     *   type  : string,
     *   value : mixed
     * }
     */
    public function sanitize_value( $value ): array|false {
        $defaults = array(
            'column'  => false,
            'compare' => '=',
            'type'    => 'CHAR',
            'value'   => null,
        );

        $value = \wp_parse_args( $value, $defaults );

        return $value['column'] ? $value : false;
    }

    /**
     * Is the given value a query?
     *
     * Value is a query if it's an array and it has at least one of the keys returned by get_query_keys.
     *
     * @param  mixed $query Value to check.
     * @return bool
     */
    protected function is_query( mixed $query ): bool {
        return \is_array( $query ) && isset( $query['column'] );
    }

    /**
     * Is the given value a relation?
     *
     * Value is a relation if it's a string and it's either 'AND' or 'OR'.
     *
     * @param  mixed $what Value to check.
     * @return bool
     */
    protected function is_relation( mixed $what ): bool {
        return \is_string( $what ) && \in_array( \strtoupper( $what ), array( 'AND', 'OR' ), true );
    }

    /**
     * Get the SQL clauses for the query.
     *
     * @param  array $sql SQL clauses to get.
     * @return array
     */
    protected function get_sql_clauses( array $sql = array() ): array {
        $queries      = $this->queries;
        $sql['where'] = $this->get_sql_for_query( $queries );

        return $sql;
    }

    /**
     * Get the SQL for a query.
     *
     * @param  array  $query  Query to get SQL for.
     * @param  string $indent Indentation to use.
     * @return string
     */
    protected function get_sql_for_query( array &$query, string $indent = '' ): string {
        $chunks = array();

        foreach ( $query as $key => &$clause ) {
            if ( 'relation' === $key ) {
                $relation = $query['relation'];
                continue;
            }

            $clause_sql = $this->get_sql_for_clause( $clause );
            $chunks[]   = match ( \count( $clause_sql ) ) {
                0       => $this->get_sql_for_query( $clause, $indent . '  ' ),
                1       => $clause_sql[0],
                default => \sprintf( '( %s )', \implode( ' OR ', \array_filter( $clause_sql ) ) ),
            };
        }

        $chunks = \array_filter( $chunks );

        if ( 0 === \count( $chunks ) ) {
            return '';
        }

        $cb = static fn( $q, $s ) => \sprintf( " \n  %1\$s%2\$s%3\$s\n%3\$s%3\$s%1\$s", $indent, $q, $s );

        return '(' . \sprintf( $cb( \implode( $cb( $relation ?? 'AND', ' ' ), $chunks ), '' ) ) . ')';
    }

    /**
     * Get the SQL for a clause.
     *
     * @param  array $clause Clause to get SQL for.
     * @return array
     */
    protected function get_sql_for_clause( array &$clause ): array {
        if ( ! $this->is_query( $clause ) ) {
            return array();
        }

        $chunks  = array();
        $column  = '{{ID_FIELD}}' === $clause['column'] ? $this->primary_id_column : $clause['column'];
        $compare = ! \in_array( $clause['compare'], self::$valid_ops, true ) ? '=' : $clause['compare'];
        $values  = $this->get_clause_values( $clause['value'], $compare );

        foreach ( $values as $value ) {
            $chunks[] = \sprintf( '%s.%s %s %s', $this->primary_table, $column, $compare, $value );
        }

        return $chunks ?: array( '' ); //phpcs:ignore Universal.Operators
    }

    /**
     * Get the values for a clause.
     *
     * @param  mixed  $value   Value to get clause values for.
     * @param  string $compare Comparison to use.
     * @return array
     */
    protected function get_clause_values( mixed $value, string $compare ): array {
        $array_op = \in_array( $compare, self::$array_ops, true );

        if ( ! $array_op && \is_array( $value ) ) {
            return \array_map( fn( $v ) => $this->format_sql( $v, $compare ), $value );
        }

        if ( $array_op ) {
            $value = \wc_string_to_array( $value );
        } elseif ( \is_bool( $value ) ) {
            $value = \wc_bool_to_string( $value );
        } elseif ( \is_string( $value ) ) {
            $value = \trim( $value );
		}

        return array( $this->format_sql( $value, $compare ) );
    }

    /**
     * Format a value for use in a SQL query.
     *
     * @param  mixed  $value   Value to format.
     * @param  string $compare Comparison to use.
     * @return string
     */
    protected function format_sql( mixed $value, string $compare ): string {
        global $wpdb;

        //phpcs:disable WordPress.DB.PreparedSQL
        return match ( $compare ) {
            'IS NULL', 'IS NOT NULL' => '',
            'LIKE', 'NOT LIKE'       => $wpdb->prepare( '%s', $this->prepare_like( $value ) ),
            'IN', 'NOT IN'           => $wpdb->prepare(
                '(' . \substr( \str_repeat( ',%s', \count( $value ) ), 1 ) . ')',
                $value,
            ),
            'BETWEEN', 'NOT BETWEEN' => $wpdb->prepare( '%s AND %s', $value[0], $value[1] ),
            default                  => $wpdb->prepare( '%s', $value ),
		};
        //phpcs:enable
    }

    /**
     * Prepare a value for use in a LIKE clause.
     *
     * @param  string $value Value to prepare.
     * @return string
     */
    protected function prepare_like( string $value ): string {
        global $wpdb;

        $lw = \str_starts_with( $value, '%' ) ? '%' : '';
        $rw = \str_ends_with( $value, '%' ) ? '%' : '';

        $value = $lw || $rw ? $wpdb->esc_like( \trim( $value, '% ' ) ) : $value;

        return $lw . $value . $rw;
    }
}
