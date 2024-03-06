<?php
/**
 * Object Query class file.
 *
 * @package eXtended WooCommerce
 */

namespace XWC;

use XWC\Traits\Data_Type_Meta;

/**
 * Abstract Object Query class
 *
 * Extended by classes to provide a consistent way to query objects.
 */
abstract class Object_Query {
    use Data_Type_Meta;

    /**
     * ID field.
     *
     * @var string
     */
    protected string $id_field;

    /**
     * Object type.
     *
     * @var string
     */
    protected string $object_type;

    /**
     * Query vars.
     *
     * @var array
     */
    protected array $vars = array();

    /**
     * Constructor
     *
     * @param  array $vars Query vars.
     *
     * @throws \Exception If the data type is not set.
     */
    public function __construct( array $vars = array() ) {
        $this->data_type = $this->get_data_type();

        $this->init_metadata();

        $this->vars = \wp_parse_args( $vars, $this->get_default_query_vars() );
    }

    /**
     * Get the data type.
     *
     * @return string
     */
    abstract protected function get_data_type(): string;

    // phpcs:ignore Squiz.Commenting
    protected function get_metadata_keys(): array {
        return array( 'id_field', 'object_type' );
    }

    /**
     * Get the default query vars.
     */
    protected function get_default_query_vars() {
        return array(
            'data_type' => $this->get_data_type(),
            'limit'     => \get_option( "{$this->data_type}_per_page", \get_option( 'posts_per_page', 20 ) ),
            'offset'    => null,
            'order'     => 'DESC',
            'orderby'   => $this->id_field,
            'page'      => 1,
            'paginate'  => false,
            'return'    => 'objects',
        );
    }

    /**
     * Get Objects matching the query vars.
     *
     * @return array<int, Data|int>|object
     */
    public function get_objects() {
        $filter_prefix = "xwc_{$this->data_type}_object_query";

        // Documented in WooCommerce.
        $args    = \apply_filters( $filter_prefix . '_args', $this->vars );
        $results = Data_Store::load( $this->object_type )->query( $args );

        // Documented in WoCommerce.
        return \apply_filters( $filter_prefix . '_results', $results, $args );
    }
}
