<?php //phpcs:disable Squiz.Commenting
/**
 * Cache_Handler class file.
 *
 * @package WooCommerce Utils
 */

namespace XWC\Query;

use XWC\Interfaces\Data_Query;
use XWC\Interfaces\Query_Cache_Handler;

/**
 * Object Query cache handler.
 */
class Cache implements Query_Cache_Handler {
    protected Data_Query $q;

    protected ?bool $cached = null;

    /**
     * Is the current query cacheable?
     *
     * @var bool|null
     */
    protected ?bool $cacheable = null;

    public function __construct( Data_Query &$query ) {
        $this->q = $query;
    }

    protected function get_cacheable_fields(): array {
        $table = $this->q->vars->table;

        return array(
			"{$table}.*",
			"{$table}.ID, {$table}.parent",
			"{$table}.ID, {$table}.object_parent",
			"{$table}.ID",
		);
    }

    /**
     * Ensure the ID database query is able to be cached.
     *
     * Random queries are expected to have unpredictable results and
     * cannot be cached. Note the space before `RAND` in the string
     * search, that to ensure against a collision with another
     * function.
     *
     * If `$fields` has been modified by the `posts_fields`,
     * `posts_fields_request`, `post_clauses` or `posts_clauses_request`
     * filters, then caching is disabled to prevent caching collisions.
     *
     * @return bool
     */
    public function is_cacheable(): bool {
        $this->cacheable ??=
            $this->q->vars['cache']
            &&
            ! \str_contains( \strtoupper( $this->q->clauses['orderby'] ), ' RAND(' )
            &&
            \in_array( $this->q->clauses['fields'], $this->get_cacheable_fields(), true );

        return $this->cacheable;
    }

    public function found(): bool {
        return $this->cached ?? false;
    }

    /**
	 * Generates cache key.
	 *
	 * @since 6.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array  $args Query arguments.
	 * @param string $sql  SQL statement.
	 * @return string Cache key.
	 */
	public function get_key() {
		global $wpdb;

        $args = \array_diff_key(
            $this->q->vars->get(),
            \wp_array_slice_assoc(
                $this->q->vars->get_feature_vars( 'core' ),
                array( 'page', 'per_page', 'offset' ),
            ),
        );

        $sql = \str_replace( $args['fields'], "{$this->q->vars->table}.*", $this->q->sql );
		$sql = $wpdb->remove_placeholder_escape( $sql );
		$key = \md5( \wp_json_encode( $args ) . $sql );

		$last_changed = \wp_cache_get_last_changed( 'posts' );
		if ( ! $this->q->vars['has_tax_queries'] ?? false ) {
			$last_changed .= \wp_cache_get_last_changed( 'terms' );
		}

		return "wp_query:$key:$last_changed";
	}

    /**
     * Get the cached query results.
     *
     * @return array<int, Extended_Data|int>
     */
    public function get(): ?array {
        $result = \wp_cache_get( $this->get_key(), 'data-queries', false, $this->cached );

        return $this->found() ? $result : null;
    }

    public function set() {
        //Waka.
    }

    public function reset(): void {
        $this->cacheable = null;
    }

    public function shutdown(): void {
        unset( $this->q );
    }
}
