<?php
/**
 * Data type dependency class file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC;

/**
 * Base class for data type dependencies.
 *
 * Initialize function is abstract and must be implemented in the child class.
 *
 * Additional functions are `add_hooks` and `remove_hooks` for hook management.
 * Since data types can be unregistered - if adding hooks for a data type via `add_hooks` Implement the `remove_hooks` function.
 */
abstract class Data_Type_Dependency {
    /**
     * Whether hooks have been added.
     *
     * @var bool
     */
    private $has_hooks = true;

    /**
     * Constructor.
     *
     * @param  string $data_type The data type.
     */
    public function __construct(
        /**
         * The data type.
         *
         * @var string
         */
        protected string $data_type,
    ) {
    }

    /**
     * Initialize the dependency.
     */
    abstract public function initialize(): void;

    /**
     * Add hooks for the data type.
     */
    public function add_hooks() {
        $this->has_hooks = false;
    }

    /**
     * Removes hooks for the data type.
     *
     * @throws \WC_Data_Exception If removal has not been implemented.
     */
    public function remove_hooks() {
        if ( ! $this->has_hooks ) {
            return;
        }

        throw new \WC_Data_Exception(
            'data_type_no_hook_removal',
            \sprintf(
                'Hooks have been added for %s data type in %s class, but removal has not been implemented.',
                \esc_html( $this->data_type ),
                \esc_html( static::class ),
            ),
        );
    }

    /**
     * Destructor.
     *
     * Removes hooks.
     */
    public function __destruct() {
        $this->remove_hooks();
    }
}
