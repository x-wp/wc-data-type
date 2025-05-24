<?php

if ( ! function_exists( 'xwc_load_polyfill' ) && function_exists( 'add_action' ) ) :
    /**
     * Load the polyfill for WooCommerce.
     *
     * @since 1.0.0
     */
    function xwc_load_polyfill(): void {
        if ( function_exists( 'WC' ) ) {
            return;
        }

        require_once __DIR__ . '/autoloader.php';

        $autoloader = new XWC_Polyfill_Autoloader();
        $autoloader->register();
    }

    add_action( 'plugins_loaded', xwc_load_polyfill( ... ), 0, 1 );
endif;
