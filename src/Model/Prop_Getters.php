<?php //phpcs:disable WordPress.PHP.DiscouragedPHPFunctions

namespace XWC\Data\Model;

use JsonSerializable;
use Stringable;
use XWC_Data;

/**
 * Prop getters trait.
 *
 * @template TDt of XWC_Data
 */
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
     * Taxonomy data.
     *
     * @var array<string,mixed>
     */
    protected array $tax_data = array();

    /**
     * Array linking props to their types.
     *
     * @var array<string,'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array_set'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'string'|'other'>
     */
    protected array $prop_types = array();

    /**
     * Props that should be unique.
     *
     * @var array<string>
     */
    protected array $unique_data = array();

    /**
     * Props that must be set.
     *
     * @var array<string>
     */
    protected array $required_data = array();

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
     * @return array<string,string>
     */
    public function get_prop_types(): array {
        return $this->prop_types;
    }

    /**
     * Get core data keys
     *
     * @return array<int,string>
     */
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

    /**
     * Get core changes
     *
     * Returns an array of core data keys that have changed.
     * The values are the values that will be saved to the database.
     *
     * @return array<string,mixed>
     */
    public function get_core_changes(): array {
        $changed = array();
        $props   = \array_intersect( $this->get_core_keys(), \array_keys( $this->get_changes() ) );

        if ( 0 === \count( $props ) ) {
            return $changed;
        }

        foreach ( $props as $prop ) {
            $changed[ $prop ] = $this->get_prop( $prop, 'db' );
        }

        return $changed;
    }

    /**
     * Get all data for this object.
     *
     * This includes core data, extra data, and meta data.
     *
     * @return array<string,mixed>
     */
    public function get_data() {
        $data = parent::get_data();

        if ( ! $this->has_meta ) {
            unset( $data['meta_data'] );
        }

        return $data;
    }

    /**
     * Get all changes for this object.
     *
     * This includes core data, extra data, and meta data.
     *
     * @return array<string,mixed>
     */
    public function get_changes() {
        return $this
            ->maybe_set_object()
            ->get_wc_data_changes();
    }

    /**
     * Get the type of a prop.
     *
     * @param  string $prop Name of prop to get type for.
     * @return array{
     *   0: 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array_set'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'other'|string|class-string,
     *   1: array<int,mixed>
     * } | array{0: 'enum', 1: array{0: class-string<BackedEnum>}}
     */
    protected function get_prop_type( string $prop ): array {
        $types = $this->prop_types[ $prop ] ?? 'string';
        $types = \explode( '|', $types );
        /**
         * Variable narrowing for prop types.
         *
         * @var 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array_set'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'other'|string|class-string $type
         */
        $type = \array_shift( $types );

        return array( $type, $types );
    }

    protected function is_binary_string( ?string $value ): bool {
        //phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return ! (bool) @\mb_check_encoding( $value ?? '', 'UTF-8' );
    }

    protected function is_base64_string( ?string $value ): bool {
        if ( ! \is_string( $value ) ) {
            return false;
        }

        $dec = \base64_decode( $value, true );

        return false !== $dec && \base64_encode( $dec ) === $value;
    }

    /**
     * Get prop(s) by type.
     *
     * @param  string            $type Type of prop to get.
     * @return ($type is 'date_created' ? null|string : ($type is 'date_updated' ? null|string : null|string|array<string>))
     */
    protected function get_prop_by_type( string $type ): null|string|array {
        $types = \array_filter(
            $this->get_prop_types(),
            fn( $t ) => $this->filter_prop( $t, $type ),
        );

        $types = \array_keys( $types );

        return match ( \count( $types ) ) {
            0       => null,
            1       => $types[0],
            default => $types,
        };
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

        $value          = $this->get_wc_data_prop( $prop, 'edit' );
        [ $type, $sub ] = $this->get_prop_type( $prop );

        return match ( $type ) {
            'string'        => $value,
            'date'          => $this->get_date_prop( $value ),
            'date_created'  => $this->get_date_prop( $value ),
            'date_updated'  => $this->get_date_prop( $value ),
            'bool'          => $this->get_bool_prop( $value, 'string' ),
            'bool_int'      => $this->get_bool_prop( $value, 'int' ),
            'array_assoc'   => $this->get_array_prop( $value, 'assoc' ),
            'array'         => $this->get_array_prop( $value, 'normal' ),
            'array_set'     => $this->get_array_prop( $value, 'set' ),
            'term_single'   => $this->get_term_prop( $value, ...$sub ),
            'term_array'    => $this->get_term_prop( $value, ...$sub ),
            'enum'          => $this->get_enum_prop( $value ),
            'json'          => $this->get_json_prop( $value ),
            'json_obj'      => $this->get_json_prop( $value, \JSON_FORCE_OBJECT ),
            'binary'        => $this->get_binary_prop( $value ),
            'base64_string' => $this->get_base64_string_prop( $value ),
            'object'        => $this->get_object_prop( $value, ...$sub ),
            default         => $this->get_unknown_prop( $type, $prop, $value ),
        };
    }

    /**
     * Get WC data changes.
     *
     * This is a wrapper for the parent method to ensure that the WC_Data
     * changes are returned in the correct format.
     *
     * @return array<string,mixed>
     */
    protected function get_wc_data_changes(): array {
        return parent::get_changes();
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

    /**
     * Get boolean prop value.
     *
     * @param  mixed          $value  Value to convert to a boolean.
     * @param  'string'|'int' $format Format of the boolean value.
     * @return 'yes'|'no'|int<0,1>
     */
    protected function get_bool_prop( mixed $value, string $format = 'string' ): int|string {
        return match ( $format ) {
            'string'  => \wc_bool_to_string( $value ),
            'int'     => (int) \wc_string_to_bool( $value ),
        };
    }

    /**
     * Get array prop value.
     *
     * @param  mixed            $value  Value to convert to a string.
     * @param  'assoc'|'normal'|'set' $format Format of the array.
     * @return string
     */
    protected function get_array_prop( mixed $value, string $format = 'assoc' ): string {
        return match ( $format ) {
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
            'assoc'  => \serialize( $value ),
            'normal' => \implode( ',', $value ),
            'set' => \implode( ',', \array_unique( (array) $value ) ),
        };
    }

    /**
     * Get term prop value.
     *
     * @param  mixed  $value Term value.
     * @param  string $field Field which is stored in the object.
     * @return array<int>
     */
    protected function get_term_prop( mixed $value, string $field, string $taxonomy ): array {
        $value = (array) $value;

        foreach ( $value as &$v ) {
            $v = \get_term_by( $field, $v, $taxonomy );
        }

        return \array_filter( $value );
    }

    /**
     * Get enum prop value.
     *
     * @param  \BackedEnum|null $enum_val Enum value.
     * @return null|string|int
     */
    protected function get_enum_prop( $enum_val ): null|string|int {
        return $enum_val?->value ?? null;
    }

    /**
     * Get JSON prop value.
     *
     * @param  mixed  $value Value to encode to JSON.
     * @param  int    $flags Optional. JSON encoding flags. Default 0.
     * @return string
     */
    protected function get_json_prop( mixed $value, int $flags = 0 ): string {
        return (string) \wp_json_encode( $value, $flags );
    }

    /**
     * Get a binary string prop value.
     *
     * @param  mixed  $value Value to decode from hex to binary.
     * @return string
     */
    protected function get_binary_prop( mixed $value ): string {
        return ! $this->is_binary_string( $value ) ? \hex2bin( $value ) : $value;
    }

    protected function get_base64_string_prop( ?string $value ): string {
        return ! $this->is_base64_string( $value ) ? \base64_encode( $value ) : $value;
    }

    /**
     * Get an object prop value.
     *
     * This is used for objects that implement JsonSerializable or Stringable.
     *
     * @param  mixed  $value Value to convert to a string.
     * @param  string $cname Class name of the prop, defaults to XWC_Prop.
     * @return ?string
     */
    protected function get_object_prop( mixed $value, string $cname = \XWC_Prop::class ): ?string {
        $iof = static fn( $t ) => $t instanceof JsonSerializable || $t instanceof Stringable;
        $enc = JSON_UNESCAPED_UNICODE;

        return match ( true ) {
            $iof( $value )                   => $this->get_json_prop( $value, $enc ),
            $value === $cname                => $this->get_json_prop( new $cname(), $enc ),
            \class_exists( (string) $value ) => null,
            '' === (string) $value           => null,
            default                          => null,
        };
    }

    protected function get_unknown_prop( string $type, string $prop, mixed $value ): mixed {
        if ( \method_exists( $this, "get_{$type}_prop" ) ) {
            $value = $this->{"get_{$type}_prop"}( $value, $prop );
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

    private function filter_prop( string $type, string $find ): bool {
        $regex = '/^' . \preg_quote( $find, '/' ) . '(?:$|\|.+$)/';

        return 1 === \preg_match( $regex, $type );
    }
}
