<?php
/**
 * Data_Type_Args class file.
 *
 * @package eXtended WooCommerce
 * @subpackage Decorators
 */

namespace XWC\Decorators;

/**
 * Shared functionalities for parsing configuration arguments.
 */
abstract class Data_Type_Args {
    /**
     * Constructor.
     *
     * @param  string $key Configuration key.
     */
    public function __construct(
        /**
         * Configuration key.
         *
         * @var string
         */
        protected string $key,
    ) {
    }

    /**
     * Get the default configuration for the config type.
     *
     * @return array<string, mixed>
     */
    abstract protected function get_defaults(): array;

    /**
     * Parses the configuration arguments.
     *
     * @param  array $definition   Configuration definition.
     * @param  array ...$overrides Configuration overrides.
     * @return array
     */
    public function parse_args( array $definition, array ...$overrides ): array {
        $overrides = \array_filter( $overrides );
        $config    = $definition[ $this->key ];
        $defaults  = $this->get_defaults( $definition );

        foreach ( $overrides as $override ) {
            foreach ( $defaults as $arg => $default ) {
                $config[ $arg ] = $this->parse_arg(
                    $defaults[ $arg ],
                    $config[ $arg ] ?? null,
                    $override[ $arg ] ?? null,
                );

            }
        }

        return $config;
    }

    /**
     * Parses a single argument in the configuration.
     *
     * If default is callable, we're dealing with a nested array.
     *
     * @param  mixed $def     Default value.
     * @param  mixed $initial Initial value.
     * @param  mixed $changed Changed value.
     * @return mixed
     */
    protected function parse_arg( mixed $def, mixed $initial = null, mixed $changed = null ): mixed {
        if ( \is_callable( $def ) ) {
            $changed = \array_map( $def, $changed ?? array() );
            $initial = \array_map( $def, $initial ?? array() );

            return \array_merge( $initial, $changed );
        }

        return $changed ?? $initial ?? $def;
    }
}
