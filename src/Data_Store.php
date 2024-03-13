<?php
/**
 * Data_Store class file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC;

use XWC\Interfaces\Data_Repository;

/**
 * Rework of the `\Data_Store` class - so we have only one instance of individual data store classes.
 */
class Data_Store {
    /**
     * Array of data store classes.
     *
     * @var array
     */
    private static ?array $stores = null;

    /**
     * Array of instances of the data store.
     *
     * @var array
     */
    private static array $instances = array();

    /**
     * Instance of the data store.
     *
     * @var Data_Repository
     */
    private Data_Repository $instance;

    /**
	 * Contains the name of the current data store's class name.
	 *
	 * @var string
	 */
	private $current_class_name = '';

    /**
     * Load the data store for the object type.
     *
     * @param  string $object_type Object type.
     * @return Data_Repository
     */
    public static function &load( string $object_type ) {
        static::$instances[ $object_type ] ??= new Data_Store( $object_type );

        $instance = &static::$instances[ $object_type ];

        return $instance;
    }

    /**
     * Check if the data store is valid.
     *
     * @param  mixed $store Data store.
     * @return bool
     */
    public static function is_valid( mixed $store ): bool {
        return static::valid_string( $store ) || static::valid_instance( $store );
    }

    /**
     * Check if the data store string is valid.
     *
     * @param  mixed $store Data store.
     * @return bool
     */
    protected static function valid_string( mixed $store ): bool {
        return \is_string( $store )
            && \class_exists( $store )
            && \in_array( Data_Repository::class, \class_implements( $store ), true );
    }

    /**
     * Check if the data store instance is valid.
     *
     * @param  mixed $store Data store.
     * @return bool
     */
    protected static function valid_instance( mixed $store ): bool {
        return \is_object( $store ) && \is_a( $store, Data_Repository::class );
    }

    /**
     * Constructor
     *
     * @param  string $object_type Object type.
     */
    public function __construct(
        /**
         * The object type this store works with.
         *
         * @var string
         */
        private string $object_type,
    ) {
        // Documented in Data_Type.php.
        static::$stores ??= \apply_filters( 'xwc_data_stores', array() );

        if ( ! isset( static::$stores[ $object_type ] ) ) {
            $this->error( $object_type );
        }

        $this->instance           = $this->get_store( $object_type );
        $this->current_class_name = $this->instance::class;
    }

    /**
     * Get the data store for the object type.
     *
     * @param  string|object $object_type Object type.
     * @return Data_Repository
     */
    private function get_store( string|object $object_type ): Data_Repository {
        // Documented in WooCommerce.
        $store = \apply_filters( "woocommerce_{$object_type}_data_store", static::$stores[ $object_type ] );

        if ( ! static::is_valid( $store ) ) {
            $this->error( $store::class );
        }

        return ! \is_object( $store ) ? new $store() : $store;
    }

    /**
     * Throws an exception for an invalid data store.
     *
     * @param  string $object_type Object type.
     * @throws \Exception If the data store is invalid.
     */
    public function error( string $object_type ) {
        throw new \Exception(
            \sprintf(
                '%s %s',
                \esc_html__( 'Invalid data store.', 'woocommerce' ),
                \esc_html( \is_object( $object_type ) ? $object_type::class : $object_type ),
            ),
        );
    }

    /**
	 * Returns the class name of the current data store.
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_current_class_name() {
		return $this->current_class_name;
	}

    /**
	 * Create an object in the data store.
	 *
	 * @since 3.0.0
	 * @param Data $data WooCommerce data instance.
	 */
	public function create( &$data ) {
		$this->instance->create( $data );
	}

    /**
	 * Reads an object from the data store.
	 *
	 * @since 3.0.0
	 * @param Data $data WooCommerce data instance.
	 */
	public function read( &$data ) {
		$this->instance->read( $data );
	}

	/**
	 * Reads multiple objects from the data store.
	 *
	 * @since 6.9.0
	 * @param array[Data] $objects Array of object instances to read.
	 */
	public function read_multiple( &$objects = array() ) {
        foreach ( $objects as &$obj ) {
            $this->read( $obj );
        }
	}

	/**
	 * Update an object in the data store.
	 *
	 * @since 3.0.0
	 * @param Data $data WooCommerce data instance.
	 */
	public function update( &$data ) {
		$this->instance->update( $data );
	}

	/**
	 * Delete an object from the data store.
	 *
	 * @since 3.0.0
	 * @param Data  $data WooCommerce data instance.
	 * @param array $args Array of args to pass to the delete method.
	 */
	public function delete( &$data, $args = array() ) {
		$this->instance->delete( $data, $args );
	}

	/**
	 * Data stores can define additional functions (for example, coupons have
	 * some helper methods for increasing or decreasing usage). This passes
	 * through to the instance if that function exists.
	 *
	 * @since 3.0.0
	 * @param string $method     Method.
	 * @param mixed  $parameters Parameters.
	 * @return mixed
	 */
	public function __call( $method, $parameters ) {
		if ( \is_callable( array( $this->instance, $method ) ) ) {
			$object     = \array_shift( $parameters );
			$parameters = \array_merge( array( &$object ), $parameters );
			return $this->instance->$method( ...$parameters );
		}
	}

	/**
	 * Check if the data store we are working with has a callable method.
	 *
	 * @param string $method Method name.
	 *
	 * @return bool Whether the passed method is callable.
	 */
	public function has_callable( string $method ): bool {
		return \is_callable( array( $this->instance, $method ) );
	}
}
