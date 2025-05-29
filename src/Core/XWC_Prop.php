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
    protected string $hash = '';

    /**
     * Gets the default JSON representation of the object.
     *
     * @return static
     */
    public static function default(): static {
        return new static();
    }

    /**
     * Creates a new instance from JSON data.
     *
     * @template Tp of XWC_Prop<string,mixed>
     * @param  null|false|array{class?: class-string<Tp>, data?: array<TKey,TValue>, hash?: string} $data The JSON data.
     * @return Tp
     */
    public static function from_json( null|bool|array $data ): XWC_Prop {
        if ( ! is_array( $data ) || ! $data ) {
            $data = array(
                'data' => array(),
                'hash' => '',
            );
        }
        $data['class'] ??= static::class;

        $cname = is_a( $data['class'], static::class, true ) ? $data['class'] : static::class;

        return new $data['class']( $data );
    }

    /**
     * Constructor.
     *
     * @param array{data?: array<TKey,TValue>, hash?: string} $args Data and hash.
     * }
     */
    public function __construct( array $args = array() ) {
        if ( isset( $args['data'] ) ) {
            $this->sort( $args['data'] );
        }

        $this->data = $args['data'] ?? array();
        $this->hash = $args['hash'] ?? $this->hash_data();
    }

    /**
     * Serializes the object to an array.
     *
     * @return array{data: array<TKey,TValue>, hash: string} Data and hash.
     */
    public function __serialize(): array {
        return array(
            'data' => $this->data,
            'hash' => $this->hash_data(),
        );
    }

    /**
     * Unserializes the object from an array.
     *
     * @param array{data?: array<TKey,TValue>, hash?: string} $data Data and hash.
     */
    public function __unserialize( array $data ): void {
        $this->data = $data['data'] ?? array();
        $this->hash = $data['hash'] ?? $this->hash_data();
    }

    /**
     * Return data needed for JSON serialization.
     *
     * @return array{
     *   class:class-string<static>,
     *   data: array<TKey,TValue>,
     *   hash: string,
     * }
     */
    public function jsonSerialize(): mixed {
        return array(
            'class' => static::class,
            'data'  => $this->get_data(),
            'hash'  => $this->hash_data(),
        );
    }

    /**
     * Sets the value at the specified offset.
     *
     * @param  TKey   $offset The offset to set.
     * @param  TValue $value The value to set.
     * @return static
     */
    public function set( string $offset, mixed $value ): static {
        $this->data[ $offset ] = $value;

        return $this;
    }

    /**
     * Loads data into the property.
     *
     * @param  array<TKey,TValue> $data The data to load.
     * @return static
     */
    public function set_data( array $data ): static {
        $this->sort( $data );

        $this->data = $data;

        return $this;
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

    public function get_data(): array {
        return $this->data;
    }

    public function changed(): bool {
        return array() !== $this->data && $this->hash !== $this->hash_data();
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

    private function hash_data(): string {
        $data = $this->data;

        ksort( $data );

        return hash( 'md5', (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    private function sort( array &$arr ): void {
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
}
