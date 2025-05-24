<?php

if ( ! function_exists( 'wc_maybe_define_constant' ) ) :
    /**
     * Define a constant if it is not already defined.
     *
     * @since 3.0.0
     * @param string $name  Constant name.
     * @param mixed  $value Value.
     */
    function wc_maybe_define_constant( $name, $value ) {
        if ( defined( $name ) ) {
            return;
        }

        define( $name, $value );
    }

endif;
