<?php

/**
 * Base class for properties.
 *
 * @template TKey of string
 * @template TValue of mixed
 *
 * @implements ArrayAccess<TKey,TValue>
 */
class XWC_Prop implements ArrayAccess, JsonSerializable {
    /**
     * Traversible data array.
     *
     * @var array<TKey,TValue>
     */
    protected array $data = array();

    /**
     * The hash of the data.
     *
     * This is used to track changes to the data.
     *
     * @var string
     */
    protected string $hash;

    /**
     * Did we read the object?
     *
     * @var bool
     */
    protected bool $read = false;

    protected bool $changed = false;

    /**
     * Gets the default JSON representation of the object.
     *
     * @return static
     */
    public static function default(): static {
        // @phpstan-ignore new.static
        return new static();
    }

    /**
     * Constructor.
     *
     * @param array<TKey,TValue> $data Data and hash.
     * }
     */
    public function __construct( array $data = array() ) {
        $this->data = $this->default_data();
        $this->hash = $this->hash_data();

        $this->load_data( $data );
    }

    /**
     * Serializes the object to an array.
     *
     * @return array<TKey,TValue>
     */
    public function __serialize(): array {
        return $this->get_data();
    }

    /**
     * Unserializes the object from an array.
     *
     * @param array<TKey,TValue> $data Data to unserialize.
     */
    public function __unserialize( array $data ): void {
        $this->load_data( $data );
    }

    /**
     * Return data needed for JSON serialization.
     *
     * @return ?array<TKey,TValue>
     */
    public function jsonSerialize(): mixed {
        $data = $this->get_data();
        $defl = $this->default_data();

        $this->sort( $data );
        $this->sort( $defl );
        return $data === $defl
            ? null
            : $data;
    }

    /**
     * Sets the value at the specified offset.
     *
     * @param  TKey   $offset The offset to set.
     * @param  TValue $value The value to set.
     * @return static
     */
    public function set( string $offset, mixed $value ): static {
        $old = $this->get( $offset );

        $this->data[ $offset ] = $value;

        if ( $this->get_read() && $old !== $value ) {
            $this->changed = true;
        }

        return $this;
    }

    /**
     * Loads data into the property.
     *
     * @param  array<TKey,TValue> $data The data to load.
     * @return static
     */
    public function set_data( array $data ): static {
        $old        = $this->data;
        $this->data = $data;

        if ( $this->get_read() && $old !== $data ) {
            $this->changed = true;
        }

        return $this;
    }

    /**
     * Mark the object as read.
     *
     * @param  bool   $read Whether the object has been read.
     * @return static
     */
    public function set_read( bool $read = true ): static {
        $this->read = $read;

        return $this;
    }

    /**
     * Sets the data for the property.
     *
     * @template TData of XWC_Prop
     *
     * @param  TData|array<TKey,TValue> $data The data to set.
     * @return ($data is array ? static<TKey,TValue> : TData<TKey,TValue>)
     */
    public function with_data( array|XWC_Prop $data ): XWC_Prop {
        if ( is_array( $data ) ) {
            return $this->set_data( $data );
        }

        $cname = $data::class;

        return new $cname( $data->get_data() );
    }

    /**
     * Gets the value at the specified offset.
     *
     * @param TKey $offset The offset to retrieve.
     * @return TValue|null Can return any type or null if not set.
     */
    public function get( string $offset ): mixed {
        return $this->data[ $offset ] ?? null;
    }

    /**
     * Gets the data array.
     *
     * @return array<TKey,TValue>
     */
    public function get_data(): array {
        return $this->data;
    }

    /**
     * Gets the hash of the data.
     *
     * @return ?string
     */
    public function get_hash(): ?string {
        return $this->hash ?? null;
    }

    /**
     * Checks if the object has been read.
     *
     * @return bool
     */
    public function get_read(): bool {
        return $this->read;
    }

    public function changed(): bool {
        if ( ! $this->get_read() ) {
            return false;
        }

        return $this->changed;
    }

    /**
     * Assigns a value to the specified offset.
     *
     * Used by the ArrayAccess interface.
     *
     * @param TKey   $offset The offset to assign the value to.
     * @param TValue $value The value to set.
     * @return void
     */
    public function offsetSet( $offset, $value ): void {
        throw new \BadMethodCallException( 'Do not use this method directly. Use the setter method instead.' );
    }

    /**
     * Returns the value at the specified offset.
     *
     * Used by the ArrayAccess interface.
     *
     * @param TKey $offset The offset to retrieve.
     * @return TValue|array<mixed> Can return any type.
     */
    public function &offsetGet( $offset ): mixed {
        return $this->data[ $offset ] ?? array();
    }

    /**
     * Checks if the specified offset exists.
     *
     * Used by the ArrayAccess interface.
     *
     * @param TKey $offset The offset to check.
     * @return bool
     */
    public function offsetExists( $offset ): bool {
        return isset( $this->data[ $offset ] );
    }

    /**
     * Unsets the value at the specified offset.
     *
     * Used by the ArrayAccess interface.
     *
     * @param TKey $offset The offset to unset.
     * @return void
     */
    public function offsetUnset( $offset ): void {
        throw new \BadMethodCallException( 'Do not use this method directly. Use the setter method instead.' );
    }

    /**
     * Sets the hash of the data.
     *
     * @param  string $hash The hash to set.
     * @return static
     */
    protected function set_hash( string $hash ): static {
        $this->hash = $hash;

        return $this;
    }

    /**
     * Returns the default data for this property.
     *
     * @return array<TKey,TValue>
     */
    protected function default_data(): array {
        return array();
    }

    /**
     * Hashes the data.
     *
     * This method sorts the data and then hashes it using md5.
     *
     * @param  null|array<TKey,TValue> $data Optional data to hash. If not provided, uses the current data.
     * @return string The hash of the data.
     */
    protected function hash_data( ?array $data = null ): string {
        $data ??= $this->data;

        $this->sort( $data );

        return hash( 'md5', (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    /**
     * Sorts the data array recursively.
     *
     * This method sorts the array in place, preserving the keys and ensuring
     * that nested arrays are also sorted.
     *
     * @param  array<mixed> $arr The array to sort.
     * @return void
     */
    protected function sort( array &$arr ): void {
        array_is_list( $arr )
            ? sort( $arr, SORT_NATURAL | SORT_FLAG_CASE )
            : ksort( $arr, SORT_NATURAL | SORT_FLAG_CASE );

        foreach ( $arr as &$value ) {
            if ( ! is_array( $value ) ) {
                continue;
            }

            $this->sort( $value );
        }
    }

    /**
     * Load the data.
     *
     * @param array<TKey,TValue> $data The data to load.
     * }
     */
    protected function load_data( array $data = array() ): void {
        if ( ! $data ) {
            $this->set_read( true );
            return;
        }

        $this
            ->set_data( $data )
            ->set_hash( $this->hash_data() )
            ->set_read( true );
    }
}
