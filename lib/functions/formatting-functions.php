<?php // phpcs:disable

if ( ! function_exists( 'wc_stwc_string_to_boolring_to_array' ) ) :
/**
 * Converts a string (e.g. 'yes' or 'no') to a bool.
 *
 * @since 3.0.0
 * @param string|bool $string String to convert. If a bool is passed it will be returned as-is.
 * @return bool
 */
function wc_string_to_bool( $string ) {
	$string = $string ?? '';
	return is_bool( $string ) ? $string : ( 'yes' === strtolower( $string ) || 1 === $string || 'true' === strtolower( $string ) || '1' === $string );
}
endif;

if ( ! function_exists( 'wc_bool_to_string' ) ) :

/**
 * Converts a bool to a 'yes' or 'no'.
 *
 * @since 3.0.0
 * @param bool|string $bool Bool to convert. If a string is passed it will first be converted to a bool.
 * @return string
 */
function wc_bool_to_string( $bool ) {
	if ( ! is_bool( $bool ) ) {
		$bool = wc_string_to_bool( $bool );
	}
	return true === $bool ? 'yes' : 'no';
}

endif;

if ( ! function_exists( 'wc_string_to_array' ) ) :

/**
 * Explode a string into an array by $delimiter and remove empty values.
 *
 * @since 3.0.0
 * @param string $string    String to convert.
 * @param string $delimiter Delimiter, defaults to ','.
 * @return array
 */
function wc_string_to_array( $string, $delimiter = ',' ) {
	$string = $string ?? '';
	return is_array( $string ) ? $string : array_filter( explode( $delimiter, $string ) );
}

endif;

if ( ! function_exists( 'wc_strtolower' ) ) :

/**
 * Make a string lowercase.
 * Try to use mb_strtolower() when available.
 *
 * @since  2.3
 * @param  string $string String to format.
 * @return string
 */
function wc_strtolower( $string ) {
	$string = $string ?? '';
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $string ) : strtolower( $string );
}

endif;

if ( ! function_exists( 'wc_string_to_timestamp' ) ) :

/**
 * Convert mysql datetime to PHP timestamp, forcing UTC. Wrapper for strtotime.
 *
 * Based on wcs_strtotime_dark_knight() from WC Subscriptions by Prospress.
 *
 * @since  3.0.0
 * @param  string   $time_string    Time string.
 * @param  int|null $from_timestamp Timestamp to convert from.
 * @return int
 */
function wc_string_to_timestamp( $time_string, $from_timestamp = null ) {
    $time_string ??= '';

    $original_timezone = date_default_timezone_get();

	date_default_timezone_set( 'UTC' );

	if ( null === $from_timestamp ) {
		$next_timestamp = strtotime( $time_string );
	} else {
		$next_timestamp = strtotime( $time_string, $from_timestamp );
	}

	date_default_timezone_set( $original_timezone );

    return $next_timestamp;
}

endif;

if ( ! function_exists( 'wc_string_to_datetime' ) ) :

/**
 * Convert a date string to a WC_DateTime.
 *
 * @since  3.1.0
 * @param  string $time_string Time string.
 * @return WC_DateTime
 */
function wc_string_to_datetime( $time_string ) {
    $time_string ??= '';

    // Strings are defined in local WP timezone. Convert to UTC.
    if (1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|((-|\+)\d{2}:\d{2}))$/', $time_string, $date_bits  )) {
        $offset    = ! empty( $date_bits[7] ) ? iso8601_timezone_to_offset(
            $date_bits[7],
        ) : wc_timezone_offset();
        $timestamp = gmmktime(
            $date_bits[4],
            $date_bits[5],
            $date_bits[6],
            $date_bits[2],
            $date_bits[3],
            $date_bits[1],
        ) - $offset;
    } else {
        $timestamp = wc_string_to_timestamp(
            get_gmt_from_date( gmdate( 'Y-m-d H:i:s', wc_string_to_timestamp( $time_string ) ) ),
        );
    }
    $datetime = new WC_DateTime( "@{$timestamp}", new DateTimeZone( 'UTC' ) );

    // Set local timezone or offset.
    if ( get_option( 'timezone_string' ) ) {
        $datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
    } else {
        $datetime->set_utc_offset( wc_timezone_offset() );
    }

    return $datetime;
}

endif;

if ( ! function_exists( 'wc_timezone_string' ) ) :

/**
 * WooCommerce Timezone - helper to retrieve the timezone string for a site until.
 * a WP core method exists (see https://core.trac.wordpress.org/ticket/24730).
 *
 * Adapted from https://secure.php.net/manual/en/function.timezone-name-from-abbr.php#89155.
 *
 * @since 2.1
 * @return string PHP timezone string for the site
 */
function wc_timezone_string() {
    // Added in WordPress 5.3 Ref https://developer.wordpress.org/reference/functions/wp_timezone_string/.
    if ( function_exists( 'wp_timezone_string' ) ) {
        return wp_timezone_string();
    }

    // If site timezone string exists, return it.
    $timezone = get_option( 'timezone_string' );
    if ( $timezone ) {
        return $timezone;
    }

    // Get UTC offset, if it isn't set then return UTC.
    $utc_offset = floatval( get_option( 'gmt_offset', 0 ) );
    if ( ! is_numeric( $utc_offset ) || 0.0 === $utc_offset ) {
        return 'UTC';
    }

    // Adjust UTC offset from hours to seconds.
    $utc_offset = (int) ( $utc_offset * 3600 );

    // Attempt to guess the timezone string from the UTC offset.
    $timezone = timezone_name_from_abbr( '', $utc_offset );
    if ( $timezone ) {
        return $timezone;
    }

    // Last try, guess timezone string manually.
    foreach ( timezone_abbreviations_list() as $abbr ) {
        foreach ( $abbr as $city ) {
            // WordPress restrict the use of date(), since it's affected by timezone settings, but in this case is just what we need to guess the correct timezone.
            if (  (bool) date( 'I' ) === (bool) $city['dst'] && $city['timezone_id'] && intval( $city['offset'] ) === $utc_offset ) {
                return $city['timezone_id'];
            }
        }
    }

    // Fallback to UTC.
    return 'UTC';
}

endif;

if ( ! function_exists( 'wc_timezone_offset' ) ) :

/**
 * Get timezone offset in seconds.
 *
 * @since  3.0.0
 * @return float
 */
function wc_timezone_offset() {
    $timezone = get_option( 'timezone_string' );

    if ( $timezone ) {
        $timezone_object = new DateTimeZone( $timezone );
        return $timezone_object->getOffset( new DateTime( 'now' ) );
    }

    return floatval( get_option( 'gmt_offset', 0 ) ) * HOUR_IN_SECONDS;
}

endif;
