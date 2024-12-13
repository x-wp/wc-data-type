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

    protected array $tax_to_props = array();


    protected array $must_exist_term_props = array();

    protected array $default_term_ids = array();

    public array $term_query_vars = array();

    protected function read_term_data( \XWC_Data &$data ) {
        foreach ( $this->get_tax_to_props() as $tax => $prop ) {
            $data->{"set_$prop"}( $this->get_terms( $data, $tax ) );
        }
    }

    /**
     * Update term data.
     *
     * @param  T $data
     */
    protected function update_term_data( \XWC_Data &$data, bool $force = false ) {
        $changes = $data->get_changes();

        foreach ( $this->get_tax_to_props() as $tax => $prop ) {
            if ( ! $force && ! isset( $changes[ $prop ] ) ) {
                continue;
            }

            $terms = $data->{"get_$prop"}( 'db' );

            \wp_set_object_terms( $data->get_id(), \wp_list_pluck( $terms, 'term_id' ), $tax, false );
        }
    }

    /**
     * Get terms for a taxonomy.
     *
     * @param  T $data Data object.
     * @param  string $taxonomy Taxonomy.
     * @return array
     */
    protected function get_terms( \XWC_Data $data, string $taxonomy ) {
        $terms = \wp_get_object_terms( $data->get_id(), $taxonomy );

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
     * Delete term data.
     *
     * @param  \XWC_Data $data Data object.
     */
    protected function delete_term_data( \XWC_Data $data ) {
        $taxonomies = \array_keys( $this->get_tax_to_props() );

        if ( ! $taxonomies ) {
            return;
        }
        \wp_delete_object_term_relationships( $data->get_id(), $taxonomies );
    }
}
