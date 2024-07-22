<?php // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
/**
 * Meta_Repository class file.
 */

/**
 * Implements functions similar to WP's add_metadata(), get_metadata(), and friends using a custom table.
 *
 * Copied from WooCommerce.
 *
 * @see WC_Data_Store_WP For an implementation using WP's metadata functions and tables.
 * @see `\Automattic\WooCommerce\Internal\DataStores\CustomMetaDataStore` for original WooCommerce implementation.
 *
 * @template T of XWC_Data
 */
abstract class XWC_Meta_Store {
	/**
	 * Returns the name of the table used for storage.
	 *
	 * @return string
	 */
	abstract protected function get_table_name();

	/**
	 * Returns the name of the field/column used for identifiying metadata entries.
	 *
	 * @return string
	 */
	protected function get_meta_id_field() {
		return 'id';
	}

	/**
	 * Returns the name of the field/column used for associating meta with objects.
	 *
	 * @return string
	 */
	protected function get_object_id_field() {
		return 'object_id';
	}

	/**
	 * Describes the structure of the metadata table.
	 *
	 * @return array Array elements: table, object_id_field, meta_id_field.
	 */
	protected function get_db_info() {
		return array(
			'tbl' => $this->get_table_name(),
			'mid' => $this->get_meta_id_field(),
			'oid' => $this->get_object_id_field(),
		);
	}

    /**
     * Get the meta which is considered as object data
     *
     * @param  T     $data
     * @param  array $keys_to_props
     * @return array
     */
    public function read_meta_props( &$data, array $keys_to_props ): array {
        $meta  = $this->read_meta( $data );
        $props = array();

        foreach ( $meta as $row ) {
            if ( ! isset( $keys_to_props[ $row->meta_key ] ) ) {
                continue;
            }

            $prop           = $keys_to_props[ $row->meta_key ];
            $props[ $prop ] = \maybe_unserialize( $row->meta_value );
        }

        return $props;
    }

