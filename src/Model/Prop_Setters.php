<?php //phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

namespace XWC\Data\Model;

use XWC_Data;
use XWC_Data_Store_XT;
use XWC_Meta_Store;

/**
 * Prop setters trait.
 *
 * @template TDt of XWC_Data
 * @template TDs of XWC_Data_Store_XT
 *
 * @method WC_Data_Store<TDs<TDt,XWC_Meta_Store>> get_data_store() Get the data store object.
 */
trait Prop_Setters {
    /**
     * All data for this object. Name value pairs (name + default value).
     *
     * @var array
     */
    protected $data = array();

    abstract protected function get_prop_type( string $prop ): array;

    abstract protected function is_binary_string( string $value ): bool;

    abstract protected function is_base64_string( string $value ): bool;

    /**
     * Set a collection of props in one go, collect any errors, and return the result.
     * Only sets using public methods.
     *
     * @since  3.0.0
     *
     * @param array  $props Key value pairs to set. Key is the prop and should map to a setter function name.
     * @param string $context In what context to run this.
     *
     * @return bool|static|\WP_Error
     */
    public function set_props( $props, $context = 'set' ) {
        $prop_res = parent::set_props( $props, $context );

        if ( 'save' !== $context ) {
            return $prop_res;
        }

        if ( \is_wp_error( $prop_res ) ) {
            return $prop_res;
        }

        $save_res = null;

        try {
            $save_res = $this->save();
        } catch ( \Throwable $e ) {
            $save_res = new \WP_Error( 'save_error', $e->getMessage() );
        } finally {
            return match ( true ) {
                0 === $save_res          => new \WP_Error(
                    'save_error',
                    'An unknown error occurred while saving.',
                ),
                \is_wp_error( $save_res ) => $save_res,
                default                  => $this,
            };
        }
    }

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
        if ( 'id' === \strtolower( $prop ) ) {
            return;
        }

        if ( $this->get_object_read() ) {
            $this->check_unique_prop( $prop, $value );
            $this->check_required_prop( $prop, $value );
            $this->check_value_prop( $prop, $value );
        }

        [ $type, $sub ] = $this->get_prop_type( $prop );

        match ( $type ) {
            'date_created'  => $this->set_date_prop( $prop, $value ),
            'date_updated'  => $this->set_date_prop( $prop, $value ),
            'date'          => $this->set_date_prop( $prop, $value ),
            'bool'          => $this->set_bool_prop( $prop, $value ),
            'bool_int'      => $this->set_bool_prop( $prop, $value ),
            'enum'          => $this->set_enum_prop( $prop, $value, ...$sub ),
            'term_single'   => $this->set_single_term_prop( $prop, $value, ...$sub ),
            'term_array'    => $this->set_array_term_prop( $prop, $value, ...$sub ),
            'array_assoc'   => $this->set_assoc_arr_prop( $prop, $value ),
            'array'         => $this->set_normal_arr_prop( $prop, $value ),
            'binary'        => $this->set_binary_prop( $prop, $value ),
            'base64_string' => $this->set_base64_string_prop( $prop, $value ),
            'json_obj'      => $this->set_json_prop( $prop, $value, false ),
            'json'          => $this->set_json_prop( $prop, $value ),
            'int'           => $this->set_int_prop( $prop, $value ),
            'float'         => $this->set_float_prop( $prop, $value ),
            'slug'          => $this->set_slug_prop( $prop, $value ),
            'string'        => $this->set_wc_data_prop( $prop, $value ),
            default         => $this->set_unknown_prop( $type, $prop, $value ),
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
     * Sets an enum prop
     *
     * @template T of \BackedEnum
     *
     * @param  string            $prop Property name.
     * @param  mixed             $val  Property value.
     * @param  T|class-string<T> $type Enum class.
     */
    protected function set_enum_prop( string $prop, mixed $val, $type = null ) {
        if ( $val instanceof $type ) {
            $this->set_wc_data_prop( $prop, $val );
            return;
        }

        try {
            $val = $type::from( $val );

            $this->set_wc_data_prop( $prop, $val );
        } catch ( \ValueError ) {
            $this->error(
                'invalid_enum_value',
                \sprintf(
                    'The value %s for %s is not a valid enum value.',
                    \esc_html( $val ),
                    \esc_html( $prop ),
                ),
            );
        }
    }

    protected function set_single_term_prop( string $prop, mixed $value, string $field ) {
        $value = (array) $value;
        $value = \array_shift( $value );
        $value = $this->map_term_field( $field, $value );

        $this->set_wc_data_prop( $prop, $value );
    }

    protected function set_array_term_prop( string $prop, mixed $value, string $field ) {
        $value = \array_map(
            fn( $v ) => $this->map_term_field( $field, $v ),
            (array) $value,
        );

        $this->set_wc_data_prop( $prop, $value );
    }

    private function map_term_field( string $field, mixed $value ) {
        if ( $value instanceof \WP_Term ) {
            return $value->$field;
        }

        return match ( $field ) {
            'term_id' => \intval( $value ),
            'parent'  => \intval( $value ),
            'slug'    => \sanitize_title( $value ),
            default   => $value,
        };
    }

    /**
     * Set an array prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     */
    protected function set_normal_arr_prop( string $prop, $value ) {
        $this->set_wc_data_prop( $prop, \wc_string_to_array( $value ) );
    }

    protected function set_assoc_arr_prop( string $prop, $value ) {
        $value = \maybe_unserialize( $value );
        $this->set_wc_data_prop( $prop, $value );
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

    protected function set_base64_string_prop( string $prop, mixed $value ) {
        $value = match ( true ) {
            null === $value                   => null,
            '' === $value                     => null,
            $this->is_base64_string( $value ) => \base64_decode( $value, true ),
            default                           => $value,
        };
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
        $value = $this->get_data_store()->unique_entity_slug( $value, $prop, $this->get_id() );

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
