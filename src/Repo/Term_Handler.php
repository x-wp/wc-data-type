<?php

namespace XWC\Data\Repo;

use WP_Term;
use XWC_Data;

/**
 * Handles term data.
 *
 * @template T of XWC_Data
 */
trait Term_Handler {
    /**
     * Taxonomy to property mapping.
     *
     * @var array<string,string>
     */
    protected array $tax_to_props = array();

    /**
     * Properties that must exist for terms.
     *
     * @var array<string>
     */
    protected array $must_exist_term_props = array();

    /**
     * Default term IDs for properties that must exist.
     *
     * @var array<string,array<int>>
     */
    protected array $default_term_ids = array();

    /**
     * Read term data.
     *
     * @param T $data Data object.
     */
    protected function read_term_data( \XWC_Data &$data ): void {
        foreach ( $this->get_tax_to_props() as $tax => $prop ) {
            $data->{"set_$prop"}( $this->get_terms( $data, $tax ) );
        }
    }

    /**
     * Update term data.
     *
     * @param  T    $data Data object.
     * @param  bool $force Force update even if no changes.
     */
    protected function update_term_data( \XWC_Data &$data, bool $force = false ): void {
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
     * @return array<WP_Term>
     */
    protected function get_terms( \XWC_Data $data, string $taxonomy ) {
        $terms = \wp_get_object_terms( $data->get_id(), $taxonomy );

        return ! \is_wp_error( $terms ) ? $terms : array();
    }

    /**
     * Get the prop data for a set of terms.
     *
     * @param  array<int|string> $terms Array of term IDs.
     * @param  string $prop Property name.
     * @return array<int>
     */
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
    protected function delete_term_data( \XWC_Data $data ): void {
        $taxonomies = \array_keys( $this->get_tax_to_props() );

        if ( ! $taxonomies ) {
            return;
        }
        \wp_delete_object_term_relationships( $data->get_id(), $taxonomies );
    }
}
