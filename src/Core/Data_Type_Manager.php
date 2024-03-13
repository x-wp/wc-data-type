<?php //phpcs:disable SlevomatCodingStandard.Arrays
/**
 * Data_Type_Manager class file.
 *
 * @package eXtended WooCommerce
 * @subpackage Core
 */

namespace XWC\Core;

use Oblak\WP\Traits\Singleton;
use XWC\Data;
use XWC\Data_Type;
use XWC\Decorators\Data_Type_Config;
use XWC\Decorators\Data_Type_Definition;
use XWC\Decorators\Data_Type_Structure;

/**
 * Manages data type registration, de-registration, and provides access to data types.
 */
final class Data_Type_Manager {
    use Singleton;

    /**
     * Array of data type objects.
     *
     * @var array<string, Data_Type>
     */
    protected array $data_types = array();

    /**
     * Array of supported features for data types.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $supports = array();

    /**
     * Get a data type object.
     *
     * @param  string|false $type Data type.
     * @return array|Data_Type|null
     */
    public function get_data_type( string|false $type = false ): array|Data_Type|null {
        return $type ? $this->data_types[ $type ] ?? null : $this->data_types;
    }

    /**
     * Registers a data type.
     *
     * @param  string $classname Data type class name.
     * @param  string ...$deps   Optional. Data type dependencies.
     * @return Data_Type|\WP_Error
     *
     * @throws \WC_Data_Exception And catches it.
     */
    public function register_class( string $classname, string ...$deps ): Data_Type|\WP_Error {
        try {
			if ( ! \class_exists( $classname ) ) {
				throw new \WC_Data_Exception( 'invalid_data_type', 'Invalid data type class' );
			}

			$args = $this->get_definition( $classname );
            $dt   = $args['name'];

			if ( isset( $this->data_types[ $dt ] ) ) {
                throw new \WC_Data_Exception( 'data_type_exists', 'Data type already exists' );
			}

            $dto = new Data_Type(
                $classname,
                new Data_Type_Definition( ...$args ),
                $deps,
            );

			$this->data_types[ $dt ] = $dto;

            $dto->register_data_store();
            $dto->add_supports();
            $dto->add_hooks();
            $dto->register_taxonomies();
            $dto->initialize();
        } catch ( \Error $e ) {
            $dto = new \WP_Error(
                'invalid_data_type',
                'Missing Data_Type attribute on ' . $classname,
                $e->getMessage(),
            );
        } catch ( \WC_Data_Exception $e ) {
            $dto = new \WP_Error( $e->getCode(), $e->getMessage(), $e->getErrorData() );
        }

        return $dto;
    }

    /**
     * Adds an already registered taxonomy to an object type.
     *
     * @since 3.0.0
     *
     * @global array<int, WP_Taxonomy> $wp_taxonomies The registered taxonomies.
     *
     * @param  string $taxonomy Name of taxonomy object.
     * @param  string $data_type Name of the object type.
     * @param  bool   $ikwid    I know what I'm doing. Allows registering a taxonomy in use by another data type.
     * @return bool             True if successful, false if not.
     */
    public function register_taxonomy( string $taxonomy, string $data_type, bool $ikwid = false ) {
        global $wp_taxonomies;

        if ( ! isset( $wp_taxonomies[ $taxonomy ] ) || \is_null( \xwc_get_data_type_object( $data_type ) ) ) {
            return false;
        }

        if ( \count( $wp_taxonomies[ $taxonomy ]->object_type ) > 0 && ! $ikwid ) {
            \_doing_it_wrong(
                __FUNCTION__,
                \sprintf(
                    "\n" . 'Taxonomy %s already registered for post or object types [%s]. ID collisons are possible.' . "\n",
                    \esc_html( $taxonomy ),
                    \esc_html( \implode( ', ', $wp_taxonomies[ $taxonomy ]->object_type ) ),
                ),
                '6.5.0',
            );
        }

        if ( ! \in_array( $data_type, $wp_taxonomies[ $taxonomy ]->object_type, true ) ) {
            $wp_taxonomies[ $taxonomy ]->object_type[] = $data_type;
        }

        // Filter out empties.
        $wp_taxonomies[ $taxonomy ]->object_type = \array_filter( $wp_taxonomies[ $taxonomy ]->object_type );

        /**
         * Fires after a taxonomy is registered for an object type.
         *
         * @since 5.1.0
         *
         * @param string $taxonomy    Taxonomy name.
         * @param string $object_type Name of the object type.
         */
        \do_action( 'xwc_registered_taxonomy_for_data_type', $taxonomy, $data_type );

        return true;
    }

