<?php
/**
 * Data_Type_Config class file.
 *
 * @package eXtended WooCommerce
 * @subpackage Decorators
 */

namespace XWC\Decorators;

/**
 * Data Type configuration
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Data_Type_Config extends Data_Type_Args {
    /**
     * Get the default configuration for the config type.
     *
     * @return array<string, mixed>
     */
    protected function get_defaults(): array {
        return array(
            'cache_group'  => null,
            'data_store'   => null,
            'dependencies' => array(),
            'factory'      => null,
            'object_type'  => null,
            'query_prefix' => null,
            'supports'     => array(),
        );
    }
}
