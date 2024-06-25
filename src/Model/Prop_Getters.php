<?php

namespace XWC\Data\Model;

trait Prop_Getters {
    /**
     * Array of core data keys.
     *
     * Core data keys are the keys that are stored in the main table.
     *
     * @var array<string, mixed>
     */
    protected array $core_data = array();

    /**
     * Array linking props to their types.
     *
     * @var array<string, string>
     */
    protected array $prop_types = array();

    /**
     * Props that should be unique.
     *
     * @var array<string>
     */
    protected array $unique_data = array();

    protected function is_binary_string( string $value ): bool {
        //phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return ! @\mb_check_encoding( $value, 'UTF-8' );
    }

    public function get_prop_group( string $prop ): string {
        return match ( true ) {
            isset( $this->core_data[ $prop ] )  => 'core',
            isset( $this->extra_data[ $prop ] ) => 'extra',
            isset( $this->meta_data[ $prop ] )  => 'meta',
            default => 'none',
        };
    }

    /**
     * Get prop types
     *
     * @return array
     */
    public function get_prop_types(): array {
        return $this->prop_types;
    }

    protected function get_prop_type( string $prop ): string {
        return $this->prop_types[ $prop ] ?? 'string';
    }

    protected function get_prop_by_type( string $type ): null|string|array {
        $types = \array_filter( $this->get_prop_types(), static fn( $t ) => $t === $type );
        $types = \array_keys( $types );

        return match ( \count( $types ) ) {
            0       => null,
            1       => $types[0],
            default => $types,
        };
    }

    public function get_core_keys(): array {
        return \array_keys( $this->core_data );
    }

    /**
     * Get core data
     *
     * @return array<string, mixed>
     */
    public function get_core_data( string $context = 'db', bool $include_id = false, ): array {
        $data = array();

        foreach ( $this->get_core_keys() as $key ) {
            $data[ $key ] = $this->get_prop( $key, $context );
        }

        if ( $include_id ) {
            $data['id'] = $this->get_id();
        }

        return $data;
    }

    public function get_core_changes(): array {
        $changed = array();
        $props   = \array_intersect( $this->get_core_keys(), \array_keys( $this->changes ) );

        if ( 0 === \count( $props ) ) {
            return $changed;
        }

        foreach ( $props as $prop ) {
            $changed[ $prop ] = $this->get_prop( $prop, 'db' );
        }

        return $changed;
    }

    /**
	 * Gets a prop for a getter method.
	 *
	 * Gets the value from either current pending changes, or the data itself.
	 * Context controls what happens to the value before it's returned.
	 *
	 * @since  3.0.0
	 * @param  string $prop Name of prop to get.
	 * @param  string $context What the value is for. Valid values are view, edit and db.
	 * @return mixed
	 */
	protected function get_prop( $prop, $context = 'view' ) {
        if ( 'db' !== $context ) {
            return $this->get_wc_data_prop( $prop, $context );
        }

		$type  = $this->get_prop_type( $prop );
        $value = $this->get_wc_data_prop( $prop, 'edit' );

        return match ( $type ) {
            'string'       => $value,
            'date'         => $this->get_date_prop( $value ),
            'date_created' => $this->get_date_prop( $value ),
            'date_updated' => $this->get_date_prop( $value ),
            'bool'         => $this->get_bool_prop( $value, 'string' ),
            'bool_int'     => $this->get_bool_prop( $value, 'int' ),
            'array'        => $this->get_array_prop( $value, 'serialize' ),
            'array_raw'    => $this->get_array_prop( $value, 'string' ),
            'json'         => $this->get_json_prop( $value ),
            'json_obj'     => $this->get_json_prop( $value, \JSON_FORCE_OBJECT ),
            'binary'       => $this->get_binary_prop( $value ),
            default        => $this->get_unknown_prop( $type, $prop, $value ),
        };
	}

    protected function get_wc_data_prop( string $prop, string $context = 'view' ): mixed {
        return parent::get_prop( $prop, $context );
    }

    protected function get_date_prop( ?\WC_DateTime $value ): ?string {
        if ( ! $value ) {
            return null;
        }

        return \gmdate( 'Y-m-d H:i:s', $value->getOffsetTimestamp() );
    }

    protected function get_bool_prop( $value, $format = 'string' ) {
        return match ( $format ) {
            'string'  => \wc_bool_to_string( $value ),
            'int'     => (int) \wc_string_to_bool( $value ),
            default   => (bool) $value,
        };
    }

    protected function get_array_prop( $value, $format = 'serialize' ) {
        return match ( $format ) {
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
            'serialize' => \serialize( $value ),
            'string'    => \wc_string_to_array( $value ),
            default     => $value,
        };
    }

    protected function get_json_prop( $value, int $flags = 0 ): string {
        return \wp_json_encode( $value, $flags );
    }

    protected function get_binary_prop( $value ): string {
        return ! $this->is_binary_string( $value ) ? \hex2bin( $value ) : $value;
    }

    protected function get_unknown_prop( string $type, string $prop, mixed $value ): mixed {
        if ( \method_exists( $this, "get_{$type}_prop" ) ) {
            $value = $this->{"get_{$type}_prop"}( $value );
        }

        /**
		 * Filters the default value for an unknown prop type.
		 *
		 * @param mixed  $value The default value.
		 * @param string $prop  The prop name.
		 *
		 * @return mixed
		 *
		 * @since 0.2
		 */
		return \apply_filters( "xwc_data_get_{$type}_prop", $value, $prop );
    }
}
