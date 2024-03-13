<?php //phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
/**
 * Data_Store class file
 *
 * @package    eXtended WooCommerce
 * @subpackage Data Type
 */

namespace XWC;

use WP_Error;

/**
 * Data store for Custom Types
 */
abstract class Data_Store_CT extends \WC_Data_Store_WP implements Interfaces\Data_Repository {
    use Traits\Data_Type_Meta;

    /**
     * Table name
     *
     * @var string
     */
    protected string $table;

    /**
     * By default, the table prefix is null.
     * This means that it will be set by default to the base `WC_Data_Store_WP` prefix which is `woocommerce_`
     *
     * Change this if you want to have a different prefix for your table. For instance `wc_`
     *
     * @var string|null
     */
    protected ?string $table_prefix = null;

    /**
     * ID field for the table
     *
     * @var string
     */
    protected string $id_field;

    /**
     * Meta keys to props array
     *
     * @var array<string, string>
     */
    protected $meta_key_to_props = array();

    /**
     * Term props array
     *
     * @var array<string, string>
     */
    protected array|false $term_props;

    /**
     * Array of updated props
     *
     * @var array<int, string>
     */
	protected array $updated_props = array();

    /**
     * Lookup table data keys
     *
     * @var array<int, string>
     */
	protected array $lookup_data_keys = array();

    /**
     * Query vars
     *
     * @var array<string, array>
     */
    protected array $query_vars;

    /**
     * Get the data type for the data store.
     *
     * @return string
     */
    abstract protected function get_data_type();

    /**
     * Class constructor.
     *
     * Gets the needed data type metadata and populates the class properties.
     */
    public function __construct() {
        $this->data_type = $this->get_data_type();

        $this->init_metadata();
    }

