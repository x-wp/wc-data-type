<?php

namespace XWC\Data\Repo;

use XWC_Data;

/**
 * Handles lookup table data.
 *
 * @template T of XWC_Data
 */
trait Lookup_Handler {
    /**
     * Array of updated props
     *
     * @var string[]
     */
	protected array $updated_props = array();

    /**
     * Lookup table data keys
     *
     * @var string[]
     */
	protected array $lookup_data_keys = array();

    /**
     * Get the lookup table for the data store.
     *
     * @return string|null Database table name.
     */
    protected function get_lookup_table_name(): ?string {
        return null;
    }

    /**
     * Handle updated meta props after updating entity meta.
     *
     * @param T $data Data object.
     */
	protected function handle_updated_props( &$data ) {
		if ( \array_intersect( $this->updated_props, $this->lookup_data_keys ) && ! \is_null(
            $this->get_lookup_table_name(),
        ) ) {
            $this->update_lookup_table( $data->get_id(), $this->get_lookup_table_name() );
		}

        $this->updated_props = array();
	}
}
