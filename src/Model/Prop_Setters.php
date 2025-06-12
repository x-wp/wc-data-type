<?php //phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

namespace XWC\Data\Model;

use BackedEnum;
use WC_Data_Exception;
use XWC_Data;
use XWC_Data_Store_XT;
use XWC_Meta_Store;
use XWC_Prop;

/**
 * Prop setters trait.
 *
 * @template TDt of XWC_Data
 *
 * @method XWC_Data_Store_XT<TDt,XWC_Meta_Store<TDt>> get_data_store() Get the data store.
 *
 * @phpstan-require-extends XWC_Data
 */
trait Prop_Setters {
    /**
     * Get the type of a prop.
     *
     * @param  string $prop Name of prop to get type for.
     * @return array{
     *   0: 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array_set'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'other'|string|class-string,
     *   1: array<int,mixed>
     * } | array{0: 'enum', 1: array{0: class-string<BackedEnum>}}
     */
    abstract protected function get_prop_type( string $prop ): array;

    abstract protected function is_binary_string( string $value ): bool;

    abstract protected function is_base64_string( string $value ): bool;

    /**
     * Set a collection of props in one go, collect any errors, and return the result.
     * Only sets using public methods.
     *
     * @since  3.0.0
     *
     * @param array<string,mixed>  $props Key value pairs to set. Key is the prop and should map to a setter function name.
     * @param string $context In what context to run this.
     *
     * @return true|static|\WP_Error
     */
    public function set_props( $props, $context = 'set' ) {
        $prop_res = parent::set_props( $props, $context );

        if ( 'save' !== $context || \is_wp_error( $prop_res ) ) {
            return $prop_res;
        }

        $save_res = null;

        try {
            $save_res = $this->save();
        } catch ( \Throwable $e ) {
            $save_res = new \WP_Error( 'save_error', $e->getMessage() );
        } finally {
            return match ( true ) {
                0 === $save_res           => new \WP_Error(
                    'save_error',
                    'An unknown error occurred while saving.',
                ),
                \is_wp_error( $save_res ) => $save_res,
                default                   => $this,
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
     * @param  string $prop Name of prop to set.
     * @param  mixed  $value Value of the prop.
     * @return static
     */
    protected function set_prop( $prop, $value ): static {
        if ( 'id' === \strtolower( $prop ) ) {
            return $this;
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
            'array_set'     => $this->set_unique_arr_prop( $prop, $value ),
            'binary'        => $this->set_binary_prop( $prop, $value ),
            'base64_string' => $this->set_base64_string_prop( $prop, $value ),
            'json_obj'      => $this->set_json_prop( $prop, $value, false ),
            'json'          => $this->set_json_prop( $prop, $value ),
            'int'           => $this->set_int_prop( $prop, $value ),
            'float'         => $this->set_float_prop( $prop, $value ),
            'slug'          => $this->set_slug_prop( $prop, $value ),
            'string'        => $this->set_wc_data_prop( $prop, $value ),
            'object'        => $this->set_object_prop( $prop, $value, ...$sub ),
            default         => $this->set_unknown_prop( $type, $prop, $value ),
        };

        return $this;
    }

    /**
     * Direct access to the `WC_Data::set_prop` method.
     *
     * We sometimes need to access the basic `set_prop` method.
     *
     * @param  string $prop  Prop name.
     * @param  mixed  $value Prop value.
     * @return void
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
     * @return void
     */
    protected function set_date_prop( $prop, $value ) {
        static $loop;

        if ( ! $loop ) {
            $loop = true;
            parent::set_date_prop( $prop, $value );
            return;
        }

        $loop = false;

        $this->set_wc_data_prop( $prop, $value );
    }

    /**
     * Set a boolean prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @return void
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
     * @param  null|T|class-string<T> $type Enum class.
     * @return void
     */
    protected function set_enum_prop( string $prop, mixed $val, null|string|BackedEnum $type = null ) {
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

    /**
     * Set a single term prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @param  string $field Field to map the term to.
     * @return void
     */
    protected function set_single_term_prop( string $prop, mixed $value, string $field ) {
        $value = (array) $value;
        $value = \array_shift( $value );
        $value = $this->map_term_field( $field, $value );

        $this->set_wc_data_prop( $prop, $value );
    }

    /**
     * Set an array term prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @param  string $field Field to map the term to.
     * @return void
     */
    protected function set_array_term_prop( string $prop, mixed $value, string $field ) {
        $value = \array_map(
            fn( $v ) => $this->map_term_field( $field, $v ),
            (array) $value,
        );

        $this->set_wc_data_prop( $prop, $value );
    }

    /**
     * Set an array prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @return void
     */
    protected function set_normal_arr_prop( string $prop, $value ) {
        $this->set_wc_data_prop( $prop, \wc_string_to_array( $value ) );
    }

    /**
     * Set an array prop with unique values
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @return void
     */
    protected function set_unique_arr_prop( string $prop, $value ) {
        $value = \array_values( \array_unique( \wc_string_to_array( $value ) ) );

        $this->set_wc_data_prop( $prop, $value );
    }

    /**
     * Set an associative array prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @return void
     */
    protected function set_assoc_arr_prop( string $prop, $value ) {
        $value = \maybe_unserialize( $value );
        $this->set_wc_data_prop( $prop, $value );
    }

    /**
     * Set a binary prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @return void
     */
    protected function set_binary_prop( string $prop, $value ) {
        $value = $this->is_binary_string( $value )
            ? \bin2hex( $value )
            : $value;

        $this->set_wc_data_prop( $prop, $value );
    }

    /**
     * Set a base64 string prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @return void
     */
    protected function set_base64_string_prop( string $prop, mixed $value ) {
        $value = match ( true ) {
            null === $value                   => null,
            '' === $value                     => null,
            $this->is_base64_string( $value ) => \base64_decode( $value, true ),
            default                           => $value,
        };

        $this->set_wc_data_prop( $prop, $value );
    }

    /**
     * Set a json prop
     *
     * @param  string              $prop  Property name.
     * @param  string|array<mixed> $value Property value.
     * @param  bool                $assoc Whether to return an associative array or not.
     * @return void
     */
    protected function set_json_prop( string $prop, string|array $value, bool $assoc = true ) {
        if ( ! \is_array( $value ) ) {
            $value = \json_decode( $value, $assoc );
        }
        $this->set_wc_data_prop( $prop, $value );
    }

    /**
     * Set an object prop
     *
     * @template TObj of XWC_Prop<string,mixed>
     *
     * @param  string $prop
     * @param  mixed  $value
     * @param  class-string<TObj> $cname Class name to parse the value into.
     */
    protected function set_object_prop( string $prop, mixed $value, string $cname = XWC_Prop::class ): void {
        $data = match ( true ) {
            \is_array( $value )              => $value,
            \is_string( $value )             => \json_decode( $value, true ) ?? array(),
            \is_a( $value, XWC_Prop::class ) => $value,
            default                          => array(),
        };

        /**
         * If the object is not read, we need to get the prop from the data store.
         *
         * @var TObj $obj
         */
        $obj = $this->get_object_read()
            ? $this->get_prop( $prop )?->with_data( $data ) ?? new $cname( $data )
            : new $cname( $data );

        $this->set_wc_data_prop( $prop, $obj );
    }

    /**
     * Set an int prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @return void
     */
    protected function set_int_prop( string $prop, $value ) {
        $this->set_wc_data_prop( $prop, \intval( $value ) );
    }

    /**
     * Set a float prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @return void
     */
    protected function set_float_prop( string $prop, $value ) {
        $this->set_wc_data_prop( $prop, \floatval( $value ) );
    }

    /**
     * Set a slug prop
     *
     * @param  string $prop  Property name.
     * @param  mixed  $value Property value.
     * @return void
     */
    protected function set_slug_prop( string $prop, mixed $value ) {
        if ( ! $this->get_object_read() || '' === $value ) {
            $this->set_wc_data_prop( $prop, $value );
            return;
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
     * @return void
     */
    protected function set_unknown_prop( string $type, string $prop, mixed $value ) {
        if ( ! \method_exists( $this, "set_{$type}_prop" ) ) {
            $this->set_wc_data_prop( $prop, $value );

            return;
        }

        $this->{"set_{$type}_prop"}( $prop, $value );
    }

    /**
     * Maps a term field to its value.
     *
     * @param  string|'term_id'|'parent'|'slug' $field  Field to map.
     * @param  mixed  $value Field value
     * @return string|int
     */
    private function map_term_field( string $field, mixed $value ): string|int {
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
}
