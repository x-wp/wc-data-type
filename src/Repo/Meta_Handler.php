<?php

namespace XWC\Data\Repo;

use XWC_Data;
use XWC_Meta_Store;

/**
 * Trait for handling metadata for a data model.
 *
 * @template T of XWC_Data
 */
trait Meta_Handler {
    /**
     * Get the metadata store class
     *
     * @return XWC_Meta_Store|null
     */
    abstract public function get_meta_store(): ?XWC_Meta_Store;

    /**
     * Read props stored in meta keys.
     *
     * @param  T $data Data object.
     */
    protected function read_prop_data( &$data ) {
        if ( ! $this->get_meta_store() ) {
            return;
        }

        $props = $this->get_meta_store()->read_meta_props( $data, $this->get_meta_to_props() );

        $data->set_props( $props );
    }

    protected function update_prop_data( &$data ) {
        if ( ! $this->get_meta_store() ) {
            return;
        }

        $this->get_meta_store()->update_meta_props( $data, \array_flip( $this->get_meta_to_props() ) );
    }

    /**
     * Update meta data
     *
    * @param T $data Data Object.
     */
    protected function update_meta_data( &$data ) {
        if ( ! $this->get_meta_store() ) {
            return;
        }

        $data->save_meta_data();
    }

    /**
     * Read props stored in meta keys.
     *
     * @param  T $data Data object.
     */
    protected function read_extra_data( &$data ) {
        if ( ! $this->get_meta_store() ) {
            return;
        }
        foreach ( $data->get_extra_data_keys() as $key ) {
            try {
                $this->read_extra_prop( $data, $key );
            } catch ( \Throwable ) {
                continue;
            }
        }
    }

    protected function read_extra_prop( &$data, string $key ) {
        $mid  = $this->get_meta_store()->get_meta_id_by_key( $data, '_' . $key );
        $meta = $this->get_meta_store()->get_metadata_by_id( $mid );

        if ( ! \is_object( $meta ) ) {
            return;
        }

        $data->{"set_$key"}( $meta->meta_value );
    }

    protected function update_extra_data( &$data ) {
        if ( ! $this->get_meta_store() ) {
            return;
        }
        $extra = $data->get_extra_data_keys();

        $mtk = \array_combine(
            $extra,
            \array_map( array( $this, 'prefix_key' ), $extra ),
        );

        try {
            $this->get_meta_store()->update_meta_props( $data, $mtk );
        } catch ( \Throwable ) {
            return;
        }
    }

    /**
     * Read props stored in meta keys.
     *
     * @param  T      $data Data object.
     * @param  object $meta
     * @return int|false
     */
    public function add_meta( &$data, $meta ) {
        if ( ! $this->get_meta_store() ) {
            return false;
        }

        return $this->get_meta_store()->add_meta( $data, $meta );
    }

    /**
     * Update meta.
     *
     * @param  T        $data Data object.
     * @param \stdClass $meta
     */
    public function update_meta( &$data, $meta ) {
        if ( ! $this->get_meta_store() ) {
            return;
        }

        return $this->get_meta_store()->update_meta( $data, $meta );
    }

    /**
     * Read meta.
     *
     * @param  T        $data Data object.
     */
    public function read_meta( &$data ) {
        if ( ! $this->get_meta_store() ) {
            return array();
        }

        $raw_meta = $this->get_meta_store()->read_meta( $data );

        return $this->filter_raw_meta_data( $data, $raw_meta );
    }

    /**
     * Delete meta.
     *
     * @param  T     $data Data object.
     * @param  object $meta Meta object (containing at least ->id).
     * @return array
     */
    public function delete_meta( &$data, $meta ) {
        if ( ! $this->get_meta_store() ) {
            return array();
        }

        return array( $this->get_meta_store()->delete_meta( $data, $meta ) );
    }

    public function delete_all_meta( &$data ) {
        if ( ! $this->get_meta_store() ) {
            return;
        }

        return $this->get_meta_store()->delete_all_meta( $data );
    }
}
