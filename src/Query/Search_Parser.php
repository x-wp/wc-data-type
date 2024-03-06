<?php //phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput, Universal.Operators.DisallowShortTernary, WordPress.DB.PreparedSQL
/**
 * Search_Parser class file.
 *
 * @package WooCommerce Utils
 * @subpackage Query
 */

namespace XWC\Query;

use XWC\Interfaces\Clause_Parser;
use XWC\Interfaces\Query_Var_Handler;

/**
 * Parses the search query.
 */
class Search_Parser implements Clause_Parser {
    /**
     * Maximum number of search terms.
     */
    const MAX_SEARCH_TERMS = 9;

    /**
     * Maximum length of the search string.
     */
    const MAX_SEARCH_LENGTH = 1600;

    /**
     * Stopwords.
     *
     * @var array
     */
    protected array $stopwords;

    /**
     * Search queries.
     *
     * @var array
     */
    protected array $queries = array();

    /**
     * Columns to search.
     *
     * @var array
     */
    protected array $search_columns;

    /**
     * Query vars.
     *
     * @var Query_Var_Handler
     */
    protected Query_Var_Handler $qv;

    //phpcs:disable Squiz.Commenting
    public function __construct( Query_Var_Handler &$qv ) {
        $this->qv             = $qv;
        $this->search_columns = $qv->search_columns;
        $this->stopwords      = $this->get_search_stopwords();
    }

    /**
     * Get the search queries.
     *
     * @return array
     */
    public function get_queries(): array {
        return $this->queries;
    }

    // phpcs:disable Squiz.Commenting
    public function has_queries(): bool {
        return \count( $this->queries['terms'] ) >= 1 && \count( $this->queries['cols'] ) >= 1;
    }

    /**
     * Pick and clean the query vars.
     *
     * @param  array $qv       Query vars.
     * @param  array $defaults Default values.
     * @return array
     */
    protected function pick_and_clean( array $qv, array $defaults ): array {
        $qv = \array_intersect_key( $qv, $defaults );
        $qv = \wp_parse_args( $qv, $defaults );

        $qv['orderby'] = array();
        $qv['terms']   = array();
        $qv['orderby'] = array();
        $qv['count']   = 0;

        return $qv;
    }

    /**
     * Prepare the search string.
     *
     * @param  array $q Query vars.
     * @return array
     */
    protected function prepare_search_string( array $q ): array {
        if ( ! \is_scalar( $q['s'] ) || ( '' === $q['s'] && \strlen( $q['s'] > self::MAX_SEARCH_LENGTH ) ) ) {
            $q['s'] = '';

            return $q;
        }

        $q['s'] = \stripslashes( $q['s'] );

        if ( ! ( $_GET['s'] ?? false ) ) {
            $q['s'] = \urldecode( $q['s'] );
        }

        $q['s'] = \str_replace( array( "\r", "\n" ), '', $q['s'] );

        return $q;
    }

    /**
     * Prepare the search terms.
     *
     * @param  array $q Query vars.
     * @return array<int, string>
     */
    protected function prepare_search_terms( array $q ): array {
        if (
            ! \preg_match_all( '/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/', $q['s'], $matches ) ||
            $q['sentence']
        ) {
            $q['terms'] = \array_filter( array( $q['s'] ) );

            return $q;
        }

        $q['count'] = \count( $matches[0] );
        $q['terms'] = $this->parse_search_terms( $matches[0] );

        if ( ! $q['terms'] || \count( $q['terms'] ) > self::MAX_SEARCH_TERMS ) {
            $q['terms'] = array( $q['s'] );
        }

        return $q;
    }

    /**
     * Prepare the search columns.
     *
     * @param  array $q Query vars.
     * @return array
     */
    protected function prepare_search_columns( array $q ): array {
        $q['cols'] = \wc_string_to_array( $q['search_columns'] ?: $this->search_columns );
        $q['cols'] = \array_intersect( $q['cols'], $this->search_columns ) ?: $this->search_columns;

        unset( $q['search_columns'] );

        return $q;
    }

    /**
     * Parse the query vars and prepare the search query.
     *
     * @param  Query_Var_Handler $qv Query vars.
     * @return static
     */
    public function parse_query_vars( $qv ): static {
        $q = $this->pick_and_clean( $qv->get(), $qv->get_feature_vars( 'search', true ) );
        $q = $this->prepare_search_string( $q );
        $q = $this->prepare_search_terms( $q );
        $q = $this->prepare_search_columns( $q );

        $this->queries = $q;

        return $this;
    }

