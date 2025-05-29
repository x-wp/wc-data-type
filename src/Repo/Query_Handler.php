<?php //phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query

namespace XWC\Data\Repo;

use stdClass;
use XWC_Data;

/**
 * Handles data queries.
 *
 * @template T of XWC_Data
 */
trait Query_Handler {
    /**
     * Find an object by ID.
     *
     * @param  array<string,mixed> $vars Query arguments.
     * @return T
     */
    public function find( array $vars ): object {
        $vars['limit']       = 1;
        $vars['return']      = 'ids';
        $vars['paginate']    = false;
        $vars['count_found'] = false;

        /**
         * Variable override.
         *
         * @var int $found
         */
        $found = $this->query( $vars )[0] ?? 0;
        /**
         * Object instance.
         *
         * @var T $data
         */
        $data = \xwc_get_object_instance( $found, $this->get_object_type() );

        return $data;
    }

    /**
     * Query for objects.
     *
     * @param  array<string,mixed> $vars Query arguments.
     * @return array<T|int>|array{objects: array<T|int>, pages: int, total: int}
     */
    public function query( array $vars ): array {
        $retn = $vars['return'] ?? 'ids';
        $vars = $this->get_data_query_args( $vars );

        $query = $this->get_query( $vars );

        $objects = 'ids' !== $retn ? $this->remap_objects( $query->objects ) : $query->objects;

        if ( ! ( $vars['paginate'] ?? false ) ) {
            return $objects;
        }

        return array(
            'objects' => $objects,
            'pages'   => $query->pages,
            'total'   => $query->total,
        );
    }

    /**
     * Count objects.
     *
     * @param  array<string,mixed> $vars Query arguments.
     * @return int
     */
    public function count( array $vars ): int {
        $vars  = $this->get_data_query_args( $vars );
        $query = new \XWC_Object_Query( ...$vars );

        return $query->total;
    }

    /**
     * Get the query arguments for the data query.
     *
     * @param  array<string,mixed> $vars Query arguments.
     * @return array<string,mixed>
     */
    protected function get_data_query_args( array $vars ): array {
        $vars = $this->get_base_query_args( $vars );
        $vars = $this->get_core_query_args( $vars );
        $vars = $this->get_tax_query_args( $vars );
        $vars = $this->get_meta_query_args( $vars );

        return $vars;
    }

    /**
     * Get the base query arguments.
     *
     * @param  array<string,mixed> $vars Query arguments.
     * @return array<string,mixed>
     */
    protected function get_base_query_args( array $vars ): array {
        $vars['id_field'] = $this->get_id_field();
        $vars['table']    = $this->get_table();

        $vars['fields']   = $vars['return'] ?? 'ids';
        $vars['per_page'] = $vars['limit'] ?? 20;
        $vars['orderby']  = isset( $vars['orderby'] )
            ? $this->cols_to_props[ $vars['orderby'] ] ?? $vars['orderby']
            : $this->get_id_field();

        return \xwp_array_diff_assoc( $vars, 'return', 'limit' );
    }

    /**
     * Is the property a date type?
     *
     * @param  string $prop Property name.
     * @return bool
     */
    protected function is_date_prop( string $prop ): bool {
        $type = $this->get_object_args()['prop_types'][ $prop ] ?? 'string';

        return \str_starts_with( $type, 'date' );
    }

    /**
     * Get the core query arguments.
     *
     * @param  array<string,mixed> $vars Query arguments.
     * @return array<string,mixed>
     */
    protected function get_core_query_args( array $vars ): array {
        $dates = array();
        $colq  = array();

        foreach ( $this->get_cols_to_props() as $prop => $col ) {
            if ( ! isset( $vars[ $prop ] ) ) {
                continue;
            }

            if ( $this->is_date_prop( $prop ) ) {
                $dates[] = $prop;
            }

            $colq[ $col ] = $vars[ $prop ];
            unset( $vars[ $prop ] );
        }

        $vars['col_query'] = $colq;

        return $this->get_date_query_args( $vars, $dates );
    }

