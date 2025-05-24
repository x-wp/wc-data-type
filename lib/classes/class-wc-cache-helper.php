<?php
/**
 * WC_Cache_Helper class.
 *
 * @package WooCommerce\Classes
 */

use Automattic\WooCommerce\Caching\CacheNameSpaceTrait;

/**
 * WC_Cache_Helper.
 */
class WC_Cache_Helper {
    use CacheNameSpaceTrait;

    /**
     * Transients to delete on shutdown.
     *
     * @var array Array of transient keys.
     */
    private static $delete_transients = array();

    /**
     * Hook in methods.
     */
    public static function init() {
        add_filter( 'nocache_headers', array( self::class, 'additional_nocache_headers' ), 10 );
        add_action( 'shutdown', array( self::class, 'delete_transients_on_shutdown' ), 10 );
        add_action( 'delete_version_transients', array( self::class, 'delete_version_transients' ), 10 );
    }

    /**
     * Set additional nocache headers.
     *
     * @param array $headers Header names and field values.
     * @since 3.6.0
     */
    public static function additional_nocache_headers( $headers ) {
        global $wp_query;

        $agent = xwp_fetch_server_var( 'HTTP_USER_AGENT', '' );

        $set_cache = false;

        /**
         * Allow plugins to enable nocache headers. Enabled for Google weblight.
         *
         * @param bool $enable_nocache_headers Flag indicating whether to add nocache headers. Default: false.
         */
        if ( apply_filters( 'woocommerce_enable_nocache_headers', false ) ) {
            $set_cache = true;
        }

        /**
         * Enabled for Google weblight.
         *
         * @see https://support.google.com/webmasters/answer/1061943?hl=en
         */
        if ( false !== strpos( $agent, 'googleweblight' ) ) {
            // no-transform: Opt-out of Google weblight. https://support.google.com/webmasters/answer/6211428?hl=en.
            $set_cache = true;
        }

        if ( false !== strpos( $agent, 'Chrome' ) && isset( $wp_query ) ) {
            $set_cache = true;
        }

        if ( $set_cache ) {
            $headers['Cache-Control'] = 'no-transform, no-cache, no-store, must-revalidate';
        }
        return $headers;
    }

    /**
     * Add a transient to delete on shutdown.
     *
     * @since 3.6.0
     * @param string|array $keys Transient key or keys.
     */
    public static function queue_delete_transient( $keys ) {
        self::$delete_transients = array_unique(
            array_merge( is_array( $keys ) ? $keys : array( $keys ), self::$delete_transients ),
        );
    }

    /**
     * Transients that don't need to be cleaned right away can be deleted on shutdown to avoid repetition.
     *
     * @since 3.6.0
     */
    public static function delete_transients_on_shutdown() {
        if ( ! self::$delete_transients ) {
            return;
        }

        foreach ( self::$delete_transients as $key ) {
            delete_transient( $key );
        }
        self::$delete_transients = array();
    }

    /**
     * Get transient version.
     *
     * When using transients with unpredictable names, e.g. those containing an md5
     * hash in the name, we need a way to invalidate them all at once.
     *
     * When using default WP transients we're able to do this with a DB query to
     * delete transients manually.
     *
     * With external cache however, this isn't possible. Instead, this function is used
     * to append a unique string (based on time()) to each transient. When transients
     * are invalidated, the transient version will increment and data will be regenerated.
     *
     * Raised in issue https://github.com/woocommerce/woocommerce/issues/5777.
     * Adapted from ideas in http://tollmanz.com/invalidation-schemes/.
     *
     * @param  string  $group   Name for the group of transients we need to invalidate.
     * @param  boolean $refresh true to force a new version.
     * @return string transient version based on time(), 10 digits.
     */
    public static function get_transient_version( $group, $refresh = false ) {
        $transient_name  = $group . '-transient-version';
        $transient_value = get_transient( $transient_name );

        if ( false === $transient_value || true === $refresh ) {
            $transient_value = (string) time();

            set_transient( $transient_name, $transient_value );
        }

        return $transient_value;
    }

    /**
     * Set constants to prevent caching by some plugins.
     *
     * @param  mixed $return Value to return. Previously hooked into a filter.
     * @return mixed
     */
    public static function set_nocache_constants( $return = true ) {
        wc_maybe_define_constant( 'DONOTCACHEPAGE', true );
        wc_maybe_define_constant( 'DONOTCACHEOBJECT', true );
        wc_maybe_define_constant( 'DONOTCACHEDB', true );
        return $return;
    }

    /**
     * When the transient version increases, this is used to remove all past transients to avoid filling the DB.
     *
     * Note; this only works on transients appended with the transient version, and when object caching is not being used.
     *
     * @deprecated 3.6.0 Adjusted transient usage to include versions within the transient values, making this cleanup obsolete.
     * @since  2.3.10
     * @param string $version Version of the transient to remove.
     */
    public static function delete_version_transients( $version = '' ) {
        if ( wp_using_ext_object_cache() || ! $version ) {
            return;
        }

        global $wpdb;

        $limit = apply_filters( 'woocommerce_delete_version_transients_limit', 1000 );

        if ( ! $limit ) {
            return;
        }

        $affected = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d;",
                '\_transient\_%' . $version,
                $limit,
            ),
        ); // WPCS: cache ok, db call ok.

        // If affected rows is equal to limit, there are more rows to delete. Delete in 30 secs.
        if ( $affected !== $limit ) {
            return;
        }

        wp_schedule_single_event( time() + 30, 'delete_version_transients', array( $version ) );
    }
}