    // phpcs:ignore Squiz.Commenting
    protected function get_metadata_keys(): array {
        return array(
            'id_field',
            'table',
            'table_prefix',
            'meta_type',
            'internal_meta_keys',
            'must_exist_meta_keys',
            'meta_key_to_props',
            'term_props',
            'query_vars',
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param Data $data Data object.
     */
	public function create( &$data ) {
		global $wpdb;

		if ( $data->has_created_prop() && ! $data->get_date_created( 'edit' ) ) {
			$data->set_date_created( \time() );
		}

		$wpdb->insert( $this->table, $data->get_core_data( 'db' ) );

		if ( ! $wpdb->insert_id ) {
			return;
		}

		$data->set_id( $wpdb->insert_id );

		$this->update_entity_meta( $data, true );
		$this->update_terms( $data, true );
		$this->handle_updated_props( $data );
		$this->clear_caches( $data );

        $data->save_meta_data();
        $data->apply_changes();

        // Documented in `WC_Data_Store_WP`.
        \do_action( 'woocommerce_new_' . $this->get_data_type(), $data->get_id(), $data );
	}

    /**
     * {@inheritDoc}
     *
     * @param  Data $data   Package object.
     *
     * @throws \Exception If invalid Entity.
     */
    public function read( &$data ) {
        $data->set_defaults();

        $data_row = match ( $data->source ) {
            'row'   => true,
            'cache' => $this->check_cache( $data->get_id() ),
            default => $this->load_data_row( $data->get_id() ),
        };

        if ( ! $data->get_id() || ! $data_row ) {
            throw new \Exception( 'Invalid Entity' );
        }

        \is_array( $data_row ) && $data->set_props( (array) $data_row );

        $this->read_entity_data( $data );
        $this->read_extra_data( $data );

        $data->set_object_read( true );

        // Documented in `WC_Data_Store_WP`.
        \do_action( "woocommerce_{$this->get_data_type()}_read", $data->get_id() );
    }

    /**
     * Check if cached ID still exists.
     *
     * @param  int $id ID to check.
     * @return bool     True if exists, false if not.
     */
    protected function check_cache( int $id ): bool {
        global $wpdb;
        return 1 === (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE %i = %d',
                $this->table,
                $this->id_field,
                $id,
            ),
        );
    }

    /**
     * Load a data row from the database.
     *
     * @param  int $id ID to load.
     * @return array|false
     */
    protected function load_data_row( int $id ): array|false {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE %i = %d',
                $this->table,
                $this->id_field,
                $id,
            ),
            ARRAY_A,
        ) ?: false; //phpcs:ignore Universal.Operators
    }

    /**
     * {@inheritDoc}
     *
     * @param  Data $data Data Object.
     */
    public function update( &$data ) {
        global $wpdb;

        $changes = $data->get_changes();
        $ch_keys = \array_intersect( \array_keys( $changes ), $data->get_core_data_keys() );

        $core_data = \count( $ch_keys ) > 0
            ? \array_merge( $data->get_core_data( 'db' ), $this->get_date_modified_prop( $data ) )
            : array();

        if ( \count( $core_data ) > 0 ) {
            $wpdb->update(
                $this->table,
                $core_data,
                array( $this->id_field => $data->get_id() ),
            );
        }

        $this->update_entity_meta( $data );
        $this->update_terms( $data );
        $this->handle_updated_props( $data );
        $this->clear_caches( $data );

        $data->save_meta_data();
        $data->apply_changes();

        // Documented in `WC_Data_Store_WP`.
        \do_action( 'woocommerce_update_' . $this->get_data_type(), $data->get_id(), $data );
    }

    /**
     * Get the date modified core data (if it exists).
     *
     * @param  Data $data    Data object.
     * @return array                  Array of props.
     */
    protected function get_date_modified_prop( Data &$data ): array {
        $props = array();

        if ( ! $data->has_modified_prop() ) {
            return $props;
        }

        $props['date_modified'] = $data->get_date_modified( 'db' ) ?? \current_time( 'mysql' );

        if ( $data->has_modified_prop( true ) ) {
            $props['date_modified_gmt'] = $data->get_date_modified_gmt( 'db' ) ?? \current_time( 'mysql', 1 );
        }

        return $props;
    }

    /**
     * {@inheritDoc}
     *
     * @param  Data  $data Data object.
     * @param  array $args Array of args to pass to delete method.
     */
    public function delete( &$data, $args = array() ) {
        global $wpdb;

        $id = $data->get_id();

        $args = \wp_parse_args( $args, array( 'force' => false ) );

        if ( ! $id ) {
            return;
        }

        if ( ! $args['force_delete'] ) {
            return;
        }

        //phpcs:ignore WooCommerce.Commenting
        \do_action( 'woocommerce_before_delete_' . $this->get_data_type(), $id, $args );

        $wpdb->delete( $this->table, array( $this->id_field => $data->get_id() ) );
        $data->set_id( 0 );

        $this->delete_entity_meta( $id );

        //phpcs:ignore WooCommerce.Commenting
        \do_action( 'woocommerce_delete_' . $this->get_data_type(), $id, $data, $args );
    }

    //phpcs:ignore Squiz.Commenting
    public function query( array $query_vars ): array {
        $query = $this->get_query( $query_vars );

        if ( \is_wp_error( $query ) ) {
            $query = (object) array(
                'objects' => array(),
                'pages'   => 0,
                'total'   => 0,
            );
        }

        if ( ! ( $query_vars['paginate'] ?? false ) ) {
            return $query->objects;
        }

        return array(
            'objects' => $query->objects,
            'pages'   => $query->pages,
            'total'   => $query->total,
        );
    }

    //phpcs:ignore Squiz.Commenting
    public function count( array $query_vars ): int {
        $query = $this->get_query( $query_vars, false );

        if ( \is_wp_error( $query ) ) {
            return 0;
        }

        return $query->total_objects();
    }

    /**
     * Get the underlying data query object.
     *
     * @param  array $qv   Query vars.
     * @param  bool  $init Initialize the query.
     *
     * @return Data_Query|\WP_Error
     */
    protected function get_query( array $qv, bool $init = true ): Data_Query|\WP_Error {
        if ( ( $qv['data_type'] ?? '' ) !== $this->get_data_type() ) {
            return new \WP_Error( 'invalid_data_type', 'Invalid data type' );
        }

        $qv = $this->parse_query_vars( $qv );

        return new Data_Query( $qv, $init );
    }

    /**
     * Parses the `Object_Query` args to `Data_Query` args
     *
     * @param  array $qv Query vars.
     * @return array
     */
    protected function parse_query_vars( array $qv ): array {
        $qv = $this->parse_object_vars( $qv );
        $qv = $this->parse_standard_vars( $qv );

        // Documented in WooCommerce.
        return \apply_filters( "xwc_{$this->data_type}_get_objects_query", $qv );
    }

    /**
     * Parses the object specific query vars to the generic query vars.
     *
     * @param  array $qv Query vars.
     * @return array     Parsed query vars.
     */
    protected function parse_object_vars( array $qv ): array {
        $addon = array(
            'meta_query' => array(), //phpcs:ignore WordPress.DB
            'tax_query'  => array(), //phpcs:ignore WordPress.DB
        );

        foreach ( \array_intersect_key( $this->query_vars, $qv ) as $prop => $data ) {
            $value = $qv[ $prop ];
            unset( $qv[ $prop ] );

            if ( 'column' === $data['type'] ) {
                $qv[ $data['var'] ] = $value;
                continue;
            }

            $key = 'tax' === $data['type'] ? 'taxonomy' : 'key';

            $addon[ $data['type'] . '_query' ] = array(
                $key    => $data['var'],
                'value' => $value,
            );
        }

        return \array_merge( $qv, \array_filter( $addon ) );
    }

    /**
     * Parses the standard query vars to the generic query vars.
     *
     * @param  array $qv Query vars.
     * @return array
     */
    protected function parse_standard_vars( array $qv ): array {
        $map = array(
            'limit'  => 'per_page',
            'return' => 'fields',
        );

        foreach ( $map as $from => $to ) {
            if ( ! isset( $qv[ $from ] ) ) {
                continue;
            }

            $qv[ $to ] = $qv[ $from ];
            unset( $qv[ $from ] );
        }

        return $qv;
    }

    /**
     * Get the lookup table for the data store.
     *
     * @return string|null Database table name.
     */
    protected function get_lookup_table(): ?string {
        return null;
    }

    /**
	 * Get and store terms from a taxonomy.
	 *
	 * @param  WC_Data|integer $obj      WC_Data object or object ID.
	 * @param  string          $taxonomy Taxonomy name e.g. product_cat.
	 * @return array of terms
	 */
	protected function get_term_ids( $obj, $taxonomy ) {
		$object_id = \is_numeric( $obj ) ? $obj : $obj->get_id();
		$terms     = \wp_get_object_terms( $object_id, $taxonomy );
		if ( false === $terms || \is_wp_error( $terms ) ) {
			return array();
		}
		return \wp_list_pluck( $terms, 'term_id' );
	}

    /**
     * Reads the entity data from the database.
     *
     * @param Data $data Object.
     * @since 2.0.1
     */
    protected function read_entity_data( &$data ) {
        $object_id = $data->get_id();

        $meta_values = \array_map(
            static fn( $mv ) => \maybe_unserialize( $mv[0] ),
            \array_intersect_key(
                \get_metadata( $this->meta_type, $object_id ),
                $this->meta_key_to_props,
            ),
        );
        $meta_values = \array_combine(
            \array_map( fn( $v ) => $this->meta_key_to_props[ $v ], \array_keys( $meta_values ) ),
            \array_values( $meta_values ),
        );

        $set_props = \array_merge(
            $meta_values,
            $this->read_entity_terms( $object_id ),
        );

        $data->set_props( $set_props );
    }

    /**
     * Read the entity terms from the database.
     *
     * @param  int $object_id Object ID.
     * @return array<string, array<int, string>>
     */
    protected function read_entity_terms( int $object_id ): array {
        if ( ! $this->term_props ) {
            return array();
        }

        $props = array();

        foreach ( $this->term_props as $term_prop => $taxonomy ) {
            $props[ $term_prop ] = $this->get_term_ids( $object_id, $taxonomy );
        }

        return $props;
    }

    /**
     * Reads the extra entity Data
     *
     * @param  Data $data Data object.
     */
    protected function read_extra_data( &$data ) {
        foreach ( $data->get_extra_data_keys() as $key ) {
            try {
                $data->{"set_{$key}"}(
                    \get_metadata(
                        $this->meta_type,
                        $data->get_id(),
                        '_' . $key,
                        true,
                    )
                );
            } catch ( \Exception ) {
                continue;
            }
        }
    }

    /**
     * Update the entity meta in the DB.
     *
     * We first update the meta data defined as prop data, then we update the extra data.
     * Extra data is data that is not considered meta, but is stored in the meta table.
     *
     * @param  Data $data  Data object.
     * @param  bool $force Force update.
     */
	protected function update_entity_meta( &$data, $force = false ) {
		$this->update_meta_data( $data, $force );

        $props = \array_filter(
            \array_intersect(
                $data->get_extra_data_keys(),
                \array_keys( $data->get_changes() ),
            ),
            fn( $p ) => ! \in_array( $p, $this->updated_props, true )
        );

        if ( \count( $props ) <= 0 ) {
            return;
        }

        $this->update_extra_data( $data, $props );
	}

    /**
     * Update meta data.
     *
     * @param  Data $data  Data object.
     * @param  bool $force Force update.
     */
    protected function update_meta_data( Data &$data, bool $force ) {
        $to_update = $force
            ? $this->meta_key_to_props
            : $this->get_props_to_update( $data, $this->meta_key_to_props, $this->meta_type );

		foreach ( $to_update as $meta_key => $prop ) {
            $this->update_meta_prop( $data, $meta_key, $prop );
		}
    }

    /**
     * Update extra data.
     *
     * @param  Data     $data  Data object.
     * @param  string[] $props Extra data props.
     */
    protected function update_extra_data( Data &$data, array $props ) {
		foreach ( $props as $prop ) {
			$meta_key = '_' . $prop;

			try {
                $this->update_meta_prop( $data, $meta_key, $prop );
			} catch ( \Exception ) {
				continue;
			}
		}
    }

    /**
     * Updates a meta prop.
     *
     * @param  Data   $data     Data object.
     * @param  string $meta_key Meta key.
     * @param  string $prop     Property.
     */
    protected function update_meta_prop( &$data, $meta_key, $prop ) {
        $value = $data->{"get_$prop"}( 'db' );
        $value = \is_string( $value ) ? \wp_slash( $value ) : $value;

        if ( ! $this->update_or_delete_entity_meta( $data, $meta_key, $value ) ) {
            return;
        }

        $this->updated_props[] = $prop;
    }

    /**
     * For all stored terms in all taxonomies save them to the DB.
     *
     * @param WC_Data $the_object Data Object.
     * @param bool    $force  Force update. Used during create.
     */
    protected function update_terms( &$the_object, $force = false ) {
        if ( ! $this->term_props ) {
            return;
        }

        $props   = $this->term_props;
        $changes = \array_intersect_key( $the_object->get_changes(), $props );

        // If we don't have term props or there are no changes, and we're not forcing an update, return.
        if ( 0 === \count( $props ) || ( \count( $changes ) && ! $force ) ) {
            return;
        }

        foreach ( $props as $term_prop => $taxonomy ) {
            $terms = \wc_string_to_array( $the_object->{"get_$term_prop"}( 'edit' ) );

            \wp_set_object_terms( $the_object->get_id(), $terms, $taxonomy, false );
        }
    }

    /**
     * Handle updated meta props after updating entity meta.
     *
     * @param  Data $data Data object.
     */
	protected function handle_updated_props( &$data ) {
		if ( \array_intersect( $this->updated_props, $this->lookup_data_keys ) && ! \is_null(
            $this->get_lookup_table(),
        ) ) {
            $this->update_lookup_table( $data->get_id(), $this->get_lookup_table() );
		}

        $this->updated_props = array();
	}

    /**
     * Should the meta data exist?
     *
     * @param  string $meta_key   Meta key.
     * @param  mixed  $meta_value Meta value.
     * @return bool
     */
    protected function meta_must_exist( $meta_key, $meta_value ): bool {
        return \in_array( $meta_value, array( array(), '' ), true )
                &&
                ! \in_array( $meta_key, $this->must_exist_meta_keys, true );
    }

    /**
     * Updates or deletes entity meta data
     *
     * @param  WC_Data $obj   Object.
     * @param  string  $key   Meta key.
     * @param  string  $value Meta value.
     * @return bool           True if updated, false if not.
     */
    protected function update_or_delete_entity_meta( $obj, $key, $value ) {
        $updated = $this->meta_must_exist( $key, $value )
            ? \delete_metadata( $this->meta_type, $obj->get_id(), $key )
            : \update_metadata( $this->meta_type, $obj->get_id(), $key, $value );

        return (bool) $updated;
    }

    /**
     * Delete metadata for a given object.
     *
     * @param  int $object_id Object ID.
     */
    protected function delete_entity_meta( int $object_id ) {
        if ( ! $this->meta_type ) {
            return;
        }

        $GLOBALS['wpdb']->delete(
            \_get_meta_table( $this->meta_type ),
            array(
                "{$this->meta_type}_id" => $object_id,
            ),
        );
    }

    /**
	 * Table structure is slightly different between meta types, this function will return what we need to know.
	 *
	 * @since  3.0.0
	 * @return array Array elements: table, object_id_field, meta_id_field
	 */
	protected function get_db_info() {
		global $wpdb;

		$meta_id_field = 'meta_id'; // for some reason users calls this umeta_id so we need to track this as well.
		$table         = $wpdb->prefix;

		// If we are dealing with a type of metadata that is not a core type, the table should be prefixed.
		if ( ! \in_array( $this->meta_type, array( 'post', 'user', 'comment', 'term' ), true ) ) {
			$table .= $this->table_prefix ?? 'woocommerce_';
		}

		$table          .= $this->meta_type . 'meta';
		$object_id_field = $this->meta_type . '_id';

		// Figure out our field names.
		if ( 'user' === $this->meta_type ) {
			$meta_id_field = 'umeta_id';
			$table         = $wpdb->usermeta;
		}

		if ( '' !== $this->object_id_field_for_meta ) {
			$object_id_field = $this->object_id_field_for_meta;
		}

		return array(
            'meta_id_field'   => $meta_id_field,
            'object_id_field' => $object_id_field,
            'table'           => $table,
		);
	}

    /**
     * Clear caches.
     *
     * @param  Data $data Data object.
     */
	protected function clear_caches( &$data ) {
		\WC_Cache_Helper::invalidate_cache_group( $this->get_data_type() . '_' . $data->get_id() );
	}
}
