<?php


if ( ! function_exists( 'wc_doing_it_wrong' ) ) :

    /**
     * Wrapper for _doing_it_wrong().
     *
     * @since  3.0.0
     * @param string $function Function used.
     * @param string $message Message to log.
     * @param string $version Version the message was added in.
     */
    function wc_doing_it_wrong( $function, $message, $version ) {
		// @codingStandardsIgnoreStart
		$message .= ' Backtrace: ' . wp_debug_backtrace_summary();

		if ( wp_doing_ajax() || wp_is_rest_endpoint()) {
			do_action( 'doing_it_wrong_run', $function, $message, $version );
			error_log( "{$function} was called incorrectly. {$message}. This message was added in version {$version}." );
		} else {
			_doing_it_wrong( $function, $message, $version );
		}
		// @codingStandardsIgnoreEnd
    }

endif;

if ( ! function_exists( 'wc_deprecated_function' ) ) :


    /**
     * Wrapper for deprecated functions so we can apply some extra logic.
     *
     * @since 3.0.0
     * @param string $function Function used.
     * @param string $version Version the message was added in.
     * @param string $replacement Replacement for the called function.
     */
    function wc_deprecated_function( $function, $version, $replacement = null ) {
		// @codingStandardsIgnoreStart
		if ( wp_doing_ajax() || wp_is_rest_endpoint() ) {
			do_action( 'deprecated_function_run', $function, $replacement, $version );
			$log_string  = "The {$function} function is deprecated since version {$version}.";
			$log_string .= $replacement ? " Replace with {$replacement}." : '';
			error_log( $log_string );
		} else {
			_deprecated_function( $function, $version, $replacement );
		}
		// @codingStandardsIgnoreEnd
    }

endif;
