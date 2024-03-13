<?php //phpcs:disable SlevomatCodingStandard.Classes.ClassMemberSpacing, WordPress.DB.SlowDBQuery

namespace XWC\Query;

use XWC\Interfaces\Query_Var_Handler;
use XWC\Traits\Data_Type_Meta;

/**
 * Wrapper class for handling and parsing query vars.
 *
 * @property-read string      $id_field       ID field.
 * @property-read string      $table          Table name.
 * @property-read string      $classname      Class name.
 * @property-read array|false $taxonomies     Taxonomies.
 * @property-read array       $columns        Columns.
 * @property-read array       $search_columns Searchable columns.
 * @property-read string      $column_prefix  Column prefix.
 */
#[\AllowDynamicProperties]
class Vars implements Query_Var_Handler {
    use Data_Type_Meta;

    /**
     * Data type.
     *
     * @var string
     */
    protected string $data_type;

    /**
     * Parsed query vars.
     *
     * @var array<int, mixed>
     */
    protected array $vars = array();

    /**
     * Query vars keys.
     *
     * @var array
     */
    protected array $keys = array();

    /**
     * Default query vars.
     *
     * @var array<int, mixed>
     */
    protected array $defaults;

    /**
     * Current position.
     *
     * @var int
     */
    protected int $position = 0;

    /**
     * Supported features. Can be `meta`, `search`, `date`a
     *
     * @var array<int, string>
     */
    protected array $supports = array();

    /**
     * Query hash.
     *
     * @var string
     */
    protected ?string $hash = null;

    /**
	 * Whether query vars have changed since the initial parse_query() call. Used to catch modifications to query vars made
	 * via `"pre_get_{$this->entity}"` hooks.
	 *
     * @var bool
	 */
    public bool $changed = false;

    /**
     * Key for unset variables in the default query.
     *
     * @var string
     */
    protected string $std_key;

    /**
     * Get the metadata keys.
     *
     * @return array
     */
    protected function get_metadata_keys(): array {
        return array(
            'id_field',
            'meta_type',
            'table',
            'classname',
            'taxonomies',
            'columns',
            'search_columns',
            'date_columns',
            'column_vars',
            'column_prefix',
        );
    }

    /**
     * Constructor.
     */
    public function __construct() {
        $this->std_key = \uniqid();
    }

    /**
     * Set the query vars.
     *
     * @param  array $query Query vars.
     */
    public function set( array $query ): void {
        $this->vars = $query;
    }

    /**
     * Get the query vars.
     *
     * @return array
     */
    public function get(): array {
        return $this->vars;
    }

    /**
     * Set the query vars and cleanup
     *
     * @param  array $qv Query vars.
     */
    public function set_query_vars( array $qv ): void {
        $this->data_type = $qv['data_type'];

        $this->init_metadata();

        $this->supports = $this->get_features();
        $this->defaults = $this->get_defaults();

        // Remove the that are not in the defaults.
        $qv = \array_intersect_key( $qv, $this->defaults );

        // Remove the default values.
        $qv = $this->remove_defaults( $qv );

        $qv = \wp_parse_args( $qv, $this->get_feature_vars( 'core', true ) );
        $qv = $this->validate_vars( $qv );

        $this->keys = \array_keys( $qv );
        $this->vars = $qv;
    }

    /**
     * Reset the query vars.
     */
    public function reset(): void {
        $this->supports = array();
        $this->vars     = array();
        $this->keys     = array();
        $this->position = 0;
        $this->hash     = '';
        $this->changed  = false;

        unset( $this->data_type );

        foreach ( $this->get_metadata_keys() as $prop ) {
            unset( $this->$prop );
        }
    }

    /**
     * Fill the query vars.
     */
    public function fill(): void {
        $this->set_query_vars( $this->vars );
    }

