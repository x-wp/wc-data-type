<?php

namespace XWC\Data\Repo;

use XWC_Data;

/**
 * Handles data queries.
 *
 * @template T of XWC_Data
 */
trait Query_Handler {
    protected function get_data_query_args( array $vars ): array {
        $vars = $this->get_base_query_args( $vars );
        $vars = $this->get_core_query_args( $vars );
        $vars = $this->get_meta_query_args( $vars );

        return $vars;
    }

    protected function get_base_query_args( array $vars ): array {
        $vars['id_field'] = $this->get_id_field();
        $vars['table']    = $this->get_table();

        $vars['fields']   = $vars['return'] ?? 'ids';
        $vars['per_page'] = $vars['limit'] ?? 20;

        return \xwp_array_diff_assoc( $vars, 'return', 'limit' );
    }

    protected function is_date_prop( $prop ) {
        $type = $this->get_object_args()['prop_types'][ $prop ] ?? 'string';

        return \str_starts_with( $type, 'date' );
    }

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
     * Query for objects.
     *
     * @param  array $vars
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
     * Get the query object.
     *
     * @param  array                       $vars
     * @return \XWC_Object_Query|\stdClass{objects: array<int|array<string,mixed>>, total: int, pages: int}
     */
    private function get_query( array $vars ): \XWC_Object_Query|\stdClass {
        if ( 0 === \count( $vars['errors'] ?? array() ) ) {
            return new \XWC_Object_Query( ...$vars );
        }

        $res = new \stdClass();

        $res->objects = array();
        $res->total   = 0;
        $res->pages   = 0;

        return $res;
    }

    /**
     * Count objects.
     *
     * @param  array $vars
     * @return int
     */
    public function count( array $vars ): int {
        $vars  = $this->get_data_query_args( $vars );
        $query = new \XWC_Object_Query( ...$vars );

        return $query->total;
    }

    /**
     * Remap object rows to object instances.
     *
     * @param  array $objects
     * @return array<T>
     */
    protected function remap_objects( array $objects ): array {
        $cname = \xwc_get_object_classname( 0, $this->get_object_type() );
        return \array_map(
            fn( $obj ) => new $cname( $this->remap_columns( (array) $obj, true ) ),
            $objects,
        );
    }
}
