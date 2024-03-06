<?php
/**
 * Data_Type_Meta trait file.
 *
 * @package eXtended WooCommerce
 * @subpackage Traits
 */

namespace XWC\Traits;

/**
 * Trait for metadata handling.
 */
trait Data_Type_Meta {
    /**
     * Metadata array. Keyed by data type.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $metadata = array();

    /**
     * Data type
     *
     * @var string
     */
    protected string $data_type;

    /**
     * Needed metadata keys for the data type.
     *
     * @return array<int, string>
     */
    abstract protected function get_metadata_keys(): array;

    /**
     * Initialize metadata.
     *
     * @param  bool $set_props If true, set the properties.
     */
    protected function init_metadata( bool $set_props = true ): void {
        $dt  = $this->data_type;
        $dto = \xwc_get_data_type_object( $dt );

        foreach ( $this->get_metadata_keys() as $prop ) {
            static::$metadata[ $dt ][ $prop ] ??= $dto->{"get_$prop"}() ?? $dto->$prop;

            if ( ! $set_props ) {
                continue;
            }

            $this->$prop = &static::$metadata[ $dt ][ $prop ];
        }
    }

    /**
     * Get metadata for the data type.
     *
     * @param  string $name Metadata key.
     */
    protected function get_data_type_meta( string $name ) {
        if ( ! \in_array( $name, $this->get_metadata_keys(), true ) ) {
            return null;
        }

        return static::$metadata[ $this->data_type ][ $name ];
    }
}