    /**
     * Add support for a feature to a data type.
     *
     * @param string $data_type Data type.
     * @param string $feature   Feature being added.
     * @param mixed  ...$args   Optional additional arguments for the feature.
     */
    public function add_support( string $data_type, string $feature, mixed ...$args ) {
        $features = (array) $feature;
        foreach ( $features as $feature ) {
            $args = $args ? $args : true;

            if ( \is_array( $args[0] ?? 'no' ) ) {
                $args = $args[0];
            }

            $this->supports[ $data_type ][ $feature ] = $args;
        }
    }

    /**
     * Checks if a data type supports a feature.
     *
     * @param  string $data_type Data type.
     * @param  string $feature   Feature being checked.
     * @return bool              True if the feature is supported, false if not.
     */
    public function supports( string $data_type, string $feature ): bool {
        return isset( $this->supports[ $data_type ][ $feature ] );
    }

    /**
     * Get a feature from a data type.
     *
     * @param  string $data_type Data type.
     * @param  string $feature   Feature name.
     * @param  mixed  $def       Default value.
     * @return mixed
     */
    public function get_supports( string $data_type, string $feature, mixed $def = false ): mixed {
        return $this->supports[ $data_type ][ $feature ] ?? $def;
    }

    /**
     * Get the data type definition arguments
     *
     * @param  string $classname Data type class name.
     * @return array<string, mixed>
     */
    protected function get_definition( string $classname ): array {
        if ( Data::class === \get_parent_class( $classname ) ) {
            $this->get_decorator( $classname, Data_Type_Definition::class )->newInstance();
        }

        $classes = $this->get_class_parents( $classname );
        $args    = $this->get_definition_arguments( \array_shift( $classes ) );
        $dec     = array(
            'structure' => Data_Type_Structure::class,
            'config'    => Data_Type_Config::class,
        );

        foreach ( $dec as $key => $decorator ) {
            $args[ $key ] = ( new $decorator( $key ) )->parse_args(
                $args,
                ...$this->get_decorator_arguments( $classes, $decorator, $key ),
            );
        }

        return $args;
    }

    /**
     * Get the class parents - up to base Data class.
     *
     * @param  string $classname Top level class name.
     * @param  bool   $reverse   Optional. Reverse the array. Default true.
     * @return array
     */
    protected function get_class_parents( string $classname, bool $reverse = true ): array {
        $classes = array();

        while ( Data::class !== $classname ) {
            $classes[] = $classname;
            $classname = \get_parent_class( $classname );
        }

        return $reverse ? \array_reverse( $classes ) : $classes;
    }

    /**
     * Get the data type definition arguments.
     *
     * @param  string $classname Data type class name.
     * @return array
     *
     * @throws \WC_Data_Exception If data type definition cannot be found.
     */
    protected function get_definition_arguments( string $classname ): array {
        $definition = \current(
            $this->get_decorator_arguments( array( $classname ), Data_Type_Definition::class ),
        );

        if ( ! $definition ) {
            throw new \WC_Data_Exception( 'data_type_no_definition', 'Data type definition not found' );
        }

        return $definition;
    }

    /**
     * Get arguments for a decorator from a list of classes.
     *
     * @param  array       $classes   List of class names.
     * @param  string      $decorator Decorator class name.
     * @param  string|null $key       Optional. Key to get from the decorator arguments.
     * @return array
     */
    protected function get_decorator_arguments( array $classes, string $decorator, ?string $key = null ): array {
        $args = array();

        foreach ( $classes as $classname ) {
            $att = $this->get_decorator( $classname, $decorator )?->getArguments();

            if ( \is_null( $key ) ) {
                $args[] = $att;
                continue;
            }

            $args[] = $att[ $key ] ?? $att[0] ?? array();
        }

        return $args;
    }

    /**
     * Get a decorator from a class.
     *
     * @param  string $classname Class name.
     * @param  string $decorator Decorator class name.
     * @return \ReflectionAttribute|null
     */
    protected function get_decorator( string $classname, string $decorator ): ?\ReflectionAttribute {
        return \current(
            ( new \ReflectionClass( $classname ) )->getAttributes(
                $decorator,
                \ReflectionAttribute::IS_INSTANCEOF,
            ),
        ) ?: null; //phpcs:ignore Universal.Operators
    }
}
