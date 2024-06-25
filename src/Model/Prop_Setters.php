<?php

namespace XWC\Data\Model;

trait Prop_Setters {
    /**
	 * All data for this object. Name value pairs (name + default value).
	 *
	 * @var array
	 */
	protected $data = array();

    abstract protected function get_prop_type( string $prop ): string;

    abstract protected function is_binary_string( string $value ): bool;

    /**
	 * Sets a prop for a setter method.
	 *
	 * This stores changes in a special array so we can track what needs saving
	 * the DB later.
	 *
	 * @since 3.0.0
	 * @param string $prop Name of prop to set.
	 * @param mixed  $value Value of the prop.
	 */
	protected function set_prop( $prop, $value ) {
		$type = $this->get_prop_type( $prop );

		if ( \in_array( $prop, $this->unique_data, true ) && $this->get_object_read() ) {
			$this->check_unique_prop( $prop, $value );
		}

		match ( $type ) {
			'date_created' => $this->set_date_prop( $prop, $value ),
            'date_updated' => $this->set_date_prop( $prop, $value ),
            'date'         => $this->set_date_prop( $prop, $value ),
			'bool'         => $this->set_bool_prop( $prop, $value ),
			'bool_int'     => $this->set_bool_prop( $prop, $value ),
			'array'        => $this->set_array_prop( $prop, $value ),
			'array_raw'    => $this->set_array_prop( $prop, $value ),
			'binary'       => $this->set_binary_prop( $prop, $value ),
			'json_obj'     => $this->set_json_prop( $prop, $value, false ),
			'json'         => $this->set_json_prop( $prop, $value ),
			'int'          => $this->set_int_prop( $prop, $value ),
			'float'        => $this->set_float_prop( $prop, $value ),
            'slug'         => $this->set_slug_prop( $prop, $value ),
            'string'       => $this->set_wc_data_prop( $prop, $value ),
			default        => $this->set_unknown_prop( $type, $prop, $value ),
		};
	}

    /**
     * Direct access to the `WC_Data::set_prop` method.
     *
     * We sometimes need to access the basic `set_prop` method.
     *
     * @param  string $prop  Prop name.
     * @param  mixed  $value Prop value.
     */
    protected function set_wc_data_prop( $prop, $value ) {
        if ( ! \array_key_exists( $prop, $this->data ) ) {
            return;
        }

        if ( ! $this->object_read ) {
            $this->data[ $prop ] = $value;
            return;
        }

        if ( $value === $this->data[ $prop ] && ! \array_key_exists( $prop, $this->changes ) ) {
            return;
        }

        $this->changes[ $prop ] = $value;
    }

    /**
     * Set a date prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
	protected function set_date_prop( $prop, $value ) {
		static $loop;

        if ( ! $loop ) {
            $loop = true;
            return parent::set_date_prop( $prop, $value );
        }

        $loop = false;

        $this->set_wc_data_prop( $prop, $value );
	}

    /**
     * Set a boolean prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
	protected function set_bool_prop( string $prop, $value ) {
		if ( '' === $value ) {
			return;
		}

		$this->set_wc_data_prop( $prop, \wc_string_to_bool( $value ) );
	}

    /**
     * Set an array prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
	protected function set_array_prop( string $prop, $value ) {
		$this->set_wc_data_prop( $prop, \wc_string_to_array( $value ) );
	}

    /**
     * Set a binary prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
	protected function set_binary_prop( string $prop, $value ) {
		$value = $this->is_binary_string( $value )
            ? \bin2hex( $value )
            : $value;

		$this->set_wc_data_prop( $prop, $value );
	}

    /**
     * Set a json prop
     *
     * @param  string       $prop  Property name.
     * @param  string|array $value Property value.
     * @param  bool         $assoc Whether to return an associative array or not.
     */
	protected function set_json_prop( string $prop, string|array $value, bool $assoc = true ) {
        if ( ! \is_array( $value ) ) {
            $value = \json_decode( $value, $assoc );
        }
		$this->set_wc_data_prop( $prop, $value );
	}

    /**
     * Set an int prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
	protected function set_int_prop( string $prop, $value ) {
		$this->set_wc_data_prop( $prop, \intval( $value ) );
	}

    /**
     * Set a float prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
	protected function set_float_prop( string $prop, $value ) {
		$this->set_wc_data_prop( $prop, \floatval( $value ) );
	}

    protected function set_slug_prop( string $prop, $value ) {
        if ( ! $this->get_object_read() || '' === $value ) {
            return $this->set_wc_data_prop( $prop, $value );
        }

        $value = \sanitize_title( $value );
        $value = $this->data_store->unique_entity_slug( $value, $prop, $this->get_id() );

        $this->set_wc_data_prop( $prop, $value );
    }

    /**
     * Set an unknown prop type
     *
     * @param  string $type  Property type.
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
    protected function set_unknown_prop( string $type, string $prop, mixed $value ) {
        if ( ! \method_exists( $this, "set_{$type}_prop" ) ) {
            return $this->set_wc_data_prop( $prop, $value );

        }

        $this->{"set_{$type}_prop"}( $prop, $value );
    }
}