	/**
	 * Returns an array of meta for an object.
	 *
	 * @param  T $data XWC_Data object.
	 * @return array
	 */
	public function read_meta( &$data ) {
		global $wpdb;

		$db = $this->get_db_info();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$raw_meta_data = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT %i AS meta_id, meta_key, meta_value FROM %i WHERE %i = %d ORDER BY meta_id',
                $db['mid'],
                $db['tbl'],
                $db['oid'],
				$data->get_id(),
			),
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $raw_meta_data;
	}

	/**
	 * Deletes meta based on meta ID.
	 *
	 * @param  T $data XWC_Data object.
	 * @param  stdClass   $meta (containing at least ->id).
	 *
	 * @return bool
	 */
	public function delete_meta( &$data, $meta ): bool {
		global $wpdb;

		if ( ! isset( $meta->id ) ) {
			return false;
		}

		$db      = $this->get_db_info();
		$meta_id = \absint( $meta->id );

		return (bool) $wpdb->delete( $db['tbl'], array( $db['mid'] => $meta_id ) );
	}

    /**
	 * Deletes all meta for an object.
	 *
	 * @param  T $data \XWC_Data object.
     * @return bool
     */
    public function delete_all_meta( &$data ): bool {
        global $wpdb;

        $db = $this->get_db_info();

        return (bool) $wpdb->delete( $db['tbl'], array( $db['oid'] => $data->get_id() ) );
    }

	/**
	 * Add new piece of meta.
	 *
	 * @param  T        $data XWC_Data object.
	 * @param  stdClass $meta (containing ->key and ->value).
	 *
	 * @return int|false meta ID
	 */
	public function add_meta( &$data, $meta ) {
		global $wpdb;

		$db = $this->get_db_info();

		$object_id  = $data->get_id();
		$meta_key   = \wp_unslash( \wp_slash( $meta->key ) );
		$meta_value = \maybe_serialize(
            \is_string( $meta->value ) ? \wp_unslash( \wp_slash( $meta->value ) ) : $meta->value,
        );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$result = $wpdb->insert(
			$db['tbl'],
			array(
				$db['oid']   => $object_id,
				'meta_key'   => $meta_key,
				'meta_value' => $meta_value,
			),
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_meta_key

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update meta.
	 *
	 * @param  T        $data XWC_Data object.
	 * @param  stdClass $meta (containing ->id, ->key and ->value).
	 *
	 * @return bool
	 */
	public function update_meta( &$data, $meta ): bool {
		global $wpdb;

		if ( ! isset( $meta->id ) || ! $meta->key ) {
			return false;
		}

		$db = $this->get_db_info();

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$result = $wpdb->update(
			$db['tbl'],
			array(
                'meta_key'   => $meta->key,
                'meta_value' => \maybe_serialize( $meta->value ),
            ),
			array( $db['mid'] => $meta->id ),
			'%s',
			'%d',
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_meta_key

		return 1 === $result;
	}

    public function update_meta_props( &$data, array $props_to_keys ) {
        foreach ( $props_to_keys as $prop => $key ) {
            $id = $this->get_meta_id_by_key( $data, $key );

            $cb = $id > 0 ? 'update_meta' : 'add_meta';

            $this->$cb(
                $data,
                (object) array(
					'id'    => $id,
					'key'   => $key,
					'value' => $data->{"get_$prop"}( 'db' ),
                ),
            );
        }
    }

    public function get_meta_id_by_key( &$data, string $key ): int {
        //phpcs:ignore Universal.Operators
        $db_meta = ( $this->get_metadata_by_key( $data, $key ) ?: array() )[0] ?? null;
        $idf     = $this->get_db_info()['mid'];

        return $db_meta?->$idf ?? 0;
    }

	/**
	 * Retrieves metadata by meta ID.
	 *
	 * @param int $meta_id Meta ID.
	 * @return object|bool Metadata object or FALSE if not found.
	 */
	public function get_metadata_by_id( $meta_id ) {
		global $wpdb;

        if ( ! \is_int( $meta_id ) || $meta_id <= 0 ) {
            return false;
        }

        $db      = $this->get_db_info();
		$meta_id = \absint( $meta_id );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$meta = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT %i, `meta_key`, `meta_value`, %i FROM %i WHERE %i = %d',
                $db['mid'],
				$db['oid'],
				$db['tbl'],
                $db['mid'],
				$meta_id,
			),
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $meta ) {
			return false;
		}

		if ( isset( $meta->meta_value ) ) {
			$meta->meta_value = \maybe_unserialize( $meta->meta_value );
		}

		return $meta;
	}

	/**
	 * Retrieves metadata by meta key.
	 *
	 * @param T      $data     Object ID.
	 * @param string $meta_key Meta key.
	 *
	 * @return \stdClass|bool Metadata object or FALSE if not found.
	 */
	public function get_metadata_by_key( &$data, string $meta_key ) {
		global $wpdb;

		$db = $this->get_db_info();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$meta = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT %i, `meta_key`, `meta_value`, %i FROM %i WHERE meta_key = %s AND %i = %d',
                $db['mid'],
                $db['oid'],
                $db['tbl'],
				$meta_key,
                $db['oid'],
				$data->get_id(),
			),
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $meta ) {
			return false;
		}

		foreach ( $meta as $row ) {
			if ( ! isset( $row->meta_value ) ) {
                continue;
            }

            $row->meta_value = \maybe_unserialize( $row->meta_value );
		}

		return $meta;
	}

	/**
	 * Returns distinct meta keys in use.
	 *
	 * @since 8.8.0
	 *
	 * @param int    $limit           Maximum number of meta keys to return. Defaults to 100.
	 * @param string $order           Order to use for the results. Either 'ASC' or 'DESC'. Defaults to 'ASC'.
	 * @param bool   $include_private Whether to include private meta keys in the results. Defaults to FALSE.
	 * @return string[]
	 */
	public function get_meta_keys( $limit = 100, $order = 'ASC', $include_private = false ) {
		global $wpdb;

		$db = $this->get_db_info();

		$query = $wpdb->prepare( 'SELECT DISTINCT meta_key FROM %i ', $db['tbl'] );

        $query .= "WHERE meta_key != '' ";

        if ( ! $include_private ) {
            $query .= $wpdb->prepare(
                "AND meta_key NOT BETWEEN '_' AND '_z' AND meta_key NOT LIKE %s ",
                $wpdb->esc_like( '_' ) . '%',
            );
        }

        $order = match ( \strtoupper( $order ) ) {
            'ASC'   => 'ASC',
            'DESC'  => 'DESC',
            default => 'ASC',
        };

		$query .= "ORDER BY meta_key {$order} ";

		if ( $limit ) {
			$query .= $wpdb->prepare( 'LIMIT %d ', $limit );
		}

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is prepared.
		return $wpdb->get_col( $query );
	}
}
