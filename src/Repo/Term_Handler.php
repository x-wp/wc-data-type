<?php

namespace XWC\Data\Repo;

use XWC_Data;

/**
 * Handles term data.
 *
 * @template T of XWC_Data
 */
trait Term_Handler {
    /**
     * Term props
     *
     * @var array
     */
    protected $term_props = array();

    protected array $must_exist_term_props = array();

    protected array $default_term_ids = array();

    public array $term_query_vars = array();

    /**
	 * Get and store terms from a taxonomy.
	 *
	 * @param  T|int  $data     Object or object ID.
	 * @param  string $taxonomy Taxonomy name e.g. product_cat.
	 * @return array of terms
	 */
	protected function get_term_ids( $data, $taxonomy ) {
		$object_id = \is_numeric( $data ) ? $data : $data->get_id();
        /**
         * Variable override
         *
         * @var array<int>|\WP_Error $terms
         */
		$terms = \wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids' ) );

        return ! \is_wp_error( $terms ) ? $terms : array();
	}

    protected function get_term_prop_data( array $terms, string $prop ): array {
        $terms = \wp_parse_id_list( $terms );
        $terms = \array_filter( $terms );

        if ( ! \in_array( $prop, $this->must_exist_term_props, true ) ) {
            return $terms;
        }

        return \count( $terms ) <= 0 ? $this->default_term_ids[ $prop ] : $terms;
    }

    /**
     * For all stored terms in all taxonomies save them to the DB.
     *
     * @param T $data Data Object.
     * @param bool    $force  Force update. Used during create.
     */
    protected function update_terms( &$data, $force = false ) {
        if ( ! $this->term_props ) {
            return;
        }

        $changes = $data->get_changes();

        foreach ( $this->term_props as $term_prop => $taxonomy ) {
            if ( ! $force && ! isset( $changes[ $term_prop ] ) ) {
                continue;
            }

            $terms = $data->{"get_$term_prop"}( 'edit' );
            $terms = $this->get_term_prop_data( (array) $terms, $term_prop );

            \wp_set_object_terms( $data->get_id(), $terms, $taxonomy, false );

            $data->{"set_$term_prop"}( $terms );
        }
    }

    protected function delete_terms( int $object_id ) {
        if ( \count( $this->term_props ) <= 0 ) {
            return;
        }

        \wp_delete_object_term_relationships( $object_id, \array_values( $this->term_props ) );
    }
}