    // phpcs:disable Squiz.Commenting, SlevomatCodingStandard.Complexity
    public function get_sql( $primary_table = null, $primary_id_column = null ) {
        $sql = array();
        $ob  = array();

        foreach ( $this->queries['terms'] as $term ) {
            $exclude = \preg_match( '/^[-\!]/', $term );
            $term    = $this->format_term_value( $term, $exclude );

            if ( ! $this->queries['exact'] && ! $exclude ) {
                $ob[] = $term;
            }

            $sql[] = $this->get_column_queries(
                $primary_table,
                $this->queries['cols'],
                $exclude ? 'NOT LIKE' : 'LIKE',
                $term,
            );
		}

        if ( ! $sql ) {
            return array();
        }

        $this->format_orderby( $primary_table, $ob );

        $this->qv['search_queries'] = $this->get_queries();

        return array( 'where' => ' AND ( ' . \implode( ' AND ', $sql ) . ' )' );
    }

    /**
     * Format the orderby clause for the SQL query.
     *
     * @param  string $table Table name.
     * @param  array  $terms Search terms.
     */
    protected function format_orderby( string $table, array $terms ) {
        global $wpdb;

        $col = $this->queries['cols'][0];
        foreach ( $terms as $term ) {
            $this->queries['orderby'][] = $wpdb->prepare( "{$table}.{$col} LIKE %s", $term );
        }
    }

    /**
     * Format the search term for the SQL query.
     *
     * @param  string $term    Search term.
     * @param  bool   $exclude Whether to exclude the term.
     * @return string
     */
    protected function format_term_value( string $term, bool $exclude ): string {
        global $wpdb;

        if ( $exclude ) {
            $term = \substr( $term, 1 );
        }

        return \sprintf(
            '%1$s%2$s%1$s',
            ! $this->queries['exact'] ? '%' : '',
            $wpdb->esc_like( $term ),
		);
    }

    /**
     * Get queries for single column.
     *
     * @param  string $table Table name.
     * @param  array  $cols  Columns to search.
     * @param  string $op    Operator.
     * @param  string $term  Search term.
     * @return string
     */
    protected function get_column_queries( string $table, array $cols, string $op, string $term ): string {
        global $wpdb;

        $glue  = 'LIKE' === $op ? 'OR' : 'AND';
        $parts = array();

        foreach ( $cols as $col ) {
            $parts[] = $wpdb->prepare( "{$table}.{$col} {$op} %s", $term );
        }

        return '( ' . \implode( " {$glue} ", $parts ) . ' )';
    }

    /**
	 * Checks if the terms are suitable for searching.
	 *
	 * Uses an array of stopwords (terms) that are excluded from the separate
	 * term matching when searching for posts. The list of English stopwords is
	 * the approximate search engines list, and is translatable.
	 *
	 * @since 3.7.0
	 *
	 * @param string[] $terms Array of terms to check.
	 * @return string[] Terms that are not stopwords.
	 */
	protected function parse_search_terms( $terms ) {
        $terms = \array_map( array( $this, 'keep_search_term_spaces' ), $terms );
        $terms = \array_filter( $terms, array( $this, 'remove_letters' ) );
        $terms = \array_diff( $terms, $this->stopwords );

		return $terms;
	}

    /**
     * Remove quotes, but keep spaces for terms that are enclosed in quotes.
     *
     * @param  string $term Search term.
     * @return string       Search term without quotes.
     */
    protected function keep_search_term_spaces( string $term ): string {
        return \preg_match( '/^".+"$/', $term ) ? \trim( $term, "\"'" ) : \trim( $term, "\"' " );
    }

    /**
     * Remove letters from the term if it's not a phrase.
     *
     * @param  string $term Search term.
     * @return string       Search term without letters.
     */
    protected function remove_letters( string $term ): string {
        return $term && ( 1 !== \strlen( $term ) || ! \preg_match( '/^[a-z\-]$/i', $term ) );
    }

    /**
	 * Retrieves stopwords used when parsing search terms.
	 *
	 * @since 3.7.0
	 *
	 * @return string[] Stopwords.
	 */
	protected function get_search_stopwords() {
		/*
		 * translators: This is a comma-separated list of very common words that should be excluded from a search,
		 * like a, an, and the. These are usually called "stopwords". You should not simply translate these individual
		 * words into your language. Instead, look for and provide commonly accepted stopwords in your language.
		 */
		$words = \explode(
			',',
			\_x(
				'about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www',
				'Comma-separated list of search stopwords in your language',
                'default',
			),
		);

		$stopwords = array();
		foreach ( $words as $word ) {
			$word = \trim( $word, "\r\n\t " );
			if ( ! $word ) {
                continue;
            }

            $stopwords[] = $word;
		}

		/**
		 * Filters stopwords used when parsing search terms.
		 *
		 * @since 3.7.0
		 *
		 * @param  string[] $stopwords Array of stopwords.
         * @return string[]
		 */
		return \apply_filters( 'wp_search_stopwords', $stopwords );
	}
}
