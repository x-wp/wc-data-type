<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Meta_Query class file.
 *
 * @package WooCommerce Utils
 */

namespace XWC\Query;

use XWC\Interfaces\Clause_Parser;
use XWC\Interfaces\Query_Var_Handler;

/**
 * A rework of the `WP_Meta_Query` class to work with our custom object query
 */
class Meta_Parser extends \WP_Meta_Query implements Clause_Parser {
    /**
     * Meta type.
     *
     * @var string
     */
    protected string $meta_type;

    /**
     * Query vars.
     *
     * @var Query_Var_Handler
     */
    protected $qv;

    public function __construct( Query_Var_Handler &$qv ) {
        $this->meta_type = $qv->meta_type;
        $this->qv        = $qv;
    }

    public function has_queries(): bool {
        return \count( $this->queries ) > 0;
    }

    public function get_sql( $primary_table = null, $primary_id_column = null, $nn_a = null, $nn_b = null ) {
        $this->qv['meta_clauses'] = $this->get_clauses();
        return parent::get_sql( $this->meta_type, $primary_table, $primary_id_column );
    }

    /**
	 * Constructs a meta query based on 'meta_*' query vars
	 *
	 * @since 3.2.0
	 *
	 * @param array $qv The query variables.
	 */
	public function parse_query_vars( $qv ): static {
        $pmq = $this->parse_primary_query( $qv );
        $emq = \is_array( $qv['meta_query'] ?? null ) ? $qv['meta_query'] : false;

        $meta_query = match ( true ) {
            (bool) ($pmq && $emq) => array( 'relation' => 'AND', $pmq, $emq ), //phpcs:ignore
            (bool) $pmq           => array( $pmq ),
            (bool) $emq           => $emq,
            default               => array(),
        };

		parent::__construct( $meta_query );

        return $this;
	}

    /**
     * Parses the primary query
     *
     * For `orderby = meta_value` to work correctly, simple query needs to be
     * first (so that its table join is against an unaliased meta table) and
     * needs to be its own clause (so it doesn't interfere with the logic of
     * the rest of the meta_query).
     *
     * @param  Traversable $qv The query variables.
     * @return array|false
     */
    protected function parse_primary_query( $qv ): array|false {
        $pmq = null;
        foreach ( array( 'key', 'compare', 'type', 'compare_key', 'type_key' ) as $key ) {
            if ( ! ( $qv[ "meta_$key" ] ?? false ) ) {
                continue;
            }
            $pmq ??= array();

            $pmq[ $key ] = $qv[ "meta_$key" ];
        }

        $mv = $qv['meta_value'] ?? null;

        if ( $mv && ! \is_array( $mv ) ) {
            $pmq['value'] = $mv;
        }

        return $pmq ?? false;
    }
}
