<?php
/**
 * XWC Polyfill Autoloader
 *
 * Autoloads classes, functions, and interfaces from the WC includes directory
 */

/**
 * XWC_Polyfill_Autoloader class
 */
class XWC_Polyfill_Autoloader {
    /**
     * Path to the wc directory
     *
     * @var string
     */
    private $base_path;

    /**
     * Class map for autoloading
     *
     * @var array<class-string,string>
     */
    private $classmap = array(
        'Automattic\WooCommerce\Caching\CacheNameSpaceTrait' => 'mixins/CacheNameSpaceTrait.php',
        'WC_Cache_Helper'                => 'classes/class-wc-cache-helper.php',
        'WC_Data'                        => 'classes/abstract-wc-data.php',
        'WC_Data_Exception'              => 'classes/class-wc-data-exception.php',
        'WC_Data_Store'                  => 'classes/class-wc-data-store.php',
        'WC_Data_Store_WP'               => 'classes/class-wc-data-store-wp.php',
        'WC_DateTime'                    => 'classes/class-wc-datetime.php',
        'WC_Meta_Data'                   => 'classes/class-wc-meta-data.php',
        'WC_Object_Data_Store_Interface' => 'intefaces/wc-object-data-store-interface.php',
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->base_path = __DIR__;
    }

    /**
     * Register the autoloader
     */
    public function register(): void {
        $this->load_functions();

        spl_autoload_register( array( $this, 'autoload' ) );
    }

    /**
     * Autoload WC classes
     *
     * @param string $cname Class name.
     */
    public function autoload( string $cname ): void {
        if ( ! isset( $this->classmap[ $cname ] ) ) {
            return;
        }

        require_once $this->base_path . '/' . $this->classmap[ $cname ];
    }

    /**
     * Load function files
     */
    private function load_functions(): void {
        $functions_dir = $this->base_path . '/functions/';

        if ( ! is_dir( $functions_dir ) ) {
            return;
        }

        $function_files = glob( $functions_dir . '*.php' );
        if ( ! $function_files ) {
            return;
        }

        foreach ( $function_files as $file ) {
            require_once $file;
        }
    }
}
