<?php
/**
 * Data_Type_Extension class file.
 *
 * @package eXtended WooCommerce
 * @subpackage Decorators
 */

namespace XWC\Decorators;

/**
 * Enables extending the definition of a data type.
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Data_Type_Extension {
    /**
     * Last hook priority.
     *
     * @var int
     */
    protected static int $last_priority = 0;

    /**
     * Hook priority.
     *
     * @var int
     */
    protected int $priority;

    /**
     * Configuration arguments.
     *
     * @var array
     */
    protected array $args = array();

    /**
     * Constructor.
     *
     * @param string $name      Data type name.
     * @param array  $config    Configuration arguments.
     * @param array  $structure Structure arguments.
     */
    public function __construct(
        /**
         * Data type name.
         *
         * @var string
         */
        private string $name,
        array $config = array(),
        array $structure = array(),
	) {
        $this->priority = static::$last_priority;
        $this->args     = \array_merge( $config, $structure );

        static::$last_priority += 10;

        \add_filter( 'xwc_data_type_definition', array( $this, 'extend_definition' ), $this->priority, 2 );
    }

    /**
     * Extends the definition of a data type.
     *
     * @param  array  $definition Data type definition.
     * @param  string $type       Data type name.
     * @return array
     */
    public function extend_definition( array $definition, string $type ): array {
        \remove_filter( 'xwc_data_type_definition', array( $this, 'extend_definition' ), $this->priority );

        if ( $type !== $this->name ) {
            return $definition;
        }

        return $this->parse_args_recursive( $definition, $this->args );
    }

    /**
     * Parses the configuration arguments.
     *
     * @param  array $a Configuration definition.
     * @param  array $b Configuration overrides.
     * @return array
     */
    protected function parse_args_recursive( &$a, $b ) {
        $a = (array) $a;
        $b = (array) $b;
        $r = $b;

        foreach ( $a as $k => &$v ) {
            $r[ $k ] = \is_array( $v ) && isset( $r[ $k ] )
                ? $this->parse_args_recursive( $v, $r[ $k ] )
                : $v;
        }

        return $r;
    }

    /**
     * Destructor.
     *
     * Decrements the last priority.
     */
    public function __destruct() {
        static::$last_priority -= 10;
    }
}
