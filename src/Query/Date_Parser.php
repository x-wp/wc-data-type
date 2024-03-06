<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Date_Query class file.
 *
 * @package WooCommerce Utils
 */

namespace XWC\Query;

use XWC\Interfaces\Clause_Parser;
use XWC\Interfaces\Query_Var_Handler;

/**
 * A rework of the `WP_Date_Query` class to work with our custom object query
 */
class Date_Parser extends \WP_Date_Query implements Clause_Parser {
    /**
     * Month param
     *
     * @var string|false
     */
    protected string|false $month = false;

    /**
     * Params
     *
     * @var array|false
     */
    protected array|false $params = false;

    /**
     * Query
     *
     * @var array|false
     */
    protected array|false $query = false;

    public function __construct( Query_Var_Handler &$qv ) {
        $col = \array_keys( $qv->date_columns );
        $col = \reset( $col );

        if ( ! $col ) {
            return;
        }

        $this->column = $qv->table . '.' . $col;
    }

    public function has_queries(): bool {
        return \count( $this->queries ) > 0;
    }

    public function parse_query_vars( $qv ): static {
        if ( $qv['w'] ?? false ) {
            $qv['week'] = $qv['w'];
        }

        $this->month  = $qv['m'] ?? false;
        $this->params = array(
			\wp_array_slice_assoc(
                $qv,
                array( 'hour', 'minute', 'second', 'year', 'monthnum', 'week', 'day' ),
            ),
		);
        $this->query  = $qv['date_query'] ?? false;

        return $this;
    }

    public function get_sql( $t = null, $c = null ) {
        $where = '';

        if ( $this->month ) {
            $where .= $this->get_month_sql( $this->month );
        }

        foreach ( array( 'params', 'query' ) as $prop ) {
            if ( ! $this->$prop ) {
                continue;
            }

            $this->queries = array();
            parent::__construct( $this->$prop, $this->column );

            $where .= parent::get_sql();
        }

        return array(
            'where' => $where,
        );
    }

    /**
     * Get the SQL for `$m` parameter
     *
     * @param  string $m Month param.
     * @return string
     */
    protected function get_month_sql( string $m ) {
        $where  = '';
        $m      = \preg_replace( '|[^0-9]|', '', $m );
        $length = \strlen( $m );

        if ( $length < 4 ) {
            return '';
        }

        $where .= " AND YEAR({$this->column})=" . \substr( $m, 0, 4 );
        $cb     = static fn( $o, $l ) => \substr( $m, $o, $l );

        $where .= match ( true ) {
            $length > 5  => " AND MONTH({$this->column})=" . $cb( 4, 2 ),
            $length > 7  => " AND DAYOFMONTH({$this->column})=" . $cb( 6, 2 ),
            $length > 9  => " AND HOUR({$this->column})=" . $cb( 8, 2 ),
            $length > 11 => " AND MINUTE({$this->column})=" . $cb( 10, 2 ),
            $length > 13 => " AND SECOND({$this->column})=" . $cb( 12, 2 ),
            default      => '',
        };

        return $where;
    }
}