    /**
     * Validates and sanitizes the query vars.
     *
     * @param  array $qv Query vars.
     * @return array
     */
    protected function validate_vars( array $qv ): array {
        $qv['count_found'] = (bool) $qv['count_found'];
        $qv['nopaging']    = -1 === \intval( $qv['per_page'] );

        if ( $qv['nopaging'] ) {
            $qv['page']     = 0;
            $qv['per_page'] = 0;

            return $qv;
        }

        $page       = \absint( $qv['page'] );
        $qv['page'] = $page ? $page : 1;

        if ( false === $qv['offset'] || ! \is_numeric( $qv['offset'] ) ) {
            return $qv;
        }

        $qv['offset'] = \absint( $qv['offset'] );
        $qv['page']   = 0;

        return $qv;
    }

    /**
     * Perform a sanity check on the query vars.
     */
    public function sanity_check(): void {
        $this->set_query_vars( $this->vars );
    }

    /**
     * Hash the query vars.
     *
     * @param  bool $check_only Whether to only check if the query has changed.
     * @return bool             Whether the query has changed.
     */
    public function hash( bool $check_only = true ): bool {
        $hash    = \md5( \wp_json_encode( $this ) );
        $changed = ! \is_null( $this->hash ) && $this->hash !== $hash;

        if ( $check_only ) {
            return $changed;
        }

        $this->hash    = $hash;
        $this->changed = $changed;

        return $changed;
    }

    //phpcs:disable Squiz.Commenting
    #region Features
    public function get_features(): array {
        return \array_merge(
            array( 'cols', 'orderby', 'limit' ),
            \array_keys( \xwc_data_type_get_supports( $this->data_type, 'query' ) ),
        );
    }

    public function supports( string $what ): bool {
        return \in_array( $what, $this->supports, true );
    }

    public function needs_parser( string $which ): bool {
        if ( ! $this->supports( $which ) ) {
            return false;
        }

        return \in_array( $which, array( 'orderby', 'limit' ), true ) || \count(
            \array_intersect_key( $this->get(), $this->get_feature_vars( $which ) ),
        ) > 0;
    }
    #endregion Features
    //phpcs:enable Squiz.Commenting

    //phpcs:disable Squiz.Commenting
    #region Defaults

    protected function remove_defaults( array $vars ): array {
        return \array_filter( $vars, fn( $v ) => $v !== $this->std_key );
    }

    public function get_defaults(): array {
        $defaults = array();

        foreach ( $this->supports as $feature ) {
            $defaults = \array_merge( $defaults, $this->get_feature_vars( $feature ) );
        }

        return \array_merge(
            $defaults,
            $this->get_feature_vars( 'cols' ),
            $this->get_feature_vars( 'core', true ),
        );
    }

    public function get_feature_vars( string $feature, bool $real = false ): array {
        $dt = $this->data_type;

        $pp = \get_option( "{$dt}_per_page", \get_option( 'posts_per_page', 20 ) );

        $vars = match ( $feature ) {
            'core' => \array_merge(
                array(
                    'cache'       => true,
                    'count_found' => true,
                    'data_type'   => $dt,
                    'date_column' => null,
                    'fields'      => '*',
                    'filters'     => true,
                    'offset'      => false,
                    'page'        => 1,
                    'per_page'    => $pp,
                ),
                array(
                    "update_{$dt}_meta_cache" => false,
                    "update_{$dt}_term_cache" => false,
                    'lazy_load_term_meta'     => false,
                ),
            ),
            'cols' => $this->get_column_defaults(),
            'meta' => array(
                'meta_compare' => null,
                'meta_key'     => null,
                'meta_query'   => array(),
                'meta_type'    => null,
                'meta_value'   => null,
            ),
            'date' => array(
				'date_query' => false,
				'day'        => null,
				'hour'       => null,
				'm'          => null,
				'minute'     => null,
				'monthnum'   => null,
				'second'     => null,
				'w'          => null,
                'week'       => null,
				'year'       => null,
            ),
            'search' => array(
                'exact'          => false,
                's'              => '',
                'search_columns' => $this->search_columns,
                'sentence'       => false,
            ),
            'taxonomy' => $this->get_taxonomy_defaults(),
            default => array(),
        };

        return ! $real ? \array_map( fn() => $this->std_key, $vars ) : $vars;
    }

