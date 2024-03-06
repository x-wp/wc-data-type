<?php // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query, Squiz.Commenting.FunctionComment.Missing
/**
 * Tax_Parser class file.
 *
 * @package WooCommerce Utils
 */

namespace XWC\Query;

use Traversable;
use XWC\Interfaces\Clause_Parser;
use XWC\Interfaces\Query_Var_Handler;

/**
 * A rework of the `WP_Tax_Query` class to work with our custom object query
 */
class Tax_Parser extends \WP_Tax_Query implements Clause_Parser {
    /**
     * Taxonomies to parse.
     *
     * @var array
     */
    protected array $taxonomies = array();

    /**
     * Query vars.
     *
     * @var Query_Var_Handler
     */
    protected Query_Var_Handler $q;

    public function __construct( Query_Var_Handler &$qv ) {
        $this->taxonomies = \is_array( $qv->taxonomies )
            ? \array_values( $qv->taxonomies )
            : array();

        $this->q = $qv;
    }

    /**
     * Sets up the defaul tax query array.
     *
     * @param  Traversable $q Tax query array.
     * @return array
     */
    protected function setup_tax_query( Traversable $q ): array {
        $q['tax_query'] ??= array();

        if ( $this->is_valid_tax( $q['taxonomy'] ?? '' ) && '' !== ( $q['term'] ?? '' ) ) {
            $q['tax_query'][] = array(
                'field'    => 'slug',
                'taxonomy' => $q['taxonomy'],
                'terms'    => array( $q['term'] ),
            );
        }

        return $q['tax_query'];
    }

    /**
     * Checks if the taxonomy in the `taxonomy` key is valid.
     *
     * @param  string $tax Taxonomy name.
     * @return bool
     */
    protected function is_valid_tax( string $tax ): bool {
        return '' !== $tax && \in_array( $tax, $this->taxonomies, true );
    }

    /**
     * Gets the valid taxonomy keys for a given taxonomy from the query array.
     *
     * @param  Traversable $q    Query array.
     * @param  array       $keys Keys to check.
     * @param  string      $tax  Taxonomy name.
     * @return array<string, array<int, mixed>>
     */
    protected function get_valid_keys( Traversable $q, array $keys, string $tax ): array {
        $q = \array_filter(
            \wp_parse_args(
                \wp_array_slice_assoc( $q, $keys ),
                \array_combine( $keys, \array_fill( 0, \count( $keys ), false ) ),
            ),
        );

        if ( isset( $q[ "{$tax}__and" ] ) && 1 === \count( (array) $q[ "{$tax}__and" ] ) ) {
            $q[ "{$tax}__and" ] = (array) $q[ "{$tax}__and" ];
            $q[ "{$tax}__in" ]  = \array_merge(
                $q[ "{$tax}__in" ] ?? array(),
                array( \absint( \reset( $q[ "{$tax}__and" ] ) ) ),
            );

            unset( $q[ "{$tax}__and" ] );
        }

        return $q;
    }

    /**
     * Get tax query values.
     *
     * @param  Traversable $q     Query array.
     * @param  array       $types Field types.
     * @param  string      $tax   Taxonomy name.
     * @return array<int, int|string>
     */
    protected function get_query_values( Traversable $q, array $types, string $tax ): array {
        $vars = $this->get_valid_keys( $q, \array_keys( $types ), $tax );

        foreach ( $vars as $key => $value ) {
            $vars[ $key ] = \array_map(
                static fn( $v ) => \sanitize_term_field( $types[ $key ], $v, 0, $tax, 'db' ),
                $this->split_term_values( $value, $types[ $key ] ),
            );
        }

        return $vars;
    }

    /**
     * Split the term values if needed.
     *
     * @param  string|array $vars Term values.
     * @param  string       $type Field type.
     * @return array<int, int|string>
     */
    protected function split_term_values( string|array $vars, string $type ): array {
        if ( ! \is_array( $vars ) ) {
            $split_regex = 'term_id' === $type ? '/[,\s]+/' : '/[,\r\n\t ]+/';

            $vars = \preg_split( $split_regex, $vars );
        }

        return $vars;
    }

    /**
     * Get default tax query values.
     *
     * @param  \WP_Taxonomy $t Taxonomy object.
     * @return array
     */
    protected function get_query_defaults( \WP_Taxonomy $t ): array {
        if ( ! $t->query_var ) {
            return array();
        }

        $qv = $t->name;
        $dq = array(
            'field'    => 'slug',
            'taxonomy' => $t->name,
        );

        if ( ( $t->rewrite['hierarchical'] ?? false ) ) {
            $qv = \wp_basename( $qv );
        }

        $tq = array(
            $qv              => \array_merge(
                $dq,
                array(
					'field'            => 'term_id',
					'include_children' => true,
                ),
            ),
            $qv . '__and'    => \array_merge(
                $dq,
                array(
					'field'            => 'term_id',
					'include_children' => false,
					'operator'         => 'AND',
                ),
            ),
            $qv . '__in'     => \array_merge(
                $dq,
                array(
					'field'            => 'term_id',
					'include_children' => false,
                ),
            ),
            $qv . '__not_in' => \array_merge(
                $dq,
                array(
					'include_children' => false,
					'operator'         => 'NOT IN',
                ),
            ),
        );

        if ( ! $t->hierarchical ) {
            $tq[ $qv . '_id' ]        = \array_merge( $dq, array( 'field' => 'term_id' ) );
            $tq[ $qv . '__slug_in' ]  = \array_merge( $dq, array( 'operator' => 'NOT IN' ) );
            $tq[ $qv . '__slug_and' ] = \array_merge( $dq, array( 'operator' => 'AND' ) );
        }

        return $tq;
    }

    /**
	 * Parses various taxonomy related query vars.
	 *
	 * For BC, this method is not marked as protected. See [28987].
	 *
	 * @since 3.1.0
	 *
	 * @param Traversable $q The query variables. Passed by reference.
	 */
	public function parse_query_vars( $q ): static {
		$tax_query = $this->setup_tax_query( $q );

        foreach ( $this->taxonomies as $taxonomy ) {
            $t = \get_taxonomy( $taxonomy );

            if ( ! $t ) {
                continue;
            }

            $defs = $this->get_query_defaults( $t );
            $type = \array_combine( \array_keys( $defs ), \wp_list_pluck( $defs, 'field' ) );

            foreach ( $this->get_query_values( $q, $type, $t->name ) as $key => $value ) {
                $tax_query[] = \array_merge( $defs[ $key ], array( 'terms' => $value ) );
            }
		}

        parent::__construct( $tax_query );

        $this->q['has_tax_queries'] = $this->has_queries();

        return $this;
	}

    public function has_queries(): bool {
        return \count( $this->queries ) > 0;
    }
}