    /**
     * Get the date query arguments.
     *
     * @param  array<string,mixed> $vars Query arguments.
     * @param  array<string>       $dates Date properties.
     * @return array<string,mixed>
     */
    protected function get_date_query_args( array $vars, array $dates ): array {
        if ( ! $dates ) {
            return $vars;
        }

        foreach ( $dates as $index => $key ) {
            $vars = $this->parse_date_for_wp_query( $vars['col_query'][ $key ], 'post_date', $vars );

            $vars['date_query'][ $index ]['column'] = "{$this->get_table()}.{$key}";

            unset( $vars['col_query'][ $key ] );
        }

        return $vars;
    }

    /**
     * Get the meta query arguments.
     *
     * @param  array<string,mixed> $vars Query arguments.
     * @return array<string,mixed>
     */
    protected function get_meta_query_args( array $vars ): array {
        $keys = array(
            'parent',
            'parent_exclude',
            'exclude',
            'limit',
            'type',
            'return',
        );

        return parent::get_wp_query_args( $vars );
    }

    /**
     * Get the taxonomy query arguments.
     *
     * @param  array<string,mixed> $vars Query arguments.
     * @return array<string,mixed>
     */
    protected function get_tax_query_args( array $vars ): array {
        $query = array();

        foreach ( $this->get_tax_to_props() as $tax => $prop ) {
            if ( ! isset( $vars[ $prop ] ) ) {
                continue;
            }

            $query[] = $this->get_tax_query_arg( (array) $vars[ $prop ], $tax );

            unset( $vars[ $prop ] );
        }

        $query = \array_filter( $query );

        if ( $query ) {
            $vars['tax_query'] = $query;
        }

        return $vars;
    }

    /**
     * Remap object rows to object instances.
     *
     * @param  array<object> $objects
     * @return array<T>
     */
    protected function remap_objects( array $objects ): array {
        /**
         * Object class name.
         *
         * @var class-string<T> $cname
         */
        $cname = \xwc_get_object_classname( 0, $this->get_object_type() );

        return \array_map(
            fn( $obj ) => new $cname( $this->remap_columns( (array) $obj, true ) ),
            $objects,
        );
    }

    /**
     * Get the taxonomy query argument for a specific taxonomy.
     *
     * @param  array<string> $query Taxonomy query terms.
     * @param  string            $tax   Taxonomy name.
     * @return array<int<0,max>|string, array<string, array<int,string|null>|string>|string>
     */
    private function get_tax_query_arg( array $query, string $tax ): array {
        $terms = array(
            'IN'     => array(),
            'NOT IN' => array(),
        );
        $res   = array();

        foreach ( $query as $term ) {
            $key = \str_starts_with( $term, '!' ) ? 'NOT IN' : 'IN';

            $terms[ $key ][] = \preg_replace( '/^!/', '', $term );
        }

        foreach ( \array_filter( $terms ) as $key => $value ) {
            $res[] = array(
                'field'    => $this->tax_fields[ $tax ],
                'operator' => $key,
                'taxonomy' => $tax,
                'terms'    => $value,
            );
        }

        if ( $res ) {
            $res['relation'] = 'OR';
        }

        return $res;
    }

    /**
     * Get the query object.
     *
     * @param  array<string,mixed> $vars Query arguments.
     * @return \XWC_Object_Query|stdClass{objects: array<int|array<string,mixed>>, total: int, pages: int}
     */
    private function get_query( array $vars ): \XWC_Object_Query|stdClass {
        if ( 0 === \count( $vars['errors'] ?? array() ) ) {
            return new \XWC_Object_Query( ...$vars );
        }

        $res = new \stdClass();

        $res->objects = array();
        $res->total   = 0;
        $res->pages   = 0;

        return $res;
    }
}