    protected function get_column_defaults(): array {
        $cols = \array_merge(
            array( 'id', 'ID', 'column_query' ),
            \array_keys( \array_diff( $this->column_vars, $this->search_columns ) ),
        );

        return \array_combine(
            $cols,
            \array_fill( 0, \count( $cols ), null ),
        );
    }

    /**
     * Get default variables for taxonomies.
     *
     * We only get first hierarchical taxonomy and first non-hierarchical taxonomy.
     *
     * @return array
     */
    protected function get_taxonomy_defaults() {
        if ( ! $this->taxonomies ) {
            return array();
        }

        $tax_done   = array();
        $query_vars = array();

        foreach ( $this->taxonomies as $tax_name ) {
            $query_vars = \array_merge(
                $query_vars,
                $this->get_taxonomy_vars( \get_taxonomy( $tax_name ), $tax_done ),
            );
        }

        unset( $tax_done );

        return $query_vars;
    }

    /**
     * Get variables for one taxonomy.
     *
     * @param  \WP_Taxonomy $tax  Taxonomy object.
     * @param  array        $done Taxonomy types already processed.
     * @return array
     */
    protected function get_taxonomy_vars( \WP_Taxonomy $tax, array &$done ): array {
        $all_keys = array( '', '__in', '__not_in', '__and' );
        $tag_keys = array( '_slug__in', '_slug__and' );
        $tax_type = $tax->hierarchical ? 'id' : 'slug';

        if ( \in_array( $tax_type, $done, true ) ) {
            return array();
        }

        $tax_keys = ! $tax->hierarchical ? \array_merge( $all_keys, $tag_keys ) : $all_keys;
        $tax_keys = \array_map( static fn( $k ) => $tax->query_var . $k, $tax_keys );

        $done[] = $tax_type;

        return $tax_keys;
    }
    #endregion Defaults
    //phpcs:enable Squiz.Commenting

    //phpcs:disable Squiz.Commenting
    #region Countable
    public function count(): int {
        return \count( $this->keys );
    }
    #endregion Countable
    //phpcs:enable Squiz.Commenting

    //phpcs:disable Squiz.Commenting
    #region Iterator

    public function rewind(): void {
        $this->position = 0;
    }

    public function current() {
        return $this->vars[ $this->keys[ $this->position ] ];
    }

    public function key() {
        return $this->keys[ $this->position ];
    }

    public function next(): void {
        ++$this->position;
    }

    public function valid(): bool {
        return isset( $this->keys[ $this->position ] );
    }

    #endregion Iterator
    //phpcs:enable Squiz.Commenting

    //phpcs:disable Squiz.Commenting
    #region ArrayAccess
    public function offsetSet( $offset, $value ): void {
        if ( \is_null( $offset ) ) {
            $this->vars[] = $value;
            $this->keys[] = \array_key_last( $this->vars );

            return;
        }

        $this->vars[ $offset ] = $value;

        if ( \in_array( $offset, $this->keys, true ) ) {
            return;
        }

        $this->keys[] = $offset;
    }

    public function &offsetGet( $offset ) {
        $this->vars[ $offset ] ??= array();

        return $this->vars[ $offset ];
	}

    public function offsetExists( $offset ): bool {
        return isset( $this->vars[ $offset ] );
    }

    public function offsetUnset( $offset ): void {
        unset( $this->vars[ $offset ] );
        unset( $this->keys[ \array_search( $offset, $this->keys, true ) ] );

        $this->keys = \array_values( $this->keys );
    }
    #endregion ArrayAccess
    //phpcs:enable Squiz.Commenting

    /**
     * Get the json representation of the query vars.
     *
     * @return string
     */
    public function jsonSerialize(): mixed {
        return $this->vars;
    }
}
