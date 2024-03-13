<?php
/**
 * Data_Type_Structure class file.
 *
 * @package eXtended WooCommerce
 * @subpackage Decorators
 */

namespace XWC\Decorators;

/**
 * Data Type structure configuration
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Data_Type_Structure extends Data_Type_Args {
    /**
     * Get the default configuration for the config type.
     *
     * @return array<string, mixed>
     */
    protected function get_defaults(): array {
        return array(
            'columns'       => static fn( $v ) => \wp_parse_args(
                $v,
                array(
                    'default' => null,
                    'search'  => false,
                    'type'    => 'string',
                    'unique'  => false,
                    'var'     => null,
                ),
            ),
            'column_prefix' => '',
            'id_field'      => 'ID',
            'meta'          => static fn( $v ) => \wp_parse_args(
                $v,
                array(
                    'default'  => null,
                    'key'      => null,
                    'required' => false,
                    'type'     => 'string',
                    'unique'   => false,
                    'var'      => null,
                ),
            ),
            'meta_type'     => null,
            'taxonomies'    => static fn( $v ) => \wp_parse_args(
                $v,
                array(
                    'default'  => null,
                    'force'    => false,
                    'multiple' => true,
                    'tax'      => false,
                    'type'     => 'tax',
                    'var'      => null,
                ),
            ),
        );
    }
}
