<?php

/**
 * Data store class.
 *
 * @template TDS of WC_Data_Store_WP
 * @mixin TDS
 */
class WC_Data_Store {
    /**
     * Contains an instance of the data store class that we are working with.
     *
     * @var WC_Data_Store
     */
    private $instance = \null;
    /**
     * Contains an array of default WC supported data stores.
     * Format of object name => class name.
     * Example: 'product' => 'WC_Product_Data_Store_CPT'
     * You can also pass something like product_<type> for product stores and
     * that type will be used first when available, if a store is requested like
     * this and doesn't exist, then the store would fall back to 'product'.
     * Ran through `woocommerce_data_stores`.
     *
     * @var array
     */
    private $stores = array( 'coupon' => 'WC_Coupon_Data_Store_CPT', 'customer' => 'WC_Customer_Data_Store', 'customer-download' => 'WC_Customer_Download_Data_Store', 'customer-download-log' => 'WC_Customer_Download_Log_Data_Store', 'customer-session' => 'WC_Customer_Data_Store_Session', 'order' => 'WC_Order_Data_Store_CPT', 'order-refund' => 'WC_Order_Refund_Data_Store_CPT', 'order-item' => 'WC_Order_Item_Data_Store', 'order-item-coupon' => 'WC_Order_Item_Coupon_Data_Store', 'order-item-fee' => 'WC_Order_Item_Fee_Data_Store', 'order-item-product' => 'WC_Order_Item_Product_Data_Store', 'order-item-shipping' => 'WC_Order_Item_Shipping_Data_Store', 'order-item-tax' => 'WC_Order_Item_Tax_Data_Store', 'payment-token' => 'WC_Payment_Token_Data_Store', 'product' => 'WC_Product_Data_Store_CPT', 'product-grouped' => 'WC_Product_Grouped_Data_Store_CPT', 'product-variable' => 'WC_Product_Variable_Data_Store_CPT', 'product-variation' => 'WC_Product_Variation_Data_Store_CPT', 'shipping-zone' => 'WC_Shipping_Zone_Data_Store', 'webhook' => 'WC_Webhook_Data_Store' );
    /**
     * Contains the name of the current data store's class name.
     *
     * @var string
     */
    private $current_class_name = '';
    /**
     * The object type this store works with.
     *
     * @var string
     */
    private $object_type = '';

    /**
     * Tells WC_Data_Store which object (coupon, product, order, etc)
     * store we want to work with.
     *
     * @throws Exception When validation fails.
     * @param string $object_type Name of object.
     */
    public function __construct( $object_type ) {
    }

    /**
     * Only store the object type to avoid serializing the data store instance.
     *
     * @return array
     */
    public function __sleep() {
    }

    /**
     * Re-run the constructor with the object type.
     *
     * @throws Exception When validation fails.
     */
    public function __wakeup() {
    }

    /**
     * Loads a data store.
     *
     * @param string $object_type Name of object.
     * @param class-string<TDS> $dscn Data store class name.
     * @since 3.0.0
     * @throws Exception When validation fails.
     * @return WC_Data_Store<TDS>
     */
    public static function load( $object_type, $dscn = WC_Data_Store_WP::class ) {
    }

    /**
     * Returns the class name of the current data store.
     *
     * @since 3.0.0
     * @return string
     */
    public function get_current_class_name() {
    }

    /**
     * Reads an object from the data store.
     *
     * @since 3.0.0
     * @param WC_Data $data WooCommerce data instance.
     */
    public function read( &$data ) {
    }

    /**
     * Reads multiple objects from the data store.
     *
     * @since 6.9.0
     * @param array[WC_Data] $objects Array of object instances to read.
     */
    public function read_multiple( &$objects = array() ) {
    }

    /**
     * Create an object in the data store.
     *
     * @since 3.0.0
     * @param WC_Data $data WooCommerce data instance.
     */
    public function create( &$data ) {
    }

    /**
     * Update an object in the data store.
     *
     * @since 3.0.0
     * @param WC_Data $data WooCommerce data instance.
     */
    public function update( &$data ) {
    }

    /**
     * Delete an object from the data store.
     *
     * @since 3.0.0
     * @param WC_Data $data WooCommerce data instance.
     * @param array   $args Array of args to pass to the delete method.
     */
    public function delete( &$data, $args = array() ) {
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
    }

    /**
     * Check if the data store we are working with has a callable method.
     *
     * @param string $method Method name.
     *
     * @return bool Whether the passed method is callable.
     */
    public function has_callable( string $method ): bool {
    }
}
